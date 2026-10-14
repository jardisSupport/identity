<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when the role of a membership changed (role is the new role). */
final readonly class MembershipRoleChanged
{
    public function __construct(
        public string $userId,
        public string $tenantId,
        public string $role,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
