<?php
declare(strict_types=1);

/**
 * Real job openings from the Adzuna API (https://developer.adzuna.com), country Brazil. The person registers for a free
 * app id and app key, saves them in the panel (encrypted) and the panel fetches the latest openings state by state,
 * in small batches, inside a daily budget of calls.
 *
 * The openings are kept as a short-lived copy (they disappear 14 days after Adzuna stops returning them), every one
 * links to Adzuna and is labelled "Jobs by Adzuna" as its terms ask. Nothing here invents an opening, a place or a salary.
 */

const RADAR_ADZUNA_COUNTRY = 'br';
/** States fetched per batch (one call each). 27 states = 3 batches. */
const RADAR_ADZUNA_BATCH = 9;
/** Openings older than this (days) are not asked for; openings not seen again for this long are removed. */
const RADAR_ADZUNA_KEEP_DAYS = 14;

/** @return array{id:string,key:string}|null */
function radar_adzuna_keys(): ?array
{
    $id = radar_secret_exists('adzuna_app_id') ? (string)radar_secret_get('adzuna_app_id') : (string)(getenv('RADAR_ADZUNA_APP_ID') ?: '');
    $key = radar_secret_exists('adzuna_app_key') ? (string)radar_secret_get('adzuna_app_key') : (string)(getenv('RADAR_ADZUNA_APP_KEY') ?: '');
    return $id !== '' && $key !== '' ? ['id' => $id, 'key' => $key] : null;
}

function radar_adzuna_configured(): bool
{
    return radar_adzuna_keys() !== null;
}

/** Save the credentials (encrypted) and the daily limit of calls. */
function radar_adzuna_save(string $appId, string $appKey, int $dailyLimit): void
{
    $appId = trim($appId);
    $appKey = trim($appKey);
    if (preg_match('/\A[A-Za-z0-9]{4,64}\z/D', $appId) !== 1 || preg_match('/\A[A-Za-z0-9]{8,128}\z/D', $appKey) !== 1) {
        throw new InvalidArgumentException('O app id e a app key da Adzuna têm só letras e números, sem espaços. Copie os dois do painel developer.adzuna.com.');
    }
    if ($dailyLimit < 10 || $dailyLimit > 500) {
        throw new InvalidArgumentException('O limite diário de chamadas fica entre 10 e 500.');
    }
    radar_secret_set('adzuna_app_id', $appId);
    radar_secret_set('adzuna_app_key', $appKey);
    radar_setting_set('adzuna_daily_limit', (string)$dailyLimit);
}

function radar_adzuna_forget(): void
{
    radar_secret_delete('adzuna_app_id');
    radar_secret_delete('adzuna_app_key');
}

function radar_adzuna_daily_limit(): int
{
    return max(10, min(500, (int)radar_setting('adzuna_daily_limit', '60')));
}

function radar_adzuna_calls_today(): int
{
    return (int)radar_setting('adzuna_calls_' . date('Ymd'), '0');
}

/** Never let a message carry the credentials. */
function radar_adzuna_mask(string $text): string
{
    $keys = radar_adzuna_keys();
    if ($keys !== null) {
        $text = str_replace([$keys['id'], $keys['key']], '[oculto]', $text);
    }
    $text = preg_replace('/(app_(?:id|key)=)[^&\s]+/i', '$1[oculto]', $text) ?? $text;
    return mb_substr($text, 0, 240);
}

/** Pause between calls (the API allows a few per second at most; the panel keeps well under). */
function radar_adzuna_pause(): void
{
    if (!isset($GLOBALS['radar_test_transport'])) {
        usleep(2_600_000);
    }
}

/**
 * One search call (counts against the daily budget). Returns the decoded answer.
 *
 * @param array<string,scalar> $params what, where, results_per_page, max_days_old, sort_by...
 * @return array<string,mixed>
 */
