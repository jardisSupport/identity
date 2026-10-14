<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Shared;

use JardisSupport\Identity\Exception\InvalidSlugException;
use JardisSupport\Validation\Validator\Format;
use JardisSupport\Validation\Validator\Length;

/** Guards a tenant slug: lower-case alphanumerics with single inner hyphens, 2 to 63 characters. */
final class AssertSlug
{
    private const MIN_LENGTH = 2;
    private const MAX_LENGTH = 63;

    public function __invoke(string $slug): string
    {
        $error = (new Length())->validateValue($slug, Length::between(self::MIN_LENGTH, self::MAX_LENGTH))
            ?? (new Format())->validateValue($slug, Format::slug());
        if ($error !== null) {
            throw new InvalidSlugException(sprintf('Invalid slug "%s": %s', $slug, $error));
        }

        return $slug;
    }
}
