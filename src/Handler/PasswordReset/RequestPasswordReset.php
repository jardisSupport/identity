<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\PasswordReset;

use Closure;
use DateTimeImmutable;
use JardisSupport\Auth\Data\Subject;
use JardisSupport\Auth\TokenIssuer;
use JardisSupport\Contract\Auth\TokenType;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\Event\PasswordResetRequested;
use JardisSupport\Identity\Data\PasswordResetRequestResult;

/** Issues a password-reset token for an active user; unknown or disabled users yield null (no enumeration). */
final class RequestPasswordReset
{
    /**
     * @param Closure(string): string $normalizeEmail
     * @param positive-int $ttl
     */
    public function __construct(
        private readonly UserStoreInterface $users,
        private readonly TokenIssuer $issuer,
        private readonly Closure $normalizeEmail,
        private readonly int $ttl,
    ) {
    }

    public function __invoke(string $email): ?PasswordResetRequestResult
    {
        $user = $this->users->findByEmail(($this->normalizeEmail)($email));
        if ($user === null || $user->getStatus() !== UserStatus::Active) {
            return null;
        }

        $issued = $this->issuer->issue(TokenType::PasswordReset, Subject::from($user->getId()), $this->ttl);

        return new PasswordResetRequestResult(
            $issued->plain,
            [new PasswordResetRequested($user->getId(), new DateTimeImmutable())],
        );
    }
}
