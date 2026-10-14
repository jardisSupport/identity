<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Shared;

/** Normalizes an e-mail address for storage and comparison: trimmed and lower-cased. */
final class NormalizeEmail
{
    public function __invoke(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
