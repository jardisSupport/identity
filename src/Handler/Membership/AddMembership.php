<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Membership;

use DateTimeImmutable;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Contract\Identity\TenantStoreInterface;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\Event\MembershipAdded;
use JardisSupport\Identity\Data\Membership;
use JardisSupport\Identity\Data\MembershipResult;
use JardisSupport\Identity\Exception\MembershipExistsException;
use JardisSupport\Identity\Exception\TenantNotFoundException;
use JardisSupport\Identity\Exception\UserNotFoundException;

/** Adds a user to a tenant with a role; user and tenant must exist, the membership must not. */
final class AddMembership
{
    public function __construct(
        private readonly MembershipStoreInterface $memberships,
        private readonly UserStoreInterface $users,
        private readonly TenantStoreInterface $tenants,
    ) {
    }

    public function __invoke(string $userId, string $tenantId, string $role): MembershipResult
    {
        if ($this->users->findById($userId) === null) {
            throw new UserNotFoundException(sprintf('User "%s" not found', $userId));
        }
        if ($this->tenants->findById($tenantId) === null) {
            throw new TenantNotFoundException(sprintf('Tenant "%s" not found', $tenantId));
        }
        if ($this->memberships->find($userId, $tenantId) !== null) {
            throw new MembershipExistsException('User is already a member of the tenant');
        }

        $now = new DateTimeImmutable();
        $membership = new Membership($userId, $tenantId, $role, $now);

        try {
            $this->memberships->store($membership);
        } catch (UniqueViolationException $e) {
            throw new MembershipExistsException('User is already a member of the tenant', 0, $e);
        }

        return new MembershipResult($membership, [new MembershipAdded($userId, $tenantId, $role, $now)]);
    }
}
