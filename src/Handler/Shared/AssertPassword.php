<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Shared;

use JardisSupport\Identity\Exception\WeakPasswordException;

/** Guards the minimum password length (characters). */
final class AssertPassword
{
    public function __construct(private readonly int $minLength = 8)
    {
    }

    public function __invoke(string $password): string
    {
        if (mb_strlen($password) < $this->minLength) {
            throw new WeakPasswordException(
                sprintf('Password must be at least %d characters long', $this->minLength)
            );
        }

        return $password;
    }
}
