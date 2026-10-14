# jardissupport/identity

Identity management on top of `jardissupport/auth`: users, tenants, memberships, groups, invitations, password reset, tenant switching, login lookup. No HTTP, no e-mail, no framework coupling. Eight Orchestrators: `UserManager`, `TenantManager`, `MembershipManager`, `GroupManager`, `InvitationManager`, `PasswordResetManager`, `TenantSwitcher`, `CredentialLookup` (+ six `Db*Store` classes).

## Usage essentials

- **Orchestrator pattern:** orchestrators in `src/` root bind invokable handlers (`src/Handler/{Category}/`) via `->__invoke(...)`; zero business logic. One event = returned directly, several = `*Result{..., list<object> $events}`. Events are plain readonly objects, never dispatched.
- **G1 persistence:** id tables run on `jardissupport/repository` (`PkStrategy::NONE`, ids from `IdentityInterface`), the pair tables (memberships, group members) on `jardissupport/dbquery`. Stores: `new DbXStore($connection, $prefix = 'identity_')`. DDL: `resources/sql/{mysql,sqlite,pgsql}.sql` (pgsql shipped, not run in CI); `auth_tokens` of auth is also required.
- **G2 tokens:** invitation and reset tokens come from auth (`TokenIssuer`/`TokenRedeemer`); only hashes are stored; the plain token is returned once, sending it is the consumer's job.
- **G3 sessions:** `PasswordResetManager::reset()` and `UserManager::disable()` end ALL sessions (`invalidateAll`). `TenantSwitcher::switch($userId, $tenantId, ?$sessionRef)` creates a new session, then revokes the old access/refresh pair via `invalidateByReference`.
- **Claims are ids:** tenant claim = tenant id (not slug), subject = `user:<id>`. `CredentialLookup` sets tenant + role claims only for exactly ONE membership; unknown or disabled users yield `null`.
- **Transaction:** `InvitationManager::accept()` runs in one transaction (needs the `DbConnectionInterface`); `MembershipManager::remove()` is transactional (membership + group memberships, needs the connection); it registers an unknown e-mail (password required) or attaches the known user.
- **Errors:** all extend `IdentityException`; auth exceptions (`InvalidCredentialException`, `Token*Exception`) pass through. No enumeration: `PasswordResetManager::request()` returns `null` for unknown/disabled users.
- **Tests:** integration over SQLite plus MariaDB (`make phpunit` starts it); flow example: `tests/Integration/IdentityFlowTest.php`.

## Full reference

https://docs.jardis.io/en/support/identity
