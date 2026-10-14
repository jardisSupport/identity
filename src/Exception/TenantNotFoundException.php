<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Exception;

/** Thrown when a tenant id does not resolve to a tenant. */
class TenantNotFoundException extends IdentityException
{
}
