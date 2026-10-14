<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\User;

use Closure;
use JardisSupport\Auth\Data\Subject;
use DateTimeImmutable;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Contract\Identity\UserStoreInterface;
use JardisSupport\Identity\Data\Event\UserDisabled;

/** Disables a user and ends all of the user's sessions. */
final class DisableUser
{
    /**
     * @param Closure(string): \JardisSupport\Contract\Identity\UserInterface $loadUser
     * @param Closure(string): mixed $invalidateAll receives the qualified subject ("user:{id}")
     */
    public function __construct(
        private readonly UserStoreInterface $users,
        private readonly Closure $loadUser,
        private readonly Closure $invalidateAll,
    ) {
    }

    public function __invoke(string $userId): UserDisabled
    {
        ($this->loadUser)($userId);
        $this->users->updateStatus($userId, UserStatus::Disabled);
        ($this->invalidateAll)(Subject::from($userId)->toString());

        return new UserDisabled($userId, new DateTimeImmutable());
    }
}
