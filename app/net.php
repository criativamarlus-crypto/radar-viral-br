<?php
declare(strict_types=1);

/**
 * Outbound HTTP client with SSRF protection.
 *
 * Every request must be HTTPS to a public address. The resolved address is pinned
 * with CURLOPT_RESOLVE (no DNS rebinding between check and connect) and each
 * redirect hop is validated again. Bodies are size-capped while streaming.
 */

/** Request values may arrive as arrays (?x[]=1): only scalars become strings. */
function radar_str(mixed $value, int $max = 0): string
{
    $text = is_scalar($value) ? (string)$value : '';
    return $max > 0 ? mb_substr($text, 0, $max) : $text;
}

function radar_net_ip_is_public(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }
    $packed = inet_pton($ip);
    if ($packed === false) {
        return false;
    }
    if (strlen($packed) === 16) {
        // IPv4-mapped (::ffff:a.b.c.d) must be judged by the embedded IPv4 address.
        if (str_starts_with($packed, "\0\0\0\0\0\0\0\0\0\0\xff\xff")) {
            return radar_net_ip_is_public(inet_ntop(substr($packed, 12)) ?: '');
        }
        // Unique local, link-local, documentation and multicast ranges.
        $first = ord($packed[0]);
        $second = ord($packed[1]);
        if (($first & 0xfe) === 0xfc || $first === 0xff || ($first === 0xfe && ($second & 0xc0) === 0x80)
            || ($first === 0x20 && $second === 0x01 && ord($packed[2]) === 0x0d && ord($packed[3]) === 0xb8)) {
            return false;
        }
        return true;
    }
    $n = unpack('N', $packed)[1];
    $blocked = [
        ['100.64.0.0', 10], ['192.0.0.0', 24], ['192.0.2.0', 24], ['198.18.0.0', 15],
        ['198.51.100.0', 24], ['203.0.113.0', 24], ['224.0.0.0', 4], ['240.0.0.0', 4],
    ];
    foreach ($blocked as [$network, $bits]) {
        $base = unpack('N', inet_pton($network))[1];
        $mask = $bits === 0 ? 0 : (0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF;
        if (($n & $mask) === ($base & $mask)) {
            return false;
        }
    }
    return true;
}

/** Validate an outbound URL. Returns [host, port] or throws with a user-readable reason. */
function radar_net_check_url(string $url): array
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 4096 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
        throw new RuntimeException('Endereço inválido.');
    }
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('Somente endereços HTTPS sem credenciais são aceitos.');
    }
    $port = (int)($parts['port'] ?? 443);
    if ($port !== 443 && !radar_net_allow_private()) {
        throw new RuntimeException('Somente a porta 443 é permitida.');
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    if ($host === '' || strlen($host) > 253) {
        throw new RuntimeException('Endereço sem domínio válido.');
    }
    return [$host, $port];
}

/** Test-only switch (config.php: net_allow_private). Never enabled by setup. */
function radar_net_allow_private(): bool
{
    return function_exists('radar_config') && (radar_config()['net_allow_private'] ?? false) === true;
}

/** Resolve a hostname and refuse anything that is not public. */
function radar_net_resolve(string $host): string
{
    static $cache = [];
    if (isset($cache[$host])) {
        return $cache[$host];
    }
    $addresses = [];
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $addresses[] = $host;
    } else {
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/D', $host)) {
            throw new RuntimeException('Nome de domínio inválido.');
        }
        $v4 = @gethostbynamel($host);
        foreach (is_array($v4) ? $v4 : [] as $ip) {
            $addresses[] = $ip;
        }
        $v6 = @dns_get_record($host, DNS_AAAA);
        foreach (is_array($v6) ? $v6 : [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }
    }
    if ($addresses === []) {
        throw new RuntimeException('Não foi possível resolver o endereço.');
    }
    if (!radar_net_allow_private()) {
        foreach ($addresses as $ip) {
            if (!radar_net_ip_is_public($ip)) {
                throw new RuntimeException('O endereço aponta para uma rede privada e foi bloqueado.');
            }
        }
    }
    return $cache[$host] = $addresses[0];
}

/** Resolve a (possibly relative) Location header against the URL that produced it. */
function radar_net_join(string $base, string $location): string
{
    $location = trim($location);
    if (preg_match('#^https?://#i', $location)) {
        return $location;
    }
    $parts = parse_url($base);
    if (!is_array($parts) || empty($parts['host'])) {
        return $location;
    }
    $origin = ($parts['scheme'] ?? 'https') . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    if (str_starts_with($location, '//')) {
        return ($parts['scheme'] ?? 'https') . ':' . $location;
    }
    if (str_starts_with($location, '/')) {
        return $origin . $location;
    }
    $path = $parts['path'] ?? '/';
    $directory = substr($path, 0, (int)strrpos($path, '/') + 1);
    return $origin . $directory . $location;
}

/**
 * Fetch many URLs concurrently.
 *
 * @param array<string,array{url:string,headers?:array<int,string>,max_bytes?:int,timeout?:int}> $requests
 * @return array<string,array{status:int,body:string,headers:array<string,string>,url:string,error:?string,ms:int}>
 */
