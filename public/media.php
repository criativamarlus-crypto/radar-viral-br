<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/media.php';
radar_require_login(true);
header('Cache-Control: private, no-store');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    radar_json(['error' => 'Método não permitido.'], 405);
}
$id = $_GET['id'] ?? '';
if (!is_string($id) || !radar_media_id_valid($id)) {
    radar_json(['error' => 'Imagem não encontrada.'], 404);
}
try {
    $path = radar_media_directory() . '/' . $id;
    if (!is_file($path) || is_link($path)) {
        radar_json(['error' => 'Imagem não encontrada.'], 404);
    }
    $info = radar_media_validate($path, (int)filesize($path));
    header('Content-Type: ' . $info['mime']);
    header('Content-Disposition: inline; filename="' . $id . '"');
    header('Content-Length: ' . filesize($path));
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
        readfile($path);
    }
} catch (Throwable $error) {
    radar_json(['error' => 'Imagem indisponível.'], 404);
}
