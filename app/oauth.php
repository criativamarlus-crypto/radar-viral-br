<?php
declare(strict_types=1);

/**
 * OAuth 2.1 authorization server for the MCP endpoint (what ChatGPT connectors expect).
 *
 *  - Discovery: RFC 9728 (protected resource) and RFC 8414 (authorization server) metadata.
 *  - Dynamic client registration (RFC 7591) for public clients, plus manual clients with a secret.
 *  - Authorization code flow with mandatory PKCE (S256), exact redirect_uri match, single-use
 *    5-minute codes, resource indicator (RFC 8707) and issuer identification (RFC 9207).
 *  - Short-lived access tokens (1 h) and rotating refresh tokens (30 d). Only hashes are stored.
 *  - The admin login is the consent screen: nothing is granted without the admin approving.
 */

const RADAR_OAUTH_SCOPES = ['read' => 'Ler notícias, tendências e roteiros', 'react' => 'Preparar reacts (usa a IA do servidor e salva um roteiro)'];
const RADAR_OAUTH_ACCESS_TTL = 3600;
const RADAR_OAUTH_REFRESH_TTL = 30 * 86400;
const RADAR_OAUTH_CODE_TTL = 300;

final class RadarOAuthError extends RuntimeException
{
    public function __construct(public readonly string $oauthCode, string $description, public readonly int $httpStatus = 400)
    {
        parent::__construct($description);
    }
}

function radar_oauth_site(): string
{
    return rtrim((string)(radar_config()['site_url'] ?? ''), '/');
}

function radar_oauth_resource(): string
{
    return radar_oauth_site() . '/mcp.php';
}

/** Value for the WWW-Authenticate header so clients can discover how to authorize. */
function radar_oauth_challenge(?string $error = null): string
{
    $parts = ['realm="Radar Viral BR"'];
    if ($error !== null) {
        $parts[] = 'error="' . $error . '"';
    }
    if (radar_oauth_site() !== '') {
        $parts[] = 'resource_metadata="' . radar_oauth_site() . '/.well-known/oauth-protected-resource"';
    }
    return 'Bearer ' . implode(', ', $parts);
}

