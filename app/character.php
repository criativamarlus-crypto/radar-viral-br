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
/** Reference images (sheets and photos) a character can keep: all of them travel with every video prompt. */
const RADAR_REFERENCES_MAX = 6;
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
 * The reference images of a character: sheets with several views and photos. ALL of them go with the video
 * prompt, so the video tool keeps the same face. Sheets come first.
 *
 * @return array<int,array<string,mixed>>
 */
function radar_character_references(?string $slug = null): array
{
    return radar_db_all("SELECT id, mime, width, height, label, role, character_slug FROM media WHERE kind = 'reference' AND character_slug = ? ORDER BY CASE role WHEN 'sheet' THEN 0 ELSE 1 END, created_at, rowid", [radar_character_slug($slug)]);
}

/** The first reference image (the sheet when there is one), or null. @return array<string,mixed>|null */
function radar_character_reference(?string $slug = null): ?array
{
    return radar_character_references($slug)[0] ?? null;
}

/** What the prompt needs to know about the references: the kind and the label of each. @return array<int,array{role:string,label:string}> */
function radar_character_reference_roles(?string $slug = null): array
{
    return array_map(static fn(array $r): array => ['role' => (string)$r['role'] === 'photo' ? 'photo' : 'sheet', 'label' => (string)$r['label']], radar_character_references($slug));
}

/**
 * The paragraph of every scene prompt that tells the video tool which images to attach. With no images, or
 * only a sheet, it is the classic text; with photos it numbers every image.
 *
 * @param array<int,array{role:string,label:string}> $references
 */
