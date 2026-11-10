<?php
declare(strict_types=1);

/** SQLite persistence (PDO), automatic migrations and encrypted settings. */

function radar_db_path(): string
{
    return rtrim((string)radar_config()['storage_path'], '/\\') . '/radar.sqlite';
}

/** Connection is cached per path so tests can point at different storage roots. */
function radar_db(): PDO
{
    static $connections = [];
    $path = radar_db_path();
    if (isset($connections[$path])) {
        return $connections[$path];
    }
    if (!extension_loaded('pdo_sqlite')) {
        throw new RuntimeException('A extensão pdo_sqlite não está instalada no PHP.');
    }
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Armazenamento privado indisponível.');
    }
    if (is_link($path)) {
        throw new RuntimeException('Banco de dados inválido (link simbólico).');
    }
    $previous = umask(0077);
    try {
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } finally {
        umask($previous);
    }
    $pdo->exec('PRAGMA busy_timeout = 8000');
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA synchronous = NORMAL');
    @chmod($path, 0600);
    radar_db_migrate($pdo);
    return $connections[$path] = $pdo;
}

/** @return array<int,string> version => SQL */
function radar_db_migrations(): array
{
    return [
        1 => <<<'SQL'
CREATE TABLE sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    kind TEXT NOT NULL DEFAULT 'rss',
    url TEXT NOT NULL UNIQUE,
    category TEXT NOT NULL DEFAULT 'auto',
    region TEXT NOT NULL DEFAULT 'BR',
    weight REAL NOT NULL DEFAULT 1.0,
    active INTEGER NOT NULL DEFAULT 1,
    builtin INTEGER NOT NULL DEFAULT 0,
    etag TEXT,
    last_modified TEXT,
    last_status TEXT,
    last_error TEXT,
    last_http INTEGER,
    last_fetched_at INTEGER,
    last_success_at INTEGER,
    last_item_count INTEGER NOT NULL DEFAULT 0,
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);
CREATE TABLE news (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uid TEXT NOT NULL UNIQUE,
    source_id INTEGER REFERENCES sources(id) ON DELETE SET NULL,
    source_name TEXT NOT NULL,
    title TEXT NOT NULL,
    url TEXT NOT NULL,
    summary TEXT NOT NULL DEFAULT '',
    published_at INTEGER,
    first_seen_at INTEGER NOT NULL,
    last_seen_at INTEGER NOT NULL,
    category TEXT NOT NULL DEFAULT 'Geral',
    image_url TEXT,
    image_credit TEXT,
    image_note TEXT,
    related TEXT NOT NULL DEFAULT '[]',
    cluster_id INTEGER,
    source_count INTEGER NOT NULL DEFAULT 1,
    trend_hits TEXT NOT NULL DEFAULT '[]',
    score INTEGER NOT NULL DEFAULT 0,
    score_reasons TEXT NOT NULL DEFAULT '[]',
    saved INTEGER NOT NULL DEFAULT 0,
    saved_at INTEGER,
    note TEXT NOT NULL DEFAULT ''
);
CREATE INDEX news_published ON news(published_at);
CREATE INDEX news_seen ON news(first_seen_at);
CREATE INDEX news_score ON news(score);
CREATE INDEX news_category ON news(category);
CREATE INDEX news_cluster ON news(cluster_id);
CREATE INDEX news_saved ON news(saved);
CREATE TABLE trends (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id INTEGER,
    rank INTEGER NOT NULL,
    query TEXT NOT NULL,
    traffic TEXT,
    url TEXT,
    related TEXT NOT NULL DEFAULT '[]',
    fetched_at INTEGER NOT NULL
);
CREATE INDEX trends_fetched ON trends(fetched_at);
CREATE TABLE collection_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    started_at INTEGER NOT NULL,
    finished_at INTEGER,
    trigger_kind TEXT NOT NULL DEFAULT 'manual',
    status TEXT NOT NULL DEFAULT 'running',
    sources_ok INTEGER NOT NULL DEFAULT 0,
    sources_failed INTEGER NOT NULL DEFAULT 0,
    items_seen INTEGER NOT NULL DEFAULT 0,
    items_new INTEGER NOT NULL DEFAULT 0,
    message TEXT
);
CREATE TABLE collection_run_sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id INTEGER NOT NULL REFERENCES collection_runs(id) ON DELETE CASCADE,
    source_id INTEGER,
    source_name TEXT NOT NULL,
    status TEXT NOT NULL,
    http_status INTEGER,
    items INTEGER NOT NULL DEFAULT 0,
    new_items INTEGER NOT NULL DEFAULT 0,
    error TEXT,
    duration_ms INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX run_sources_run ON collection_run_sources(run_id);
