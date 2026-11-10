<?php
declare(strict_types=1);

/** Only these server-selected Google endpoints may be fetched. Article URLs are never fetched. */
function radar_categories(): array
{
    return [
        'Geral' => 'https://news.google.com/rss?hl=pt-BR&gl=BR&ceid=BR:pt-419',
        'Brasil' => 'https://news.google.com/rss/headlines/section/topic/NATION?hl=pt-BR&gl=BR&ceid=BR:pt-419',
        'Futebol' => 'https://news.google.com/rss/search?q=futebol&hl=pt-BR&gl=BR&ceid=BR:pt-419',
        'Política' => 'https://news.google.com/rss/headlines/section/topic/POLITICS?hl=pt-BR&gl=BR&ceid=BR:pt-419',
        'Famosos' => 'https://news.google.com/rss/search?q=celebridades&hl=pt-BR&gl=BR&ceid=BR:pt-419',
        'Economia' => 'https://news.google.com/rss/headlines/section/topic/BUSINESS?hl=pt-BR&gl=BR&ceid=BR:pt-419',
        'Mundo' => 'https://news.google.com/rss/headlines/section/topic/WORLD?hl=pt-BR&gl=BR&ceid=BR:pt-419',
        'Tecnologia' => 'https://news.google.com/rss/headlines/section/topic/TECHNOLOGY?hl=pt-BR&gl=BR&ceid=BR:pt-419',
    ];
}

/** Validate links for display; this is deliberately not an arbitrary-URL HTTP client. */
function radar_feed_safe_url(string $url): ?string
{
    $url = trim($url);
    if (strlen($url) > 4096 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https'
        || isset($parts['user']) || isset($parts['pass'])
        || (isset($parts['port']) && $parts['port'] !== 443)) {
        return null;
    }
    $host = strtolower($parts['host'] ?? '');
    if ($host === '' || strlen($host) > 253 || filter_var($host, FILTER_VALIDATE_IP)
        || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $host)
        || preg_match('/\.(?:localhost|local|internal|test|invalid|onion)$/D', $host)
        || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    return $url;
}

function radar_feed_text(string $text, int $maximum = 500): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u', '', $text) ?? '';
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return function_exists('mb_substr') ? mb_substr($text, 0, $maximum, 'UTF-8') : substr($text, 0, $maximum);
}

/** Reject DTDs/entities explicitly: LIBXML_NONET alone is not an entity policy. */
function radar_feed_xml(string $data): SimpleXMLElement
{
    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException('A extensão SimpleXML não está instalada.');
    }
    if ($data === '' || str_contains($data, "\0") || strlen($data) > 2_000_000 || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $data)) {
        throw new RuntimeException('O feed ultrapassou o limite ou contém XML não permitido.');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false || $xml->getName() !== 'rss' || !isset($xml->channel)) {
            throw new RuntimeException('O provedor retornou um feed RSS inválido.');
        }
        return $xml;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function radar_feed_date(string $value): ?string
{
    $value = trim($value);
    if (strlen($value) > 100 || !preg_match('/^(?:(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun),?\s+\d{1,2}\s+[A-Za-z]{3}\s+\d{4}|\d{1,2}\s+[A-Za-z]{3}\s+\d{4}|\d{4}-\d{2}-\d{2}T)/', $value)) {
        return null;
    }
    try {
        $date = new DateTimeImmutable($value);
        $timestamp = $date->getTimestamp();
        return $timestamp >= 946684800 && $timestamp <= time() + 300 ? gmdate(DATE_ATOM, $timestamp) : null;
    } catch (Exception) {
        return null;
    }
}

/** Images must actually be declared in the RSS. No screenshots or scraped covers are invented. */
function radar_feed_image(SimpleXMLElement $entry): ?string
{
    $media = $entry->children('http://search.yahoo.com/mrss/');
    $containers = [$media];
    foreach ($media->group as $group) {
        $containers[] = $group->children('http://search.yahoo.com/mrss/');
    }
    $imageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];
    foreach ($containers as $container) {
        foreach ($container->thumbnail as $thumbnail) {
            // MediaRSS elements are namespaced; their attributes normally are not.
            $attributes = $thumbnail->attributes();
            $type = strtolower(trim((string)$attributes['type']));
            if ($type !== '' && !in_array($type, $imageTypes, true)) {
                continue;
            }
            $url = radar_feed_safe_url((string)$attributes['url']);
            if ($url !== null) {
                return $url;
            }
        }
        foreach ($container->content as $content) {
            $attributes = $content->attributes();
            $type = strtolower(trim((string)$attributes['type']));
            if ($type !== '' && !in_array($type, $imageTypes, true)) {
                continue;
            }
            if ((string)$attributes['medium'] !== 'image' && !in_array($type, $imageTypes, true)) {
                continue;
            }
            $url = radar_feed_safe_url((string)$attributes['url']);
            if ($url !== null) {
                return $url;
            }
        }
    }
    foreach ($entry->enclosure as $enclosure) {
        $attributes = $enclosure->attributes();
        if (in_array(strtolower(trim((string)$attributes['type'])), $imageTypes, true)) {
            $url = radar_feed_safe_url((string)$attributes['url']);
            if ($url !== null) {
                return $url;
            }
        }
    }
    return null;
}

