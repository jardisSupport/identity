<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data;

use DateTimeImmutable;
use JardisSupport\Contract\Identity\InvitationInterface;

/** Immutable invitation of an e-mail address into a tenant; only the token hash is held. */
final readonly class Invitation implements InvitationInterface
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $email,
        public string $role,
        public string $tokenHash,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $acceptedAt,
        public ?DateTimeImmutable $revokedAt,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** Creates a pending invitation (neither accepted nor revoked). */
    public static function create(
        string $id,
        string $tenantId,
        string $email,
        string $role,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $tenantId, $email, $role, $tokenHash, $expiresAt, null, null, $createdAt);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getAcceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function withAccepted(DateTimeImmutable $at): self
    {
        return new self(
            $this->id,
            $this->tenantId,
            $this->email,
            $this->role,
            $this->tokenHash,
            $this->expiresAt,
            $at,
            $this->revokedAt,
            $this->createdAt,
        );
    }

    public function withRevoked(DateTimeImmutable $at): self
    {
        return new self(
            $this->id,
            $this->tenantId,
            $this->email,
            $this->role,
            $this->tokenHash,
            $this->expiresAt,
            $this->acceptedAt,
            $at,
            $this->createdAt,
        );
    }
}
