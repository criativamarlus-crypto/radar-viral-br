<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$error = '';
$configured = radar_configured();
if (!$configured) {
    http_response_code(503);
    $error = 'A instalação ainda não foi configurada. Execute php bin/setup.php pelo SSH do servidor.';
} else {
    // Rate-limit anonymous page loads before opening a new session file.
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !radar_rate_limit(radar_client_key('login-view'), 120, 300)) {
        header('Retry-After: 300');
        radar_json(['error' => 'Muitas consultas à página de acesso. Aguarde alguns minutos.'], 429);
    }
    // After login, return to a pending OAuth authorization (same-site relative path only).
    $loginNext = static function (): string {
        $next = $_SESSION['login_next'] ?? '';
        return is_string($next) && preg_match('#\A/oauth-authorize\.php\?[A-Za-z0-9%=&._~:/+-]{0,2000}\z#D', $next) === 1 ? $next : '/';
    };
    if (radar_logged_in()) {
        header('Location: ' . $loginNext(), true, 302);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        radar_require_csrf();
        if (!radar_rate_limit(radar_client_key('login'), 5, 900)) {
            http_response_code(429);
            header('Retry-After: 900');
            $error = 'Muitas tentativas de acesso. Aguarde 15 minutos e tente novamente.';
        } else {
            $user = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $config = radar_config();
            $passwordValid = is_string($password) && strlen($password) <= 1024
                && password_verify($password, (string)$config['admin_password_hash']);
            if (is_string($user) && hash_equals((string)$config['admin_user'], $user) && $passwordValid) {
                $returnTo = $loginNext();
                session_regenerate_id(true);
                $_SESSION = ['admin' => $user, 'created' => time(), 'seen' => time(), 'credential_version' => hash('sha256', (string)$config['admin_password_hash']), 'csrf' => bin2hex(random_bytes(32))];
                header('Location: ' . $returnTo, true, 303);
                exit;
            }
            http_response_code(401);
            $error = 'Usuário ou senha incorretos.';
        }
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET, POST');
        http_response_code(405);
        $error = 'Método não permitido.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Entrar · Radar Viral BR</title>
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
</head>
<body class="login-page">
<main>
    <section class="auth-card" aria-labelledby="login-title">
        <p class="eyebrow">NOTÍCIAS · REACTS · BRASIL</p>
        <h1 id="login-title">Radar <span>Viral BR</span></h1>
        <p>Acesse seu painel privado de pautas e roteiros.</p>
        <?php if ($error !== ''): ?>
            <p class="notice error" role="alert"><?= radar_escape($error) ?></p>
        <?php endif; ?>
        <?php if ($configured): ?>
        <form method="post" action="/login.php" class="login-form">
            <input type="hidden" name="csrf" value="<?= radar_escape(radar_csrf_token()) ?>">
            <label for="username">Usuário</label>
            <input id="username" name="username" autocomplete="username" required maxlength="100" autofocus>
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="1024">
            <button class="btn primary" type="submit">Entrar no painel</button>
        </form>
        <?php endif; ?>
        <p class="muted">Acesso restrito à equipe editorial.</p>
    </section>
</main>
</body>
</html>
