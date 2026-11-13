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
 *   --role       the kind of image: master (the official image), frontal, profile_right, profile_left, fullbody, seated,
 *                surprise, extra, sheet (several views, the default) or photo. The slots hold one image: a new one replaces the old one.
 *   --voice      an optional reference audio (mp3, wav, ogg or m4a, up to 8 MB) the user is allowed to use.
 *   --default    make this character the default one (selected first when a react is generated).
 *   --primary    an image for the Studio preview; it becomes the main character image.
 *
 * Exit codes: 0 saved, 1 an image was refused, 2 wrong usage.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$options = getopt('', ['character:', 'reference:', 'primary:', 'label:', 'role:', 'voice:', 'default']);
$characterSlug = isset($options['character']) ? (is_array($options['character']) ? (string)$options['character'][0] : (string)$options['character']) : '';
if ($characterSlug !== '' && !radar_character_valid($characterSlug)) {
    fwrite(STDERR, 'Personagem desconhecido: ' . $characterSlug . ' (use ' . implode(', ', array_keys(radar_characters())) . ")\n");
    exit(2);
}
$first = static fn(mixed $value): string => is_array($value) ? (string)($value[0] ?? '') : (string)$value;
if (!isset($options['reference']) && !isset($options['primary']) && !isset($options['voice']) && !isset($options['default'])) {
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
            'label' => isset($options['label']) && !isset($options['primary']) ? $first($options['label']) : (radar_reference_slots()[isset($options['role']) ? $first($options['role']) : 'sheet']['label'] ?? 'Folha de referência'),
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
    if (isset($options['voice'])) {
        $bytes = @file_get_contents($first($options['voice']));
        if ($bytes === false) {
            throw new InvalidArgumentException('Não foi possível ler o áudio de referência.');
        }
        $media = radar_voice_save_bytes($bytes, $characterSlug, isset($options['label']) ? $first($options['label']) : 'Áudio de referência');
        printf("Áudio de referência salvo (%s, %d bytes).\n", $media['mime'], $media['bytes']);
    }
    if (isset($options['default'])) {
        radar_character_default_set($characterSlug !== '' ? $characterSlug : radar_character_default_slug());
        printf("Personagem padrão: %s.\n", radar_character_get($characterSlug)['name']);
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Erro: ' . $error->getMessage() . "\n");
    exit(1);
}
