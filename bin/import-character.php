#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Registers the character images from the command line (no panel login needed).
 *
 *   php bin/import-character.php --reference=folha.webp [--primary=frente.png] [--label="Frente"]
 *
 *   --reference  the reference sheet (front, profiles, 3/4 views). It replaces the current one and goes
 *                with every video prompt, so the video tool keeps the same character in every scene.
 *   --primary    an image for the Studio preview; it becomes the main character image.
 *
 * Exit codes: 0 saved, 1 an image was refused, 2 wrong usage.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$options = getopt('', ['reference:', 'primary:', 'label:']);
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
            'label' => 'Folha de referência do personagem',
            'note' => 'Imagem de referência importada pela linha de comando.',
        ]);
        printf("Folha de referência salva (%dx%d).\n", $media['width'], $media['height']);
    }
    if (isset($options['primary'])) {
        $bytes = @file_get_contents($first($options['primary']));
        if ($bytes === false) {
            throw new InvalidArgumentException('Não foi possível ler a imagem principal.');
        }
        $media = radar_media_save_bytes($bytes, 'character', [
            'label' => isset($options['label']) ? $first($options['label']) : 'Frente',
            'is_primary' => true,
            'note' => 'Imagem do personagem importada pela linha de comando.',
        ]);
        printf("Imagem principal do personagem salva (%dx%d).\n", $media['width'], $media['height']);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Erro: ' . $error->getMessage() . "\n");
    exit(1);
}
