<?php
declare(strict_types=1);

/**
 * The fixed character. The master prompt below is code, not data: it cannot be edited
 * from the panel, so every generation keeps the same identity. Reference images are data.
 */

const RADAR_CHARACTER_VERSION = 1;

/** One scene = one clip made by the video tool: at most 25 s of speech (~2.5 spoken words per second). */
const RADAR_SCENE_MAX_SECONDS = 25;
const RADAR_SCENE_MAX_COUNT = 5;
const RADAR_SCENES_DEFAULT = 3;
/** What the writer aims for in each scene, leaving room under the 25 s limit. */
const RADAR_SCENE_TARGET_SECONDS = 22;

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

/**
 * The character reference sheet (one image with the front, profile and 3/4 views). It goes with the
 * video prompt: the video tool gets it attached to every scene so it keeps the same man.
 *
 * @return array<string,mixed>|null
 */
function radar_character_reference(): ?array
{
    return radar_db_one("SELECT id, mime, width, height, label FROM media WHERE kind = 'reference' ORDER BY created_at DESC LIMIT 1");
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
 * Video layouts. "caption_card" (the DEFAULT, listed first) is the character filling the frame with a big
 * caption on top and the news cover as a card at the bottom (the reference template); "split" is news on
 * top / character below.
 *
 * @return array<string,array{label:string,description:string,framing:string,layout:string}>
 */
function radar_video_templates(): array
{
    return [
        'caption_card' => [
            'label' => 'Vídeo em tela cheia, frase de impacto no topo e card da notícia',
            'description' => 'O vídeo do personagem ocupa o quadro inteiro. Uma faixa preta no topo traz a frase de impacto em maiúsculas; embaixo, no centro, um card com a imagem da notícia e, colada nela, a barra com FONTE • DATA e a manchete real.',
            'framing' => 'character filling the whole frame, framed from the chest up with his face centered at about 40% of the frame height (between the caption band and the news card), centered',
            'layout' => 'LAYOUT NOTE: full-frame vertical shot. A black caption band is composed over the top 22% of the frame and a news card over the bottom 38% (from about 62% of the height down), so keep his face and gestures inside the middle area, between 22% and 62% of the height. Nothing important in the top and bottom areas. Gestures never cover the face.',
        ],
        'split' => [
            'label' => 'Notícia em cima, personagem embaixo',
            'description' => 'Metade de cima com a imagem da notícia e a headline; metade de baixo com o personagem.',
            'framing' => 'character framed from the chest up, centered',
            'layout' => 'LAYOUT NOTE: the character occupies the lower half of the frame; keep the upper half visually calm because a real news screenshot will be composed there afterwards.',
        ],
    ];
}

function radar_video_template_valid(string $key): bool
{
    return isset(radar_video_templates()[$key]);
}

/** Template used when none is chosen (new reacts, the Studio, the API and the MCP). Older scripts keep "split". */
function radar_video_default_template(): string
{
    return 'caption_card';
}

/**
 * Fixed rules appended to EVERY video prompt (shown read-only in "Meu Personagem").
 * Video models tend to add a closing grin when the speech ends; the character must not.
 *
 * @return array<string,array{label:string,description:string,rule:string,avoid:string}>
 */
function radar_video_standards(): array
{
    return [
        'no_smile_after_speech' => [
            'label' => 'Sem sorrir depois da fala',
            'description' => 'Ele pode sorrir, brincar e gesticular enquanto fala. Na hora em que termina a última palavra, para de sorrir: boca fechada e relaxada, rosto neutro olhando para a câmera por cerca de 1 segundo, e o vídeo acaba. Sem sorriso de despedida, risada ou piscadinha no final.',
            'rule' => 'ENDING (default rule, do not override): he may smile, joke and gesture while he is speaking. The moment the last word is said he STOPS smiling: mouth closed, lips relaxed and neutral, no grin, no smirk, no laughing, no teeth showing, no wink, no smiling head-tilt. He holds a calm neutral face looking straight at the camera for about one second and the clip ends there. Never add a closing smile, a thank-you smile or a laugh after the dialogue.',
            'avoid' => 'smiling, grinning, laughing or winking after the last spoken word',
        ],
        'same_character_every_scene' => [
            'label' => 'Mesmo personagem e mesma reação em todas as cenas',
            'description' => 'Todo prompt manda anexar a folha de referência (frente, perfis e 3/4) e exige o mesmo homem, com o mesmo rosto, óculos, barba, corrente e camiseta, e a mesma reação em todas as cenas. Nada de segundo personagem, sósia ou pessoas extras.',
            'rule' => 'CONTINUITY (the same rules in every scene of this video): exactly ONE person appears, always the same man from the reference sheet: same face, same skin tone, same hair, same beard, same glasses, same gold chain, same black t-shirt, same body and same voice. Never create a second or a different character, a look-alike, a double or extra people, and never change his appearance from one scene to the next.',
            'avoid' => 'a second character, a different person, a look-alike or double, a changed face or outfit between scenes',
        ],
    ];
}

/** Words in a text, counting letters and digits only (same rule as the speech-time estimate). */
function radar_scene_words(string $text): int
{
    $count = preg_match_all('/[\p{L}\p{N}]+/u', $text);
    return $count === false ? 0 : $count;
}

/** Seconds a scene needs: its words at ~2.5 words/s plus a breath, never above the 25 s limit. */
function radar_scene_seconds(string $text): int
{
    return max(3, min(RADAR_SCENE_MAX_SECONDS, (int)ceil(radar_scene_words($text) / 2.5) + 1));
}

/**
 * Cut the spoken text into scenes of at most 25 s each, always where a sentence ends (a sentence longer
 * than a scene is cut at a comma). The requested number of scenes is a goal: fewer come out when there
 * are not enough sentences and more when the text needs them to stay under the limit.
 *
 * @return array<int,string>
 */
function radar_scene_split(string $spoken, int $scenes = 1): array
{
    $max = (int)floor(RADAR_SCENE_MAX_SECONDS * 2.5);
    $text = trim(preg_replace('/\s+/u', ' ', $spoken) ?? '');
    if ($text === '') {
        return [''];
    }
    $pieces = [];
    foreach (preg_split('/(?<=[.!?…])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $sentence) {
        $chunk = [];
        foreach (preg_split('/\s+/u', trim($sentence), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            $chunk[] = $token;
            if (radar_scene_words(implode(' ', $chunk)) >= $max) {
                $cut = count($chunk);
                for ($i = $cut - 1; $i >= intdiv($cut, 2); $i--) {
                    if (preg_match('/[,;:]$/u', $chunk[$i]) === 1) {
                        $cut = $i + 1;
                        break;
                    }
                }
                $pieces[] = implode(' ', array_slice($chunk, 0, $cut));
                $chunk = array_slice($chunk, $cut);
            }
        }
        if ($chunk !== []) {
            $pieces[] = implode(' ', $chunk);
        }
    }
    $sizes = array_map('radar_scene_words', $pieces);
    $total = array_sum($sizes);
    $count = max(1, min(count($pieces), max($scenes, (int)ceil($total / $max))));
    $out = [];
    $current = [];
    $currentWords = 0;
    $remaining = $total;
    foreach ($pieces as $index => $piece) {
        $size = $sizes[$index];
        $left = $count - count($out);
        if ($current !== [] && $left > 1) {
            $target = $remaining / $left;
            $mustClose = count($pieces) - $index <= $left - 1;
            if ($mustClose || $currentWords + $size > $max || abs($currentWords - $target) <= abs($currentWords + $size - $target)) {
                $out[] = implode(' ', $current);
                $remaining -= $currentWords;
                $current = [];
                $currentWords = 0;
            }
        }
        $current[] = $piece;
        $currentWords += $size;
    }
    $out[] = implode(' ', $current);
    return $out;
}

/**
 * One self-contained prompt per scene for external AI video tools. Built from code (not by the language
 * model) so the character block, the reference-image instruction and the fixed standards stay identical.
 * The dialogue is the user's own approved script, cut into scenes of at most 25 s.
 *
 * @param array{spoken:string,expression:string,scene:string,headline:string,source:string,layout?:string,scenes?:int} $parts
 * @return array<int,string>
 */
function radar_video_prompts(array $parts): array
{
    $expressions = radar_character_expressions();
    $expression = $expressions[$parts['expression']] ?? $expressions['sorriso-largo'];
    $setting = radar_character_scene_visual($parts['scene']);
    $templates = radar_video_templates();
    $template = $templates[(string)($parts['layout'] ?? 'split')] ?? $templates['split'];
    $standards = radar_video_standards();
    $chunks = radar_scene_split((string)$parts['spoken'], (int)($parts['scenes'] ?? 1));
    $total = count($chunks);
    $prompts = [];
    foreach ($chunks as $index => $chunk) {
        $prompts[] = implode("\n", [
            $total > 1
                ? 'SCENE ' . ($index + 1) . ' OF ' . $total . ': one separate clip. The clips are edited together afterwards, so repeat exactly the same character, setting and camera framing in every scene.'
                    . ($index > 0 ? ' This scene continues the same talk: he does not greet again or introduce himself again.' : '')
                : 'SCENE 1 OF 1: one single clip.',
            '',
            'REFERENCE IMAGE (attach the character reference sheet to the video tool together with this prompt, in every scene): one image with five views of the SAME man: front, left profile, right profile, 3/4 left and 3/4 right. It is the only source of his face, head shape, hair, beard, glasses, gold chain and black t-shirt. Copy him exactly from it and never redesign him.',
            '',
            'CHARACTER (use the attached reference image, do not change identity):',
            radar_character_master_prompt('en'),
            '',
            $standards['same_character_every_scene']['rule'],
            '',
            'SHOT: vertical 9:16, about ' . radar_scene_seconds($chunk) . ' seconds (never more than ' . RADAR_SCENE_MAX_SECONDS . ' seconds), handheld smartphone selfie style, ' . $template['framing'] . ' and looking straight at the camera. Slight natural camera shake, no cinematic filters.',
            'SETTING: ' . $setting . '.',
            'EXPRESSION AND ENERGY: ' . $expression['visual'] . '. Keep this same reaction and energy in every scene. Spontaneous, funny and expressive body language, natural hand gestures.',
            $template['layout'],
            '',
            'DIALOGUE (spoken in natural, colloquial Brazilian Portuguese, with lip-sync, exactly as written):',
            '"' . $chunk . '"',
            '',
            $standards['no_smile_after_speech']['rule'],
            '',
            'CONTEXT (do not show or invent any text on screen): reacting to the headline "' . trim($parts['headline']) . '" reported by ' . trim($parts['source']) . '.',
            'AVOID: ' . $standards['no_smile_after_speech']['avoid'] . ', ' . $standards['same_character_every_scene']['avoid'] . ', news-anchor look, studio desk, teleprompter feel, extra people, subtitles burned into the video, logos, watermarks, distorted hands, a different face or body type, changing glasses/chain/watch/clothing.',
        ]);
    }
    return $prompts;
}

/** All scene prompts in one text (what is stored, exported and shown as a whole). */
function radar_video_prompt(array $parts): string
{
    return implode("\n\n" . str_repeat('-', 40) . "\n\n", radar_video_prompts($parts));
}
