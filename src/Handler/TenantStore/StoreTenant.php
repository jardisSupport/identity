<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\TenantStore;

use Closure;
use JardisSupport\Contract\Identity\TenantInterface;
use JardisSupport\Contract\Repository\PrimaryKey\PkStrategy;
use JardisSupport\Repository\Repository;

/** Inserts a tenant; a duplicate slug or id throws UniqueViolationException. */
final class StoreTenant
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $formatTimestamp,
        private readonly string $table,
    ) {
    }

    public function __invoke(TenantInterface $tenant): void
    {
        $this->repository->insert($this->table, 'id', [
            'id' => $tenant->getId(),
            'slug' => $tenant->getSlug(),
            'name' => $tenant->getName(),
            'created_at' => ($this->formatTimestamp)($tenant->getCreatedAt()),
        ], PkStrategy::NONE);
    }
}
