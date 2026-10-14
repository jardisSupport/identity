<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration\Data;

use DateTimeImmutable;
use JardisSupport\Identity\Data\Invitation;
use PHPUnit\Framework\TestCase;

final class InvitationTest extends TestCase
{
    private function pending(): Invitation
    {
        return Invitation::create(
            'i1',
            't1',
            'a@b.de',
            'editor',
            'hash',
            new DateTimeImmutable('2026-02-01'),
            new DateTimeImmutable('2026-01-01'),
        );
    }

    public function testCreateYieldsPendingInvitation(): void
    {
        $invitation = $this->pending();

        $this->assertNull($invitation->getAcceptedAt());
        $this->assertNull($invitation->getRevokedAt());
    }

    public function testWithAcceptedSetsOnlyAcceptedAt(): void
    {
        $at = new DateTimeImmutable('2026-01-02');
        $invitation = $this->pending();

        $accepted = $invitation->withAccepted($at);

        $this->assertNull($invitation->getAcceptedAt());
        $this->assertSame($at, $accepted->getAcceptedAt());
        $this->assertNull($accepted->getRevokedAt());
        $this->assertSame('hash', $accepted->getTokenHash());
        $this->assertSame('editor', $accepted->getRole());
    }

    public function testWithRevokedSetsOnlyRevokedAt(): void
    {
        $at = new DateTimeImmutable('2026-01-02');

        $revoked = $this->pending()->withRevoked($at);

        $this->assertSame($at, $revoked->getRevokedAt());
        $this->assertNull($revoked->getAcceptedAt());
        $this->assertSame('a@b.de', $revoked->getEmail());
    }
}
