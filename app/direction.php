<?php
declare(strict_types=1);

/**
 * Visual direction of a react: what the character wears, the accessories, the light and the camera
 * angle of each scene. The FACE never changes (the reference sheet keeps it); everything else is made
 * for the news at hand, follows its tone and is never the same twice in a row.
 *
 * A direction comes from the AI (when configured) or from the curated pools below. All text is English
 * because it goes straight into the prompt for the video tool.
 *
 * @phpstan-type Direction array{outfit:string,accessories:string,lighting:string,angles:array<int,string>,gestures:array<int,string>,mood:string,origin:string}
 */

/** @return array<string,string> mood => label shown in the panel */
function radar_direction_moods(): array
{
    return [
        'serious' => 'Sério e sóbrio',
        'business' => 'Executivo casual',
        'sport' => 'Esportivo',
        'fun' => 'Colorido e leve',
        'tech' => 'Tech casual',
        'casual' => 'Casual do dia a dia',
    ];
}

/** The tone of the news decides the look: serious topics get sober clothes, light ones get color. */
function radar_direction_mood(string $category, string $expression = ''): string
{
    if ($expression === 'serio' || in_array($category, ['Política', 'Notícias internacionais'], true)) {
        return 'serious';
    }
    return match ($category) {
        'Finanças', 'Cartão de crédito', 'Finanças pessoais', 'Economia' => 'business',
        'Futebol', 'Esportes' => 'sport',
        'Famosos', 'Entretenimento', 'Curiosidades', 'Assuntos virais' => 'fun',
        'Tecnologia' => 'tech',
        default => 'casual',
    };
}

/**
 * Outfits (shared base plus what the character adds), accessories (the character's own) and light, by mood.
 *
 * @return array<string,array<string,array<int,string>>> pool => mood => options
 */
function radar_direction_pools(?string $slug = null): array
{
    $preset = radar_characters()[radar_character_slug($slug)];
    $pools = [
        'outfit' => [
            'serious' => [
                'a charcoal grey crew-neck sweater over a plain white collared shirt',
                'a dark navy blazer over a plain black t-shirt',
                'a deep burgundy knit polo with a soft collar',
                'a black henley shirt with the sleeves pushed to the forearms',
                'a dark olive overshirt open over a heather grey t-shirt',
                'a charcoal merino turtleneck',
                'a navy zip-up bomber jacket over a plain white tee',
                'a plain dark grey hoodie with no logos or prints',
                'a black leather jacket over a plain dark grey tee',
                'a slate blue button-up shirt, top button open, no tie',
            ],
            'business' => [
                'a crisp light-blue button-up shirt with the sleeves rolled to the forearms',
                'a navy blazer over a plain white t-shirt',
                'a white linen shirt with an open collar',
                'a grey knit vest over a white shirt',
                'a forest green polo shirt with a structured collar',
                'a camel cardigan over a plain black tee',
                'a slim navy quarter-zip sweater over a white collar',
                'a light grey blazer over a black turtleneck',
                'a pale pink oxford shirt with the sleeves rolled up',
                'a charcoal overshirt over a crisp white tee',
            ],
            'sport' => [
                'a plain bright yellow athletic t-shirt with no logos',
                'a green and white track jacket with no badges or logos',
                'a plain red training top with no club badge or text',
                'a sky blue polo shirt with a white collar',
                'a black long-sleeve compression top with a plain white stripe',
                'a royal blue jersey-style tee with no numbers, names or logos',
                'a white sleeveless training shirt layered under an open grey zip hoodie',
                'an orange windbreaker over a black tee, no logos',
            ],
            'fun' => [
                'a bright orange t-shirt with a relaxed fit',
                'a loud tropical floral short-sleeve shirt, open over a white tee',
                'a teal bomber jacket over a mustard yellow tee',
                'a violet hoodie with a front pocket and no prints',
                'a mint green polo over a white tee',
                'a red denim jacket over a plain white tee',
                'a hot pink oversized shirt with the sleeves rolled',
                'a colorful geometric-pattern shirt with no readable text',
                'a cobalt blue tracksuit top with white piping',
            ],
            'tech' => [
                'a dark grey crew-neck tee under a charcoal overshirt',
                'a plain black hoodie with no logos',
                'a white tee under a light denim jacket',
                'a navy tech-fleece zip jacket',
                'a teal t-shirt with a black bomber jacket',
                'an indigo mock-neck sweater',
                'an olive utility shirt with chest pockets',
                'a heather grey sweatshirt with a quarter zip',
            ],
            'casual' => [
                'a plain white t-shirt with a relaxed fit',
                'a grey henley with the top buttons open',
                'an olive green tee',
                'a denim shirt open over a white tee',
                'a mustard crew-neck sweatshirt',
                'a navy polo shirt',
                'a maroon tee under an open black overshirt',
                'a sand-colored short-sleeve button-up shirt',
            ],
        ],
        'lighting' => [
            'serious' => ['cool overcast daylight with soft shadows', 'low-key lighting with a soft warm rim light', 'neutral flat indoor light, calm and clean', 'dim window light with a quiet, tense mood'],
            'business' => ['bright even indoor light, clean and professional', 'soft warm daylight from the side', 'cool morning light through a window', 'clean neutral light with a subtle warm background lamp'],
            'sport' => ['bright sunny daylight with crisp contrast', 'golden-hour sunlight with long warm highlights', 'energetic high-contrast daylight', 'stadium-evening feel, warm light with a cooler background'],
            'fun' => ['golden-hour sunlight through the window, warm and playful', 'bright saturated daylight with a lively mood', 'neon pink and blue accent light in the background', 'warm party-like light with soft colorful bokeh'],
            'tech' => ['cool blue accent light with a dark desk lamp glow', 'soft screen glow on {his} face with ambient room light', 'clean white light with a hint of teal in the background', 'low evening light with a monitor glow behind him'],
            'casual' => ['soft warm daylight from a window', 'overcast natural light, relaxed and honest', 'late-afternoon sun with gentle shadows', 'bright even daylight'],
        ],
    ];
    foreach (array_keys(radar_direction_moods()) as $mood) {
        $pools['outfit'][$mood] = array_values(array_unique(array_merge($pools['outfit'][$mood], $preset['outfits_extra'][$mood] ?? [])));
        $pools['accessories'][$mood] = $preset['accessories'][$mood] ?? ['no accessories except a simple watch'];
    }
    return $pools;
}

