#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Registers the character images from the command line (no panel login needed).
 *
 *   php bin/import-character.php --character=clara --reference=folha.webp [--primary=frente.png] [--label="Frente"]
 *
 *   --character  which character (a slug such as beto or clara); the default character when omitted.
 *   --reference  a reference image (the sheet with the front, profile and 3/4 views, or a photo). It is added to
 *                the others and ALL of them go with every video prompt, so the video tool keeps the same face.
 *   --role       sheet (several views, the default) or photo.
 *   --primary    an image for the Studio preview; it becomes the main character image.
 *
 * Exit codes: 0 saved, 1 an image was refused, 2 wrong usage.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$options = getopt('', ['character:', 'reference:', 'primary:', 'label:', 'role:']);
$characterSlug = isset($options['character']) ? (is_array($options['character']) ? (string)$options['character'][0] : (string)$options['character']) : '';
if ($characterSlug !== '' && !radar_character_valid($characterSlug)) {
    fwrite(STDERR, 'Personagem desconhecido: ' . $characterSlug . ' (use ' . implode(', ', array_keys(radar_characters())) . ")\n");
    exit(2);
}
$first = static fn(mixed $value): string => is_array($value) ? (string)($value[0] ?? '') : (string)$value;
if (!isset($options['reference']) && !isset($options['primary'])) {
    fwrite(STDERR, "Uso: php bin/import-character.php --reference=ARQUIVO [--primary=ARQUIVO] [--label=TEXTO]\n");
    exit(2);
}

try {
    if (isset($options['reference'])) {
        $bytes = @file_get_contents($first($options['reference']));
        if ($bytes === false) {
            throw new InvalidArgumentException('Não foi possível ler a folha de referência.');
        }
        $media = radar_media_save_bytes($bytes, 'reference', [
            'character' => $characterSlug,
            'role' => isset($options['role']) ? $first($options['role']) : 'sheet',
            'label' => isset($options['label']) && !isset($options['primary']) ? $first($options['label']) : (isset($options['role']) && $first($options['role']) === 'photo' ? 'Foto' : 'Folha de referência'),
            'note' => 'Imagem de referência importada pela linha de comando.',
        ]);
        printf("Imagem de referência salva (%dx%d, %s).\n", $media['width'], $media['height'], $media['role']);
    }
    if (isset($options['primary'])) {
        $bytes = @file_get_contents($first($options['primary']));
        if ($bytes === false) {
            throw new InvalidArgumentException('Não foi possível ler a imagem principal.');
        }
        $media = radar_media_save_bytes($bytes, 'character', [
            'label' => isset($options['label']) ? $first($options['label']) : 'Frente',
            'is_primary' => true,
            'character' => $characterSlug,
            'note' => 'Imagem do personagem importada pela linha de comando.',
        ]);
        printf("Imagem principal do personagem salva (%dx%d).\n", $media['width'], $media['height']);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Erro: ' . $error->getMessage() . "\n");
    exit(1);
}