function radar_reference_paragraph(array $references): string
{
    $count = count($references);
    if ($count === 0 || ($count === 1 && ($references[0]['role'] ?? 'sheet') === 'sheet')) {
        return 'REFERENCE IMAGE (attach the character reference sheet to the video tool together with this prompt, in every scene): one image with five views of the SAME {man}: front, left profile, right profile, 3/4 left and 3/4 right. Use it ONLY to keep {his} face and identity: face shape and features, skin tone, hair, facial hair (if any), glasses and body build. Copy those exactly and never redesign {him}. Do not copy the clothes, accessories, pose or grey background of the sheet.';
    }
    $items = [];
    foreach (array_values($references) as $index => $reference) {
        $label = radar_direction_text($reference['label'] ?? '', 80);
        $items[] = ($index + 1) . ') ' . (($reference['role'] ?? 'sheet') === 'sheet'
            ? 'a character sheet with several views of the SAME {man} (front, profiles and 3/4 views)'
            : 'a photo of the SAME {man}' . ($label !== '' ? ' (' . $label . ')' : ''));
    }
    return 'REFERENCE IMAGE' . ($count > 1 ? 'S (attach ALL ' . $count . ' images' : ' (attach this image') . ' to the video tool together with this prompt, in every scene): ' . implode('; ', $items)
        . '. Use ' . ($count > 1 ? 'them' : 'it') . ' ONLY to keep {his} face and identity: face shape and features, skin tone, hair, facial hair (if any), glasses and body build. Copy those exactly and never redesign {him}. Do not copy the clothes, accessories, pose or background of the ' . ($count > 1 ? 'images' : 'image') . '.';
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
 * The template as a detailed brief for the video tool: what goes where (as a percentage of the frame), the exact
 * texts and what must not appear. The template image (the Studio's 1080x1920 composition) goes along with it.
 *
 * @param array{caption?:string,headline?:string,source?:string,source_line?:string} $texts
 */
function radar_template_brief(string $layout, array $texts): string
{
    $headline = radar_direction_text($texts['headline'] ?? '', 200);
    $source = radar_direction_text($texts['source'] ?? '', 80);
    $intro = 'TEMPLATE IMAGE (attach the template image to the video tool together with this prompt, in every scene): a vertical 9:16 mock-up of the final frame. Deliver the clip in EXACTLY this layout. The person in the mock-up is only a placeholder: the character comes from the REFERENCE IMAGES.';
    if ($layout === 'caption_card') {
        $caption = mb_strtoupper(radar_direction_text($texts['caption'] ?? '', 200) ?: $headline);
        $sourceLine = mb_strtoupper(radar_direction_text($texts['source_line'] ?? '', 120) ?: $source);
        return implode("\n", [
            $intro,
            'TEMPLATE LAYOUT (vertical 9:16 frame; positions as a percentage of the frame):',
            '1) FULL-FRAME VIDEO: the character fills the whole frame, with {his} face between 22% and 62% of the height.',
            '2) TOP BAND, from 0% to about 17% of the height: a solid black band across the full width. Centered inside it, up to 3 lines of the IMPACT PHRASE in bold white uppercase heavy sans-serif, with comfortable side margins: "' . $caption . '".',
            '3) NEWS CARD, bottom center, from about 62% to 94% of the height and 71% of the frame width, centered horizontally, with a thin white border and a soft drop shadow. Its upper part (about 55% of the card) is the NEWS IMAGE (the article thumbnail, attached to this prompt). Its lower part (about 45%) is a solid black info bar: a first line in bold yellow uppercase, "' . $sourceLine . '", and below it the real headline in bold white uppercase, up to 3 lines, "' . mb_strtoupper($headline) . '".',
            '4) NOTHING ELSE on screen: no other text, subtitles, logos or watermarks. Spell every word exactly as written above. If the video tool cannot render text reliably, keep the band and the card as clean flat areas (the text and the photo are added in editing) and never write different words.',
        ]);
    }
    return implode("\n", [
        $intro,
        'TEMPLATE LAYOUT (vertical 9:16 frame; positions as a percentage of the frame height):',
        '1) TOP HALF, 0% to 50%: the NEWS IMAGE (the article thumbnail, attached to this prompt) filling the whole half, with a dark gradient over its lower part. On the gradient, near the bottom of the half, the headline in bold white sans-serif, up to 4 lines: "' . $headline . '". In the top-left corner, a small rounded dark pill with white text: "Fonte: ' . $source . '".',
        '2) BOTTOM HALF, 50% to 100%: the character, framed from the chest up, centered and anchored to the top of the half. A thin yellow line separates the two halves.',
        '3) NOTHING ELSE on screen: no other text, subtitles, logos or watermarks. Spell every word exactly as written above. If the video tool cannot render text reliably, keep the top half as a clean area (the photo and the text are added in editing) and never write different words.',
    ]);
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
            'description' => 'Todo prompt manda anexar todas as imagens de referência (folha e fotos) só para manter o rosto e exige o mesmo personagem em todas as cenas, com a mesma reação. A roupa e os acessórios ficam iguais dentro do vídeo; só o ângulo e os gestos mudam de cena para cena. Nada de segundo personagem, sósia ou pessoas extras.',
            'rule' => 'CONTINUITY (the same rules in every scene of this video): exactly ONE person appears, always the same {man} from the reference images: same face and features, same skin tone, same hair, same facial hair (if any), same glasses, same body build and same voice. {His} clothes and accessories are the ones in WARDROBE below and stay identical in every scene of this video; only the camera angle and {his} gestures change from scene to scene. Never create a second or a different character, a look-alike, a double or extra people, and never change {his} face between scenes.',
            'avoid' => 'a second character, a different person, a look-alike or double, a changed face between scenes',
        ],
        'new_look_every_video' => [
            'label' => 'Visual novo a cada notícia',
            'description' => 'As imagens de referência servem só para manter o rosto. A roupa, os acessórios, a iluminação e o ângulo são criados para cada notícia, combinando com o tom do assunto, e evitam repetir o visual dos vídeos anteriores.',
            'rule' => 'VARIATION (default rule, do not override): the reference images are used ONLY to keep {his} face and identity. Do not copy their clothes, accessories, pose or background. Dress {him} as described in WARDROBE, which was chosen for the tone of this news and differs from the previous videos.',
            'avoid' => 'copying the clothes or the background of the reference images',
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
 * @param array{spoken:string,expression:string,scene:string,headline:string,source:string,layout?:string,scenes?:int,direction?:array<string,mixed>,character?:string,references?:array<int,array{role:string,label:string}>,caption?:string,source_line?:string} $parts
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
    $referenceText = radar_reference_paragraph($parts['references'] ?? radar_character_reference_roles($slug));
    $templateKey = isset($templates[(string)($parts['layout'] ?? 'split')]) ? (string)($parts['layout'] ?? 'split') : 'split';
    $templateBrief = radar_template_brief($templateKey, ['caption' => $parts['caption'] ?? '', 'headline' => $parts['headline'], 'source' => $parts['source'], 'source_line' => $parts['source_line'] ?? '']);
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
            $referenceText,
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
            $templateBrief,
            '',
            'DIALOGUE (spoken in natural, colloquial Brazilian Portuguese, with lip-sync, exactly as written):',
            '"' . $chunk . '"',
            '',
            $standards['no_smile_after_speech']['rule'],
            '',
            'CONTEXT: reacting to the headline "' . trim($parts['headline']) . '" reported by ' . trim($parts['source']) . '. The only text allowed on screen is the text listed in TEMPLATE LAYOUT; never invent other text.',
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
