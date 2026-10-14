<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\GroupStore;

use Closure;
use JardisSupport\Contract\Identity\GroupInterface;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Repository\Repository;

/** Loads a group by (tenant, name), exact case-sensitive match, or null if unknown. */
final class FindGroupByName
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $hydrate,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $tenantId, string $name): ?GroupInterface
    {
        $query = (new DbQuery())->select('*')->from($this->table);
        $query->where('tenant_id')->equals($tenantId)->and('name')->equals($name);
        $query->limit(1);
        $rows = $this->repository->findByQuery($query);

        return $rows === [] ? null : ($this->hydrate)($rows[0]);
    }
}
