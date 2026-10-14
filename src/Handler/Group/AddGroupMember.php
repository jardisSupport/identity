<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Group;

use Closure;
use DateTimeImmutable;
use JardisSupport\Contract\Identity\GroupMemberStoreInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Identity\Data\Event\GroupMemberAdded;
use JardisSupport\Identity\Exception\NotAMemberException;

/** Adds a user to a group; only members of the group's tenant qualify. */
final class AddGroupMember
{
    /**
     * @param Closure(string): \JardisSupport\Contract\Identity\GroupInterface $loadGroup
     */
    public function __construct(
        private readonly GroupMemberStoreInterface $groupMembers,
        private readonly MembershipStoreInterface $memberships,
        private readonly Closure $loadGroup,
    ) {
    }

    public function __invoke(string $groupId, string $userId): GroupMemberAdded
    {
        $group = ($this->loadGroup)($groupId);

        if ($this->memberships->find($userId, $group->getTenantId()) === null) {
            throw new NotAMemberException('User is not a member of the group\'s tenant');
        }

        $this->groupMembers->add($groupId, $userId);

        return new GroupMemberAdded($groupId, $userId, new DateTimeImmutable());
    }
}