/** @return array<string,mixed> */
function radar_oauth_metadata(string $document): array
{
    $site = radar_oauth_site();
    if ($document === 'oauth-protected-resource') {
        return [
            'resource' => radar_oauth_resource(),
            'authorization_servers' => [$site],
            'scopes_supported' => array_keys(RADAR_OAUTH_SCOPES),
            'bearer_methods_supported' => ['header'],
            'resource_name' => 'Radar Viral BR',
        ];
    }
    return [
        'issuer' => $site,
        'authorization_endpoint' => $site . '/oauth-authorize.php',
        'token_endpoint' => $site . '/oauth-token.php',
        'registration_endpoint' => $site . '/oauth-register.php',
        'response_types_supported' => ['code'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'code_challenge_methods_supported' => ['S256'],
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post', 'client_secret_basic'],
        'scopes_supported' => array_keys(RADAR_OAUTH_SCOPES),
        'authorization_response_iss_parameter_supported' => true,
    ];
}

function radar_oauth_random(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function radar_oauth_hash(string $secret): string
{
    return hash('sha256', $secret);
}

/** @return array<int,string>|null null when an unknown scope is requested */
function radar_oauth_parse_scopes(string $scope): ?array
{
    $scopes = array_values(array_unique(array_filter(preg_split('/\s+/', trim($scope)) ?: [])));
    foreach ($scopes as $item) {
        if (!isset(RADAR_OAUTH_SCOPES[$item])) {
            return null;
        }
    }
    return $scopes;
}

function radar_oauth_redirect_valid(string $uri): bool
{
    if ($uri === '' || strlen($uri) > 500 || preg_match('/[\x00-\x20\x7f\\\\]/', $uri)) {
        return false;
    }
    $parts = parse_url($uri);
    if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower($parts['scheme'] ?? '');
    $host = strtolower($parts['host']);
    if ($scheme === 'https') {
        return true;
    }
    // Plain HTTP only for loopback (local tools), never for a public host.
    return $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
}

/** @param array<int,mixed> $uris @return array<int,string> */
function radar_oauth_clean_redirects(array $uris): array
{
    $clean = [];
    foreach ($uris as $uri) {
        if (!is_string($uri) || !radar_oauth_redirect_valid($uri)) {
            throw new RadarOAuthError('invalid_redirect_uri', 'Cada redirect_uri deve ser HTTPS (ou http://localhost) e sem fragmento.');
        }
        $clean[$uri] = true;
    }
    if ($clean === [] || count($clean) > 5) {
        throw new RadarOAuthError('invalid_redirect_uri', 'Informe de 1 a 5 redirect_uris.');
    }
    return array_keys($clean);
}

/**
 * Dynamic registration of a public client (RFC 7591).
 *
 * @return array<string,mixed> the registration response
 */
function radar_oauth_register(array $input): array
{
    if ((int)radar_db_value('SELECT COUNT(*) FROM oauth_clients') >= 100) {
        throw new RadarOAuthError('server_error', 'Limite de clientes atingido. Remova clientes antigos no painel.', 503);
    }
    $redirects = radar_oauth_clean_redirects(is_array($input['redirect_uris'] ?? null) ? $input['redirect_uris'] : []);
    $name = mb_substr(trim(strip_tags(radar_str($input['client_name'] ?? ''))), 0, 80);
    $name = $name !== '' ? $name : 'Aplicativo sem nome';
    $method = radar_str($input['token_endpoint_auth_method'] ?? 'none');
    if ($method !== 'none') {
        throw new RadarOAuthError('invalid_client_metadata', 'Registro dinâmico aceita apenas clientes públicos (token_endpoint_auth_method=none) com PKCE. Para cliente com segredo, crie-o no painel.');
    }
    foreach ((array)($input['grant_types'] ?? ['authorization_code']) as $grant) {
        if (!in_array($grant, ['authorization_code', 'refresh_token'], true)) {
            throw new RadarOAuthError('invalid_client_metadata', 'grant_type não suportado.');
        }
    }
    $clientId = 'rvc_' . radar_oauth_random(18);
    $now = time();
    radar_db_exec('INSERT INTO oauth_clients (client_id, name, redirect_uris, secret_hash, kind, created_at) VALUES (?, ?, ?, NULL, ?, ?)', [$clientId, $name, json_encode($redirects, JSON_UNESCAPED_SLASHES), 'dynamic', $now]);
    return [
        'client_id' => $clientId, 'client_id_issued_at' => $now, 'client_name' => $name, 'redirect_uris' => $redirects,
        'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'], 'token_endpoint_auth_method' => 'none',
    ];
}

/** Manual client with a secret, created by the admin (for UIs that ask for client id + secret). @return array{client_id:string,client_secret:string} */
function radar_oauth_client_create(string $name, string $redirectUris): array
{
    $name = mb_substr(trim(strip_tags($name)), 0, 80);
    if ($name === '') {
        throw new InvalidArgumentException('Informe o nome do cliente.');
    }
    try {
        $redirects = radar_oauth_clean_redirects(array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $redirectUris) ?: []))));
    } catch (RadarOAuthError $error) {
        throw new InvalidArgumentException($error->getMessage());
    }
    $clientId = 'rvc_' . radar_oauth_random(18);
    $secret = 'rvs_' . radar_oauth_random(32);
    radar_db_exec('INSERT INTO oauth_clients (client_id, name, redirect_uris, secret_hash, kind, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$clientId, $name, json_encode($redirects, JSON_UNESCAPED_SLASHES), radar_oauth_hash($secret), 'manual', time()]);
    return ['client_id' => $clientId, 'client_secret' => $secret];
}

