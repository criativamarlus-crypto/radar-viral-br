<?php
declare(strict_types=1);

/**
 * TV characters ("tv3": Dona Maricota do Babado). The pieces that do not talk to the database or the AI:
 * the tone of the news, the fixed look in the official studio, the plan of the three scenes, the facts of the
 * news, the prompt of each scene for the video tool (Muse) and the subtitles file.
 *
 * The prompts are built from code, like the other characters', so the character block, the references, the
 * studio and the fixed standards stay identical in every video.
 */

const RADAR_TV_SCENES = 3;

/** True when the text is about death, tragedy, violence, serious illness or victims: no jokes, no humor catchphrases. */
function radar_tv_sensitive(string $text): bool
{
    return preg_match('/\b(morre|morreu|morrem|morrer|morte|mortes|morto|mortos|morta|mortas|luto|velorio|falec\w*|tragedia|acidente|assassin\w*|homicidio|feminicidio|estupr\w*|abuso|suicid\w*|vitima|vitimas|naufrag\w*|desabamento|incendio|chacina|massacre|atentado|sequestr\w*|desaparecid\w*|internad[oa]s?|uti|cancer|enterro|sepultad\w*|balead[oa]s?|esfaquead[oa]s?|afogad[oa]s?)\b/', radar_fold($text)) === 1;
}

/**
 * How the presenter talks about this news. Sensitive news (death, tragedy) always wins over the category.
 *
 * @param array<string,mixed> $news
 * @return array{key:string,label:string,group:string,guide:string,humor:bool,catchphrases:bool,expressions:array<int,string>,emotions:array<int,string>,calm:bool}
 */
function radar_tv_tone(array $news, string $context = ''): array
{
    $text = (string)($news['title'] ?? '') . ' ' . (string)($news['summary'] ?? '') . ' ' . $context;
    $category = (string)($news['category'] ?? '');
    $key = match (true) {
        radar_tv_sensitive($text) => 'sensivel',
        $category === 'Política' || preg_match('/\b(governo|presidente|ministro|ministra|senado|senador\w*|camara|deputad\w*|stf|congresso|eleicao|eleicoes|partido|prefeit\w*|governador\w*|planalto)\b/', radar_fold($text)) === 1 => 'politica',
        in_array($category, array_merge(radar_finance_categories(), ['Economia']), true) => 'financas',
        $category === 'Notícias internacionais' => 'internacional',
        $category === 'Tecnologia' => 'tecnologia',
        in_array($category, ['Famosos', 'Entretenimento'], true) => 'entretenimento',
        default => 'geral',
    };
    $tones = [
        'entretenimento' => ['label' => 'Famosos e entretenimento', 'group' => 'leve', 'humor' => true, 'catchphrases' => true, 'calm' => false,
            'guide' => 'Curiosa, divertida, dramática e bem-humorada. Pode brincar com a situação, nunca com a pessoa. Não transforme boato em fato.',
            'expressions' => ['surpreso', 'pensativo', 'provocador'], 'emotions' => ['SURPRESA', 'EXPLICACAO', 'REACAO']],
        'politica' => ['label' => 'Política', 'group' => 'politica', 'humor' => false, 'catchphrases' => false, 'calm' => true,
            'guide' => 'Expressiva e questionadora, mas comprometida com os fatos: atribua tudo a quem disse ("segundo..."), não invente acusações, não apresente opinião como fato comprovado, não ataque pessoas, partidos ou grupos. Humor só leve e sobre a situação, nunca sobre pessoas.',
            'expressions' => ['pensativo', 'serio', 'pensativo'], 'emotions' => ['QUESTIONAMENTO', 'EXPLICACAO', 'REFLEXAO']],
        'financas' => ['label' => 'Finanças e economia', 'group' => 'financas', 'humor' => true, 'catchphrases' => true, 'calm' => false,
            'guide' => 'Linguagem popular, com um exemplo do dia a dia, e reação à notícia. Use só os números da matéria. NUNCA recomende comprar, vender, investir ou contratar nada e diga, em uma frase, que não é recomendação financeira.',
            'expressions' => ['surpreso', 'apontando', 'pensativo'], 'emotions' => ['ATENCAO', 'EXPLICACAO', 'REACAO']],
        'internacional' => ['label' => 'Internacional', 'group' => 'internacional', 'humor' => false, 'catchphrases' => true, 'calm' => false,
            'guide' => 'Apresente o acontecimento de forma acessível para o brasileiro: diga quem é quem e onde fica, em uma frase, quando for preciso. Sem piada com tragédia ou conflito.',
            'expressions' => ['surpreso', 'pensativo', 'pensativo'], 'emotions' => ['CURIOSIDADE', 'EXPLICACAO', 'REFLEXAO']],
        'tecnologia' => ['label' => 'Tecnologia', 'group' => 'leve', 'humor' => true, 'catchphrases' => true, 'calm' => false,
            'guide' => 'Curiosa e surpresa com a novidade; explique de forma simples, sem jargão.',
            'expressions' => ['surpreso', 'pensativo', 'rindo'], 'emotions' => ['CURIOSIDADE', 'EXPLICACAO', 'ENTUSIASMO']],
        'sensivel' => ['label' => 'Notícia grave (tom respeitoso)', 'group' => 'sensivel', 'humor' => false, 'catchphrases' => false, 'calm' => true,
            'guide' => 'TOM RESPEITOSO E EMPÁTICO. Sem piada, sem deboche, sem exclamações festivas e SEM bordões de humor. Informe com cuidado, sem detalhes sensacionalistas e sem especular causas ou culpados. Expressão séria.',
            'expressions' => ['serio', 'serio', 'serio'], 'emotions' => ['GRAVIDADE', 'INFORMACAO', 'EMPATIA']],
        'geral' => ['label' => 'Notícia geral', 'group' => 'leve', 'humor' => true, 'catchphrases' => true, 'calm' => false,
            'guide' => 'Curiosa e expressiva, com humor leve quando o assunto permitir.',
            'expressions' => ['surpreso', 'pensativo', 'provocador'], 'emotions' => ['SURPRESA', 'EXPLICACAO', 'REACAO']],
    ];
    return ['key' => $key] + $tones[$key];
}

