<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\User;

use Closure;
use DateTimeImmutable;
use JardisSupport\Contract\Auth\PasswordHasherInterface;
use JardisSupport\Contract\Data\IdentityInterface;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\Event\UserRegistered;
use JardisSupport\Identity\Data\User;
use JardisSupport\Identity\Data\UserResult;
use JardisSupport\Identity\Exception\EmailAlreadyRegisteredException;

/** Registers a user: normalizes and validates e-mail and password, rejects duplicates, stores the hash. */
final class RegisterUser
{
    /**
     * @param Closure(string): string $normalizeEmail
     * @param Closure(string): string $assertEmail
     * @param Closure(string): string $assertPassword
     */
    public function __construct(
        private readonly UserStoreInterface $users,
        private readonly PasswordHasherInterface $hasher,
        private readonly IdentityInterface $ids,
        private readonly Closure $normalizeEmail,
        private readonly Closure $assertEmail,
        private readonly Closure $assertPassword,
    ) {
    }

    public function __invoke(string $email, string $password, string $displayName): UserResult
    {
        $normalized = ($this->assertEmail)(($this->normalizeEmail)($email));
        ($this->assertPassword)($password);

        if ($this->users->findByEmail($normalized) !== null) {
            throw new EmailAlreadyRegisteredException(sprintf('E-mail "%s" is already registered', $normalized));
        }

        $now = new DateTimeImmutable();
        $user = User::create($this->ids->generateUuid7(), $normalized, $displayName, $now);

        try {
            $this->users->store($user, $this->hasher->hash($password));
        } catch (UniqueViolationException $e) {
            throw new EmailAlreadyRegisteredException(
                sprintf('E-mail "%s" is already registered', $normalized),
                0,
                $e,
            );
        }

        return new UserResult($user, [new UserRegistered($user->getId(), $normalized, $now)]);
    }
}
