<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\UserStore;

use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Repository\Repository;

/** Sets the status of a user (unknown id is a no-op). */
final class UpdateUserStatus
{
    public function __construct(private readonly Repository $repository, private readonly string $table)
    {
    }

    public function __invoke(string $id, UserStatus $status): void
    {
        $this->repository->update($this->table, 'id', $id, ['status' => $status->value]);
    }
}
