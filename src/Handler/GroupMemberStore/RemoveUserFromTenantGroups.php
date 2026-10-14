<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\GroupMemberStore;

use Closure;
use JardisSupport\DbQuery\DbDelete;
use JardisSupport\DbQuery\DbQuery;

/**
 * Deletes the user's memberships in every group whose tenant_id is the given tenant.
 *
 * Two statements (group ids of the tenant, then DELETE ... IN (ids)): DbDelete renders an
 * IN-subquery as a bare placeholder, so the ids are resolved first.
 */
final class RemoveUserFromTenantGroups
{
    public function __construct(
        private readonly Closure $executeWrite,
        private readonly Closure $fetchRows,
        private readonly string $table,
        private readonly string $groupsTable,
    ) {
    }

    public function __invoke(string $userId, string $tenantId): void
    {
        $rows = ($this->fetchRows)(
            (new DbQuery())->select('id')->from($this->groupsTable)->where('tenant_id')->equals($tenantId)
        );
        $groupIds = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        if ($groupIds === []) {
            return;
        }

        ($this->executeWrite)(
            (new DbDelete())->from($this->table)
                ->where('user_id')->equals($userId)
                ->and('group_id')->in($groupIds)
        );
    }
}
