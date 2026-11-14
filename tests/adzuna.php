<?php
declare(strict_types=1);

/** Adzuna: real job openings from the API (credentials, batches, budget, filters). Network replaced by $GLOBALS['radar_test_transport']. Included by run.php. */
$adzunaJob = static fn(string $id, string $title, array $area, array $extra = []): array => array_merge([
    'id' => $id, 'title' => $title, 'description' => 'Atuar no atendimento ao cliente, com <b>carteira assinada</b> e benefícios.', 'created' => gmdate('Y-m-d\TH:i:s\Z', time() - 7200),
    'redirect_url' => 'https://www.adzuna.com.br/land/ad/' . $id . '?se=abc', 'company' => ['display_name' => 'Empresa ' . $id], 'location' => ['display_name' => implode(', ', array_reverse(array_slice($area, 1))), 'area' => $area],
    'category' => ['label' => 'Vendas', 'tag' => 'sales-jobs'], 'salary_min' => 2500, 'salary_max' => 3500, 'salary_is_predicted' => '0', 'contract_type' => 'permanent', 'contract_time' => 'full_time',
], $extra);
$adzunaReply = static fn(array $results, int $count = 0): array => ['one' => fakeResponse(200, json_encode(['__CLASS__' => 'Adzuna::API::Response::JobSearchResults', 'count' => $count ?: count($results), 'results' => $results], JSON_UNESCAPED_UNICODE))];

