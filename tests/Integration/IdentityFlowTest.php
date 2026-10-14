<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Integration;

use JardisAdapter\DbConnection\Factory\ConnectionFactory;
use JardisSupport\Auth\Data\Credential;
use JardisSupport\Auth\Data\HashedToken;
use JardisSupport\Auth\DbTokenStore;
use JardisSupport\Auth\Exception\AuthenticationException;
use JardisSupport\Auth\PasswordAuthenticator;
use JardisSupport\Auth\PasswordHasher;
use JardisSupport\Auth\SessionManager;
use JardisSupport\Auth\TokenAuthenticator;
use JardisSupport\Auth\TokenIssuer;
use JardisSupport\Auth\TokenRedeemer;
use JardisSupport\Contract\Auth\SessionClaim;
use JardisSupport\Data\Identity;
use JardisSupport\Identity\CredentialLookup;
use JardisSupport\Identity\Data\Event\InvitationAccepted;
use JardisSupport\Identity\Data\Event\TenantSwitched;
use JardisSupport\Identity\Data\Event\UserRegistered;
use JardisSupport\Identity\DbGroupMemberStore;
use JardisSupport\Identity\DbGroupStore;
use JardisSupport\Identity\DbInvitationStore;
use JardisSupport\Identity\DbMembershipStore;
use JardisSupport\Identity\DbTenantStore;
use JardisSupport\Identity\DbUserStore;
use JardisSupport\Identity\Exception\UserDisabledException;
use JardisSupport\Identity\GroupManager;
use JardisSupport\Identity\InvitationManager;
use JardisSupport\Identity\MembershipManager;
use JardisSupport\Identity\PasswordResetManager;
use JardisSupport\Identity\TenantManager;
use JardisSupport\Identity\TenantSwitcher;
use JardisSupport\Identity\Tests\Support\IdentityTables;
use JardisSupport\Identity\UserManager;
use PHPUnit\Framework\TestCase;

/**
 * Konzept-Artefakt (Stadium 0) und Akzeptanztest der Etappe 2:
 * der ganze Identity-Lebenslauf gegen echte Stores (SQLite) und echtes support/auth.
 */