/** @return array<string,mixed>|null */
function radar_oauth_client(string $clientId): ?array
{
    if (preg_match('/\Arvc_[A-Za-z0-9_-]{10,64}\z/D', $clientId) !== 1) {
        return null;
    }
    $row = radar_db_one('SELECT * FROM oauth_clients WHERE client_id = ?', [$clientId]);
    if ($row === null) {
        return null;
    }
    $row['redirect_uris'] = json_decode((string)$row['redirect_uris'], true) ?: [];
    return $row;
}

function radar_oauth_client_delete(string $clientId): void
{
    radar_db_exec("UPDATE api_tokens SET revoked_at = ? WHERE client_id = ? AND revoked_at IS NULL", [time(), $clientId]);
    radar_db_exec('DELETE FROM oauth_codes WHERE client_id = ?', [$clientId]);
    radar_db_exec('DELETE FROM oauth_clients WHERE client_id = ?', [$clientId]);
}

/** @return array<int,array<string,mixed>> */
function radar_oauth_clients(): array
{
    return radar_db_all('SELECT c.client_id, c.name, c.kind, c.redirect_uris, c.created_at, (SELECT COUNT(*) FROM api_tokens t WHERE t.client_id = c.client_id AND t.revoked_at IS NULL) AS active_tokens FROM oauth_clients c ORDER BY c.created_at DESC');
}

/**
 * Validate an authorization request. Problems with the client or redirect URI must never
 * redirect (the target is untrusted), so they are thrown for an on-page error.
 *
 * @return array{client:array<string,mixed>,redirect_uri:string,state:string,scopes:array<int,string>,challenge:string,resource:string}
 */
function radar_oauth_authorize_request(array $params): array
{
    $client = radar_oauth_client(radar_str($params['client_id'] ?? ''));
    if ($client === null) {
        throw new RadarOAuthError('invalid_client', 'Aplicativo desconhecido. Registre o cliente de novo.');
    }
    $redirect = radar_str($params['redirect_uri'] ?? '');
    if ($redirect === '' && count($client['redirect_uris']) === 1) {
        $redirect = $client['redirect_uris'][0];
    }
    if (!in_array($redirect, $client['redirect_uris'], true)) {
        throw new RadarOAuthError('invalid_request', 'O redirect_uri não corresponde ao registrado para este aplicativo.');
    }
    if (radar_str($params['response_type'] ?? '') !== 'code') {
        throw new RadarOAuthError('unsupported_response_type', 'Somente response_type=code é suportado.');
    }
    $challenge = radar_str($params['code_challenge'] ?? '');
    if (preg_match('/\A[A-Za-z0-9\-._~]{43,128}\z/D', $challenge) !== 1 || radar_str($params['code_challenge_method'] ?? '') !== 'S256') {
        throw new RadarOAuthError('invalid_request', 'PKCE obrigatório: envie code_challenge (S256).');
    }
    $scopes = radar_oauth_parse_scopes(radar_str($params['scope'] ?? ''));
    if ($scopes === null) {
        throw new RadarOAuthError('invalid_scope', 'Escopo desconhecido. Use: ' . implode(', ', array_keys(RADAR_OAUTH_SCOPES)) . '.');
    }
    $resource = radar_str($params['resource'] ?? '');
    if ($resource !== '' && rtrim($resource, '/') !== radar_oauth_resource() && rtrim($resource, '/') !== radar_oauth_site()) {
        throw new RadarOAuthError('invalid_target', 'O recurso solicitado não é este servidor.');
    }
    return ['client' => $client, 'redirect_uri' => $redirect, 'state' => radar_str($params['state'] ?? '', 500), 'scopes' => $scopes, 'challenge' => $challenge, 'resource' => $resource];
}

