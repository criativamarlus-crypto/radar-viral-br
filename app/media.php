<?php
declare(strict_types=1);

function radar_media_validate(string $path, int $size): array
{
    if ($size < 1 || $size > 5 * 1024 * 1024 || !is_file($path) || filesize($path) !== $size) {
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
    $storage = rtrim((string)radar_config()['storage_path'], '/');
    $directory = $storage . '/media';
    if ((!is_dir($directory) && !@mkdir($directory, 0700, true)) || is_link($directory)) {
        throw new RuntimeException('Armazenamento de imagens indisponível.');
    }
    return $directory;
}
