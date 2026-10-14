<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Auth\SessionManager;
use JardisSupport\Contract\Auth\TokenStoreInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\TenantSwitchResult;
use JardisSupport\Identity\Handler\TenantSwitch\SwitchTenant;

/** Orchestrates the tenant switch: new session for the target tenant, the current one is revoked. */
final class TenantSwitcher
{
    private readonly Closure $switchTenant;

    public function __construct(
        UserStoreInterface $users,
        MembershipStoreInterface $memberships,
        TokenStoreInterface $tokens,
        SessionManager $sessions,
    ) {
        $this->switchTenant = (new SwitchTenant(
            $users,
            $memberships,
            $tokens,
            $sessions->create(...),
            $sessions->invalidateByReference(...),
        ))->__invoke(...);
    }

    /**
     * @param string|null $sessionRef Reference of the current session (access and refresh end with it)
     */
    public function switch(string $userId, string $tenantId, ?string $sessionRef): TenantSwitchResult
    {
        return ($this->switchTenant)($userId, $tenantId, $sessionRef);
    }
}
