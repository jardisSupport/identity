---
name: support-identity
description: Identity management on top of auth — users, tenants, memberships, groups, invitations, password reset, tenant switching, login lookup (UserLookupInterface), SQL stores, events.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, foundation-patterns, support-auth, support-dbquery, support-repository]
next: []
---

# IDENTITY_COMPONENT_SKILL
> `jardissupport/identity` | NS: `JardisSupport\Identity` | No HTTP, no e-mail, no framework coupling | PHP 8.3+

## ARCHITECTURE
```
UserManager          register / changePassword / disable (ends all sessions) / enable
TenantManager        create tenant (validated slug)
MembershipManager    add / changeRole / remove (also leaves the tenant's groups) / forUser / forTenant
GroupManager         create / addMember / removeMember / members / forTenant
InvitationManager    invite (token returned once) / accept (ONE transaction) / pendingForTenant
PasswordResetManager request (null for unknown/disabled) / reset (ends ALL sessions)
TenantSwitcher       new session for the target tenant, old access/refresh pair revoked
CredentialLookup     UserLookupInterface for PasswordAuthenticator (tenant/role claims only for exactly one membership)

DbUserStore · DbTenantStore · DbGroupStore · DbInvitationStore   jardissupport/repository (PkStrategy::NONE, UUID ids)
DbMembershipStore · DbGroupMemberStore                           jardissupport/dbquery via repository QueryExecutor (composite keys)
```
Persistence (G1): repository for id tables, dbquery for the pair tables. Tokens (G2): auth `TokenIssuer`/`TokenRedeemer`. Sessions (G3): auth `SessionManager`.

## API / SIGNATURES

### UserManager
```php
new UserManager(
    UserStoreInterface $users,
    PasswordHasherInterface $hasher,
    IdentityInterface $ids,
    SessionManager $sessions,
    int $minPasswordLength = 8,
)
$m->register(string $email, string $password, string $displayName): UserResult   // events=[UserRegistered]; e-mail normalized
$m->changePassword(string $userId, string $current, string $new): PasswordChanged   // InvalidCredentialException if current is wrong; sessions stay
$m->disable(string $userId): UserDisabled   // status Disabled + sessions->invalidateAll('user:<id>')
$m->enable(string $userId): UserEnabled
```

### TenantManager
```php
new TenantManager(TenantStoreInterface $tenants, IdentityInterface $ids)
$m->create(string $name, string $slug): TenantResult   // events=[TenantCreated]
```

### MembershipManager
```php
new MembershipManager(
    MembershipStoreInterface $memberships,
    GroupMemberStoreInterface $groupMembers,
    UserStoreInterface $users,
    TenantStoreInterface $tenants,
)
$m->add(string $userId, string $tenantId, string $role): MembershipResult        // events=[MembershipAdded]
$m->changeRole(string $userId, string $tenantId, string $role): MembershipResult // events=[MembershipRoleChanged]
$m->remove(string $userId, string $tenantId): MembershipRemoved                  // also removes the user from the tenant's groups
/** @return list<MembershipInterface> */ $m->forUser(string $userId): array
/** @return list<MembershipInterface> */ $m->forTenant(string $tenantId): array
```

### GroupManager
```php
new GroupManager(
    GroupStoreInterface $groups,
    GroupMemberStoreInterface $groupMembers,
    MembershipStoreInterface $memberships,
    IdentityInterface $ids,
)
$m->create(string $tenantId, string $name): GroupResult              // events=[GroupCreated]; GroupNameTakenException
$m->addMember(string $groupId, string $userId): GroupMemberAdded     // NotAMemberException unless member of the group's tenant
$m->removeMember(string $groupId, string $userId): GroupMemberRemoved
/** @return list<string> */ $m->members(string $groupId): array
/** @return list<GroupInterface> */ $m->forTenant(string $tenantId): array
```

