<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data;

use DateTimeImmutable;
use JardisSupport\Contract\Identity\UserInterface;
use JardisSupport\Contract\Identity\UserStatus;

/** Immutable user entity. The password hash is deliberately not part of it. */
final readonly class User implements UserInterface
{
    public function __construct(
        public string $id,
        public string $email,
        public string $displayName,
        public UserStatus $status,
        public DateTimeImmutable $createdAt,
    ) {
    }

    /** Creates a new, active user. */
    public static function create(
        string $id,
        string $email,
        string $displayName,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $email, $displayName, UserStatus::Active, $createdAt);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getStatus(): UserStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function withStatus(UserStatus $status): self
    {
        return new self($this->id, $this->email, $this->displayName, $status, $this->createdAt);
    }
}
