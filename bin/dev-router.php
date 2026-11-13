<?php
declare(strict_types=1);

/**
 * Router for the PHP built-in server (development and tests only):
 *   php -S localhost:8080 -t public bin/dev-router.php
 * The built-in server does not fall back to a front controller, so OAuth discovery
 * (/.well-known/oauth-*) is routed here. In production Nginx does the same through the
 * rewrite in deploy/nginx-hardening.conf.
 */
$path = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (str_starts_with($path, '/.well-known/')) {
    require rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') . '/oauth-metadata.php';
    return true;
}
return false; // everything else is served normally
