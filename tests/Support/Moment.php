<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;

/** Test helper: fixed and relative points in time. */
final class Moment
{
    /** A fixed non-UTC moment with microseconds (stored as UTC: 2026-03-04 03:06:07.123456). */
    public static function fixed(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-03-04 05:06:07.123456', new DateTimeZone('+02:00'));
    }

    public static function inSeconds(int $seconds): DateTimeImmutable
    {
        return new DateTimeImmutable(sprintf('%+d seconds', $seconds));
    }

    public static function utcText(\DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s.u');
    }
}
