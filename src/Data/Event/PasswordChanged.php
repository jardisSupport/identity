<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a user changed their password. */
final readonly class PasswordChanged
{
    public function __construct(
        public string $userId,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