/** @return array<int,string> */
function radar_direction_angle_pool(): array
{
    return [
        'eye-level handheld selfie angle, slightly off-center',
        'slightly low angle looking up at {him}, giving {him} presence',
        'slightly high angle, like a phone held a little above {his} head',
        'three-quarter view from {his} left side; {he} turns to the camera when {he} speaks',
        'three-quarter view from {his} right side; {he} glances away, then back at the lens',
        'tight close-up with {his} face filling the middle of the frame',
        'medium shot with a slow handheld push-in toward {his} face',
        'camera tilted about 5 degrees for energy, eye-level',
        'starts with a profile glimpse, then {he} turns and faces the lens',
        'steady medium shot with a gentle slide to the side',
    ];
}

/** @return array<int,string> */
function radar_direction_gesture_pool(): array
{
    return [
        'counts a point on {his} fingers',
        'opens both palms toward the camera',
        'points at the lens with one finger',
        'rests {his} hand on {his} chin, thinking',
        'shrugs with raised eyebrows',
        'leans in close as if sharing a secret',
        'slowly shakes {his} head, then nods',
        'taps the air with one hand to stress a word',
        'raises one hand like a "wait a second"',
        'leans back and crosses {his} arms for a moment',
    ];
}

/** The look used by callers that do not pass a direction: the character's starting look. @return array<string,mixed> */
function radar_direction_default(?string $slug = null): array
{
    $preset = radar_characters()[radar_character_slug($slug)];
    return [
        'outfit' => $preset['default_outfit'],
        'accessories' => $preset['default_accessories'],
        'lighting' => 'soft natural daylight',
        'angles' => ['eye-level handheld selfie angle'],
        'gestures' => ['natural hand gestures'],
        'mood' => 'casual',
        'origin' => 'padrão',
    ];
}

/**
 * Make a new look for a news item. Random every time unless a seed is given (old scripts use their id, so
 * their prompt stays the same on every read). Looks listed in $avoid are skipped when possible.
 *
 * @param array{category?:string,expression?:string,character?:string} $context
 * @param array<int,string> $avoid outfits used recently
 * @return array<string,mixed>
 */
