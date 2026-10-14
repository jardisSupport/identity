<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\InvitationStore;

use Closure;
use JardisSupport\Contract\Identity\InvitationInterface;
use JardisSupport\Contract\Repository\PrimaryKey\PkStrategy;
use JardisSupport\Repository\Repository;

/** Inserts an invitation (token hash only); a duplicate id or token hash throws UniqueViolationException. */
final class StoreInvitation
{
    public function __construct(
        private readonly Repository $repository,
        private readonly Closure $formatTimestamp,
        private readonly string $table,
    ) {
    }

    public function __invoke(InvitationInterface $invitation): void
    {
        $acceptedAt = $invitation->getAcceptedAt();
        $revokedAt = $invitation->getRevokedAt();

        $this->repository->insert($this->table, 'id', [
            'id' => $invitation->getId(),
            'tenant_id' => $invitation->getTenantId(),
            'email' => $invitation->getEmail(),
            'role' => $invitation->getRole(),
            'token_hash' => $invitation->getTokenHash(),
            'expires_at' => ($this->formatTimestamp)($invitation->getExpiresAt()),
            'accepted_at' => $acceptedAt === null ? null : ($this->formatTimestamp)($acceptedAt),
            'revoked_at' => $revokedAt === null ? null : ($this->formatTimestamp)($revokedAt),
            'created_at' => ($this->formatTimestamp)($invitation->getCreatedAt()),
        ], PkStrategy::NONE);
    }
}