function radar_adzuna_request(array $params, int $page = 1): array
{
    $keys = radar_adzuna_keys();
    if ($keys === null) {
        throw new RuntimeException('Cadastre o app id e a app key da Adzuna primeiro.');
    }
    radar_setting_set('adzuna_calls_' . date('Ymd'), (string)(radar_adzuna_calls_today() + 1));
    $query = http_build_query(array_merge(['results_per_page' => 50, 'sort_by' => 'date', 'max_days_old' => RADAR_ADZUNA_KEEP_DAYS, 'content-type' => 'application/json'], $params, ['app_id' => $keys['id'], 'app_key' => $keys['key']]));
    $response = radar_net_get('https://api.adzuna.com/v1/api/jobs/' . RADAR_ADZUNA_COUNTRY . '/search/' . max(1, $page) . '?' . $query, ['headers' => ['Accept: application/json'], 'timeout' => 25, 'max_bytes' => 3_000_000]);
    if ($response['error'] !== null) {
        throw new RuntimeException('Falha ao falar com a Adzuna: ' . radar_adzuna_mask($response['error']));
    }
    $decoded = json_decode($response['body'], true);
    if ($response['status'] === 200 && is_array($decoded) && isset($decoded['results']) && is_array($decoded['results'])) {
        return $decoded;
    }
    $detail = is_array($decoded) ? radar_adzuna_mask((string)($decoded['display'] ?? $decoded['exception'] ?? '')) : '';
    throw new RuntimeException(match (true) {
        in_array($response['status'], [401, 403], true) => 'A Adzuna recusou a chave (app id ou app key errados, ou conta ainda não ativada).',
        $response['status'] === 429 => 'Limite de chamadas da Adzuna atingido. Tente mais tarde.',
        $response['status'] === 400 => 'A Adzuna recusou o pedido' . ($detail !== '' ? ': ' . $detail : '') . '.',
        $response['status'] >= 500 => 'A Adzuna está indisponível agora.',
        default => 'Resposta inesperada da Adzuna (HTTP ' . $response['status'] . ').',
    });
}

/** The UF of a place name that is a state, or ''. */
function radar_adzuna_state_of(string $name): string
{
    $folded = radar_fold($name);
    foreach (radar_job_states() as $uf => $info) {
        if ($folded === radar_fold($info['name'])) {
            return $uf;
        }
    }
    return '';
}

/**
 * One result of the API as an opening of the panel, or null when it has no usable id, title or link.
 *
 * @param array<string,mixed> $result
 * @return array<string,mixed>|null
 */
function radar_adzuna_normalize(array $result): ?array
{
    $clean = static fn(mixed $text, int $max): string => mb_substr(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? ''), 0, $max);
    $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($result['id'] ?? '')) ?? '';
    $title = $clean($result['title'] ?? '', 200);
    $url = radar_feed_safe_url((string)($result['redirect_url'] ?? ''));
    if ($id === '' || $title === '' || $url === null) {
        return null;
    }
    $location = is_array($result['location'] ?? null) ? $result['location'] : [];
    $area = array_values(array_filter(array_map(static fn(mixed $a): string => trim((string)$a), (array)($location['area'] ?? [])), static fn(string $a): bool => $a !== ''));
    $display = $clean($location['display_name'] ?? '', 120);
    $state = '';
    foreach (array_merge($area, array_map('trim', explode(',', $display))) as $part) {
        $state = $state !== '' ? $state : radar_adzuna_state_of($part);
    }
    $city = '';
    foreach (array_reverse($area) as $part) {
        if (radar_adzuna_state_of($part) === '' && !in_array(radar_fold($part), ['brasil', 'brazil'], true)) {
            $city = $clean($part, 80);
            break;
        }
    }
    if ($city === '') {
        // No usable area: the display name is "City, State" in Adzuna's answers.
        foreach (array_map('trim', explode(',', $display)) as $part) {
            if ($part !== '' && radar_adzuna_state_of($part) === '' && !in_array(radar_fold($part), ['brasil', 'brazil'], true)) {
                $city = $clean($part, 80);
                break;
            }
        }
    }
    $description = $clean($result['description'] ?? '', 600);
    // When the API gave no usable area, the text may still name the place.
    if ($state === '') {
        $read = radar_job_extract($display . ' ' . $title, $description);
        $state = $read['state'];
        $city = $city !== '' ? $city : $read['city'];
    }
    $region = $state !== '' ? radar_job_states()[$state]['region'] : '';
    $number = static fn(mixed $value): ?float => is_numeric($value) && (float)$value > 0 ? (float)$value : null;
    $created = isset($result['created']) ? strtotime((string)$result['created']) : false;
    $category = is_array($result['category'] ?? null) ? $clean($result['category']['label'] ?? '', 80) : '';
    return [
        'external_id' => $id, 'title' => $title, 'company' => $clean(is_array($result['company'] ?? null) ? ($result['company']['display_name'] ?? '') : '', 120),
        'location' => $display, 'region' => $region, 'state' => $state, 'city' => $city, 'age' => radar_job_extract($title, $description)['age'], 'category' => $category,
        'description' => $description, 'salary_min' => $number($result['salary_min'] ?? null), 'salary_max' => $number($result['salary_max'] ?? null),
        'salary_predicted' => (string)($result['salary_is_predicted'] ?? '0') === '1' ? 1 : 0, 'contract_type' => $clean($result['contract_type'] ?? '', 40), 'contract_time' => $clean($result['contract_time'] ?? '', 40),
        'url' => $url, 'created_at' => $created !== false ? $created : time(),
    ];
}

