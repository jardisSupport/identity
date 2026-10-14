<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a user switched the active tenant of their session. */
final readonly class TenantSwitched
{
    public function __construct(
        public string $userId,
        public string $tenantId,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
