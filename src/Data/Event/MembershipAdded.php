<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a user became a member of a tenant. */
final readonly class MembershipAdded
{
    public function __construct(
        public string $userId,
        public string $tenantId,
        public string $role,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
