<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Group;

use Closure;
use JardisSupport\Contract\Identity\GroupMemberStoreInterface;

/** Lists the user ids of a group. */
final class ListGroupMemberIds
{
    /**
     * @param Closure(string): \JardisSupport\Contract\Identity\GroupInterface $loadGroup
     */
    public function __construct(
        private readonly GroupMemberStoreInterface $groupMembers,
        private readonly Closure $loadGroup,
    ) {
    }

    /** @return list<string> */
    public function __invoke(string $groupId): array
    {
        ($this->loadGroup)($groupId);

        return $this->groupMembers->listMembers($groupId);
    }
}
