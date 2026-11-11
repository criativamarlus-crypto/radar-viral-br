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

function radar_character_master_prompt(string $language = 'pt', ?string $slug = null): string
{
    $preset = radar_characters()[radar_character_slug($slug)];
    return $language === 'en' ? $preset['identity_en'] : $preset['identity_pt'];
}

/** @return array<int,string> */
function radar_character_personality(?string $slug = null): array
{
    return radar_characters()[radar_character_slug($slug)]['personality'];
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
        'escritorio' => 'at a small home office desk with a monitor and shelves behind {him}, soft natural light',
        default => 'sitting in the driver seat of a parked car, seatbelt on, daylight through the windshield',
    };
}

/**
 * Still images of a character (used by the Studio preview). Without a slug: the default character.
 *
 * @return array<int,array<string,mixed>>
 */
function radar_character_images(?string $slug = null): array
{
    return radar_db_all("SELECT id, mime, width, height, label, is_primary, character_slug, created_at FROM media WHERE kind = 'character' AND character_slug = ? ORDER BY is_primary DESC, created_at DESC", [radar_character_slug($slug)]);
}

/** Every character image of every character (the Studio switches between them). @return array<int,array<string,mixed>> */
function radar_character_images_all(): array
{
    return radar_db_all("SELECT id, mime, width, height, label, is_primary, character_slug, created_at FROM media WHERE kind = 'character' ORDER BY character_slug, is_primary DESC, created_at DESC");
}

/**
 * The reference sheet of a character (one image with the front, profile and 3/4 views). It goes with the
 * video prompt: the video tool gets it attached to every scene so it keeps the same face.
 *
 * @return array<string,mixed>|null
 */
function radar_character_reference(?string $slug = null): ?array
{
    return radar_db_one("SELECT id, mime, width, height, label, character_slug FROM media WHERE kind = 'reference' AND character_slug = ? ORDER BY created_at DESC LIMIT 1", [radar_character_slug($slug)]);
}

/** @return array<string,mixed>|null */
function radar_character_primary(?string $slug = null): ?array
{
    return radar_db_one("SELECT id, mime, width, height, label, is_primary, character_slug FROM media WHERE kind = 'character' AND character_slug = ? ORDER BY is_primary DESC, created_at DESC LIMIT 1", [radar_character_slug($slug)]);
}

