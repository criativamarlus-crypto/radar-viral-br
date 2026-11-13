<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login(true);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    radar_json(['error' => 'Use POST para sair.'], 405);
}
radar_require_csrf();
$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => $cookie['path'],
    'secure' => $cookie['secure'],
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_destroy();
header('Location: /login.php', true, 303);
