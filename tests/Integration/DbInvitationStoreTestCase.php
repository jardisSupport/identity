<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use DateTimeImmutable;
use InvalidArgumentException;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Contract\Repository\Exception\UniqueViolationException;
use JardisSupport\Identity\Data\Invitation;
use JardisSupport\Identity\DbInvitationStore;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use JardisSupport\Identity\Tests\Support\Moment;
use PHPUnit\Framework\TestCase;

/** InvitationStoreInterface behaviour of DbInvitationStore against a real database (one subclass per driver). */
abstract class DbInvitationStoreTestCase extends TestCase
{
    protected DbConnectionInterface $connection;
    protected DbInvitationStore $store;

    abstract protected function createConnection(): DbConnectionInterface;

    protected function setUp(): void
    {
        $this->connection = $this->createConnection();
        IdentityTables::recreate($this->connection);
        $this->store = new DbInvitationStore($this->connection);
    }

    private function invitation(
        string $id = 'i-1',
        string $email = 'new@example.com',
        ?DateTimeImmutable $expiresAt = null,
        ?string $hash = null,
        string $tenantId = 't-1',
    ): Invitation {
        return Invitation::create(
            $id,
            $tenantId,
            $email,
            'member',
            $hash ?? hash('sha256', 'token-' . $id),
            $expiresAt ?? Moment::inSeconds(3600),
            Moment::fixed(),
        );
    }

    // (1) roundtrip
    public function testStoreAndFindRoundTripsAllFields(): void
    {
        $invitation = $this->invitation('i-1', 'new@example.com', Moment::fixed());
        $this->store->store($invitation->withAccepted(Moment::fixed())->withRevoked(Moment::fixed()));

        $found = $this->store->findByTokenHash(hash('sha256', 'token-i-1'));

        self::assertNotNull($found);
        self::assertSame('i-1', $found->getId());
        self::assertSame('t-1', $found->getTenantId());
        self::assertSame('new@example.com', $found->getEmail());
        self::assertSame('member', $found->getRole());
        self::assertSame(hash('sha256', 'token-i-1'), $found->getTokenHash());
        self::assertSame('2026-03-04 03:06:07.123456', Moment::utcText($found->getExpiresAt()));
        self::assertSame('2026-03-04 03:06:07.123456', Moment::utcText($found->getCreatedAt()));
        self::assertNotNull($found->getAcceptedAt());
        self::assertSame('2026-03-04 03:06:07.123456', Moment::utcText($found->getAcceptedAt()));
        self::assertNotNull($found->getRevokedAt());
        self::assertSame('2026-03-04 03:06:07.123456', Moment::utcText($found->getRevokedAt()));
    }

    public function testOpenInvitationHasNoAcceptedOrRevokedMoment(): void
    {
        $this->store->store($this->invitation());

        $found = $this->store->findByTokenHash(hash('sha256', 'token-i-1'));

        self::assertNull($found?->getAcceptedAt());
        self::assertNull($found?->getRevokedAt());
    }

    // (2) unknown -> null
    public function testUnknownReturnsNull(): void
    {
        self::assertNull($this->store->findByTokenHash(hash('sha256', 'missing')));
        self::assertNull($this->store->findPending('t-1', 'nobody@example.com'));
        self::assertSame([], $this->store->listPendingForTenant('t-1'));
    }

    // (3) duplicate -> UniqueViolationException
    public function testDuplicateTokenHashThrowsUniqueViolation(): void
    {
        $this->store->store($this->invitation('i-1', hash: 'same-hash'));

        $this->expectException(UniqueViolationException::class);

        $this->store->store($this->invitation('i-2', hash: 'same-hash'));
    }

    // (4) custom prefix
    public function testCustomPrefix(): void
    {
        IdentityTables::recreate($this->connection, 'acme_');
        $store = new DbInvitationStore($this->connection, 'acme_');

        $store->store($this->invitation());

        self::assertNotNull($store->findByTokenHash(hash('sha256', 'token-i-1')));
        self::assertNull($this->store->findByTokenHash(hash('sha256', 'token-i-1')));
    }