/** Build the URL that sends the user back to the client. */
function radar_oauth_callback_url(string $redirectUri, array $params): string
{
    $params = array_filter($params, static fn($v): bool => $v !== '' && $v !== null);
    return $redirectUri . (str_contains($redirectUri, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

/** @param array<int,string> $scopes granted by the admin */
function radar_oauth_issue_code(string $clientId, string $redirectUri, string $challenge, array $scopes, string $resource): string
{
    $code = radar_oauth_random(32);
    radar_db_exec(
        'INSERT INTO oauth_codes (code_hash, client_id, redirect_uri, code_challenge, scopes, resource, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [radar_oauth_hash($code), $clientId, $redirectUri, $challenge, implode(' ', $scopes), $resource, time() + RADAR_OAUTH_CODE_TTL]
    );
    return $code;
}

/** Client authentication at the token endpoint. */
function radar_oauth_authenticate_client(array $post, ?string $basicUser, ?string $basicPass): array
{
    $clientId = $basicUser ?? radar_str($post['client_id'] ?? '');
    $client = radar_oauth_client($clientId);
    if ($client === null) {
        throw new RadarOAuthError('invalid_client', 'Cliente desconhecido.', 401);
    }
    if ($client['secret_hash'] !== null) {
        $secret = $basicPass ?? radar_str($post['client_secret'] ?? '');
        if ($secret === '' || !hash_equals((string)$client['secret_hash'], radar_oauth_hash($secret))) {
            throw new RadarOAuthError('invalid_client', 'Credenciais do cliente inválidas.', 401);
        }
    }
    return $client;
}

/** @return array<string,mixed> token response */
function radar_oauth_token(array $post, ?string $basicUser = null, ?string $basicPass = null): array
{
    $client = radar_oauth_authenticate_client($post, $basicUser, $basicPass);
    $grant = radar_str($post['grant_type'] ?? '');
    if ($grant === 'authorization_code') {
        return radar_oauth_exchange_code($client, $post);
    }
    if ($grant === 'refresh_token') {
        return radar_oauth_refresh($client, radar_str($post['refresh_token'] ?? ''));
    }
    throw new RadarOAuthError('unsupported_grant_type', 'grant_type deve ser authorization_code ou refresh_token.');
}

/** @return array<string,mixed> */
function radar_oauth_exchange_code(array $client, array $post): array
{
    $code = radar_str($post['code'] ?? '');
    $verifier = radar_str($post['code_verifier'] ?? '');
    $row = $code === '' ? null : radar_db_one('SELECT * FROM oauth_codes WHERE code_hash = ?', [radar_oauth_hash($code)]);
    if ($row === null || !hash_equals((string)$row['client_id'], (string)$client['client_id'])) {
        throw new RadarOAuthError('invalid_grant', 'Código de autorização inválido.');
    }
    if ($row['used_at'] !== null) {
        // Replay: assume the code leaked and cut every token it produced.
        radar_db_exec('UPDATE api_tokens SET revoked_at = ? WHERE code_id = ? AND revoked_at IS NULL', [time(), $row['id']]);
        throw new RadarOAuthError('invalid_grant', 'Código de autorização já usado.');
    }
    if ((int)$row['expires_at'] < time()) {
        throw new RadarOAuthError('invalid_grant', 'Código de autorização expirado.');
    }
    // Atomic single use: only one request can flip used_at from NULL.
    if (radar_db_exec('UPDATE oauth_codes SET used_at = ? WHERE id = ? AND used_at IS NULL', [time(), $row['id']]) !== 1) {
        throw new RadarOAuthError('invalid_grant', 'Código de autorização já usado.');
    }
    $redirect = radar_str($post['redirect_uri'] ?? '');
    if ($redirect !== (string)$row['redirect_uri']) {
        throw new RadarOAuthError('invalid_grant', 'redirect_uri diferente do usado na autorização.');
    }
    if (preg_match('/\A[A-Za-z0-9\-._~]{43,128}\z/D', $verifier) !== 1
        || !hash_equals((string)$row['code_challenge'], radar_oauth_s256($verifier))) {
        throw new RadarOAuthError('invalid_grant', 'code_verifier (PKCE) não confere.');
    }
    $resource = radar_str($post['resource'] ?? '');
    if ($resource !== '' && $row['resource'] !== '' && rtrim($resource, '/') !== rtrim((string)$row['resource'], '/')) {
        throw new RadarOAuthError('invalid_target', 'resource diferente do usado na autorização.');
    }
    return radar_oauth_mint($client, explode(' ', (string)$row['scopes']), (int)$row['id']);
}

/** base64url(SHA-256(verifier)): the S256 transform. */
function radar_oauth_s256(string $verifier): string
{
    return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
}

/** @param array<int,string> $scopes @return array<string,mixed> */
function radar_oauth_mint(array $client, array $scopes, ?int $codeId): array
{
    $scopes = array_values(array_intersect(array_keys(RADAR_OAUTH_SCOPES), $scopes));
    if (!in_array('read', $scopes, true)) {
        array_unshift($scopes, 'read'); // "react" always includes reading
    }
    $access = 'rvo_' . bin2hex(random_bytes(24));
    $refresh = 'rvr_' . bin2hex(random_bytes(32));
    $now = time();
    radar_db_exec(
        "INSERT INTO api_tokens (name, token_hash, scopes, created_at, kind, client_id, refresh_hash, expires_at, refresh_expires_at, code_id) VALUES (?, ?, ?, ?, 'oauth', ?, ?, ?, ?, ?)",
        [$client['name'], radar_oauth_hash($access), implode(',', $scopes), $now, $client['client_id'], radar_oauth_hash($refresh), $now + RADAR_OAUTH_ACCESS_TTL, $now + RADAR_OAUTH_REFRESH_TTL, $codeId]
    );
    return ['access_token' => $access, 'token_type' => 'Bearer', 'expires_in' => RADAR_OAUTH_ACCESS_TTL, 'refresh_token' => $refresh, 'scope' => implode(' ', $scopes)];
}

/** Rotating refresh: the old pair stops working the moment a new one is issued. @return array<string,mixed> */
function radar_oauth_refresh(array $client, string $refreshToken): array
{
    $row = $refreshToken === '' ? null : radar_db_one('SELECT * FROM api_tokens WHERE refresh_hash = ? AND kind = \'oauth\'', [radar_oauth_hash($refreshToken)]);
    if ($row === null || !hash_equals((string)$row['client_id'], (string)$client['client_id'])) {
        throw new RadarOAuthError('invalid_grant', 'refresh_token inválido.');
    }
    if ($row['revoked_at'] !== null || (int)$row['refresh_expires_at'] < time()) {
        throw new RadarOAuthError('invalid_grant', 'refresh_token expirado ou revogado. Autorize novamente.');
    }
    // Claim the row atomically so a replayed refresh token cannot mint a second pair.
    if (radar_db_exec('UPDATE api_tokens SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', [time(), $row['id']]) !== 1) {
        throw new RadarOAuthError('invalid_grant', 'refresh_token já usado.');
    }
    return radar_oauth_mint($client, explode(',', (string)$row['scopes']), $row['code_id'] !== null ? (int)$row['code_id'] : null);
}

/** Housekeeping: expired codes, stale dynamic clients that never got a token. */
function radar_oauth_cleanup(): void
{
    $now = time();
    radar_db_exec('DELETE FROM oauth_codes WHERE expires_at < ?', [$now - 86400]);
    // Rotated or expired OAuth pairs only clutter the panel; manual tokens are kept for audit.
    radar_db_exec("DELETE FROM api_tokens WHERE kind = 'oauth' AND (revoked_at < ? OR refresh_expires_at < ?)", [$now - 86400, $now - 86400]);
    radar_db_exec("DELETE FROM oauth_clients WHERE kind = 'dynamic' AND created_at < ? AND client_id NOT IN (SELECT client_id FROM api_tokens WHERE client_id IS NOT NULL) AND client_id NOT IN (SELECT client_id FROM oauth_codes)", [$now - 7 * 86400]);
}
