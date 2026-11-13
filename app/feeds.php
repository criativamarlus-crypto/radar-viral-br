<?php
declare(strict_types=1);

/**
 * Feed parsing only (RSS 2.0, RSS 1.0/RDF, Atom, Google Trends RSS).
 * No network access happens here; fetching lives in net.php / collector.php.
 * Nothing is invented: every field comes from the feed or stays empty.
 */

/** Validate links for display/storage; deliberately not an arbitrary-URL HTTP client. */
function radar_feed_safe_url(string $url): ?string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    // Publishers often leave accents unescaped in paths (…/estado-alemão/…).
    $url = preg_replace_callback('/[\x80-\xff]+/', static fn(array $m): string => rawurlencode($m[0]), $url) ?? $url;
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
        || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9-]{2,63}$/D', $host)
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
    $data = ltrim($data, "\xEF\xBB\xBF \t\r\n");
    // Several Brazilian publishers (UOL, Folha) still send Latin-1 without a usable declaration.
    if ($data !== '' && function_exists('mb_check_encoding') && !mb_check_encoding($data, 'UTF-8')) {
        $converted = mb_convert_encoding($data, 'UTF-8', 'Windows-1252');
        $converted = preg_replace('/^(<\?xml[^>]*?)\s+encoding=["\'][^"\']*["\']/i', '$1', $converted, 1) ?? $converted;
        $data = $converted;
    }
    // A DTD (the only place entities can be declared) must precede the root element, so only
    // the prolog needs inspecting. Article HTML inside CDATA may legitimately mention <!DOCTYPE>.
    $rootAt = preg_match('/<(?:rss|feed|rdf:RDF|RDF)[\s>]/i', $data, $root, PREG_OFFSET_CAPTURE) === 1 ? (int)$root[0][1] : strlen($data);
    if ($data === '' || str_contains($data, "\0") || strlen($data) > 3_000_000 || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', substr($data, 0, $rootAt))) {
        throw new RuntimeException('O feed está vazio, ultrapassou o limite ou contém XML não permitido.');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if ($xml === false || !in_array($xml->getName(), ['rss', 'feed', 'RDF'], true)) {
            throw new RuntimeException('O endereço não devolveu um feed RSS/Atom válido.');
        }
        return $xml;
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

/** @return array<int,SimpleXMLElement> */
function radar_xml_children(SimpleXMLElement $node, string $localName): array
{
    $found = $node->xpath("*[local-name()='" . $localName . "']");
    return is_array($found) ? $found : [];
}

function radar_xml_text(SimpleXMLElement $node, string $localName): string
{
    $children = radar_xml_children($node, $localName);
    return $children === [] ? '' : trim((string)$children[0]);
}

function radar_feed_date(string $value): ?int
{
    $value = trim($value);
    if (strlen($value) > 100 || !preg_match('/^(?:(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun|Seg|Ter|Qua|Qui|Sex|S[áa]b|Dom),?\s+\d{1,2}\s+[A-Za-z]{3}\s+\d{4}|\d{1,2}\s+[A-Za-z]{3}\s+\d{4}|\d{4}-\d{2}-\d{2}T)/u', $value)) {
        return null;
    }
    // Some Brazilian feeds use Portuguese day/month abbreviations.
    $value = strtr($value, ['Seg' => 'Mon', 'Ter' => 'Tue', 'Ter,' => 'Tue,', 'Qua' => 'Wed', 'Qui' => 'Thu', 'Sex' => 'Fri', 'Sáb' => 'Sat', 'Sab' => 'Sat', 'Dom' => 'Sun',
        'Fev' => 'Feb', 'Abr' => 'Apr', 'Mai' => 'May', 'Ago' => 'Aug', 'Set' => 'Sep', 'Out' => 'Oct', 'Dez' => 'Dec']);
    try {
        $timestamp = (new DateTimeImmutable($value))->getTimestamp();
    } catch (Exception) {
        return null;
    }
    return $timestamp >= 946684800 && $timestamp <= time() + 600 ? $timestamp : null;
}

/** Canonical form used for deduplication: no fragment, no tracking parameters. */
function radar_feed_canonical_url(string $url): string
{
    $parts = parse_url($url);
    if (!is_array($parts) || !isset($parts['host'])) {
        return $url;
    }
    $query = '';
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $params);
        foreach (array_keys($params) as $key) {
            if (preg_match('/^(?:utm_|fbclid|gclid|ref|cmpid|origem|xtor|at_)/i', (string)$key)) {
                unset($params[$key]);
            }
        }
        ksort($params);
        $query = $params === [] ? '' : '?' . http_build_query($params);
    }
    $path = $parts['path'] ?? '/';
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }
    return 'https://' . strtolower($parts['host']) . $path . $query;
}