/** @return array<string,string> camera key => what it is, in English for the prompt */
function radar_tv_cameras(): array
{
    return ['CLOSE' => 'close-up (head and shoulders)', 'PLANO_MEDIO' => 'medium shot (waist up, the desk and the studio behind her)'];
}

/** @return array<string,string> */
function radar_tv_camera_labels(): array
{
    return ['CLOSE' => 'Plano próximo', 'PLANO_MEDIO' => 'Plano médio'];
}

/**
 * Pools for the fixed look: same studio, same outfit; the light, the camera and the gestures change.
 *
 * @return array{lighting:array<string,array<int,string>>,close:array<int,string>,medium:array<int,string>,gestures:array<int,string>,calm:array<int,string>}
 */
function radar_tv_pools(): array
{
    return [
        'lighting' => [
            'serious' => ['softened studio lighting: a warm gold key light on her face, a deep muted purple background and the marquee bulbs dimmed', 'calm studio lighting with soft gold light on her face and the pink and purple panels turned down low'],
            'business' => ['balanced pink, purple and gold studio lighting with clean white highlights on her face', 'gold key light with soft pink and purple panels behind her, clean and professional'],
            'fun' => ['vivid magenta, purple and gold studio lighting with sparkling marquee bulbs and glowing panels', 'rich pink and gold stage lighting with lens glints on the marquee bulbs and the jewelry'],
            'sport' => ['bright pink, purple and gold studio lighting with glowing light panels behind her', 'energetic pink and gold studio lighting with crisp highlights on her hair'],
            'tech' => ['bright pink, purple and gold studio lighting with a cooler blue glow on the big screen', 'pink and gold studio lighting with a soft blue glow from the big screen'],
            'casual' => ['bright pink, purple and gold studio lighting with glowing light panels behind her', 'warm golden studio lighting with pink and purple panels, cheerful and polished'],
        ],
        'close' => [
            'tight close-up on her face and shoulders, slow push-in toward the lens',
            'close-up with a slight three-quarter turn toward the lens, gentle drift to the right',
            'close-up from a slightly low angle that gives her presence, very slow dolly-in',
            'close-up, she leans toward the camera as if sharing a secret, very slow zoom-in',
        ],
        'medium' => [
            'medium shot at the presenter desk, slow slide to the right revealing the big screen behind her',
            'medium shot with a gentle dolly-in, the lit sign visible behind her',
            'medium-wide shot of the desk and the studio lights, slow pan following her gestures',
            'steady medium shot from a slightly high angle with a soft slide to the left',
        ],
        'gestures' => [
            'counts the facts on her fingers, her rings and bangles catching the light',
            'opens both palms toward the camera',
            'points at the lens with one finger and a raised eyebrow',
            'covers her mouth with one hand in a dramatic gasp, then lowers it',
            'rests her chin on her hand, thinking',
            'taps the desk with one red-nailed finger to stress a word',
            'leans back and fans herself with a sheet of paper',
            'adjusts her glasses with two fingers',
            'takes a theatrical pause holding the mug',
        ],
        'calm' => [
            'rests both hands folded on the desk, serious and attentive',
            'places one hand over her chest for a moment',
            'slowly shakes her head, then looks back at the lens',
            'lowers her eyes briefly, then returns to the lens',
            'keeps her hands still on the desk and speaks slowly',
        ],
    ];
}

