<?php
declare(strict_types=1);

/**
 * The fixed character. The master prompt below is code, not data: it cannot be edited
 * from the panel, so every generation keeps the same identity. Reference images are data.
 */

const RADAR_CHARACTER_VERSION = 1;

function radar_character_master_prompt(string $language = 'pt'): string
{
    if ($language === 'en') {
        return 'Same recurring fictional Brazilian character in every shot: a Black Brazilian man, adult around 30 to 40 years old, heavyset and robust build, round face, short black hair, neat and discreet beard and mustache, modern dark-framed glasses, bright white and very expressive smile, thick gold chain necklace, gold wristwatch, plain black casual t-shirt. Natural, charismatic, memorable look. He looks like an ordinary person recording a spontaneous vertical video on a phone (in a car, at home or in a small office), never like a TV news anchor. Keep face, proportions, glasses, chain, watch and clothing identical in every generation, and use the attached reference images to preserve his identity.';
    }
    return 'Personagem fictício brasileiro, sempre o mesmo em todos os vídeos: homem negro brasileiro, adulto de aproximadamente 30 a 40 anos, corpo gordo e robusto, rosto arredondado, cabelo preto curto, barba e bigode discretos, óculos modernos de armação escura, sorriso branco e muito expressivo, corrente de ouro grossa, relógio dourado e camiseta preta casual. Aparência natural, carismática e marcante. Parece uma pessoa comum gravando um vídeo espontâneo na vertical com o celular (no carro, em casa ou no escritório), nunca um apresentador de telejornal. Manter rosto, proporções, óculos, corrente, relógio e roupa idênticos em todas as gerações, usando as imagens de referência enviadas para preservar a identidade.';
}

/** @return array<int,string> */
function radar_character_personality(): array
{
    return [
        'Inteligente e antenado nos acontecimentos',
        'Espontâneo e comunicativo',
        'Engraçado e provocador, sem humilhar ninguém',
        'Expressivo, fala com linguagem popular brasileira',
        'Consegue comentar assuntos sérios com clareza e respeito',
    ];
}

/** @return array<string,array{label:string,visual:string}> */
function radar_character_expressions(): array
{
    return [
        'sorriso-largo' => ['label' => 'Sorriso largo', 'visual' => 'a huge friendly smile showing white teeth, eyebrows slightly raised'],
        'surpreso' => ['label' => 'Surpreso', 'visual' => 'wide eyes behind the glasses, mouth open in surprise, eyebrows high'],
        'provocador' => ['label' => 'Provocador', 'visual' => 'one eyebrow raised, sly half-smile, leaning slightly toward the camera'],
        'rindo' => ['label' => 'Rindo', 'visual' => 'laughing out loud, eyes squeezed, head tilted back a little'],
        'serio' => ['label' => 'Sério', 'visual' => 'serious and respectful expression, calm eyes, no smile'],
        'incredulo' => ['label' => 'Incrédulo', 'visual' => 'disbelief, frowning with a small head shake, mouth slightly open'],
        'pensativo' => ['label' => 'Pensativo', 'visual' => 'thoughtful, hand on chin, looking slightly up then back at the camera'],
        'apontando' => ['label' => 'Apontando para a câmera', 'visual' => 'pointing at the camera with one finger, energetic and engaging'],
    ];
}

/** @return array<string,string> key => label */
function radar_character_scenes(): array
{
    return [
        'carro' => 'No carro',
        'casa' => 'Em casa',
        'escritorio' => 'No escritório',
    ];
}

function radar_character_scene_visual(string $scene): string
{
    return match ($scene) {
        'casa' => 'sitting on a sofa in a lived-in living room, soft warm daylight from a window',
        'escritorio' => 'at a small home office desk with a monitor and shelves behind him, soft natural light',
        default => 'sitting in the driver seat of a parked car, seatbelt on, daylight through the windshield',
    };
}

/** @return array<int,array<string,mixed>> */
function radar_character_images(): array
{
    return radar_db_all("SELECT id, mime, width, height, label, is_primary, created_at FROM media WHERE kind = 'character' ORDER BY is_primary DESC, created_at DESC");
}

/** @return array<string,mixed>|null */
function radar_character_primary(): ?array
{
    return radar_db_one("SELECT id, mime, width, height, label, is_primary FROM media WHERE kind = 'character' ORDER BY is_primary DESC, created_at DESC LIMIT 1");
}

function radar_character_set_primary(string $mediaId): bool
{
    if (radar_db_value("SELECT 1 FROM media WHERE id = ? AND kind = 'character'", [$mediaId]) === null) {
        return false;
    }
    radar_db_exec("UPDATE media SET is_primary = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE kind = 'character'", [$mediaId]);
    return true;
}

/**
 * Prompt for external AI video tools. Built from code (not by the language model) so the
 * character block stays identical. The dialogue is the user's own approved script.
 *
 * @param array{spoken:string,expression:string,scene:string,duration:int,headline:string,source:string} $parts
 */
function radar_video_prompt(array $parts): string
{
    $expressions = radar_character_expressions();
    $expression = $expressions[$parts['expression']] ?? $expressions['sorriso-largo'];
    $scene = radar_character_scene_visual($parts['scene']);
    $spoken = trim(preg_replace('/\s+/u', ' ', $parts['spoken']) ?? '');
    $seconds = max(15, min(35, $parts['duration']));
    return implode("\n", [
        'CHARACTER (use the attached reference images, do not change identity):',
        radar_character_master_prompt('en'),
        '',
        'SHOT: vertical 9:16, ' . $seconds . ' seconds, handheld smartphone selfie style, character framed from the chest up and looking straight at the camera, centered. Slight natural camera shake, no cinematic filters.',
        'SETTING: ' . $scene . '.',
        'EXPRESSION AND ENERGY: ' . $expression['visual'] . '. Spontaneous, funny and expressive body language, natural hand gestures.',
        'LAYOUT NOTE: the character occupies the lower half of the frame; keep the upper half visually calm because a real news screenshot will be composed there afterwards.',
        '',
        'DIALOGUE (spoken in natural, colloquial Brazilian Portuguese, with lip-sync, exactly as written):',
        '"' . $spoken . '"',
        '',
        'CONTEXT (do not show or invent any text on screen): reacting to the headline "' . trim($parts['headline']) . '" reported by ' . trim($parts['source']) . '.',
        'AVOID: news-anchor look, studio desk, teleprompter feel, extra people, subtitles burned into the video, logos, watermarks, distorted hands, a different face or body type, changing glasses/chain/watch/clothing.',
    ]);
}
