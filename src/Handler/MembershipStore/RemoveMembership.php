<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\MembershipStore;

use Closure;
use JardisSupport\DbQuery\DbDelete;

/** Deletes a membership (unknown pair is a no-op). */
final class RemoveMembership
{
    public function __construct(private readonly Closure $executeWrite, private readonly string $table)
    {
    }

    public function __invoke(string $userId, string $tenantId): void
    {
        ($this->executeWrite)(
            (new DbDelete())->from($this->table)
                ->where('user_id')->equals($userId)
                ->and('tenant_id')->equals($tenantId)
        );
    }
}
