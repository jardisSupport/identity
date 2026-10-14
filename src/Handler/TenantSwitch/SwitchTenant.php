<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\TenantSwitch;

use Closure;
use DateTimeImmutable;
use JardisSupport\Auth\Data\SessionResult;
use JardisSupport\Auth\Data\Subject;
use JardisSupport\Contract\Auth\SessionClaim;
use JardisSupport\Contract\Auth\TokenStoreInterface;
use JardisSupport\Contract\Identity\MembershipStoreInterface;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\Event\TenantSwitched;
use JardisSupport\Identity\Data\TenantSwitchResult;
use JardisSupport\Identity\Exception\NotAMemberException;
use JardisSupport\Identity\Exception\NotYourSessionException;
use JardisSupport\Identity\Exception\UserDisabledException;
use JardisSupport\Identity\Exception\UserNotFoundException;

/** Switches the active tenant: a new session carrying tenant and role replaces the current one. */
final class SwitchTenant
{
    /**
     * @param Closure(Subject, array<string, mixed>): SessionResult $createSession
     * @param Closure(string): mixed $invalidateByReference
     */
    public function __construct(
        private readonly UserStoreInterface $users,
        private readonly MembershipStoreInterface $memberships,
        private readonly TokenStoreInterface $tokens,
        private readonly Closure $createSession,
        private readonly Closure $invalidateByReference,
    ) {
    }

    public function __invoke(string $userId, string $tenantId, ?string $sessionRef): TenantSwitchResult
    {
        $user = $this->users->findById($userId)
            ?? throw new UserNotFoundException(sprintf('User "%s" not found', $userId));
        if ($user->getStatus() !== UserStatus::Active) {
            throw new UserDisabledException(sprintf('User "%s" is disabled', $userId));
        }

        $membership = $this->memberships->find($userId, $tenantId)
            ?? throw new NotAMemberException('User is not a member of the tenant');

        if ($sessionRef !== null) {
            $subject = Subject::from($userId)->toString();
            if ($this->tokens->find($sessionRef)?->getSubject() !== $subject) {
                throw new NotYourSessionException('The session does not belong to the user');
            }
        }

        $session = ($this->createSession)(
            Subject::from($userId),
            [SessionClaim::TENANT => $tenantId, SessionClaim::ROLE => $membership->getRole()],
        );

        if ($sessionRef !== null) {
            ($this->invalidateByReference)($sessionRef);
        }

        return new TenantSwitchResult($session, [new TenantSwitched($userId, $tenantId, new DateTimeImmutable())]);
    }
}
