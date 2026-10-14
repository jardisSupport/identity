<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Auth\TokenIssuer;
use JardisSupport\Auth\TokenRedeemer;
use JardisSupport\Contract\Auth\PasswordHasherInterface;
use JardisSupport\Contract\Auth\TokenStoreInterface;
use JardisSupport\Contract\Data\IdentityInterface;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Identity\InvitationInterface;
use JardisSupport\Contract\Identity\InvitationStoreInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\AcceptResult;
use JardisSupport\Identity\Data\InvitationResult;
use JardisSupport\Identity\Handler\Invitation\AcceptInvitation;
use JardisSupport\Identity\Handler\Invitation\CreateInvitation;
use JardisSupport\Identity\Handler\Invitation\ListPendingForTenant;
use JardisSupport\Identity\Handler\Shared\AssertEmail;
use JardisSupport\Identity\Handler\Shared\AssertPassword;
use JardisSupport\Identity\Handler\Shared\NormalizeEmail;
use JardisSupport\Identity\Handler\Shared\RunInTransaction;
use JardisSupport\Identity\Handler\User\RegisterUser;

/** Orchestrates tenant invitations: invite, accept (in one transaction) and list the open ones. */
final class InvitationManager
{
    private readonly Closure $createInvitation;
    private readonly Closure $acceptInvitation;
    private readonly Closure $listPending;

    /** @param positive-int $ttl */
    public function __construct(
        InvitationStoreInterface $invitations,
        UserStoreInterface $users,
        MembershipStoreInterface $memberships,
        PasswordHasherInterface $hasher,
        IdentityInterface $ids,
        TokenIssuer $issuer,
        TokenRedeemer $redeemer,
        TokenStoreInterface $tokens,
        DbConnectionInterface $connection,
        int $ttl = 604800,
        int $minPasswordLength = 8,
    ) {
        $normalizeEmail = (new NormalizeEmail())->__invoke(...);
        $assertEmail = (new AssertEmail())->__invoke(...);

        $registerUser = (new RegisterUser(
            $users,
            $hasher,
            $ids,
            $normalizeEmail,
            $assertEmail,
            (new AssertPassword($minPasswordLength))->__invoke(...),
        ))->__invoke(...);

        $this->createInvitation = (new CreateInvitation(
            $invitations,
            $ids,
            $issuer,
            $tokens,
            $normalizeEmail,
            $assertEmail,
            $ttl,
        ))->__invoke(...);
        $this->acceptInvitation = (new AcceptInvitation(
            $invitations,
            $users,
            $memberships,
            $redeemer,
            $registerUser,
            $normalizeEmail,
            (new RunInTransaction($connection))->__invoke(...),
        ))->__invoke(...);
        $this->listPending = (new ListPendingForTenant($invitations))->__invoke(...);
    }

    public function invite(string $tenantId, string $email, string $role): InvitationResult
    {
        return ($this->createInvitation)($tenantId, $email, $role);
    }

    public function accept(string $token, ?string $password = null, ?string $displayName = null): AcceptResult
    {
        return ($this->acceptInvitation)($token, $password, $displayName);
    }

    /** @return list<InvitationInterface> */
    public function pendingForTenant(string $tenantId): array
    {
        return ($this->listPending)($tenantId);
    }
}
