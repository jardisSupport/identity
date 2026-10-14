<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Lookup;

use Closure;
use JardisSupport\Auth\Data\Subject;
use JardisSupport\Contract\Auth\CredentialRecordInterface;
use JardisSupport\Contract\Auth\SessionClaim;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\CredentialRecord;

/**
 * Resolves a login identifier (e-mail) to a credential record. Unknown and disabled users yield null.
 * Tenant and role claims are set only for exactly one membership; with several the user picks one later.
 */
final class LookupCredential
{
    /** @param Closure(string): string $normalizeEmail */
    public function __construct(
        private readonly UserStoreInterface $users,
        private readonly MembershipStoreInterface $memberships,
        private readonly Closure $normalizeEmail,
    ) {
    }

    public function __invoke(string $identifier): ?CredentialRecordInterface
    {
        $user = $this->users->findByEmail(($this->normalizeEmail)($identifier));
        if ($user === null || $user->getStatus() !== UserStatus::Active) {
            return null;
        }

        $hash = $this->users->passwordHashOf($user->getId());
        if ($hash === null) {
            return null;
        }

        $list = $this->memberships->listForUser($user->getId());
        $claims = count($list) === 1
            ? [SessionClaim::TENANT => $list[0]->getTenantId(), SessionClaim::ROLE => $list[0]->getRole()]
            : [];

        return new CredentialRecord($hash, Subject::from($user->getId())->toString(), $claims);
    }
}
