<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\MembershipStore;

use Closure;
use JardisSupport\DbQuery\DbUpdate;

/** Sets the role of a membership (unknown pair is a no-op). */
final class UpdateMembershipRole
{
    public function __construct(private readonly Closure $executeWrite, private readonly string $table)
    {
    }

    public function __invoke(string $userId, string $tenantId, string $role): void
    {
        ($this->executeWrite)(
            (new DbUpdate())->table($this->table)->set('role', $role)
                ->where('user_id')->equals($userId)
                ->and('tenant_id')->equals($tenantId)
        );
    }
}
