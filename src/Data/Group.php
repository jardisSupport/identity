<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data;

use DateTimeImmutable;
use JardisSupport\Contract\Identity\GroupInterface;

/** Immutable group inside a tenant. */
final readonly class Group implements GroupInterface
{
    public function __construct(
        public string $id,
        public string $tenantId,
        public string $name,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTenantId(): string
    {
        return $this->tenantId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
