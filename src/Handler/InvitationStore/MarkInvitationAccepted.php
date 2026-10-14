<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\InvitationStore;

use Closure;
use DateTimeImmutable;
use JardisSupport\Repository\Repository;

/** CAS: sets accepted_at only while the invitation is neither accepted nor revoked; false if it lost. */
final class MarkInvitationAccepted
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $formatTimestamp,
        private readonly string $table,
    ) {
    }

    public function __invoke(string $id, DateTimeImmutable $at): bool
    {
        return $this->repository->update(
            $this->table,
            'id',
            $id,
            ['accepted_at' => ($this->formatTimestamp)($at)],
            ['accepted_at' => null, 'revoked_at' => null],
        );
    }
}
