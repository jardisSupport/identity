<?php

declare(strict_types=1);

namespace JardisSupport\Identity;

use Closure;
use JardisSupport\Contract\Data\IdentityInterface;
use JardisSupport\Contract\Identity\TenantStoreInterface;
use JardisSupport\Identity\Data\TenantResult;
use JardisSupport\Identity\Handler\Shared\AssertSlug;
use JardisSupport\Identity\Handler\Tenant\CreateTenant;

/** Orchestrates tenant creation. */
final class TenantManager
{
    private readonly Closure $createTenant;

    public function __construct(TenantStoreInterface $tenants, IdentityInterface $ids)
    {
        $this->createTenant = (new CreateTenant($tenants, $ids, (new AssertSlug())->__invoke(...)))->__invoke(...);
    }

    public function create(string $name, string $slug): TenantResult
    {
        return ($this->createTenant)($name, $slug);
    }
}
