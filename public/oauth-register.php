<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

/** Dynamic client registration (RFC 7591) for public clients. Anonymous by design, so it is capped and rate-limited. */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST, OPTIONS');
    radar_json(['error' => 'invalid_request', 'error_description' => 'Use POST com JSON.'], 405);
}
if (!radar_configured()) {
    radar_json(['error' => 'server_error', 'error_description' => 'Instalação pendente.'], 503);
}
if (!radar_rate_limit(radar_client_key('oauth-register'), 20, 3600)) {
    header('Retry-After: 3600');
    radar_json(['error' => 'temporarily_unavailable', 'error_description' => 'Muitos registros. Tente mais tarde.'], 429);
}
if (strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0])) !== 'application/json') {
    radar_json(['error' => 'invalid_client_metadata', 'error_description' => 'Content-Type application/json necessário.'], 415);
}
$raw = file_get_contents('php://input', false, null, 0, 16385);
$input = is_string($raw) && strlen($raw) <= 16384 ? json_decode($raw, true) : null;
if (!is_array($input) || array_is_list($input)) {
    radar_json(['error' => 'invalid_client_metadata', 'error_description' => 'Envie um objeto JSON de até 16 KB.'], 400);
}
try {
    radar_json(radar_oauth_register($input), 201);
} catch (RadarOAuthError $error) {
    radar_json(['error' => $error->oauthCode, 'error_description' => $error->getMessage()], $error->httpStatus);
} catch (Throwable $error) {
    error_log('Radar OAuth register failure: ' . get_class($error));
    radar_json(['error' => 'server_error', 'error_description' => 'Erro interno.'], 500);
}