    // (5) invalid prefix
    public function testInvalidPrefixIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DbInvitationStore($this->connection, 'x y');
    }

    // (6) only the hash is persisted
    public function testOnlyTheHashIsStoredNeverThePlainToken(): void
    {
        $plain = 'plain-token-value-1234567890';
        $this->store->store($this->invitation('i-1', hash: hash('sha256', $plain)));

        $dump = json_encode(
            $this->connection->pdo()->query('SELECT * FROM identity_invitations')->fetchAll(),
            JSON_THROW_ON_ERROR,
        );

        self::assertStringNotContainsString($plain, $dump);
        self::assertStringContainsString(hash('sha256', $plain), $dump);
    }

    // (7) markAccepted CAS
    public function testMarkAcceptedSucceedsOnceThenReturnsFalse(): void
    {
        $this->store->store($this->invitation());
        $at = Moment::fixed();

        self::assertTrue($this->store->markAccepted('i-1', $at));
        self::assertFalse($this->store->markAccepted('i-1', $at));

        $found = $this->store->findByTokenHash(hash('sha256', 'token-i-1'));
        self::assertNotNull($found?->getAcceptedAt());
        self::assertSame('2026-03-04 03:06:07.123456', Moment::utcText($found->getAcceptedAt()));
    }

    public function testMarkAcceptedFailsOnRevokedAndUnknownInvitation(): void
    {
        $this->store->store($this->invitation());
        $this->store->markRevoked('i-1', Moment::fixed());

        self::assertFalse($this->store->markAccepted('i-1', Moment::fixed()));
        self::assertFalse($this->store->markAccepted('missing', Moment::fixed()));
    }

    public function testMarkRevokedSetsTheMoment(): void
    {
        $this->store->store($this->invitation());

        $this->store->markRevoked('i-1', Moment::fixed());

        $found = $this->store->findByTokenHash(hash('sha256', 'token-i-1'));
        self::assertNotNull($found?->getRevokedAt());
        self::assertSame('2026-03-04 03:06:07.123456', Moment::utcText($found->getRevokedAt()));
    }

    // (8) pending = not accepted, not revoked, not expired
    public function testFindPendingIgnoresExpiredRevokedAndAccepted(): void
    {
        $this->store->store($this->invitation('i-expired', 'x@example.com', Moment::inSeconds(-60)));
        $this->store->store($this->invitation('i-revoked', 'x@example.com'));
        $this->store->markRevoked('i-revoked', Moment::inSeconds(-5));
        $this->store->store($this->invitation('i-accepted', 'x@example.com'));
        $this->store->markAccepted('i-accepted', Moment::inSeconds(-5));

        self::assertNull($this->store->findPending('t-1', 'x@example.com'));

        $this->store->store($this->invitation('i-open', 'x@example.com'));

        self::assertSame('i-open', $this->store->findPending('t-1', 'x@example.com')?->getId());
    }

    public function testFindPendingIsScopedToTenantAndEmail(): void
    {
        $this->store->store($this->invitation('i-1', 'x@example.com', tenantId: 't-1'));

        self::assertNull($this->store->findPending('t-2', 'x@example.com'));
        self::assertNull($this->store->findPending('t-1', 'y@example.com'));
    }

    public function testListPendingForTenantReturnsOnlyOpenInvitationsOfThatTenant(): void
    {
        $this->store->store($this->invitation('i-1', 'a@example.com'));
        $this->store->store($this->invitation('i-2', 'b@example.com'));
        $this->store->store($this->invitation('i-expired', 'c@example.com', Moment::inSeconds(-60)));
        $this->store->store($this->invitation('i-other', 'd@example.com', tenantId: 't-2'));
        $this->store->markAccepted('i-2', Moment::inSeconds(-5));

        $ids = array_map(static fn ($i): string => $i->getId(), $this->store->listPendingForTenant('t-1'));

        self::assertSame(['i-1'], $ids);
    }
}
