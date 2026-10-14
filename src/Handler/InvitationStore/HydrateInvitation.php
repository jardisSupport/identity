<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\InvitationStore;

use Closure;
use JardisSupport\Identity\Data\Invitation;

/** Builds an Invitation from a stored row. */
final class HydrateInvitation
{
    public function __construct(private readonly Closure $parseTimestamp)
    {
    }

    /** @param array<string, mixed> $row */
    public function __invoke(array $row): Invitation
    {
        return new Invitation(
            id: (string) $row['id'],
            tenantId: (string) $row['tenant_id'],
            email: (string) $row['email'],
            role: (string) $row['role'],
            tokenHash: (string) $row['token_hash'],
            expiresAt: ($this->parseTimestamp)($row['expires_at']),
            acceptedAt: $row['accepted_at'] === null ? null : ($this->parseTimestamp)($row['accepted_at']),
            revokedAt: $row['revoked_at'] === null ? null : ($this->parseTimestamp)($row['revoked_at']),
            createdAt: ($this->parseTimestamp)($row['created_at']),
        );
    }
}