function radar_direction_generate(array $context, int $scenes, array $avoid = [], ?int $seed = null): array
{
    $mood = radar_direction_mood((string)($context['category'] ?? ''), (string)($context['expression'] ?? ''));
    $pools = radar_direction_pools($context['character'] ?? null);
    $counter = 0;
    $rand = static function (int $max) use (&$counter, $seed): int {
        $counter++;
        return $seed === null ? random_int(0, $max - 1) : (int)(crc32($seed . ':' . $counter) % $max);
    };
    // Newest first. When the recent looks use up a whole pool, only the newest ones are avoided: the new look
    // always differs from the latest ones, and from as many older ones as the pool allows.
    $avoidFolded = array_map('mb_strtolower', array_values($avoid));
    $pick = static function (array $pool) use ($rand, $avoidFolded): string {
        for ($keep = count($avoidFolded); $keep >= 0; $keep--) {
            $skip = array_slice($avoidFolded, 0, $keep);
            $options = array_values(array_filter($pool, static fn(string $option): bool => !in_array(mb_strtolower($option), $skip, true)));
            if ($options !== []) {
                return $options[$rand(count($options))];
            }
        }
        return $pool[$rand(count($pool))];
    };
    $distinct = static function (array $pool, int $count) use ($rand): array {
        $chosen = [];
        while (count($chosen) < min($count, count($pool))) {
            $candidate = $pool[$rand(count($pool))];
            if (!in_array($candidate, $chosen, true)) {
                $chosen[] = $candidate;
            }
        }
        return $chosen;
    };
    $count = max(1, min(RADAR_SCENE_MAX_COUNT, $scenes));
    return [
        'outfit' => $pick($pools['outfit'][$mood]),
        'accessories' => $pick($pools['accessories'][$mood]),
        'lighting' => $pick($pools['lighting'][$mood]),
        'angles' => $distinct(radar_direction_angle_pool(), $count),
        'gestures' => $distinct(radar_direction_gesture_pool(), $count),
        'mood' => $mood,
        'origin' => 'sorteio',
    ];
}

/** One short line of clean text (no markup, no line breaks), or ''. */
function radar_direction_text(mixed $value, int $max = 240): string
{
    $text = is_string($value) ? strip_tags($value) : '';
    $text = preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $text) ?? '';
    $text = preg_replace('/https?:\/\/\S+/iu', '', $text) ?? '';
    return trim(mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, $max));
}

/**
 * Validate a direction coming from the AI, the panel or the database. Returns null when it has no outfit.
 *
 * @return array<string,mixed>|null
 */
function radar_direction_normalize(mixed $value, string $origin = 'ia'): ?array
{
    if (!is_array($value)) {
        return null;
    }
    $outfit = radar_direction_text($value['outfit'] ?? '');
    if ($outfit === '') {
        return null;
    }
    $list = static function (mixed $items): array {
        $clean = [];
        foreach (is_array($items) ? $items : [] as $item) {
            $text = radar_direction_text($item, 200);
            if ($text !== '') {
                $clean[] = $text;
            }
        }
        return array_slice($clean, 0, RADAR_SCENE_MAX_COUNT);
    };
    $mood = (string)($value['mood'] ?? '');
    return [
        'outfit' => $outfit,
        'accessories' => radar_direction_text($value['accessories'] ?? ''),
        'lighting' => radar_direction_text($value['lighting'] ?? ''),
        'angles' => $list($value['angles'] ?? []),
        'gestures' => $list($value['gestures'] ?? []),
        'mood' => isset(radar_direction_moods()[$mood]) ? $mood : 'casual',
        'origin' => in_array((string)($value['origin'] ?? $origin), ['ia', 'sorteio', 'manual', 'padrão'], true) ? (string)($value['origin'] ?? $origin) : $origin,
    ];
}

/** Outfits of the latest scripts, so the next look is different from them. @return array<int,string> */
function radar_direction_recent(int $limit = 8): array
{
    $outfits = [];
    foreach (radar_db_all("SELECT direction FROM scripts WHERE direction != '' ORDER BY id DESC LIMIT " . max(1, min(30, $limit))) as $row) {
        $direction = json_decode((string)$row['direction'], true);
        if (is_array($direction) && is_string($direction['outfit'] ?? null)) {
            $outfits[] = $direction['outfit'];
        }
    }
    return $outfits;
}

/** The angle, gesture and the rest of a direction for one scene (the lists cycle when there are fewer than scenes). @return array<string,string> */
function radar_direction_for_scene(array $direction, int $index): array
{
    $angles = $direction['angles'] ?? [];
    $gestures = $direction['gestures'] ?? [];
    return [
        'angle' => $angles === [] ? 'eye-level handheld selfie angle' : (string)$angles[$index % count($angles)],
        'gesture' => $gestures === [] ? 'natural hand gestures' : (string)$gestures[$index % count($gestures)],
    ];
}
