<?php
declare(strict_types=1);

/** Open Graph extraction from a publisher's own article page (head only, size-capped). */

/**
 * @return array{image:?string,title:?string,description:?string,site:?string}
 */
function radar_og_extract(string $html, string $pageUrl): array
{
    $head = substr($html, 0, 400_000);
    $end = stripos($head, '</head>');
    if ($end !== false) {
        $head = substr($head, 0, $end);
    }
    $values = [];
    if (preg_match_all('/<meta\b[^>]*>/i', $head, $tags)) {
        foreach ($tags[0] as $tag) {
            if (!preg_match('/\b(?:property|name)\s*=\s*(["\'])(.*?)\1/i', $tag, $key)
                || !preg_match('/\bcontent\s*=\s*(["\'])(.*?)\1/is', $tag, $content)) {
                continue;
            }
            $values[strtolower(trim($key[2]))][] = html_entity_decode($content[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
    $first = static function (array $keys) use ($values): ?string {
        foreach ($keys as $key) {
            foreach ($values[$key] ?? [] as $value) {
                $value = trim($value);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return null;
    };
    $image = $first(['og:image:secure_url', 'og:image', 'og:image:url', 'twitter:image', 'twitter:image:src']);
    if ($image !== null) {
        $image = radar_feed_image_allowed(radar_net_join($pageUrl, $image));
    }
    $title = $first(['og:title', 'twitter:title']);
    $description = $first(['og:description', 'description', 'twitter:description']);
    return [
        'image' => $image,
        'title' => $title === null ? null : radar_feed_text($title, 300),
        'description' => $description === null ? null : radar_feed_text($description, 500),
        'site' => ($site = $first(['og:site_name'])) === null ? null : radar_feed_text($site, 100),
    ];
}

/** Google News links are opaque redirects: their og:image would be Google's, not the story's. */
function radar_og_fetchable(string $url): bool
{
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return $host !== '' && $host !== 'news.google.com' && !str_ends_with($host, '.google.com');
}

/**
 * Look up Open Graph data for several stored news rows at once.
 *
 * @param array<int,array<string,mixed>> $rows news rows (id, url, image_url, summary, source_name)
 * @return array{checked:int,images:int}
 */
function radar_og_enrich(array $rows): array
{
    $requests = [];
    $byId = [];
    foreach ($rows as $row) {
        if (!radar_og_fetchable((string)$row['url'])) {
            radar_db_exec('UPDATE news SET og_checked_at = ? WHERE id = ?', [time(), $row['id']]);
            continue;
        }
        $requests['n' . $row['id']] = ['url' => $row['url'], 'headers' => ['Accept: text/html,application/xhtml+xml'], 'max_bytes' => 300_000, 'timeout' => 12, 'truncate_ok' => true];
        $byId['n' . $row['id']] = $row;
    }
    if ($requests === []) {
        return ['checked' => 0, 'images' => 0];
    }
    $responses = radar_net_fetch_many($requests);
    $images = 0;
    foreach ($responses as $key => $response) {
        $row = $byId[$key];
        $update = ['og_checked_at' => time()];
        if ($response['error'] === null && $response['status'] === 200 && $response['body'] !== '') {
            $og = radar_og_extract($response['body'], $response['url']);
            $borrowed = str_starts_with((string)($row['image_note'] ?? ''), 'Imagem de matéria relacionada');
            if ($og['image'] !== null && (empty($row['image_url']) || $borrowed)) {
                $update['image_url'] = $og['image'];
                $update['image_credit'] = $row['source_name'];
                $update['image_note'] = 'Imagem Open Graph da própria página da matéria; confira os direitos antes de reutilizar.';
                $images++;
            }
            if (($row['summary'] ?? '') === '' && $og['description'] !== null && mb_strlen($og['description']) >= 25) {
                $update['summary'] = $og['description'];
            }
        }
        $sets = [];
        $params = [];
        foreach ($update as $column => $value) {
            $sets[] = $column . ' = ?';
            $params[] = $value;
        }
        $params[] = $row['id'];
        radar_db_exec('UPDATE news SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }
    return ['checked' => count($requests), 'images' => $images];
}
