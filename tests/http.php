<?php
declare(strict_types=1);

/** Real HTTP checks against a disposable local installation (PHP built-in server). */
$httpRoot = $testDirectory . '/http';
mkdir($httpRoot, 0700, true);
function copyTestTree(string $source, string $destination): void
{
    mkdir($destination, 0700, true);
    foreach (new FilesystemIterator($source) as $entry) {
        if ($entry->isLink()) {
            throw new RuntimeException('Test source may not contain symbolic links');
        }
        if ($entry->isDir()) {
            copyTestTree($entry->getPathname(), $destination . '/' . $entry->getFilename());
        } else {
            copy($entry->getPathname(), $destination . '/' . $entry->getFilename());
        }
    }
}
copyTestTree(dirname(__DIR__) . '/app', $httpRoot . '/app');
copyTestTree(dirname(__DIR__) . '/public', $httpRoot . '/public');
$listener = stream_socket_server('tcp://127.0.0.1:0', $socketError, $socketMessage);
if (!is_resource($listener)) {
    throw new RuntimeException('Cannot allocate local HTTP test port');
}
$address = stream_socket_get_name($listener, false);
fclose($listener);
$ini = php_ini_loaded_file();
$command = [PHP_BINARY];
if (is_string($ini)) {
    array_push($command, '-c', $ini);
}
array_push($command, '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=7M', '-S', $address, '-t', $httpRoot . '/public');
$null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$server = proc_open($command, [0 => ['file', $null, 'r'], 1 => ['file', $httpRoot . '/server.log', 'a'], 2 => ['file', $httpRoot . '/server.log', 'a']], $pipes, $httpRoot);
if (!is_resource($server)) {
    throw new RuntimeException('Could not start disposable PHP HTTP server');
}
register_shutdown_function(static function () use ($server): void {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
});
$ready = false;
for ($attempt = 0; $attempt < 100; $attempt++) {
    $probe = @stream_socket_client('tcp://' . $address, $probeError, $probeMessage, 0.05);
    if (is_resource($probe)) {
        fclose($probe);
        $ready = true;
        break;
    }
    usleep(20_000);
}
if (!$ready) {
    throw new RuntimeException('Disposable PHP HTTP server did not start');
}
$httpCookie = '';
function localRequest(string $path, string $method = 'GET', string $body = '', array $headers = [], bool $useCookie = true): array
{
    global $address, $httpCookie;
    if ($useCookie && $httpCookie !== '') {
        $headers[] = 'Cookie: ' . $httpCookie;
    }
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20]]);
    $response = @file_get_contents('http://' . $address . '/' . ltrim($path, '/'), false, $context);
    $responseHeaders = $http_response_header ?? [];
    $status = 0;
    $parsedHeaders = [];
    foreach ($responseHeaders as $line) {
        if (preg_match('/^HTTP\/\S+ (\d{3})/', $line, $match)) {
            $status = (int)$match[1];
        } elseif (str_contains($line, ':')) {
            [$key, $value] = explode(':', $line, 2);
            $key = strtolower(trim($key));
            $parsedHeaders[$key] = trim($value);
            if ($useCookie && $key === 'set-cookie') {
                $httpCookie = explode(';', trim($value))[0];
            }
        }
    }
    return ['status' => $status, 'headers' => $parsedHeaders, 'body' => is_string($response) ? $response : ''];
}
function formBody(array $fields): string
{
    return http_build_query($fields);
}
const FORM = ['Content-Type: application/x-www-form-urlencoded'];
try {
    $notConfigured = localRequest('login.php');
    check('unconfigured installation fails closed with HTTP 503', static fn(): bool => $notConfigured['status'] === 503);
    $notReady = localRequest('health.php');
    check('unconfigured public health returns 503 without private paths', static fn(): bool => $notReady['status'] === 503 && json_decode($notReady['body'], true) === ['status' => 'unavailable'] && !str_contains($notReady['body'], $httpRoot));

    $password = bin2hex(random_bytes(20));
    $legacyToken = bin2hex(random_bytes(24));
    $httpConfig = [
        'admin_user' => 'test-editor',
        'admin_password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'api_token_hash' => hash('sha256', $legacyToken),
        'storage_path' => $httpRoot . '/storage',
        'secure_cookies' => false,
        'site_url' => 'http://' . $address,
    ];
    $writeHttpConfig = static function () use (&$httpConfig, $httpRoot): void {
        file_put_contents($httpRoot . '/config.php', '<?php return ' . var_export($httpConfig, true) . ';');
        chmod($httpRoot . '/config.php', 0600);
    };
    $writeHttpConfig();

    // Seed the disposable installation: one source, two stories, two tokens.
    $testStorage = $httpRoot . '/storage';
    radar_setting_set('sources_seeded_version', '1');
    $seedSourceId = radar_source_create(['name' => 'Fonte HTTP', 'url' => 'https://http-fixture.example.com/feed', 'category' => 'Futebol']);
    $seedItems = radar_parse_feed(rssFeed(
        rssItem('Gol decisivo muda o rumo do campeonato nesta rodada', 'https://http-fixture.example.com/gol', time() - 300, '<media:thumbnail url="https://img.example.com/g.jpg"/>')
        . rssItem('Governo anuncia pacote de medidas econômicas para o ano', 'https://http-fixture.example.com/pacote', time() - 4000),
        'xmlns:media="http://search.yahoo.com/mrss/"'
    ), 'Fonte HTTP');
    radar_store_items($seedItems, radar_source_get($seedSourceId), time());
    radar_rank_recompute();
    radar_setting_set('last_collect_at', (string)time());
    $firstNewsId = (int)radar_db_value("SELECT id FROM news WHERE url = 'https://http-fixture.example.com/gol'");
    $readToken = radar_token_create('http-read', 'read')['token'];
    $reactToken = radar_token_create('http-react', 'react')['token'];

    if (PHP_OS_FAMILY !== 'Windows') {
        $healthy = localRequest('health.php');
        check('public health reports coarse readiness without configuration details', static fn(): bool => $healthy['status'] === 200 && json_decode($healthy['body'], true) === ['status' => 'ok']);
    }
    $healthWrite = localRequest('health.php', 'POST');
    check('health endpoint accepts only read methods', static fn(): bool => $healthWrite['status'] === 405);

    /* ---- anonymous access ---- */
    $anonymous = localRequest('api.php');
    check('private news API rejects anonymous access', static fn(): bool => $anonymous['status'] === 401);
    $pages = ['', 'noticias.php', 'noticia.php?id=' . $firstNewsId, 'fontes.php', 'salvas.php', 'estudio.php', 'personagem.php', 'roteiros.php', 'config-ia.php', 'integracoes.php', 'coletas.php'];
    check('every admin page redirects anonymous visitors to the login', static function () use ($pages): bool {
        foreach ($pages as $page) {
            $response = localRequest($page, 'GET', '', [], false);
            if ($response['status'] !== 302 || ($response['headers']['location'] ?? '') !== '/login.php') {
                echo "  $page -> {$response['status']}\n";
                return false;
            }
        }
        return true;
    });
    $login = localRequest('login.php');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $loginMatch);
    $loginCsrf = $loginMatch[1] ?? '';
    $preLoginCookie = $httpCookie;
    check('login response sets HttpOnly SameSite session cookies', static fn(): bool => $login['status'] === 200 && $loginCsrf !== '' && str_contains(strtolower($login['headers']['set-cookie'] ?? $anonymous['headers']['set-cookie'] ?? ''), 'httponly') && str_contains(strtolower($login['headers']['set-cookie'] ?? $anonymous['headers']['set-cookie'] ?? ''), 'samesite=strict'));
    check('responses enforce a strict CSP without inline scripts or styles', static fn(): bool => str_contains($login['headers']['content-security-policy'] ?? '', "script-src 'self'") && str_contains($login['headers']['content-security-policy'] ?? '', "style-src 'self'") && !str_contains($login['headers']['content-security-policy'] ?? '', 'unsafe-inline') && ($login['headers']['x-content-type-options'] ?? '') === 'nosniff' && ($login['headers']['x-frame-options'] ?? '') === 'DENY');
    $missingCsrf = localRequest('login.php', 'POST', formBody(['username' => 'test-editor', 'password' => $password]), FORM);
    check('login requires a valid anti-CSRF token', static fn(): bool => $missingCsrf['status'] === 403);
    $wrongPassword = localRequest('login.php', 'POST', formBody(['csrf' => $loginCsrf, 'username' => 'test-editor', 'password' => 'wrong-password']), FORM);
    check('incorrect password does not authenticate', static fn(): bool => $wrongPassword['status'] === 401);
    $authenticated = localRequest('login.php', 'POST', formBody(['csrf' => $loginCsrf, 'username' => 'test-editor', 'password' => $password]), FORM);
    check('successful login rotates the session identifier', static fn(): bool => $authenticated['status'] === 303 && $preLoginCookie !== $httpCookie);

    /* ---- logged-in pages ---- */
    $dashboard = localRequest('');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $dashboard['body'], $csrfMatch);
    $writeCsrf = $csrfMatch[1] ?? '';
    check('authenticated dashboard exposes a new write CSRF token and real counters', static fn(): bool => $dashboard['status'] === 200 && $writeCsrf !== '' && $writeCsrf !== $loginCsrf && str_contains($dashboard['body'], 'Notícias coletadas hoje') && str_contains($dashboard['body'], 'Reacts preparados'));
    check('every admin page renders for the logged-in admin without PHP errors', static function () use ($pages): bool {
        foreach ($pages as $page) {
            $response = localRequest($page);
            if ($response['status'] !== 200 || preg_match('/Fatal error|Warning:|Notice:|Deprecated:|Stack trace/', $response['body']) || !str_contains($response['body'], '<title>')) {
                echo "  $page -> {$response['status']}\n";
                return false;
            }
        }
        return true;
    });
    $newsPage = localRequest('noticias.php?janela=24');
    check('news page lists the stored stories with the viral estimate and the generate button', static fn(): bool => str_contains($newsPage['body'], 'Gol decisivo muda o rumo') && str_contains($newsPage['body'], 'Gerar react com IA') && str_contains($newsPage['body'], 'Estimativa própria') || str_contains($newsPage['body'], 'estimativa própria'));
    $filtered = localRequest('noticias.php?janela=24&categoria=' . rawurlencode('Futebol'));
    check('news page category filter shows only that category', static fn(): bool => str_contains($filtered['body'], 'Gol decisivo') && !str_contains($filtered['body'], 'Governo anuncia pacote'));
    $badFilter = localRequest('noticias.php?categoria%5B%5D=x&ordem=%27%3B%20DROP%20TABLE%20news');
    check('hostile filter values do not break or leak the page', static fn(): bool => $badFilter['status'] === 200 && !str_contains($badFilter['body'], 'DROP TABLE') && !str_contains($badFilter['body'], 'Fatal'));
    $missingNews = localRequest('noticia.php?id=999999');
    check('unknown news ids return 404', static fn(): bool => $missingNews['status'] === 404);

    /* ---- read API (session and tokens) ---- */
    $api = localRequest('api.php?action=news&janela=24');
    check('authenticated session can read the news API with the own-estimate disclaimer', static fn(): bool => $api['status'] === 200 && count(json_decode($api['body'], true)['items'] ?? []) === 2 && str_contains(json_decode($api['body'], true)['ranking'], 'Não é audiência'));
    $item = localRequest('api.php?action=news_item&id=' . $firstNewsId);
    check('news item API returns reasons for the score and the original link', static fn(): bool => $item['status'] === 200 && count(json_decode($item['body'], true)['item']['score_reasons'] ?? []) === 8 && json_decode($item['body'], true)['item']['url'] === 'https://http-fixture.example.com/gol');
    $bearer = localRequest('api.php?action=news&janela=24', 'GET', '', ['Authorization: Bearer ' . $readToken], false);
    check('a panel-created read token can read without session cookies', static fn(): bool => $bearer['status'] === 200);
    $legacy = localRequest('api.php?action=trends', 'GET', '', ['Authorization: Bearer ' . $legacyToken], false);
    check('the legacy setup token from config.php still works for reading', static fn(): bool => $legacy['status'] === 200);
    $invalidBearer = localRequest('api.php', 'GET', '', ['Authorization: Bearer ' . str_repeat('z', 48)], false);
    check('invalid bearer token is rejected', static fn(): bool => $invalidBearer['status'] === 401 && isset($invalidBearer['headers']['www-authenticate']));
    $badAction = localRequest('api.php?action=drop');
    check('unknown read actions return 404', static fn(): bool => $badAction['status'] === 404);
    $arrayInjection = localRequest('api.php?action[]=news');
    check('array parameter injection cannot reach an action', static fn(): bool => in_array($arrayInjection['status'], [200, 404, 400], true) && !str_contains($arrayInjection['body'], 'Fatal'));

    /* ---- MCP ---- */
    $rpc = static fn(int $id, string $method, array $params = []): string => json_encode(['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => (object)$params], JSON_THROW_ON_ERROR);
    $mcpHeaders = static fn(string $token): array => ['Content-Type: application/json', 'Authorization: Bearer ' . $token];
    $mcpNoToken = localRequest('mcp.php', 'POST', $rpc(1, 'initialize'), ['Content-Type: application/json']);
    check('MCP requires bearer credentials even with an authenticated cookie', static fn(): bool => $mcpNoToken['status'] === 401);
    $mcp = localRequest('mcp.php', 'POST', $rpc(1, 'initialize', ['protocolVersion' => '2025-06-18']), $mcpHeaders($readToken), false);
    check('authenticated MCP negotiates a supported protocol version and warns about unverified facts', static fn(): bool => $mcp['status'] === 200 && (json_decode($mcp['body'], true)['result']['protocolVersion'] ?? '') === '2025-06-18' && str_contains($mcp['body'], 'não verifica fatos'));
    check('MCP rejects GET, wrong content types, bad JSON and bad shapes', static function () use ($readToken, $mcpHeaders): bool {
        $get = localRequest('mcp.php', 'GET', '', ['Authorization: Bearer ' . $readToken], false);
        $html = localRequest('mcp.php', 'POST', '<html>', ['Content-Type: text/html', 'Authorization: Bearer ' . $readToken], false);
        $bad = localRequest('mcp.php', 'POST', '{invalid-json', $mcpHeaders($readToken), false);
        $shape = localRequest('mcp.php', 'POST', '[]', $mcpHeaders($readToken), false);
        return $get['status'] === 405 && $html['status'] === 415 && $bad['status'] === 400 && (json_decode($bad['body'], true)['error']['code'] ?? 0) === -32700 && $shape['status'] === 400 && (json_decode($shape['body'], true)['error']['code'] ?? 0) === -32600;
    });
    $ping = localRequest('mcp.php', 'POST', $rpc(4, 'ping'), $mcpHeaders($readToken), false);
    $pingValue = json_decode($ping['body']);
    check('MCP ping returns an empty result object rather than an array', static fn(): bool => $ping['status'] === 200 && ($pingValue->result ?? null) instanceof stdClass && get_object_vars($pingValue->result) === []);
    $list = localRequest('mcp.php', 'POST', $rpc(2, 'tools/list'), $mcpHeaders($readToken), false);
    check('MCP exposes exactly the requested Portuguese tools', static fn(): bool => array_column(json_decode($list['body'], true)['result']['tools'] ?? [], 'name') === ['listar_noticias', 'buscar_noticias', 'consultar_tendencias', 'obter_noticia', 'obter_imagens', 'listar_reacts', 'preparar_react']);
    $call = static fn(string $token, string $tool, array $args): array => localRequest('mcp.php', 'POST', $rpc(9, 'tools/call', ['name' => $tool, 'arguments' => (object)$args]), $mcpHeaders($token), false);
    $listed = $call($readToken, 'listar_noticias', ['periodo_horas' => 24, 'limite' => 5]);
    $listedData = json_decode(json_decode($listed['body'], true)['result']['content'][0]['text'] ?? '{}', true);
    check('listar_noticias returns attributed stories with an estimated (not official) score', static fn(): bool => count($listedData['noticias'] ?? []) === 2 && isset($listedData['noticias'][0]['link_original'], $listedData['noticias'][0]['pontuacao_viral_estimada']) && str_contains($listedData['aviso'], 'não audiência'));
    $searched = json_decode(json_decode($call($readToken, 'buscar_noticias', ['consulta' => 'pacote'])['body'], true)['result']['content'][0]['text'] ?? '{}', true);
    check('buscar_noticias finds by headline text', static fn(): bool => count($searched['noticias'] ?? []) === 1 && str_contains($searched['noticias'][0]['titulo'], 'pacote'));
    $one = json_decode(json_decode($call($readToken, 'obter_noticia', ['id' => $firstNewsId])['body'], true)['result']['content'][0]['text'] ?? '{}', true);
    check('obter_noticia returns the original link and every reason for the score', static fn(): bool => $one['link_original'] === 'https://http-fixture.example.com/gol' && count($one['motivos_da_pontuacao'] ?? []) === 8);
    $images = json_decode(json_decode($call($readToken, 'obter_imagens', ['id' => $firstNewsId])['body'], true)['result']['content'][0]['text'] ?? '{}', true);
    check('obter_imagens returns the source cover with credit and a rights warning', static fn(): bool => ($images['capa_da_fonte']['url'] ?? '') === 'https://img.example.com/g.jpg' && str_contains($images['aviso'] ?? '', 'direitos'));
    $trendsCall = $call($readToken, 'consultar_tendencias', []);
    check('consultar_tendencias answers even without a snapshot', static fn(): bool => $trendsCall['status'] === 200 && isset(json_decode($trendsCall['body'], true)['result']));
    $notFound = $call($readToken, 'obter_noticia', ['id' => 99999999]);
    check('unknown story is a tool error, not a crash', static fn(): bool => (json_decode($notFound['body'], true)['result']['isError'] ?? false) === true);
    $extraArgs = $call($readToken, 'obter_noticia', ['id' => 1, 'extra' => 'x']);
    check('unexpected tool arguments are refused', static fn(): bool => (json_decode($extraArgs['body'], true)['error']['code'] ?? 0) === -32602);
    $unknownTool = $call($readToken, 'apagar_tudo', []);
    check('unknown tools are refused', static fn(): bool => (json_decode($unknownTool['body'], true)['error']['code'] ?? 0) === -32602);
    $readOnlyReact = $call($readToken, 'preparar_react', ['noticia_id' => $firstNewsId]);
    check('a read-only token cannot prepare a react', static fn(): bool => (json_decode($readOnlyReact['body'], true)['result']['isError'] ?? false) === true && str_contains($readOnlyReact['body'], 'preparar react'));
    $react = $call($reactToken, 'preparar_react', ['noticia_id' => $firstNewsId, 'duracao_segundos' => 20]);
    $reactData = json_decode(json_decode($react['body'], true)['result']['content'][0]['text'] ?? '{}', true);
    check('a react-scoped token prepares a react with fixed-character prompt, fact-check notes and source', static fn(): bool => ($reactData['origem'] ?? '') !== '' && str_contains($reactData['prompt_de_video'] ?? '', 'recurring fictional Brazilian character') && ($reactData['link'] ?? '') === 'https://http-fixture.example.com/gol' && isset($reactData['conferir_antes_de_gravar']));
    $listedReacts = json_decode(json_decode($call($readToken, 'listar_reacts', [])['body'], true)['result']['content'][0]['text'] ?? '{}', true);
    check('listar_reacts returns what was prepared', static fn(): bool => count($listedReacts['reacts'] ?? []) === 1);
    $foreignOrigin = localRequest('mcp.php', 'POST', $rpc(1, 'initialize'), array_merge($mcpHeaders($readToken), ['Origin: https://evil.example.com']), false);
    check('MCP rejects cross-origin browser requests', static fn(): bool => $foreignOrigin['status'] === 403);

    /* ---- writes: session + CSRF only ---- */
    $bearerWrite = localRequest('api.php', 'POST', json_encode(['action' => 'delete_news', 'id' => $firstNewsId]), ['Authorization: Bearer ' . $reactToken, 'Content-Type: application/json'], false);
    check('bearer tokens can never write through the REST API', static fn(): bool => $bearerWrite['status'] === 403);
    $noCsrf = localRequest('api.php', 'POST', json_encode(['action' => 'save_news', 'id' => $firstNewsId, 'saved' => true]), ['Content-Type: application/json']);
    check('authenticated write endpoints still require CSRF', static fn(): bool => $noCsrf['status'] === 403);
    $wrongType = localRequest('api.php', 'POST', 'action=save_news', array_merge(FORM, ['X-CSRF-Token: ' . $writeCsrf]));
    check('writes require JSON or multipart bodies', static fn(): bool => $wrongType['status'] === 415);
    $post = static fn(array $payload): array => localRequest('api.php', 'POST', json_encode($payload, JSON_THROW_ON_ERROR), ['Content-Type: application/json', 'X-CSRF-Token: ' . $writeCsrf]);
    $saved = $post(['action' => 'save_news', 'id' => $firstNewsId, 'saved' => true]);
    check('saving a story via the API works and shows up in the saved page', static fn(): bool => $saved['status'] === 200 && str_contains(localRequest('salvas.php')['body'], 'Gol decisivo'));
    $generated = $post(['action' => 'generate', 'news_id' => $firstNewsId, 'duration' => 25, 'tone' => 'bem-humorado', 'context' => '']);
    $generatedData = json_decode($generated['body'], true);
    check('GERAR REACT returns headlines, hook, script, commentary, CTA and the video prompt', static fn(): bool => $generated['status'] === 201 && count($generatedData['item']['headlines']) === 3 && $generatedData['item']['hook'] !== '' && $generatedData['item']['script'] !== '' && $generatedData['item']['commentary'] !== '' && $generatedData['item']['cta'] !== '' && str_contains($generatedData['item']['video_prompt'], '9:16'));
    $scriptId = $generatedData['item']['id'] ?? 0;
    check('invalid generation requests are rejected with readable errors', static fn(): bool => $post(['action' => 'generate', 'news_id' => $firstNewsId, 'duration' => 99])['status'] === 400 && $post(['action' => 'generate', 'news_id' => 0, 'duration' => 25])['status'] === 400);
    $txt = localRequest('api.php?action=script&id=' . $scriptId . '&format=txt');
    check('a script exports as a downloadable text file', static fn(): bool => $txt['status'] === 200 && str_contains($txt['headers']['content-type'] ?? '', 'text/plain') && str_contains($txt['body'], 'PROMPT DE VÍDEO'));
    $roteiroPage = localRequest('roteiro.php?id=' . $scriptId);
    check('the script page shows editable fields, copy buttons and the fixed-character prompt', static fn(): bool => $roteiroPage['status'] === 200 && str_contains($roteiroPage['body'], 'data-copy="video-prompt"') && str_contains($roteiroPage['body'], 'recurring fictional Brazilian character'));
    $edit = localRequest('roteiro.php', 'POST', formBody(['csrf' => $writeCsrf, 'id' => $scriptId, 'hook' => 'Gancho editado pelo usuário', 'script' => 'Texto novo', 'commentary' => 'Opinião nova', 'cta' => 'E aí?', 'expression' => 'rindo', 'scene' => 'casa']), FORM);
    check('editing a script through the form redirects back and rebuilds the prompt', static fn(): bool => $edit['status'] === 303 && str_contains(radar_script_get($scriptId)['video_prompt'], 'Gancho editado pelo usuário'));
    $editNoCsrf = localRequest('roteiro.php', 'POST', formBody(['id' => $scriptId, 'hook' => 'Invasor']), FORM);
    check('the script form without CSRF changes nothing', static fn(): bool => $editNoCsrf['status'] === 303 && !str_contains(radar_script_get($scriptId)['hook'], 'Invasor'));
    $collectFresh = $post(['action' => 'collect', 'trigger' => 'auto']);
    check('automatic collection does nothing while data is fresh (no useless requests to the sources)', static fn(): bool => $collectFresh['status'] === 200 && (json_decode($collectFresh['body'], true)['status'] ?? '') === 'fresh');
    $project = $post(['action' => 'project_save', 'title' => 'Projeto HTTP', 'headline' => 'Headline', 'script' => 'Falado', 'news_id' => $firstNewsId, 'cover_x' => 30, 'cover_zoom' => 1.5]);
    check('Studio projects save through the API and open in the Studio', static fn(): bool => $project['status'] === 201 && str_contains(localRequest('estudio.php?projeto=' . (json_decode($project['body'], true)['item']['id'] ?? 0))['body'], 'Projeto HTTP'));
    $videoPrompt = $post(['action' => 'video_prompt', 'spoken' => 'Fala de teste', 'expression' => 'surpreso', 'scene' => 'carro', 'duration' => 20, 'headline' => 'H', 'source' => 'S']);
    check('the Studio can rebuild the video prompt on demand', static fn(): bool => $videoPrompt['status'] === 200 && str_contains(json_decode($videoPrompt['body'], true)['prompt'], '"Fala de teste"'));
    $unknownAction = $post(['action' => 'format_disk']);
    check('unknown write actions are refused', static fn(): bool => $unknownAction['status'] === 400);

    /* ---- images ---- */
    $multipart = static function (array $fields, string $filename, string $mime, string $bytes): array {
        $boundary = 'radar-tests-' . bin2hex(random_bytes(8));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        }
        $body .= '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$filename\"\r\nContent-Type: $mime\r\n\r\n" . $bytes . "\r\n--" . $boundary . "--\r\n";
        return [$body, ['Content-Type: multipart/form-data; boundary=' . $boundary]];
    };
    [$upBody, $upHeaders] = $multipart(['action' => 'upload', 'kind' => 'character', 'label' => 'Teste'], 'test.php.png', 'image/png', $png);
    $upload = localRequest('api.php', 'POST', $upBody, array_merge($upHeaders, ['X-CSRF-Token: ' . $writeCsrf]));
    $uploadData = json_decode($upload['body'], true);
    check('image upload is inspected and renamed to an opaque image ID', static fn(): bool => $upload['status'] === 201 && preg_match('/^[a-f0-9]{32}\.png$/D', $uploadData['media']['id'] ?? '') === 1 && (int)$uploadData['media']['is_primary'] === 1);
    $mediaId = $uploadData['media']['id'] ?? '';
    $media = localRequest('media.php?id=' . $mediaId);
    check('private media serves an image MIME without executing the uploaded filename', static fn(): bool => $media['status'] === 200 && ($media['headers']['content-type'] ?? '') === 'image/png' && $media['body'] === $png && !str_contains($media['headers']['content-disposition'] ?? '', 'php'));
    check('uploaded images stay private without a login', static fn(): bool => localRequest('media.php?id=' . $mediaId, 'GET', '', [], false)['status'] === 401);
    foreach ([['evil.png', 'image/png', '<?php system($_GET["c"]); ?>'], ['evil.svg', 'image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'], ['evil.jpg', 'image/jpeg', "\xFF\xD8\xFF<?php echo 1;"]] as [$name, $mime, $bytes]) {
        [$b, $h] = $multipart(['action' => 'upload', 'kind' => 'cover'], $name, $mime, $bytes);
        $response = localRequest('api.php', 'POST', $b, array_merge($h, ['X-CSRF-Token: ' . $writeCsrf]));
        check("hostile upload $name is rejected and stores nothing", static fn(): bool => $response['status'] === 400);
    }
    [$noCsrfBody, $noCsrfHeaders] = $multipart(['action' => 'upload', 'kind' => 'character'], 'a.png', 'image/png', $png);
    check('uploads without CSRF are refused', static fn(): bool => localRequest('api.php', 'POST', $noCsrfBody, $noCsrfHeaders)['status'] === 403);
    check('only the one valid image exists in private storage', static fn(): bool => count(array_filter(scandir($httpRoot . '/storage/media') ?: [], static fn(string $f): bool => !str_starts_with($f, '.'))) === 1);
    $traversal = localRequest('media.php?id=..%2Fconfig.php');
    check('media endpoint rejects traversal to private configuration', static fn(): bool => $traversal['status'] === 404 && !str_contains($traversal['body'], 'admin_password_hash'));
    $personagem = localRequest('personagem.php');
    check('"Meu Personagem" shows the immutable master prompt and the uploaded reference', static fn(): bool => str_contains($personagem['body'], 'Prompt mestre (imutável)') && str_contains($personagem['body'], $mediaId) && str_contains($personagem['body'], 'readonly'));

    /* ---- forms: sources, AI settings, integrations ---- */
    $sourceForm = localRequest('fontes.php', 'POST', formBody(['csrf' => $writeCsrf, 'op' => 'create', 'name' => 'Nova Fonte', 'url' => 'https://nova.example.com/rss', 'category' => 'Economia', 'region' => 'BR', 'weight' => '1.1']), FORM);
    check('adding a source via the form works and lists it', static fn(): bool => $sourceForm['status'] === 303 && str_contains(localRequest('fontes.php')['body'], 'Nova Fonte'));
    $newSourceId = (int)radar_db_value("SELECT id FROM sources WHERE url = 'https://nova.example.com/rss'");
    $badSource = localRequest('fontes.php', 'POST', formBody(['csrf' => $writeCsrf, 'op' => 'create', 'name' => 'Ruim', 'url' => 'http://inseguro.example.com/rss']), FORM);
    check('an insecure (HTTP) source is refused with a flash message', static fn(): bool => $badSource['status'] === 303 && radar_db_value("SELECT COUNT(*) FROM sources WHERE name = 'Ruim'") === 0 && str_contains(localRequest('fontes.php')['body'], 'HTTPS'));
    $toggle = localRequest('fontes.php', 'POST', formBody(['csrf' => $writeCsrf, 'op' => 'toggle', 'id' => $newSourceId, 'active' => '0']), FORM);
    check('sources can be deactivated', static fn(): bool => $toggle['status'] === 303 && (int)radar_source_get($newSourceId)['active'] === 0);
    $noCsrfDelete = localRequest('fontes.php', 'POST', formBody(['op' => 'delete', 'id' => $newSourceId]), FORM);
    check('deleting a source without CSRF does nothing', static fn(): bool => $noCsrfDelete['status'] === 303 && radar_source_get($newSourceId) !== null);
    $delete = localRequest('fontes.php', 'POST', formBody(['csrf' => $writeCsrf, 'op' => 'delete', 'id' => $newSourceId]), FORM);
    check('sources can be deleted with CSRF', static fn(): bool => $delete['status'] === 303 && radar_source_get($newSourceId) === null);
    $secretKey = 'sk-ant-http-test-key-' . bin2hex(random_bytes(6));
    $aiSave = localRequest('config-ia.php', 'POST', formBody(['csrf' => $writeCsrf, 'provider' => 'anthropic', 'model' => 'claude-sonnet-5-5', 'base_url' => '', 'temperature' => '0.7', 'max_tokens' => '1500', 'api_key' => $secretKey]), FORM);
    $aiPage = localRequest('config-ia.php');
    check('the AI key is stored encrypted and never rendered back to the browser', static fn(): bool => $aiSave['status'] === 303 && radar_secret_get('ai_anthropic') === $secretKey && !str_contains($aiPage['body'], $secretKey) && str_contains($aiPage['body'], substr($secretKey, -4)) && !str_contains((string)file_get_contents($httpRoot . '/storage/radar.sqlite'), $secretKey));
    $aiBad = localRequest('config-ia.php', 'POST', formBody(['csrf' => $writeCsrf, 'provider' => 'compatible', 'model' => 'm', 'base_url' => 'http://inseguro.example.com', 'temperature' => '0.7', 'max_tokens' => '1500']), FORM);
    check('an insecure custom AI endpoint is refused', static fn(): bool => $aiBad['status'] === 303 && radar_setting('ai_provider') === 'anthropic');
    $aiTestWithoutKey = $post(['action' => 'ai_test']);
    check('the AI connection test reports a failure honestly when the provider is unreachable', static fn(): bool => $aiTestWithoutKey['status'] === 200 && isset(json_decode($aiTestWithoutKey['body'], true)['ok']));
    radar_setting_set('ai_provider', 'none');
    $tokenForm = localRequest('integracoes.php', 'POST', formBody(['csrf' => $writeCsrf, 'op' => 'token_create', 'name' => 'ChatGPT', 'scope' => 'react']), FORM);
    $tokenPage = localRequest('integracoes.php');
    preg_match('/id="new-token">(rvb_[a-f0-9]+)</', $tokenPage['body'], $tokenMatch);
    $shownToken = $tokenMatch[1] ?? '';
    check('a new API token is shown once, works immediately and is not shown again', static fn(): bool => $tokenForm['status'] === 303 && $shownToken !== '' && localRequest('api.php?action=stats', 'GET', '', ['Authorization: Bearer ' . $shownToken], false)['status'] === 200 && !str_contains(localRequest('integracoes.php')['body'], $shownToken));
    check('the integrations page lists the MCP URL and diagnostics but no secrets', static fn(): bool => str_contains($tokenPage['body'], '/mcp.php') && str_contains($tokenPage['body'], 'Diagnóstico do servidor') && !str_contains($tokenPage['body'], $secretKey));
    $shotBad = localRequest('integracoes.php', 'POST', formBody(['csrf' => $writeCsrf, 'op' => 'shot_save', 'template' => 'http://insecure.example.com/?url={url}']), FORM);
    check('an insecure screenshot service URL is refused', static fn(): bool => $shotBad['status'] === 303 && radar_setting('shot_url', '') === '');

    /* ---- private files are not reachable ---- */
    foreach (['config.php', 'app/bootstrap.php', 'app/db.php', 'storage/radar.sqlite', 'storage/app.key', 'bin/deploy.sh', 'tests/run.php', '.git/config', '.env', 'config.example.php', 'docs/API.md', 'AGENTS.md'] as $private) {
        $response = localRequest($private, 'GET', '', [], false);
        check("private path /$private is not served", static fn(): bool => in_array($response['status'], [403, 404], true) && !str_contains($response['body'], 'admin_password_hash') && !str_contains($response['body'], '<?php') && !str_contains($response['body'], 'SQLite format'));
    }
    check('the web root exposes no stored database or key file', static fn(): bool => !file_exists($httpRoot . '/public/radar.sqlite') && !file_exists($httpRoot . '/public/app.key') && !is_dir($httpRoot . '/public/storage'));
    $serverLog = (string)file_get_contents($httpRoot . '/server.log');
    check('no PHP warnings, notices or fatal errors were logged while exercising every page', static function () use ($serverLog): bool {
        if (preg_match('/PHP (?:Fatal error|Warning|Notice|Deprecated|Parse error)/', $serverLog, $m)) {
            echo '  ' . $m[0] . ': ' . substr($serverLog, (int)strpos($serverLog, $m[0]), 300) . "\n";
            return false;
        }
        return true;
    });

    /* ---- sessions ---- */
    $logoutGet = localRequest('logout.php');
    check('logout cannot be triggered by GET', static fn(): bool => $logoutGet['status'] === 405);
    $oldCookie = $httpCookie;
    $sessionId = explode('=', $oldCookie, 2)[1] ?? '';
    $sessionFile = $httpRoot . '/storage/sessions/sess_' . $sessionId;
    $originalSession = (string)file_get_contents($sessionFile);
    file_put_contents($sessionFile, preg_replace('/seen\|i:\d+;/', 'seen|i:' . (time() - 3601) . ';', $originalSession));
    $idleExpired = localRequest('api.php?action=stats');
    check('one-hour idle sessions cannot access the private API', static fn(): bool => $idleExpired['status'] === 401);
    file_put_contents($sessionFile, $originalSession);
    $httpCookie = $oldCookie;
    file_put_contents($sessionFile, preg_replace('/created\|i:\d+;/', 'created|i:' . (time() - 28801) . ';', $originalSession));
    $absoluteExpired = localRequest('api.php?action=stats');
    check('eight-hour absolute session lifetime is enforced', static fn(): bool => $absoluteExpired['status'] === 401);
    file_put_contents($sessionFile, $originalSession);
    $httpCookie = $oldCookie;
    $httpConfig['admin_password_hash'] = password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT);
    $writeHttpConfig();
    $revoked = localRequest('api.php?action=stats');
    check('changing the admin password revokes existing sessions', static fn(): bool => $revoked['status'] === 401);
    $httpConfig['admin_password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    $writeHttpConfig();
    $loginAgain = localRequest('login.php');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $loginAgain['body'], $loginAgainMatch);
    $relogin = localRequest('login.php', 'POST', formBody(['csrf' => $loginAgainMatch[1] ?? '', 'username' => 'test-editor', 'password' => $password]), FORM);
    $dashboardAgain = localRequest('');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $dashboardAgain['body'], $csrfAgainMatch);
    $logout = localRequest('logout.php', 'POST', formBody(['csrf' => $csrfAgainMatch[1] ?? '']), FORM);
    check('valid POST logout destroys the authenticated session', static fn(): bool => $relogin['status'] === 303 && $logout['status'] === 303 && localRequest('api.php?action=stats')['status'] === 401);
    @unlink($httpRoot . '/storage/limits/' . hash('sha256', 'login:127.0.0.1') . '.json');
    $limitedLogin = localRequest('login.php');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $limitedLogin['body'], $limitedLoginMatch);
    $limitedCsrf = $limitedLoginMatch[1] ?? '';
    $attemptsRejected = true;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $failure = localRequest('login.php', 'POST', formBody(['csrf' => $limitedCsrf, 'username' => 'test-editor', 'password' => 'wrong-password']), FORM);
        $attemptsRejected = $attemptsRejected && $failure['status'] === 401;
    }
    $rateLimited = localRequest('login.php', 'POST', formBody(['csrf' => $limitedCsrf, 'username' => 'test-editor', 'password' => 'wrong-password']), FORM);
    check('repeated failed login attempts trigger HTTP 429', static fn(): bool => $attemptsRejected && $rateLimited['status'] === 429 && isset($rateLimited['headers']['retry-after']));
} finally {
    proc_terminate($server);
    proc_close($server);
}
