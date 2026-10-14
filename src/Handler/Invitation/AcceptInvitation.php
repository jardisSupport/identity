<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Invitation;

use Closure;
use DateTimeImmutable;
use JardisSupport\Auth\Exception\AuthenticationException;
use JardisSupport\Auth\TokenRedeemer;
use JardisSupport\Contract\Auth\TokenType;
use JardisSupport\Contract\Identity\InvitationStoreInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\AcceptResult;
use JardisSupport\Identity\Data\Event\InvitationAccepted;
use JardisSupport\Identity\Data\Event\MembershipAdded;
use JardisSupport\Identity\Data\Membership;
use JardisSupport\Identity\Data\User;
use JardisSupport\Identity\Exception\InvitationInvalidException;
use JardisSupport\Identity\Exception\MembershipExistsException;
use JardisSupport\Identity\Exception\PasswordRequiredException;
use JardisSupport\Identity\Exception\UserDisabledException;

/**
 * Accepts an invitation in one transaction: redeems the token, claims the invitation (CAS),
 * then registers the unknown user or attaches the known one, and adds the membership.
 */
final class AcceptInvitation
{
    /**
     * @param Closure(string): string $normalizeEmail
     * @param Closure(string, string, string): \JardisSupport\Identity\Data\UserResult $registerUser
     * @param Closure(Closure): mixed $inTransaction
     */
    public function __construct(
        private readonly InvitationStoreInterface $invitations,
        private readonly UserStoreInterface $users,
        private readonly MembershipStoreInterface $memberships,
        private readonly TokenRedeemer $redeemer,
        private readonly Closure $registerUser,
        private readonly Closure $normalizeEmail,
        private readonly Closure $inTransaction,
    ) {
    }

    public function __invoke(string $token, ?string $password, ?string $displayName): AcceptResult
    {
        /** @var AcceptResult $result */
        $result = ($this->inTransaction)(fn (): AcceptResult => $this->accept($token, $password, $displayName));

        return $result;
    }

    private function accept(string $token, ?string $password, ?string $displayName): AcceptResult
    {
        try {
            $redeemed = $this->redeemer->redeem($token, TokenType::Verification);
        } catch (AuthenticationException $e) {
            throw new InvitationInvalidException('Invitation token is invalid, expired or already used', 0, $e);
        }

        $invitation = $this->invitations->findByTokenHash($redeemed->getHash())
            ?? throw new InvitationInvalidException('Invitation is unknown');

        $now = new DateTimeImmutable();
        if ($invitation->getExpiresAt() <= $now) {
            throw new InvitationInvalidException('Invitation is expired');
        }
        if (!$this->invitations->markAccepted($invitation->getId(), $now)) {
            throw new InvitationInvalidException('Invitation was already accepted or revoked');
        }

        $events = [];
        $known = $this->users->findByEmail(($this->normalizeEmail)($invitation->getEmail()));

        if ($known === null) {
            if ($password === null || $password === '') {
                throw new PasswordRequiredException('A password is required to create the account');
            }
            $registered = ($this->registerUser)($invitation->getEmail(), $password, $displayName ?? '');
            $user = $registered->user;
            $events = $registered->events;
        } else {
            if ($known->getStatus() !== UserStatus::Active) {
                throw new UserDisabledException(sprintf('User "%s" is disabled', $known->getId()));
            }
            if ($this->memberships->find($known->getId(), $invitation->getTenantId()) !== null) {
                throw new MembershipExistsException('User is already a member of the tenant');
            }
            $user = new User(
                $known->getId(),
                $known->getEmail(),
                $known->getDisplayName(),
                $known->getStatus(),
                $known->getCreatedAt(),
            );
        }

        $membership = new Membership($user->getId(), $invitation->getTenantId(), $invitation->getRole(), $now);
        try {
            $this->memberships->store($membership);
        } catch (UniqueViolationException $e) {
            throw new MembershipExistsException('User is already a member of the tenant', 0, $e);
        }

        $events[] = new MembershipAdded($user->getId(), $invitation->getTenantId(), $invitation->getRole(), $now);
        $events[] = new InvitationAccepted($invitation->getId(), $invitation->getTenantId(), $user->getId(), $now);

        return new AcceptResult($user, $membership, $events);
    }
}