final class IdentityFlowTest extends TestCase
{
    public function testRegisterInviteLoginSwitchTenantResetPassword(): void
    {
        // --- Verdrahtung: auth + identity auf einer Verbindung -------------------------------
        $connection = (new ConnectionFactory())->sqlite();
        IdentityTables::recreate($connection);           // identity_* + auth_tokens

        $ids = new Identity();
        $hasher = PasswordHasher::argon2id(memoryCost: 8192, timeCost: 1);
        $tokens = new DbTokenStore($connection);
        $sessions = new SessionManager($tokens);
        $issuer = new TokenIssuer($tokens);
        $redeemer = new TokenRedeemer($tokens);

        $users = new DbUserStore($connection);
        $tenants = new DbTenantStore($connection);
        $memberships = new DbMembershipStore($connection);
        $groups = new DbGroupStore($connection);
        $groupMembers = new DbGroupMemberStore($connection);
        $invitations = new DbInvitationStore($connection);

        $userManager = new UserManager($users, $hasher, $ids, $sessions);
        $tenantManager = new TenantManager($tenants, $ids);
        $membershipManager = new MembershipManager($memberships, $groupMembers, $users, $tenants, $connection);
        $groupManager = new GroupManager($groups, $groupMembers, $memberships, $ids);
        $invitationManager = new InvitationManager($invitations, $users, $memberships, $hasher, $ids, $issuer, $redeemer, $tokens, $connection);
        $resetManager = new PasswordResetManager($users, $hasher, $issuer, $redeemer, $sessions);
        $switcher = new TenantSwitcher($users, $memberships, $tokens, $sessions);
        $login = new PasswordAuthenticator($hasher, $sessions, new CredentialLookup($users, $memberships));
        $tokenAuth = new TokenAuthenticator($tokens);

        // --- A1/A5/A6: Registrieren (E-Mail wird normalisiert), Mandant, Mitgliedschaft -------
        $registered = $userManager->register('Owner@ACME.test', 'secret-1', 'Owner');
        $owner = $registered->user;
        self::assertSame('owner@acme.test', $owner->getEmail());
        self::assertInstanceOf(UserRegistered::class, $registered->events[0]);
        $acme = $tenantManager->create('ACME Inc.', 'acme')->tenant;
        $membershipManager->add($owner->getId(), $acme->getId(), 'admin');

        // --- A9/A10: Login über die Vertrags-Naht; genau eine Mitgliedschaft → Claims gesetzt
        $result = $login->authenticate(Credential::password('owner@acme.test', 'secret-1'));
        self::assertTrue($result->isSuccess());
        self::assertSame('user:' . $owner->getId(), $result->getSubject());
        self::assertSame($acme->getId(), $result->session->metadata[SessionClaim::TENANT]);
        self::assertSame('admin', $result->session->metadata[SessionClaim::ROLE]);

        // --- A11/A12: Einladung an eine unbekannte E-Mail → Konto + Mitgliedschaft -------------
        $invite = $invitationManager->invite($acme->getId(), 'dev@acme.test', 'editor');
        self::assertSame(HashedToken::hashOf($invite->token), $invite->invitation->getTokenHash());
        $dump = json_encode($connection->pdo()->query('SELECT * FROM identity_invitations')->fetchAll(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($invite->token, $dump, 'nur der Hash liegt im Store');

        $accepted = $invitationManager->accept($invite->token, password: 'secret-2', displayName: 'Dev');
        self::assertSame('editor', $accepted->membership->getRole());
        self::assertContainsOnlyInstancesOf(InvitationAccepted::class, array_filter(
            $accepted->events,
            static fn (object $e): bool => $e instanceof InvitationAccepted,
        ));
        self::assertCount(0, $invitationManager->pendingForTenant($acme->getId()));

        // --- A7: Gruppe nur für Mitglieder ---------------------------------------------------
        $group = $groupManager->create($acme->getId(), 'backend')->group;
        $groupManager->addMember($group->getId(), $accepted->user->getId());
        self::assertSame([$accepted->user->getId()], $groupManager->members($group->getId()));

        // --- A8/F3: zweiter Mandant, Login ohne Tenant-Claim, Wechsel = neue Sitzung ---------
        $beta = $tenantManager->create('Beta GmbH', 'beta')->tenant;
        $membershipManager->add($owner->getId(), $beta->getId(), 'viewer');

        $multi = $login->authenticate(Credential::password('owner@acme.test', 'secret-1'));
        self::assertArrayNotHasKey(SessionClaim::TENANT, $multi->session->metadata, 'zwei Mitgliedschaften → kein Default-Mandant');

        $before = $tokenAuth->authenticate(Credential::token($multi->accessToken));
        self::assertTrue($before->isSuccess());

        $switched = $switcher->switch($owner->getId(), $beta->getId(), $before->getSessionReference());
        self::assertInstanceOf(TenantSwitched::class, $switched->events[0]);
        self::assertSame('viewer', $switched->session->session->metadata[SessionClaim::ROLE]);
        self::assertSame($beta->getId(), $switched->session->session->metadata[SessionClaim::TENANT]);
        self::assertFalse($tokenAuth->authenticate(Credential::token($multi->accessToken))->isSuccess(), 'alte Sitzung widerrufen');
        self::assertSame($beta->getId(), $tokenAuth->authenticate(Credential::token($switched->session->accessToken))->getTenant());

        try {
            $sessions->refresh($multi->refreshToken);
            self::fail('alter Refresh-Token darf keine Sitzung mehr erzeugen (G3)');
        } catch (AuthenticationException) {
            // erwartet: Refresh-Token der alten Sitzung ist mit widerrufen
        }

        // --- A14/A15: Passwort zurücksetzen über auth-Token (PasswordReset) -------------------
        self::assertNull($resetManager->request('nobody@acme.test'), 'keine Enumeration');
        $reset = $resetManager->request('owner@acme.test');
        self::assertNotNull($reset);
        $resetManager->reset($reset->token, 'secret-3');
        self::assertFalse($login->authenticate(Credential::password('owner@acme.test', 'secret-1'))->isSuccess());
        self::assertTrue($login->authenticate(Credential::password('owner@acme.test', 'secret-3'))->isSuccess());
        self::assertFalse($tokenAuth->authenticate(Credential::token($switched->session->accessToken))->isSuccess(), 'Reset beendet alle Sitzungen');

        // --- A3: deaktiviert → Login, Wechsel und Reset verweigert ----------------------------
        $userManager->disable($owner->getId());
        self::assertFalse($login->authenticate(Credential::password('owner@acme.test', 'secret-3'))->isSuccess());
        self::assertNull($resetManager->request('owner@acme.test'));
        $this->expectException(UserDisabledException::class);
        $switcher->switch($owner->getId(), $acme->getId(), null);
    }
}
