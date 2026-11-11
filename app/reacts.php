<?php
declare(strict_types=1);

/** Script generation pipeline, saved scripts ("Roteiros criados") and Studio projects. */

const RADAR_TONES = ['bem-humorado' => 'Bem-humorado', 'direto' => 'Direto', 'provocador' => 'Provocador (sem ofender)'];

/** Words → approximate seconds at a natural speaking rate (~2.5 words/s). */
function radar_speech_seconds(string $text): int
{
    $words = preg_match_all('/[\p{L}\p{N}]+/u', $text);
    return (int)round(($words === false ? 0 : $words) / 2.5);
}

function radar_script_spoken(array $script): string
{
    return trim(implode(' ', array_filter([$script['hook'] ?? '', $script['script'] ?? '', $script['commentary'] ?? '', $script['cta'] ?? ''], static fn($p): bool => trim((string)$p) !== '')));
}

/**
 * Generate and store a react package for one news item.
 *
 * @param array{scenes?:int,duration?:int,tone?:string,context?:string,expression?:string,scene?:string,layout?:string} $options
 * @return array<string,mixed> saved script (decoded)
 */
function radar_react_generate(int $newsId, array $options = []): array
{
    $news = radar_news_get($newsId);
    if ($news === null) {
        throw new InvalidArgumentException('Notícia não encontrada. Atualize a lista.');
    }
    $tone = (string)($options['tone'] ?? 'bem-humorado');
    if (isset($options['scenes'])) {
        $scenes = (int)$options['scenes'];
        $duration = $scenes * RADAR_SCENE_TARGET_SECONDS;
    } elseif (isset($options['duration'])) {
        // Older callers: a single duration between 15 and 35 s.
        $duration = (int)$options['duration'];
        $scenes = max(1, (int)ceil($duration / RADAR_SCENE_MAX_SECONDS));
        if ($duration < 15 || $duration > 35) {
            throw new InvalidArgumentException('Escolha de 1 a ' . RADAR_SCENE_MAX_COUNT . ' cenas (até ' . RADAR_SCENE_MAX_SECONDS . ' segundos cada) e um tom disponível.');
        }
    } else {
        $scenes = RADAR_SCENES_DEFAULT;
        $duration = $scenes * RADAR_SCENE_TARGET_SECONDS;
    }
    $context = trim(preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', '', strip_tags((string)($options['context'] ?? ''))) ?? '');
    if ($scenes < 1 || $scenes > RADAR_SCENE_MAX_COUNT || !isset(RADAR_TONES[$tone])) {
        throw new InvalidArgumentException('Escolha de 1 a ' . RADAR_SCENE_MAX_COUNT . ' cenas (até ' . RADAR_SCENE_MAX_SECONDS . ' segundos cada) e um tom disponível.');
    }
    if (mb_strlen($context) > 2000) {
        throw new InvalidArgumentException('O contexto deve ter até 2.000 caracteres.');
    }
    // The character that covers the category, unless one was chosen.
    $requestedCharacter = (string)($options['character'] ?? '');
    $slug = radar_character_valid($requestedCharacter) ? $requestedCharacter : radar_character_for_category((string)$news['category']);
    $config = radar_ai_config();
    $mode = 'rascunho';
    $warnings = [];
    $failure = null;
    $generated = null;
    if ($config['provider'] !== 'none' && $config['has_key']) {
        try {
            $reply = radar_ai_complete(radar_ai_system_prompt(), radar_ai_user_message($news, $duration, $tone, $context, $scenes, radar_direction_recent(), $slug), $config);
            $decoded = radar_ai_extract_json($reply);
            if ($decoded === null) {
                throw new RuntimeException('A IA não devolveu um JSON válido. Tente gerar novamente.');
            }
            $generated = radar_ai_normalize($decoded, $news, $context, $scenes);
            $mode = 'ia';
        } catch (RuntimeException $error) {
            // Fail visibly, but still hand over a safe draft so the work is not blocked.
            $failure = $error->getMessage();
        }
    }
    $generated ??= radar_ai_fallback($news, $duration, $tone, $context, $failure, $slug);
    $generated['warnings'] = array_merge($warnings, $generated['warnings']);
    $expression = isset(radar_character_expressions()[(string)($options['expression'] ?? '')]) ? (string)$options['expression'] : $generated['expression'];
    $scene = isset(radar_character_scenes()[(string)($options['scene'] ?? '')]) ? (string)$options['scene'] : $generated['scene'];
    $spoken = radar_script_spoken($generated);
    $estimated = radar_speech_seconds($spoken);
    if ($estimated > $duration + 6) {
        $generated['warnings'][] = "O texto falado dura cerca de {$estimated}s, acima dos {$duration}s pedidos. Edite para encurtar.";
    }
    $needed = count(radar_scene_split($spoken, $scenes));
    if ($needed > $scenes) {
        $generated['warnings'][] = "O texto precisa de {$needed} cenas de até " . RADAR_SCENE_MAX_SECONDS . " s (você pediu {$scenes}). Encurte o texto ou aceite mais cenas.";
    }
    $layout = radar_video_template_valid((string)($options['layout'] ?? '')) ? (string)$options['layout'] : radar_video_default_template();
    // A new look for this news: the model's, or one drawn from the pools that differs from the latest ones.
    $direction = radar_direction_normalize($generated['direction'] ?? null, 'ia')
        ?? radar_direction_generate(['category' => (string)$news['category'], 'expression' => $expression, 'character' => $slug], $scenes, radar_direction_recent());
    $prompt = radar_video_prompt(['spoken' => $spoken, 'expression' => $expression, 'scene' => $scene, 'headline' => $news['title'], 'source' => $news['source'], 'layout' => $layout, 'scenes' => $scenes, 'direction' => $direction, 'character' => $slug]
        + radar_template_texts($generated['headlines'], (string)$generated['hook'], (string)$news['title'], (string)$news['source'], $newsId));
    $now = time();
    $id = radar_db_insert(
        'INSERT INTO scripts (news_id, news_title, news_url, news_source, mode, provider, model, duration, scenes, tone, context, headlines, hook, script, commentary, cta, expression, scene, layout, direction, character_slug, video_prompt, warnings, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $newsId, $news['title'], $news['url'], $news['source'], $mode, $mode === 'ia' ? $config['provider'] : null, $mode === 'ia' ? $config['model'] : null,
            $duration, $scenes, $tone, $context, json_encode($generated['headlines'], JSON_UNESCAPED_UNICODE), $generated['hook'], $generated['script'],
            $generated['commentary'], $generated['cta'], $expression, $scene, $layout, json_encode($direction, JSON_UNESCAPED_UNICODE), $slug, $prompt,
            json_encode(['warnings' => $generated['warnings'], 'fact_check' => $generated['fact_check_notes']], JSON_UNESCAPED_UNICODE), $now, $now,
        ]
    );
    return radar_script_get($id) ?? [];
}

