<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a user was disabled. */
final readonly class UserDisabled
{
    public function __construct(
        public string $userId,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
