<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a user password was reset via a reset token. */
final readonly class PasswordReset
{
    public function __construct(
        public string $userId,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
