# Jardis Identity

![Build Status](https://github.com/jardisSupport/identity/actions/workflows/ci.yml/badge.svg)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.3-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)

> Part of **[Jardis](https://jardis.io)** — the Domain-Driven Design platform for PHP. You model your domain; Jardis generates the production-ready hexagonal code (DTOs, Command/Query handlers, repositories, persistence). This package is part of the open-source foundation that generated code runs on.

**Identity management on top of `jardissupport/auth`.** Users, tenants, memberships, groups, invitations, password reset and tenant switching — framework-free, with SQL stores for MySQL/MariaDB and SQLite (PostgreSQL DDL included). No HTTP layer, no e-mail delivery. Pure support package.

---

## Why This Package?

- **Accounts without a framework** — register users, create tenants, attach members, organise them in groups, invite newcomers; all as plain PHP objects
- **Built on `auth`, not beside it** — passwords are hashed by `PasswordHasher`, one-time tokens (invitation, reset) are issued and redeemed by `TokenIssuer`/`TokenRedeemer`, sessions are created and ended by `SessionManager`. Identity owns the accounts, auth owns the credentials
- **Login seam included** — `CredentialLookup` plugs straight into `PasswordAuthenticator`; the session carries tenant and role as claims
- **Tenant switching is a new session** — the old access/refresh pair is revoked, no token is mutated
- **Safe by default** — only token hashes are stored, unknown e-mails never reveal themselves on password reset, reset and disable end every session of the user
- **Events are returned, never dispatched** — you decide how to publish them

---

## Installation

```bash
composer require jardissupport/identity
```

Ids are generated through `IdentityInterface` (`jardissupport/contracts`). The implementation `jardissupport/data` (UUID v7) is suggested; any `IdentityInterface` works.

---

## Quick Start

### Wiring

Identity and auth share one `DbConnectionInterface`. Apply the DDL of both packages once (see [Table DDL](#table-ddl)).

```php
use JardisAdapter\DbConnection\Factory\ConnectionFactory;
use JardisSupport\Auth\DbTokenStore;
use JardisSupport\Auth\PasswordAuthenticator;
use JardisSupport\Auth\PasswordHasher;
use JardisSupport\Auth\SessionManager;
use JardisSupport\Auth\TokenAuthenticator;
use JardisSupport\Auth\TokenIssuer;
use JardisSupport\Auth\TokenRedeemer;
use JardisSupport\Data\Identity;
use JardisSupport\Identity\CredentialLookup;
use JardisSupport\Identity\DbGroupMemberStore;
use JardisSupport\Identity\DbGroupStore;
use JardisSupport\Identity\DbInvitationStore;
use JardisSupport\Identity\DbMembershipStore;
use JardisSupport\Identity\DbTenantStore;
use JardisSupport\Identity\DbUserStore;
use JardisSupport\Identity\GroupManager;
use JardisSupport\Identity\InvitationManager;
use JardisSupport\Identity\MembershipManager;
use JardisSupport\Identity\PasswordResetManager;
use JardisSupport\Identity\TenantManager;
use JardisSupport\Identity\TenantSwitcher;
use JardisSupport\Identity\UserManager;

$connection = (new ConnectionFactory())->sqlite();   // any DbConnectionInterface

// auth
$ids      = new Identity();
$hasher   = PasswordHasher::argon2id();
$tokens   = new DbTokenStore($connection);
$sessions = new SessionManager($tokens);
$issuer   = new TokenIssuer($tokens);
$redeemer = new TokenRedeemer($tokens);

// identity stores (second argument: table prefix, default 'identity_')
$users        = new DbUserStore($connection);
$tenants      = new DbTenantStore($connection);
$memberships  = new DbMembershipStore($connection);
$groups       = new DbGroupStore($connection);
$groupMembers = new DbGroupMemberStore($connection);
$invitations  = new DbInvitationStore($connection);

// identity orchestrators
$userManager       = new UserManager($users, $hasher, $ids, $sessions);
$tenantManager     = new TenantManager($tenants, $ids);
$membershipManager = new MembershipManager($memberships, $groupMembers, $users, $tenants, $connection);
$groupManager      = new GroupManager($groups, $groupMembers, $memberships, $ids);
$invitationManager = new InvitationManager($invitations, $users, $memberships, $hasher, $ids, $issuer, $redeemer, $tokens, $connection);
$resetManager      = new PasswordResetManager($users, $hasher, $issuer, $redeemer, $sessions);
$switcher          = new TenantSwitcher($users, $memberships, $tokens, $sessions);

// login seam
$login     = new PasswordAuthenticator($hasher, $sessions, new CredentialLookup($users, $memberships));
$tokenAuth = new TokenAuthenticator($tokens);
```

Optional constructor arguments: `UserManager(..., int $minPasswordLength = 8)`, `InvitationManager(..., int $ttl = 604800, int $minPasswordLength = 8)` (seven days), `PasswordResetManager(..., int $ttl = 3600, int $minPasswordLength = 8)` (one hour).

### Register & Login

```php
use JardisSupport\Auth\Data\Credential;
use JardisSupport\Contract\Auth\SessionClaim;

$registered = $userManager->register('Owner@ACME.test', 'secret-1', 'Owner');
$owner = $registered->user;                  // e-mail is normalized: owner@acme.test
$registered->events;                         // [UserRegistered]

$result = $login->authenticate(Credential::password('owner@acme.test', 'secret-1'));
$result->isSuccess();
$result->getSubject();                       // 'user:<user id>'
$result->session->metadata[SessionClaim::TENANT];   // tenant id — only if the user has exactly ONE membership
$result->session->metadata[SessionClaim::ROLE];     // role of that membership
```

`CredentialLookup::lookup()` returns `null` for unknown and disabled users. With zero or several memberships no tenant/role claims are set — the user picks a tenant via the switcher.

### Tenant & Membership

```php
$acme = $tenantManager->create('ACME Inc.', 'acme')->tenant;    // TenantResult{tenant, events=[TenantCreated]}

$membershipManager->add($owner->getId(), $acme->getId(), 'admin');          // MembershipResult{membership, events=[MembershipAdded]}
$membershipManager->changeRole($owner->getId(), $acme->getId(), 'editor');  // MembershipResult{..., events=[MembershipRoleChanged]}
$membershipManager->forUser($owner->getId());                               // list<MembershipInterface>
$membershipManager->forTenant($acme->getId());                              // list<MembershipInterface>
$membershipManager->remove($owner->getId(), $acme->getId());                // MembershipRemoved (also removes the user from the tenant's groups)
```

Roles are free strings (`admin`, `editor`, ...); pair them with `auth`'s `Guard`/`Policy`.

### Groups

```php
$group = $groupManager->create($acme->getId(), 'backend')->group;           // GroupResult{group, events=[GroupCreated]}

$groupManager->addMember($group->getId(), $userId);       // GroupMemberAdded — the user must be a member of the group's tenant
$groupManager->members($group->getId());                  // list<string> user ids
$groupManager->forTenant($acme->getId());                 // list<GroupInterface>
$groupManager->removeMember($group->getId(), $userId);    // GroupMemberRemoved
```

### Invitations

```php
$invite = $invitationManager->invite($acme->getId(), 'dev@acme.test', 'editor');
$invite->token;        // plain one-time token — handed out ONCE, only its hash is stored
$invite->invitation;   // Invitation
$invite->events;       // [InvitationCreated]

// Sending the token (e-mail, link, chat) is the consumer's job.
// An open invitation for the same tenant + e-mail is revoked when a new one is issued: the row and its auth token (the old plain token no longer redeems).

$accepted = $invitationManager->accept($invite->token, password: 'secret-2', displayName: 'Dev');
$accepted->user;         // new account, or the existing one
$accepted->membership;
$accepted->events;       // [UserRegistered?, MembershipAdded, InvitationAccepted]

$invitationManager->pendingForTenant($acme->getId());   // list<InvitationInterface>
```

`accept()` runs in ONE transaction: redeem the token, claim the invitation, register the user (unknown e-mail, password required) or attach the existing one, add the membership. Any failure rolls everything back.

### Switch tenant

```php
$before = $tokenAuth->authenticate(Credential::token($accessToken));

$switched = $switcher->switch($userId, $betaTenantId, $before->getSessionReference());
$switched->session->accessToken;     // new session with tenant + role of the target membership
$switched->session->refreshToken;
$switched->events;                   // [TenantSwitched]
```

The new session is created first, then the current one is revoked — access token **and** its refresh token (`refresh()` with the old refresh token fails). Pass `null` as session reference to only create the new session. A reference that belongs to another subject throws `NotYourSessionException`.

### Password reset

```php
$requested = $resetManager->request('owner@acme.test');   // ?PasswordResetRequestResult — null for unknown or disabled users
$requested->token;                                         // send to the user (consumer's job)
$requested->events;                                        // [PasswordResetRequested]

$resetManager->reset($requested->token, 'secret-3');       // PasswordReset — ends ALL sessions of the user (global logout)
```

### Disable

```php
$userManager->changePassword($userId, 'secret-3', 'secret-4');   // PasswordChanged — current password must match, sessions stay
$userManager->disable($userId);   // UserDisabled — ends ALL sessions; login, switch and reset are refused afterwards
$userManager->enable($userId);    // UserEnabled
```

### Public API (orchestrators)

| Class | Method | Returns |
|-------|--------|---------|
| `UserManager` | `register(string $email, string $password, string $displayName)` | `UserResult` |
| | `changePassword(string $userId, string $current, string $new)` | `PasswordChanged` |
| | `disable(string $userId)` | `UserDisabled` |
| | `enable(string $userId)` | `UserEnabled` |
| `TenantManager` | `create(string $name, string $slug)` | `TenantResult` |
| `MembershipManager` | `add(string $userId, string $tenantId, string $role)` | `MembershipResult` |
| | `changeRole(string $userId, string $tenantId, string $role)` | `MembershipResult` |
| | `remove(string $userId, string $tenantId)` | `MembershipRemoved` |
| | `forUser(string $userId)` | `list<MembershipInterface>` |
| | `forTenant(string $tenantId)` | `list<MembershipInterface>` |
| `GroupManager` | `create(string $tenantId, string $name)` | `GroupResult` |
| | `addMember(string $groupId, string $userId)` | `GroupMemberAdded` |
| | `removeMember(string $groupId, string $userId)` | `GroupMemberRemoved` |
| | `members(string $groupId)` | `list<string>` |
| | `forTenant(string $tenantId)` | `list<GroupInterface>` |
| `InvitationManager` | `invite(string $tenantId, string $email, string $role)` | `InvitationResult` |
| | `accept(string $token, ?string $password = null, ?string $displayName = null)` | `AcceptResult` |
| | `pendingForTenant(string $tenantId)` | `list<InvitationInterface>` |
| `PasswordResetManager` | `request(string $email)` | `?PasswordResetRequestResult` |
| | `reset(string $token, string $newPassword)` | `PasswordReset` |
| `TenantSwitcher` | `switch(string $userId, string $tenantId, ?string $sessionRef)` | `TenantSwitchResult` |
| `CredentialLookup` | `lookup(string $identifier)` | `?CredentialRecordInterface` |

Methods with one event return it directly; methods with several events return a `*Result` with `list<object> $events`. The caller dispatches.

### Stores

Each store takes `(DbConnectionInterface $connection, string $prefix = 'identity_')` and implements the matching port from `jardissupport/contracts`.

| Store | Methods |
|-------|---------|
| `DbUserStore` | `store(UserInterface, string $passwordHash)` · `updateStatus` · `updatePasswordHash` · `findById` · `findByEmail` · `passwordHashOf` |
| `DbTenantStore` | `store` · `findById` · `findBySlug` |
| `DbMembershipStore` | `store` · `updateRole` · `remove` · `find` · `listForUser` · `listForTenant` |
| `DbGroupStore` | `store` · `findById` · `findByName(tenantId, name)` · `listForTenant` |
| `DbGroupMemberStore` | `add` · `remove` · `removeUserFromTenantGroups` · `listMembers` · `isMember` |
| `DbInvitationStore` | `store` · `findByTokenHash` · `findPending(tenantId, email)` · `listPendingForTenant` · `markAccepted` · `markRevoked` |

Id tables run on `jardissupport/repository` (`PkStrategy::NONE`, ids from `IdentityInterface`); the two key-pair tables (memberships, group members) use `jardissupport/dbquery` through the repository's `QueryExecutor`.

---

## Table DDL

Six tables in three dialect files (all statements `IF NOT EXISTS`, timestamps in UTC, no foreign keys):

| Dialect | File | Status |
|---------|------|--------|
| MySQL / MariaDB | `vendor/jardissupport/identity/resources/sql/mysql.sql` | tested in CI (MariaDB) |
| SQLite | `vendor/jardissupport/identity/resources/sql/sqlite.sql` | tested in CI |
| PostgreSQL | `vendor/jardissupport/identity/resources/sql/pgsql.sql` | shipped, not run in CI |

Tables: `identity_users`, `identity_tenants`, `identity_memberships`, `identity_groups`, `identity_group_members`, `identity_invitations`. For another prefix replace `identity_` in the file and pass the prefix to the stores.

**`auth_tokens` is also required.** Invitation and reset tokens as well as sessions live in `jardissupport/auth`'s token table — apply `vendor/jardissupport/auth/resources/sql/<dialect>.sql` as well.

---

## Error Handling

All identity errors extend `IdentityException` (`RuntimeException`).

| Exception | When |
|-----------|------|
| `IdentityException` | Base class of all identity domain errors |
| `InvalidEmailException` | E-mail address is syntactically invalid |
| `InvalidSlugException` | Tenant slug violates format or length |
| `WeakPasswordException` | Password shorter than the configured minimum |
| `EmailAlreadyRegisteredException` | Normalized e-mail is already registered |
| `SlugAlreadyTakenException` | Tenant slug already in use |
| `GroupNameTakenException` | Tenant already has a group with this name |
| `GroupMemberExistsException` | User is already a member of the group |
| `UserNotFoundException` | User id does not resolve |
| `TenantNotFoundException` | Tenant id does not resolve |
| `GroupNotFoundException` | Group id does not resolve |
| `UserDisabledException` | Operation requires an active user |
| `MembershipExistsException` | User is already a member of the tenant |
| `NotAMemberException` | User is not a member of the tenant |
| `NotYourSessionException` | Session reference does not belong to the acting user |
| `InvitationInvalidException` | Invitation unknown, expired, revoked or already accepted |
| `PasswordRequiredException` | Password needed to create a new user from an invitation |

Exceptions of `jardissupport/auth` pass through unchanged: `InvalidCredentialException` (e.g. wrong current password in `changePassword`, unknown or wrong-type reset token), `TokenExpiredException`, `TokenRevokedException` (reset token already used), all extending `AuthenticationException`.

```php
use JardisSupport\Identity\Exception\EmailAlreadyRegisteredException;
use JardisSupport\Identity\Exception\InvitationInvalidException;

try {
    $userManager->register($email, $password, $name);
} catch (EmailAlreadyRegisteredException $e) {
    // e-mail taken
}

try {
    $invitationManager->accept($token, $password, $name);
} catch (InvitationInvalidException $e) {
    // unknown, expired, revoked or used
}
```

---

## Architecture

Eight orchestrators and six stores. Each delegates to invokable handlers:

```
UserManager (Orchestrator)             Handler/User/ RegisterUser · ChangePassword · DisableUser · EnableUser · LoadUser
TenantManager (Orchestrator)           Handler/Tenant/ CreateTenant
MembershipManager (Orchestrator)       Handler/Membership/ AddMembership · ChangeMembershipRole · RemoveMembership · ListUserMemberships · ListTenantMemberships
GroupManager (Orchestrator)            Handler/Group/ CreateGroup · AddGroupMember · RemoveGroupMember · ListGroupMemberIds · LoadGroup
InvitationManager (Orchestrator)       Handler/Invitation/ CreateInvitation · AcceptInvitation · ListPendingForTenant
PasswordResetManager (Orchestrator)    Handler/PasswordReset/ RequestPasswordReset · ResetPassword
TenantSwitcher (Orchestrator)          Handler/TenantSwitch/ SwitchTenant
CredentialLookup (Orchestrator)        Handler/Lookup/ LookupCredential

DbUserStore                            Handler/UserStore/ StoreUser · FindUserById · FindUserByEmail · FindPasswordHash · UpdateUserStatus · UpdateUserPasswordHash · HydrateUser
DbTenantStore                          Handler/TenantStore/ StoreTenant · FindTenantById · FindTenantBySlug · HydrateTenant
DbMembershipStore                      Handler/MembershipStore/ StoreMembership · UpdateMembershipRole · RemoveMembership · FindMembership · ListMemberships · HydrateMembership
DbGroupStore                           Handler/GroupStore/ StoreGroup · FindGroupById · FindGroupByName · ListTenantGroups · HydrateGroup
DbGroupMemberStore                     Handler/GroupMemberStore/ AddGroupMember · RemoveGroupMember · RemoveUserFromTenantGroups · ListGroupMembers · IsGroupMember
DbInvitationStore                      Handler/InvitationStore/ StoreInvitation · FindInvitationByTokenHash · ListPendingInvitations · MarkInvitationAccepted · MarkInvitationRevoked · HydrateInvitation

Handler/Shared/                        AssertEmail · AssertPassword · AssertSlug · AssertTablePrefix · NormalizeEmail · ExecuteWrite · FetchRows · RunInTransaction · FormatUtcTimestamp · ParseUtcTimestamp

Data (immutable entities, results, events)
  ├── User, Tenant, Membership, Group, Invitation, CredentialRecord
  ├── UserResult, TenantResult, MembershipResult, GroupResult, InvitationResult, AcceptResult,
  │   PasswordResetRequestResult, TenantSwitchResult
  └── Event/ (UserRegistered, PasswordChanged, UserDisabled, UserEnabled, TenantCreated, MembershipAdded,
       MembershipRoleChanged, MembershipRemoved, GroupCreated, GroupMemberAdded, GroupMemberRemoved,
       InvitationCreated, InvitationAccepted, PasswordResetRequested, PasswordReset, TenantSwitched)
```

Each handler is an **invokable object** (`__invoke`) — independently testable, replaceable, composable. The orchestrators contain no business logic, only delegation.

Session claims are **ids**: the tenant claim is the tenant id (not the slug), the subject is `user:<user id>`.

---

## Contracts

Requires `jardissupport/contracts` ^2.10, `jardissupport/auth` ^1.4, `jardissupport/dbquery` ^1.1, `jardissupport/repository` ^1.6, `jardissupport/validation` ^1.5. Defined in `jardissupport/contracts`:

| Interface | Purpose |
|-----------|---------|
| `UserLookupInterface` | Login lookup port — implemented by `CredentialLookup`, accepted by `PasswordAuthenticator` |
| `CredentialRecordInterface` | `getPasswordHash()`, `getSubject()`, `getClaims()` — implemented by `Data\CredentialRecord` |
| `SessionClaim` | Claim names `TENANT` / `ROLE` set in the session |
| `UserInterface`, `TenantInterface`, `MembershipInterface`, `GroupInterface`, `InvitationInterface` | Entity ports implemented by `Data\*` |
| `UserStoreInterface`, `TenantStoreInterface`, `MembershipStoreInterface`, `GroupStoreInterface`, `GroupMemberStoreInterface`, `InvitationStoreInterface` | Store ports implemented by the `Db*Store` classes |
| `UserStatus` | Enum `Active` / `Disabled` |
| `IdentityInterface` (`Contract\Data`) | Id generator used by the managers |
| `PasswordHasherInterface`, `TokenStoreInterface` (`Contract\Auth`) | Consumed from `auth` |
| `DbConnectionInterface` (`Contract\DbConnection`) | Connection of all stores |

---

## What This Package Does NOT Do

- **No HTTP layer** — no routes, controllers or middleware; transports map the results themselves
- **No e-mail sending** — invitation and reset tokens are returned; delivery is the consumer's job
- **No rate limiting** — brute-force protection is an infrastructure concern
- **No OAuth2/OIDC/JWT** — opaque tokens from `auth` only
- **No per-resource roles** — a role is one string per membership; fine-grained permissions belong to `auth`'s `Guard`/`Policy`
- **No deleting of users or tenants** — users are disabled, memberships removed
- **No event dispatching** — events are returned to the caller, not dispatched internally

---

## Development

```bash
cp .env.example .env    # One-time setup
make install             # Install dependencies
make phpunit             # Run tests (SQLite + a MariaDB service started via Compose)
make phpstan             # Static analysis (Level 8)
make phpcs               # Coding standards (PSR-12)
```

---

## Documentation

Full documentation, guides, and API reference:

**[docs.jardis.io/en/support/identity](https://docs.jardis.io/en/support/identity)**

---

## License

[MIT License](LICENSE.md) — free for any use, including commercial.

<!-- BEGIN jardis/dev-skills README block — do not edit by hand -->
## AI-Assisted Development

This package ships with a skill for Claude Code, Cursor, Continue, and Aider. Install it in your consuming project:

```bash
composer require --dev jardis/dev-skills
```

More details: <https://docs.jardis.io/en/skills>
<!-- END jardis/dev-skills README block -->
