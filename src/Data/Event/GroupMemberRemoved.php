<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a user was removed from a group. */
final readonly class GroupMemberRemoved
{
    public function __construct(
        public string $groupId,
        public string $userId,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
