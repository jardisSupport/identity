<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\User;

use JardisSupport\Contract\Identity\UserInterface;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Exception\UserNotFoundException;

/** Loads a user by id or fails with UserNotFoundException. */
final class LoadUser
{
    public function __construct(private readonly UserStoreInterface $users)
    {
    }

    public function __invoke(string $userId): UserInterface
    {
        return $this->users->findById($userId)
            ?? throw new UserNotFoundException(sprintf('User "%s" not found', $userId));
    }
}
