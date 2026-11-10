<?php
declare(strict_types=1);

/**
 * News collection: fetch active sources concurrently, store, classify, cluster and rank.
 * Failures are recorded per source and shown in the history; nothing is fabricated.
 */

function radar_collect_lock_path(): string
{
    return rtrim((string)radar_config()['storage_path'], '/\\') . '/collect.lock';
}

/** Run a collection. Returns a summary, or ['status' => 'busy'] when another run holds the lock. */
function radar_collect(string $trigger = 'manual', ?int $ogBudget = 12): array
{
    $lockDirectory = dirname(radar_collect_lock_path());
    if (!is_dir($lockDirectory)) {
        @mkdir($lockDirectory, 0700, true);
    }
    $lock = @fopen(radar_collect_lock_path(), 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        return ['status' => 'busy', 'message' => 'Outra coleta já está em andamento.'];
    }
    @chmod(radar_collect_lock_path(), 0600);
    try {
        return radar_collect_locked($trigger, $ogBudget);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function radar_collect_locked(string $trigger, ?int $ogBudget): array
{
    @set_time_limit(120);
    radar_sources_seed();
    $now = time();
    $sources = radar_db_all('SELECT * FROM sources WHERE active = 1 ORDER BY id');
    $runId = radar_db_insert('INSERT INTO collection_runs (started_at, trigger_kind, status) VALUES (?, ?, ?)', [$now, $trigger, 'running']);
    if ($sources === []) {
        radar_db_exec('UPDATE collection_runs SET finished_at = ?, status = ?, message = ? WHERE id = ?', [time(), 'error', 'Nenhuma fonte ativa. Ative ao menos uma fonte.', $runId]);
        return ['status' => 'error', 'run_id' => $runId, 'message' => 'Nenhuma fonte ativa. Ative ao menos uma fonte.', 'sources_ok' => 0, 'sources_failed' => 0, 'items_seen' => 0, 'items_new' => 0];
    }
    $requests = [];
    foreach ($sources as $source) {
        $headers = ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml, */*;q=0.5'];
        if (!empty($source['etag'])) {
            $headers[] = 'If-None-Match: ' . $source['etag'];
        }
        if (!empty($source['last_modified'])) {
            $headers[] = 'If-Modified-Since: ' . $source['last_modified'];
        }
        $requests['s' . $source['id']] = ['url' => $source['url'], 'headers' => $headers, 'max_bytes' => 3_000_000, 'timeout' => 20];
    }
    $responses = radar_net_fetch_many($requests);

    $ok = 0;
    $failed = 0;
    $seen = 0;
    $new = 0;
    foreach ($sources as $source) {
        $response = $responses['s' . $source['id']];
        $count = 0;
        $fresh = 0;
        $error = $response['error'];
        $status = 'ok';
        if ($error === null && $response['status'] === 304) {
            $status = 'unchanged';
        } elseif ($error === null && $response['status'] !== 200) {
            $error = 'A fonte respondeu HTTP ' . $response['status'] . '.';
        }
        if ($error === null && $status === 'ok') {
            try {
                if ($source['kind'] === 'trends') {
                    [$count, $fresh] = radar_store_trends(radar_parse_trends($response['body']), $runId, $source, $now);
                } else {
                    $items = radar_parse_feed($response['body'], (string)$source['name']);
                    if ($items === []) {
                        throw new RuntimeException('O feed não trouxe nenhuma matéria válida.');
                    }
                    [$count, $fresh] = radar_store_items($items, $source, $now);
                }
            } catch (Throwable $parseError) {
                $error = $parseError instanceof RuntimeException ? $parseError->getMessage() : 'Falha ao processar o feed.';
            }
        }
        if ($error !== null) {
            $status = 'error';
            $failed++;
        } else {
            $ok++;
        }
        $seen += $count;
        $new += $fresh;
        radar_db_exec(
            'INSERT INTO collection_run_sources (run_id, source_id, source_name, status, http_status, items, new_items, error, duration_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$runId, $source['id'], $source['name'], $status, $response['status'] ?: null, $count, $fresh, $error, $response['ms']]
        );
        radar_db_exec(
            'UPDATE sources SET last_status = ?, last_error = ?, last_http = ?, last_fetched_at = ?, last_success_at = CASE WHEN ? = 0 THEN ? ELSE last_success_at END, last_item_count = CASE WHEN ? = 0 AND ? > 0 THEN ? ELSE last_item_count END, etag = CASE WHEN ? = 0 THEN ? ELSE etag END, last_modified = CASE WHEN ? = 0 THEN ? ELSE last_modified END WHERE id = ?',
            [
                $status, $error, $response['status'] ?: null, $now,
                $error === null ? 0 : 1, $now,
                $error === null ? 0 : 1, $count, $count,
                $error === null && $status === 'ok' ? 0 : 1, $response['headers']['etag'] ?? null,
                $error === null && $status === 'ok' ? 0 : 1, $response['headers']['last-modified'] ?? null,
                $source['id'],
            ]
        );
    }

    $ranked = radar_rank_recompute();
    $og = ['checked' => 0, 'images' => 0];
    if ($ogBudget !== null && $ogBudget > 0) {
        $candidates = radar_db_all(
            'SELECT id, url, image_url, image_note, summary, source_name FROM news
             WHERE og_checked_at IS NULL AND COALESCE(published_at, first_seen_at) >= ? AND (image_url IS NULL OR image_note LIKE \'Imagem de matéria relacionada%\')
             ORDER BY score DESC LIMIT ' . (int)$ogBudget,
            [$now - 86400]
        );
        try {
            $og = radar_og_enrich($candidates);
            if ($og['images'] > 0) {
                radar_rank_recompute();
            }
        } catch (Throwable $error) {
            error_log('Radar OG enrichment failed: ' . get_class($error));
        }
    }
    radar_collect_purge();

    $statusFinal = $failed === 0 ? 'ok' : ($ok === 0 ? 'error' : 'partial');
    $message = sprintf('%d de %d fontes responderam; %d matérias vistas, %d novas; %d imagens Open Graph.', $ok, count($sources), $seen, $new, $og['images']);
    radar_db_exec(
        'UPDATE collection_runs SET finished_at = ?, status = ?, sources_ok = ?, sources_failed = ?, items_seen = ?, items_new = ?, message = ? WHERE id = ?',
        [time(), $statusFinal, $ok, $failed, $seen, $new, $message, $runId]
    );
    radar_setting_set('last_collect_at', (string)time());
    return ['status' => $statusFinal, 'run_id' => $runId, 'message' => $message, 'sources_ok' => $ok, 'sources_failed' => $failed, 'items_seen' => $seen, 'items_new' => $new, 'ranked' => $ranked, 'og' => $og];
}

/**
 * @param array<int,array<string,mixed>> $items
 * @return array{0:int,1:int} [seen, new]
 */
function radar_store_items(array $items, array $source, int $now): array
{
    $pdo = radar_db();
    $new = 0;
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        foreach ($items as $item) {
            $new += radar_store_one($item, $source, $now) ? 1 : 0;
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
    return [count($items), $new];
}

/** Insert or refresh one story. Returns true when it was new. */
function radar_store_one(array $item, array $source, int $now, ?string $trendOrigin = null): bool
{
    $existing = radar_db_one('SELECT id, image_url, summary, published_at FROM news WHERE uid = ?', [$item['uid']]);
    if ($existing === null) {
        $category = radar_classify((string)$item['title'], (string)$item['summary'], (string)$source['category'], $trendOrigin !== null);
        radar_db_exec(
            'INSERT INTO news (uid, source_id, source_name, title, url, summary, published_at, first_seen_at, last_seen_at, category, image_url, image_credit, image_note, related, trend_origin)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $item['uid'], $source['id'], $item['source_name'], $item['title'], $item['url'], $item['summary'],
                $item['published_at'], $now, $now, $category, $item['image_url'], $item['image_credit'], $item['image_note'],
                json_encode($item['related'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $trendOrigin,
            ]
        );
        return true;
    }
    // Existing story: refresh liveness and fill gaps only; never overwrite curated data.
    radar_db_exec(
        'UPDATE news SET last_seen_at = ?, summary = CASE WHEN summary = \'\' THEN ? ELSE summary END, published_at = COALESCE(published_at, ?),
         image_url = COALESCE(image_url, ?), image_credit = CASE WHEN image_url IS NULL THEN ? ELSE image_credit END,
         image_note = CASE WHEN image_url IS NULL THEN ? ELSE image_note END, trend_origin = COALESCE(trend_origin, ?) WHERE id = ?',
        [$now, $item['summary'], $item['published_at'], $item['image_url'], $item['image_credit'], $item['image_note'], $trendOrigin, $existing['id']]
    );
    return false;
}

/**
 * Persist the trends snapshot and turn each trend's associated articles into news rows,
 * which carry direct article URLs (needed for Open Graph and screenshots).
 *
 * @param array<int,array<string,mixed>> $trends
 * @return array{0:int,1:int}
 */
function radar_store_trends(array $trends, int $runId, array $source, int $now): array
{
    if ($trends === []) {
        throw new RuntimeException('O Google Trends não devolveu buscas em alta.');
    }
    $pdo = radar_db();
    $new = 0;
    $seen = 0;
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        foreach ($trends as $trend) {
            radar_db_exec(
                'INSERT INTO trends (run_id, rank, query, traffic, url, related, fetched_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                [$runId, $trend['rank'], $trend['query'], $trend['traffic'], RADAR_TRENDS_URL, json_encode($trend['news'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $now]
            );
            foreach ($trend['news'] as $article) {
                $item = [
                    'uid' => hash('sha256', radar_feed_canonical_url($article['url'])),
                    'title' => $article['title'],
                    'url' => $article['url'],
                    'published_at' => null, // Trends gives the trend's start, not each article's publication time.
                    'source_name' => $article['source'] !== '' ? $article['source'] : 'Fonte não informada',
                    'summary' => $article['snippet'],
                    'image_url' => $article['image'],
                    'image_credit' => $article['image'] === null ? null : ($article['source'] ?: null),
                    'image_note' => $article['image'] === null ? 'O Google Trends não forneceu imagem.' : 'Miniatura indicada pelo Google Trends para esta matéria; confira os direitos antes de reutilizar.',
                    'related' => [],
                ];
                $seen++;
                $new += radar_store_one($item, $source, $now, $trend['query']) ? 1 : 0;
            }
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
    return [$seen, $new];
}

/**
 * Re-cluster and re-score recent stories (recency changes every run).
 * Borrowed images are labelled; nothing is presented as the story's own cover.
 */
function radar_rank_recompute(int $windowHours = 72): int
{
    $now = time();
    $since = $now - $windowHours * 3600;
    $rows = radar_db_all(
        'SELECT n.id, n.title, n.summary, n.category, n.published_at, n.first_seen_at, n.image_url, n.image_credit, n.image_note, n.source_name, n.trend_origin, COALESCE(s.weight, 1.0) AS source_weight
         FROM news n LEFT JOIN sources s ON s.id = n.source_id
         WHERE COALESCE(n.published_at, n.first_seen_at) >= ?
         ORDER BY n.id',
        [$since]
    );
    if ($rows === []) {
        return 0;
    }
    $clusterMap = radar_rank_clusters(array_map(static fn(array $r): array => ['id' => (int)$r['id'], 'title' => (string)$r['title']], $rows));
    $publishers = [];
    $imageDonor = [];
    foreach ($rows as $row) {
        $cluster = $clusterMap[(int)$row['id']];
        $publishers[$cluster][radar_publisher_key((string)$row['source_name'])] = true;
        if (!empty($row['image_url']) && !str_starts_with((string)$row['image_note'], 'Imagem de matéria relacionada') && !isset($imageDonor[$cluster])) {
            $imageDonor[$cluster] = $row;
        }
    }
    // One story, one category: auto-classified members adopt the cluster's most common
    // specific category (never override a specific category with a generic one).
    $generic = ['Geral' => true, 'Assuntos virais' => true];
    $votes = [];
    foreach ($rows as $row) {
        if (!isset($generic[$row['category']])) {
            $cluster = $clusterMap[(int)$row['id']];
            $votes[$cluster][$row['category']] = ($votes[$cluster][$row['category']] ?? 0) + 1;
        }
    }
    foreach ($rows as $position => $row) {
        $cluster = $clusterMap[(int)$row['id']];
        if (isset($generic[$row['category']]) && isset($votes[$cluster])) {
            arsort($votes[$cluster]);
            $rows[$position]['category'] = (string)array_key_first($votes[$cluster]);
        }
    }
    $trendRows = radar_db_all('SELECT rank, query, traffic FROM trends WHERE fetched_at = (SELECT MAX(fetched_at) FROM trends) ORDER BY rank');
    $trends = array_map(static fn(array $t): array => $t + ['tokens' => radar_rank_tokens((string)$t['query'])], $trendRows);
    $pdo = radar_db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $update = $pdo->prepare('UPDATE news SET cluster_id = ?, source_count = ?, score = ?, score_reasons = ?, trend_hits = ?, image_url = ?, image_credit = ?, image_note = ?, category = ? WHERE id = ?');
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $cluster = $clusterMap[$id];
            $image = $row['image_url'];
            $credit = $row['image_credit'];
            $note = $row['image_note'];
            if (empty($image) && isset($imageDonor[$cluster])) {
                $donor = $imageDonor[$cluster];
                $image = $donor['image_url'];
                $credit = $donor['source_name'];
                $note = 'Imagem de matéria relacionada (' . $donor['source_name'] . ') sobre o mesmo assunto, não desta matéria; confira antes de usar.';
            }
            $scored = radar_score_item($row + ['source_count' => count($publishers[$cluster]), 'image_url' => $image], ['now' => $now, 'trends' => $trends]);
            radar_db_bind($update, [
                $cluster, count($publishers[$cluster]), $scored['score'],
                json_encode(['reasons' => $scored['reasons'], 'summary' => $scored['summary']], JSON_UNESCAPED_UNICODE),
                json_encode($scored['trend_hits'], JSON_UNESCAPED_UNICODE), $image, $credit, $note, (string)$row['category'], $id,
            ]);
            $update->execute();
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $error) {
        $pdo->exec('ROLLBACK');
        throw $error;
    }
    return count($rows);
}

/** Keep the database bounded. Saved stories and anything referenced by a project survive. */
function radar_collect_purge(): void
{
    $now = time();
    radar_db_exec(
        'DELETE FROM news WHERE saved = 0 AND last_seen_at < ? AND id NOT IN (SELECT news_id FROM projects WHERE news_id IS NOT NULL) AND id NOT IN (SELECT news_id FROM scripts WHERE news_id IS NOT NULL)',
        [$now - 14 * 86400]
    );
    radar_db_exec('DELETE FROM trends WHERE fetched_at < ?', [$now - 7 * 86400]);
    radar_db_exec('DELETE FROM collection_runs WHERE started_at < ?', [$now - 30 * 86400]);
}
