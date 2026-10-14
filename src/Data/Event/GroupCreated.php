<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a group was created in a tenant. */
final readonly class GroupCreated
{
    public function __construct(
        public string $groupId,
        public string $tenantId,
        public string $name,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
