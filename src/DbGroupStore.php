<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Identity\GroupInterface;
use JardisSupport\Contract\Identity\GroupStoreInterface;
use JardisSupport\Identity\Handler\GroupStore\FindGroupById;
use JardisSupport\Identity\Handler\GroupStore\FindGroupByName;
use JardisSupport\Identity\Handler\GroupStore\HydrateGroup;
use JardisSupport\Identity\Handler\GroupStore\ListTenantGroups;
use JardisSupport\Identity\Handler\GroupStore\StoreGroup;
use JardisSupport\Identity\Handler\Shared\AssertTablePrefix;
use JardisSupport\Identity\Handler\Shared\FormatUtcTimestamp;
use JardisSupport\Identity\Handler\Shared\ParseUtcTimestamp;
use JardisSupport\Repository\Repository;

/**
 * Persists groups on a database connection via jardissupport/repository.
 *
 * Table DDL ships in resources/sql/<driver>.sql; the table is "<prefix>groups".
 */
final class DbGroupStore implements GroupStoreInterface
{
    private readonly Closure $storeGroup;
    private readonly Closure $findById;
    private readonly Closure $findByName;
    private readonly Closure $listForTenant;

    public function __construct(DbConnectionInterface $connection, string $prefix = 'identity_')
    {
        $table = (new AssertTablePrefix())->__invoke($prefix) . 'groups';
        $repository = new Repository($connection->pdo());
        $format = (new FormatUtcTimestamp())->__invoke(...);
        $hydrate = (new HydrateGroup((new ParseUtcTimestamp())->__invoke(...)))->__invoke(...);

        $this->storeGroup = (new StoreGroup($repository, $format, $table))->__invoke(...);
        $this->findById = (new FindGroupById($repository, $hydrate, $table))->__invoke(...);
        $this->findByName = (new FindGroupByName($repository, $hydrate, $table))->__invoke(...);
        $this->listForTenant = (new ListTenantGroups($repository, $hydrate, $table))->__invoke(...);
    }

    public function store(GroupInterface $group): void
    {
        ($this->storeGroup)($group);
    }

    public function findById(string $id): ?GroupInterface
    {
        return ($this->findById)($id);
    }

    public function findByName(string $tenantId, string $name): ?GroupInterface
    {
        return ($this->findByName)($tenantId, $name);
    }

    public function listForTenant(string $tenantId): array
    {
        return ($this->listForTenant)($tenantId);
    }
}
