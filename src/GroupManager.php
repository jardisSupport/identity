<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Contract\Data\IdentityInterface;
use JardisSupport\Contract\Identity\GroupInterface;
use JardisSupport\Contract\Identity\GroupMemberStoreInterface;
use JardisSupport\Contract\Identity\GroupStoreInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Identity\Data\Event\GroupMemberAdded;
use JardisSupport\Identity\Data\Event\GroupMemberRemoved;
use JardisSupport\Identity\Data\GroupResult;
use JardisSupport\Identity\Handler\Group\AddGroupMember;
use JardisSupport\Identity\Handler\Group\CreateGroup;
use JardisSupport\Identity\Handler\Group\ListGroupMemberIds;
use JardisSupport\Identity\Handler\Group\LoadGroup;
use JardisSupport\Identity\Handler\Group\RemoveGroupMember;

/** Orchestrates groups inside a tenant: create, manage members, list. */
final class GroupManager
{
    private readonly Closure $createGroup;
    private readonly Closure $addMember;
    private readonly Closure $removeMember;
    private readonly Closure $listMembers;
    private readonly Closure $listForTenant;

    public function __construct(
        GroupStoreInterface $groups,
        GroupMemberStoreInterface $groupMembers,
        MembershipStoreInterface $memberships,
        IdentityInterface $ids,
    ) {
        $loadGroup = (new LoadGroup($groups))->__invoke(...);

        $this->createGroup = (new CreateGroup($groups, $ids))->__invoke(...);
        $this->addMember = (new AddGroupMember($groupMembers, $memberships, $loadGroup))->__invoke(...);
        $this->removeMember = (new RemoveGroupMember($groupMembers, $loadGroup))->__invoke(...);
        $this->listMembers = (new ListGroupMemberIds($groupMembers, $loadGroup))->__invoke(...);
        $this->listForTenant = $groups->listForTenant(...);
    }

    public function create(string $tenantId, string $name): GroupResult
    {
        return ($this->createGroup)($tenantId, $name);
    }

    public function addMember(string $groupId, string $userId): GroupMemberAdded
    {
        return ($this->addMember)($groupId, $userId);
    }

    public function removeMember(string $groupId, string $userId): GroupMemberRemoved
    {
        return ($this->removeMember)($groupId, $userId);
    }

    /** @return list<string> */
    public function members(string $groupId): array
    {
        return ($this->listMembers)($groupId);
    }

    /** @return list<GroupInterface> */
    public function forTenant(string $tenantId): array
    {
        return ($this->listForTenant)($tenantId);
    }
}
