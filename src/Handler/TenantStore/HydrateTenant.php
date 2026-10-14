<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\TenantStore;

use Closure;
use JardisSupport\Identity\Data\Tenant;

/** Builds a Tenant from a stored row. */
final class HydrateTenant
{
    public function __construct(private readonly Closure $parseTimestamp)
    {
    }

    /** @param array<string, mixed> $row */
    public function __invoke(array $row): Tenant
    {
        return new Tenant(
            id: (string) $row['id'],
            slug: (string) $row['slug'],
            name: (string) $row['name'],
            createdAt: ($this->parseTimestamp)($row['created_at']),
        );
    }
}
