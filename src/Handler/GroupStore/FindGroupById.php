<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\GroupStore;

use Closure;
use JardisSupport\Contract\Identity\GroupInterface;
use JardisSupport\Repository\Repository;

/** Loads a group by id, or null if unknown. */
final class FindGroupById
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $hydrate,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $id): ?GroupInterface
    {
        $row = $this->repository->findById($this->table, 'id', $id);

        return $row === null ? null : ($this->hydrate)($row);
    }
}
