<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration\Handler\Shared;

use InvalidArgumentException;
use JardisSupport\Identity\Handler\Shared\AssertTablePrefix;
use PHPUnit\Framework\TestCase;

final class AssertTablePrefixTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validPrefixes(): array
    {
        return [
            'empty' => [''],
            'default' => ['identity_'],
            'leading underscore' => ['_app1_'],
        ];
    }

    /**
     * @dataProvider validPrefixes
     */
    public function testInvokeReturnsValidPrefix(string $prefix): void
    {
        $this->assertSame($prefix, (new AssertTablePrefix())($prefix));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPrefixes(): array
    {
        return [
            'leading digit' => ['1app_'],
            'hyphen' => ['my-app_'],
            'dot' => ['schema.'],
            'injection' => ['x; DROP TABLE users; --'],
            'space' => ['a b'],
        ];
    }

    /**
     * @dataProvider invalidPrefixes
     */
    public function testInvokeRejectsInvalidPrefix(string $prefix): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new AssertTablePrefix())($prefix);
    }
}
