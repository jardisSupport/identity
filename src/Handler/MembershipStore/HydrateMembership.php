<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\MembershipStore;

use Closure;
use JardisSupport\Identity\Data\Membership;

/** Builds a Membership from a stored row. */
final class HydrateMembership
{
    public function __construct(private readonly Closure $parseTimestamp)
    {
    }

    /** @param array<string, mixed> $row */
    public function __invoke(array $row): Membership
    {
        return new Membership(
            userId: (string) $row['user_id'],
            tenantId: (string) $row['tenant_id'],
            role: (string) $row['role'],
            createdAt: ($this->parseTimestamp)($row['created_at']),
        );
    }
}
