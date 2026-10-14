<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\MembershipStore;

use Closure;
use JardisSupport\Contract\Identity\MembershipInterface;
use JardisSupport\DbQuery\DbQuery;

/** Lists memberships filtered by one column (user_id or tenant_id), ordered by creation time. */
final class ListMemberships
{
    public function __construct(
        private readonly Closure $fetchRows,
        private readonly Closure $hydrate,
        private readonly string $table,
        private readonly string $column,
        private readonly string $orderColumn,
    ) {
    }

    /** @return list<MembershipInterface> */
    public function __invoke(string $id): array
    {
        $rows = ($this->fetchRows)(
            (new DbQuery())->select('*')->from($this->table)
                ->where($this->column)->equals($id)
                ->orderBy('created_at')->orderBy($this->orderColumn)
        );

        return array_values(array_map(fn (array $row): MembershipInterface => ($this->hydrate)($row), $rows));
    }
}
