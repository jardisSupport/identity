<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration\Handler\Shared;

use JardisSupport\Identity\Exception\IdentityException;
use JardisSupport\Identity\Exception\InvalidEmailException;
use JardisSupport\Identity\Handler\Shared\AssertEmail;
use PHPUnit\Framework\TestCase;

final class AssertEmailTest extends TestCase
{
    public function testInvokeReturnsValidAddress(): void
    {
        $this->assertSame('max@example.com', (new AssertEmail())('max@example.com'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidAddresses(): array
    {
        return [
            'empty' => [''],
            'no at sign' => ['max.example.com'],
            'no domain' => ['max@'],
            'whitespace inside' => ['ma x@example.com'],
        ];
    }

    /**
     * @dataProvider invalidAddresses
     */
    public function testInvokeRejectsInvalidAddress(string $address): void
    {
        $this->expectException(InvalidEmailException::class);

        (new AssertEmail())($address);
    }

    public function testInvalidEmailIsAnIdentityException(): void
    {
        $this->expectException(IdentityException::class);

        (new AssertEmail())('nope');
    }
}
