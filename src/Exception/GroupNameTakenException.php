<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Exception;

/** Thrown when the tenant already has a group with this name. */
class GroupNameTakenException extends IdentityException
{
}
