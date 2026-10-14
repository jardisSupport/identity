<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Data\Identity;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Identity\MembershipInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Auth\DbTokenStore;
use JardisSupport\Auth\PasswordHasher;
use JardisSupport\Auth\SessionManager;
use JardisSupport\Identity\Data\Event\MembershipAdded;
use JardisSupport\Identity\Data\Event\MembershipRemoved;
use JardisSupport\Identity\Data\Event\MembershipRoleChanged;
use JardisSupport\Identity\DbGroupMemberStore;
use JardisSupport\Identity\DbGroupStore;
use JardisSupport\Identity\DbMembershipStore;
use JardisSupport\Identity\DbTenantStore;
use JardisSupport\Identity\DbUserStore;
use JardisSupport\Identity\Exception\MembershipExistsException;
use JardisSupport\Identity\Exception\NotAMemberException;
use JardisSupport\Identity\Exception\TenantNotFoundException;
use JardisSupport\Identity\Exception\UserNotFoundException;
use JardisSupport\Identity\GroupManager;
use JardisSupport\Identity\MembershipManager;
use JardisSupport\Identity\TenantManager;
use JardisSupport\Identity\Tests\Support\Connections;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use JardisSupport\Identity\UserManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MembershipManagerTest extends TestCase
{
    private DbConnectionInterface $connection;
    private MembershipManager $manager;
    private GroupManager $groups;
    private string $userId;
    private string $tenantId;

    protected function setUp(): void
    {
        $this->connection = Connections::sqlite();
        IdentityTables::recreate($this->connection);
        $ids = new Identity();
        $users = new DbUserStore($this->connection);
        $tenants = new DbTenantStore($this->connection);
        $memberships = new DbMembershipStore($this->connection);
        $groupMembers = new DbGroupMemberStore($this->connection);

        $this->manager = new MembershipManager($memberships, $groupMembers, $users, $tenants, $this->connection);
        $this->groups = new GroupManager(new DbGroupStore($this->connection), $groupMembers, $memberships, $ids);

        $userManager = new UserManager(
            $users,
            PasswordHasher::argon2id(memoryCost: 8192, timeCost: 1),
            $ids,
            new SessionManager(new DbTokenStore($this->connection)),
        );
        $this->userId = $userManager->register('max@example.test', 'secret-pw', 'Max')->user->getId();
        $this->tenantId = (new TenantManager($tenants, $ids))->create('ACME', 'acme')->tenant->getId();
    }

    public function testAddStoresMembershipAndEmitsEvent(): void
    {
        $result = $this->manager->add($this->userId, $this->tenantId, 'admin');

        self::assertSame('admin', $result->membership->getRole());
        self::assertCount(1, $this->manager->forUser($this->userId));
        self::assertCount(1, $this->manager->forTenant($this->tenantId));
        $event = $result->events[0];
        self::assertInstanceOf(MembershipAdded::class, $event);
        self::assertSame([$this->userId, $this->tenantId, 'admin'], [$event->userId, $event->tenantId, $event->role]);
        self::assertSame('UTC', $event->timestamp->getTimezone()->getName());
    }

    public function testDuplicateMembershipIsRejected(): void
    {
        $this->manager->add($this->userId, $this->tenantId, 'admin');

        $this->expectException(MembershipExistsException::class);
        $this->manager->add($this->userId, $this->tenantId, 'viewer');
    }

    public function testUnknownUserIsRejectedOnAdd(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->manager->add('nope', $this->tenantId, 'admin');
    }

    public function testUnknownTenantIsRejectedOnAdd(): void
    {
        $this->expectException(TenantNotFoundException::class);
        $this->manager->add($this->userId, 'nope', 'admin');
    }

    public function testChangeRoleUpdatesRoleAndEmitsEvent(): void
    {
        $this->manager->add($this->userId, $this->tenantId, 'admin');

        $result = $this->manager->changeRole($this->userId, $this->tenantId, 'viewer');

        self::assertSame('viewer', $result->membership->getRole());
        self::assertSame('viewer', $this->manager->forUser($this->userId)[0]->getRole());
        $event = $result->events[0];
        self::assertInstanceOf(MembershipRoleChanged::class, $event);
        self::assertSame('viewer', $event->role);
    }

    public function testChangeRoleOfNonMemberIsRejected(): void
    {
        $this->expectException(NotAMemberException::class);
        $this->manager->changeRole($this->userId, $this->tenantId, 'viewer');
    }

    public function testRemoveDeletesMembershipAndGroupMemberships(): void
    {
        $this->manager->add($this->userId, $this->tenantId, 'admin');
        $group = $this->groups->create($this->tenantId, 'backend')->group;
        $this->groups->addMember($group->getId(), $this->userId);

        $event = $this->manager->remove($this->userId, $this->tenantId);

        self::assertInstanceOf(MembershipRemoved::class, $event);
        self::assertSame([$this->userId, $this->tenantId], [$event->userId, $event->tenantId]);
        self::assertSame([], $this->manager->forUser($this->userId));
        self::assertSame([], $this->groups->members($group->getId()));
    }

    public function testRemoveOfNonMemberIsRejected(): void
    {
        $this->expectException(NotAMemberException::class);
        $this->manager->remove($this->userId, $this->tenantId);
    }

    public function testRemoveRollsBackGroupMembershipsWhenMembershipDeleteFails(): void
    {
        $memberships = new DbMembershipStore($this->connection);
        $groupMembers = new DbGroupMemberStore($this->connection);
        $failing = new class ($memberships) implements MembershipStoreInterface {
            public function __construct(private readonly MembershipStoreInterface $inner)
            {
            }

            public function store(MembershipInterface $membership): void
            {
                $this->inner->store($membership);
            }

            public function updateRole(string $userId, string $tenantId, string $role): void
            {
                $this->inner->updateRole($userId, $tenantId, $role);
            }

            public function remove(string $userId, string $tenantId): void
            {
                throw new RuntimeException('membership delete failed');
            }

            public function find(string $userId, string $tenantId): ?MembershipInterface
            {
                return $this->inner->find($userId, $tenantId);
            }

            public function listForUser(string $userId): array
            {
                return $this->inner->listForUser($userId);
            }

            public function listForTenant(string $tenantId): array
            {
                return $this->inner->listForTenant($tenantId);
            }
        };
        $manager = new MembershipManager(
            $failing,
            $groupMembers,
            new DbUserStore($this->connection),
            new DbTenantStore($this->connection),
            $this->connection,
        );
        $this->manager->add($this->userId, $this->tenantId, 'admin');
        $group = $this->groups->create($this->tenantId, 'backend')->group;
        $this->groups->addMember($group->getId(), $this->userId);

        try {
            $manager->remove($this->userId, $this->tenantId);
            self::fail('the failing store must abort remove()');
        } catch (RuntimeException) {
        }

        self::assertSame([$this->userId], $this->groups->members($group->getId()));
        self::assertCount(1, $this->manager->forUser($this->userId));
    }
}
