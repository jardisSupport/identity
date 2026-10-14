<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\PasswordReset;

use Closure;
use DateTimeImmutable;
use JardisSupport\Auth\Data\Subject;
use JardisSupport\Auth\TokenRedeemer;
use JardisSupport\Contract\Auth\PasswordHasherInterface;
use JardisSupport\Contract\Auth\TokenType;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\Event\PasswordReset;
use JardisSupport\Identity\Exception\UserDisabledException;
use JardisSupport\Identity\Exception\UserNotFoundException;

/**
 * Redeems a reset token, sets the new password and ends all sessions of the user.
 * Redeem failures surface as the auth exceptions (wrong type, expired, revoked, unknown).
 */
final class ResetPassword
{
    /**
     * @param Closure(string): string $assertPassword
     * @param Closure(string): mixed $invalidateAll receives the qualified subject ("user:{id}")
     */
    public function __construct(
        private readonly UserStoreInterface $users,
        private readonly PasswordHasherInterface $hasher,
        private readonly TokenRedeemer $redeemer,
        private readonly Closure $assertPassword,
        private readonly Closure $invalidateAll,
    ) {
    }

    public function __invoke(string $token, string $newPassword): PasswordReset
    {
        // Check first: a too weak password must not burn the single-use reset token.
        ($this->assertPassword)($newPassword);

        $redeemed = $this->redeemer->redeem($token, TokenType::PasswordReset);
        $subject = Subject::parse((string) $redeemed->getSubject());

        $user = $this->users->findById($subject->id)
            ?? throw new UserNotFoundException(sprintf('User "%s" not found', $subject->id));
        if ($user->getStatus() !== UserStatus::Active) {
            throw new UserDisabledException(sprintf('User "%s" is disabled', $subject->id));
        }

        $this->users->updatePasswordHash($subject->id, $this->hasher->hash($newPassword));
        ($this->invalidateAll)($subject->toString());

        return new PasswordReset($subject->id, new DateTimeImmutable());
    }
}
