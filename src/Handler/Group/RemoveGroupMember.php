<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Group;

use Closure;
use DateTimeImmutable;
use JardisSupport\Contract\Identity\GroupMemberStoreInterface;
use JardisSupport\Identity\Data\Event\GroupMemberRemoved;

/** Removes a user from a group. */
final class RemoveGroupMember
{
    /**
     * @param Closure(string): \JardisSupport\Contract\Identity\GroupInterface $loadGroup
     */
    public function __construct(
        private readonly GroupMemberStoreInterface $groupMembers,
        private readonly Closure $loadGroup,
    ) {
    }

    public function __invoke(string $groupId, string $userId): GroupMemberRemoved
    {
        ($this->loadGroup)($groupId);
        $this->groupMembers->remove($groupId, $userId);

        return new GroupMemberRemoved($groupId, $userId, new DateTimeImmutable());
    }
}
