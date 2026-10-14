<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Exception;

/** Thrown when an operation requires an active user but the user is disabled. */
class UserDisabledException extends IdentityException
{
}
