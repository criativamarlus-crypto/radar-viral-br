<?php
declare(strict_types=1);

/**
 * Private image storage. Files live under storage/media (never web-served) with a
 * random name; each one has a database row that records where it came from.
 */

const RADAR_MEDIA_MAX_BYTES = 5 * 1024 * 1024;
const RADAR_MEDIA_QUOTA_BYTES = 300 * 1024 * 1024;

function radar_media_validate(string $path, int $size): array
{
    if ($size < 1 || $size > RADAR_MEDIA_MAX_BYTES || !is_file($path) || filesize($path) !== $size) {
        throw new InvalidArgumentException('Envie uma imagem de até 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $formats = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $dimensions = @getimagesize($path);
    if (!is_string($mime) || !isset($formats[$mime]) || $dimensions === false || ($dimensions['mime'] ?? '') !== $mime) {
        throw new InvalidArgumentException('Use uma imagem JPEG, PNG ou WebP válida. SVG e arquivos executáveis não são aceitos.');
    }
    [$width, $height] = $dimensions;
    if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096 || $width * $height > 16000000) {
        throw new InvalidArgumentException('A imagem deve ter até 4096 pixels por lado e 16 megapixels.');
    }
    return ['mime' => $mime, 'extension' => $formats[$mime], 'width' => $width, 'height' => $height];
}

function radar_media_id_valid(string $id): bool
{
    return preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/D', $id) === 1;
}

function radar_media_directory(): string
{
    $storage = rtrim((string)radar_config()['storage_path'], '/\\');
    $directory = $storage . '/media';
    if ((!is_dir($directory) && !@mkdir($directory, 0700, true)) || is_link($directory)) {
        throw new RuntimeException('Armazenamento de imagens indisponível.');
    }
    return $directory;
}

function radar_media_path(string $id): ?string
{
    if (!radar_media_id_valid($id)) {
        return null;
    }
    $path = radar_media_directory() . '/' . $id;
    return is_file($path) && !is_link($path) ? $path : null;
}

/**
 * Validate and persist image bytes. Returns the media row.
 *
 * @param array{news_id?:?int,label?:string,source_url?:?string,credit?:?string,note?:?string,is_primary?:bool} $meta
 * @return array<string,mixed>
 */
function radar_media_save_bytes(string $bytes, string $kind, array $meta = []): array
{
    if (!in_array($kind, ['cover', 'character', 'screenshot', 'upload', 'reference'], true)) {
        throw new InvalidArgumentException('Tipo de mídia inválido.');
    }
    $slug = radar_character_slug($meta['character'] ?? null);
    if ($kind === 'reference' && (int)radar_db_value("SELECT COUNT(*) FROM media WHERE kind = 'reference' AND character_slug = ?", [$slug]) >= RADAR_REFERENCES_MAX) {
        throw new InvalidArgumentException('Cada personagem aceita até ' . RADAR_REFERENCES_MAX . ' imagens de referência. Exclua uma antes de enviar outra.');
    }
    $role = $kind === 'reference' ? (in_array($meta['role'] ?? '', ['sheet', 'photo'], true) ? (string)$meta['role'] : 'sheet') : '';
    $directory = radar_media_directory();
    $temp = $directory . '/.incoming-' . bin2hex(random_bytes(8));
    if (strlen($bytes) > RADAR_MEDIA_MAX_BYTES || @file_put_contents($temp, $bytes, LOCK_EX) === false) {
        @unlink($temp);
        throw new InvalidArgumentException('Envie uma imagem de até 5 MB.');
    }
    @chmod($temp, 0600);
    try {
        $info = radar_media_validate($temp, strlen($bytes));
        $used = (int)radar_db_value('SELECT COALESCE(SUM(bytes), 0) FROM media');
        if ($used + strlen($bytes) > RADAR_MEDIA_QUOTA_BYTES) {
            throw new InvalidArgumentException('Limite de armazenamento de imagens atingido. Exclua imagens que não usa mais.');
        }
        $id = bin2hex(random_bytes(16)) . '.' . $info['extension'];
        if (!@rename($temp, $directory . '/' . $id)) {
            throw new RuntimeException('Falha ao salvar a imagem.');
        }
    } catch (Throwable $error) {
        @unlink($temp);
        throw $error;
    }
    $isPrimary = $kind === 'character' && (!empty($meta['is_primary']) || radar_db_value("SELECT 1 FROM media WHERE kind = 'character' AND character_slug = ? LIMIT 1", [$slug]) === null);
    if ($isPrimary) {
        radar_db_exec("UPDATE media SET is_primary = 0 WHERE kind = 'character' AND character_slug = ?", [$slug]);
    }
    radar_db_exec(
        'INSERT INTO media (id, kind, news_id, mime, width, height, bytes, label, is_primary, source_url, credit, note, created_at, character_slug, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $id, $kind, $meta['news_id'] ?? null, $info['mime'], $info['width'], $info['height'], strlen($bytes),
            mb_substr((string)($meta['label'] ?? ''), 0, 120), $isPrimary ? 1 : 0, $meta['source_url'] ?? null,
            isset($meta['credit']) ? mb_substr((string)$meta['credit'], 0, 200) : null,
            isset($meta['note']) ? mb_substr((string)$meta['note'], 0, 400) : null, time(), $slug, $role,
        ]
    );
    return radar_media_get($id) ?? [];
}

