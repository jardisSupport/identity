<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\InvitationStore;

use Closure;
use DateTimeImmutable;
use JardisSupport\Repository\Repository;

/** Sets revoked_at on an invitation (unknown id is a no-op). */
final class MarkInvitationRevoked
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $formatTimestamp,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $id, DateTimeImmutable $at): void
    {
        $this->repository->update($this->table, 'id', $id, ['revoked_at' => ($this->formatTimestamp)($at)]);
    }
}
