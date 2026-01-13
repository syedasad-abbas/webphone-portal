CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

CREATE TABLE IF NOT EXISTS groups (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name TEXT UNIQUE NOT NULL,
    permissions JSONB NOT NULL DEFAULT '[]',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS carriers (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    name TEXT UNIQUE NOT NULL,
    default_caller_id TEXT,
    caller_id_required BOOLEAN NOT NULL DEFAULT TRUE,
    sip_domain TEXT,
    sip_port INTEGER,
    transport TEXT NOT NULL DEFAULT 'udp',
    registration_required BOOLEAN NOT NULL DEFAULT FALSE,
    registration_username TEXT,
    registration_password TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS carrier_prefixes (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    carrier_id UUID NOT NULL REFERENCES carriers(id) ON DELETE CASCADE,
    prefix TEXT NOT NULL,
    caller_id TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

ALTER TABLE carriers
    ALTER COLUMN default_caller_id DROP NOT NULL;

ALTER TABLE carrier_prefixes
    ALTER COLUMN caller_id DROP NOT NULL;

CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    full_name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin', 'user')),
    group_id UUID REFERENCES groups(id),
    carrier_id UUID REFERENCES carriers(id),
    recording_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    permissions JSONB NOT NULL DEFAULT '[]',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS call_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID REFERENCES users(id),
    destination TEXT NOT NULL,
    caller_id TEXT,
    status TEXT NOT NULL,
    recording_path TEXT,
    call_uuid UUID,
    connected_at TIMESTAMPTZ,
    ended_at TIMESTAMPTZ,
    duration_seconds INTEGER,
    sip_status INTEGER,
    sip_reason TEXT,
    hangup_cause TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS password_reset_otps (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    code_hash TEXT NOT NULL,
    attempt_count INTEGER NOT NULL DEFAULT 0,
    expires_at TIMESTAMPTZ NOT NULL,
    consumed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_password_reset_otps_user ON password_reset_otps(user_id);

INSERT INTO groups (name, permissions)
VALUES ('Standard User', '["dial","view_self_cdr"]'::jsonb)
ON CONFLICT (name) DO NOTHING;

INSERT INTO carriers (name, default_caller_id, caller_id_required, sip_domain, sip_port, transport, registration_required)
VALUES ('Default Carrier', '1000', true, '127.0.0.1', 5062, 'udp', false)
ON CONFLICT (name) DO NOTHING;

INSERT INTO users (full_name, email, password_hash, role, group_id, carrier_id, recording_enabled)
SELECT 'Seeded Admin',
       'admin@webphone.com',
       '$2a$10$oeqo9FwE3smcGmE60BFtt.VOA4aZhzozT8ksvw1Jg/LLXcJVN57I.',
       'admin',
       g.id,
       c.id,
       true
FROM groups g, carriers c
WHERE g.name = 'Standard User'
  AND c.name = 'Default Carrier'
  AND NOT EXISTS (
      SELECT 1 FROM users WHERE email = 'admin@webphone.com'
  );
