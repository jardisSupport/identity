<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Data;

use DateTimeImmutable;
use JardisSupport\Contract\Identity\TenantInterface;

/** Immutable tenant entity. */
final readonly class Tenant implements TenantInterface
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
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
