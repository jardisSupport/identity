<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Contract\Auth\PasswordHasherInterface;
use JardisSupport\Auth\SessionManager;
use JardisSupport\Contract\Data\IdentityInterface;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\Event\PasswordChanged;
use JardisSupport\Identity\Data\Event\UserDisabled;
use JardisSupport\Identity\Data\Event\UserEnabled;
use JardisSupport\Identity\Data\UserResult;
use JardisSupport\Identity\Handler\Shared\AssertEmail;
use JardisSupport\Identity\Handler\Shared\AssertPassword;
use JardisSupport\Identity\Handler\Shared\NormalizeEmail;
use JardisSupport\Identity\Handler\User\ChangePassword;
use JardisSupport\Identity\Handler\User\DisableUser;
use JardisSupport\Identity\Handler\User\EnableUser;
use JardisSupport\Identity\Handler\User\LoadUser;
use JardisSupport\Identity\Handler\User\RegisterUser;

/** Orchestrates the user account lifecycle: registration, password change, disable and enable. */
final class UserManager
{
    private readonly Closure $registerUser;
    private readonly Closure $changePassword;
    private readonly Closure $disableUser;
    private readonly Closure $enableUser;

    public function __construct(
        UserStoreInterface $users,
        PasswordHasherInterface $hasher,
        IdentityInterface $ids,
        SessionManager $sessions,
        int $minPasswordLength = 8,
    ) {
        $assertPassword = (new AssertPassword($minPasswordLength))->__invoke(...);
        $loadUser = (new LoadUser($users))->__invoke(...);

        $this->registerUser = (new RegisterUser(
            $users,
            $hasher,
            $ids,
            (new NormalizeEmail())->__invoke(...),
            (new AssertEmail())->__invoke(...),
            $assertPassword,
        ))->__invoke(...);
        $this->changePassword = (new ChangePassword($users, $hasher, $loadUser, $assertPassword))->__invoke(...);
        $this->disableUser = (new DisableUser($users, $loadUser, $sessions->invalidateAll(...)))->__invoke(...);
        $this->enableUser = (new EnableUser($users, $loadUser))->__invoke(...);
    }

    public function register(string $email, string $password, string $displayName): UserResult
    {
        return ($this->registerUser)($email, $password, $displayName);
    }

    public function changePassword(string $userId, string $current, string $new): PasswordChanged
    {
        return ($this->changePassword)($userId, $current, $new);
    }

    public function disable(string $userId): UserDisabled
    {
        return ($this->disableUser)($userId);
    }

    public function enable(string $userId): UserEnabled
    {
        return ($this->enableUser)($userId);
    }
}
