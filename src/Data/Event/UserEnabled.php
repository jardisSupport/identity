<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a disabled user was enabled again. */
final readonly class UserEnabled
{
    public function __construct(
        public string $userId,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
