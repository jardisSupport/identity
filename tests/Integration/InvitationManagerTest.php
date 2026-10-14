<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Auth\Exception\TokenRevokedException;
use JardisSupport\Identity\Data\Event\InvitationAccepted;
use JardisSupport\Identity\Data\Event\InvitationCreated;
use JardisSupport\Identity\Data\Event\MembershipAdded;
use JardisSupport\Identity\Data\Event\UserRegistered;
use JardisSupport\Identity\Exception\InvalidEmailException;
use JardisSupport\Identity\Exception\InvitationInvalidException;
use JardisSupport\Identity\Exception\MembershipExistsException;
use JardisSupport\Identity\Exception\PasswordRequiredException;
use JardisSupport\Identity\Exception\UserDisabledException;
use JardisSupport\Identity\InvitationManager;
use JardisSupport\Identity\Tests\Support\Wiring;
use PHPUnit\Framework\TestCase;

final class InvitationManagerTest extends TestCase
{
    private Wiring $w;
    private InvitationManager $manager;
    private string $tenantId;

    protected function setUp(): void
    {
        $this->w = new Wiring();
        $this->manager = new InvitationManager(
            $this->w->invitations,
            $this->w->users,
            $this->w->memberships,
            $this->w->hasher,
            $this->w->ids,
            $this->w->issuer,
            $this->w->redeemer,
            $this->w->tokens,
            $this->w->connection,
        );
        $this->tenantId = $this->w->tenantManager->create('ACME', 'acme')->tenant->getId();
    }

    private function rows(string $table): int
    {
        return (int) $this->w->connection->pdo()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    }

    public function testMatrix01UnknownInvitationIsRejected(): void
    {
        $this->expectException(InvitationInvalidException::class);
        $this->manager->accept('not-a-token', 'secret-pw');
    }

    public function testMatrix02ExpiredInvitationIsRejected(): void
    {
        $invite = $this->manager->invite($this->tenantId, 'a@acme.test', 'editor');
        $this->w->connection->pdo()->exec("UPDATE identity_invitations SET expires_at = '2000-01-01 00:00:00.000000'");

        try {
            $this->manager->accept($invite->token, 'secret-pw');
            self::fail('expired invitation must not be accepted');
        } catch (InvitationInvalidException) {
            self::assertSame(0, $this->rows('identity_users'));
        }
    }

    public function testMatrix02bExpiredAuthTokenIsRejectedWithPrevious(): void
    {
        $invite = $this->manager->invite($this->tenantId, 'a@acme.test', 'editor');
        $this->w->connection->pdo()->exec("UPDATE auth_tokens SET expires_at = '2000-01-01 00:00:00.000000'");

        try {
            $this->manager->accept($invite->token, 'secret-pw');
            self::fail('expired token must not be accepted');
        } catch (InvitationInvalidException $e) {
            self::assertNotNull($e->getPrevious());
        }
    }

    public function testMatrix03SecondRedemptionIsRejected(): void
    {
        $invite = $this->manager->invite($this->tenantId, 'a@acme.test', 'editor');
        $this->manager->accept($invite->token, 'secret-pw');

        $this->expectException(InvitationInvalidException::class);
        $this->manager->accept($invite->token, 'secret-pw');
    }

    public function testMatrix04ReplacedInvitationIsRevoked(): void
    {
        $first = $this->manager->invite($this->tenantId, 'a@acme.test', 'editor');
        $second = $this->manager->invite($this->tenantId, 'A@ACME.test', 'viewer');

        self::assertCount(1, $this->manager->pendingForTenant($this->tenantId));
        try {
            $this->manager->accept($first->token, 'secret-pw');
            self::fail('replaced invitation must not be accepted');
        } catch (InvitationInvalidException) {
            self::assertSame(0, $this->rows('identity_users'));
        }

        self::assertSame('viewer', $this->manager->accept($second->token, 'secret-pw')->membership->getRole());
    }

