<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

/**
 * OAuth discovery documents (RFC 9728 / RFC 8414). Reached as /.well-known/oauth-protected-resource
 * and /.well-known/oauth-authorization-server (Nginx rewrite, or the index.php fallback).
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, MCP-Protocol-Version');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD, OPTIONS');
    radar_json(['error' => 'Método não permitido.'], 405);
}
$name = radar_str($_GET['doc'] ?? '');
if ($name === '') {
    $path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $name = preg_match('#/\.well-known/([a-z-]+)#', $path, $m) === 1 ? $m[1] : '';
}
$document = match ($name) {
    'oauth-protected-resource' => 'oauth-protected-resource',
    'oauth-authorization-server', 'openid-configuration' => 'oauth-authorization-server',
    default => null,
};
if ($document === null || radar_oauth_site() === '') {
    radar_json(['error' => 'Documento não encontrado.'], 404);
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
    echo json_encode(radar_oauth_metadata($document), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
