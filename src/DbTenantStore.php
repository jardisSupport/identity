<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Identity\TenantInterface;
use JardisSupport\Contract\Identity\TenantStoreInterface;
use JardisSupport\Identity\Handler\Shared\AssertTablePrefix;
use JardisSupport\Identity\Handler\Shared\FormatUtcTimestamp;
use JardisSupport\Identity\Handler\Shared\ParseUtcTimestamp;
use JardisSupport\Identity\Handler\TenantStore\FindTenantById;
use JardisSupport\Identity\Handler\TenantStore\FindTenantBySlug;
use JardisSupport\Identity\Handler\TenantStore\HydrateTenant;
use JardisSupport\Identity\Handler\TenantStore\StoreTenant;
use JardisSupport\Repository\Repository;

/**
 * Persists tenants on a database connection via jardissupport/repository.
 *
 * Table DDL ships in resources/sql/<driver>.sql; the table is "<prefix>tenants".
 */
final class DbTenantStore implements TenantStoreInterface
{
    private readonly Closure $storeTenant;
    private readonly Closure $findById;
    private readonly Closure $findBySlug;

    public function __construct(DbConnectionInterface $connection, string $prefix = 'identity_')
    {
        $table = (new AssertTablePrefix())->__invoke($prefix) . 'tenants';
        $repository = new Repository($connection->pdo());
        $format = (new FormatUtcTimestamp())->__invoke(...);
        $hydrate = (new HydrateTenant((new ParseUtcTimestamp())->__invoke(...)))->__invoke(...);

        $this->storeTenant = (new StoreTenant($repository, $format, $table))->__invoke(...);
        $this->findById = (new FindTenantById($repository, $hydrate, $table))->__invoke(...);
        $this->findBySlug = (new FindTenantBySlug($repository, $hydrate, $table))->__invoke(...);
    }

    public function store(TenantInterface $tenant): void
    {
        ($this->storeTenant)($tenant);
    }

    public function findById(string $id): ?TenantInterface
    {
        return ($this->findById)($id);
    }

    public function findBySlug(string $slug): ?TenantInterface
    {
        return ($this->findBySlug)($slug);
    }
}
