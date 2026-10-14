<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\GroupStore;

use Closure;
use JardisSupport\Contract\Identity\GroupInterface;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Repository\Repository;

/** Lists the groups of a tenant ordered by creation time, then name. */
final class ListTenantGroups
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $hydrate,
        private readonly string $table,
    ) {
    }

    /** @return list<GroupInterface> */
    public function __invoke(string $tenantId): array
    {
        $query = (new DbQuery())->select('*')->from($this->table);
        $query->where('tenant_id')->equals($tenantId);
        $query->orderBy('created_at')->orderBy('id');
        $rows = $this->repository->findByQuery($query);

        return array_values(array_map(fn (array $row): GroupInterface => ($this->hydrate)($row), $rows));
    }
}