    public function testMatrix04bReplacedInvitationTokenIsRevokedInTheTokenStore(): void
    {
        $first = $this->manager->invite($this->tenantId, 'a@acme.test', 'editor');
        $firstHash = $first->invitation->getTokenHash();
        self::assertFalse($this->w->tokens->find($firstHash)?->isRevoked());

        $second = $this->manager->invite($this->tenantId, 'a@acme.test', 'viewer');

        self::assertTrue($this->w->tokens->find($firstHash)?->isRevoked());
        self::assertFalse($this->w->tokens->find($second->invitation->getTokenHash())?->isRevoked());
        try {
            $this->manager->accept($first->token, 'secret-pw');
            self::fail('replaced invitation token must not redeem');
        } catch (InvitationInvalidException $e) {
            self::assertInstanceOf(TokenRevokedException::class, $e->getPrevious());
        }
    }

    public function testMatrix05PasswordIsRequiredForNewEmail(): void
    {
        $invite = $this->manager->invite($this->tenantId, 'a@acme.test', 'editor');

        try {
            $this->manager->accept($invite->token);
            self::fail('password is required');
        } catch (PasswordRequiredException) {
            // rolled back: the invitation stays usable
        }
        self::assertSame(0, $this->rows('identity_users'));
        self::assertCount(1, $this->manager->pendingForTenant($this->tenantId));
        self::assertSame('editor', $this->manager->accept($invite->token, 'secret-pw')->membership->getRole());
    }

    public function testMatrix06ExistingUserGetsOnlyMembershipAndPasswordIsIgnored(): void
    {
        $user = $this->w->userManager->register('a@acme.test', 'original-pw', 'A')->user;
        $invite = $this->manager->invite($this->tenantId, 'a@acme.test', 'editor');

        $result = $this->manager->accept($invite->token, 'other-password');

        self::assertSame($user->getId(), $result->user->getId());
        self::assertSame('editor', $result->membership->getRole());
        self::assertSame(1, $this->rows('identity_users'));
        self::assertTrue($this->w->hasher->verify('original-pw', (string) $this->w->users->passwordHashOf($user->getId())));
    }

    public function testMatrix07ExistingMemberIsRejected(): void
    {
        $user = $this->w->userManager->register('a@acme.test', 'original-pw', 'A')->user;
        $this->w->membershipManager->add($user->getId(), $this->tenantId, 'admin');
        $invite = $this->manager->invite($this->tenantId, 'a@acme.test', 'editor');

        $this->expectException(MembershipExistsException::class);
        $this->manager->accept($invite->token);
    }

    public function testMatrix08DisabledExistingUserIsRejectedAndInvitationStaysOpen(): void
    {
        $user = $this->w->userManager->register('a@acme.test', 'original-pw', 'A')->user;
        $this->w->userManager->disable($user->getId());
        $invite = $this->manager->invite($this->tenantId, 'a@acme.test', 'editor');

        try {
            $this->manager->accept($invite->token);
            self::fail('disabled user must be rejected');
        } catch (UserDisabledException) {
            self::assertCount(1, $this->manager->pendingForTenant($this->tenantId), 'transaction rolled back');
            self::assertNull($this->w->memberships->find($user->getId(), $this->tenantId));
        }
    }

    public function testMatrix20InviteAndAcceptEmitEvents(): void
    {
        $invite = $this->manager->invite($this->tenantId, 'New@Acme.test', 'editor');

        $created = $invite->events[0];
        self::assertInstanceOf(InvitationCreated::class, $created);
        self::assertSame([$invite->invitation->getId(), $this->tenantId, 'new@acme.test', 'editor'], [
            $created->invitationId,
            $created->tenantId,
            $created->email,
            $created->role,
        ]);

        $accepted = $this->manager->accept($invite->token, 'secret-pw', 'New');
        $types = array_map(static fn (object $e): string => $e::class, $accepted->events);
        self::assertContains(UserRegistered::class, $types);
        self::assertContains(MembershipAdded::class, $types);
        self::assertContains(InvitationAccepted::class, $types);
        foreach ($accepted->events as $event) {
            if ($event instanceof InvitationAccepted) {
                self::assertSame([$invite->invitation->getId(), $accepted->user->getId()], [
                    $event->invitationId,
                    $event->userId,
                ]);
            }
        }
        self::assertSame('New', $accepted->user->getDisplayName());
    }

    public function testInviteRejectsInvalidEmail(): void
    {
        $this->expectException(InvalidEmailException::class);
        $this->manager->invite($this->tenantId, 'not-an-email', 'editor');
    }
}
