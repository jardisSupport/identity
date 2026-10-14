<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Contract\Auth\CredentialRecordInterface;
use JardisSupport\Contract\Auth\UserLookupInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Handler\Lookup\LookupCredential;
use JardisSupport\Identity\Handler\Shared\NormalizeEmail;

/** Login seam for the password authenticator: resolves an e-mail to hash, subject and session claims. */
final class CredentialLookup implements UserLookupInterface
{
    private readonly Closure $lookupCredential;

    public function __construct(UserStoreInterface $users, MembershipStoreInterface $memberships)
    {
        $this->lookupCredential = (new LookupCredential(
            $users,
            $memberships,
            (new NormalizeEmail())->__invoke(...),
        ))->__invoke(...);
    }

    public function lookup(string $identifier): ?CredentialRecordInterface
    {
        return ($this->lookupCredential)($identifier);
    }
}