/** Return a list of normalized, deduplicated items. Also used by deterministic fixture tests. */
function radar_parse_feed(string $data, string $category, int $limit = 24): array
{
    $xml = radar_feed_xml($data);
    $items = [];
    foreach ($xml->channel->item as $entry) {
        $title = radar_feed_text((string)$entry->title);
        $url = radar_feed_safe_url((string)$entry->link);
        if ($title === '' || $url === null) {
            continue;
        }
        $id = hash('sha256', $url);
        if (isset($items[$id])) {
            continue;
        }
        $published = radar_feed_date((string)$entry->pubDate);
        $ageHours = $published === null ? null : max(0, (time() - (int)strtotime($published)) / 3600);
        $score = $ageHours === null ? 5 : max(5, 100 - (int)floor($ageHours * 2));
        $source = radar_feed_text((string)$entry->source, 150) ?: 'Google Notícias — origem não informada';
        $image = radar_feed_image($entry);
        $items[$id] = [
            'id' => $id,
            'title' => $title,
            'url' => $url,
            'published' => $published,
            'source' => $source,
            'source_url' => radar_feed_safe_url((string)$entry->source['url']),
            'category' => $category,
            'image' => $image,
            'image_credit' => $image === null ? null : $source,
            'image_note' => $image === null ? 'Este feed não forneceu imagem.' : 'Miniatura fornecida pelo feed; confira os direitos antes de reutilizar.',
            'verification' => 'headline_only',
            'score' => $score,
            'rank_reason' => $published === null
                ? 'Data indisponível; prioridade mínima. Checagem editorial pendente.'
                : 'Prioridade calculada pela recência informada no RSS. Checagem editorial pendente.',
        ];
        // Bound processing and memory even for a very large item count.
        if (count($items) >= 100) {
            break;
        }
    }
    $items = array_values($items);
    usort($items, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
    return array_slice($items, 0, max(1, min(100, $limit)));
}

function radar_parse_trends(string $data): array
{
    $xml = radar_feed_xml($data);
    $items = [];
    foreach ($xml->channel->item as $entry) {
        $title = radar_feed_text((string)$entry->title, 200);
        $url = radar_feed_safe_url((string)$entry->link);
        if ($title === '' || $url === null) {
            continue;
        }
        $namespaces = $entry->getDocNamespaces(true);
        $traffic = null;
        foreach ($namespaces as $uri) {
            $children = $entry->children($uri);
            if (isset($children->approx_traffic)) {
                $candidate = radar_feed_text((string)$children->approx_traffic, 40);
                if ($candidate !== '') {
                    $traffic = $candidate;
                }
            }
        }
        $items[hash('sha256', $title)] = ['title' => $title, 'url' => $url, 'traffic' => $traffic];
        if (count($items) >= 20) {
            break;
        }
    }
    return array_values($items);
}

function radar_feed_download(string $url): string
{
    // The allowlist contains complete URLs, not user-provided hostname substrings.
    $allowed = [...array_values(radar_categories()), 'https://trends.google.com/trending/rss?geo=BR'];
    if (!in_array($url, $allowed, true)) {
        throw new RuntimeException('Endereço de feed não permitido.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL não está instalada.');
    }
    $body = '';
    $tooLarge = false;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'RadarViralBR/2.0 (+https://viral.netnewsltda.online)',
        CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/xml, text/xml'],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge): int {
            if (strlen($body) + strlen($chunk) > 2_000_000) {
                $tooLarge = true;
                return 0;
            }
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    try {
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($tooLarge) {
            throw new RuntimeException('O feed ultrapassou o limite de 2 MB.');
        }
        if ($ok === false || $status !== 200) {
            throw new RuntimeException($status > 0
                ? 'O provedor de notícias está indisponível (HTTP ' . $status . ').'
                : 'Não foi possível conectar ao provedor de notícias.');
        }
        return $body;
    } finally {
        curl_close($curl);
    }
}

function radar_feed_cache_dir(): ?string
{
    $config = function_exists('radar_config') ? radar_config() : [];
    $storage = rtrim((string)($config['storage_path'] ?? dirname(__DIR__) . '/storage'), '/');
    $directory = $storage . '/feeds';
    if ((!is_dir($directory) && !@mkdir($directory, 0700, true)) || !is_writable($directory)) {
        return null;
    }
    @chmod($directory, 0700);
    return $directory;
}

function radar_feed_read_cache(string $filename): ?array
{
    if (!is_file($filename) || filesize($filename) > 4_000_000) {
        return null;
    }
    $contents = @file_get_contents($filename);
    $cache = is_string($contents) ? json_decode($contents, true) : null;
    if (!is_array($cache) || !is_int($cache['fetched_at'] ?? null)
        || $cache['fetched_at'] > time() + 300 || !is_array($cache['items'] ?? null)) {
        return null;
    }
    return $cache;
}

/** Lock refreshes, save atomically and serve a labelled stale cache on upstream failure. */
function radar_feed_cached_fetch(string $key, string $url, callable $parse): array
{
    $directory = radar_feed_cache_dir();
    if ($directory === null) {
        return ['items' => [], 'error' => 'O cache privado está indisponível. Verifique as permissões de storage.', 'updated' => null, 'stale' => false, 'warnings' => []];
    }
    $filename = $directory . '/' . hash('sha256', $key) . '.json';
    $cache = radar_feed_read_cache($filename);
    $config = function_exists('radar_config') ? radar_config() : [];
    $ttl = max(30, min(3600, (int)($config['feed_ttl'] ?? 300)));
    $cachedResponse = static fn(array $stored, bool $stale, array $warnings = []): array => [
        'items' => $stored['items'], 'error' => null,
        'updated' => gmdate(DATE_ATOM, $stored['fetched_at']), 'stale' => $stale, 'warnings' => $warnings,
    ];
    if ($cache !== null && time() - $cache['fetched_at'] < $ttl) {
        return $cachedResponse($cache, false);
    }
    $lock = @fopen($filename . '.lock', 'c');
    if ($lock === false) {
        return $cache === null
            ? ['items' => [], 'error' => 'Não foi possível bloquear o cache privado.', 'updated' => null, 'stale' => false, 'warnings' => []]
            : $cachedResponse($cache, true, ['O cache não pôde ser atualizado; mostrando a última coleta disponível.']);
    }
    @chmod($filename . '.lock', 0600);
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return $cache === null
            ? ['items' => [], 'error' => 'Coleta em andamento. Tente novamente em alguns segundos.', 'updated' => null, 'stale' => false, 'warnings' => []]
            : $cachedResponse($cache, true, ['Nova coleta em andamento; mostrando a última coleta disponível.']);
    }
    try {
        // Another process may have finished between the initial read and this lock.
        $newCache = radar_feed_read_cache($filename);
        if ($newCache !== null && time() - $newCache['fetched_at'] < $ttl) {
            return $cachedResponse($newCache, false);
        }
        $items = $parse(radar_feed_download($url));
        $stored = ['fetched_at' => time(), 'items' => $items];
        $temp = $filename . '.' . bin2hex(random_bytes(8)) . '.tmp';
        $encoded = json_encode($stored, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
        $warnings = [];
        if (@file_put_contents($temp, $encoded, LOCK_EX) === false) {
            $warnings[] = 'Notícias coletadas, mas o cache privado não pôde ser salvo.';
        } else {
            @chmod($temp, 0600);
            if (!@rename($temp, $filename)) {
                @unlink($temp);
                $warnings[] = 'Notícias coletadas, mas o cache privado não pôde ser substituído.';
            }
        }
        return $cachedResponse($stored, false, $warnings);
    } catch (Throwable $error) {
        $message = $error instanceof RuntimeException ? $error->getMessage() : 'Falha ao processar o feed de notícias.';
        if ($cache !== null) {
            return $cachedResponse($cache, true, [$message . ' Mostrando a última coleta disponível; confira a data de atualização.']);
        }
        return ['items' => [], 'error' => $message, 'updated' => null, 'stale' => false, 'warnings' => []];
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function radar_fetch(string $category = 'Geral', int $limit = 24): array
{
    $feeds = radar_categories();
    if (!isset($feeds[$category])) {
        $category = 'Geral';
    }
    $result = radar_feed_cached_fetch('news:' . $category, $feeds[$category], static fn(string $xml): array => radar_parse_feed($xml, $category, 100));
    $result['items'] = array_slice($result['items'], 0, max(1, min(100, $limit)));
    $result['category'] = $category;
    $result['ranking'] = 'Prioridade editorial por recência (heurística). Não mede viralização e não é ranking oficial do Google Trends.';
    return $result;
}

function radar_trends(): array
{
    return radar_feed_cached_fetch('trends:BR', 'https://trends.google.com/trending/rss?geo=BR', 'radar_parse_trends');
}
