<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Shared;

use JardisSupport\Identity\Exception\InvalidEmailException;
use JardisSupport\Validation\Validator\Email;

/** Guards an (already normalized) e-mail address with the validation package. */
final class AssertEmail
{
    public function __invoke(string $email): string
    {
        $error = (new Email())->validateValue($email, Email::basic());
        if ($error !== null) {
            throw new InvalidEmailException(sprintf('Invalid e-mail address "%s": %s', $email, $error));
        }

        return $email;
    }
}