/**
 * The look of a TV character: the outfit and the jewelry never change, the studio light, the camera and the gestures
 * do. Same shape as a direction made for the other characters, so the Studio's tools keep working.
 *
 * @param array{category?:string,expression?:string,character?:string} $context
 * @param array<int,string> $avoid kept for the same signature (the outfit never changes)
 * @return array<string,mixed>
 */
function radar_tv_direction(array $context, int $scenes, array $avoid = [], ?int $seed = null): array
{
    $slug = radar_character_slug($context['character'] ?? null);
    $preset = radar_characters()[$slug];
    $mood = radar_direction_mood((string)($context['category'] ?? ''), (string)($context['expression'] ?? ''));
    $pools = radar_tv_pools();
    $counter = 0;
    $rand = static function (int $max) use (&$counter, $seed): int {
        $counter++;
        return $seed === null ? random_int(0, $max - 1) : (int)(crc32($seed . ':' . $counter) % $max);
    };
    $calm = $mood === 'serious';
    $count = max(1, min(RADAR_SCENE_MAX_COUNT, $scenes));
    $close = $pools['close'];
    $medium = $pools['medium'];
    $angles = [];
    for ($i = 0; $i < $count; $i++) {
        // Close-up opens the video, medium shot explains, and the reaction goes back to a close-up (a medium one when serious).
        $pool = $i % 2 === 1 || ($calm && $i === $count - 1 && $count > 1) ? $medium : $close;
        $pool = array_values(array_filter($pool, static fn(string $angle): bool => !in_array($angle, $angles, true)));
        $angles[] = $pool[$rand(count($pool))];
    }
    $gesturePool = $calm ? $pools['calm'] : $pools['gestures'];
    $gestures = [];
    while (count($gestures) < min($count, count($gesturePool))) {
        $candidate = $gesturePool[$rand(count($gesturePool))];
        if (!in_array($candidate, $gestures, true)) {
            $gestures[] = $candidate;
        }
    }
    $lighting = $pools['lighting'][$mood] ?? $pools['lighting']['casual'];
    return radar_direction_resolve([
        'outfit' => (string)$preset['default_outfit'],
        'accessories' => (string)$preset['default_accessories'],
        'lighting' => $lighting[$rand(count($lighting))],
        'angles' => $angles,
        'gestures' => $gestures,
        'mood' => $mood,
        'origin' => 'sorteio',
    ], $slug);
}

