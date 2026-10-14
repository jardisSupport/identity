<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data\Event;

use DateTimeImmutable;

/** Domain event emitted when a user was added to a group. */
final readonly class GroupMemberAdded
{
    public function __construct(
        public string $groupId,
        public string $userId,
        public DateTimeImmutable $timestamp,
    ) {
    }
}
