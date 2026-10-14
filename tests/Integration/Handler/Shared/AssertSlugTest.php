<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration\Handler\Shared;

use JardisSupport\Identity\Exception\InvalidSlugException;
use JardisSupport\Identity\Handler\Shared\AssertSlug;
use PHPUnit\Framework\TestCase;

final class AssertSlugTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function validSlugs(): array
    {
        return [
            'two characters' => ['ab'],
            'with hyphen' => ['acme-inc'],
            'digits' => ['team42'],
            '63 characters' => [str_repeat('a', 63)],
        ];
    }

    /**
     * @dataProvider validSlugs
     */
    public function testInvokeReturnsValidSlug(string $slug): void
    {
        $this->assertSame($slug, (new AssertSlug())($slug));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidSlugs(): array
    {
        return [
            'one character' => ['a'],
            'empty' => [''],
            '64 characters' => [str_repeat('a', 64)],
            'upper case' => ['Acme'],
            'leading hyphen' => ['-acme'],
            'trailing hyphen' => ['acme-'],
            'double hyphen' => ['ac--me'],
            'space' => ['ac me'],
            'underscore' => ['ac_me'],
        ];
    }

    /**
     * @dataProvider invalidSlugs
     */
    public function testInvokeRejectsInvalidSlug(string $slug): void
    {
        $this->expectException(InvalidSlugException::class);

        (new AssertSlug())($slug);
    }
}
