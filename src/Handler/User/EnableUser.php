<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\User;

use Closure;
use DateTimeImmutable;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\Event\UserEnabled;

/** Re-enables a disabled user. */
final class EnableUser
{
    /**
     * @param Closure(string): \JardisSupport\Contract\Identity\UserInterface $loadUser
     */
    public function __construct(
        private readonly UserStoreInterface $users,
        private readonly Closure $loadUser,
    ) {
    }

    public function __invoke(string $userId): UserEnabled
    {
        ($this->loadUser)($userId);
        $this->users->updateStatus($userId, UserStatus::Active);

        return new UserEnabled($userId, new DateTimeImmutable());
    }
}
