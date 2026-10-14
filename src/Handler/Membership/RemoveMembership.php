<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Membership;

use DateTimeImmutable;
use JardisSupport\Contract\Identity\GroupMemberStoreInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Identity\Data\Event\MembershipRemoved;
use JardisSupport\Identity\Exception\NotAMemberException;

/** Removes a membership together with the user's group memberships in that tenant. */
final class RemoveMembership
{
    public function __construct(
        private readonly MembershipStoreInterface $memberships,
        private readonly GroupMemberStoreInterface $groupMembers,
    ) {
    }

    public function __invoke(string $userId, string $tenantId): MembershipRemoved
    {
        if ($this->memberships->find($userId, $tenantId) === null) {
            throw new NotAMemberException('User is not a member of the tenant');
        }

        $this->groupMembers->removeUserFromTenantGroups($userId, $tenantId);
        $this->memberships->remove($userId, $tenantId);

        return new MembershipRemoved($userId, $tenantId, new DateTimeImmutable());
    }
}