/**
 * The plan of the three scenes: what each one is for, how long, the emotion, the expression, the gestures and the camera.
 * The text of each scene (narration, caption, title) comes from the writer and is joined in later.
 *
 * @param array<string,mixed> $tone from radar_tv_tone()
 * @param array<string,mixed> $direction
 * @return array<int,array<string,mixed>> three entries
 */
function radar_tv_plan(array $tone, array $direction, int $seconds): array
{
    $roles = [
        ['role' => 'gancho', 'label' => 'Gancho', 'objective' => 'Prender a atenção na hora com uma chamada original e específica, sem revelar mais do que a matéria sustenta, e deixar a próxima cena no ar.'],
        ['role' => 'desenvolvimento', 'label' => 'Desenvolvimento', 'objective' => 'Contar o principal fato confirmado, com os detalhes relevantes e quem está envolvido, em linguagem popular, continuando o assunto da cena 1.'],
        ['role' => 'reacao', 'label' => 'Reação e fechamento', 'objective' => 'Comentar a notícia no estilo da apresentadora (opinião dela, sinalizada como opinião), fechar de um jeito específico e convidar o público a comentar com uma pergunta sobre a notícia.'],
    ];
    $continuity = [
        'Opening shot of the video: she is already seated at the presenter desk in the official studio and starts talking right away. End the clip on a short natural pause, facing the lens, ready for the next scene.',
        'The same take continued from scene 1: identical outfit, hair, glasses, jewelry, desk, mug, microphone, lighting and lit sign. She does NOT greet again and does not introduce herself again: she picks up the energy where scene 1 ended.',
        'The closing scene of the same video: the same studio, outfit, jewelry and lighting as scenes 1 and 2, no new introduction. She finishes the thought, asks the question to the audience and, after the last word, holds a neutral look at the lens for about one second.',
    ];
    $angles = (array)($direction['angles'] ?? []);
    $gestures = (array)($direction['gestures'] ?? []);
    $plan = [];
    foreach ($roles as $index => $role) {
        $movement = $angles === [] ? 'slow gentle push-in' : (string)$angles[$index % count($angles)];
        $plan[] = [
            'number' => $index + 1,
            'role' => $role['role'], 'role_label' => $role['label'], 'objective' => $role['objective'],
            'duration_seconds' => $seconds,
            'emotion' => (string)$tone['emotions'][$index], 'expression' => (string)$tone['expressions'][$index],
            'gestures' => $gestures === [] ? 'natural hand gestures' : (string)$gestures[$index % count($gestures)],
            'camera' => $index === 1 || ($tone['calm'] && $index === 2) ? 'PLANO_MEDIO' : 'CLOSE',
            'camera_movement' => $movement,
            'continuity' => $continuity[$index],
        ];
    }
    return $plan;
}

/** Plan for a text that has no stored scenes (the Studio before saving): the roles by position. @return array<int,array<string,mixed>> */
function radar_tv_default_plan(int $total, array $direction, int $seconds, string $category = ''): array
{
    $tone = radar_tv_tone(['category' => $category, 'title' => '', 'summary' => '']);
    $plan = radar_tv_plan($tone, $direction, $seconds);
    $out = [];
    for ($i = 0; $i < max(1, $total); $i++) {
        $base = $plan[min($i, 2)];
        if ($total === 1) {
            $base['role_label'] = 'Cena única';
        } elseif ($i > 2) {
            $base['role_label'] = 'Continuação';
            $base['role'] = 'desenvolvimento';
        }
        $base['number'] = $i + 1;
        $out[] = $base;
    }
    return $out;
}

/** Seconds a scene needs: the chosen duration, longer only when the text does not fit (a phrase is never cut and the voice never sped up). */
function radar_tv_scene_seconds(string $narration, int $configured): int
{
    $estimated = (int)ceil(radar_scene_words($narration) / 2.5);
    return min(RADAR_SCENE_MAX_SECONDS, max($configured, $estimated));
}

