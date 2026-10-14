<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a membership was removed. */
final readonly class MembershipRemoved
{
    public function __construct(
        public string $userId,
        public string $tenantId,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
