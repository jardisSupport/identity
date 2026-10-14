<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Auth\SessionManager;
use JardisSupport\Auth\TokenIssuer;
use JardisSupport\Auth\TokenRedeemer;
use JardisSupport\Contract\Auth\PasswordHasherInterface;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\Event\PasswordReset;
use JardisSupport\Identity\Data\PasswordResetRequestResult;
use JardisSupport\Identity\Handler\PasswordReset\RequestPasswordReset;
use JardisSupport\Identity\Handler\PasswordReset\ResetPassword;
use JardisSupport\Identity\Handler\Shared\AssertPassword;
use JardisSupport\Identity\Handler\Shared\NormalizeEmail;

/** Orchestrates the password reset: issue a one-time token, redeem it and set the new password. */
final class PasswordResetManager
{
    private readonly Closure $requestReset;
    private readonly Closure $resetPassword;

    /** @param positive-int $ttl */
    public function __construct(
        UserStoreInterface $users,
        PasswordHasherInterface $hasher,
        TokenIssuer $issuer,
        TokenRedeemer $redeemer,
        SessionManager $sessions,
        int $ttl = 3600,
        int $minPasswordLength = 8,
    ) {
        $this->requestReset = (new RequestPasswordReset(
            $users,
            $issuer,
            (new NormalizeEmail())->__invoke(...),
            $ttl,
        ))->__invoke(...);
        $this->resetPassword = (new ResetPassword(
            $users,
            $hasher,
            $redeemer,
            (new AssertPassword($minPasswordLength))->__invoke(...),
            $sessions->invalidateAll(...),
        ))->__invoke(...);
    }

    /** @return PasswordResetRequestResult|null Null for unknown or disabled users (no enumeration) */
    public function request(string $email): ?PasswordResetRequestResult
    {
        return ($this->requestReset)($email);
    }

    /**
     * Sets the new password and ends ALL sessions and tokens of the user (global logout).
     */
    public function reset(string $token, string $newPassword): PasswordReset
    {
        return ($this->resetPassword)($token, $newPassword);
    }
}