/** Words that fit in a scene of this duration. */
function radar_tv_word_budget(int $seconds): int
{
    return (int)floor($seconds * 2.5);
}

/* ---------- facts ---------- */

/** The paragraphs of an article page as plain text (what the publisher put in the page), at most ~4000 characters. */
function radar_tv_article_text(string $html): string
{
    $html = preg_replace('~<(script|style|noscript|nav|footer|header|aside|form)\b.*?</\1>~is', ' ', $html) ?? $html;
    $paragraphs = [];
    if (preg_match_all('~<p\b[^>]*>(.*?)</p>~is', $html, $matches)) {
        foreach ($matches[1] as $raw) {
            $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
            if (mb_strlen($text) >= 60 && !preg_match('/(leia tamb|veja tamb|assine|siga o|clique aqui|todos os direitos|cookies)/iu', $text)) {
                $paragraphs[] = $text;
            }
        }
    }
    $text = implode(' ', array_slice($paragraphs, 0, 12));
    return mb_substr($text, 0, 4000);
}

/** Download the article page of the publisher and return its text, or '' when it is not reachable or is the Google News redirect. */
function radar_tv_fetch_article(string $url): string
{
    if (!radar_og_fetchable($url)) {
        return '';
    }
    $response = radar_net_get($url, ['headers' => ['Accept: text/html,application/xhtml+xml'], 'max_bytes' => 1_500_000, 'timeout' => 15]);
    if ($response['error'] !== null || $response['status'] !== 200 || $response['body'] === '') {
        return '';
    }
    return radar_tv_article_text($response['body']);
}

/**
 * Separate what the source reports from what it only attributes to someone, what is uncertain and what is
 * the presenter's opinion (that one is written later, by her). Nothing here is invented: every item is a sentence
 * of the headline, the summary, the article text or the context given by the user.
 *
 * @param array<string,mixed> $news
 * @return array{headline:string,source:string,date:?string,verified:array<int,string>,attributed:array<int,string>,uncertain:array<int,string>,opinions:array<int,string>,incomplete:bool,article_chars:int}
 */
function radar_tv_facts(array $news, string $context = '', string $article = ''): array
{
    $verified = [];
    $attributed = [];
    $uncertain = [];
    $seen = [];
    $title = trim((string)($news['title'] ?? ''));
    foreach ([[(string)($news['summary'] ?? ''), false], [$article, false], [$context, true]] as [$text, $fromUser]) {
        foreach (radar_editorial_sentences(trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '')) as $sentence) {
            $key = radar_fold($sentence);
            if (mb_strlen($sentence) < 20 || isset($seen[$key]) || $key === radar_fold($title)) {
                continue;
            }
            $seen[$key] = true;
            $folded = radar_fold($sentence);
            if (preg_match('/\b(pode|podem|poderia|poderiam|deve|devem|talvez|possivel|possivelmente|rumor\w*|especul\w+|supostamente|teria|teriam|seria|seriam)\b/', $folded) === 1) {
                $uncertain[] = $sentence;
            } elseif (preg_match('/\b(segundo|de acordo com|afirm\w+|diss\w+|declar\w+|alega\w*|acus\w+|denunci\w+|investig\w+|suspeit\w+|negou|nega|informou|relatou|apontou)\b/', $folded) === 1) {
                $attributed[] = $sentence;
            } else {
                $verified[] = ($fromUser ? 'Informado por você: ' : '') . $sentence;
            }
        }
    }
    $published = ($news['published_at'] ?? null) !== null ? strtotime((string)$news['published_at']) : false;
    $incomplete = trim((string)($news['summary'] ?? '')) === '' && $article === '' && $context === '';
    return [
        'headline' => $title, 'source' => (string)($news['source'] ?? ''), 'date' => $published !== false ? date('d/m/Y', $published) : null,
        'verified' => array_slice($verified, 0, 8), 'attributed' => array_slice($attributed, 0, 6), 'uncertain' => array_slice($uncertain, 0, 6), 'opinions' => [],
        'incomplete' => $incomplete, 'article_chars' => mb_strlen($article),
    ];
}

