<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration\Handler\Shared;

use DateTimeImmutable;
use DateTimeZone;
use JardisSupport\Identity\Handler\Shared\FormatUtcTimestamp;
use PHPUnit\Framework\TestCase;

final class FormatUtcTimestampTest extends TestCase
{
    public function testInvokeFormatsUtcMomentWithMicroseconds(): void
    {
        $moment = new DateTimeImmutable('2026-10-14 08:30:15.123456', new DateTimeZone('UTC'));

        $this->assertSame('2026-10-14 08:30:15.123456', (new FormatUtcTimestamp())($moment));
    }

    public function testInvokeConvertsOtherTimezoneToUtc(): void
    {
        $moment = new DateTimeImmutable('2026-10-14 10:30:15', new DateTimeZone('Europe/Berlin'));

        $this->assertSame('2026-10-14 08:30:15.000000', (new FormatUtcTimestamp())($moment));
    }
}
