<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\User;

use Closure;
use DateTimeImmutable;
use JardisSupport\Auth\Exception\InvalidCredentialException;
use JardisSupport\Contract\Auth\PasswordHasherInterface;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\Event\PasswordChanged;

/** Changes a password after verifying the current one; the stored hash stays untouched on any failure. */
final class ChangePassword
{
    /**
     * @param Closure(string): \JardisSupport\Contract\Identity\UserInterface $loadUser
     * @param Closure(string): string $assertPassword
     */
    public function __construct(
        private readonly UserStoreInterface $users,
        private readonly PasswordHasherInterface $hasher,
        private readonly Closure $loadUser,
        private readonly Closure $assertPassword,
    ) {
    }

    public function __invoke(string $userId, string $current, string $new): PasswordChanged
    {
        ($this->loadUser)($userId);

        $hash = $this->users->passwordHashOf($userId);
        if ($hash === null || !$this->hasher->verify($current, $hash)) {
            throw new InvalidCredentialException('Current password does not match');
        }

        ($this->assertPassword)($new);
        $this->users->updatePasswordHash($userId, $this->hasher->hash($new));

        return new PasswordChanged($userId, new DateTimeImmutable());
    }
}
