<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\UserStore;

use JardisSupport\Repository\Repository;

/** Replaces the password hash of a user (unknown id is a no-op). */
final class UpdateUserPasswordHash
{
    public function __construct(private readonly Repository $repository, private readonly string $table)
    {
    }

    public function __invoke(string $id, string $hash): void
    {
        $this->repository->update($this->table, 'id', $id, ['password_hash' => $hash]);
    }
}
