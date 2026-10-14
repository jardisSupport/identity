<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data;

use DateTimeImmutable;
use JardisSupport\Contract\Identity\MembershipInterface;

/** Immutable membership of a user in a tenant; the role is a free string (guard role name). */
final readonly class Membership implements MembershipInterface
{
    public function __construct(
        public string $userId,
        public string $tenantId,
        public string $role,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function withRole(string $role): self
    {
        return new self($this->userId, $this->tenantId, $role, $this->createdAt);
    }
}