/** Everything the narration may rely on, as one text (numbers and quotes are checked against it). */
function radar_tv_material(array $news, string $context, string $article): string
{
    return implode(' ', [(string)($news['title'] ?? ''), (string)($news['summary'] ?? ''), (string)($news['source'] ?? ''), (string)($news['published_at'] ?? ''), $context, $article]);
}

/* ---------- prompts ---------- */

/** @return array<string,mixed> the character's options for the TV prompt (profile merged) */
function radar_tv_character(?string $slug): array
{
    return radar_character_get($slug);
}

/**
 * One prompt per scene for the video tool (Muse). It declares that she is the registered Dona Maricota, lists the
 * reference images, the physical description, the official studio, the language, the voice, the exact narration,
 * the expression, the lip-sync and body motion, the duration, the format and what must not happen.
 *
 * @param array<string,mixed> $parts spoken (paragraphs = scenes), character, direction, layout, headline, source, caption, source_line, plan (optional), scenes (ignored)
 * @return array<int,string>
 */
function radar_tv_prompts(array $parts): array
{
    $slug = radar_character_slug($parts['character'] ?? null);
    $character = radar_tv_character($slug);
    $direction = radar_direction_normalize($parts['direction'] ?? null, 'manual') ?? radar_tv_direction(['character' => $slug], RADAR_TV_SCENES, [], 1);
    $chunks = radar_scene_split((string)$parts['spoken'], RADAR_TV_SCENES, true);
    $total = count($chunks);
    $seconds = (int)$character['narration']['scene_seconds'];
    $plan = array_values((array)($parts['plan'] ?? []));
    if (count($plan) < $total) {
        $plan = radar_tv_default_plan($total, $direction, $seconds);
    }
    $standards = radar_video_standards($slug);
    $expressions = radar_character_expressions();
    $references = radar_character_reference_roles($slug);
    $referenceText = radar_reference_paragraph($references);
    $voice = radar_character_voice($slug);
    $cameras = radar_tv_cameras();
    $templates = radar_video_templates();
    $layout = isset($templates[(string)($parts['layout'] ?? '')]) ? (string)$parts['layout'] : 'tv_studio';
    $brief = radar_template_brief($layout, ['caption' => $parts['caption'] ?? '', 'headline' => $parts['headline'] ?? '', 'source' => $parts['source'] ?? '', 'source_line' => $parts['source_line'] ?? '']);
    $negative = implode(', ', array_map(static fn(mixed $rule): string => radar_direction_text($rule, 200), (array)$character['negative_rules']));
    $moods = radar_direction_moods();
    $prompts = [];
    foreach ($chunks as $index => $chunk) {
        $scene = $plan[$index];
        $expression = $expressions[(string)($scene['expression'] ?? '')] ?? $expressions['surpreso'];
        $duration = radar_tv_scene_seconds($chunk, (int)($scene['duration_seconds'] ?? $seconds));
        $camera = $cameras[(string)($scene['camera'] ?? 'CLOSE')] ?? $cameras['CLOSE'];
        $title = radar_direction_text($scene['screen_title'] ?? '', 120);
        $caption = radar_direction_text($scene['caption'] ?? $chunk, 200);
        $prompts[] = radar_character_words(implode("\n", [
            'SCENE ' . ($index + 1) . ' OF ' . $total . ' (' . mb_strtoupper((string)($scene['role_label'] ?? 'SCENE')) . '): one separate clip of the same video; the clips are edited together afterwards.',
            'THIS IS THE SAME OFFICIAL CHARACTER REGISTERED IN THE PANEL: ' . $character['name'] . ' (configuration version ' . (int)$character['version'] . '). Do not create a new character and do not invent a new face for this clip.',
            '',
            $referenceText,
            '',
            'CHARACTER (use the attached reference images, do not change identity):',
            radar_character_master_prompt('en', $slug),
            '',
            $standards['same_character_every_scene']['rule'],
            '',
            'WARDROBE (fixed, identical in every scene and in every video): ' . $direction['outfit'] . '. Accessories: ' . $direction['accessories'] . '.',
            '',
            'FORMAT: vertical 9:16, about ' . $duration . ' seconds' . ($duration > $seconds ? ' (the narration needs more than the planned ' . $seconds . ' seconds: never speed up the voice and never cut the sentence)' : ', matching the narration without speeding up the voice') . ', professional TV-studio camera work, smooth and steady, no cinematic filters.',
            'CAMERA FOR THIS SCENE: ' . $camera . '; ' . ($scene['camera_movement'] ?? 'slow gentle push-in') . '. Keep her face away from the very top and bottom edges of the frame.',
            'SETTING: ' . $character['scenery_en'] . ' The only sign allowed on screen is the studio sign with her name.',
            'LIGHTING AND MOOD: ' . ($direction['lighting'] !== '' ? $direction['lighting'] : 'pink, purple and gold studio lighting') . ' (' . ($moods[$direction['mood']] ?? $moods['casual']) . ' mood).',
            '',
            'OBJECTIVE OF THIS SCENE: ' . radar_direction_text($scene['objective_en'] ?? radar_tv_objective_en((string)($scene['role'] ?? 'desenvolvimento')), 300),
            'EMOTIONAL TONE: ' . radar_tv_emotion_en((string)($scene['emotion'] ?? '')) . '. FACIAL EXPRESSION: ' . $expression['visual'] . '. Natural, expressive and theatrical, never frightening.',
            'BODY LANGUAGE: she ' . ($scene['gestures'] ?? 'uses natural hand gestures') . '. Smooth body motion; her hands never cover her face (except the gesture above, briefly).',
            '',
            'VOICE (the same voice in every scene and every video): ' . $voice['en'],
            'LANGUAGE: Brazilian Portuguese, natural and colloquial.',
            'DIALOGUE (spoken in natural, colloquial Brazilian Portuguese, with lip-sync, exactly as written):',
            '"' . $chunk . '"',
            '',
            'SPEECH RULES: she says exactly these words with natural lip-sync; do not add, remove, change or invent any word, and add no other speech.',
            $layout === 'tv_studio'
                ? 'SCREEN TEXT: none inside the video. Titles and subtitles are added later in editing' . ($title !== '' ? ' (title for this scene: "' . $title . '"; subtitle: "' . $caption . '")' : '') . '. If a news image is attached to this prompt, show it unchanged on the big studio screen behind her, with no text on it; otherwise the screen shows soft pink and purple light. Never invent headlines or words on the screen.'
                : $brief,
            '',
            'CONTINUITY: ' . radar_direction_text($scene['continuity'] ?? '', 500),
            '',
            $standards['no_smile_after_speech']['rule'],
            '',
            'CONTEXT: reacting to the real headline "' . trim((string)($parts['headline'] ?? '')) . '" reported by ' . trim((string)($parts['source'] ?? '')) . '. She only says what is in DIALOGUE.',
            'AVOID: ' . $negative . ', ' . $standards['same_character_every_scene']['avoid'] . ', logos or readable text on clothes, a different studio.',
        ]), $slug);
    }
    return $prompts;
}

