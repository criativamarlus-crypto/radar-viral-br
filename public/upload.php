<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/media.php';
radar_require_login(true);
header('Cache-Control: private, no-store');

try {
    $directory = radar_media_directory();
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (($_GET['kind'] ?? '') !== 'character') {
            radar_json(['error' => 'Tipo de mídia inválido.'], 400);
        }
        $record = is_file($directory . '/character.json') ? json_decode((string)file_get_contents($directory . '/character.json'), true) : null;
        $id = is_array($record) && is_string($record['id'] ?? null) ? $record['id'] : '';
        radar_json(['url' => radar_media_id_valid($id) && is_file($directory . '/' . $id) ? 'media.php?id=' . $id : null]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: GET, POST');
        radar_json(['error' => 'Método não permitido.'], 405);
    }
    radar_require_csrf();
    if (!radar_rate_limit('upload:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 20, 3600)) {
        radar_json(['error' => 'Limite de uploads atingido. Tente mais tarde.'], 429);
    }
    $kind = $_POST['kind'] ?? '';
    $file = $_FILES['file'] ?? null;
    if (!in_array($kind, ['cover', 'character'], true) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        radar_json(['error' => 'Selecione uma imagem válida de até 5 MB.'], 400);
    }
    $size = filesize($file['tmp_name']);
    $info = radar_media_validate($file['tmp_name'], $size === false ? 0 : $size);
    $lock = fopen($directory . '/.upload.lock', 'c');
    if ($lock === false || !chmod($directory . '/.upload.lock', 0600) || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Falha ao bloquear armazenamento.');
    }
    // Prevent unbounded storage growth by repeated administrator uploads.
    $total = 0;
    foreach (new DirectoryIterator($directory) as $stored) {
        if ($stored->isFile()) {
            $total += $stored->getSize();
        }
    }
    if ($total + $size > 200 * 1024 * 1024) {
        radar_json(['error' => 'Limite de armazenamento de imagens atingido. Solicite a limpeza ao administrador.'], 413);
    }
    $id = bin2hex(random_bytes(16)) . '.' . $info['extension'];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $id)) {
        throw new RuntimeException('Falha ao salvar imagem.');
    }
    chmod($directory . '/' . $id, 0600);
    if ($kind === 'character') {
        $temp = $directory . '/.character-' . bin2hex(random_bytes(8));
        if (file_put_contents($temp, json_encode(['id' => $id], JSON_THROW_ON_ERROR), LOCK_EX) === false || !chmod($temp, 0600) || !rename($temp, $directory . '/character.json')) {
            throw new RuntimeException('Falha ao salvar personagem.');
        }
    }
    flock($lock, LOCK_UN);
    fclose($lock);
    radar_json(['id' => $id, 'url' => 'media.php?id=' . $id, 'mime' => $info['mime']], 201);
} catch (InvalidArgumentException $error) {
    radar_json(['error' => $error->getMessage()], 400);
} catch (Throwable $error) {
    error_log('Radar media upload failed: ' . get_class($error));
    radar_json(['error' => 'Armazenamento de mídia indisponível.'], 503);
}
