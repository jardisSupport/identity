<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\TenantStore;

use Closure;
use JardisSupport\Contract\Identity\TenantInterface;
use JardisSupport\Repository\Repository;

/** Loads a tenant by id, or null if unknown. */
final class FindTenantById
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $hydrate,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $id): ?TenantInterface
    {
        $row = $this->repository->findById($this->table, 'id', $id);

        return $row === null ? null : ($this->hydrate)($row);
    }
}