### InvitationManager
```php
new InvitationManager(
    InvitationStoreInterface $invitations,
    UserStoreInterface $users,
    MembershipStoreInterface $memberships,
    PasswordHasherInterface $hasher,
    IdentityInterface $ids,
    TokenIssuer $issuer,
    TokenRedeemer $redeemer,
    DbConnectionInterface $connection,
    int $ttl = 604800,
    int $minPasswordLength = 8,
)
$m->invite(string $tenantId, string $email, string $role): InvitationResult   // {invitation, token (plain, once), events=[InvitationCreated]}; an open invitation for tenant+e-mail is revoked
$m->accept(string $token, ?string $password = null, ?string $displayName = null): AcceptResult
    // {user, membership, events=[UserRegistered?, MembershipAdded, InvitationAccepted]}
    // one transaction: redeem token → claim invitation (CAS) → register unknown user (password required) or attach known one → membership
/** @return list<InvitationInterface> */ $m->pendingForTenant(string $tenantId): array
```

### PasswordResetManager
```php
new PasswordResetManager(
    UserStoreInterface $users,
    PasswordHasherInterface $hasher,
    TokenIssuer $issuer,
    TokenRedeemer $redeemer,
    SessionManager $sessions,
    int $ttl = 3600,
    int $minPasswordLength = 8,
)
$m->request(string $email): ?PasswordResetRequestResult   // {token, events=[PasswordResetRequested]}; null for unknown or disabled (no enumeration)
$m->reset(string $token, string $newPassword): PasswordReset   // sets hash, sessions->invalidateAll (global logout)
```

### TenantSwitcher
```php
new TenantSwitcher(
    UserStoreInterface $users,
    MembershipStoreInterface $memberships,
    TokenStoreInterface $tokens,
    SessionManager $sessions,
)
$s->switch(string $userId, string $tenantId, ?string $sessionRef): TenantSwitchResult
    // {session: SessionResult, events=[TenantSwitched]}; new session carries claims tenant (id) + role
    // $sessionRef (TokenAuthenticator result->getSessionReference()) is revoked incl. its refresh token; null = only create
    // UserNotFound / UserDisabled / NotAMember / NotYourSession
```

### CredentialLookup
```php
new CredentialLookup(UserStoreInterface $users, MembershipStoreInterface $memberships)   // implements UserLookupInterface
$l->lookup(string $identifier): ?CredentialRecordInterface
    // null for unknown or disabled; CredentialRecord{passwordHash, subject 'user:<id>', claims}
    // claims [SessionClaim::TENANT => tenantId, SessionClaim::ROLE => role] only for exactly ONE membership, else []
```

### Stores
```php
new Db{User,Tenant,Membership,Group,GroupMember,Invitation}Store(DbConnectionInterface $connection, string $prefix = 'identity_')

DbUserStore:        store(UserInterface $user, string $passwordHash): void · updateStatus(string $id, UserStatus $status): void
                    updatePasswordHash(string $id, string $hash): void · findById(string $id): ?UserInterface
                    findByEmail(string $email): ?UserInterface · passwordHashOf(string $id): ?string
DbTenantStore:      store(TenantInterface $tenant): void · findById(string $id): ?TenantInterface · findBySlug(string $slug): ?TenantInterface
DbMembershipStore:  store(MembershipInterface $membership): void · updateRole(string $userId, string $tenantId, string $role): void
                    remove(string $userId, string $tenantId): void · find(string $userId, string $tenantId): ?MembershipInterface
                    listForUser(string $userId): array · listForTenant(string $tenantId): array
DbGroupStore:       store(GroupInterface $group): void · findById(string $id): ?GroupInterface
                    findByName(string $tenantId, string $name): ?GroupInterface · listForTenant(string $tenantId): array
DbGroupMemberStore: add(string $groupId, string $userId): void · remove(string $groupId, string $userId): void
                    removeUserFromTenantGroups(string $userId, string $tenantId): void · listMembers(string $groupId): array
                    isMember(string $groupId, string $userId): bool
DbInvitationStore:  store(InvitationInterface $invitation): void · findByTokenHash(string $tokenHash): ?InvitationInterface
                    findPending(string $tenantId, string $email): ?InvitationInterface · listPendingForTenant(string $tenantId): array
                    markAccepted(string $id, DateTimeImmutable $at): bool · markRevoked(string $id, DateTimeImmutable $at): void
```