/**
 * The visual direction of a stored script. Scripts made before directions existed get one drawn from their id,
 * so their prompt is varied but identical on every read.
 *
 * @return array<string,mixed>
 */
function radar_script_direction(array $row): array
{
    $stored = radar_direction_normalize(json_decode((string)($row['direction'] ?? ''), true), 'manual');
    if ($stored !== null) {
        return $stored;
    }
    $category = $row['news_id'] !== null ? (string)radar_db_value('SELECT category FROM news WHERE id = ?', [(int)$row['news_id']]) : '';
    return radar_direction_generate(['category' => $category, 'expression' => (string)($row['expression'] ?? ''), 'character' => (string)($row['character_slug'] ?? '')], max(1, (int)($row['scenes'] ?? 1)), [], (int)$row['id']);
}

/**
 * Texts of the template for a script: the impact phrase (the first headline that differs from the real one,
 * else the hook) and the yellow line "SOURCE • DATE". The date appears only when the source gave one.
 *
 * @param array<int,string> $headlines
 * @return array{caption:string,source_line:string}
 */
function radar_template_texts(array $headlines, string $hook, string $title, string $source, ?int $newsId): array
{
    $plain = static fn(string $text): string => trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($text)) ?? '');
    $caption = $hook !== '' ? $hook : ($headlines[0] ?? $title);
    foreach ($headlines as $headline) {
        if ($plain($headline) !== $plain($title)) {
            $caption = $headline;
            break;
        }
    }
    $published = $newsId !== null ? radar_db_value('SELECT published_at FROM news WHERE id = ?', [$newsId]) : null;
    $line = $source . ($published !== null && (int)$published > 0 ? ' • ' . date('d/m/Y', (int)$published) : '');
    return ['caption' => $caption, 'source_line' => mb_strtoupper($line)];
}

