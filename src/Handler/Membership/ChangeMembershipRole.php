<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Membership;

use DateTimeImmutable;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Identity\Data\Event\MembershipRoleChanged;
use JardisSupport\Identity\Data\Membership;
use JardisSupport\Identity\Data\MembershipResult;
use JardisSupport\Identity\Exception\NotAMemberException;

/** Changes the role of an existing membership. */
final class ChangeMembershipRole
{
    public function __construct(private readonly MembershipStoreInterface $memberships)
    {
    }

    public function __invoke(string $userId, string $tenantId, string $role): MembershipResult
    {
        $existing = $this->memberships->find($userId, $tenantId)
            ?? throw new NotAMemberException('User is not a member of the tenant');

        $this->memberships->updateRole($userId, $tenantId, $role);
        $membership = new Membership($userId, $tenantId, $role, $existing->getCreatedAt());

        return new MembershipResult($membership, [
            new MembershipRoleChanged($userId, $tenantId, $role, new DateTimeImmutable()),
        ]);
    }
}
