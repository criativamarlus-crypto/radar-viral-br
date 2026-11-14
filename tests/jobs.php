<?php
declare(strict_types=1);

/** The "Vagas de emprego" category: real announcements, with region, state, city and age read from the text. Included by run.php. */
check('the vacancy category exists, is a valid source category and the real job feeds are one per state, region and age group', static function (): bool {
    $sources = radar_job_sources();
    $names = array_column($sources, 'name');
    $urls = array_column($sources, 'url');
    return in_array(RADAR_JOB_CATEGORY, radar_category_list(), true) && radar_source_category_valid('Vagas de emprego') && count($sources) === 7 + 5 + 27 && count(array_unique($urls)) === count($urls)
        && in_array('Google Notícias · Vagas · Bahia', $names, true) && in_array('Google Notícias · Vagas · Região Nordeste', $names, true) && in_array('Google Notícias · Vagas · Jovem aprendiz', $names, true)
        && array_unique(array_column($sources, 'category')) === ['Vagas de emprego'] && str_starts_with($urls[0], 'https://news.google.com/rss/search?q=%22vagas+de+emprego%22&hl=pt-BR');
});
check('a headline that offers vacancies is a vacancy story; a football "vaga", a parking space or the jobs balance are not', static function (): bool {
    $yes = ['Prefeitura abre 300 vagas de emprego em Campinas', 'Mutirão oferece 1.200 vagas em Fortaleza para o emprego', 'Jovem aprendiz: empresa abre 4 mil vagas', 'Processo seletivo tem 100 vagas para o hospital', 'Vagas abertas para operador de caixa (SP)',
        'Concurso do INSS abre 1.000 vagas', 'Empresa abre 50 vagas com carteira assinada', 'Feira de empregos tem vagas de estágio'];
    $no = ['Flamengo garante vaga na final da Libertadores', 'Senado aprova vaga para novo ministro do STF', 'Prédio tem 200 vagas de garagem', 'Economia gera 200 mil empregos em outubro, diz Caged', 'Neymar volta a treinar no Santos', 'Santos contrata atacante'];
    foreach ($yes as $title) {
        if (!radar_job_is_vacancy($title) || radar_classify($title) !== 'Vagas de emprego') {
            fwrite(STDERR, "  should be vacancy: $title => " . radar_classify($title) . "\n");
            return false;
        }
    }
    foreach ($no as $title) {
        if (radar_job_is_vacancy($title) || radar_classify($title) === 'Vagas de emprego') {
            fwrite(STDERR, "  should not be vacancy: $title\n");
            return false;
        }
    }
    return radar_classify('Mutirão de emprego em Santos oferece vagas') === 'Vagas de emprego' && radar_classify('Neymar anuncia vagas de emprego na sua empresa') === 'Neymar'
        && radar_classify('Economia gera 200 mil empregos em outubro, diz Caged', '', 'Vagas de emprego') !== 'Vagas de emprego';
});
check('the place is read from the text: city, state, region; a homonym or a Christmas "Natal" is not a city', static function (): bool {
    $cases = [
        ['Prefeitura abre 300 vagas de emprego em Campinas', '', ['region' => 'Sudeste', 'state' => 'SP', 'city' => 'Campinas']],
        ['Mutirão tem 1.200 vagas de emprego em Fortaleza (CE)', '', ['region' => 'Nordeste', 'state' => 'CE', 'city' => 'Fortaleza']],
        ['Vagas de emprego abertas em Santa Catarina', '', ['region' => 'Sul', 'state' => 'SC', 'city' => '']],
        ['Vagas de emprego no Mato Grosso do Sul', '', ['region' => 'Centro-Oeste', 'state' => 'MS', 'city' => '']],
        ['Vagas de emprego: Natal oferece 500 postos (RN)', '', ['region' => 'Nordeste', 'state' => 'RN', 'city' => 'Natal']],
        ['Vagas de emprego temporárias de Natal no comércio', '', ['region' => '', 'state' => '', 'city' => '']],
        ['Vagas abertas em Londrina', 'Empresas do Paraná contratam.', ['region' => 'Sul', 'state' => 'PR', 'city' => 'Londrina']],
        ['Vagas de emprego no Nordeste', '', ['region' => 'Nordeste', 'state' => '', 'city' => '']],
        ['Empresa abre vagas de emprego em Santos', 'Unidade em Cubatão (SP).', ['region' => 'Sudeste', 'state' => 'SP', 'city' => 'Santos']],
        ['Vagas de emprego em Santos (RN)', '', ['region' => 'Nordeste', 'state' => 'RN', 'city' => '']],
        ['Vagas de emprego para todo o Brasil', '', ['region' => '', 'state' => '', 'city' => '']],
        ['IEL reúne 660 vagas de emprego em oito municípios de MT', '', ['region' => 'Centro-Oeste', 'state' => 'MT', 'city' => '']],
        ['São Vicente oferece 30 vagas de emprego para controlador de acesso', '', ['region' => 'Sudeste', 'state' => 'SP', 'city' => 'São Vicente']],
        ['Vagas de emprego em Santana do Livramento', '', ['region' => '', 'state' => '', 'city' => '']],
        ['Vagas de emprego em Toledo (PR)', '', ['region' => 'Sul', 'state' => 'PR', 'city' => 'Toledo']],
        ['Vagas de emprego com Toledo no nome da empresa', '', ['region' => '', 'state' => '', 'city' => '']],
    ];
    foreach ($cases as [$title, $summary, $expected]) {
        $got = radar_job_extract($title, $summary);
        foreach ($expected as $key => $value) {
            if ($got[$key] !== $value) {
                fwrite(STDERR, "  $title: $key = '" . $got[$key] . "' (esperado '$value')\n");
                return false;
            }
        }
    }
    return true;
});
check('the age group is read from the text: apprentice, first job, internship, 50+; and left empty when the text does not say', static function (): bool {
    $age = static fn(string $title, string $summary = ''): string => radar_job_extract($title, $summary)['age'];
    return $age('Jovem aprendiz: 4 mil vagas em São Paulo') === 'aprendiz' && $age('Programa de primeiro emprego abre vagas') === 'primeiro_emprego' && $age('Vagas de estágio para estudantes de Direito') === 'estagio'
        && $age('Empresa abre vagas para maiores de 50 anos') === '50mais' && $age('Vagas de emprego 50+ no Rio') === '50mais' && $age('Vagas para idosos e terceira idade') === '50mais'
        && $age('Vagas de emprego para vendedor em Campinas') === '' && $age('Vagas de emprego para vendedor', 'Estágio também disponível.') === 'estagio';
});
check('re-classification saves the place and the age of vacancy stories, clears them for other stories and the card shows them', static function (): bool {
    global $now;
    $ins = static fn(int $id, string $title, string $summary, string $category) => radar_db_exec("INSERT INTO news (id, uid, source_name, title, url, summary, published_at, first_seen_at, last_seen_at, category, score, source_count) VALUES (?, ?, 'G1', ?, ?, ?, ?, ?, ?, ?, 50, 1)", [$id, 'job' . $id, $title, 'https://g1.example.com/job' . $id, $summary, $now - 600, $now - 600, $now - 600, $category]);
    $ins(840, 'Prefeitura abre 300 vagas de emprego em Campinas', 'Há vagas para jovem aprendiz.', 'Geral');
    $ins(841, 'Mutirão tem 1.200 vagas de emprego em Fortaleza (CE)', 'Para maiores de 50 anos.', 'Geral');
    $ins(842, 'Vagas de emprego abertas em Curitiba', '', 'Geral');
    $ins(843, 'Santos vence o Coritiba', 'Jogo em Curitiba.', 'Futebol');
    radar_db_exec("UPDATE news SET job_state = 'XX', job_city = 'Lixo' WHERE id = 843");
    radar_news_reclassify();
    $rows = [];
    foreach ([840, 841, 842, 843] as $id) {
        $rows[$id] = radar_db_one('SELECT category, job_region, job_state, job_city, job_age FROM news WHERE id = ?', [$id]);
    }
    $item = radar_news_get(841);
    return $rows[840] === ['category' => 'Vagas de emprego', 'job_region' => 'Sudeste', 'job_state' => 'SP', 'job_city' => 'Campinas', 'job_age' => 'aprendiz']
        && $rows[841]['job_state'] === 'CE' && $rows[841]['job_city'] === 'Fortaleza' && $rows[841]['job_age'] === '50mais' && $rows[842]['job_city'] === 'Curitiba' && $rows[842]['job_age'] === ''
        && $rows[843] === ['category' => 'Futebol', 'job_region' => '', 'job_state' => '', 'job_city' => '', 'job_age' => ''] && $item['job']['city'] === 'Fortaleza' && $item['job']['age_label'] === '50+ (maiores de 45 ou 50 anos)'
        && radar_news_get(843)['job'] === null && str_contains(radar_news_card($item), 'Fortaleza · CE · Nordeste') && str_contains(radar_news_card(radar_news_get(842)), 'Curitiba') && !str_contains(radar_news_card(radar_news_get(843)), 'Local não informado');
});
check('the filters by region, state, city and age narrow the vacancy stories, imply the category and ignore invalid values', static function (): bool {
    $by = static function (array $input): array {
        $result = array_column(radar_news_query(radar_news_filters($input + ['janela' => 'tudo', 'agrupar' => '0']))['items'], 'id');
        sort($result);
        return $result;
    };
    $all = $by(['categoria' => 'Vagas de emprego']);
    return in_array(840, $all, true) && in_array(841, $all, true) && in_array(842, $all, true) && !in_array(843, $all, true)
        && $by(['regiao' => 'Nordeste']) === [841] && $by(['estado' => 'sp']) === [840] && $by(['cidade' => 'Curitiba']) === [842] && $by(['idade' => '50mais']) === [841] && $by(['idade' => 'aprendiz', 'regiao' => 'Sudeste']) === [840]
        && $by(['regiao' => 'Sul', 'idade' => 'aprendiz']) === [] && $by(['regiao' => 'Marte']) === $by([]) && $by(['estado' => 'ZZ', 'idade' => 'velhice']) === $by([])
        && radar_news_filters(['regiao' => 'Sul'])['categories'] === ['Vagas de emprego'] && radar_news_filters(['categoria' => 'Futebol', 'idade' => 'aprendiz'])['categories'] === ['Futebol'];
});
check('the facets list what exists: regions, states, cities (of one state) and age groups with their counts', static function (): bool {
    global $now;
    $facets = radar_job_facets($now - 86400);
    $sp = radar_job_facets($now - 86400, 'SP');
    $cities = array_column($facets['cities'], 'city');
    return $facets['regions']['Nordeste'] >= 1 && $facets['regions']['Sudeste'] >= 1 && ($facets['states']['CE'] ?? 0) >= 1 && ($facets['ages']['aprendiz'] ?? 0) >= 1 && in_array('Fortaleza', $cities, true) && in_array('Curitiba', $cities, true)
        && array_column($sp['cities'], 'city') === ['Campinas'] && $facets['total'] >= 3;
});
check('"Pará" is the state only when written as a name, never the word "para"', static function (): bool {
    $belem = radar_job_extract('Vagas de emprego em Belém, no Pará', '');
    $word = radar_job_extract('Vagas de emprego para vendedor e para caixa', '');
    return $belem['state'] === 'PA' && $belem['city'] === 'Belém' && $belem['region'] === 'Norte' && $word['state'] === '' && $word['region'] === '' && radar_job_extract('VAGAS DE EMPREGO NO PARÁ')['state'] === 'PA';
});
