<?php
declare(strict_types=1);

/** Read/write access to collected news: filters, ranking views, saved selection, dashboard stats. */

const RADAR_NEWS_WINDOWS = [1, 3, 6, 24];

/**
 * `category` is a comma-separated list of categories (one or several); `categories` is the same as an array.
 *
 * @return array{window:int,sort:string,category:string,categories:array<int,string>,q:string,saved:bool,with_image:bool,grouped:bool,limit:int,page:int}
 */
function radar_news_filters(array $input): array
{
    $window = (int)radar_str($input['janela'] ?? $input['window'] ?? 0);
    $sort = radar_str($input['ordem'] ?? $input['sort'] ?? 'viral');
    $rawCategory = $input['categoria'] ?? $input['category'] ?? '';
    $asked = is_array($rawCategory) ? array_map('radar_str', $rawCategory) : array_map('trim', explode(',', radar_str($rawCategory)));
    $categories = array_values(array_filter(radar_category_list(), static fn(string $c): bool => in_array($c, $asked, true)));
    $q = trim(radar_str($input['q'] ?? ''));
    return [
        'window' => in_array($window, RADAR_NEWS_WINDOWS, true) ? $window : 0,
        'sort' => in_array($sort, ['viral', 'recent'], true) ? $sort : 'viral',
        'category' => implode(',', $categories),
        'categories' => $categories,
        'q' => mb_substr($q, 0, 100),
        'saved' => in_array(radar_str($input['salvas'] ?? $input['saved'] ?? ''), ['1', 'true'], true),
        'with_image' => in_array(radar_str($input['com_imagem'] ?? $input['with_image'] ?? ''), ['1', 'true'], true),
        // One card per story (cluster) unless explicitly disabled.
        'grouped' => !in_array(radar_str($input['agrupar'] ?? $input['grouped'] ?? '1'), ['0', 'false'], true),
        'limit' => max(1, min(100, (int)radar_str($input['limite'] ?? $input['limit'] ?? 24))),
        'page' => max(1, min(200, (int)radar_str($input['pagina'] ?? $input['page'] ?? 1))),
    ];
}

