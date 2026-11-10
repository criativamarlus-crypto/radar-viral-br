<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login(true);
header('Cache-Control: private, max-age=3600');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    radar_json(['error' => 'Método não permitido.'], 405);
}
$id = $_GET['id'] ?? '';
if (!is_string($id) || !radar_media_id_valid($id)) {
    radar_json(['error' => 'Imagem não encontrada.'], 404);
}
try {
    $path = radar_media_path($id);
    if ($path === null) {
        radar_json(['error' => 'Imagem não encontrada.'], 404);
    }
    // Re-validate on every read: never serve a stored file whose content is not a real image.
    $info = radar_media_validate($path, (int)filesize($path));
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Content-Type: ' . $info['mime']);
    $disposition = ($_GET['download'] ?? '') === '1' ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="radar-' . substr($id, 0, 12) . '.' . $info['extension'] . '"');
    header('Content-Length: ' . filesize($path));
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        readfile($path);
    }
} catch (Throwable $error) {
    radar_json(['error' => 'Imagem indisponível.'], 404);
}