function radar_feed_image_allowed(?string $url): ?string
{
    if ($url === null) {
        return null;
    }
    $safe = radar_feed_safe_url($url);
    if ($safe === null || preg_match('/(?:pixel|tracking|beacon|1x1|spacer|blank\.gif)/i', $safe)) {
        return null;
    }
    return $safe;
}

/** Images must actually be declared by the feed. No screenshots or scraped covers are invented here. */
function radar_feed_image(SimpleXMLElement $entry): ?string
{
    $imageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/jpg'];
    $media = $entry->children('http://search.yahoo.com/mrss/');
    $containers = [$media];
    foreach ($media->group as $group) {
        $containers[] = $group->children('http://search.yahoo.com/mrss/');
    }
    foreach ($containers as $container) {
        foreach ($container->thumbnail as $thumbnail) {
            $attributes = $thumbnail->attributes();
            $url = radar_feed_image_allowed((string)$attributes['url']);
            if ($url !== null) {
                return $url;
            }
        }
        foreach ($container->content as $content) {
            $attributes = $content->attributes();
            $type = strtolower(trim((string)$attributes['type']));
            $medium = strtolower(trim((string)$attributes['medium']));
            if ($medium !== 'image' && !in_array($type, $imageTypes, true)) {
                continue;
            }
            $url = radar_feed_image_allowed((string)$attributes['url']);
            if ($url !== null) {
                return $url;
            }
        }
    }
    foreach (radar_xml_children($entry, 'enclosure') as $enclosure) {
        $attributes = $enclosure->attributes();
        $type = strtolower(trim((string)$attributes['type']));
        // Standard: <enclosure url="…">. Exame nests <url> inside instead.
        $candidate = (string)$attributes['url'] !== '' ? (string)$attributes['url'] : radar_xml_text($enclosure, 'url');
        $url = radar_feed_image_allowed($candidate);
        if ($url !== null && (in_array($type, $imageTypes, true) || preg_match('/\.(?:jpe?g|png|webp)(?:\?|$)/i', $url))) {
            return $url;
        }
    }
    $mediaUrl = radar_feed_image_allowed(radar_xml_text($entry, 'mediaurl'));
    if ($mediaUrl !== null && preg_match('/\.(?:jpe?g|png|webp)(?:\?|$)/i', $mediaUrl)) {
        return $mediaUrl;
    }
    // Last resort: an <img> that the publisher itself embedded in the item body.
    foreach (['encoded', 'description', 'summary', 'content'] as $field) {
        $html = radar_xml_text($entry, $field);
        if ($html !== '' && preg_match_all('/<img\b[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/\b(?:width|height)\s*=\s*["\']?1["\']?(?:\s|\/|>)/i', $tag)) {
                    continue;
                }
                if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $tag, $source)) {
                    $url = radar_feed_image_allowed($source[1]);
                    if ($url !== null) {
                        return $url;
                    }
                }
            }
        }
    }
    return null;
}

/** Google News packs "related coverage" into the description as a list of links. */
function radar_feed_related(string $html): array
{
    $related = [];
    if (preg_match_all('/<a\s+[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>(?:&nbsp;|\s)*(?:<font[^>]*>(.*?)<\/font>)?/is', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $url = radar_feed_safe_url($match[1]);
            $title = radar_feed_text($match[2], 200);
            if ($url === null || $title === '') {
                continue;
            }
            $related[] = ['title' => $title, 'source' => radar_feed_text($match[3] ?? '', 100), 'url' => $url];
            if (count($related) >= 6) {
                break;
            }
        }
    }
    return $related;
}

/**
 * Parse an RSS/Atom feed into normalised items.
 *
 * @return array<int,array<string,mixed>>
 */
