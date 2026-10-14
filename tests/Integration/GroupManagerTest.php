<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Auth\DbTokenStore;
use JardisSupport\Auth\PasswordHasher;
use JardisSupport\Auth\SessionManager;
use JardisSupport\Data\Identity;
use JardisSupport\Identity\Data\Event\GroupCreated;
use JardisSupport\Identity\Data\Event\GroupMemberAdded;
use JardisSupport\Identity\Data\Event\GroupMemberRemoved;
use JardisSupport\Identity\DbGroupMemberStore;
use JardisSupport\Identity\DbGroupStore;
use JardisSupport\Identity\DbMembershipStore;
use JardisSupport\Identity\DbTenantStore;
use JardisSupport\Identity\DbUserStore;
use JardisSupport\Identity\Exception\GroupMemberExistsException;
use JardisSupport\Identity\Exception\GroupNameTakenException;
use JardisSupport\Identity\Exception\GroupNotFoundException;
use JardisSupport\Identity\Exception\NotAMemberException;
use JardisSupport\Identity\GroupManager;
use JardisSupport\Identity\MembershipManager;
use JardisSupport\Identity\TenantManager;
use JardisSupport\Identity\Tests\Support\Connections;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use JardisSupport\Identity\UserManager;
use PHPUnit\Framework\TestCase;

final class GroupManagerTest extends TestCase
{
    private GroupManager $manager;
    private string $userId;
    private string $tenantId;
    private string $otherTenantId;

    protected function setUp(): void
    {
        $connection = Connections::sqlite();
        IdentityTables::recreate($connection);
        $ids = new Identity();
        $users = new DbUserStore($connection);
        $tenants = new DbTenantStore($connection);
        $memberships = new DbMembershipStore($connection);
        $groupMembers = new DbGroupMemberStore($connection);

        $this->manager = new GroupManager(new DbGroupStore($connection), $groupMembers, $memberships, $ids);

        $this->userId = (new UserManager(
            $users,
            PasswordHasher::argon2id(memoryCost: 8192, timeCost: 1),
            $ids,
            new SessionManager(new DbTokenStore($connection)),
        ))->register('max@example.test', 'secret-pw', 'Max')->user->getId();
        $tenantManager = new TenantManager($tenants, $ids);
        $this->tenantId = $tenantManager->create('ACME', 'acme')->tenant->getId();
        $this->otherTenantId = $tenantManager->create('Beta', 'beta')->tenant->getId();
        (new MembershipManager($memberships, $groupMembers, $users, $tenants))
            ->add($this->userId, $this->tenantId, 'admin');
    }

    public function testCreateStoresGroupAndEmitsEvent(): void
    {
        $result = $this->manager->create($this->tenantId, 'backend');

        self::assertSame('backend', $result->group->getName());
        self::assertSame([$result->group->getId()], array_map(
            static fn ($g) => $g->getId(),
            $this->manager->forTenant($this->tenantId),
        ));
        $event = $result->events[0];
        self::assertInstanceOf(GroupCreated::class, $event);
        self::assertSame([$result->group->getId(), $this->tenantId, 'backend'], [$event->groupId, $event->tenantId, $event->name]);
        self::assertSame('UTC', $event->timestamp->getTimezone()->getName());
    }

    public function testDuplicateNameIsRejectedPerTenantOnly(): void
    {
        $this->manager->create($this->tenantId, 'backend');
        $this->manager->create($this->otherTenantId, 'backend');

        $this->expectException(GroupNameTakenException::class);
        $this->manager->create($this->tenantId, 'backend');
    }

    public function testUnknownGroupIsRejected(): void
    {
        foreach ([
            fn () => $this->manager->addMember('nope', $this->userId),
            fn () => $this->manager->removeMember('nope', $this->userId),
            fn () => $this->manager->members('nope'),
        ] as $call) {
            try {
                $call();
                self::fail('GroupNotFoundException expected');
            } catch (GroupNotFoundException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testNonMemberOfTheGroupsTenantIsRejected(): void
    {
        $group = $this->manager->create($this->otherTenantId, 'backend')->group;

        $this->expectException(NotAMemberException::class);
        $this->manager->addMember($group->getId(), $this->userId);
    }

    public function testAddAndRemoveMemberEmitEventsAndChangeMembers(): void
    {
        $group = $this->manager->create($this->tenantId, 'backend')->group;

        $added = $this->manager->addMember($group->getId(), $this->userId);
        self::assertInstanceOf(GroupMemberAdded::class, $added);
        self::assertSame([$group->getId(), $this->userId], [$added->groupId, $added->userId]);
        self::assertSame([$this->userId], $this->manager->members($group->getId()));

        $removed = $this->manager->removeMember($group->getId(), $this->userId);
        self::assertInstanceOf(GroupMemberRemoved::class, $removed);
        self::assertSame([$group->getId(), $this->userId], [$removed->groupId, $removed->userId]);
        self::assertSame([], $this->manager->members($group->getId()));
    }

    public function testAddingTheSameMemberTwiceIsTranslated(): void
    {
        $group = $this->manager->create($this->tenantId, 'backend')->group;
        $this->manager->addMember($group->getId(), $this->userId);

        $this->expectException(GroupMemberExistsException::class);
        $this->manager->addMember($group->getId(), $this->userId);
    }
}
