<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Exception;

/** Thrown when a password is shorter than the configured minimum. */
class WeakPasswordException extends IdentityException
{
}
