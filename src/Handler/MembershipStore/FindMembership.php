<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\MembershipStore;

use Closure;
use JardisSupport\Contract\Identity\MembershipInterface;
use JardisSupport\DbQuery\DbQuery;

/** Loads a membership by (user, tenant), or null if there is none. */
final class FindMembership
{
    public function __construct(
        private readonly Closure $fetchRows,
        private readonly Closure $hydrate,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $userId, string $tenantId): ?MembershipInterface
    {
        $rows = ($this->fetchRows)(
            (new DbQuery())->select('*')->from($this->table)
                ->where('user_id')->equals($userId)
                ->and('tenant_id')->equals($tenantId)
                ->limit(1)
        );

        return $rows === [] ? null : ($this->hydrate)($rows[0]);
    }
}