function radar_net_fetch_many(array $requests, int $maxRedirects = 3): array
{
    if (isset($GLOBALS['radar_test_transport']) && is_callable($GLOBALS['radar_test_transport'])) {
        return ($GLOBALS['radar_test_transport'])($requests);
    }
    $results = [];
    $state = [];
    foreach ($requests as $id => $request) {
        $state[$id] = ['url' => (string)$request['url'], 'hops' => 0, 'started' => microtime(true)];
    }
    if (!function_exists('curl_multi_init')) {
        foreach ($requests as $id => $request) {
            $results[$id] = ['status' => 0, 'body' => '', 'headers' => [], 'url' => (string)$request['url'], 'error' => 'A extensão cURL não está instalada.', 'ms' => 0];
        }
        return $results;
    }
    $pending = array_keys($requests);
    for ($round = 0; $round <= $maxRedirects && $pending !== []; $round++) {
        $multi = curl_multi_init();
        $handles = [];
        $buffers = [];
        foreach ($pending as $id) {
            $request = $requests[$id];
            $url = $state[$id]['url'];
            try {
                [$host, $port] = radar_net_check_url($url);
                $ip = radar_net_resolve($host);
            } catch (RuntimeException $error) {
                $results[$id] = ['status' => 0, 'body' => '', 'headers' => [], 'url' => $url, 'error' => $error->getMessage(), 'ms' => 0];
                continue;
            }
            $buffers[$id] = ['body' => '', 'headers' => [], 'too_large' => false, 'truncate_ok' => !empty($request['truncate_ok'])];
            $maxBytes = max(1024, (int)($request['max_bytes'] ?? 2_000_000));
            $curl = curl_init($url);
            $address = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
            if (strtoupper((string)($request['method'] ?? 'GET')) === 'POST') {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, (string)($request['body'] ?? ''));
            }
            curl_setopt_array($curl, [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 6,
                CURLOPT_TIMEOUT => max(3, min(60, (int)($request['timeout'] ?? 15))),
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_RESOLVE => [$host . ':' . $port . ':' . $address],
                CURLOPT_ENCODING => '',
                CURLOPT_USERAGENT => 'RadarViralBR/2.0 (+https://viral.netnewsltda.online)',
                CURLOPT_HTTPHEADER => $request['headers'] ?? ['Accept: */*'],
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$buffers, $id): int {
                    if (str_contains($line, ':')) {
                        [$name, $value] = explode(':', $line, 2);
                        $buffers[$id]['headers'][strtolower(trim($name))] = trim($value);
                    } elseif (str_starts_with($line, 'HTTP/')) {
                        $buffers[$id]['headers'] = [];
                    }
                    return strlen($line);
                },
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$buffers, $id, $maxBytes): int {
                    if (strlen($buffers[$id]['body']) + strlen($chunk) > $maxBytes) {
                        if ($buffers[$id]['truncate_ok']) {
                            // Page heads (Open Graph) are enough: keep what fits, then stop.
                            $buffers[$id]['body'] .= substr($chunk, 0, max(0, $maxBytes - strlen($buffers[$id]['body'])));
                        }
                        $buffers[$id]['too_large'] = true;
                        return 0;
                    }
                    $buffers[$id]['body'] .= $chunk;
                    return strlen($chunk);
                },
            ]);
            curl_multi_add_handle($multi, $curl);
            $handles[$id] = $curl;
        }
        $running = 0;
        do {
            curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 0.5);
            }
        } while ($running > 0);
        $next = [];
        foreach ($handles as $id => $curl) {
            $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            $buffer = $buffers[$id];
            curl_multi_remove_handle($multi, $curl);
            curl_close($curl);
            $location = $buffer['headers']['location'] ?? '';
            if ($status >= 300 && $status < 400 && strtoupper((string)($requests[$id]['method'] ?? 'GET')) === 'POST') {
                // Never replay a POST body to a redirect target (could leak API keys).
                $results[$id] = ['status' => $status, 'body' => '', 'headers' => $buffer['headers'], 'url' => $state[$id]['url'], 'error' => 'O servidor respondeu com redirecionamento; verifique o endereço configurado.', 'ms' => (int)((microtime(true) - $state[$id]['started']) * 1000)];
                continue;
            }
            if ($status >= 300 && $status < 400 && $location !== '' && $status !== 304) {
                if ($round >= $maxRedirects) {
                    $results[$id] = ['status' => $status, 'body' => '', 'headers' => $buffer['headers'], 'url' => $state[$id]['url'], 'error' => 'Redirecionamentos demais.', 'ms' => (int)((microtime(true) - $state[$id]['started']) * 1000)];
                } else {
                    $state[$id]['url'] = radar_net_join($state[$id]['url'], $location);
                    $next[] = $id;
                }
                continue;
            }
            $message = null;
            if ($buffer['too_large'] && !$buffer['truncate_ok']) {
                $message = 'A resposta ultrapassou o limite de tamanho.';
            } elseif ($status === 0) {
                $message = $error !== '' ? 'Falha de conexão: ' . $error : 'Não foi possível conectar.';
            }
            $results[$id] = ['status' => $status, 'body' => $buffer['body'], 'headers' => $buffer['headers'], 'url' => $state[$id]['url'], 'error' => $message, 'ms' => (int)((microtime(true) - $state[$id]['started']) * 1000)];
        }
        curl_multi_close($multi);
        $pending = $next;
    }
    foreach ($requests as $id => $request) {
        $results[$id] ??= ['status' => 0, 'body' => '', 'headers' => [], 'url' => (string)$request['url'], 'error' => 'Sem resposta.', 'ms' => 0];
    }
    return $results;
}

/** @return array{status:int,body:string,headers:array<string,string>,url:string,error:?string,ms:int} */
function radar_net_get(string $url, array $options = []): array
{
    $options['url'] = $url;
    return radar_net_fetch_many(['one' => $options])['one'];
}
