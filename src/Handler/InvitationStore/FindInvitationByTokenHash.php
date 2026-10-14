<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\InvitationStore;

use Closure;
use JardisSupport\Contract\Identity\InvitationInterface;
use JardisSupport\DbQuery\DbQuery;
use JardisSupport\Repository\Repository;

/** Loads an invitation by token hash, or null if unknown. */
final class FindInvitationByTokenHash
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $hydrate,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $tokenHash): ?InvitationInterface
    {
        $query = (new DbQuery())->select('*')->from($this->table);
        $query->where('token_hash')->equals($tokenHash);
        $query->limit(1);
        $rows = $this->repository->findByQuery($query);

        return $rows === [] ? null : ($this->hydrate)($rows[0]);
    }
}
