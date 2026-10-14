<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Invitation;

use JardisSupport\Contract\Identity\InvitationInterface;
use JardisSupport\Contract\Identity\InvitationStoreInterface;

/** Lists the open invitations of a tenant (not accepted, not revoked, not expired). */
final class ListPendingForTenant
{
    public function __construct(private readonly InvitationStoreInterface $invitations)
    {
    }

    /** @return list<InvitationInterface> */
    public function __invoke(string $tenantId): array
    {
        return $this->invitations->listPendingForTenant($tenantId);
    }
}
