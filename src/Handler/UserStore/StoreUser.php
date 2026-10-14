<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\UserStore;

use Closure;
use JardisSupport\Contract\Identity\UserInterface;
use JardisSupport\Contract\Repository\PrimaryKey\PkStrategy;
use JardisSupport\Repository\Repository;

/** Inserts a user with its password hash; a duplicate email or id throws UniqueViolationException. */
final class StoreUser
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $formatTimestamp,
        private readonly string $table,
    ) {
    }

    public function __invoke(UserInterface $user, string $passwordHash): void
    {
        $this->repository->insert($this->table, 'id', [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'display_name' => $user->getDisplayName(),
            'password_hash' => $passwordHash,
            'status' => $user->getStatus()->value,
            'created_at' => ($this->formatTimestamp)($user->getCreatedAt()),
        ], PkStrategy::NONE);
    }
}
