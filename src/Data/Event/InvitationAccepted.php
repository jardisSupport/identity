<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when an invitation was accepted. */
final readonly class InvitationAccepted
{
    public function __construct(
        public string $invitationId,
        public string $tenantId,
        public string $userId,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
