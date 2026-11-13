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
    $voice = radar_db_one("SELECT mime FROM media WHERE id = ? AND kind = 'voice'", [$id]);
    if ($voice !== null) {
        // The optional reference audio of a character: served only with the type that was detected when it was saved.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header('Content-Type: ' . $voice['mime']);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . (($_GET['download'] ?? '') === '1' ? 'attachment' : 'inline') . '; filename="voz-referencia-' . substr($id, 0, 8) . '.' . pathinfo($id, PATHINFO_EXTENSION) . '"');
        header('Content-Length: ' . filesize($path));
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            readfile($path);
        }
        exit;
    }
    // Re-validate on every read: never serve a stored file whose content is not a real image.
    $info = radar_media_validate($path, (int)filesize($path));
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Content-Type: ' . $info['mime']);
    $disposition = ($_GET['download'] ?? '') === '1' ? 'attachment' : 'inline';
    $isReference = radar_db_value("SELECT 1 FROM media WHERE id = ? AND kind = 'reference'", [$id]) !== null;
    header('Content-Disposition: ' . $disposition . '; filename="' . ($isReference ? 'personagem-referencia-' . substr($id, 0, 8) : 'radar-' . substr($id, 0, 12)) . '.' . $info['extension'] . '"');
    header('Content-Length: ' . filesize($path));
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        readfile($path);
    }
} catch (Throwable $error) {
    radar_json(['error' => 'Imagem indisponível.'], 404);
}
