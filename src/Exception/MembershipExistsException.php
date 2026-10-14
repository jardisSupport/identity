<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Exception;

/** Thrown when the user is already a member of the tenant. */
class MembershipExistsException extends IdentityException
{
}