/** @return array<string,mixed>|null */
function radar_media_get(string $id): ?array
{
    return radar_media_id_valid($id) ? radar_db_one('SELECT * FROM media WHERE id = ?', [$id]) : null;
}

function radar_media_delete(string $id): bool
{
    $row = radar_media_get($id);
    if ($row === null) {
        return false;
    }
    $path = radar_media_path($id);
    if ($path !== null) {
        @unlink($path);
    }
    radar_db_exec('UPDATE projects SET cover_media_id = NULL WHERE cover_media_id = ?', [$id]);
    radar_db_exec('UPDATE projects SET character_media_id = NULL WHERE character_media_id = ?', [$id]);
    radar_db_exec('DELETE FROM media WHERE id = ?', [$id]);
    if ($row['kind'] === 'character' && (int)$row['is_primary'] === 1) {
        $next = radar_db_value("SELECT id FROM media WHERE kind = 'character' AND character_slug = ? ORDER BY created_at DESC LIMIT 1", [$row['character_slug']]);
        if (is_string($next)) {
            radar_character_set_primary($next);
        }
    }
    return true;
}

/** Photos from news sites are often bigger than what the panel stores: they are downloaded up to this size and shrunk. */
const RADAR_MEDIA_FETCH_MAX_BYTES = 15 * 1024 * 1024;

/** True when a picture is within what the panel stores (size, sides and megapixels). */
function radar_media_fits(string $bytes): bool
{
    $info = @getimagesizefromstring($bytes);
    return $info !== false && strlen($bytes) <= RADAR_MEDIA_MAX_BYTES && $info[0] >= 1 && $info[1] >= 1 && $info[0] <= 4096 && $info[1] <= 4096 && $info[0] * $info[1] <= 16000000;
}

/**
 * Shrink a picture that is too big (a 4267-pixel-wide news photo, say) to 2400 px on its longest side, as JPEG or PNG.
 * Returns null when it cannot be done safely: no GD, a format GD does not read, too many pixels for the memory.
 */
