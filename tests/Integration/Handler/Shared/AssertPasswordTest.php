<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration\Handler\Shared;

use JardisSupport\Identity\Exception\WeakPasswordException;
use JardisSupport\Identity\Handler\Shared\AssertPassword;
use PHPUnit\Framework\TestCase;

final class AssertPasswordTest extends TestCase
{
    public function testInvokeAcceptsPasswordWithMinimumLength(): void
    {
        $this->assertSame('12345678', (new AssertPassword(8))('12345678'));
    }

    public function testInvokeRejectsPasswordOneCharacterTooShort(): void
    {
        $this->expectException(WeakPasswordException::class);

        (new AssertPassword(8))('1234567');
    }

    public function testInvokeCountsCharactersNotBytes(): void
    {
        $this->assertSame('äöüäöüäö', (new AssertPassword(8))('äöüäöüäö'));
    }

    public function testInvokeUsesConfiguredMinimumLength(): void
    {
        $this->expectException(WeakPasswordException::class);

        (new AssertPassword(12))('1234567890');
    }
}