/** @return array{en:string,pt:string} the voice profile for prompts and for the panel */
function radar_character_voice(?string $slug = null): array
{
    $character = radar_character_get($slug);
    return ['en' => (string)($character['voice_en'] ?? $character['voice']), 'pt' => (string)$character['voice']];
}

function radar_tv_objective_en(string $role): string
{
    return match ($role) {
        'gancho' => 'Hook: grab the attention immediately with an original, specific opening and prepare the next scene.',
        'reacao' => 'Reaction and closing: comment on the news in her own style (clearly her opinion), close in a way that is specific to this news and invite the audience to comment with a question about it.',
        default => 'Development: tell the main confirmed fact with the relevant details and who is involved, in popular language, continuing the subject of the previous scene.',
    };
}

function radar_tv_emotion_en(string $emotion): string
{
    return match ($emotion) {
        'SURPRESA' => 'surprise and curiosity',
        'EXPLICACAO' => 'clear, engaged explanation',
        'REACAO' => 'a theatrical but good-humored reaction',
        'QUESTIONAMENTO' => 'a questioning, attentive and fair attitude',
        'REFLEXAO' => 'reflection and calm interest',
        'ATENCAO' => 'attention: this one concerns everybody\'s pocket',
        'CURIOSIDADE' => 'curiosity',
        'ENTUSIASMO' => 'surprised enthusiasm',
        'GRAVIDADE' => 'gravity and respect',
        'INFORMACAO' => 'calm, careful information',
        'EMPATIA' => 'empathy and respect',
        default => 'expressive and warm',
    };
}

