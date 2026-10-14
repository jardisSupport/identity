<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use InvalidArgumentException;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\Group;
use JardisSupport\Identity\DbGroupMemberStore;
use JardisSupport\Identity\DbGroupStore;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use JardisSupport\Identity\Tests\Support\Moment;
use PHPUnit\Framework\TestCase;

/** GroupMemberStoreInterface behaviour of DbGroupMemberStore against a real database (one subclass per driver). */
abstract class DbGroupMemberStoreTestCase extends TestCase
{
    protected DbConnectionInterface $connection;
    protected DbGroupMemberStore $store;

    abstract protected function createConnection(): DbConnectionInterface;

    protected function setUp(): void
    {
        $this->connection = $this->createConnection();
        IdentityTables::recreate($this->connection);
        $this->store = new DbGroupMemberStore($this->connection);
    }

    // (1) roundtrip: add -> isMember / listMembers -> remove
    public function testAddListIsMemberAndRemove(): void
    {
        $this->store->add('g-1', 'u-1');
        $this->store->add('g-1', 'u-2');
        $this->store->add('g-2', 'u-1');

        self::assertTrue($this->store->isMember('g-1', 'u-1'));
        self::assertSame(['u-1', 'u-2'], $this->store->listMembers('g-1'));

        $this->store->remove('g-1', 'u-1');

        self::assertFalse($this->store->isMember('g-1', 'u-1'));
        self::assertSame(['u-2'], $this->store->listMembers('g-1'));
        self::assertTrue($this->store->isMember('g-2', 'u-1'));
    }

    public function testStoredJoinMomentIsUtcWithMicroseconds(): void
    {
        $this->store->add('g-1', 'u-1');

        $stored = (string) $this->connection->pdo()
            ->query('SELECT created_at FROM identity_group_members')->fetchColumn();

        self::assertSame(
            substr(Moment::utcText(new \DateTimeImmutable('now')), 0, 10),
            substr($stored, 0, 10),
        );
    }

    // (2) unknown -> empty / false
    public function testUnknownReturnsEmptyOrFalse(): void
    {
        self::assertSame([], $this->store->listMembers('missing'));
        self::assertFalse($this->store->isMember('missing', 'u-1'));
        $this->store->remove('missing', 'u-1');
    }

    // (3) duplicate -> UniqueViolationException
    public function testDuplicateMemberThrowsUniqueViolation(): void
    {
        $this->store->add('g-1', 'u-1');

        $this->expectException(UniqueViolationException::class);

        $this->store->add('g-1', 'u-1');
    }

    // (4) custom prefix
    public function testCustomPrefix(): void
    {
        IdentityTables::recreate($this->connection, 'acme_');
        $store = new DbGroupMemberStore($this->connection, 'acme_');

        $store->add('g-1', 'u-1');

        self::assertTrue($store->isMember('g-1', 'u-1'));
        self::assertFalse($this->store->isMember('g-1', 'u-1'));
    }

    // (5) invalid prefix
    public function testInvalidPrefixIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DbGroupMemberStore($this->connection, 'bad prefix');
    }

    // (6) removeUserFromTenantGroups touches only the groups of that tenant (and only that user)
    public function testRemoveUserFromTenantGroupsRemovesOnlyGroupsOfThatTenant(): void
    {
        $groups = new DbGroupStore($this->connection);
        $groups->store(new Group('g-a1', 't-a', 'One', Moment::fixed()));
        $groups->store(new Group('g-a2', 't-a', 'Two', Moment::fixed()));
        $groups->store(new Group('g-b1', 't-b', 'One', Moment::fixed()));
        foreach (['g-a1', 'g-a2', 'g-b1'] as $groupId) {
            $this->store->add($groupId, 'u-1');
        }
        $this->store->add('g-a1', 'u-2');

        $this->store->removeUserFromTenantGroups('u-1', 't-a');

        self::assertFalse($this->store->isMember('g-a1', 'u-1'));
        self::assertFalse($this->store->isMember('g-a2', 'u-1'));
        self::assertTrue($this->store->isMember('g-b1', 'u-1'));
        self::assertTrue($this->store->isMember('g-a1', 'u-2'));
    }

    public function testRemoveUserFromTenantGroupsWithoutGroupsIsNoOp(): void
    {
        $this->store->add('g-x', 'u-1');

        $this->store->removeUserFromTenantGroups('u-1', 't-none');

        self::assertTrue($this->store->isMember('g-x', 'u-1'));
    }
}
