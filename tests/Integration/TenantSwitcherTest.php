<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisSupport\Auth\Data\Credential;
use JardisSupport\Auth\Data\HashedToken;
use JardisSupport\Auth\Data\Subject;
use JardisSupport\Auth\Exception\AuthenticationException;
use JardisSupport\Auth\TokenAuthenticator;
use JardisSupport\Contract\Auth\SessionClaim;
use JardisSupport\Identity\Data\Event\TenantSwitched;
use JardisSupport\Identity\Exception\NotAMemberException;
use JardisSupport\Identity\Exception\NotYourSessionException;
use JardisSupport\Identity\Exception\UserDisabledException;
use JardisSupport\Identity\TenantSwitcher;
use JardisSupport\Identity\Tests\Support\Wiring;
use PHPUnit\Framework\TestCase;

final class TenantSwitcherTest extends TestCase
{
    private Wiring $w;
    private TenantSwitcher $switcher;
    private string $userId;
    private string $acmeId;
    private string $betaId;

    protected function setUp(): void
    {
        $this->w = new Wiring();
        $this->switcher = new TenantSwitcher($this->w->users, $this->w->memberships, $this->w->tokens, $this->w->sessions);
        $this->userId = $this->w->userManager->register('max@acme.test', 'secret-1', 'Max')->user->getId();
        $this->acmeId = $this->w->tenantManager->create('ACME', 'acme')->tenant->getId();
        $this->betaId = $this->w->tenantManager->create('Beta', 'beta')->tenant->getId();
        $this->w->membershipManager->add($this->userId, $this->acmeId, 'admin');
        $this->w->membershipManager->add($this->userId, $this->betaId, 'viewer');
    }

    public function testMatrix13SwitchWithoutMembershipIsRejected(): void
    {
        $other = $this->w->tenantManager->create('Other', 'other')->tenant->getId();

        $this->expectException(NotAMemberException::class);
        $this->switcher->switch($this->userId, $other, null);
    }

    public function testMatrix14SwitchOfDisabledUserIsRejected(): void
    {
        $this->w->userManager->disable($this->userId);

        $this->expectException(UserDisabledException::class);
        $this->switcher->switch($this->userId, $this->acmeId, null);
    }

    public function testMatrix15ForeignSessionReferenceIsRejected(): void
    {
        $otherId = $this->w->userManager->register('eve@acme.test', 'secret-1', 'Eve')->user->getId();
        $foreign = $this->w->sessions->create(Subject::from($otherId));

        try {
            $this->switcher->switch($this->userId, $this->acmeId, HashedToken::hashOf($foreign->accessToken));
            self::fail('foreign session reference must be rejected');
        } catch (NotYourSessionException) {
            $auth = new TokenAuthenticator($this->w->tokens);
            self::assertTrue($auth->authenticate(Credential::token($foreign->accessToken))->isSuccess());
        }
    }

    public function testMatrix15bUnknownSessionReferenceIsRejected(): void
    {
        $this->expectException(NotYourSessionException::class);
        $this->switcher->switch($this->userId, $this->acmeId, 'unknown-reference');
    }

    public function testMatrix16SwitchRevokesAccessAndRefresh(): void
    {
        $old = $this->w->sessions->create(Subject::from($this->userId), [SessionClaim::TENANT => $this->acmeId]);
        $auth = new TokenAuthenticator($this->w->tokens);
        $ref = $auth->authenticate(Credential::token($old->accessToken))->getSessionReference();
        self::assertNotNull($ref);

        $switched = $this->switcher->switch($this->userId, $this->betaId, $ref);

        self::assertFalse($auth->authenticate(Credential::token($old->accessToken))->isSuccess());
        try {
            $this->w->sessions->refresh($old->refreshToken);
            self::fail('refresh of the old session must fail');
        } catch (AuthenticationException) {
            self::assertTrue($auth->authenticate(Credential::token($switched->session->accessToken))->isSuccess());
        }
    }

    public function testMatrix20SwitchEmitsEventAndCarriesTenantAndRole(): void
    {
        $result = $this->switcher->switch($this->userId, $this->betaId, null);

        $event = $result->events[0];
        self::assertInstanceOf(TenantSwitched::class, $event);
        self::assertSame([$this->userId, $this->betaId], [$event->userId, $event->tenantId]);
        self::assertSame('viewer', $result->session->session->metadata[SessionClaim::ROLE]);
        self::assertSame($this->betaId, $result->session->session->metadata[SessionClaim::TENANT]);
    }
}