/* ---------- subtitles ---------- */

/** One or two balanced lines of at most ~32 characters each (the break goes at the word closest to the middle). */
function radar_srt_lines(string $cue): string
{
    if (mb_strlen($cue) <= 32) {
        return $cue;
    }
    $words = explode(' ', $cue);
    $best = 1;
    $bestGap = PHP_INT_MAX;
    for ($i = 1; $i < count($words); $i++) {
        $gap = abs(mb_strlen(implode(' ', array_slice($words, 0, $i))) - mb_strlen(implode(' ', array_slice($words, $i))));
        if ($gap < $bestGap) {
            [$best, $bestGap] = [$i, $gap];
        }
    }
    return implode(' ', array_slice($words, 0, $best)) . "\n" . implode(' ', array_slice($words, $best));
}

function radar_srt_time(float $seconds): string
{
    $milliseconds = (int)round($seconds * 1000);
    return sprintf('%02d:%02d:%02d,%03d', intdiv($milliseconds, 3_600_000), intdiv($milliseconds % 3_600_000, 60_000), intdiv($milliseconds % 60_000, 1000), $milliseconds % 1000);
}

/**
 * Subtitles (SRT) from the scenes. Without generated audio the times are estimated from the words (about 2.5 per second)
 * inside each scene's duration; with real audio, replace the times by the real ones in editing.
 *
 * @param array<int,array<string,mixed>> $scenes narration and duration_seconds of each scene
 */
function radar_tv_srt(array $scenes): string
{
    $clock = 0.0;
    $number = 1;
    $out = '';
    foreach ($scenes as $scene) {
        $duration = (float)($scene['duration_seconds'] ?? 8);
        $words = preg_split('/\s+/u', trim((string)($scene['narration'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // A cue holds up to two lines of about 32 characters: it ends at the end of a sentence or when the next word would not fit.
        $cues = [];
        $current = [];
        foreach ($words as $word) {
            $candidate = implode(' ', array_merge($current, [$word]));
            if ($current !== [] && (count($current) >= 11 || mb_strlen($candidate) > 62)) {
                $cues[] = implode(' ', $current);
                $current = [];
            }
            $current[] = $word;
            if (preg_match('/[.!?…]["”)]?$/u', $word) === 1 && count($current) >= 3) {
                $cues[] = implode(' ', $current);
                $current = [];
            }
        }
        if ($current !== []) {
            $cues[] = implode(' ', $current);
        }
        $spoken = min($duration, max(1.0, count($words) / 2.5));
        $letters = max(1, array_sum(array_map('mb_strlen', $cues)));
        $cursor = $clock + 0.1;
        foreach ($cues as $cue) {
            $length = max(0.8, $spoken * mb_strlen($cue) / $letters);
            $end = min($clock + $duration, $cursor + $length);
            $out .= $number++ . "\n" . radar_srt_time($cursor) . ' --> ' . radar_srt_time($end) . "\n" . radar_srt_lines($cue) . "\n\n";
            $cursor = $end;
        }
        $clock += $duration;
    }
    return $out;
}