function radar_media_shrink(string $bytes): ?string
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagescale')) {
        return null;
    }
    $info = @getimagesizefromstring($bytes);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > 40000000) {
        return null;
    }
    $limit = (string)ini_get('memory_limit');
    $limitBytes = $limit === '-1' ? PHP_INT_MAX : (int)$limit * match (strtoupper(substr($limit, -1))) { 'G' => 1073741824, 'M' => 1048576, 'K' => 1024, default => 1 };
    if ($info[0] * $info[1] * 6 > $limitBytes - memory_get_usage()) {
        return null;
    }
    $image = @imagecreatefromstring($bytes);
    if ($image === false) {
        return null;
    }
    $scale = min(1.0, 2400 / max($info[0], $info[1]));
    $small = $scale < 1.0 ? imagescale($image, max(1, (int)round($info[0] * $scale)), max(1, (int)round($info[1] * $scale)), IMG_BICUBIC) : $image;
    if ($small === false) {
        return null;
    }
    ob_start();
    $ok = $info[2] === IMAGETYPE_PNG ? imagepng($small, null, 6) : imagejpeg($small, null, 86);
    $out = (string)ob_get_clean();
    return $ok && $out !== '' && radar_media_fits($out) ? $out : null;
}

/** Download an image from a public HTTPS URL into private storage (shrinking it when it is too big). @return array<string,mixed> */
function radar_media_import_remote(string $url, string $kind, array $meta = []): array
{
    $response = radar_net_get($url, ['headers' => ['Accept: image/jpeg,image/png,image/webp'], 'max_bytes' => RADAR_MEDIA_FETCH_MAX_BYTES, 'timeout' => 20]);
    if ($response['error'] !== null || $response['status'] !== 200 || $response['body'] === '') {
        throw new RuntimeException('Não foi possível baixar a imagem' . ($response['error'] !== null ? ': ' . $response['error'] : ' (HTTP ' . $response['status'] . ').'));
    }
    $meta['source_url'] = $url;
    $bytes = $response['body'];
    if (@getimagesizefromstring($bytes) !== false && !radar_media_fits($bytes)) {
        $shrunk = radar_media_shrink($bytes);
        if ($shrunk === null) {
            throw new RuntimeException('A imagem da fonte é grande demais para o painel (até 4096 pixels por lado, 16 megapixels e 5 MB) e não pôde ser reduzida. Anexe um print ou outra imagem.');
        }
        $bytes = $shrunk;
        $meta['note'] = trim((string)($meta['note'] ?? '') . ' Imagem reduzida para caber no painel.');
    }
    try {
        return radar_media_save_bytes($bytes, $kind, $meta);
    } catch (InvalidArgumentException $error) {
        throw new RuntimeException('A imagem da fonte não pôde ser usada: ' . $error->getMessage());
    }
}

/** Keep the news cover (as declared by the source) as an editable local asset. @return array<string,mixed> */
function radar_media_import_news_cover(int $newsId): array
{
    $news = radar_db_one('SELECT id, image_url, image_credit, image_note, source_name, title FROM news WHERE id = ?', [$newsId]);
    if ($news === null) {
        throw new InvalidArgumentException('Notícia não encontrada.');
    }
    if (empty($news['image_url'])) {
        throw new RuntimeException('Esta notícia não tem imagem de capa. Anexe uma imagem ou use a captura de tela.');
    }
    $existing = radar_db_one("SELECT id FROM media WHERE news_id = ? AND kind = 'cover' AND source_url = ?", [$newsId, $news['image_url']]);
    if ($existing !== null) {
        return radar_media_get((string)$existing['id']) ?? [];
    }
    // Keep the truth about where the picture came from: it may belong to a related story.
    $borrowed = str_starts_with((string)$news['image_note'], 'Imagem de matéria relacionada');
    try {
        return radar_media_import_remote((string)$news['image_url'], 'cover', [
            'news_id' => $newsId,
            'label' => $borrowed ? 'Imagem de matéria relacionada' : 'Capa original',
            'credit' => $news['image_credit'] ?: $news['source_name'],
            'note' => ($news['image_note'] ?: 'Capa declarada pela fonte.') . ' Confira direitos de uso e mantenha a atribuição.',
        ]);
    } catch (RuntimeException $first) {
        // The source's own picture failed (blocked, too big, not an image): the same story in other outlets may have a usable one.
        $mates = radar_db_all('SELECT n.id, n.image_url, n.image_credit, n.source_name FROM news n WHERE n.cluster_id = (SELECT cluster_id FROM news WHERE id = ?) AND n.id != ? AND n.image_url IS NOT NULL AND n.image_url != ? ORDER BY n.score DESC, n.id DESC LIMIT 3', [$newsId, $newsId, $news['image_url']]);
        foreach ($mates as $mate) {
            try {
                return radar_media_import_remote((string)$mate['image_url'], 'cover', [
                    'news_id' => $newsId,
                    'label' => 'Imagem de matéria relacionada',
                    'credit' => $mate['image_credit'] ?: $mate['source_name'],
                    'note' => 'Imagem de outra matéria sobre o mesmo assunto (' . $mate['source_name'] . '), usada porque a da fonte não pôde ser baixada. Confira direitos de uso e mantenha a atribuição.',
                ]);
            } catch (RuntimeException) {
                continue;
            }
        }
        throw $first;
    }
}