function radar_like(string $text): string
{
    return '%' . strtr($text, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
}

/**
 * WHERE clause shared by the list and the category counts.
 *
 * @return array{0:string,1:array<int,mixed>} condition and its parameters
 */
function radar_news_where(array $filters, int $now, bool $withCategory): array
{
    $effective = 'COALESCE(published_at, first_seen_at)';
    $where = ['1 = 1'];
    $params = [];
    if ($filters['window'] > 0) {
        $where[] = $effective . ' >= ?';
        $params[] = $now - $filters['window'] * 3600;
    } else {
        // Without an explicit window, stay inside the ranked horizon (72 h).
        $where[] = $effective . ' >= ?';
        $params[] = $now - 72 * 3600;
    }
    if ($withCategory && $filters['categories'] !== []) {
        $where[] = 'category IN (' . implode(', ', array_fill(0, count($filters['categories']), '?')) . ')';
        array_push($params, ...$filters['categories']);
    }
    if ($filters['q'] !== '') {
        $where[] = '(title LIKE ? ESCAPE \'\\\' OR source_name LIKE ? ESCAPE \'\\\' OR summary LIKE ? ESCAPE \'\\\')';
        $like = radar_like($filters['q']);
        array_push($params, $like, $like, $like);
    }
    if ($filters['saved']) {
        $where[] = 'saved = 1';
    }
    if ($filters['with_image']) {
        $where[] = 'image_url IS NOT NULL';
    }
    return [implode(' AND ', $where), $params];
}

/**
 * Stories per category under the current filters. The category filter itself is ignored, so every
 * category button shows how many stories it would bring.
 *
 * @return array<string,int>
 */
function radar_news_category_counts(array $filters, ?int $now = null): array
{
    [$condition, $params] = radar_news_where($filters, $now ?? time(), false);
    $rows = $filters['grouped']
        ? radar_db_all('SELECT category, COUNT(DISTINCT COALESCE(cluster_id, -id)) AS total FROM news WHERE ' . $condition . ' GROUP BY category', $params)
        : radar_db_all('SELECT category, COUNT(*) AS total FROM news WHERE ' . $condition . ' GROUP BY category', $params);
    $counts = [];
    foreach ($rows as $row) {
        $counts[(string)$row['category']] = (int)$row['total'];
    }
    return $counts;
}

/**
 * @return array{items:array<int,array<string,mixed>>,total:int,filters:array<string,mixed>}
 */
function radar_news_query(array $filters, ?int $now = null): array
{
    $now ??= time();
    $effective = 'COALESCE(published_at, first_seen_at)';
    [$condition, $params] = radar_news_where($filters, $now, true);
    $order = $filters['sort'] === 'recent' ? $effective . ' DESC, score DESC' : 'score DESC, ' . $effective . ' DESC';
    $offset = ($filters['page'] - 1) * $filters['limit'];
    if ($filters['grouped']) {
        // Keep the strongest headline of each story; the others stay reachable via "same story".
        $total = (int)radar_db_value('SELECT COUNT(DISTINCT COALESCE(cluster_id, -id)) FROM news WHERE ' . $condition, $params);
        $rows = radar_db_all(
            'SELECT * FROM (SELECT news.*, ROW_NUMBER() OVER (PARTITION BY COALESCE(cluster_id, -id) ORDER BY score DESC, ' . $effective . ' ASC) AS rn FROM news WHERE ' . $condition . ')
             WHERE rn = 1 ORDER BY ' . $order . ' LIMIT ' . (int)$filters['limit'] . ' OFFSET ' . (int)$offset,
            $params
        );
    } else {
        $total = (int)radar_db_value('SELECT COUNT(*) FROM news WHERE ' . $condition, $params);
        $rows = radar_db_all('SELECT * FROM news WHERE ' . $condition . ' ORDER BY ' . $order . ' LIMIT ' . (int)$filters['limit'] . ' OFFSET ' . (int)$offset, $params);
    }
    return ['items' => array_map(static fn(array $row): array => radar_news_decode($row, $now), $rows), 'total' => $total, 'filters' => $filters];
}

/** Decode JSON columns and add display fields. */
function radar_news_decode(array $row, ?int $now = null): array
{
    $now ??= time();
    $reasons = json_decode((string)$row['score_reasons'], true);
    $time = $row['published_at'] !== null ? (int)$row['published_at'] : (int)$row['first_seen_at'];
    return [
        'id' => (int)$row['id'],
        'title' => (string)$row['title'],
        'url' => (string)$row['url'],
        'source' => (string)$row['source_name'],
        'category' => (string)$row['category'],
        'summary' => (string)$row['summary'],
        'summary_origin' => $row['summary'] === '' ? null : 'Texto fornecido pelo próprio veículo (feed ou metadados da página).',
        'published_at' => $row['published_at'] !== null ? gmdate(DATE_ATOM, (int)$row['published_at']) : null,
        'published_known' => $row['published_at'] !== null,
        'first_seen_at' => gmdate(DATE_ATOM, (int)$row['first_seen_at']),
        'age_minutes' => max(0, (int)round(($now - $time) / 60)),
        'image' => $row['image_url'],
        'image_credit' => $row['image_credit'],
        'image_note' => $row['image_note'],
        'score' => (int)$row['score'],
        'score_note' => 'Estimativa própria do Radar Viral BR, não é audiência medida nem ranking oficial do Google Trends.',
        'score_reasons' => is_array($reasons) ? ($reasons['reasons'] ?? []) : [],
        'score_summary' => is_array($reasons) ? ($reasons['summary'] ?? '') : '',
        'source_count' => (int)$row['source_count'],
        'trend_hits' => json_decode((string)$row['trend_hits'], true) ?: [],
        'trend_origin' => $row['trend_origin'] ?? null,
        'related' => json_decode((string)$row['related'], true) ?: [],
        'cluster_id' => $row['cluster_id'] !== null ? (int)$row['cluster_id'] : null,
        'saved' => (int)$row['saved'] === 1,
        'note' => (string)$row['note'],
        'verification' => 'Conferência editorial pendente: o Radar lê manchetes e metadados, não verifica fatos.',
    ];
}

/** @return array<string,mixed>|null */
function radar_news_get(int $id): ?array
{
    $row = radar_db_one('SELECT * FROM news WHERE id = ?', [$id]);
    if ($row === null) {
        return null;
    }
    $news = radar_news_decode($row);
    $news['same_story'] = [];
    if ($row['cluster_id'] !== null) {
        foreach (radar_db_all('SELECT id, title, url, source_name, published_at, image_url FROM news WHERE cluster_id = ? AND id <> ? ORDER BY COALESCE(published_at, first_seen_at) ASC LIMIT 10', [$row['cluster_id'], $id]) as $mate) {
            $news['same_story'][] = [
                'id' => (int)$mate['id'], 'title' => $mate['title'], 'url' => $mate['url'], 'source' => $mate['source_name'],
                'published_at' => $mate['published_at'] !== null ? gmdate(DATE_ATOM, (int)$mate['published_at']) : null,
                'image' => $mate['image_url'],
            ];
        }
    }
    // Earliest dated headline in the cluster hints at the original source; it is a hint, not proof.
    $dated = array_values(array_filter($news['same_story'], static fn(array $m): bool => $m['published_at'] !== null));
    $news['earliest_in_cluster'] = null;
    if ($dated !== [] && ($news['published_at'] === null || strcmp($dated[0]['published_at'], $news['published_at']) < 0)) {
        $news['earliest_in_cluster'] = $dated[0];
    }
    $news['media'] = radar_db_all('SELECT id, kind, mime, width, height, label, credit, note, source_url, created_at FROM media WHERE news_id = ? ORDER BY created_at DESC', [$id]);
    return $news;
}

function radar_news_set_saved(int $id, bool $saved, ?string $note = null): bool
{
    $changed = radar_db_exec(
        'UPDATE news SET saved = ?, saved_at = ?, note = COALESCE(?, note) WHERE id = ?',
        [$saved ? 1 : 0, $saved ? time() : null, $note === null ? null : mb_substr($note, 0, 1000), $id]
    );
    return $changed > 0;
}

function radar_news_delete(int $id): bool
{
    return radar_db_exec('DELETE FROM news WHERE id = ?', [$id]) > 0;
}

/** Current Google Trends snapshot (latest collection). @return array<int,array<string,mixed>> */
function radar_trends_latest(): array
{
    $rows = radar_db_all('SELECT rank, query, traffic, related, fetched_at FROM trends WHERE fetched_at = (SELECT MAX(fetched_at) FROM trends) ORDER BY rank');
    return array_map(static function (array $row): array {
        return [
            'rank' => (int)$row['rank'], 'query' => $row['query'], 'traffic' => $row['traffic'],
            'articles' => json_decode((string)$row['related'], true) ?: [],
            'collected_at' => gmdate(DATE_ATOM, (int)$row['fetched_at']),
        ];
    }, $rows);
}

/** Real counters only: everything comes from stored rows. @return array<string,mixed> */
function radar_dashboard_stats(?int $now = null): array
{
    $now ??= time();
    $startOfDay = strtotime('today', $now) ?: $now - 86400;
    $lastRun = radar_db_one('SELECT * FROM collection_runs ORDER BY id DESC LIMIT 1');
    $lastOk = radar_db_one("SELECT finished_at FROM collection_runs WHERE status IN ('ok','partial') ORDER BY id DESC LIMIT 1");
    return [
        'collected_today' => (int)radar_db_value('SELECT COUNT(*) FROM news WHERE first_seen_at >= ?', [$startOfDay]),
        'new_last_3h' => (int)radar_db_value('SELECT COUNT(*) FROM news WHERE first_seen_at >= ?', [$now - 3 * 3600]),
        'saved' => (int)radar_db_value('SELECT COUNT(*) FROM news WHERE saved = 1'),
        'reacts_prepared' => (int)radar_db_value('SELECT COUNT(*) FROM scripts'),
        'projects' => (int)radar_db_value('SELECT COUNT(*) FROM projects'),
        'total_news' => (int)radar_db_value('SELECT COUNT(*) FROM news'),
        'active_sources' => (int)radar_db_value('SELECT COUNT(*) FROM sources WHERE active = 1'),
        'last_collect_at' => $lastOk !== null ? (int)$lastOk['finished_at'] : null,
        'last_run' => $lastRun === null ? null : [
            'id' => (int)$lastRun['id'], 'status' => $lastRun['status'], 'started_at' => (int)$lastRun['started_at'],
            'message' => $lastRun['message'], 'trigger' => $lastRun['trigger_kind'],
            'sources_ok' => (int)$lastRun['sources_ok'], 'sources_failed' => (int)$lastRun['sources_failed'],
        ],
    ];
}

/** True when the last successful collection is older than the automatic interval (30 min). */
function radar_collect_is_stale(?int $now = null, int $seconds = 1800): bool
{
    $now ??= time();
    $last = radar_setting('last_collect_at');
    return $last === null || $now - (int)$last >= $seconds;
}

function radar_age_label(int $minutes): string
{
    if ($minutes < 1) {
        return 'agora';
    }
    if ($minutes < 60) {
        return 'há ' . $minutes . ' min';
    }
    if ($minutes < 1440) {
        return 'há ' . intdiv($minutes, 60) . ' h';
    }
    return 'há ' . intdiv($minutes, 1440) . ' d';
}
