<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

/** Token endpoint: authorization_code (+PKCE) and refresh_token grants. */
header('Cache-Control: no-store');
header('Pragma: no-cache');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    radar_json(['error' => 'invalid_request', 'error_description' => 'Use POST.'], 405);
}
if (!radar_configured()) {
    radar_json(['error' => 'server_error', 'error_description' => 'Instalação pendente.'], 503);
}
if (!radar_rate_limit(radar_client_key('oauth-token'), 60, 60)) {
    header('Retry-After: 60');
    radar_json(['error' => 'temporarily_unavailable', 'error_description' => 'Muitas requisições.'], 429);
}
if (strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0])) !== 'application/x-www-form-urlencoded') {
    radar_json(['error' => 'invalid_request', 'error_description' => 'Content-Type application/x-www-form-urlencoded necessário.'], 415);
}
// client_secret_basic: Authorization: Basic base64(client_id:client_secret)
$basicUser = null;
$basicPass = null;
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (is_string($authorization) && preg_match('/^Basic ([A-Za-z0-9+\/=]+)$/D', $authorization, $m) === 1) {
    $decoded = base64_decode($m[1], true);
    if (is_string($decoded) && str_contains($decoded, ':')) {
        [$basicUser, $basicPass] = array_map('rawurldecode', explode(':', $decoded, 2));
    }
}
try {
    radar_json(radar_oauth_token($_POST, $basicUser, $basicPass));
} catch (RadarOAuthError $error) {
    if ($error->httpStatus === 401) {
        header('WWW-Authenticate: Basic realm="Radar Viral BR"');
    }
    radar_json(['error' => $error->oauthCode, 'error_description' => $error->getMessage()], $error->httpStatus);
} catch (Throwable $error) {
    error_log('Radar OAuth token failure: ' . get_class($error) . ' ' . $error->getMessage());
    radar_json(['error' => 'server_error', 'error_description' => 'Erro interno.'], 500);
}