## DATA CLASSES (`src/Data/`)

| Class | Key fields / notes |
|-------|-------------------|
| `User` | readonly, implements `UserInterface` — `id`, `email`, `displayName`, `status: UserStatus`, `createdAt`; `::create()`, `withStatus()`; no password hash |
| `Tenant` | readonly, implements `TenantInterface` — `id`, `slug`, `name`, `createdAt` |
| `Membership` | readonly, implements `MembershipInterface` — `userId`, `tenantId`, `role`, `createdAt`; `withRole()` |
| `Group` | readonly, implements `GroupInterface` — `id`, `tenantId`, `name`, `createdAt` |
| `Invitation` | readonly, implements `InvitationInterface` — `id`, `tenantId`, `email`, `role`, `tokenHash`, `expiresAt`, `acceptedAt`, `revokedAt`, `createdAt`; `::create()`, `withAccepted()`, `withRevoked()` |
| `CredentialRecord` | readonly, implements `CredentialRecordInterface` — `passwordHash`, `subject`, `claims` |
| `UserResult` / `TenantResult` / `MembershipResult` / `GroupResult` | `user` / `tenant` / `membership` / `group` + `events: list<object>` |
| `InvitationResult` | `invitation`, `token` (plain), `events` |
| `AcceptResult` | `user`, `membership`, `events` |
| `PasswordResetRequestResult` | `token` (plain), `events` |
| `TenantSwitchResult` | `session: SessionResult` (auth), `events` |

## EVENTS (`src/Data/Event/`)
Plain readonly data objects — no interface, no dispatcher dependency. Caller dispatches. All carry `timestamp`.

| Event | Fields |
|-------|--------|
| `UserRegistered` | `userId`, `email` |
| `PasswordChanged` / `PasswordResetRequested` / `PasswordReset` / `UserDisabled` / `UserEnabled` | `userId` |
| `TenantCreated` | `tenantId`, `slug` |
| `MembershipAdded` / `MembershipRoleChanged` | `userId`, `tenantId`, `role` |
| `MembershipRemoved` | `userId`, `tenantId` |
| `TenantSwitched` | `userId`, `tenantId` |
| `GroupCreated` | `groupId`, `tenantId`, `name` |
| `GroupMemberAdded` / `GroupMemberRemoved` | `groupId`, `userId` |
| `InvitationCreated` | `invitationId`, `tenantId`, `email`, `role` |
| `InvitationAccepted` | `invitationId`, `tenantId`, `userId` |

## EXCEPTIONS (`src/Exception/`)
All extend `IdentityException` (extends `RuntimeException`): `InvalidEmailException`, `InvalidSlugException`, `WeakPasswordException`, `EmailAlreadyRegisteredException`, `SlugAlreadyTakenException`, `GroupNameTakenException`, `UserNotFoundException`, `TenantNotFoundException`, `GroupNotFoundException`, `UserDisabledException`, `MembershipExistsException`, `NotAMemberException`, `NotYourSessionException`, `InvitationInvalidException`, `PasswordRequiredException`.
Passed through from auth: `InvalidCredentialException`, `TokenExpiredException`, `TokenRevokedException` (extend `AuthenticationException`).

## DDL / TABLES
`resources/sql/{mysql,sqlite,pgsql}.sql` — apply once per project (`IF NOT EXISTS`, UTC, no foreign keys). pgsql is shipped, not run in CI.
Tables (prefix default `identity_`): `users`, `tenants`, `memberships`, `groups`, `group_members`, `invitations` (token hash only). **Also apply** `vendor/jardissupport/auth/resources/sql/<dialect>.sql` (`auth_tokens`).

