<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use InvalidArgumentException;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\Group;
use JardisSupport\Identity\DbGroupStore;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use JardisSupport\Identity\Tests\Support\Moment;
use PHPUnit\Framework\TestCase;

/** GroupStoreInterface behaviour of DbGroupStore against a real database (one subclass per driver). */
abstract class DbGroupStoreTestCase extends TestCase
{
    protected DbConnectionInterface $connection;
    protected DbGroupStore $store;

    abstract protected function createConnection(): DbConnectionInterface;

    protected function setUp(): void
    {
        $this->connection = $this->createConnection();
        IdentityTables::recreate($this->connection);
        $this->store = new DbGroupStore($this->connection);
    }

    private function group(string $id = 'g-1', string $tenantId = 't-1', string $name = 'Admins'): Group
    {
        return new Group($id, $tenantId, $name, Moment::fixed());
    }

    // (1) roundtrip
    public function testStoreAndFindRoundTripsAllFields(): void
    {
        $this->store->store($this->group());

        foreach ([$this->store->findById('g-1'), $this->store->findByName('t-1', 'Admins')] as $found) {
            self::assertNotNull($found);
            self::assertSame('g-1', $found->getId());
            self::assertSame('t-1', $found->getTenantId());
            self::assertSame('Admins', $found->getName());
            self::assertSame('2026-03-04 03:06:07.123456', Moment::utcText($found->getCreatedAt()));
        }
    }

    public function testListForTenantReturnsOnlyThatTenantsGroups(): void
    {
        $this->store->store($this->group('g-1', 't-1', 'Admins'));
        $this->store->store($this->group('g-2', 't-1', 'Editors'));
        $this->store->store($this->group('g-3', 't-2', 'Admins'));

        $ids = array_map(static fn ($g): string => $g->getId(), $this->store->listForTenant('t-1'));

        self::assertSame(['g-1', 'g-2'], $ids);
        self::assertSame([], $this->store->listForTenant('t-9'));
    }

    public function testFindByNameIsScopedToTheTenantAndCaseSensitive(): void
    {
        $this->store->store($this->group('g-1', 't-1', 'Admins'));

        self::assertNull($this->store->findByName('t-2', 'Admins'));
        self::assertNull($this->store->findByName('t-1', 'admins'));
    }

    // (2) unknown -> null
    public function testUnknownReturnsNull(): void
    {
        self::assertNull($this->store->findById('missing'));
        self::assertNull($this->store->findByName('t-1', 'missing'));
    }

    // (3) duplicate -> UniqueViolationException
    public function testDuplicateNameWithinTenantThrowsUniqueViolation(): void
    {
        $this->store->store($this->group('g-1', 't-1', 'Admins'));

        $this->expectException(UniqueViolationException::class);

        $this->store->store($this->group('g-2', 't-1', 'Admins'));
    }

    public function testSameNameInAnotherTenantIsAllowed(): void
    {
        $this->store->store($this->group('g-1', 't-1', 'Admins'));
        $this->store->store($this->group('g-2', 't-2', 'Admins'));

        self::assertSame('g-2', $this->store->findByName('t-2', 'Admins')?->getId());
    }

    // (4) custom prefix
    public function testCustomPrefix(): void
    {
        IdentityTables::recreate($this->connection, 'acme_');
        $store = new DbGroupStore($this->connection, 'acme_');

        $store->store($this->group());

        self::assertNotNull($store->findById('g-1'));
        self::assertNull($this->store->findById('g-1'));
    }

    // (5) invalid prefix
    public function testInvalidPrefixIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DbGroupStore($this->connection, '1bad');
    }
}
