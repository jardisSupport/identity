<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a password reset token was issued for a user. */
final readonly class PasswordResetRequested
{
    public function __construct(
        public string $userId,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