CREATE TABLE media (
    id TEXT PRIMARY KEY,
    kind TEXT NOT NULL,
    news_id INTEGER REFERENCES news(id) ON DELETE SET NULL,
    mime TEXT NOT NULL,
    width INTEGER NOT NULL,
    height INTEGER NOT NULL,
    bytes INTEGER NOT NULL,
    label TEXT NOT NULL DEFAULT '',
    is_primary INTEGER NOT NULL DEFAULT 0,
    source_url TEXT,
    credit TEXT,
    note TEXT,
    created_at INTEGER NOT NULL
);
CREATE INDEX media_kind ON media(kind);
CREATE INDEX media_news ON media(news_id);
CREATE TABLE scripts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    news_id INTEGER REFERENCES news(id) ON DELETE SET NULL,
    news_title TEXT NOT NULL,
    news_url TEXT NOT NULL,
    news_source TEXT NOT NULL,
    mode TEXT NOT NULL,
    provider TEXT,
    model TEXT,
    duration INTEGER NOT NULL,
    tone TEXT NOT NULL,
    context TEXT NOT NULL DEFAULT '',
    headlines TEXT NOT NULL DEFAULT '[]',
    hook TEXT NOT NULL DEFAULT '',
    script TEXT NOT NULL DEFAULT '',
    commentary TEXT NOT NULL DEFAULT '',
    cta TEXT NOT NULL DEFAULT '',
    expression TEXT NOT NULL DEFAULT '',
    scene TEXT NOT NULL DEFAULT '',
    video_prompt TEXT NOT NULL DEFAULT '',
    warnings TEXT NOT NULL DEFAULT '[]',
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);
CREATE INDEX scripts_news ON scripts(news_id);
CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    script_id INTEGER REFERENCES scripts(id) ON DELETE SET NULL,
    news_id INTEGER REFERENCES news(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    headline TEXT NOT NULL DEFAULT '',
    script TEXT NOT NULL DEFAULT '',
    video_prompt TEXT NOT NULL DEFAULT '',
    cover_media_id TEXT,
    character_media_id TEXT,
    cover_x REAL NOT NULL DEFAULT 50,
    cover_y REAL NOT NULL DEFAULT 50,
    cover_zoom REAL NOT NULL DEFAULT 1,
    expression TEXT NOT NULL DEFAULT '',
    created_at INTEGER NOT NULL,
    updated_at INTEGER NOT NULL
);
CREATE TABLE settings (
    name TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at INTEGER NOT NULL
);
CREATE TABLE api_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    scopes TEXT NOT NULL DEFAULT 'read',
    created_at INTEGER NOT NULL,
    last_used_at INTEGER,
    revoked_at INTEGER
);
SQL,
        2 => <<<'SQL'
ALTER TABLE news ADD COLUMN og_checked_at INTEGER;
ALTER TABLE news ADD COLUMN trend_origin TEXT;
SQL,
        3 => <<<'SQL'
ALTER TABLE api_tokens ADD COLUMN kind TEXT NOT NULL DEFAULT 'manual';
ALTER TABLE api_tokens ADD COLUMN client_id TEXT;
ALTER TABLE api_tokens ADD COLUMN refresh_hash TEXT;
ALTER TABLE api_tokens ADD COLUMN expires_at INTEGER;
ALTER TABLE api_tokens ADD COLUMN refresh_expires_at INTEGER;
ALTER TABLE api_tokens ADD COLUMN code_id INTEGER;
CREATE INDEX api_tokens_refresh ON api_tokens(refresh_hash);
CREATE TABLE oauth_clients (
    client_id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    redirect_uris TEXT NOT NULL,
    secret_hash TEXT,
    kind TEXT NOT NULL DEFAULT 'dynamic',
    created_at INTEGER NOT NULL
);
CREATE TABLE oauth_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code_hash TEXT NOT NULL UNIQUE,
    client_id TEXT NOT NULL,
    redirect_uri TEXT NOT NULL,
    code_challenge TEXT NOT NULL,
    scopes TEXT NOT NULL,
    resource TEXT,
    expires_at INTEGER NOT NULL,
    used_at INTEGER
);
SQL,
    ];
}

function radar_db_migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version INTEGER PRIMARY KEY, applied_at INTEGER NOT NULL)');
    $current = (int)$pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
    foreach (radar_db_migrations() as $version => $sql) {
        if ($version <= $current) {
            continue;
        }
        $pdo->exec('BEGIN IMMEDIATE');
        try {
            // Re-check inside the write lock: another request may have migrated meanwhile.
            $applied = (int)$pdo->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
            if ($version > $applied) {
                $pdo->exec($sql);
                $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)')->execute([$version, time()]);
            }
            $pdo->exec('COMMIT');
        } catch (Throwable $error) {
            $pdo->exec('ROLLBACK');
            throw $error;
        }
    }
}

function radar_db_schema_version(): int
{
    return (int)radar_db()->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
}

/**
 * Execute with typed bindings. PDO would otherwise send every value as TEXT, and SQLite
 * ranks TEXT above INTEGER in expressions without column affinity (e.g. COALESCE(a, b) >= ?).
 */
