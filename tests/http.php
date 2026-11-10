<?php
declare(strict_types=1);

/** Real HTTP authentication/upload checks against a disposable local installation. */
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
$server = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $httpRoot . '/server.log', 'a'], 2 => ['file', $httpRoot . '/server.log', 'a']], $pipes, $httpRoot);
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
    $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5]]);
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
try {
    $notConfigured = localRequest('login.php');
    check('unconfigured installation fails closed with HTTP 503', static fn(): bool => $notConfigured['status'] === 503);
    $notReady = localRequest('health.php');
    check('unconfigured public health returns 503 without private paths', static fn(): bool => $notReady['status'] === 503 && json_decode($notReady['body'], true) === ['status' => 'unavailable'] && !str_contains($notReady['body'], $httpRoot));
    $password = bin2hex(random_bytes(20));
    $apiToken = bin2hex(random_bytes(24));
    $httpConfig = [
        'admin_user' => 'test-editor',
        'admin_password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'api_token_hash' => hash('sha256', $apiToken),
        'storage_path' => $httpRoot . '/storage',
        'secure_cookies' => false,
        'site_url' => 'http://' . $address,
    ];
    $writeHttpConfig = static function () use (&$httpConfig, $httpRoot): void {
        file_put_contents($httpRoot . '/config.php', '<?php return ' . var_export($httpConfig, true) . ';');
        chmod($httpRoot . '/config.php', 0600);
    };
    $writeHttpConfig();
    mkdir($httpRoot . '/storage/feeds', 0700, true);
    file_put_contents($httpRoot . '/storage/feeds/' . hash('sha256', 'news:Geral') . '.json', json_encode(['fetched_at' => time(), 'items' => $items], JSON_THROW_ON_ERROR));
    $healthy = localRequest('health.php');
    check('public health reports coarse readiness without configuration details', static fn(): bool => $healthy['status'] === 200 && json_decode($healthy['body'], true) === ['status' => 'ok']);
    $healthWrite = localRequest('health.php', 'POST');
    check('health endpoint accepts only read methods', static fn(): bool => $healthWrite['status'] === 405);
    $anonymous = localRequest('api.php');
    check('private news API rejects anonymous access', static fn(): bool => $anonymous['status'] === 401);
    $index = localRequest('');
    check('private dashboard sends anonymous users to login', static fn(): bool => $index['status'] === 302 && ($index['headers']['location'] ?? '') === '/login.php');
    $login = localRequest('login.php');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $login['body'], $loginMatch);
    $loginCsrf = $loginMatch[1] ?? '';
    $preLoginCookie = $httpCookie;
    check('login response sets HttpOnly SameSite session cookies', static fn(): bool => $login['status'] === 200 && $loginCsrf !== '' && str_contains(strtolower($login['headers']['set-cookie'] ?? $anonymous['headers']['set-cookie'] ?? ''), 'httponly') && str_contains(strtolower($login['headers']['set-cookie'] ?? $anonymous['headers']['set-cookie'] ?? ''), 'samesite=strict'));
    check('responses enforce CSP without inline scripts', static fn(): bool => str_contains($login['headers']['content-security-policy'] ?? '', "script-src 'self'") && !str_contains($login['headers']['content-security-policy'] ?? '', 'unsafe-inline') && ($login['headers']['x-content-type-options'] ?? '') === 'nosniff');
    $missingCsrf = localRequest('login.php', 'POST', http_build_query(['username' => 'test-editor', 'password' => $password]), ['Content-Type: application/x-www-form-urlencoded']);
    check('login requires a valid anti-CSRF token', static fn(): bool => $missingCsrf['status'] === 403);
    $wrongPassword = localRequest('login.php', 'POST', http_build_query(['csrf' => $loginCsrf, 'username' => 'test-editor', 'password' => 'wrong-password']), ['Content-Type: application/x-www-form-urlencoded']);
    check('incorrect password does not authenticate', static fn(): bool => $wrongPassword['status'] === 401);
    $authenticated = localRequest('login.php', 'POST', http_build_query(['csrf' => $loginCsrf, 'username' => 'test-editor', 'password' => $password]), ['Content-Type: application/x-www-form-urlencoded']);
    check('successful login rotates the session identifier', static fn(): bool => $authenticated['status'] === 303 && $preLoginCookie !== $httpCookie);
    $dashboard = localRequest('');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $dashboard['body'], $csrfMatch);
    $writeCsrf = $csrfMatch[1] ?? '';
    check('authenticated dashboard exposes a new write CSRF token', static fn(): bool => $dashboard['status'] === 200 && $writeCsrf !== '' && $writeCsrf !== $loginCsrf);
    $api = localRequest('api.php?categoria=Geral');
    check('authenticated session can read cached news', static fn(): bool => $api['status'] === 200 && count(json_decode($api['body'], true)['items'] ?? []) === 2);
    $bearer = localRequest('api.php?categoria=Geral', 'GET', '', ['Authorization: Bearer ' . $apiToken], false);
    check('read-only bearer token can read news without session cookies', static fn(): bool => $bearer['status'] === 200);
    $invalidBearer = localRequest('api.php', 'GET', '', ['Authorization: Bearer ' . str_repeat('z', 48)], false);
    check('invalid bearer token is rejected', static fn(): bool => $invalidBearer['status'] === 401);
    $mcpInitialize = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26']], JSON_THROW_ON_ERROR);
    $mcpNoToken = localRequest('mcp.php', 'POST', $mcpInitialize, ['Content-Type: application/json']);
    check('MCP requires bearer credentials even with an authenticated cookie', static fn(): bool => $mcpNoToken['status'] === 401);
    $mcp = localRequest('mcp.php', 'POST', $mcpInitialize, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiToken], false);
    check('authenticated MCP negotiates supported JSON-RPC protocol', static fn(): bool => $mcp['status'] === 200 && (json_decode($mcp['body'], true)['result']['protocolVersion'] ?? '') === '2025-03-26');
    $mcpGet = localRequest('mcp.php', 'GET', '', ['Authorization: Bearer ' . $apiToken], false);
    check('MCP rejects unsupported GET transport requests', static fn(): bool => $mcpGet['status'] === 405);
    $mcpHtml = localRequest('mcp.php', 'POST', '<html>payload</html>', ['Content-Type: text/html', 'Authorization: Bearer ' . $apiToken], false);
    check('MCP rejects non-JSON content types', static fn(): bool => $mcpHtml['status'] === 415);
    $mcpInvalidJson = localRequest('mcp.php', 'POST', '{invalid-json', ['Content-Type: application/json', 'Authorization: Bearer ' . $apiToken], false);
    check('MCP reports malformed JSON with JSON-RPC parse error', static fn(): bool => $mcpInvalidJson['status'] === 400 && (json_decode($mcpInvalidJson['body'], true)['error']['code'] ?? 0) === -32700);
    $mcpInvalidRequest = localRequest('mcp.php', 'POST', '[]', ['Content-Type: application/json', 'Authorization: Bearer ' . $apiToken], false);
    check('MCP rejects invalid JSON-RPC request shapes separately', static fn(): bool => $mcpInvalidRequest['status'] === 400 && (json_decode($mcpInvalidRequest['body'], true)['error']['code'] ?? 0) === -32600);
    $mcpPing = localRequest('mcp.php', 'POST', json_encode(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'ping'], JSON_THROW_ON_ERROR), ['Content-Type: application/json', 'Authorization: Bearer ' . $apiToken], false);
    $mcpPingValue = json_decode($mcpPing['body']);
    check('MCP ping returns an empty result object rather than array', static fn(): bool => $mcpPing['status'] === 200 && ($mcpPingValue->result ?? null) instanceof stdClass && get_object_vars($mcpPingValue->result) === []);
    $mcpList = localRequest('mcp.php', 'POST', json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], JSON_THROW_ON_ERROR), ['Content-Type: application/json', 'Authorization: Bearer ' . $apiToken], false);
    check('MCP lists only the two supported read tools', static fn(): bool => $mcpList['status'] === 200 && array_column(json_decode($mcpList['body'], true)['result']['tools'] ?? [], 'name') === ['news_list', 'trends_list']);
    $mcpCall = localRequest('mcp.php', 'POST', json_encode(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'news_list', 'arguments' => ['category' => 'Geral']]], JSON_THROW_ON_ERROR), ['Content-Type: application/json', 'Authorization: Bearer ' . $apiToken], false);
    $mcpContent = json_decode(json_decode($mcpCall['body'], true)['result']['content'][0]['text'] ?? '{}', true);
    check('MCP news tool returns the same attributed cached headlines', static fn(): bool => $mcpCall['status'] === 200 && count($mcpContent['items'] ?? []) === 2 && ($mcpContent['items'][0]['verification'] ?? '') === 'headline_only');
    $mcpForeignOrigin = localRequest('mcp.php', 'POST', $mcpInitialize, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiToken, 'Origin: https://evil.example.com'], false);
    check('MCP rejects cross-origin requests', static fn(): bool => $mcpForeignOrigin['status'] === 403);
    $mcpWrite = localRequest('mcp.php', 'POST', json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'upload', 'arguments' => []]], JSON_THROW_ON_ERROR), ['Content-Type: application/json', 'Authorization: Bearer ' . $apiToken], false);
    check('MCP exposes no write or upload tool', static fn(): bool => $mcpWrite['status'] === 200 && isset(json_decode($mcpWrite['body'], true)['error']));
    $bearerWrite = localRequest('api.php?action=script', 'POST', '{}', ['Authorization: Bearer ' . $apiToken, 'Content-Type: application/json'], false);
    check('read-only bearer token cannot generate/write scripts', static fn(): bool => $bearerWrite['status'] === 401);
    $scriptBody = json_encode(['id' => $items[0]['id'], 'category' => 'Geral', 'duration' => 25, 'tone' => 'direto', 'verified_context' => ''], JSON_THROW_ON_ERROR);
    $noWriteCsrf = localRequest('api.php?action=script', 'POST', $scriptBody, ['Content-Type: application/json']);
    check('authenticated write endpoint still requires CSRF', static fn(): bool => $noWriteCsrf['status'] === 403);
    $scriptHttp = localRequest('api.php?action=script', 'POST', $scriptBody, ['Content-Type: application/json', 'X-CSRF-Token: ' . $writeCsrf]);
    check('script generation succeeds for a real cached item with CSRF', static fn(): bool => $scriptHttp['status'] === 200 && json_decode($scriptHttp['body'], true)['headline'] === $items[0]['title']);
    $badCategory = localRequest('api.php?categoria%5B%5D=Geral');
    check('array query injection cannot change the category request', static fn(): bool => $badCategory['status'] === 400);
    $unknownArticle = localRequest('api.php?action=script', 'POST', str_replace($items[0]['id'], str_repeat('0', 64), $scriptBody), ['Content-Type: application/json', 'X-CSRF-Token: ' . $writeCsrf]);
    check('client cannot invent an article outside the fetched feed', static fn(): bool => $unknownArticle['status'] === 404);
    $traversal = localRequest('media.php?id=..%2Fconfig.php');
    check('media endpoint rejects traversal to private configuration', static fn(): bool => $traversal['status'] === 404 && !str_contains($traversal['body'], 'admin_password_hash'));
    $boundary = 'radar-tests-' . bin2hex(random_bytes(8));
    $multipart = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"kind\"\r\n\r\ncharacter\r\n--" . $boundary . "\r\nContent-Disposition: form-data; name=\"file\"; filename=\"test.php.png\"\r\nContent-Type: image/png\r\n\r\n" . $png . "\r\n--" . $boundary . "--\r\n";
    $upload = localRequest('upload.php', 'POST', $multipart, ['Content-Type: multipart/form-data; boundary=' . $boundary, 'X-CSRF-Token: ' . $writeCsrf]);
    $uploadData = json_decode($upload['body'], true);
    check('image upload is inspected and renamed to an opaque image ID', static fn(): bool => $upload['status'] === 201 && preg_match('/^[a-f0-9]{32}\.png$/D', $uploadData['id'] ?? '') === 1);
    if ($upload['status'] === 201) {
        $media = localRequest($uploadData['url']);
        check('private media serves image MIME without executing uploaded filename', static fn(): bool => $media['status'] === 200 && ($media['headers']['content-type'] ?? '') === 'image/png' && $media['body'] === $png);
        $anonymousMedia = localRequest($uploadData['url'], 'GET', '', [], false);
        check('uploaded image stays private without login', static fn(): bool => $anonymousMedia['status'] === 401);
    }
    $logoutGet = localRequest('logout.php');
    check('logout cannot be triggered by GET', static fn(): bool => $logoutGet['status'] === 405);
    $oldCookie = $httpCookie;
    $sessionId = explode('=', $oldCookie, 2)[1] ?? '';
    $sessionFile = $httpRoot . '/storage/sessions/sess_' . $sessionId;
    $originalSession = (string)file_get_contents($sessionFile);
    $idleSession = preg_replace('/seen\|i:\d+;/', 'seen|i:' . (time() - 3601) . ';', $originalSession);
    file_put_contents($sessionFile, $idleSession);
    $idleExpired = localRequest('api.php');
    check('one-hour idle sessions cannot access the private API', static fn(): bool => $idleExpired['status'] === 401);
    file_put_contents($sessionFile, $originalSession);
    $httpCookie = $oldCookie;
    $absoluteSession = preg_replace('/created\|i:\d+;/', 'created|i:' . (time() - 28801) . ';', $originalSession);
    file_put_contents($sessionFile, $absoluteSession);
    $absoluteExpired = localRequest('api.php');
    check('eight-hour absolute session lifetime is enforced', static fn(): bool => $absoluteExpired['status'] === 401);
    file_put_contents($sessionFile, $originalSession);
    $httpCookie = $oldCookie;
    $newPassword = bin2hex(random_bytes(20));
    $httpConfig['admin_password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    $writeHttpConfig();
    $revoked = localRequest('api.php');
    check('changing the admin password revokes existing sessions', static fn(): bool => $revoked['status'] === 401);
    $loginAgain = localRequest('login.php');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $loginAgain['body'], $loginAgainMatch);
    $loginAgainCsrf = $loginAgainMatch[1] ?? '';
    $relogin = localRequest('login.php', 'POST', http_build_query(['csrf' => $loginAgainCsrf, 'username' => 'test-editor', 'password' => $newPassword]), ['Content-Type: application/x-www-form-urlencoded']);
    $dashboardAgain = localRequest('');
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $dashboardAgain['body'], $csrfAgainMatch);
    $csrfAgain = $csrfAgainMatch[1] ?? '';
    $logout = localRequest('logout.php', 'POST', http_build_query(['csrf' => $csrfAgain]), ['Content-Type: application/x-www-form-urlencoded']);
    check('valid POST logout destroys authenticated session', static fn(): bool => $relogin['status'] === 303 && $logout['status'] === 303 && localRequest('api.php')['status'] === 401);
    @unlink($httpRoot . '/storage/limits/' . hash('sha256', 'login:127.0.0.1') . '.json');
    $limitedLogin = localRequest('login.php');
    preg_match('/name="csrf" value="([a-f0-9]+)"/', $limitedLogin['body'], $limitedLoginMatch);
    $limitedCsrf = $limitedLoginMatch[1] ?? '';
    $attemptsRejected = true;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $failure = localRequest('login.php', 'POST', http_build_query(['csrf' => $limitedCsrf, 'username' => 'test-editor', 'password' => 'wrong-password']), ['Content-Type: application/x-www-form-urlencoded']);
        $attemptsRejected = $attemptsRejected && $failure['status'] === 401;
    }
    $rateLimited = localRequest('login.php', 'POST', http_build_query(['csrf' => $limitedCsrf, 'username' => 'test-editor', 'password' => 'wrong-password']), ['Content-Type: application/x-www-form-urlencoded']);
    check('repeated failed login attempts trigger HTTP 429', static fn(): bool => $attemptsRejected && $rateLimited['status'] === 429 && isset($rateLimited['headers']['retry-after']));
} finally {
    proc_terminate($server);
    proc_close($server);
}
