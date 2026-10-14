<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\UserStore;

use Closure;
use JardisSupport\Contract\Identity\UserStatus;
use JardisSupport\Identity\Data\User;

/** Builds a User from a stored row. */
final class HydrateUser
{
    public function __construct(private readonly Closure $parseTimestamp)
    {
    }

    /** @param array<string, mixed> $row */
    public function __invoke(array $row): User
    {
        return new User(
            id: (string) $row['id'],
            email: (string) $row['email'],
            displayName: (string) $row['display_name'],
            status: UserStatus::from((string) $row['status']),
            createdAt: ($this->parseTimestamp)($row['created_at']),
        );
    }
}