/** @return array<string,mixed> */
function radar_script_decode(array $row): array
{
    $meta = json_decode((string)$row['warnings'], true);
    $spoken = radar_script_spoken($row);
    $direction = radar_script_direction($row);
    $slug = radar_character_slug($row['character_slug'] ?? null);
    $templateTexts = radar_template_texts(json_decode((string)$row['headlines'], true) ?: [], (string)$row['hook'], (string)$row['news_title'], (string)$row['news_source'], $row['news_id'] !== null ? (int)$row['news_id'] : null);
    $prompts = radar_video_prompts($templateTexts + [
        'character' => $slug, 'spoken' => $spoken, 'expression' => (string)$row['expression'], 'scene' => (string)$row['scene'], 'direction' => $direction,
        'headline' => (string)$row['news_title'], 'source' => (string)$row['news_source'], 'scenes' => max(1, (int)($row['scenes'] ?? 1)),
        'layout' => radar_video_template_valid((string)$row['layout']) ? (string)$row['layout'] : 'split',
    ]);
    return [
        'id' => (int)$row['id'],
        'news_id' => $row['news_id'] !== null ? (int)$row['news_id'] : null,
        'news_title' => (string)$row['news_title'],
        'news_url' => (string)$row['news_url'],
        'news_source' => (string)$row['news_source'],
        'mode' => (string)$row['mode'],
        'mode_label' => $row['mode'] === 'ia' ? 'Gerado por IA (' . $row['provider'] . ' · ' . $row['model'] . ')' : 'Rascunho conservador (sem IA)',
        'duration' => (int)$row['duration'],
        'scenes' => max(1, (int)($row['scenes'] ?? 1)),
        'tone' => (string)$row['tone'],
        'context' => (string)$row['context'],
        'headlines' => json_decode((string)$row['headlines'], true) ?: [],
        'hook' => (string)$row['hook'],
        'script' => (string)$row['script'],
        'commentary' => (string)$row['commentary'],
        'cta' => (string)$row['cta'],
        'spoken' => $spoken,
        'estimated_seconds' => radar_speech_seconds($spoken),
        'expression' => (string)$row['expression'],
        'scene' => (string)$row['scene'],
        'layout' => radar_video_template_valid((string)$row['layout']) ? (string)$row['layout'] : 'split',
        // Rebuilt on every read from the stored fields, so scripts created before a prompt standard
        // existed (e.g. "no smile after speech") get it too. The stored column is legacy.
        'direction' => $direction,
        'character' => $slug,
        'character_name' => radar_character_get($slug)['name'],
        'video_prompts' => $prompts,
        'video_prompt' => implode("\n\n" . str_repeat('-', 40) . "\n\n", $prompts),
        'warnings' => is_array($meta) ? ($meta['warnings'] ?? []) : [],
        'fact_check' => is_array($meta) ? ($meta['fact_check'] ?? []) : [],
        'created_at' => gmdate(DATE_ATOM, (int)$row['created_at']),
        'updated_at' => gmdate(DATE_ATOM, (int)$row['updated_at']),
    ];
}

/** @return array<string,mixed>|null */
function radar_script_get(int $id): ?array
{
    $row = radar_db_one('SELECT * FROM scripts WHERE id = ?', [$id]);
    return $row === null ? null : radar_script_decode($row);
}