check('the Adzuna credentials are validated, saved encrypted, shown only by their tail and removable', static function (): bool {
    $bad = rejects(static fn() => radar_adzuna_save('com espaço', 'chave1234567', 60), InvalidArgumentException::class) && rejects(static fn() => radar_adzuna_save('abc12345', 'curta', 60), InvalidArgumentException::class)
        && rejects(static fn() => radar_adzuna_save('abc12345', 'chaveSecreta1234567890', 5), InvalidArgumentException::class) && rejects(static fn() => radar_adzuna_save('abc12345', 'chaveSecreta1234567890', 900), InvalidArgumentException::class);
    $before = radar_adzuna_configured();
    radar_adzuna_save('abc12345', 'chaveSecreta1234567890', 80);
    $raw = (string)radar_setting('secret:adzuna_app_key');
    $status = radar_adzuna_status();
    $ok = !$before && radar_adzuna_configured() && !str_contains($raw, 'chaveSecreta') && $raw !== '' && radar_adzuna_keys() === ['id' => 'abc12345', 'key' => 'chaveSecreta1234567890'] && $status['key_hint'] === '••••7890' && $status['daily_limit'] === 80
        && !str_contains(json_encode($status), 'chaveSecreta') && radar_adzuna_mask('falhou em app_key=chaveSecreta1234567890&x=1 e abc12345') === 'falhou em app_key=[oculto]&x=1 e [oculto]';
    radar_adzuna_forget();
    return $bad && $ok && !radar_adzuna_configured();
});
check('an API result becomes an opening: place from the area, salary, contract, age group from the text, tags stripped; unusable results are dropped', static function () use ($adzunaJob): bool {
    $job = radar_adzuna_normalize($adzunaJob('111', 'Vaga de jovem aprendiz &amp; atendimento', ['Brasil', 'São Paulo', 'Campinas']));
    $capital = radar_adzuna_normalize($adzunaJob('112', 'Vendedor', ['Brasil', 'Ceará', 'Fortaleza'], ['salary_min' => 0, 'salary_max' => 0, 'salary_is_predicted' => '1']));
    $noArea = radar_adzuna_normalize($adzunaJob('113', 'Auxiliar administrativo', [], ['location' => ['display_name' => 'Curitiba, Paraná', 'area' => []]]));
    $text = radar_adzuna_normalize($adzunaJob('114', 'Estágio em Direito', [], ['location' => ['display_name' => 'Brasil', 'area' => []], 'description' => 'Vaga em Londrina (PR) para estudantes.']));
    return $job['state'] === 'SP' && $job['city'] === 'Campinas' && $job['region'] === 'Sudeste' && $job['age'] === 'aprendiz' && $job['title'] === 'Vaga de jovem aprendiz & atendimento' && $job['salary_min'] === 2500.0 && $job['salary_max'] === 3500.0
        && !str_contains($job['description'], '<b>') && str_contains($job['description'], 'carteira assinada') && $job['url'] === 'https://www.adzuna.com.br/land/ad/111?se=abc' && $job['company'] === 'Empresa 111' && $job['contract_type'] === 'permanent' && $job['category'] === 'Vendas'
        && $capital['state'] === 'CE' && $capital['city'] === 'Fortaleza' && $capital['salary_min'] === null && $capital['salary_predicted'] === 1 && $noArea['state'] === 'PR' && $noArea['city'] === 'Curitiba'
        && $text['state'] === 'PR' && $text['age'] === 'estagio' && radar_adzuna_normalize($adzunaJob('', 'Sem id', ['Brasil'])) === null && radar_adzuna_normalize($adzunaJob('115', '', ['Brasil'])) === null
        && radar_adzuna_normalize($adzunaJob('116', 'Link ruim', ['Brasil'], ['redirect_url' => 'javascript:alert(1)'])) === null && radar_adzuna_normalize($adzunaJob('117', 'Link http', ['Brasil'], ['redirect_url' => 'http://x.example.com/a'])) === null;
});
check('the call carries the credentials only in the query, counts against the daily budget and its errors never show the key', static function () use ($adzunaReply): bool {
    radar_adzuna_save('abc12345', 'chaveSecreta1234567890', 60);
    $seen = [];
    $GLOBALS['radar_test_transport'] = static function (array $r) use ($adzunaReply, &$seen): array {
        $seen[] = $r['one'];
        return $adzunaReply([]);
    };
    $before = radar_adzuna_calls_today();
    radar_adzuna_request(['where' => 'São Paulo', 'results_per_page' => 1]);
    $query = [];
    parse_str((string)parse_url($seen[0]['url'], PHP_URL_QUERY), $query);
    $errors = [];
    foreach ([[401, 'recusou a chave'], [403, 'recusou a chave'], [429, 'Limite de chamadas'], [400, 'recusou o pedido'], [503, 'indisponível']] as [$status, $needle]) {
        $GLOBALS['radar_test_transport'] = static fn(array $r): array => ['one' => fakeResponse($status, json_encode(['display' => 'Erro com app_key=chaveSecreta1234567890', 'exception' => 'X']))];
        try {
            radar_adzuna_request(['where' => 'Bahia']);
            $errors[] = 'no error';
        } catch (RuntimeException $error) {
            $errors[] = str_contains($error->getMessage(), $needle) && !str_contains($error->getMessage(), 'chaveSecreta') && !str_contains($error->getMessage(), 'abc12345') ? 'ok' : $error->getMessage();
        }
    }
    $GLOBALS['radar_test_transport'] = static fn(array $r): array => ['one' => fakeResponse(0, '', [], 'Falha em https://api.adzuna.com/x?app_id=abc12345&app_key=chaveSecreta1234567890')];
    try {
        radar_adzuna_request([]);
        $network = 'no error';
    } catch (RuntimeException $error) {
        $network = !str_contains($error->getMessage(), 'chaveSecreta') && !str_contains($error->getMessage(), 'abc12345') ? 'ok' : $error->getMessage();
    }
    unset($GLOBALS['radar_test_transport']);
    $noKeys = (static function (): bool {
        radar_adzuna_forget();
        return rejects(static fn() => radar_adzuna_request([]), RuntimeException::class);
    })();
    return str_starts_with($seen[0]['url'], 'https://api.adzuna.com/v1/api/jobs/br/search/1?') && $query['app_id'] === 'abc12345' && $query['app_key'] === 'chaveSecreta1234567890' && $query['where'] === 'São Paulo' && $query['results_per_page'] === '1'
        && $query['sort_by'] === 'date' && $query['max_days_old'] === '14' && radar_adzuna_calls_today() >= $before + 6 && $errors === ['ok', 'ok', 'ok', 'ok', 'ok'] && $network === 'ok' && $noKeys;
});
check('a refresh reads 9 states in order, stores their openings once, moves on to the next 9 and wraps around after 27', static function () use ($adzunaJob, $adzunaReply): bool {
    radar_adzuna_save('abc12345', 'chaveSecreta1234567890', 200);
    radar_setting_set('adzuna_calls_' . date('Ymd'), '0');
    radar_setting_set('adzuna_cursor', '0');
    radar_setting_set('adzuna_last_batch_at', '0');
    $wheres = [];
    $GLOBALS['radar_test_transport'] = static function (array $r) use ($adzunaJob, $adzunaReply, &$wheres): array {
        $query = [];
        parse_str((string)parse_url($r['one']['url'], PHP_URL_QUERY), $query);
        $wheres[] = $query['where'];
        $n = count($wheres);
        return $adzunaReply([$adzunaJob('a' . $n, 'Vaga ' . $n . ' em ' . $query['where'], ['Brasil', $query['where'], 'Cidade ' . $n]), $adzunaJob('shared', 'Vaga repetida', ['Brasil', 'Bahia', 'Salvador'])]);
    };
    $first = radar_adzuna_refresh();
    $firstWheres = $wheres;
    $second = radar_adzuna_refresh(time() + 10);
    $third = radar_adzuna_refresh(time() + 20);
    $fourth = radar_adzuna_refresh(time() + 30);
    unset($GLOBALS['radar_test_transport']);
    $total = (int)radar_db_value("SELECT COUNT(*) FROM job_posts WHERE provider = 'adzuna'");
    return $first['status'] === 'ok' && $first['calls'] === 9 && $first['fetched'] === 18 && $first['new'] === 10 && $firstWheres === ['Acre', 'Alagoas', 'Amapá', 'Amazonas', 'Bahia', 'Ceará', 'Distrito Federal', 'Espírito Santo', 'Goiás']
        && $second['new'] === 9 && $third['new'] === 9 && $fourth['status'] === 'ok' && $wheres[27] === 'Acre' && count($wheres) === 36 && $total === 37;
});
check('a refresh stops at the daily budget, reports a rejected key after keeping what it read, and refuses a second run at the same time', static function () use ($adzunaJob, $adzunaReply): bool {
    radar_adzuna_save('abc12345', 'chaveSecreta1234567890', 20);
    radar_setting_set('adzuna_calls_' . date('Ymd'), '12');
    $calls = 0;
    $GLOBALS['radar_test_transport'] = static function (array $r) use (&$calls, $adzunaReply): array {
        $calls++;
        return $adzunaReply([]);
    };
    $budget = radar_adzuna_refresh();
    $noCalls = $calls === 0 && $budget['status'] === 'budget' && str_contains($budget['message'], 'limite diário de 20');
    radar_adzuna_save('abc12345', 'chaveSecreta1234567890', 200);
    radar_setting_set('adzuna_calls_' . date('Ymd'), '0');
    radar_setting_set('adzuna_cursor', '0');
    $GLOBALS['radar_test_transport'] = static function (array $r) use (&$calls, $adzunaJob, $adzunaReply): array {
        $calls++;
        return $calls === 3 ? ['one' => fakeResponse(401, json_encode(['exception' => 'AUTH_FAIL']))] : $adzunaReply([$adzunaJob('err' . $calls, 'Vaga de teste ' . $calls, ['Brasil', 'Bahia', 'Salvador'])]);
    };
    $calls = 0;
    $failed = radar_adzuna_refresh(time() + 100);
    $cursor = radar_setting('adzuna_cursor');
    $recorded = radar_adzuna_status()['last_error'];
    radar_setting_set('adzuna_running', (string)time());
    $busy = radar_adzuna_refresh(time() + 110);
    radar_setting_set('adzuna_running', '0');
    unset($GLOBALS['radar_test_transport']);
    return $noCalls && $failed['status'] === 'error' && str_contains($failed['message'], 'recusou a chave') && str_contains($failed['message'], '2 estado(s)') && $failed['fetched'] === 2 && $cursor === '2' && str_contains($recorded, 'recusou a chave')
        && $busy['status'] === 'busy' && radar_db_value("SELECT COUNT(*) FROM job_posts WHERE external_id IN ('err1', 'err2')") === 2;
});
check('a refresh is due only when connected, idle, two hours after the last one and with calls to spare; old openings are purged', static function (): bool {
    $now = time();
    radar_adzuna_save('abc12345', 'chaveSecreta1234567890', 60);
    radar_setting_set('adzuna_calls_' . date('Ymd'), '0');
    radar_setting_set('adzuna_running', '0');
    radar_setting_set('adzuna_last_batch_at', (string)($now - 3 * 3600));
    $due = radar_adzuna_stale($now);
    radar_setting_set('adzuna_last_batch_at', (string)($now - 600));
    $recent = radar_adzuna_stale($now);
    radar_setting_set('adzuna_last_batch_at', (string)($now - 3 * 3600));
    radar_setting_set('adzuna_calls_' . date('Ymd'), '55');
    $spent = radar_adzuna_stale($now);
    radar_setting_set('adzuna_calls_' . date('Ymd'), '0');
    radar_setting_set('adzuna_running', (string)$now);
    $running = radar_adzuna_stale($now);
    radar_setting_set('adzuna_running', '0');
    radar_adzuna_forget();
    $off = radar_adzuna_stale($now);
    radar_db_exec("INSERT INTO job_posts (provider, external_id, title, url, created_at, fetched_at) VALUES ('adzuna', 'velha', 'Vaga velha', 'https://www.adzuna.com.br/land/ad/velha', ?, ?)", [$now - 30 * 86400, $now - 20 * 86400]);
    radar_adzuna_save('abc12345', 'chaveSecreta1234567890', 200);
    $GLOBALS['radar_test_transport'] = static fn(array $r): array => ['one' => fakeResponse(200, json_encode(['count' => 0, 'results' => []]))];
    radar_setting_set('adzuna_calls_' . date('Ymd'), '0');
    radar_adzuna_refresh($now + 5);
    unset($GLOBALS['radar_test_transport']);
    radar_adzuna_forget();
    return $due && !$recent && !$spent && !$running && !$off && radar_db_value("SELECT COUNT(*) FROM job_posts WHERE external_id = 'velha'") === 0;
});
check('the openings are filtered by region, state, city, age, contract and words, ordered by date or salary, and the facets count them', static function () use ($adzunaJob): bool {
    radar_db_exec("DELETE FROM job_posts");
    $now = time();
    foreach ([
        ['f1', 'Vendedor de loja', ['Brasil', 'São Paulo', 'Campinas'], ['salary_min' => 2000, 'salary_max' => 2500, 'created' => gmdate('Y-m-d\TH:i:s\Z', $now - 3600)]],
        ['f2', 'Jovem aprendiz administrativo', ['Brasil', 'São Paulo', 'Santos'], ['salary_min' => 900, 'salary_max' => 900, 'created' => gmdate('Y-m-d\TH:i:s\Z', $now - 7200), 'contract_type' => 'contract']],
        ['f3', 'Auxiliar de cozinha', ['Brasil', 'Ceará', 'Fortaleza'], ['salary_min' => 1500, 'salary_max' => 1800, 'created' => gmdate('Y-m-d\TH:i:s\Z', $now - 10800)]],
        ['f4', 'Vagas para maiores de 50 anos no atendimento', ['Brasil', 'Paraná', 'Curitiba'], ['salary_min' => 4000, 'salary_max' => 5000, 'created' => gmdate('Y-m-d\TH:i:s\Z', $now - 14400)]],
    ] as [$id, $title, $area, $extra]) {
        radar_adzuna_store(radar_adzuna_normalize($adzunaJob($id, $title, $area, $extra)), $now);
    }
    $titles = static fn(array $input): array => array_column(radar_jobs_query(radar_jobs_filters($input))['items'], 'title');
    $all = $titles([]);
    $facets = radar_jobs_facets();
    $sp = radar_jobs_facets('SP');
    $first = radar_jobs_query(radar_jobs_filters([]))['items'][0];
    return count($all) === 4 && $all[0] === 'Vendedor de loja' && $titles(['ordem' => 'salario'])[0] === 'Vagas para maiores de 50 anos no atendimento' && $titles(['regiao' => 'Nordeste']) === ['Auxiliar de cozinha'] && $titles(['estado' => 'sp']) === ['Vendedor de loja', 'Jovem aprendiz administrativo']
        && $titles(['cidade' => 'Curitiba']) === ['Vagas para maiores de 50 anos no atendimento'] && $titles(['idade' => 'aprendiz']) === ['Jovem aprendiz administrativo'] && $titles(['idade' => '50mais']) === ['Vagas para maiores de 50 anos no atendimento']
        && $titles(['contrato' => 'contract']) === ['Jovem aprendiz administrativo'] && $titles(['q' => 'cozinha']) === ['Auxiliar de cozinha'] && $titles(['q' => 'Empresa f1']) === ['Vendedor de loja'] && $titles(['regiao' => 'Marte', 'idade' => 'velhice', 'estado' => 'ZZ']) === $all
        && $titles(['regiao' => 'Sul', 'idade' => 'aprendiz']) === [] && radar_jobs_filters(['pagina' => '9999', 'limite' => '500'])['page'] === 500 && radar_jobs_filters(['limite' => '500'])['limit'] === 100
        && $facets['total'] === 4 && $facets['regions']['Sudeste'] === 2 && $facets['regions']['Nordeste'] === 1 && $facets['regions']['Sul'] === 1 && count($facets['regions']) === 3 && $facets['states']['SP'] === 2 && $facets['ages']['aprendiz'] === 1 && $facets['ages']['50mais'] === 1 && count($facets['ages']) === 2
        && array_column($sp['cities'], 'city') === ['Campinas', 'Santos']
        && $first['salary'] === 'R$ 2.000 a R$ 2.500' && $first['company'] === 'Empresa f1' && $first['source'] === 'Adzuna' && $first['age_minutes'] >= 59 && radar_jobs_query(radar_jobs_filters(['idade' => 'aprendiz']))['items'][0]['salary'] === 'R$ 900';
});
radar_db_exec('DELETE FROM job_posts');
