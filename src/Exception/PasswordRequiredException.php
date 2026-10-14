<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Exception;

/** Thrown when a password is required to create a new user from an invitation. */
class PasswordRequiredException extends IdentityException
{
}
