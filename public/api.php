<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/feeds.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    radar_require_read_access();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    if (!radar_rate_limit(radar_client_key('api-read'), 90, 60)) {
        header('Retry-After: 60');
        radar_json(['error' => 'Limite de consultas atingido. Aguarde um minuto.'], 429);
    }
    $action = $_GET['action'] ?? 'news';
    if ($action === 'trends') {
        $response = radar_trends();
    } elseif ($action === 'news') {
        $category = $_GET['categoria'] ?? 'Geral';
        if (!is_string($category) || !array_key_exists($category, radar_categories())) {
            radar_json(['error' => 'Categoria inválida.'], 400);
        }
        $response = radar_fetch($category);
    } else {
        radar_json(['error' => 'Ação não encontrada.'], 404);
    }
    radar_json($response, ($response['error'] ?? null) !== null && empty($response['items']) ? 503 : 200);
}
if ($method !== 'POST') {
    header('Allow: GET, POST');
    radar_json(['error' => 'Método não permitido.'], 405);
}
radar_require_login(true);
radar_require_csrf();
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
if (!radar_rate_limit(radar_client_key('script'), 30, 60)) {
    header('Retry-After: 60');
    radar_json(['error' => 'Limite de roteiros atingido. Aguarde um minuto.'], 429);
}
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (!is_string($contentType) || strtolower(trim(explode(';', $contentType)[0])) !== 'application/json') {
    radar_json(['error' => 'Envie JSON com Content-Type application/json.'], 415);
}
$body = file_get_contents('php://input', false, null, 0, 8193);
if (!is_string($body) || strlen($body) > 8192) {
    radar_json(['error' => 'Requisição muito grande.'], 413);
}
$data = json_decode($body, true);
if (!is_array($data) || ($data['action'] ?? $_GET['action'] ?? '') !== 'script') {
    radar_json(['error' => 'Ação inválida.'], 400);
}
$id = $data['id'] ?? '';
$category = $data['category'] ?? 'Geral';
$duration = $data['duration'] ?? 25;
$tone = $data['tone'] ?? 'bem-humorado';
$context = $data['verified_context'] ?? '';
if (!is_string($id) || !preg_match('/^[a-f0-9]{16,64}$/D', $id)
    || !is_string($category) || !array_key_exists($category, radar_categories())
    || !is_int($duration) || $duration < 15 || $duration > 35
    || !is_string($tone) || !in_array($tone, ['bem-humorado', 'direto'], true)
    || !is_string($context) || strlen($context) > 2000) {
    radar_json(['error' => 'Confira notícia, categoria, duração, tom e contexto.'], 400);
}
$feed = radar_fetch($category);
foreach ($feed['items'] ?? [] as $item) {
    if (isset($item['id']) && is_string($item['id']) && hash_equals($item['id'], $id)) {
        require_once dirname(__DIR__) . '/app/scripts.php';
        radar_json(radar_build_script($item, $duration, $tone, $context));
    }
}
radar_json(['error' => 'Esta pauta não está mais no feed. Atualize as notícias.'], 404);
