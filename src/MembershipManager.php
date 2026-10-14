<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Contract\Identity\GroupMemberStoreInterface;
use JardisSupport\Contract\Identity\MembershipInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Contract\Identity\TenantStoreInterface;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\Event\MembershipRemoved;
use JardisSupport\Identity\Data\MembershipResult;
use JardisSupport\Identity\Handler\Membership\AddMembership;
use JardisSupport\Identity\Handler\Membership\ChangeMembershipRole;
use JardisSupport\Identity\Handler\Membership\ListTenantMemberships;
use JardisSupport\Identity\Handler\Membership\ListUserMemberships;
use JardisSupport\Identity\Handler\Membership\RemoveMembership;

/** Orchestrates tenant memberships: add, change role, remove and list. */
final class MembershipManager
{
    private readonly Closure $addMembership;
    private readonly Closure $changeRole;
    private readonly Closure $removeMembership;
    private readonly Closure $listForUser;
    private readonly Closure $listForTenant;

    public function __construct(
        MembershipStoreInterface $memberships,
        GroupMemberStoreInterface $groupMembers,
        UserStoreInterface $users,
        TenantStoreInterface $tenants,
    ) {
        $this->addMembership = (new AddMembership($memberships, $users, $tenants))->__invoke(...);
        $this->changeRole = (new ChangeMembershipRole($memberships))->__invoke(...);
        $this->removeMembership = (new RemoveMembership($memberships, $groupMembers))->__invoke(...);
        $this->listForUser = (new ListUserMemberships($memberships))->__invoke(...);
        $this->listForTenant = (new ListTenantMemberships($memberships))->__invoke(...);
    }

    public function add(string $userId, string $tenantId, string $role): MembershipResult
    {
        return ($this->addMembership)($userId, $tenantId, $role);
    }

    public function changeRole(string $userId, string $tenantId, string $role): MembershipResult
    {
        return ($this->changeRole)($userId, $tenantId, $role);
    }

    public function remove(string $userId, string $tenantId): MembershipRemoved
    {
        return ($this->removeMembership)($userId, $tenantId);
    }

    /** @return list<MembershipInterface> */
    public function forUser(string $userId): array
    {
        return ($this->listForUser)($userId);
    }

    /** @return list<MembershipInterface> */
    public function forTenant(string $tenantId): array
    {
        return ($this->listForTenant)($tenantId);
    }
}
