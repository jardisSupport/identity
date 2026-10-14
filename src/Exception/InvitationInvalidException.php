<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Exception;

/** Thrown when an invitation is unknown, expired, revoked or already accepted. */
class InvitationInvalidException extends IdentityException
{
}
