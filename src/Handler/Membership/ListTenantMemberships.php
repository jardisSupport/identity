<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Membership;

use JardisSupport\Contract\Identity\MembershipInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;

/** Lists the memberships of a tenant. */
final class ListTenantMemberships
{
    public function __construct(private readonly MembershipStoreInterface $memberships)
    {
    }

    /** @return list<MembershipInterface> */
    public function __invoke(string $tenantId): array
    {
        return $this->memberships->listForTenant($tenantId);
    }
}
