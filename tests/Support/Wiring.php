<?php

declare(strict_types=1);

namespace JardisSupport\Identity\Tests\Support;

use JardisSupport\Auth\DbTokenStore;
use JardisSupport\Auth\PasswordHasher;
use JardisSupport\Auth\SessionManager;
use JardisSupport\Auth\TokenIssuer;
use JardisSupport\Auth\TokenRedeemer;
use JardisSupport\Contract\DbConnection\DbConnectionInterface;
use JardisSupport\Data\Identity;
use JardisSupport\Identity\DbInvitationStore;
use JardisSupport\Identity\DbMembershipStore;
use JardisSupport\Identity\DbTenantStore;
use JardisSupport\Identity\DbUserStore;
use JardisSupport\Identity\MembershipManager;
use JardisSupport\Identity\DbGroupMemberStore;
use JardisSupport\Identity\TenantManager;
use JardisSupport\Identity\UserManager;

/** Test helper: real SQLite stores plus the auth components, wired on one connection. */
final class Wiring
{
    public readonly DbConnectionInterface $connection;
    public readonly Identity $ids;
    public readonly PasswordHasher $hasher;
    public readonly DbTokenStore $tokens;
    public readonly SessionManager $sessions;
    public readonly TokenIssuer $issuer;
    public readonly TokenRedeemer $redeemer;
    public readonly DbUserStore $users;
    public readonly DbTenantStore $tenants;
    public readonly DbMembershipStore $memberships;
    public readonly DbInvitationStore $invitations;
    public readonly UserManager $userManager;
    public readonly TenantManager $tenantManager;
    public readonly MembershipManager $membershipManager;

    public function __construct()
    {
        $this->connection = Connections::sqlite();
        IdentityTables::recreate($this->connection);

        $this->ids = new Identity();
        $this->hasher = PasswordHasher::argon2id(memoryCost: 8192, timeCost: 1);
        $this->tokens = new DbTokenStore($this->connection);
        $this->sessions = new SessionManager($this->tokens);
        $this->issuer = new TokenIssuer($this->tokens);
        $this->redeemer = new TokenRedeemer($this->tokens);
        $this->users = new DbUserStore($this->connection);
        $this->tenants = new DbTenantStore($this->connection);
        $this->memberships = new DbMembershipStore($this->connection);
        $this->invitations = new DbInvitationStore($this->connection);

        $this->userManager = new UserManager($this->users, $this->hasher, $this->ids, $this->sessions);
        $this->tenantManager = new TenantManager($this->tenants, $this->ids);
        $this->membershipManager = new MembershipManager(
            $this->memberships,
            new DbGroupMemberStore($this->connection),
            $this->users,
            $this->tenants,
        );
    }
}
