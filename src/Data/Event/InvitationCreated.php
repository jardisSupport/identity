<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when an invitation was created. */
final readonly class InvitationCreated
{
    public function __construct(
        public string $invitationId,
        public string $tenantId,
        public string $email,
        public string $role,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