function radar_db_run(string $sql, array $params = []): PDOStatement
{
    $statement = radar_db()->prepare($sql);
    radar_db_bind($statement, $params);
    $statement->execute();
    return $statement;
}

function radar_db_bind(PDOStatement $statement, array $params): void
{
    $position = 1;
    foreach ($params as $value) {
        $type = match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_INT,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
        $statement->bindValue($position++, is_bool($value) ? (int)$value : $value, $type);
    }
}

/** @return array<int,array<string,mixed>> */
function radar_db_all(string $sql, array $params = []): array
{
    return radar_db_run($sql, $params)->fetchAll();
}

/** @return array<string,mixed>|null */
function radar_db_one(string $sql, array $params = []): ?array
{
    $row = radar_db_run($sql, $params)->fetch();
    return is_array($row) ? $row : null;
}

function radar_db_value(string $sql, array $params = []): mixed
{
    $value = radar_db_run($sql, $params)->fetchColumn();
    return $value === false ? null : $value;
}

function radar_db_exec(string $sql, array $params = []): int
{
    return radar_db_run($sql, $params)->rowCount();
}

function radar_db_insert(string $sql, array $params = []): int
{
    radar_db_exec($sql, $params);
    return (int)radar_db()->lastInsertId();
}

/* ---------- settings and encrypted secrets ---------- */

function radar_setting(string $name, ?string $default = null): ?string
{
    $value = radar_db_value('SELECT value FROM settings WHERE name = ?', [$name]);
    return is_string($value) ? $value : $default;
}

function radar_setting_set(string $name, string $value): void
{
    radar_db_exec(
        'INSERT INTO settings (name, value, updated_at) VALUES (?, ?, ?) ON CONFLICT(name) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at',
        [$name, $value, time()]
    );
}

function radar_setting_delete(string $name): void
{
    radar_db_exec('DELETE FROM settings WHERE name = ?', [$name]);
}

/** 32-byte master key: config `app_key` (hex) or an auto-created 0600 file in private storage. */
function radar_app_key(): string
{
    $config = radar_config();
    $configured = $config['app_key'] ?? '';
    if (is_string($configured) && preg_match('/\A[a-f0-9]{64}\z/D', $configured) === 1) {
        return (string)hex2bin($configured);
    }
    $path = rtrim((string)$config['storage_path'], '/\\') . '/app.key';
    if (is_link($path)) {
        throw new RuntimeException('Chave mestra inválida (link simbólico).');
    }
    if (is_file($path)) {
        $raw = (string)file_get_contents($path);
        if (preg_match('/\A[a-f0-9]{64}\z/D', trim($raw)) === 1) {
            return (string)hex2bin(trim($raw));
        }
        throw new RuntimeException('Chave mestra corrompida; restaure o backup de storage/app.key.');
    }
    $previous = umask(0077);
    try {
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            // Another request created it first.
            return radar_app_key();
        }
        fwrite($handle, bin2hex(random_bytes(32)));
        fclose($handle);
    } finally {
        umask($previous);
    }
    @chmod($path, 0600);
    return radar_app_key();
}

function radar_secret_encrypt(string $plain): string
{
    $key = radar_app_key();
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1:s:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $key));
    }
    if (function_exists('openssl_encrypt')) {
        $nonce = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Falha ao criptografar o segredo.');
        }
        return 'v1:o:' . base64_encode($nonce . $tag . $cipher);
    }
    throw new RuntimeException('Instale a extensão sodium ou openssl para guardar chaves com segurança.');
}

function radar_secret_decrypt(string $stored): ?string
{
    $key = radar_app_key();
    if (str_starts_with($stored, 'v1:s:') && function_exists('sodium_crypto_secretbox_open')) {
        $raw = base64_decode(substr($stored, 5), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
        return $plain === false ? null : $plain;
    }
    if (str_starts_with($stored, 'v1:o:') && function_exists('openssl_decrypt')) {
        $raw = base64_decode(substr($stored, 5), true);
        if ($raw === false || strlen($raw) <= 28) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }
    return null;
}

function radar_secret_set(string $name, string $plain): void
{
    radar_setting_set('secret:' . $name, radar_secret_encrypt($plain));
}

function radar_secret_get(string $name): ?string
{
    $stored = radar_setting('secret:' . $name);
    return $stored === null ? null : radar_secret_decrypt($stored);
}

function radar_secret_exists(string $name): bool
{
    return radar_setting('secret:' . $name) !== null;
}

function radar_secret_delete(string $name): void
{
    radar_setting_delete('secret:' . $name);
}

/** Shows only the tail of a secret, never the whole value. */
function radar_secret_hint(string $name): string
{
    $plain = radar_secret_get($name);
    if ($plain === null || $plain === '') {
        return '';
    }
    return '••••' . substr($plain, -4);
}
