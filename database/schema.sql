-- DeskCaptcha SQLite Schema
-- Monthly database: deskcaptcha_YYYY_MM.sqlite

CREATE TABLE IF NOT EXISTS users (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    ip          TEXT NOT NULL,
    fingerprint TEXT NOT NULL,
    first_seen  TEXT NOT NULL,
    last_seen   TEXT NOT NULL,
    total_requests INTEGER NOT NULL DEFAULT 0,
    UNIQUE(fingerprint)
);

CREATE TABLE IF NOT EXISTS captchas (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    token       TEXT NOT NULL UNIQUE,
    filename    TEXT NOT NULL,
    answer      TEXT NOT NULL,
    chars       INTEGER NOT NULL DEFAULT 4,
    scale       INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT NOT NULL,
    expires_at  TEXT NOT NULL,
    used        INTEGER NOT NULL DEFAULT 0,
    used_at     TEXT,
    deleted     INTEGER NOT NULL DEFAULT 0,
    deleted_at  TEXT,
    delete_reason TEXT,
    user_id     INTEGER,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS requests (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    ip          TEXT NOT NULL,
    fingerprint TEXT NOT NULL,
    user_id     INTEGER,
    endpoint    TEXT NOT NULL,
    method      TEXT NOT NULL DEFAULT 'GET',
    scale       INTEGER,
    chars       INTEGER,
    status      INTEGER NOT NULL,
    created_at  TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS rate_limit_global (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    window_type  TEXT NOT NULL,
    window_start TEXT NOT NULL,
    count        INTEGER NOT NULL DEFAULT 0,
    UNIQUE(window_type, window_start)
);

CREATE TABLE IF NOT EXISTS rate_limit_user (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    fingerprint  TEXT NOT NULL,
    window_type  TEXT NOT NULL,
    window_start TEXT NOT NULL,
    count        INTEGER NOT NULL DEFAULT 0,
    UNIQUE(fingerprint, window_type, window_start)
);

CREATE TABLE IF NOT EXISTS pool_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    filename   TEXT NOT NULL,
    action     TEXT NOT NULL,
    reason     TEXT,
    created_at TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_captchas_token      ON captchas(token);
CREATE INDEX IF NOT EXISTS idx_captchas_deleted    ON captchas(deleted);
CREATE INDEX IF NOT EXISTS idx_captchas_expires    ON captchas(expires_at);
CREATE INDEX IF NOT EXISTS idx_requests_created    ON requests(created_at);
CREATE INDEX IF NOT EXISTS idx_requests_fp         ON requests(fingerprint);
CREATE INDEX IF NOT EXISTS idx_ratelimit_global    ON rate_limit_global(window_type, window_start);
CREATE INDEX IF NOT EXISTS idx_ratelimit_user      ON rate_limit_user(fingerprint, window_type, window_start);
