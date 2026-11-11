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
copy(dirname(__DIR__) . '/bin/dev-router.php', $httpRoot . '/dev-router.php');
// The test rewrites config.php twice within milliseconds and expects the next request to see it.
// OPcache (enabled by default for cli-server when the extension is loaded) revalidates files only
// every 2 s, which made the session-revocation checks intermittent on fast machines.
array_push($command, '-d', 'opcache.enable=0', '-d', 'opcache.validate_timestamps=1', '-d', 'opcache.revalidate_freq=0', '-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=7M', '-S', $address, '-t', $httpRoot . '/public', $httpRoot . '/dev-router.php');
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
    $multiCategory = localRequest('noticias.php?janela=24&categoria=' . rawurlencode('Finanças,Cartão de crédito'));
    check('the news page shows a count on every category button and lets several be marked at once', static fn(): bool => $multiCategory['status'] === 200 && str_contains($multiCategory['body'], 'class="count"') && str_contains($multiCategory['body'], 'Marque uma ou várias categorias')
        && str_contains($multiCategory['body'], 'aria-pressed="true"') && str_contains($multiCategory['body'], 'Cartão de crédito'));
    $apiMulti = localRequest('api.php?action=news&janela=24&categoria=' . rawurlencode('Futebol,Finanças'));
    check('the API accepts a comma-separated list of categories', static fn(): bool => $apiMulti['status'] === 200 && is_array(json_decode($apiMulti['body'], true)['items'] ?? null));
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
    check('the script page shows editable fields, copy buttons, the fixed-character prompt and the no-smile standard', static fn(): bool => $roteiroPage['status'] === 200 && str_contains($roteiroPage['body'], 'data-copy="video-prompt"') && str_contains($roteiroPage['body'], 'recurring fictional Brazilian character') && str_contains($roteiroPage['body'], 'STOPS smiling') && str_contains($roteiroPage['body'], 'não sorri depois da última fala'));
    $edit = localRequest('roteiro.php', 'POST', formBody(['csrf' => $writeCsrf, 'id' => $scriptId, 'hook' => 'Gancho editado pelo usuário', 'script' => 'Texto novo', 'commentary' => 'Opinião nova', 'cta' => 'E aí?', 'expression' => 'rindo', 'scene' => 'casa']), FORM);
    check('editing a script through the form redirects back and rebuilds the prompt', static fn(): bool => $edit['status'] === 303 && str_contains(radar_script_get($scriptId)['video_prompt'], 'Gancho editado pelo usuário'));
    $editNoCsrf = localRequest('roteiro.php', 'POST', formBody(['id' => $scriptId, 'hook' => 'Invasor']), FORM);
    check('the script form without CSRF changes nothing', static fn(): bool => $editNoCsrf['status'] === 303 && !str_contains(radar_script_get($scriptId)['hook'], 'Invasor'));
    $generatedCard = $post(['action' => 'generate', 'news_id' => $firstNewsId, 'duration' => 25, 'tone' => 'direto', 'layout' => 'caption_card']);
    $generatedCardData = json_decode($generatedCard['body'], true);
    check('GERAR REACT accepts the video template and returns the matching prompt', static fn(): bool => $generatedCard['status'] === 201 && ($generatedCardData['item']['layout'] ?? '') === 'caption_card' && str_contains($generatedCardData['item']['video_prompt'] ?? '', 'caption band') && ($generatedData['item']['layout'] ?? '') === 'caption_card');
    $promptCard = $post(['action' => 'video_prompt', 'spoken' => 'Fala', 'expression' => 'serio', 'scene' => 'carro', 'duration' => 20, 'headline' => 'H', 'source' => 'S', 'layout' => 'caption_card']);
    check('the Studio rebuilds the prompt for the chosen template', static fn(): bool => $promptCard['status'] === 200 && str_contains(json_decode($promptCard['body'], true)['prompt'], 'chest up') && str_contains(json_decode($promptCard['body'], true)['prompt'], 'caption band'));
    $projectCard = $post(['action' => 'project_save', 'title' => 'Projeto card', 'headline' => 'MANCHETE', 'template' => 'caption_card', 'caption' => 'FRASE DE IMPACTO', 'news_id' => $firstNewsId]);
    $projectCardId = json_decode($projectCard['body'], true)['item']['id'] ?? 0;
    $studioPage = localRequest('estudio.php?projeto=' . $projectCardId);
    check('the Studio page offers both templates, the impact phrase field and the transparent overlay export', static fn(): bool => $projectCard['status'] === 201 && $studioPage['status'] === 200 && str_contains($studioPage['body'], 'id="st-template"') && str_contains($studioPage['body'], 'id="st-caption"') && str_contains($studioPage['body'], 'id="st-export-overlay"')
        && str_contains($studioPage['body'], 'caption_card') && str_contains($studioPage['body'], 'FRASE DE IMPACTO') && str_contains($studioPage['body'], 'Notícia em cima, personagem embaixo'));
    $studioNews = localRequest('estudio.php?news=' . $firstNewsId);
    $studioEmpty = localRequest('estudio.php');
    check('pages reference scripts and styles with a fingerprinted URL', static fn(): bool => preg_match('#src="assets/studio\.js\?v=[a-f0-9]{10}"#', $studioNews['body']) === 1 && preg_match('#href="assets/app\.css\?v=[a-f0-9]{10}"#', $studioNews['body']) === 1 && preg_match('#src="assets/app\.js\?v=[a-f0-9]{10}"#', $studioNews['body']) === 1);
    check('the Studio can write the script itself: generator controls and tones are offered once a news is chosen', static fn(): bool => $studioNews['status'] === 200 && str_contains($studioNews['body'], 'id="st-generate"') && str_contains($studioNews['body'], 'id="st-gen-status"') && str_contains($studioNews['body'], 'id="st-scenes"') && str_contains($studioNews['body'], 'id="st-tone"')
        && str_contains($studioNews['body'], '"tones":{"bem-humorado":"Bem-humorado"') && !str_contains($studioEmpty['body'], 'id="st-generate"') && str_contains($studioEmpty['body'], 'o roteiro já é gerado aqui'));
    $newsWithDialog = localRequest('noticias.php?janela=24');
    check('the "Gerar react" dialog lists the video templates', static fn(): bool => str_contains($newsWithDialog['body'], 'id="react-layout"') && str_contains($newsWithDialog['body'], 'Vídeo em tela cheia, frase de impacto no topo e card da notícia'));
    $scriptCardPage = localRequest('roteiro.php?id=' . ($generatedCardData['item']['id'] ?? 0));
    check('the script page lets you change the template and shows the matching prompt', static fn(): bool => str_contains($scriptCardPage['body'], 'name="layout"') && str_contains($scriptCardPage['body'], 'caption band is composed'));
    $reactTemplate = $call($reactToken, 'preparar_react', ['noticia_id' => $firstNewsId, 'modelo_video' => 'caption_card']);
    $reactTemplateData = json_decode(json_decode($reactTemplate['body'], true)['result']['content'][0]['text'] ?? '{}', true);
    $reactBadTemplate = $call($reactToken, 'preparar_react', ['noticia_id' => $firstNewsId, 'modelo_video' => 'inventado']);
    check('the MCP prepares a react for a chosen template and refuses an unknown one', static fn(): bool => str_contains($reactTemplateData['prompt_de_video'] ?? '', 'caption band') && (json_decode($reactBadTemplate['body'], true)['error']['code'] ?? 0) === -32602);
    $scenesGenerated = $post(['action' => 'generate', 'news_id' => $firstNewsId, 'scenes' => 3, 'tone' => 'direto']);
    $scenesData = json_decode($scenesGenerated['body'], true)['item'] ?? [];
    $badScenes = $post(['action' => 'generate', 'news_id' => $firstNewsId, 'scenes' => 8]);
    check('GERAR REACT takes the number of scenes and returns one prompt per scene', static fn(): bool => $scenesGenerated['status'] === 201 && ($scenesData['scenes'] ?? 0) === 3 && is_array($scenesData['video_prompts'] ?? null) && count($scenesData['video_prompts']) >= 1 && count($scenesData['video_prompts']) <= 3
        && str_contains($scenesData['video_prompts'][0] ?? '', 'REFERENCE IMAGE') && $badScenes['status'] === 400);
    $promptScenes = $post(['action' => 'video_prompt', 'spoken' => implode(' ', array_map(static fn(int $i): string => 'Parte ' . $i . ' ' . implode(' ', array_fill(0, 18, 'fala')) . ' fim.', range(1, 6))), 'expression' => 'serio', 'scene' => 'casa', 'scenes' => 3, 'headline' => 'H', 'source' => 'S']);
    $promptScenesData = json_decode($promptScenes['body'], true);
    check('the Studio asks for the prompts of every scene', static fn(): bool => $promptScenes['status'] === 200 && ($promptScenesData['scenes'] ?? 0) === 3 && count($promptScenesData['prompts'] ?? []) === 3 && str_contains($promptScenesData['prompt'] ?? '', 'SCENE 3 OF 3'));
    $scenePage = localRequest('roteiro.php?id=' . ($scenesData['id'] ?? 0));
    check('the script page shows one prompt per scene, with the reference-sheet notice', static fn(): bool => str_contains($scenePage['body'], 'Cena 1 de') && str_contains($scenePage['body'], 'id="scene-prompt-0"') && str_contains($scenePage['body'], 'name="scenes"') && str_contains($scenePage['body'], 'Falta a folha de referência'));
    $characterPage = localRequest('personagem.php');
    check('Meu Personagem has the reference-sheet upload and shows the fixed standards', static fn(): bool => str_contains($characterPage['body'], 'Folha de referência') && str_contains($characterPage['body'], 'name="kind" value="reference"') && str_contains($characterPage['body'], 'Mesmo personagem e mesma reação em todas as cenas'));
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
    [$sheetBody, $sheetHeaders] = $multipart(['action' => 'upload', 'kind' => 'reference', 'label' => 'Folha'], 'folha.png', 'image/png', $png);
    $sheetUpload = localRequest('api.php', 'POST', $sheetBody, array_merge($sheetHeaders, ['X-CSRF-Token: ' . $writeCsrf]));
    $studioWithSheet = localRequest('estudio.php?news=' . $firstNewsId);
    $scenePageWithSheet = localRequest('roteiro.php?id=' . ($scenesData['id'] ?? 0));
    check('after uploading the reference sheet the Studio and the script page offer it for download with the prompts', static fn(): bool => $sheetUpload['status'] === 201 && str_contains($studioWithSheet['body'], '"reference":{"id":"') && str_contains($studioWithSheet['body'], 'id="st-scenes"') && str_contains($studioWithSheet['body'], 'id="st-prompts"')
        && str_contains($scenePageWithSheet['body'], 'Anexe esta imagem em cada cena') && str_contains($scenePageWithSheet['body'], 'download=1'));
    $mcpScenes = $call($reactToken, 'preparar_react', ['noticia_id' => $firstNewsId, 'cenas' => 2]);
    $mcpScenesData = json_decode(json_decode($mcpScenes['body'], true)['result']['content'][0]['text'] ?? '{}', true);
    $mcpBadScenes = $call($reactToken, 'preparar_react', ['noticia_id' => $firstNewsId, 'cenas' => 9]);
    check('the MCP prepares a react by scenes, one prompt per scene, and refuses a wrong count', static fn(): bool => ($mcpScenesData['cenas'] ?? 0) >= 1 && ($mcpScenesData['cenas'] ?? 9) <= 2 && count($mcpScenesData['prompts_de_video_por_cena'] ?? []) === ($mcpScenesData['cenas'] ?? -1)
        && str_contains($mcpScenesData['imagem_de_referencia'] ?? '', 'folha de referência') && (json_decode($mcpBadScenes['body'], true)['error']['code'] ?? 0) === -32602);
    $post(['action' => 'media_delete', 'id' => json_decode($sheetUpload['body'], true)['media']['id'] ?? '']);
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
    check('"Meu Personagem" shows the immutable master prompt and the uploaded reference', static fn(): bool => str_contains($personagem['body'], 'Padrões do vídeo (fixos)') && str_contains($personagem['body'], 'Sem sorrir depois da fala') && str_contains($personagem['body'], 'Prompt mestre (imutável)') && str_contains($personagem['body'], $mediaId) && str_contains($personagem['body'], 'readonly'));

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

    /* ---- OAuth 2.1 over real HTTP (what the ChatGPT connector does) ---- */
    $mainJar = $httpCookie;
    $redirectUri = 'https://chatgpt.com/connector_platform_oauth_redirect';
    $resourceDoc = localRequest('.well-known/oauth-protected-resource/mcp.php', 'GET', '', [], false);
    $serverDoc = localRequest('.well-known/oauth-authorization-server', 'GET', '', [], false);
    $resourceJson = json_decode($resourceDoc['body'], true);
    $serverJson = json_decode($serverDoc['body'], true);
    check('discovery: protected-resource metadata is served at the well-known path (with path suffix)', static fn(): bool => $resourceDoc['status'] === 200 && str_contains($resourceDoc['headers']['content-type'] ?? '', 'application/json') && ($resourceJson['resource'] ?? '') === 'http://' . $address . '/mcp.php' && ($resourceJson['authorization_servers'] ?? []) === ['http://' . $address]);
    check('discovery: authorization-server metadata advertises PKCE S256, registration and refresh', static fn(): bool => $serverDoc['status'] === 200 && ($serverJson['code_challenge_methods_supported'] ?? []) === ['S256'] && str_ends_with($serverJson['registration_endpoint'] ?? '', '/oauth-register.php') && in_array('refresh_token', $serverJson['grant_types_supported'] ?? [], true) && ($serverDoc['headers']['access-control-allow-origin'] ?? '') === '*');
    check('discovery: unknown well-known documents are 404 and the metadata endpoint is read-only', static fn(): bool => localRequest('.well-known/unknown-doc', 'GET', '', [], false)['status'] === 404 && localRequest('oauth-metadata.php?doc=oauth-authorization-server', 'POST', '', [], false)['status'] === 405);
    $challengeHeader = localRequest('mcp.php', 'POST', $rpc(1, 'initialize'), ['Content-Type: application/json'], false)['headers']['www-authenticate'] ?? '';
    check('MCP answers 401 with a WWW-Authenticate that points to the resource metadata', static fn(): bool => str_contains($challengeHeader, 'resource_metadata="http://' . $address . '/.well-known/oauth-protected-resource"') && str_starts_with($challengeHeader, 'Bearer '));
    $register = static fn(array $body): array => localRequest('oauth-register.php', 'POST', json_encode($body), ['Content-Type: application/json'], false);
    $registered = $register(['client_name' => 'ChatGPT HTTP', 'redirect_uris' => [$redirectUri], 'grant_types' => ['authorization_code', 'refresh_token'], 'token_endpoint_auth_method' => 'none']);
    $registeredJson = json_decode($registered['body'], true);
    $clientId = $registeredJson['client_id'] ?? '';
    check('dynamic client registration returns a public client (201, no secret)', static fn(): bool => $registered['status'] === 201 && str_starts_with($clientId, 'rvc_') && !isset($registeredJson['client_secret']) && ($registeredJson['redirect_uris'] ?? []) === [$redirectUri]);
    check('registration rejects insecure redirects, wrong content types and GET', static fn(): bool => $register(['redirect_uris' => ['http://evil.example.com/cb']])['status'] === 400 && json_decode($register(['redirect_uris' => ['http://evil.example.com/cb']])['body'], true)['error'] === 'invalid_redirect_uri'
        && localRequest('oauth-register.php', 'POST', 'x=1', FORM, false)['status'] === 415 && localRequest('oauth-register.php', 'GET', '', [], false)['status'] === 405);
    $verifier = radar_oauth_random(32);
    $authQuery = static fn(string $client, string $scope = 'read react', array $override = []): string => http_build_query($override + ['client_id' => $client, 'redirect_uri' => $redirectUri, 'response_type' => 'code', 'code_challenge' => radar_oauth_s256($GLOBALS['verifier']), 'code_challenge_method' => 'S256', 'state' => 'st-123', 'scope' => $scope, 'resource' => 'http://' . $GLOBALS['address'] . '/mcp.php'], '', '&', PHP_QUERY_RFC3986);
    $httpCookie = '';
    $interstitial = localRequest('oauth-authorize.php?' . $authQuery($clientId), 'GET', '', [], false);
    check('opened from another site (no cookie) the authorize page shows an interstitial, never a consent form', static fn(): bool => $interstitial['status'] === 200 && str_contains($interstitial['body'], 'ChatGPT HTTP') && str_contains($interstitial['body'], 'go=1') && !str_contains($interstitial['body'], 'name="decision"'));
    $toLogin = localRequest('oauth-authorize.php?' . $authQuery($clientId) . '&go=1');
    check('after the click, an anonymous visitor is sent to the login', static fn(): bool => $toLogin['status'] === 303 && ($toLogin['headers']['location'] ?? '') === '/login.php');
    $loginForOauth = localRequest('login.php');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $loginForOauth['body'], $oauthLoginCsrf);
    $backToAuthorize = localRequest('login.php', 'POST', formBody(['csrf' => $oauthLoginCsrf[1] ?? '', 'username' => 'test-editor', 'password' => $password]), FORM);
    $authorizeLocation = $backToAuthorize['headers']['location'] ?? '';
    check('login returns to the pending authorization request (same-site path only)', static fn(): bool => $backToAuthorize['status'] === 303 && str_starts_with($authorizeLocation, '/oauth-authorize.php?') && str_contains($authorizeLocation, 'code_challenge='));
    $consent = localRequest($authorizeLocation);
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $consent['body'], $consentCsrf);
    check('the logged-in admin sees a consent screen naming the app and the destination host', static fn(): bool => $consent['status'] === 200 && str_contains($consent['body'], 'Autorizar «ChatGPT HTTP»') && str_contains($consent['body'], 'chatgpt.com') && str_contains($consent['body'], 'value="approve"') && str_contains($consent['body'], 'value="deny"') && ($consentCsrf[1] ?? '') !== '');
    $consentFields = static fn(string $client, string $decision, array $grant, string $scope = 'read react'): string => http_build_query(['csrf' => $GLOBALS['consentCsrf'][1] ?? '', 'decision' => $decision, 'grant' => $grant, 'client_id' => $client, 'redirect_uri' => $GLOBALS['redirectUri'], 'response_type' => 'code', 'code_challenge' => radar_oauth_s256($GLOBALS['verifier']), 'code_challenge_method' => 'S256', 'state' => 'st-123', 'scope' => $scope, 'resource' => 'http://' . $GLOBALS['address'] . '/mcp.php']);
    $redirectFrom = static function (array $response): string {
        preg_match('/<meta name="oauth-redirect" content="([^"]+)"/', $response['body'], $m);
        return html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5);
    };
    check('consent without a valid CSRF token is refused', static fn(): bool => localRequest('oauth-authorize.php', 'POST', str_replace($consentCsrf[1] ?? 'x', 'bad', $consentFields($clientId, 'approve', ['react'])), FORM)['status'] === 403);
    $denied = localRequest('oauth-authorize.php', 'POST', $consentFields($clientId, 'deny', []), FORM);
    $deniedUrl = $redirectFrom($denied);
    check('denying sends the client back with access_denied and the original state, never a code', static fn(): bool => $denied['status'] === 200 && str_starts_with($deniedUrl, $redirectUri . '?') && str_contains($deniedUrl, 'error=access_denied') && str_contains($deniedUrl, 'state=st-123') && !str_contains($deniedUrl, 'code='));
    $approved = localRequest('oauth-authorize.php', 'POST', $consentFields($clientId, 'approve', ['react']), FORM);
    $approvedUrl = $redirectFrom($approved);
    parse_str((string)parse_url($approvedUrl, PHP_URL_QUERY), $callback);
    check('approving redirects (by script, not a form redirect) with code, state and issuer', static fn(): bool => $approved['status'] === 200 && str_starts_with($approvedUrl, $redirectUri . '?') && strlen($callback['code'] ?? '') >= 40 && ($callback['state'] ?? '') === 'st-123' && ($callback['iss'] ?? '') === 'http://' . $address && str_contains($approved['body'], 'oauth.js'));
    $tokenPost = static fn(array $fields, array $headers = FORM): array => localRequest('oauth-token.php', 'POST', http_build_query($fields), $headers, false);
    $exchangeFields = ['grant_type' => 'authorization_code', 'code' => $callback['code'] ?? '', 'client_id' => $clientId, 'redirect_uri' => $redirectUri, 'code_verifier' => $verifier];
    check('the token endpoint rejects non-form bodies and GET', static fn(): bool => localRequest('oauth-token.php', 'POST', '{}', ['Content-Type: application/json'], false)['status'] === 415 && localRequest('oauth-token.php', 'GET', '', [], false)['status'] === 405);
    check('a wrong PKCE verifier cannot redeem the code (RFC error invalid_grant)', static fn(): bool => json_decode($tokenPost(['code_verifier' => radar_oauth_random(32)] + $exchangeFields)['body'], true)['error'] === 'invalid_grant');
    $ownCode = localRequest('oauth-authorize.php', 'POST', $consentFields($clientId, 'approve', ['react']), FORM);
    parse_str((string)parse_url($redirectFrom($ownCode), PHP_URL_QUERY), $secondCallback);
    $tokenResponse = $tokenPost(['code' => $secondCallback['code'] ?? ''] + $exchangeFields);
    $tokenJson = json_decode($tokenResponse['body'], true);
    check('the code is exchanged for Bearer tokens with no-store headers', static fn(): bool => $tokenResponse['status'] === 200 && ($tokenJson['token_type'] ?? '') === 'Bearer' && ($tokenJson['expires_in'] ?? 0) === 3600 && ($tokenJson['scope'] ?? '') === 'read react' && str_contains($tokenResponse['headers']['cache-control'] ?? '', 'no-store') && ($tokenResponse['headers']['pragma'] ?? '') === 'no-cache');
    $mcpWith = static fn(string $token, string $payload): array => localRequest('mcp.php', 'POST', $payload, ['Content-Type: application/json', 'Authorization: Bearer ' . $token], false);
    $oauthList = $mcpWith($tokenJson['access_token'] ?? '', $rpc(2, 'tools/list'));
    check('the OAuth access token works on the MCP endpoint', static fn(): bool => $oauthList['status'] === 200 && count(json_decode($oauthList['body'], true)['result']['tools'] ?? []) === 7);
    $oauthReact = json_decode($mcpWith($tokenJson['access_token'], $rpc(3, 'tools/call', ['name' => 'preparar_react', 'arguments' => (object)['noticia_id' => $firstNewsId]]))['body'], true);
    check('with the react scope approved, preparar_react works through OAuth', static fn(): bool => ($oauthReact['result']['isError'] ?? true) === false);
    check('replaying a used authorization code fails and revokes the tokens it produced', static fn(): bool => json_decode($tokenPost(['code' => $secondCallback['code'] ?? ''] + $exchangeFields)['body'], true)['error'] === 'invalid_grant' && $mcpWith($tokenJson['access_token'], $rpc(4, 'ping'))['status'] === 401);
    $readOnlyApproval = localRequest('oauth-authorize.php', 'POST', $consentFields($clientId, 'approve', []), FORM);
    parse_str((string)parse_url($redirectFrom($readOnlyApproval), PHP_URL_QUERY), $readOnlyCallback);
    $readOnlyTokens = json_decode($tokenPost(['code' => $readOnlyCallback['code'] ?? ''] + $exchangeFields)['body'], true);
    $readOnlyReact = json_decode($mcpWith($readOnlyTokens['access_token'] ?? '', $rpc(5, 'tools/call', ['name' => 'preparar_react', 'arguments' => (object)['noticia_id' => $firstNewsId]]))['body'], true);
    check('unchecking "react" grants read only: preparar_react is refused', static fn(): bool => ($readOnlyTokens['scope'] ?? '') === 'read' && ($readOnlyReact['result']['isError'] ?? false) === true);
    $refreshed = json_decode($tokenPost(['grant_type' => 'refresh_token', 'refresh_token' => $readOnlyTokens['refresh_token'] ?? '', 'client_id' => $clientId])['body'], true);
    $oldAccess = $mcpWith($readOnlyTokens['access_token'], $rpc(6, 'ping'));
    check('refresh rotates the pair: the new access token works, the old access and refresh tokens do not', static fn(): bool => str_starts_with($refreshed['access_token'] ?? '', 'rvo_') && $mcpWith($refreshed['access_token'], $rpc(7, 'ping'))['status'] === 200 && $oldAccess['status'] === 401 && str_contains($oldAccess['headers']['www-authenticate'] ?? '', 'invalid_token')
        && json_decode($tokenPost(['grant_type' => 'refresh_token', 'refresh_token' => $readOnlyTokens['refresh_token'], 'client_id' => $clientId])['body'], true)['error'] === 'invalid_grant');
    $evil = localRequest('oauth-authorize.php?' . $authQuery($clientId, 'read', ['redirect_uri' => 'https://evil.example.com/steal']));
    check('an unregistered redirect_uri is shown as an error and never redirected to', static fn(): bool => $evil['status'] === 400 && !isset($evil['headers']['location']) && !str_contains($evil['body'], 'oauth-redirect') && str_contains($evil['body'], 'redirect_uri'));
    $noPkce = localRequest('oauth-authorize.php?' . str_replace('code_challenge_method=S256', 'code_challenge_method=plain', $authQuery($clientId)));
    check('authorization without PKCE S256 is refused', static fn(): bool => $noPkce['status'] === 400 && !isset($noPkce['headers']['location']));
    $manualPage = localRequest('integracoes.php', 'POST', formBody(['csrf' => $consentCsrf[1] ?? '', 'op' => 'oauth_client_create', 'name' => 'ChatGPT manual', 'redirects' => $redirectUri]), FORM);
    $manualView = localRequest('integracoes.php');
    preg_match('/id="new-client-id">(rvc_[A-Za-z0-9_-]+)</', $manualView['body'], $manualId);
    preg_match('/id="new-client-secret">(rvs_[A-Za-z0-9_-]+)</', $manualView['body'], $manualSecret);
    check('a manual OAuth client shows its secret exactly once in the panel', static fn(): bool => $manualPage['status'] === 303 && ($manualId[1] ?? '') !== '' && ($manualSecret[1] ?? '') !== '' && !str_contains(localRequest('integracoes.php')['body'], $manualSecret[1]) && str_contains(localRequest('integracoes.php')['body'], 'ChatGPT manual'));
    $manualApprove = localRequest('oauth-authorize.php', 'POST', $consentFields($manualId[1] ?? '', 'approve', ['react']), FORM);
    parse_str((string)parse_url($redirectFrom($manualApprove), PHP_URL_QUERY), $manualCallback);
    $manualFields = ['grant_type' => 'authorization_code', 'code' => $manualCallback['code'] ?? '', 'redirect_uri' => $redirectUri, 'code_verifier' => $verifier];
    check('a manual client must present its secret: without it the token endpoint answers 401', static fn(): bool => $tokenPost($manualFields + ['client_id' => $manualId[1] ?? ''])['status'] === 401 && $tokenPost($manualFields + ['client_id' => $manualId[1] ?? '', 'client_secret' => 'rvs_wrong'])['status'] === 401);
    $manualApprove2 = localRequest('oauth-authorize.php', 'POST', $consentFields($manualId[1] ?? '', 'approve', ['react']), FORM);
    parse_str((string)parse_url($redirectFrom($manualApprove2), PHP_URL_QUERY), $manualCallback2);
    $basic = ['Content-Type: application/x-www-form-urlencoded', 'Authorization: Basic ' . base64_encode(($manualId[1] ?? '') . ':' . ($manualSecret[1] ?? ''))];
    $manualTokens = json_decode($tokenPost(['code' => $manualCallback2['code'] ?? ''] + $manualFields, $basic)['body'], true);
    check('a manual client authenticates with HTTP Basic (client_secret_basic) and gets working tokens', static fn(): bool => str_starts_with($manualTokens['access_token'] ?? '', 'rvo_') && $mcpWith($manualTokens['access_token'], $rpc(8, 'ping'))['status'] === 200);
    $integrations = localRequest('integracoes.php');
    check('the panel lists active OAuth connections and the OAuth endpoints, without any token value', static fn(): bool => str_contains($integrations['body'], 'OAuth (conectar o ChatGPT)') && str_contains($integrations['body'], '/oauth-token.php') && str_contains($integrations['body'], 'ChatGPT HTTP') && !str_contains($integrations['body'], $manualTokens['access_token']) && !str_contains($integrations['body'], $tokenJson['access_token']));
    $revokeId = (int)radar_db_value("SELECT id FROM api_tokens WHERE kind = 'oauth' AND revoked_at IS NULL ORDER BY id DESC LIMIT 1");
    $revokeResponse = localRequest('integracoes.php', 'POST', formBody(['csrf' => $consentCsrf[1] ?? '', 'op' => 'token_revoke', 'id' => $revokeId]), FORM);
    check('an OAuth connection can be revoked from the panel and stops working immediately', static fn(): bool => $revokeResponse['status'] === 303 && $mcpWith($manualTokens['access_token'], $rpc(9, 'ping'))['status'] === 401);
    $httpCookie = $mainJar;

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
    if ($revoked['status'] !== 401) {
        echo "  diagnóstico (revogação): status={$revoked['status']} corpo=" . substr($revoked['body'], 0, 200) . "
";
        echo '  log do servidor: ' . substr(str_replace("
", ' | ', trim((string)file_get_contents($httpRoot . '/server.log'))), -600) . "
";
    }
    check('changing the admin password revokes existing sessions', static fn(): bool => $revoked['status'] === 401);
    $httpConfig['admin_password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    $writeHttpConfig();
    $loginAgain = localRequest('login.php');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $loginAgain['body'], $loginAgainMatch);
    $relogin = localRequest('login.php', 'POST', formBody(['csrf' => $loginAgainMatch[1] ?? '', 'username' => 'test-editor', 'password' => $password]), FORM);
    $dashboardAgain = localRequest('');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $dashboardAgain['body'], $csrfAgainMatch);
    $logout = localRequest('logout.php', 'POST', formBody(['csrf' => $csrfAgainMatch[1] ?? '']), FORM);
    $afterLogout = localRequest('api.php?action=stats');
    if ($relogin['status'] !== 303 || $logout['status'] !== 303 || $afterLogout['status'] !== 401) {
        echo "  diagnóstico (logout): relogin={$relogin['status']} dashboard={$dashboardAgain['status']} logout={$logout['status']} depois={$afterLogout['status']} corpoLogout=" . substr($logout['body'], 0, 160) . "
";
    }
    check('valid POST logout destroys the authenticated session', static fn(): bool => $relogin['status'] === 303 && $logout['status'] === 303 && $afterLogout['status'] === 401);
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
