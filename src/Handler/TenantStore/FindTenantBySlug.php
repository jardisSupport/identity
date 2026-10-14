<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\TenantStore;

use Closure;
use JardisSupport\Contract\Identity\TenantInterface;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Repository\Repository;

/** Loads a tenant by slug (exact, case-sensitive match), or null if unknown. */
final class FindTenantBySlug
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $hydrate,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $slug): ?TenantInterface
    {
        $query = (new DbQuery())->select('*')->from($this->table);
        $query->where('slug')->equals($slug);
        $query->limit(1);
        $rows = $this->repository->findByQuery($query);

        return $rows === [] ? null : ($this->hydrate)($rows[0]);
    }
}
