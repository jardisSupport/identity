<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use InvalidArgumentException;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\Tenant;
use JardisSupport\Identity\DbTenantStore;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use JardisSupport\Identity\Tests\Support\Moment;
use PHPUnit\Framework\TestCase;

/** TenantStoreInterface behaviour of DbTenantStore against a real database (one subclass per driver). */
abstract class DbTenantStoreTestCase extends TestCase
{
    protected DbConnectionInterface $connection;
    protected DbTenantStore $store;

    abstract protected function createConnection(): DbConnectionInterface;

    protected function setUp(): void
    {
        $this->connection = $this->createConnection();
        IdentityTables::recreate($this->connection);
        $this->store = new DbTenantStore($this->connection);
    }

    private function tenant(string $id = 't-1', string $slug = 'acme'): Tenant
    {
        return new Tenant($id, $slug, 'Acme Corp', Moment::fixed());
    }

    // (1) roundtrip
    public function testStoreAndFindRoundTripsAllFields(): void
    {
        $this->store->store($this->tenant());

        foreach ([$this->store->findById('t-1'), $this->store->findBySlug('acme')] as $found) {
            self::assertNotNull($found);
            self::assertSame('t-1', $found->getId());
            self::assertSame('acme', $found->getSlug());
            self::assertSame('Acme Corp', $found->getName());
            self::assertSame('2026-03-04 03:06:07.123456', Moment::utcText($found->getCreatedAt()));
        }
    }

    public function testFindBySlugIsCaseSensitive(): void
    {
        $this->store->store($this->tenant());

        self::assertNull($this->store->findBySlug('ACME'));
    }

    // (2) unknown -> null
    public function testUnknownReturnsNull(): void
    {
        self::assertNull($this->store->findById('missing'));
        self::assertNull($this->store->findBySlug('missing'));
    }

    // (3) duplicate -> UniqueViolationException
    public function testDuplicateSlugThrowsUniqueViolation(): void
    {
        $this->store->store($this->tenant('t-1'));

        $this->expectException(UniqueViolationException::class);

        $this->store->store($this->tenant('t-2'));
    }

    public function testSlugsDifferingOnlyInCaseAreDistinct(): void
    {
        $this->store->store($this->tenant('t-1', 'acme'));
        $this->store->store($this->tenant('t-2', 'ACME'));

        self::assertSame('t-2', $this->store->findBySlug('ACME')?->getId());
    }

    // (4) custom prefix
    public function testCustomPrefix(): void
    {
        IdentityTables::recreate($this->connection, 'acme_');
        $store = new DbTenantStore($this->connection, 'acme_');

        $store->store($this->tenant());

        self::assertNotNull($store->findBySlug('acme'));
        self::assertNull($this->store->findBySlug('acme'));
    }

    // (5) invalid prefix
    public function testInvalidPrefixIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DbTenantStore($this->connection, 'bad-prefix');
    }
}
