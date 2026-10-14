<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a new user was registered. */
final readonly class UserRegistered
{
    public function __construct(
        public string $userId,
        public string $email,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
