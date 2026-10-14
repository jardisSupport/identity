<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration\Handler\Shared;

use JardisSupport\Identity\Handler\Shared\NormalizeEmail;
use PHPUnit\Framework\TestCase;

final class NormalizeEmailTest extends TestCase
{
    public function testInvokeLowerCasesAddress(): void
    {
        $this->assertSame('max.mustermann@example.com', (new NormalizeEmail())('Max.Mustermann@EXAMPLE.com'));
    }

    public function testInvokeTrimsSurroundingWhitespace(): void
    {
        $this->assertSame('a@b.de', (new NormalizeEmail())("  a@b.de \t\n"));
    }

    public function testInvokeLowerCasesMultibyteCharacters(): void
    {
        $this->assertSame('ärzte@example.com', (new NormalizeEmail())('ÄRZTE@Example.com'));
    }
}
