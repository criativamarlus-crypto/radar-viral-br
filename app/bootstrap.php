<?php
declare(strict_types=1);

/** Private configuration, shared security and authentication helpers. */
function radar_config(): array
{
    static $config;
    if (is_array($config)) {
        return $config;
    }
    $defaults = [
        'admin_user' => '',
        'admin_password_hash' => '',
        'api_token_hash' => '',
        'site_url' => 'https://viral.netnewsltda.online',
        'secure_cookies' => true,
        'storage_path' => dirname(__DIR__) . '/storage',
        'feed_ttl' => 300,
    ];
    $path = dirname(__DIR__) . '/config.php';
    $provided = is_file($path) && is_readable($path) ? require $path : [];
    $config = array_replace($defaults, is_array($provided) ? $provided : []);
    foreach (['admin_user' => 'RADAR_ADMIN_USER', 'admin_password_hash' => 'RADAR_ADMIN_PASSWORD_HASH', 'api_token_hash' => 'RADAR_API_TOKEN_HASH'] as $key => $name) {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            $config[$key] = $value;
        }
    }
    return $config;
}

function radar_configured(): bool
{
    $config = radar_config();
    return is_string($config['admin_user']) && $config['admin_user'] !== ''
        && is_string($config['admin_password_hash'])
        && (password_get_info($config['admin_password_hash'])['algoName'] ?? 'unknown') !== 'unknown';
}

function radar_security_headers(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'none'; base-uri 'none'; script-src 'self'; style-src 'self'; img-src 'self' https: data: blob:; font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
}

function radar_json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function radar_private_directory(string $directory): bool
{
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        return false;
    }
    return !is_link($directory) && is_writable($directory) && @chmod($directory, 0700);
}

function radar_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $config = radar_config();
    $directory = rtrim((string)$config['storage_path'], '/') . '/sessions';
    if (!radar_private_directory($directory)) {
        radar_json(['error' => 'Armazenamento privado indisponível. Verifique a configuração do servidor.'], 503);
    }
    // Guard every endpoint that opens an anonymous session, not only login.php.
    // A made-up cookie must not bypass the new-session budget.
    $cookie = $_COOKIE['radarviral'] ?? '';
    $existing = is_string($cookie)
        && preg_match('/\A[A-Za-z0-9,-]{16,256}\z/D', $cookie) === 1
        && is_file($directory . '/sess_' . $cookie)
        && !is_link($directory . '/sess_' . $cookie);
    if (!$existing && !radar_rate_limit(radar_client_key('session-new'), 120, 300)) {
        header('Retry-After: 300');
        radar_json(['error' => 'Muitas novas sessões. Aguarde alguns minutos.'], 429);
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', '28800');
    // Debian's system session cleaner does not discover this private session path.
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_save_path($directory);
    session_name('radarviral');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $config['secure_cookies'] !== false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    if (!session_start()) {
        radar_json(['error' => 'Não foi possível iniciar uma sessão segura.'], 503);
    }
}

function radar_logged_in(): bool
{
    radar_session();
    $now = time();
    $valid = isset($_SESSION['admin'], $_SESSION['created'], $_SESSION['seen'], $_SESSION['credential_version'])
        && is_string($_SESSION['admin'])
        && hash_equals((string)radar_config()['admin_user'], $_SESSION['admin'])
        && is_string($_SESSION['credential_version'])
        && hash_equals(hash('sha256', (string)radar_config()['admin_password_hash']), $_SESSION['credential_version'])
        && $now - (int)$_SESSION['created'] < 28800
        && $now - (int)$_SESSION['seen'] < 3600;
    if ($valid) {
        $_SESSION['seen'] = $now;
    } else {
        unset($_SESSION['admin'], $_SESSION['created'], $_SESSION['seen'], $_SESSION['credential_version']);
    }
    return $valid;
}

function radar_require_login(bool $json = false): void
{
    if (!radar_configured()) {
        if ($json) {
            radar_json(['error' => 'Instalação pendente. Execute php bin/setup.php no servidor.'], 503);
        }
        header('Location: /login.php', true, 302);
        exit;
    }
    if (!radar_logged_in()) {
        if ($json) {
            radar_json(['error' => 'Faça login para continuar.'], 401);
        }
        header('Location: /login.php', true, 302);
        exit;
    }
}

