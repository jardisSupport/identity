<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\UserStore;

use Closure;
use JardisSupport\Contract\Identity\UserInterface;
use JardisSupport\Repository\Repository;

/** Loads a user by id, or null if unknown. */
final class FindUserById
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $hydrate,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $id): ?UserInterface
    {
        $row = $this->repository->findById($this->table, 'id', $id);

        return $row === null ? null : ($this->hydrate)($row);
    }
}
