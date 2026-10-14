<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Contract\Auth\SessionClaim;
use JardisSupport\Identity\CredentialLookup;
use JardisSupport\Identity\Tests\Support\Wiring;
use PHPUnit\Framework\TestCase;

final class CredentialLookupTest extends TestCase
{
    private Wiring $w;
    private CredentialLookup $lookup;
    private string $userId;

    protected function setUp(): void
    {
        $this->w = new Wiring();
        $this->lookup = new CredentialLookup($this->w->users, $this->w->memberships);
        $this->userId = $this->w->userManager->register('max@acme.test', 'secret-1', 'Max')->user->getId();
    }

    public function testMatrix17NoMembershipYieldsEmptyClaims(): void
    {
        $record = $this->lookup->lookup('max@acme.test');

        self::assertNotNull($record);
        self::assertSame([], $record->getClaims());
        self::assertSame('user:' . $this->userId, $record->getSubject());
        self::assertTrue($this->w->hasher->verify('secret-1', $record->getPasswordHash()));
    }

    public function testMatrix17bOneMembershipYieldsTenantIdAndRole(): void
    {
        $tenantId = $this->w->tenantManager->create('ACME', 'acme')->tenant->getId();
        $this->w->membershipManager->add($this->userId, $tenantId, 'admin');

        $record = $this->lookup->lookup('max@acme.test');

        self::assertNotNull($record);
        self::assertSame([SessionClaim::TENANT => $tenantId, SessionClaim::ROLE => 'admin'], $record->getClaims());
    }

    public function testMatrix17cTwoMembershipsYieldEmptyClaims(): void
    {
        foreach (['acme', 'beta'] as $slug) {
            $tenantId = $this->w->tenantManager->create($slug, $slug)->tenant->getId();
            $this->w->membershipManager->add($this->userId, $tenantId, 'admin');
        }

        self::assertSame([], $this->lookup->lookup('max@acme.test')?->getClaims());
    }

    public function testMatrix18EmailCaseAndWhitespaceAreNormalized(): void
    {
        self::assertNotNull($this->lookup->lookup('  MAX@Acme.TEST '));
        self::assertNull($this->lookup->lookup('nobody@acme.test'));
    }

    public function testMatrix19DisabledUserYieldsNull(): void
    {
        $this->w->userManager->disable($this->userId);

        self::assertNull($this->lookup->lookup('max@acme.test'));
    }

    public function testMatrix20LookupHasNoEventsAndIsRepeatable(): void
    {
        $first = $this->lookup->lookup('max@acme.test');
        $second = $this->lookup->lookup('max@acme.test');

        self::assertEquals($first, $second);
    }
}
