<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\GroupStore;

use Closure;
use JardisSupport\Identity\Data\Group;

/** Builds a Group from a stored row. */
final class HydrateGroup
{
    public function __construct(private readonly Closure $parseTimestamp)
    {
    }

    /** @param array<string, mixed> $row */
    public function __invoke(array $row): Group
    {
        return new Group(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            name: (string) $row['name'],
            createdAt: ($this->parseTimestamp)($row['created_at']),
        );
    }
}