## USAGE — Login wiring
```php
$hasher = PasswordHasher::argon2id();
$tokens = new DbTokenStore($connection);
$sessions = new SessionManager($tokens);
$users = new DbUserStore($connection);
$memberships = new DbMembershipStore($connection);

$login = new PasswordAuthenticator($hasher, $sessions, new CredentialLookup($users, $memberships));
$result = $login->authenticate(Credential::password('owner@acme.test', 'secret-1'));
$result->session->metadata[SessionClaim::TENANT];   // tenant id — only with exactly one membership
```

## USAGE — Switch tenant
```php
$auth = (new TokenAuthenticator($tokens))->authenticate(Credential::token($bearer));
$switched = $switcher->switch($auth->getSubjectId(), $targetTenantId, $auth->getSessionReference());
$switched->session->accessToken;   // hand out; the old access + refresh token are revoked
```

## USAGE — Password reset
```php
$requested = $resetManager->request($email);          // null → answer neutrally, send nothing
if ($requested !== null) { /* mail $requested->token yourself */ }
$resetManager->reset($tokenFromLink, $newPassword);   // ends all sessions
```

## USAGE — Invite
```php
$invite = $invitationManager->invite($tenantId, 'dev@acme.test', 'editor');
// mail $invite->token yourself; only its hash is stored
$accepted = $invitationManager->accept($tokenFromLink, password: 'secret-2', displayName: 'Dev');
```

## CONTRACTS (`jardissupport/contracts` >= 2.10)
| Interface | Methods |
|-----------|---------|
| `UserLookupInterface` | `lookup(string): ?CredentialRecordInterface` — implemented by `CredentialLookup` |
| `CredentialRecordInterface` | `getPasswordHash`, `getSubject`, `getClaims` |
| `SessionClaim` | constants `TENANT`, `ROLE` |
| `UserStoreInterface` | `store`, `updateStatus`, `updatePasswordHash`, `findById`, `findByEmail`, `passwordHashOf` |
| `TenantStoreInterface` | `store`, `findById`, `findBySlug` |
| `MembershipStoreInterface` | `store`, `updateRole`, `remove`, `find`, `listForUser`, `listForTenant` |
| `GroupStoreInterface` | `store`, `findById`, `findByName`, `listForTenant` |
| `GroupMemberStoreInterface` | `add`, `remove`, `removeUserFromTenantGroups`, `listMembers`, `isMember` |
| `InvitationStoreInterface` | `store`, `findByTokenHash`, `findPending`, `listPendingForTenant`, `markAccepted`, `markRevoked` |
| `UserInterface` / `TenantInterface` / `MembershipInterface` / `GroupInterface` / `InvitationInterface` | entity getters; `UserStatus` enum `Active`, `Disabled` |
| `IdentityInterface` (`Contract\Data`) | id generator (`jardissupport/data`) |

## PITFALLS
- **Claims are ids:** the tenant claim is the tenant id, not the slug; the subject is `user:<id>`. Resolve slugs yourself.
- **No tenant claim with 0 or 2+ memberships** — the user must call `TenantSwitcher::switch()`.
- **Refresh-token pair:** `switch()` revokes access AND refresh token of the old session; pass the session reference of the current access token (`TokenAuthenticator` result), not a refresh token.
- **`auth_tokens` is required too** — invitation, reset and sessions live there; a missing table fails at runtime.
- **`IdentityInterface` is mandatory** for the managers that create entities (`jardissupport/data` is only suggested).
- **No e-mail:** `invite()`/`request()` return the plain token once; delivery is yours. Never log it.
- **`changePassword` keeps sessions**, `reset` and `disable` end all of them.
- **Events are returned, not dispatched** — forgetting to dispatch loses them.