/** API keys permit read access only. Write endpoints must call require_login + CSRF. */
function radar_require_read_access(): void
{
    if (!radar_configured()) {
        radar_json(['error' => 'Instalação pendente. Execute php bin/setup.php no servidor.'], 503);
    }
    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (is_string($authorization) && $authorization !== '') {
        if (preg_match('/^Bearer ([A-Za-z0-9_-]{32,256})$/D', $authorization, $matches) === 1) {
            // Legacy token created by bin/setup.php (hash in config.php): read-only.
            $expected = radar_config()['api_token_hash'];
            if (is_string($expected) && preg_match('/^[a-f0-9]{64}$/D', $expected) && hash_equals($expected, hash('sha256', $matches[1]))) {
                $GLOBALS['radar_scopes'] = ['read'];
                return;
            }
            // Tokens managed in the panel (Configurações de integração).
            if (function_exists('radar_token_authenticate')) {
                try {
                    $scopes = radar_token_authenticate($matches[1]);
                } catch (Throwable $error) {
                    error_log('Radar token lookup failed: ' . get_class($error));
                    radar_json(['error' => 'Armazenamento indisponível.'], 503);
                }
                if ($scopes !== null) {
                    $GLOBALS['radar_scopes'] = $scopes;
                    return;
                }
            }
        }
        header('WWW-Authenticate: ' . radar_oauth_challenge('invalid_token'));
        radar_json(['error' => 'Credencial de leitura inválida.'], 401);
    }
    radar_require_login(true);
    $GLOBALS['radar_scopes'] = ['read', 'react', 'admin'];
}

function radar_csrf_token(): string
{
    radar_session();
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function radar_require_csrf(): void
{
    radar_session();
    $received = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';
    if (!is_string($received) || !is_string($expected) || $expected === '' || !hash_equals($expected, $received)) {
        radar_json(['error' => 'Sessão expirada ou formulário inválido. Recarregue a página.'], 403);
    }
}

/** Fixed-window limit, per trusted REMOTE_ADDR; proxy-provided IP headers are ignored. */
function radar_rate_limit(string $key, int $max, int $seconds): bool
{
    $max = max(1, min($max, 10000));
    $seconds = max(1, min($seconds, 86400));
    $directory = rtrim((string)radar_config()['storage_path'], '/') . '/limits';
    if (!radar_private_directory($directory)) {
        return false;
    }
    $path = $directory . '/' . hash('sha256', $key) . '.json';
    if (is_link($path)) {
        return false;
    }
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        return false;
    }
    @chmod($path, 0600);
    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        return false;
    }
    $raw = stream_get_contents($handle, 512);
    $state = is_string($raw) ? json_decode($raw, true) : null;
    $now = time();
    if (!is_array($state) || !isset($state['start'], $state['count']) || $now - (int)$state['start'] >= $seconds || (int)$state['start'] > $now) {
        $state = ['start' => $now, 'count' => 0];
    }
    $allowed = (int)$state['count'] < $max;
    if ($allowed) {
        $state['count'] = (int)$state['count'] + 1;
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, json_encode($state)) === false || !fflush($handle)) {
            $allowed = false;
        }
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    return $allowed;
}


/** CLI maintenance only: remove expired application-owned files with bounded work. */
function radar_cleanup_private_storage(): void
{
    if (PHP_SAPI !== 'cli') {
        return;
    }
    $storage = rtrim((string)radar_config()['storage_path'], '/');
    $now = time();
    foreach (['limits' => 86400, 'sessions' => 28800] as $name => $lifetime) {
        $directory = $storage . '/' . $name;
        if (!is_dir($directory) || is_link($directory)) {
            continue;
        }
        try {
            $visited = 0;
            foreach (new DirectoryIterator($directory) as $file) {
                if (++$visited > 10000) {
                    break;
                }
                $filename = $file->getFilename();
                $owned = $name === 'limits'
                    ? preg_match('/\A[a-f0-9]{64}\.json\z/D', $filename) === 1
                    : preg_match('/\Asess_[A-Za-z0-9,-]{1,256}\z/D', $filename) === 1;
                if (!$owned || $file->isLink() || !$file->isFile() || $file->getMTime() >= $now - $lifetime) {
                    continue;
                }
                $handle = @fopen($file->getPathname(), 'r+');
                if ($handle === false) {
                    continue;
                }
                if (flock($handle, LOCK_EX | LOCK_NB)) {
                    // Recheck after the lock: a concurrent request may have refreshed it.
                    $stat = fstat($handle);
                    if (is_array($stat) && ($stat['mtime'] ?? $now) < $now - $lifetime) {
                        @unlink($file->getPathname());
                    }
                    flock($handle, LOCK_UN);
                }
                fclose($handle);
            }
        } catch (Throwable $error) {
            error_log('Radar private maintenance failed: ' . get_class($error));
        }
    }
}

function radar_client_key(string $prefix): string
{
    return $prefix . ':' . (is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
}

function radar_escape(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/tokens.php';
require_once __DIR__ . '/layout.php';
radar_security_headers();