/* ---------- optional screenshot service ---------- */

/** @return array{template:string,has_key:bool,key_hint:string} */
function radar_screenshot_config(): array
{
    return [
        'template' => (string)radar_setting('shot_url', ''),
        'has_key' => radar_secret_exists('shot_key'),
        'key_hint' => radar_secret_hint('shot_key'),
    ];
}

function radar_screenshot_template_valid(string $template): bool
{
    $probe = str_replace(['{url}', '{key}'], ['https%3A%2F%2Fexemplo.com.br%2F', 'x'], $template);
    return str_contains($template, '{url}') && strlen($template) <= 600 && radar_feed_safe_url($probe) !== null;
}

/**
 * Capture the article page with the configured third-party service. Never fabricates
 * a capture: without a configured service this refuses.
 *
 * @return array<string,mixed>
 */
function radar_screenshot_capture(int $newsId): array
{
    $config = radar_screenshot_config();
    if ($config['template'] === '' || !radar_screenshot_template_valid($config['template'])) {
        throw new RuntimeException('Captura de tela não configurada. Cadastre um serviço em Configurações de integração ou anexe um print manualmente.');
    }
    $news = radar_db_one('SELECT id, url, source_name, title FROM news WHERE id = ?', [$newsId]);
    if ($news === null) {
        throw new InvalidArgumentException('Notícia não encontrada.');
    }
    if (!radar_og_fetchable((string)$news['url'])) {
        throw new RuntimeException('O link desta notícia é do Google Notícias e não revela a página original; o serviço capturaria a página do Google. Use uma notícia com link direto do veículo ou anexe um print manual.');
    }
    $key = radar_secret_get('shot_key') ?? '';
    $endpoint = str_replace(['{url}', '{key}'], [rawurlencode((string)$news['url']), rawurlencode($key)], $config['template']);
    $response = radar_net_get($endpoint, ['headers' => ['Accept: image/jpeg,image/png,image/webp'], 'max_bytes' => RADAR_MEDIA_MAX_BYTES, 'timeout' => 45]);
    if ($response['error'] !== null || $response['status'] !== 200) {
        $detail = $response['error'] ?? 'HTTP ' . $response['status'];
        throw new RuntimeException('O serviço de captura não devolveu a imagem (' . str_replace($key !== '' ? $key : "\0", '[chave]', (string)$detail) . ').');
    }
    return radar_media_save_bytes($response['body'], 'screenshot', [
        'news_id' => $newsId,
        'label' => 'Captura de tela',
        'source_url' => (string)$news['url'],
        'credit' => $news['source_name'],
        'note' => 'Captura automática da página original em ' . date('d/m/Y H:i') . ' por serviço de terceiros. Confira direitos de uso.',
    ]);
}
