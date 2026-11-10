<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

/**
 * Authorization endpoint. The admin login is the consent screen.
 * Anything wrong with the client or the redirect_uri is shown on this page and NEVER redirected,
 * because that target is not yet trusted.
 */
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    radar_json(['error' => 'invalid_request'], 405);
}
if (!radar_configured()) {
    radar_simple_page('Instalação pendente', '<h1>Instalação pendente</h1><p class="muted">Execute <code>php bin/setup.php</code> no servidor.</p>', ['status' => 503]);
}
if (!radar_rate_limit(radar_client_key('oauth-authorize'), 60, 300)) {
    header('Retry-After: 300');
    radar_simple_page('Muitas tentativas', '<h1>Muitas tentativas</h1><p class="muted">Aguarde alguns minutos.</p>', ['status' => 429]);
}
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$params = $isPost ? $_POST : $_GET;
try {
    $request = radar_oauth_authorize_request($params);
} catch (RadarOAuthError $error) {
    radar_simple_page('Pedido inválido', '<h1>Não foi possível autorizar</h1><p class="notice error" role="alert">' . radar_h($error->getMessage()) . '</p><p class="muted">Nada foi concedido. Volte ao aplicativo e tente conectar de novo.</p>', ['status' => 400]);
}
$client = $request['client'];
$redirectHost = (string)parse_url($request['redirect_uri'], PHP_URL_HOST);
$back = static fn(array $extra): string => radar_oauth_callback_url($request['redirect_uri'], $extra + ['state' => $request['state'], 'iss' => radar_oauth_site()]);

if (!radar_logged_in()) {
    // The session cookie is SameSite=Strict, so it is not sent when this page is opened from another
    // site (the connector). One click on our own page sends it; otherwise go to the login.
    if (!$isPost && ($_GET['go'] ?? '') !== '1') {
        $next = '/oauth-authorize.php?' . ($_SERVER['QUERY_STRING'] ?? '') . '&go=1';
        radar_simple_page('Continuar', '<h1>Conectar «' . radar_h($client['name']) . '»</h1>'
            . '<p class="muted">Este aplicativo quer acessar o seu Radar Viral BR. Continue para entrar e revisar o pedido.</p>'
            . '<a class="btn primary" href="' . radar_h($next) . '">Continuar</a>');
    }
    radar_session();
    $_SESSION['login_next'] = '/oauth-authorize.php?' . preg_replace('/&?go=1/', '', (string)($_SERVER['QUERY_STRING'] ?? '')) . '&go=1';
    header('Location: /login.php', true, 303);
    exit;
}

$csrf = radar_csrf_token();
$allowedScopes = $request['scopes'] === [] ? array_keys(RADAR_OAUTH_SCOPES) : $request['scopes'];
if ($isPost) {
    $received = $_POST['csrf'] ?? '';
    if (!is_string($received) || !hash_equals($csrf, $received)) {
        radar_simple_page('Sessão expirada', '<h1>Sessão expirada</h1><p class="notice error" role="alert">Volte ao aplicativo e tente conectar de novo.</p>', ['status' => 403]);
    }
    if (($_POST['decision'] ?? '') !== 'approve') {
        $url = $back(['error' => 'access_denied', 'error_description' => 'O administrador negou o acesso.']);
        radar_simple_page('Acesso negado', '<h1>Acesso negado</h1><p class="muted">Voltando ao aplicativo…</p><p><a href="' . radar_h($url) . '">Voltar agora</a></p>', ['redirect' => $url]);
    }
    $granted = array_values(array_intersect($allowedScopes, array_map('strval', (array)($_POST['grant'] ?? []))));
    if (!in_array('read', $granted, true)) {
        $granted[] = 'read'; // every connection can at least read
    }
    $code = radar_oauth_issue_code((string)$client['client_id'], $request['redirect_uri'], $request['challenge'], $granted, $request['resource']);
    $url = $back(['code' => $code]);
    radar_simple_page('Autorizado', '<h1>Autorizado</h1><p class="muted">«' . radar_h($client['name']) . '» foi conectado. Voltando ao aplicativo…</p><p><a href="' . radar_h($url) . '">Voltar agora</a></p>', ['redirect' => $url]);
}

$hidden = '';
foreach (['client_id', 'redirect_uri', 'response_type', 'code_challenge', 'code_challenge_method', 'state', 'scope', 'resource'] as $field) {
    $hidden .= '<input type="hidden" name="' . $field . '" value="' . radar_h(radar_str($_GET[$field] ?? '')) . '">';
}
$boxes = '';
foreach ($allowedScopes as $scope) {
    $boxes .= '<label class="check"><span><input type="checkbox" name="grant[]" value="' . radar_h($scope) . '" checked' . ($scope === 'read' ? ' disabled' : '') . '> <strong>' . radar_h($scope) . '</strong> — ' . radar_h(RADAR_OAUTH_SCOPES[$scope]) . '</span></label>';
}
radar_simple_page('Autorizar acesso', '<h1>Autorizar «' . radar_h($client['name']) . '»?</h1>'
    . '<p class="muted">Este aplicativo pede acesso ao seu Radar Viral BR e, ao concluir, voltará para <strong>' . radar_h($redirectHost) . '</strong>. Só autorize se foi você quem iniciou a conexão.</p>'
    . '<form method="post" action="/oauth-authorize.php" class="form-grid"><input type="hidden" name="csrf" value="' . radar_h($csrf) . '">' . $hidden
    . '<fieldset class="panel"><legend>Acesso concedido</legend>' . $boxes . '</fieldset>'
    . '<p class="hint">O aplicativo nunca recebe sua senha. Você pode revogar a conexão em Integrações a qualquer momento.</p>'
    . '<div class="dialog-actions"><button class="btn ghost" type="submit" name="decision" value="deny">Negar</button><button class="btn primary" type="submit" name="decision" value="approve">Autorizar</button></div></form>');
