-- jardissupport/identity: tables for the Db*Store classes (SQLite)
-- Only the SHA-256 hash of an invitation token is stored, never the plain value.
-- Timestamps are UTC. No foreign keys (consistency is kept by the managers). Default prefix: identity_.
-- slug and name compare case-sensitively (SQLite BINARY collation). Timestamps are UTC text.
CREATE TABLE IF NOT EXISTS identity_users (
    id            TEXT NOT NULL,
    email         TEXT NOT NULL,
    display_name  TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    status        TEXT NOT NULL,
    created_at    TEXT NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT uq_identity_users_email UNIQUE (email)
);

CREATE TABLE IF NOT EXISTS identity_tenants (
    id            TEXT NOT NULL,
    slug          TEXT NOT NULL,
    name          TEXT NOT NULL,
    created_at    TEXT NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT uq_identity_tenants_slug UNIQUE (slug)
);

CREATE TABLE IF NOT EXISTS identity_memberships (
    user_id       TEXT NOT NULL,
    tenant_id     TEXT NOT NULL,
    role          TEXT NOT NULL,
    created_at    TEXT NOT NULL,
    PRIMARY KEY (user_id, tenant_id)
);

CREATE TABLE IF NOT EXISTS identity_groups (
    id            TEXT NOT NULL,
    tenant_id     TEXT NOT NULL,
    name          TEXT NOT NULL,
    created_at    TEXT NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT uq_identity_groups_tenant_name UNIQUE (tenant_id, name)
);

CREATE TABLE IF NOT EXISTS identity_group_members (
    group_id      TEXT NOT NULL,
    user_id       TEXT NOT NULL,
    created_at    TEXT NOT NULL,
    PRIMARY KEY (group_id, user_id)
);

CREATE TABLE IF NOT EXISTS identity_invitations (
    id            TEXT NOT NULL,
    tenant_id     TEXT NOT NULL,
    email         TEXT NOT NULL,
    role          TEXT NOT NULL,
    token_hash    TEXT NOT NULL,
    expires_at    TEXT NOT NULL,
    accepted_at   TEXT NULL,
    revoked_at    TEXT NULL,
    created_at    TEXT NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT uq_identity_invitations_token_hash UNIQUE (token_hash)
);

CREATE INDEX IF NOT EXISTS idx_identity_memberships_tenant ON identity_memberships (tenant_id);
CREATE INDEX IF NOT EXISTS idx_identity_group_members_user ON identity_group_members (user_id);
CREATE INDEX IF NOT EXISTS idx_identity_invitations_tenant_email ON identity_invitations (tenant_id, email);
