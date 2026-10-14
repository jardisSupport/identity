<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\UserStore;

use JardisSupport\Repository\Repository;

/** Returns the stored password hash of a user, or null if the id is unknown. */
final class FindPasswordHash
{
    public function __construct(private readonly Repository $repository, private readonly string $table)
    {
    }

    public function __invoke(string $id): ?string
    {
        $row = $this->repository->findById($this->table, 'id', $id);

        return $row === null ? null : (string) $row['password_hash'];
    }
}
