<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\GroupStore;

use Closure;
use JardisSupport\Contract\Identity\GroupInterface;
use JardisSupport\Contract\Repository\PrimaryKey\PkStrategy;
use JardisSupport\Repository\Repository;

/** Inserts a group; a duplicate (tenant, name) or id throws UniqueViolationException. */
final class StoreGroup
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $formatTimestamp,
        private readonly string $table,
    ) {
    }

    public function __invoke(GroupInterface $group): void
    {
        $this->repository->insert($this->table, 'id', [
            'id' => $group->getId(),
            'tenant_id' => $group->getTenantId(),
            'name' => $group->getName(),
            'created_at' => ($this->formatTimestamp)($group->getCreatedAt()),
        ], PkStrategy::NONE);
    }
}
