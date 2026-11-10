<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    radar_json(['status' => 'unavailable'], 405);
}
// Only coarse readiness is public: never disclose paths, versions or secrets.
try {
    $config = radar_config();
    $storage = (string)$config['storage_path'];
    $url = parse_url((string)$config['site_url']);
    $secure = is_array($url) && (($url['scheme'] ?? '') === 'https' && $config['secure_cookies'] === true
        || ($url['scheme'] ?? '') === 'http' && in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true));
    $ready = radar_configured()
        && $secure
        && radar_missing_extensions() === []
        && is_dir($storage) && is_writable($storage)
        && (((int)fileperms($storage) & 0077) === 0);
    // The database must open and be migrated for the panel to work.
    $ready = $ready && radar_db_schema_version() >= 1;
} catch (Throwable $error) {
    $ready = false;
}
header('Content-Type: application/json; charset=utf-8');
http_response_code($ready ? 200 : 503);
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    echo json_encode(['status' => $ready ? 'ok' : 'unavailable']);
}