function radar_character_set_primary(string $mediaId): bool
{
    $slug = radar_db_value("SELECT character_slug FROM media WHERE id = ? AND kind = 'character'", [$mediaId]);
    if ($slug === null) {
        return false;
    }
    radar_db_exec("UPDATE media SET is_primary = CASE WHEN id = ? THEN 1 ELSE 0 END WHERE kind = 'character' AND character_slug = ?", [$mediaId, $slug]);
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
            'framing' => 'character filling the whole frame, framed from the chest up with {his} face centered at about 40% of the frame height (between the caption band and the news card), centered',
            'layout' => 'LAYOUT NOTE: full-frame vertical shot. A black caption band is composed over the top 22% of the frame and a news card over the bottom 38% (from about 62% of the height down), so keep {his} face and gestures inside the middle area, between 22% and 62% of the height. Nothing important in the top and bottom areas. Gestures never cover the face.',
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
 * The texts are written with {he}/{his}/{man} tokens and come out right for a man or a woman.
 *
 * @return array<string,array{label:string,description:string,rule:string,avoid:string}>
 */
function radar_video_standards(?string $slug = null): array
{
    $standards = [
        'no_smile_after_speech' => [
            'label' => 'Sem sorrir depois da fala',
            'description' => '{Ele} pode sorrir, brincar e gesticular enquanto fala. Na hora em que termina a última palavra, para de sorrir: boca fechada e relaxada, rosto neutro olhando para a câmera por cerca de 1 segundo, e o vídeo acaba. Sem sorriso de despedida, risada ou piscadinha no final.',
            'rule' => 'ENDING (default rule, do not override): {he} may smile, joke and gesture while {he} is speaking. The moment the last word is said {he} STOPS smiling: mouth closed, lips relaxed and neutral, no grin, no smirk, no laughing, no teeth showing, no wink, no smiling head-tilt. {He} holds a calm neutral face looking straight at the camera for about one second and the clip ends there. Never add a closing smile, a thank-you smile or a laugh after the dialogue.',
            'avoid' => 'smiling, grinning, laughing or winking after the last spoken word',
        ],
        'same_character_every_scene' => [
            'label' => 'Mesmo personagem e mesma reação em todas as cenas',
            'description' => 'Todo prompt manda anexar a folha de referência só para manter o rosto e exige o mesmo personagem em todas as cenas, com a mesma reação. A roupa e os acessórios ficam iguais dentro do vídeo; só o ângulo e os gestos mudam de cena para cena. Nada de segundo personagem, sósia ou pessoas extras.',
            'rule' => 'CONTINUITY (the same rules in every scene of this video): exactly ONE person appears, always the same {man} from the reference sheet: same face and features, same skin tone, same hair, same facial hair (if any), same glasses, same body build and same voice. {His} clothes and accessories are the ones in WARDROBE below and stay identical in every scene of this video; only the camera angle and {his} gestures change from scene to scene. Never create a second or a different character, a look-alike, a double or extra people, and never change {his} face between scenes.',
            'avoid' => 'a second character, a different person, a look-alike or double, a changed face between scenes',
        ],
        'new_look_every_video' => [
            'label' => 'Visual novo a cada notícia',
            'description' => 'A folha de referência serve só para manter o rosto. A roupa, os acessórios, a iluminação e o ângulo são criados para cada notícia, combinando com o tom do assunto, e evitam repetir o visual dos vídeos anteriores.',
            'rule' => 'VARIATION (default rule, do not override): the reference sheet is used ONLY to keep {his} face and identity. Do not copy its clothes, accessories, pose or grey background. Dress {him} as described in WARDROBE, which was chosen for the tone of this news and differs from the previous videos.',
            'avoid' => 'copying the clothes or the grey background of the reference sheet',
        ],
    ];
    foreach ($standards as $key => $standard) {
        foreach ($standard as $field => $text) {
            $standards[$key][$field] = radar_character_words($text, $slug);
        }
    }
    return $standards;
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
 * @param array{spoken:string,expression:string,scene:string,headline:string,source:string,layout?:string,scenes?:int,direction?:array<string,mixed>,character?:string} $parts
 * @return array<int,string>
 */
function radar_video_prompts(array $parts): array
{
    $expressions = radar_character_expressions();
    $expression = $expressions[$parts['expression']] ?? $expressions['sorriso-largo'];
    $setting = radar_character_scene_visual($parts['scene']);
    $templates = radar_video_templates();
    $template = $templates[(string)($parts['layout'] ?? 'split')] ?? $templates['split'];
    $slug = radar_character_slug($parts['character'] ?? null);
    $standards = radar_video_standards($slug);
    $chunks = radar_scene_split((string)$parts['spoken'], (int)($parts['scenes'] ?? 1));
    $total = count($chunks);
    $direction = radar_direction_normalize($parts['direction'] ?? null, 'manual') ?? radar_direction_default($slug);
    $moods = radar_direction_moods();
    $prompts = [];
    foreach ($chunks as $index => $chunk) {
        $view = radar_direction_for_scene($direction, $index);
        $prompts[] = radar_character_words(implode("\n", [
            $total > 1
                ? 'SCENE ' . ($index + 1) . ' OF ' . $total . ': one separate clip. The clips are edited together afterwards, so keep exactly the same character, wardrobe, setting and lighting in every scene; only the camera angle and {his} gestures change.'
                    . ($index > 0 ? ' This scene continues the same talk: {he} does not greet again or introduce {him}self again.' : '')
                : 'SCENE 1 OF 1: one single clip.',
            '',
            'REFERENCE IMAGE (attach the character reference sheet to the video tool together with this prompt, in every scene): one image with five views of the SAME {man}: front, left profile, right profile, 3/4 left and 3/4 right. Use it ONLY to keep {his} face and identity: face shape and features, skin tone, hair, facial hair (if any), glasses and body build. Copy those exactly and never redesign {him}. Do not copy the clothes, accessories, pose or grey background of the sheet.',
            '',
            'CHARACTER (use the attached reference image, do not change identity):',
            radar_character_master_prompt('en', $slug),
            '',
            $standards['same_character_every_scene']['rule'],
            $standards['new_look_every_video']['rule'],
            '',
            'WARDROBE (made for this news; keep it identical in every scene of this video): ' . $direction['outfit'] . '.' . ($direction['accessories'] !== '' ? ' Accessories: ' . $direction['accessories'] . '.' : ' No extra accessories.'),
            '',
            'SHOT: vertical 9:16, about ' . radar_scene_seconds($chunk) . ' seconds (never more than ' . RADAR_SCENE_MAX_SECONDS . ' seconds), handheld smartphone selfie style, ' . $template['framing'] . ' and looking straight at the camera. Slight natural camera shake, no cinematic filters.',
            'CAMERA ANGLE FOR THIS SCENE: ' . $view['angle'] . '. Keep {his} face inside the safe area described in the LAYOUT NOTE. Body language in this scene: {he} ' . $view['gesture'] . '.',
            'SETTING: ' . $setting . '.',
            'LIGHTING AND MOOD: ' . ($direction['lighting'] !== '' ? $direction['lighting'] : 'soft natural daylight') . ' (' . ($moods[$direction['mood']] ?? $moods['casual']) . ' look).',
            'EXPRESSION AND ENERGY: ' . $expression['visual'] . '. Keep this same reaction and energy in every scene. Spontaneous, funny and expressive body language, natural hand gestures.',
            $template['layout'],
            '',
            'DIALOGUE (spoken in natural, colloquial Brazilian Portuguese, with lip-sync, exactly as written):',
            '"' . $chunk . '"',
            '',
            $standards['no_smile_after_speech']['rule'],
            '',
            'CONTEXT (do not show or invent any text on screen): reacting to the headline "' . trim($parts['headline']) . '" reported by ' . trim($parts['source']) . '.',
            'AVOID: ' . $standards['no_smile_after_speech']['avoid'] . ', ' . $standards['same_character_every_scene']['avoid'] . ', ' . $standards['new_look_every_video']['avoid'] . ', news-anchor look, studio desk, teleprompter feel, extra people, subtitles burned into the video, logos, watermarks, distorted hands, a different face or body type, changing {his} face, glasses or body build, clothes that change between scenes, logos or readable text on clothes.',
        ]), $slug);
    }
    return $prompts;
}

/** All scene prompts in one text (what is stored, exported and shown as a whole). */
function radar_video_prompt(array $parts): string
{
    return implode("\n\n" . str_repeat('-', 40) . "\n\n", radar_video_prompts($parts));
}
