<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Handler\Tenant;

use Closure;
use DateTimeImmutable;
use JardisSupport\Contract\Data\IdentityInterface;
use JardisSupport\Contract\Identity\TenantStoreInterface;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\Event\TenantCreated;
use JardisSupport\Identity\Data\Tenant;
use JardisSupport\Identity\Data\TenantResult;
use JardisSupport\Identity\Exception\SlugAlreadyTakenException;

/** Creates a tenant: validates the slug and rejects a slug that is already taken. */
final class CreateTenant
{
    /**
     * @param Closure(string): string $assertSlug
     */
    public function __construct(
        private readonly TenantStoreInterface $tenants,
        private readonly IdentityInterface $ids,
        private readonly Closure $assertSlug,
    ) {
    }

    public function __invoke(string $name, string $slug): TenantResult
    {
        ($this->assertSlug)($slug);

        if ($this->tenants->findBySlug($slug) !== null) {
            throw new SlugAlreadyTakenException(sprintf('Slug "%s" is already taken', $slug));
        }

        $now = new DateTimeImmutable();
        $tenant = new Tenant($this->ids->generateUuid7(), $slug, $name, $now);

        try {
            $this->tenants->store($tenant);
        } catch (UniqueViolationException $e) {
            throw new SlugAlreadyTakenException(sprintf('Slug "%s" is already taken', $slug), 0, $e);
        }

        return new TenantResult($tenant, [new TenantCreated($tenant->getId(), $slug, $now)]);
    }
}
