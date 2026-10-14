<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Shared;

use InvalidArgumentException;

/** Guards the configurable table prefix: empty, or a plain identifier fragment. */
final class AssertTablePrefix
{
    public function __invoke(string $prefix): string
    {
        if ($prefix !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $prefix) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid table prefix "%s"', $prefix));
        }

        return $prefix;
    }
}
