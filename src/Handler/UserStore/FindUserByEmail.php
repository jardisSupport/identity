<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\UserStore;

use Closure;
use JardisSupport\Contract\Identity\UserInterface;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Repository\Repository;

/** Loads a user by its (already normalised) email, exact match, or null if unknown. */
final class FindUserByEmail
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $hydrate,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $email): ?UserInterface
    {
        $query = (new DbQuery())->select('*')->from($this->table);
        $query->where('email')->equals($email);
        $query->limit(1);
        $rows = $this->repository->findByQuery($query);

        return $rows === [] ? null : ($this->hydrate)($rows[0]);
    }
}