/** @return array<int,array<string,mixed>> */
function radar_scripts_list(int $limit = 50, ?int $newsId = null): array
{
    $limit = max(1, min(200, $limit));
    $rows = $newsId === null
        ? radar_db_all('SELECT * FROM scripts ORDER BY created_at DESC, id DESC LIMIT ' . $limit)
        : radar_db_all('SELECT * FROM scripts WHERE news_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit, [$newsId]);
    return array_map('radar_script_decode', $rows);
}

/** User edits of generated text; the video prompt is rebuilt so it always matches. */
function radar_script_update(int $id, array $input): array
{
    $current = radar_script_get($id);
    if ($current === null) {
        throw new InvalidArgumentException('Roteiro não encontrado.');
    }
    $text = static fn(string $key, int $max): string => mb_substr(trim(strip_tags((string)($input[$key] ?? $current[$key]))), 0, $max);
    $headlines = $current['headlines'];
    if (isset($input['headline']) && is_string($input['headline']) && trim($input['headline']) !== '') {
        $headlines = array_slice(array_values(array_unique(array_merge([mb_substr(trim(strip_tags($input['headline'])), 0, 160)], $headlines))), 0, 6);
    }
    $expression = isset(radar_character_expressions()[(string)($input['expression'] ?? '')]) ? (string)$input['expression'] : $current['expression'];
    $scene = isset(radar_character_scenes()[(string)($input['scene'] ?? '')]) ? (string)$input['scene'] : $current['scene'];
    $layout = radar_video_template_valid(radar_str($input['layout'] ?? '')) ? radar_str($input['layout']) : $current['layout'];
    $slug = radar_character_valid(radar_str($input['character'] ?? '')) ? radar_str($input['character']) : $current['character'];
    $scenes = isset($input['scenes']) && (int)$input['scenes'] >= 1 && (int)$input['scenes'] <= RADAR_SCENE_MAX_COUNT ? (int)$input['scenes'] : $current['scenes'];
    $row = ['hook' => $text('hook', 200), 'script' => $text('script', 2600), 'commentary' => $text('commentary', 600), 'cta' => $text('cta', 250)];
    $direction = $current['direction'];
    if (!empty($input['reroll_direction']) || $slug !== $current['character']) {
        // A new character gets a new look: the clothes of one are not the wardrobe of the other.
        $category = $current['news_id'] !== null ? (string)radar_db_value('SELECT category FROM news WHERE id = ?', [(int)$current['news_id']]) : '';
        $direction = radar_direction_generate(['category' => $category, 'expression' => $expression, 'character' => $slug], $scenes, array_merge([$direction['outfit']], radar_direction_recent()));
    } else {
        foreach (['outfit', 'accessories', 'lighting'] as $key) {
            if (isset($input[$key]) && is_string($input[$key])) {
                $edited = radar_direction_text($input[$key]);
                if (($key !== 'outfit' || $edited !== '') && $edited !== $direction[$key]) {
                    $direction[$key] = $edited;
                    $direction['origin'] = 'manual';
                }
            }
        }
    }
    $prompt = radar_video_prompt(radar_template_texts($headlines, $row['hook'], $current['news_title'], $current['news_source'], $current['news_id']) + [
        'character' => $slug, 'spoken' => radar_script_spoken($row), 'expression' => $expression, 'scene' => $scene, 'direction' => $direction,
        'headline' => $current['news_title'], 'source' => $current['news_source'], 'layout' => $layout, 'scenes' => $scenes,
    ]);
    radar_db_exec(
        'UPDATE scripts SET headlines = ?, hook = ?, script = ?, commentary = ?, cta = ?, expression = ?, scene = ?, layout = ?, scenes = ?, direction = ?, character_slug = ?, video_prompt = ?, updated_at = ? WHERE id = ?',
        [json_encode(array_values($headlines), JSON_UNESCAPED_UNICODE), $row['hook'], $row['script'], $row['commentary'], $row['cta'], $expression, $scene, $layout, $scenes, json_encode($direction, JSON_UNESCAPED_UNICODE), $slug, $prompt, time(), $id]
    );
    return radar_script_get($id) ?? [];
}

function radar_script_delete(int $id): bool
{
    return radar_db_exec('DELETE FROM scripts WHERE id = ?', [$id]) > 0;
}

/** Plain-text export of a script, ready to paste elsewhere. */
function radar_script_export_text(array $script): string
{
    $lines = [
        'RADAR VIRAL BR · ROTEIRO DE REACT',
        str_repeat('=', 40),
        'Notícia: ' . $script['news_title'],
        'Fonte: ' . $script['news_source'] . ' — ' . $script['news_url'],
        'Gerado: ' . $script['mode_label'] . ' · ' . $script['duration'] . 's · tom ' . $script['tone'],
        '',
        'HEADLINES',
    ];
    foreach ($script['headlines'] as $index => $headline) {
        $lines[] = ($index + 1) . '. ' . $headline;
    }
    array_push($lines, '', 'GANCHO', $script['hook'], '', 'ROTEIRO', $script['script'], '', 'COMENTÁRIO DO PERSONAGEM (opinião)', $script['commentary'], '', 'CTA', $script['cta'], '', 'TEXTO FALADO COMPLETO (~' . $script['estimated_seconds'] . 's)', $script['spoken'], '', 'PROMPT DE VÍDEO', $script['video_prompt']);
    if ($script['warnings'] !== []) {
        array_push($lines, '', 'ALERTAS', ...array_map(static fn(string $w): string => '- ' . $w, $script['warnings']));
    }
    if ($script['fact_check'] !== []) {
        array_push($lines, '', 'CONFERIR ANTES DE GRAVAR', ...array_map(static fn(string $w): string => '- ' . $w, $script['fact_check']));
    }
    return implode("\n", $lines) . "\n";
}

/* ---------- Studio projects ---------- */

/** @return array<string,mixed> */
function radar_project_decode(array $row): array
{
    return [
        'id' => (int)$row['id'], 'script_id' => $row['script_id'] !== null ? (int)$row['script_id'] : null,
        'news_id' => $row['news_id'] !== null ? (int)$row['news_id'] : null, 'title' => (string)$row['title'],
        'headline' => (string)$row['headline'], 'script' => (string)$row['script'], 'video_prompt' => (string)$row['video_prompt'],
        'cover_media_id' => $row['cover_media_id'], 'character_media_id' => $row['character_media_id'],
        'cover_x' => (float)$row['cover_x'], 'cover_y' => (float)$row['cover_y'], 'cover_zoom' => (float)$row['cover_zoom'],
        'expression' => (string)$row['expression'],
        'template' => radar_video_template_valid((string)$row['template']) ? (string)$row['template'] : 'split',
        'caption' => (string)$row['caption'],
        'direction' => radar_direction_normalize(json_decode((string)($row['direction'] ?? ''), true), 'manual'),
        'character' => radar_character_slug($row['character_slug'] ?? null),
        'created_at' => gmdate(DATE_ATOM, (int)$row['created_at']), 'updated_at' => gmdate(DATE_ATOM, (int)$row['updated_at']),
    ];
}

/** @return array<string,mixed>|null */
function radar_project_get(int $id): ?array
{
    $row = radar_db_one('SELECT * FROM projects WHERE id = ?', [$id]);
    return $row === null ? null : radar_project_decode($row);
}

/** @return array<int,array<string,mixed>> */
function radar_projects_list(int $limit = 100): array
{
    return array_map('radar_project_decode', radar_db_all('SELECT * FROM projects ORDER BY updated_at DESC, id DESC LIMIT ' . max(1, min(200, $limit))));
}

/** Create or update a Studio project. @return array<string,mixed> */
function radar_project_save(?int $id, array $input): array
{
    $title = mb_substr(trim(strip_tags((string)($input['title'] ?? ''))), 0, 160);
    $headline = mb_substr(trim(strip_tags((string)($input['headline'] ?? ''))), 0, 200);
    if ($title === '' && $headline === '') {
        throw new InvalidArgumentException('Informe um título ou uma headline para salvar o projeto.');
    }
    $title = $title !== '' ? $title : $headline;
    $coverId = (string)($input['cover_media_id'] ?? '');
    $characterId = (string)($input['character_media_id'] ?? '');
    foreach ([$coverId, $characterId] as $mediaId) {
        if ($mediaId !== '' && radar_media_get($mediaId) === null) {
            throw new InvalidArgumentException('Imagem não encontrada. Envie a imagem novamente.');
        }
    }
    $expression = isset(radar_character_expressions()[(string)($input['expression'] ?? '')]) ? (string)$input['expression'] : 'sorriso-largo';
    $values = [
        isset($input['script_id']) && (int)$input['script_id'] > 0 ? (int)$input['script_id'] : null,
        isset($input['news_id']) && (int)$input['news_id'] > 0 ? (int)$input['news_id'] : null,
        $title, $headline, mb_substr(trim(strip_tags((string)($input['script'] ?? ''))), 0, 4000),
        mb_substr(trim((string)($input['video_prompt'] ?? '')), 0, 30000), $coverId !== '' ? $coverId : null, $characterId !== '' ? $characterId : null,
        max(0.0, min(100.0, (float)($input['cover_x'] ?? 50))), max(0.0, min(100.0, (float)($input['cover_y'] ?? 50))),
        max(1.0, min(3.0, (float)($input['cover_zoom'] ?? 1))), $expression,
        radar_video_template_valid(radar_str($input['template'] ?? '')) ? radar_str($input['template']) : 'split',
        mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags(radar_str($input['caption'] ?? ''))) ?? ''), 0, 200),
        ($projectDirection = radar_direction_normalize($input['direction'] ?? null, 'manual')) === null ? '' : json_encode($projectDirection, JSON_UNESCAPED_UNICODE),
        radar_character_slug(radar_str($input['character'] ?? '')),
    ];
    $now = time();
    if ($id === null) {
        $id = radar_db_insert(
            'INSERT INTO projects (script_id, news_id, title, headline, script, video_prompt, cover_media_id, character_media_id, cover_x, cover_y, cover_zoom, expression, template, caption, direction, character_slug, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [...$values, $now, $now]
        );
    } else {
        if (radar_project_get($id) === null) {
            throw new InvalidArgumentException('Projeto não encontrado.');
        }
        radar_db_exec(
            'UPDATE projects SET script_id = ?, news_id = ?, title = ?, headline = ?, script = ?, video_prompt = ?, cover_media_id = ?, character_media_id = ?, cover_x = ?, cover_y = ?, cover_zoom = ?, expression = ?, template = ?, caption = ?, direction = ?, character_slug = ?, updated_at = ? WHERE id = ?',
            [...$values, $now, $id]
        );
    }
    return radar_project_get($id) ?? [];
}

function radar_project_delete(int $id): bool
{
    return radar_db_exec('DELETE FROM projects WHERE id = ?', [$id]) > 0;
}
