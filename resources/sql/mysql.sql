-- jardissupport/identity: tables for the Db*Store classes (MySQL / MariaDB)
-- Only the SHA-256 hash of an invitation token is stored, never the plain value.
-- Timestamps are UTC. No foreign keys (consistency is kept by the managers). Default prefix: identity_.
-- slug and name compare case-sensitively (utf8mb4_bin), matching SQLite and PostgreSQL.
CREATE TABLE IF NOT EXISTS identity_users (
    id            CHAR(36)     NOT NULL,
    email         VARCHAR(255) NOT NULL,
    display_name  VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    status        VARCHAR(16)  NOT NULL,
    created_at    DATETIME(6)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_identity_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS identity_tenants (
    id         CHAR(36)     NOT NULL,
    slug       VARCHAR(64)  COLLATE utf8mb4_bin NOT NULL,
    name       VARCHAR(255) COLLATE utf8mb4_bin NOT NULL,
    created_at DATETIME(6)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_identity_tenants_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS identity_memberships (
    user_id    CHAR(36)    NOT NULL,
    tenant_id  CHAR(36)    NOT NULL,
    role       VARCHAR(64) NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (user_id, tenant_id),
    KEY idx_identity_memberships_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS identity_groups (
    id         CHAR(36)     NOT NULL,
    tenant_id  CHAR(36)     NOT NULL,
    name       VARCHAR(255) COLLATE utf8mb4_bin NOT NULL,
    created_at DATETIME(6)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_identity_groups_tenant_name (tenant_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS identity_group_members (
    group_id   CHAR(36)    NOT NULL,
    user_id    CHAR(36)    NOT NULL,
    created_at DATETIME(6) NOT NULL,
    PRIMARY KEY (group_id, user_id),
    KEY idx_identity_group_members_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS identity_invitations (
    id          CHAR(36)     NOT NULL,
    tenant_id   CHAR(36)     NOT NULL,
    email       VARCHAR(255) NOT NULL,
    role        VARCHAR(64)  NOT NULL,
    token_hash  CHAR(64)     NOT NULL,
    expires_at  DATETIME(6)  NOT NULL,
    accepted_at DATETIME(6)  NULL,
    revoked_at  DATETIME(6)  NULL,
    created_at  DATETIME(6)  NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_identity_invitations_token_hash (token_hash),
    KEY idx_identity_invitations_tenant_email (tenant_id, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
