<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a tenant was created. */
final readonly class TenantCreated
{
    public function __construct(
        public string $tenantId,
        public string $slug,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
