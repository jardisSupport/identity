<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Data\Identity;
use JardisSupport\Identity\Data\Event\TenantCreated;
use JardisSupport\Identity\DbTenantStore;
use JardisSupport\Identity\Exception\InvalidSlugException;
use JardisSupport\Identity\Exception\SlugAlreadyTakenException;
use JardisSupport\Identity\TenantManager;
use JardisSupport\Identity\Tests\Support\Connections;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use PHPUnit\Framework\TestCase;

final class TenantManagerTest extends TestCase
{
    private DbTenantStore $tenants;
    private TenantManager $manager;

    protected function setUp(): void
    {
        $connection = Connections::sqlite();
        IdentityTables::recreate($connection);
        $this->tenants = new DbTenantStore($connection);
        $this->manager = new TenantManager($this->tenants, new Identity());
    }

    public function testCreateStoresTenantAndEmitsEvent(): void
    {
        $result = $this->manager->create('ACME Inc.', 'acme');

        self::assertSame('acme', $result->tenant->getSlug());
        self::assertSame('ACME Inc.', $result->tenant->getName());
        self::assertSame($result->tenant->getId(), $this->tenants->findBySlug('acme')?->getId());
        $event = $result->events[0];
        self::assertInstanceOf(TenantCreated::class, $event);
        self::assertSame($result->tenant->getId(), $event->tenantId);
        self::assertSame('acme', $event->slug);
        self::assertSame('UTC', $event->timestamp->getTimezone()->getName());
    }

    public function testDuplicateSlugIsRejected(): void
    {
        $this->manager->create('ACME Inc.', 'acme');

        $this->expectException(SlugAlreadyTakenException::class);
        $this->manager->create('Other', 'acme');
    }

    public function testInvalidSlugIsRejected(): void
    {
        $this->expectException(InvalidSlugException::class);
        $this->manager->create('ACME Inc.', 'Not A Slug!');
    }
}
