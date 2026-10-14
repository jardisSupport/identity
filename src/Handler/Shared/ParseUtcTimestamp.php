<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Shared;

use DateTimeImmutable;
use DateTimeZone;

/** Parses a stored UTC timestamp text into an immutable point in time. */
final class ParseUtcTimestamp
{
    public function __invoke(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }
}