function radar_parse_feed(string $data, string $defaultSource = '', int $limit = 100): array
{
    $xml = radar_feed_xml($data);
    $entries = $xml->xpath("//*[local-name()='item' or local-name()='entry']");
    $items = [];
    foreach (is_array($entries) ? $entries : [] as $entry) {
        $title = radar_feed_text(radar_xml_text($entry, 'title'), 400);
        $link = '';
        foreach (radar_xml_children($entry, 'link') as $candidate) {
            $attributes = $candidate->attributes();
            $href = (string)$attributes['href'];
            if ($href !== '') {
                // Atom: prefer rel="alternate" (the default) over self/enclosure links.
                $rel = (string)$attributes['rel'];
                if ($rel === '' || $rel === 'alternate') {
                    $link = $href;
                    break;
                }
            } elseif (trim((string)$candidate) !== '') {
                $link = trim((string)$candidate);
                break;
            }
        }
        if ($link === '') {
            $guid = radar_xml_text($entry, 'guid');
            $link = str_starts_with($guid, 'https://') ? $guid : '';
        }
        // Folha wraps links in a click redirector: …/rss091/*https://real-article-url
        if (preg_match('/^https:\/\/[^*]+\*(https:\/\/.+)$/', $link, $wrapped)) {
            $link = $wrapped[1];
        }
        $url = radar_feed_safe_url($link);
        if ($title === '' || $url === null) {
            continue;
        }
        $sourceNodes = radar_xml_children($entry, 'source');
        $sourceName = $sourceNodes !== [] ? radar_feed_text((string)$sourceNodes[0], 150) : '';
        if ($sourceName !== '') {
            // Google News appends " - Veículo" to every title.
            $suffix = ' - ' . $sourceName;
            if (str_ends_with($title, $suffix)) {
                $title = rtrim(substr($title, 0, -strlen($suffix)));
            }
        } else {
            $sourceName = $defaultSource;
        }
        $rawDescription = radar_xml_text($entry, 'description');
        if ($rawDescription === '') {
            $rawDescription = radar_xml_text($entry, 'summary');
        }
        // Only Google News uses the description as a "related coverage" list.
        $isGoogleNews = (string)parse_url($url, PHP_URL_HOST) === 'news.google.com';
        $related = [];
        if ($isGoogleNews) {
            foreach (radar_feed_related($rawDescription) as $candidate) {
                if ($candidate['url'] !== $url && mb_strtolower($candidate['title']) !== mb_strtolower($title)) {
                    $related[] = $candidate;
                }
            }
        }
        $summary = $isGoogleNews ? '' : radar_feed_text($rawDescription, 500);
        // Boilerplate some publishers append: "Leia mais (11/10/2026 - 18h36)".
        $summary = trim(preg_replace('/\s*Leia mais\b.*$/iu', '', $summary) ?? $summary);
        // WordPress footers: "O post X apareceu primeiro em Y." / "The post X appeared first on Y."
        $summary = trim(preg_replace('/\s*(?:O post|The post|A postagem)\s.+?\s(?:apareceu primeiro em|appeared first on)\s.*$/iu', '', $summary) ?? $summary);
        if ($summary !== '' && (mb_strtolower($summary) === mb_strtolower($title) || mb_strlen($summary) < 25)) {
            $summary = '';
        }
        $published = null;
        foreach (['pubDate', 'published', 'updated', 'date'] as $field) {
            $published = radar_feed_date(radar_xml_text($entry, $field));
            if ($published !== null) {
                break;
            }
        }
        $image = radar_feed_image($entry);
        $uid = hash('sha256', radar_feed_canonical_url($url));
        if (isset($items[$uid])) {
            continue;
        }
        $items[$uid] = [
            'uid' => $uid,
            'title' => $title,
            'url' => $url,
            'published_at' => $published,
            'source_name' => $sourceName !== '' ? $sourceName : 'Fonte não informada',
            'summary' => $summary,
            'image_url' => $image,
            'image_credit' => $image === null ? null : ($sourceName !== '' ? $sourceName : null),
            'image_note' => $image === null ? 'O feed não forneceu imagem.' : 'Miniatura declarada pelo próprio feed; confira os direitos antes de reutilizar.',
            'related' => $related,
        ];
        if (count($items) >= max(1, min(300, $limit))) {
            break;
        }
    }
    return array_values($items);
}

/**
 * Google Trends daily RSS: each trend carries the news items Google associates with it,
 * including direct article URLs. Those become first-class news candidates.
 *
 * @return array<int,array<string,mixed>>
 */
function radar_parse_trends(string $data): array
{
    $xml = radar_feed_xml($data);
    $trends = [];
    $entries = $xml->xpath("//*[local-name()='item']");
    foreach (is_array($entries) ? $entries : [] as $entry) {
        $query = radar_feed_text(radar_xml_text($entry, 'title'), 200);
        if ($query === '') {
            continue;
        }
        $news = [];
        foreach (radar_xml_children($entry, 'news_item') as $item) {
            $url = radar_feed_safe_url(radar_xml_text($item, 'news_item_url'));
            $title = radar_feed_text(radar_xml_text($item, 'news_item_title'), 400);
            if ($url === null || $title === '') {
                continue;
            }
            $news[] = [
                'title' => $title,
                'url' => $url,
                'source' => radar_feed_text(radar_xml_text($item, 'news_item_source'), 100),
                'image' => radar_feed_image_allowed(radar_xml_text($item, 'news_item_picture')),
                'snippet' => radar_feed_text(radar_xml_text($item, 'news_item_snippet'), 300),
            ];
            if (count($news) >= 6) {
                break;
            }
        }
        $trends[mb_strtolower($query)] = [
            'rank' => count($trends) + 1,
            'query' => $query,
            'traffic' => radar_feed_text(radar_xml_text($entry, 'approx_traffic'), 40) ?: null,
            'started_at' => radar_feed_date(radar_xml_text($entry, 'pubDate')),
            'news' => $news,
        ];
        if (count($trends) >= 25) {
            break;
        }
    }
    return array_values($trends);
}
