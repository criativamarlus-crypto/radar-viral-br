<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

/**
 * JSON API.
 *  GET  ?action=…  read access: logged-in session OR Authorization: Bearer <token>
 *  POST {action:…}  logged-in session + CSRF header (never reachable with a bearer token)
 * See docs/API.md.
 */

function api_limit(string $key, int $max, int $seconds): void
{
    if (!radar_rate_limit(radar_client_key($key), $max, $seconds)) {
        header('Retry-After: ' . $seconds);
        radar_json(['error' => 'Limite de requisições atingido. Aguarde um pouco e tente novamente.'], 429);
    }
}

function api_int(mixed $value): int
{
    return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int)$value : 0);
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if ($method === 'GET') {
        radar_require_read_access();
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        api_limit('api-read', 120, 60);
        $action = $_GET['action'] ?? 'news';
        switch ($action) {
            case 'news':
                $result = radar_news_query(radar_news_filters($_GET));
                radar_json([
                    'items' => $result['items'], 'total' => $result['total'], 'page' => $result['filters']['page'], 'limit' => $result['filters']['limit'],
                    'last_collect_at' => ($t = radar_setting('last_collect_at')) !== null ? gmdate(DATE_ATOM, (int)$t) : null,
                    'ranking' => 'Pontuação editorial estimada pelo Radar Viral BR (0 a 100). Não é audiência medida nem ranking oficial do Google Trends.',
                ]);
            // no break: radar_json exits
            case 'news_item':
                $news = radar_news_get(api_int($_GET['id'] ?? 0));
                $news === null ? radar_json(['error' => 'Notícia não encontrada.'], 404) : radar_json(['item' => $news]);
            case 'trends':
                $trends = radar_trends_latest();
                radar_json(['items' => $trends, 'source' => 'Google Trends Brasil (RSS público)', 'note' => 'Buscas em alta, não verificação de fatos.', 'collected_at' => $trends[0]['collected_at'] ?? null]);
            case 'scripts':
                radar_json(['items' => radar_scripts_list(100, isset($_GET['news_id']) ? api_int($_GET['news_id']) : null)]);
            case 'script':
                $script = radar_script_get(api_int($_GET['id'] ?? 0));
                if ($script === null) {
                    radar_json(['error' => 'Roteiro não encontrado.'], 404);
                }
                if (($_GET['format'] ?? '') === 'txt') {
                    header('Content-Type: text/plain; charset=utf-8');
                    header('Content-Disposition: attachment; filename="roteiro-' . $script['id'] . '.txt"');
                    echo radar_script_export_text($script);
                    exit;
                }
                radar_json(['item' => $script]);
            case 'projects':
                radar_json(['items' => radar_projects_list()]);
            case 'stats':
                radar_json(radar_dashboard_stats());
            default:
                radar_json(['error' => 'Ação não encontrada.'], 404);
        }
    }
    if ($method !== 'POST') {
        header('Allow: GET, POST');
        radar_json(['error' => 'Método não permitido.'], 405);
    }

    // ---- writes: session + CSRF only ----
    if (isset($_SERVER['HTTP_AUTHORIZATION']) && $_SERVER['HTTP_AUTHORIZATION'] !== '') {
        radar_json(['error' => 'Tokens de API são somente leitura. Use o painel para alterar dados.'], 403);
    }
    radar_require_login(true);
    radar_require_csrf();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    api_limit('api-write', 240, 60);
    $contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    $input = [];
    if ($contentType === 'application/json') {
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if (!is_string($raw) || strlen($raw) > 65536) {
            radar_json(['error' => 'Requisição muito grande.'], 413);
        }
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            radar_json(['error' => 'JSON inválido.'], 400);
        }
        $action = (string)($input['action'] ?? '');
    } elseif (str_starts_with($contentType, 'multipart/form-data')) {
        $input = $_POST;
        $action = (string)($_POST['action'] ?? '');
    } else {
        radar_json(['error' => 'Envie JSON (application/json) ou multipart/form-data.'], 415);
    }

    switch ($action) {
        case 'collect':
            $auto = ($input['trigger'] ?? '') === 'auto';
            if ($auto && !radar_collect_is_stale()) {
                radar_json(['status' => 'fresh', 'message' => 'Os dados já estão atualizados.']);
            }
            api_limit($auto ? 'collect-auto' : 'collect-manual', $auto ? 6 : 12, 600);
            $summary = radar_collect($auto ? 'auto' : 'manual');
            radar_json($summary, $summary['status'] === 'busy' ? 409 : 200);

        case 'save_news':
            $id = api_int($input['id'] ?? 0);
            if (!radar_news_set_saved($id, ($input['saved'] ?? true) === true, isset($input['note']) ? (string)$input['note'] : null)) {
                radar_json(['error' => 'Notícia não encontrada.'], 404);
            }
            radar_json(['ok' => true, 'saved' => ($input['saved'] ?? true) === true]);

        case 'delete_news':
            radar_news_delete(api_int($input['id'] ?? 0)) ? radar_json(['ok' => true]) : radar_json(['error' => 'Notícia não encontrada.'], 404);

        case 'generate':
            api_limit('generate', 20, 600);
            @set_time_limit(120);
            $script = radar_react_generate(api_int($input['news_id'] ?? 0), [
                'scenes' => isset($input['scenes']) ? api_int($input['scenes']) : null, 'duration' => isset($input['duration']) && !isset($input['scenes']) ? api_int($input['duration']) : null,
                'tone' => (string)($input['tone'] ?? 'bem-humorado'), 'context' => (string)($input['context'] ?? ''),
                'expression' => (string)($input['expression'] ?? ''), 'scene' => (string)($input['scene'] ?? ''), 'layout' => radar_str($input['layout'] ?? ''),
            ]);
            radar_json(['item' => $script], 201);

        case 'script_update':
            radar_json(['item' => radar_script_update(api_int($input['id'] ?? 0), $input)]);
        case 'script_delete':
            radar_script_delete(api_int($input['id'] ?? 0)) ? radar_json(['ok' => true]) : radar_json(['error' => 'Roteiro não encontrado.'], 404);

        case 'project_save':
            $projectId = api_int($input['id'] ?? 0);
            radar_json(['item' => radar_project_save($projectId > 0 ? $projectId : null, $input)], $projectId > 0 ? 200 : 201);
        case 'project_delete':
            radar_project_delete(api_int($input['id'] ?? 0)) ? radar_json(['ok' => true]) : radar_json(['error' => 'Projeto não encontrado.'], 404);

        case 'import_cover':
            api_limit('media-fetch', 30, 600);
            radar_json(['media' => radar_media_import_news_cover(api_int($input['news_id'] ?? 0))], 201);

        case 'fetch_og':
            api_limit('media-fetch', 30, 600);
            $newsId = api_int($input['news_id'] ?? 0);
            $row = radar_db_one('SELECT id, url, image_url, image_note, summary, source_name FROM news WHERE id = ?', [$newsId]);
            if ($row === null) {
                radar_json(['error' => 'Notícia não encontrada.'], 404);
            }
            if (!radar_og_fetchable((string)$row['url'])) {
                radar_json(['error' => 'O link é do Google Notícias e não revela a página original; não há como ler a imagem Open Graph da matéria.'], 422);
            }
            $found = radar_og_enrich([$row]);
            radar_json(['ok' => true, 'images' => $found['images'], 'news' => radar_news_get($newsId)]);

        case 'screenshot':
            api_limit('screenshot', 10, 600);
            @set_time_limit(90);
            radar_json(['media' => radar_screenshot_capture(api_int($input['news_id'] ?? 0))], 201);

        case 'upload':
            api_limit('upload', 30, 3600);
            $kind = (string)($input['kind'] ?? '');
            $file = $_FILES['file'] ?? null;
            if (!in_array($kind, ['cover', 'character', 'upload', 'reference'], true) || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
                radar_json(['error' => 'Selecione uma imagem JPEG, PNG ou WebP de até 5 MB.'], 400);
            }
            $bytes = (string)file_get_contents($file['tmp_name'], false, null, 0, RADAR_MEDIA_MAX_BYTES + 1);
            $newsId = api_int($input['news_id'] ?? 0);
            $media = radar_media_save_bytes($bytes, $kind, [
                'news_id' => $newsId > 0 && radar_db_value('SELECT 1 FROM news WHERE id = ?', [$newsId]) !== null ? $newsId : null,
                'label' => (string)($input['label'] ?? ($kind === 'character' ? 'Referência do personagem' : ($kind === 'reference' ? 'Folha de referência do personagem' : 'Imagem enviada'))),
                'credit' => (string)($input['credit'] ?? ''),
                'note' => in_array($kind, ['character', 'reference'], true) ? 'Imagem de referência enviada pelo administrador.' : 'Imagem enviada manualmente pelo administrador; o direito de uso é de sua responsabilidade.',
            ]);
            radar_json(['media' => $media], 201);

        case 'media_delete':
            radar_media_delete((string)($input['id'] ?? '')) ? radar_json(['ok' => true]) : radar_json(['error' => 'Imagem não encontrada.'], 404);
        case 'character_primary':
            radar_character_set_primary((string)($input['id'] ?? '')) ? radar_json(['ok' => true]) : radar_json(['error' => 'Imagem não encontrada.'], 404);

        case 'video_prompt':
            $expressionKey = (string)($input['expression'] ?? '');
            $sceneKey = (string)($input['scene'] ?? '');
            $promptParts = [
                'spoken' => mb_substr((string)($input['spoken'] ?? ''), 0, 4000),
                'expression' => isset(radar_character_expressions()[$expressionKey]) ? $expressionKey : 'sorriso-largo',
                'scene' => isset(radar_character_scenes()[$sceneKey]) ? $sceneKey : 'carro',
                'scenes' => max(1, min(RADAR_SCENE_MAX_COUNT, api_int($input['scenes'] ?? 1) ?: 1)),
                'headline' => mb_substr((string)($input['headline'] ?? ''), 0, 200),
                'source' => mb_substr((string)($input['source'] ?? ''), 0, 100),
                'layout' => radar_video_template_valid(radar_str($input['layout'] ?? '')) ? radar_str($input['layout']) : radar_video_default_template(),
            ];
            $scenePrompts = radar_video_prompts($promptParts);
            radar_json(['prompt' => radar_video_prompt($promptParts), 'prompts' => $scenePrompts, 'scenes' => count($scenePrompts)]);

        case 'source_test':
            api_limit('source-test', 30, 600);
            $source = radar_source_get(api_int($input['id'] ?? 0));
            if ($source === null) {
                radar_json(['error' => 'Fonte não encontrada.'], 404);
            }
            $response = radar_net_get((string)$source['url'], ['headers' => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*;q=0.5'], 'max_bytes' => 3_000_000, 'timeout' => 20]);
            if ($response['error'] !== null || $response['status'] !== 200) {
                radar_json(['ok' => false, 'message' => $response['error'] ?? 'A fonte respondeu HTTP ' . $response['status'] . '.', 'http' => $response['status'], 'ms' => $response['ms']]);
            }
            try {
                $items = $source['kind'] === 'trends' ? radar_parse_trends($response['body']) : radar_parse_feed($response['body'], (string)$source['name']);
            } catch (RuntimeException $error) {
                radar_json(['ok' => false, 'message' => $error->getMessage(), 'http' => $response['status'], 'ms' => $response['ms']]);
            }
            $first = $items[0] ?? null;
            radar_json(['ok' => $items !== [], 'message' => $items === [] ? 'O feed não trouxe nenhuma matéria válida.' : count($items) . ' itens lidos com sucesso.', 'count' => count($items), 'example' => is_array($first) ? ($first['title'] ?? $first['query'] ?? null) : null, 'http' => $response['status'], 'ms' => $response['ms']]);

        case 'ai_test':
            api_limit('ai-test', 6, 600);
            $config = radar_ai_config();
            if ($config['provider'] === 'none' || !$config['has_key']) {
                radar_json(['ok' => false, 'message' => 'Selecione um provedor e salve uma chave de API primeiro.']);
            }
            $config['max_tokens'] = 60;
            try {
                $reply = radar_ai_complete('Responda somente com o JSON {"ok":true}.', 'Teste de conexão.', $config);
                radar_json(['ok' => true, 'message' => 'Conexão com a IA funcionando (' . $config['model'] . ').', 'sample' => mb_substr(trim($reply), 0, 80)]);
            } catch (RuntimeException $error) {
                radar_json(['ok' => false, 'message' => $error->getMessage()]);
            }

        default:
            radar_json(['error' => 'Ação desconhecida.'], 400);
    }
} catch (InvalidArgumentException $error) {
    radar_json(['error' => $error->getMessage()], 400);
} catch (RuntimeException $error) {
    // User-facing, already-sanitised messages from the domain layer (network, AI, media).
    radar_json(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log('Radar API failure: ' . get_class($error) . ' ' . $error->getMessage());
    radar_json(['error' => 'Erro interno. Verifique os logs do servidor.'], 500);
}
