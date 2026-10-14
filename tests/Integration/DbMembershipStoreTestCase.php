<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use InvalidArgumentException;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\Membership;
use JardisSupport\Identity\DbMembershipStore;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use JardisSupport\Identity\Tests\Support\Moment;
use PHPUnit\Framework\TestCase;

/** MembershipStoreInterface behaviour of DbMembershipStore against a real database (one subclass per driver). */
abstract class DbMembershipStoreTestCase extends TestCase
{
    protected DbConnectionInterface $connection;
    protected DbMembershipStore $store;

    abstract protected function createConnection(): DbConnectionInterface;

    protected function setUp(): void
    {
        $this->connection = $this->createConnection();
        IdentityTables::recreate($this->connection);
        $this->store = new DbMembershipStore($this->connection);
    }

    private function membership(string $userId = 'u-1', string $tenantId = 't-1', string $role = 'member'): Membership
    {
        return new Membership($userId, $tenantId, $role, Moment::fixed());
    }

    // (1) roundtrip
    public function testStoreAndFindRoundTripsAllFields(): void
    {
        $this->store->store($this->membership());

        $found = $this->store->find('u-1', 't-1');

        self::assertNotNull($found);
        self::assertSame('u-1', $found->getUserId());
        self::assertSame('t-1', $found->getTenantId());
        self::assertSame('member', $found->getRole());
        self::assertSame('2026-03-04 03:06:07.123456', Moment::utcText($found->getCreatedAt()));
    }

    // (2) unknown -> null
    public function testUnknownReturnsNull(): void
    {
        $this->store->store($this->membership());

        self::assertNull($this->store->find('u-1', 't-2'));
        self::assertNull($this->store->find('u-2', 't-1'));
        self::assertSame([], $this->store->listForUser('u-9'));
        self::assertSame([], $this->store->listForTenant('t-9'));
    }

    // (3) duplicate -> UniqueViolationException
    public function testDuplicateMembershipThrowsUniqueViolation(): void
    {
        $this->store->store($this->membership());

        $this->expectException(UniqueViolationException::class);

        $this->store->store($this->membership('u-1', 't-1', 'admin'));
    }

    // (4) custom prefix
    public function testCustomPrefix(): void
    {
        IdentityTables::recreate($this->connection, 'acme_');
        $store = new DbMembershipStore($this->connection, 'acme_');

        $store->store($this->membership());

        self::assertNotNull($store->find('u-1', 't-1'));
        self::assertNull($this->store->find('u-1', 't-1'));
    }

    // (5) invalid prefix
    public function testInvalidPrefixIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DbMembershipStore($this->connection, 'a.b');
    }

    // (6) updateRole, remove, lists
    public function testUpdateRoleChangesOnlyThatMembership(): void
    {
        $this->store->store($this->membership('u-1', 't-1', 'member'));
        $this->store->store($this->membership('u-1', 't-2', 'member'));
        $this->store->store($this->membership('u-2', 't-1', 'member'));

        $this->store->updateRole('u-1', 't-1', 'admin');

        self::assertSame('admin', $this->store->find('u-1', 't-1')?->getRole());
        self::assertSame('member', $this->store->find('u-1', 't-2')?->getRole());
        self::assertSame('member', $this->store->find('u-2', 't-1')?->getRole());
    }

    public function testRemoveDeletesOnlyThatMembershipAndUnknownIsNoOp(): void
    {
        $this->store->store($this->membership('u-1', 't-1'));
        $this->store->store($this->membership('u-1', 't-2'));

        $this->store->remove('u-1', 't-1');
        $this->store->remove('u-9', 't-9');

        self::assertNull($this->store->find('u-1', 't-1'));
        self::assertNotNull($this->store->find('u-1', 't-2'));
    }

    public function testListForUserAndListForTenant(): void
    {
        $this->store->store($this->membership('u-1', 't-1'));
        $this->store->store($this->membership('u-1', 't-2'));
        $this->store->store($this->membership('u-2', 't-1'));

        $tenants = array_map(static fn ($m): string => $m->getTenantId(), $this->store->listForUser('u-1'));
        $users = array_map(static fn ($m): string => $m->getUserId(), $this->store->listForTenant('t-1'));

        self::assertSame(['t-1', 't-2'], $tenants);
        self::assertSame(['u-1', 'u-2'], $users);
    }
}
