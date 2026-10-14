<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Exception;

/** Thrown when a session reference does not belong to the acting user. */
class NotYourSessionException extends IdentityException
{
}