/** Insert or refresh an opening. Returns true when it was new. */
function radar_adzuna_store(array $job, int $now): bool
{
    $existed = radar_db_value("SELECT 1 FROM job_posts WHERE provider = 'adzuna' AND external_id = ?", [$job['external_id']]) !== null;
    radar_db_exec(
        "INSERT INTO job_posts (provider, external_id, title, company, location, region, state, city, age, category, description, salary_min, salary_max, salary_predicted, contract_type, contract_time, url, created_at, fetched_at)
         VALUES ('adzuna', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON CONFLICT(provider, external_id) DO UPDATE SET title = excluded.title, company = excluded.company, location = excluded.location, region = excluded.region, state = excluded.state, city = excluded.city, age = excluded.age,
            category = excluded.category, description = excluded.description, salary_min = excluded.salary_min, salary_max = excluded.salary_max, salary_predicted = excluded.salary_predicted,
            contract_type = excluded.contract_type, contract_time = excluded.contract_time, url = excluded.url, fetched_at = excluded.fetched_at",
        [$job['external_id'], $job['title'], $job['company'], $job['location'], $job['region'], $job['state'], $job['city'], $job['age'], $job['category'], $job['description'], $job['salary_min'], $job['salary_max'],
            $job['salary_predicted'], $job['contract_type'], $job['contract_time'], $job['url'], $job['created_at'], $now]
    );
    return !$existed;
}

/** What the panel shows about the connection. @return array<string,mixed> */
function radar_adzuna_status(): array
{
    $last = (int)radar_setting('adzuna_last_batch_at', '0');
    return [
        'configured' => radar_adzuna_configured(), 'key_hint' => radar_secret_exists('adzuna_app_key') ? radar_secret_hint('adzuna_app_key') : '', 'daily_limit' => radar_adzuna_daily_limit(), 'calls_today' => radar_adzuna_calls_today(),
        'last_batch_at' => $last > 0 ? $last : null, 'last_error' => (string)radar_setting('adzuna_last_error', ''), 'total' => (int)radar_db_value("SELECT COUNT(*) FROM job_posts WHERE provider = 'adzuna'"),
        'cursor' => (int)radar_setting('adzuna_cursor', '0'), 'states' => count(radar_job_states()),
    ];
}

/** True when a new batch should run now (connected, not running, budget left and the last batch was more than 2 hours ago). */
function radar_adzuna_stale(?int $now = null): bool
{
    $now ??= time();
    return radar_adzuna_configured() && $now - (int)radar_setting('adzuna_running', '0') > 180 && $now - (int)radar_setting('adzuna_last_batch_at', '0') > 7200
        && radar_adzuna_calls_today() + RADAR_ADZUNA_BATCH <= radar_adzuna_daily_limit();
}

/**
 * Fetch one batch of states (9 calls) and store the openings. The next batch continues where this one stopped.
 *
 * @return array{status:string,message:string,fetched:int,new:int,calls:int,states:array<int,string>}
 */
function radar_adzuna_refresh(?int $now = null): array
{
    $now ??= time();
    if (!radar_adzuna_configured()) {
        throw new RuntimeException('Cadastre o app id e a app key da Adzuna primeiro.');
    }
    if ($now - (int)radar_setting('adzuna_running', '0') <= 180) {
        return ['status' => 'busy', 'message' => 'Já há uma atualização das vagas em andamento.', 'fetched' => 0, 'new' => 0, 'calls' => 0, 'states' => []];
    }
    if (radar_adzuna_calls_today() + RADAR_ADZUNA_BATCH > radar_adzuna_daily_limit()) {
        return ['status' => 'budget', 'message' => 'O limite diário de ' . radar_adzuna_daily_limit() . ' chamadas à Adzuna foi atingido (' . radar_adzuna_calls_today() . ' hoje). Volta amanhã, ou aumente o limite se o seu plano permitir.', 'fetched' => 0, 'new' => 0, 'calls' => 0, 'states' => []];
    }
    radar_setting_set('adzuna_running', (string)$now);
    $ufs = array_keys(radar_job_states());
    $cursor = (int)radar_setting('adzuna_cursor', '0') % count($ufs);
    $fetched = 0;
    $new = 0;
    $calls = 0;
    $done = [];
    $error = null;
    try {
        for ($i = 0; $i < RADAR_ADZUNA_BATCH; $i++) {
            $uf = $ufs[($cursor + $i) % count($ufs)];
            if ($i > 0) {
                radar_adzuna_pause();
            }
            $calls++;
            $answer = radar_adzuna_request(['where' => radar_job_states()[$uf]['name']]);
            foreach ($answer['results'] as $result) {
                $job = is_array($result) ? radar_adzuna_normalize($result) : null;
                if ($job === null) {
                    continue;
                }
                $fetched++;
                $new += radar_adzuna_store($job, $now) ? 1 : 0;
            }
            $done[] = $uf;
        }
    } catch (RuntimeException $failure) {
        $error = $failure->getMessage();
    } finally {
        radar_setting_set('adzuna_running', '0');
    }
    radar_setting_set('adzuna_cursor', (string)(($cursor + count($done)) % count($ufs)));
    radar_setting_set('adzuna_last_batch_at', (string)$now);
    radar_setting_set('adzuna_last_error', $error ?? '');
    radar_db_exec("DELETE FROM job_posts WHERE provider = 'adzuna' AND fetched_at < ?", [$now - RADAR_ADZUNA_KEEP_DAYS * 86400]);
    $states = array_map(static fn(string $uf): string => radar_job_states()[$uf]['name'], $done);
    if ($error !== null) {
        return ['status' => 'error', 'message' => $error . ($done !== [] ? ' Antes disso, ' . count($done) . ' estado(s) foram lidos.' : ''), 'fetched' => $fetched, 'new' => $new, 'calls' => $calls, 'states' => $states];
    }
    return ['status' => 'ok', 'message' => 'Vagas atualizadas: ' . $fetched . ' lidas (' . $new . ' novas) em ' . implode(', ', $states) . '.', 'fetched' => $fetched, 'new' => $new, 'calls' => $calls, 'states' => $states];
}

/** One call to check that the credentials work (counts against the daily budget). @return array{ok:bool,message:string} */
function radar_adzuna_test(): array
{
    try {
        $answer = radar_adzuna_request(['results_per_page' => 1, 'where' => 'São Paulo']);
        $total = (int)($answer['count'] ?? 0);
        return ['ok' => true, 'message' => 'Conexão com a Adzuna funcionando' . ($total > 0 ? ': ' . number_format($total, 0, ',', '.') . ' vagas encontradas em São Paulo.' : '.')];
    } catch (RuntimeException $error) {
        return ['ok' => false, 'message' => $error->getMessage()];
    }
}

/* ---------- reading the openings ---------- */

/**
 * @param array<string,mixed> $input q, regiao, estado, cidade, idade, contrato, ordem (recente|salario), pagina, limite
 * @return array{q:string,region:string,state:string,city:string,age:string,contract:string,sort:string,page:int,limit:int}
 */
function radar_jobs_filters(array $input): array
{
    $region = radar_str($input['regiao'] ?? '');
    $state = strtoupper(radar_str($input['estado'] ?? ''));
    $age = radar_str($input['idade'] ?? '');
    $contract = radar_str($input['contrato'] ?? '');
    return [
        'q' => mb_substr(trim(radar_str($input['q'] ?? '')), 0, 100),
        'region' => in_array($region, radar_job_regions(), true) ? $region : '',
        'state' => isset(radar_job_states()[$state]) ? $state : '',
        'city' => mb_substr(trim(radar_str($input['cidade'] ?? '')), 0, 80),
        'age' => isset(radar_job_ages()[$age]) ? $age : '',
        'contract' => in_array($contract, ['permanent', 'contract'], true) ? $contract : '',
        'sort' => radar_str($input['ordem'] ?? 'recente') === 'salario' ? 'salario' : 'recente',
        'page' => max(1, min(500, (int)radar_str($input['pagina'] ?? 1))),
        'limit' => max(1, min(100, (int)radar_str($input['limite'] ?? 24))),
    ];
}

/** @return array{0:string,1:array<int,mixed>} */
function radar_jobs_where(array $filters): array
{
    $where = ["provider = 'adzuna'"];
    $params = [];
    foreach (['region' => 'region', 'state' => 'state', 'city' => 'city', 'age' => 'age'] as $filter => $column) {
        if ($filters[$filter] !== '') {
            $where[] = $column . ' = ?';
            $params[] = $filters[$filter];
        }
    }
    if ($filters['contract'] !== '') {
        $where[] = 'contract_type = ?';
        $params[] = $filters['contract'];
    }
    if ($filters['q'] !== '') {
        $where[] = "(title LIKE ? ESCAPE '\\' OR company LIKE ? ESCAPE '\\' OR description LIKE ? ESCAPE '\\')";
        $like = radar_like($filters['q']);
        array_push($params, $like, $like, $like);
    }
    return [implode(' AND ', $where), $params];
}

/** @return array<string,mixed> */
function radar_jobs_decode(array $row, ?int $now = null): array
{
    $now ??= time();
    $format = static fn(?float $value): string => $value === null ? '' : 'R$ ' . number_format($value, 0, ',', '.');
    $min = $row['salary_min'] !== null ? (float)$row['salary_min'] : null;
    $max = $row['salary_max'] !== null ? (float)$row['salary_max'] : null;
    $salary = $min === null && $max === null ? '' : ($min !== null && $max !== null && round($min) !== round($max) ? $format($min) . ' a ' . $format($max) : $format($min ?? $max));
    return [
        'id' => (int)$row['id'], 'title' => (string)$row['title'], 'company' => (string)$row['company'], 'location' => (string)$row['location'], 'region' => (string)$row['region'], 'state' => (string)$row['state'], 'city' => (string)$row['city'],
        'age' => (string)$row['age'], 'age_label' => radar_job_ages()[(string)$row['age']] ?? '', 'category' => (string)$row['category'], 'description' => (string)$row['description'],
        'salary' => $salary, 'salary_predicted' => (int)$row['salary_predicted'] === 1 && $salary !== '', 'contract_type' => (string)$row['contract_type'], 'contract_time' => (string)$row['contract_time'],
        'url' => (string)$row['url'], 'source' => 'Adzuna', 'posted_at' => gmdate(DATE_ATOM, (int)$row['created_at']), 'age_minutes' => max(0, (int)round(($now - (int)$row['created_at']) / 60)),
    ];
}

/** @return array{items:array<int,array<string,mixed>>,total:int,filters:array<string,mixed>} */
function radar_jobs_query(array $filters, ?int $now = null): array
{
    [$condition, $params] = radar_jobs_where($filters);
    $order = $filters['sort'] === 'salario' ? 'COALESCE(salary_max, salary_min, 0) DESC, created_at DESC' : 'created_at DESC, id DESC';
    $rows = radar_db_all('SELECT * FROM job_posts WHERE ' . $condition . ' ORDER BY ' . $order . ' LIMIT ' . (int)$filters['limit'] . ' OFFSET ' . (int)(($filters['page'] - 1) * $filters['limit']), $params);
    return ['items' => array_map(static fn(array $row): array => radar_jobs_decode($row, $now), $rows), 'total' => (int)radar_db_value('SELECT COUNT(*) FROM job_posts WHERE ' . $condition, $params), 'filters' => $filters];
}

/**
 * What the filters can offer right now, with counts.
 *
 * @return array{regions:array<string,int>,states:array<string,int>,cities:array<int,array{city:string,state:string,total:int}>,ages:array<string,int>,total:int}
 */
function radar_jobs_facets(string $state = ''): array
{
    $count = static function (string $column): array {
        $out = [];
        foreach (radar_db_all("SELECT $column AS value, COUNT(*) AS total FROM job_posts WHERE provider = 'adzuna' AND $column != '' GROUP BY $column ORDER BY total DESC") as $row) {
            $out[(string)$row['value']] = (int)$row['total'];
        }
        return $out;
    };
    $cities = [];
    $params = [];
    $where = "provider = 'adzuna' AND city != ''";
    if ($state !== '') {
        $where .= ' AND state = ?';
        $params[] = $state;
    }
    foreach (radar_db_all("SELECT city, state, COUNT(*) AS total FROM job_posts WHERE $where GROUP BY city, state ORDER BY total DESC LIMIT 150", $params) as $row) {
        $cities[] = ['city' => (string)$row['city'], 'state' => (string)$row['state'], 'total' => (int)$row['total']];
    }
    usort($cities, static fn(array $a, array $b): int => strcmp(radar_fold($a['city']), radar_fold($b['city'])));
    return ['regions' => $count('region'), 'states' => $count('state'), 'cities' => $cities, 'ages' => $count('age'), 'total' => (int)radar_db_value("SELECT COUNT(*) FROM job_posts WHERE provider = 'adzuna'")];
}
