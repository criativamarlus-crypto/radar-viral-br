<?php
declare(strict_types=1);

/** Source registry (CRUD), editorial categories and keyword classification. */

const RADAR_TRENDS_URL = 'https://trends.google.com/trending/rss?geo=BR';

/** @return array<int,string> */
function radar_category_list(): array
{
    return ['Política', 'Futebol', 'Esportes', 'Famosos', 'Economia', 'Tecnologia', 'Notícias internacionais', 'Curiosidades', 'Entretenimento', 'Assuntos virais', 'Geral'];
}

/** Categories a source may be pinned to ('auto' = classify each headline). */
function radar_source_category_valid(string $category): bool
{
    return $category === 'auto' || in_array($category, radar_category_list(), true);
}

/**
 * Built-in sources. Every URL was probed live while building this list; users can
 * deactivate any of them. Google News is for personal, non-commercial feed reading
 * (see its feed notice): keep it for a private panel only.
 *
 * @return array<int,array{name:string,url:string,category:string,region:string,weight:float,kind?:string}>
 */
function radar_default_sources(): array
{
    $g = 'hl=pt-BR&gl=BR&ceid=BR:pt-419';
    return [
        ['name' => 'Google Trends Brasil', 'url' => RADAR_TRENDS_URL, 'category' => 'auto', 'region' => 'BR', 'weight' => 1.0, 'kind' => 'trends'],
        ['name' => 'Google Notícias · Brasil', 'url' => "https://news.google.com/rss/headlines/section/topic/NATION?$g", 'category' => 'auto', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Google Notícias · Futebol', 'url' => "https://news.google.com/rss/search?q=futebol&$g", 'category' => 'Futebol', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Google Notícias · Famosos', 'url' => "https://news.google.com/rss/search?q=celebridades&$g", 'category' => 'Famosos', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'G1', 'url' => 'https://g1.globo.com/rss/g1/', 'category' => 'auto', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'G1 · Política', 'url' => 'https://g1.globo.com/rss/g1/politica/', 'category' => 'Política', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'G1 · Economia', 'url' => 'https://g1.globo.com/rss/g1/economia/', 'category' => 'Economia', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'G1 · Tecnologia', 'url' => 'https://g1.globo.com/rss/g1/tecnologia/', 'category' => 'Tecnologia', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'G1 · Mundo', 'url' => 'https://g1.globo.com/rss/g1/mundo/', 'category' => 'Notícias internacionais', 'region' => 'INT', 'weight' => 1.0],
        ['name' => 'G1 · Pop & Arte', 'url' => 'https://g1.globo.com/rss/g1/pop-arte/', 'category' => 'Entretenimento', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'GE (Globo Esporte)', 'url' => 'https://ge.globo.com/rss/ge/', 'category' => 'Futebol', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'Quem', 'url' => 'https://revistaquem.globo.com/rss/quem/', 'category' => 'Famosos', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'UOL · Notícias', 'url' => 'https://rss.uol.com.br/feed/noticias.xml', 'category' => 'auto', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'UOL · Esporte', 'url' => 'https://rss.uol.com.br/feed/esporte.xml', 'category' => 'Futebol', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'UOL · Economia', 'url' => 'https://rss.uol.com.br/feed/economia.xml', 'category' => 'Economia', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'UOL · Tecnologia', 'url' => 'https://rss.uol.com.br/feed/tecnologia.xml', 'category' => 'Tecnologia', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'UOL · Entretenimento', 'url' => 'https://rss.uol.com.br/feed/entretenimento.xml', 'category' => 'Entretenimento', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'CNN Brasil', 'url' => 'https://www.cnnbrasil.com.br/feed/', 'category' => 'auto', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'Metrópoles', 'url' => 'https://www.metropoles.com/feed', 'category' => 'auto', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'Metrópoles · Entretenimento', 'url' => 'https://www.metropoles.com/entretenimento/feed', 'category' => 'Famosos', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'Metrópoles · Esportes', 'url' => 'https://www.metropoles.com/esportes/feed', 'category' => 'Futebol', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'Terra', 'url' => 'https://www.terra.com.br/rss.xml', 'category' => 'auto', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Terra · Diversão', 'url' => 'https://www.terra.com.br/diversao/rss.xml', 'category' => 'Entretenimento', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Folha de S.Paulo', 'url' => 'https://feeds.folha.uol.com.br/emcimadahora/rss091.xml', 'category' => 'auto', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'Folha · Poder', 'url' => 'https://feeds.folha.uol.com.br/poder/rss091.xml', 'category' => 'Política', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'Folha · Ilustrada', 'url' => 'https://feeds.folha.uol.com.br/ilustrada/rss091.xml', 'category' => 'Entretenimento', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Estadão', 'url' => 'https://www.estadao.com.br/arc/outboundfeeds/rss/?outputType=xml', 'category' => 'auto', 'region' => 'BR', 'weight' => 1.0],
        ['name' => 'Veja', 'url' => 'https://veja.abril.com.br/feed/', 'category' => 'auto', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Poder360', 'url' => 'https://www.poder360.com.br/feed/', 'category' => 'Política', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Agência Brasil', 'url' => 'https://agenciabrasil.ebc.com.br/rss/ultimasnoticias/feed.xml', 'category' => 'auto', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Gazeta Esportiva', 'url' => 'https://www.gazetaesportiva.com/feed/', 'category' => 'Futebol', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'ESPN Brasil', 'url' => 'https://www.espn.com.br/espn/rss/news', 'category' => 'Futebol', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Exame', 'url' => 'https://exame.com/feed/', 'category' => 'Economia', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'InfoMoney', 'url' => 'https://www.infomoney.com.br/feed/', 'category' => 'Economia', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'TecMundo', 'url' => 'https://rss.tecmundo.com.br/feed', 'category' => 'Tecnologia', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Canaltech', 'url' => 'https://canaltech.com.br/rss/', 'category' => 'Tecnologia', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Olhar Digital', 'url' => 'https://olhardigital.com.br/feed/', 'category' => 'Tecnologia', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Tecnoblog', 'url' => 'https://tecnoblog.net/feed/', 'category' => 'Tecnologia', 'region' => 'BR', 'weight' => 0.9],
        ['name' => 'Folha · Mundo', 'url' => 'https://feeds.folha.uol.com.br/mundo/rss091.xml', 'category' => 'Notícias internacionais', 'region' => 'INT', 'weight' => 0.9],
        ['name' => 'BBC News Brasil', 'url' => 'https://feeds.bbci.co.uk/portuguese/rss.xml', 'category' => 'Notícias internacionais', 'region' => 'INT', 'weight' => 1.0],
        ['name' => 'DW Brasil', 'url' => 'https://rss.dw.com/rdf/rss-br-all', 'category' => 'Notícias internacionais', 'region' => 'INT', 'weight' => 0.9],
        ['name' => 'Euronews PT', 'url' => 'https://pt.euronews.com/rss', 'category' => 'Notícias internacionais', 'region' => 'INT', 'weight' => 0.8],
        ['name' => 'BBC World', 'url' => 'https://feeds.bbci.co.uk/news/world/rss.xml', 'category' => 'Notícias internacionais', 'region' => 'INT', 'weight' => 0.9],
        ['name' => 'The Guardian · World', 'url' => 'https://www.theguardian.com/world/rss', 'category' => 'Notícias internacionais', 'region' => 'INT', 'weight' => 0.9],
        ['name' => 'The New York Times · World', 'url' => 'https://rss.nytimes.com/services/xml/rss/nyt/World.xml', 'category' => 'Notícias internacionais', 'region' => 'INT', 'weight' => 0.9],
        ['name' => 'Al Jazeera', 'url' => 'https://www.aljazeera.com/xml/rss/all.xml', 'category' => 'Notícias internacionais', 'region' => 'INT', 'weight' => 0.8],
        ['name' => 'Superinteressante', 'url' => 'https://super.abril.com.br/feed/', 'category' => 'Curiosidades', 'region' => 'BR', 'weight' => 0.8],
        ['name' => 'Galileu', 'url' => 'https://revistagalileu.globo.com/rss/galileu/', 'category' => 'Curiosidades', 'region' => 'BR', 'weight' => 0.8],
    ];
}

/** Insert missing built-in sources. Never touches rows the user edited or deleted-then-readded. */
function radar_sources_seed(): int
{
    $added = 0;
    $now = time();
    $seeded = radar_setting('sources_seeded_version', '0');
    if ($seeded === '1') {
        return 0;
    }
    foreach (radar_default_sources() as $source) {
        $exists = radar_db_value('SELECT 1 FROM sources WHERE url = ?', [$source['url']]);
        if ($exists !== null) {
            continue;
        }
        radar_db_exec(
            'INSERT INTO sources (name, kind, url, category, region, weight, active, builtin, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?, ?)',
            [$source['name'], $source['kind'] ?? 'rss', $source['url'], $source['category'], $source['region'], $source['weight'], $now, $now]
        );
        $added++;
    }
    radar_setting_set('sources_seeded_version', '1');
    return $added;
}

/** @return array<int,array<string,mixed>> */
function radar_sources_all(): array
{
    return radar_db_all('SELECT * FROM sources ORDER BY active DESC, kind DESC, name COLLATE NOCASE');
}

/** @return array<string,mixed>|null */
function radar_source_get(int $id): ?array
{
    return radar_db_one('SELECT * FROM sources WHERE id = ?', [$id]);
}

/**
 * Validate user input for a source. Returns the cleaned row or throws InvalidArgumentException.
 *
 * @return array{name:string,url:string,category:string,region:string,weight:float,kind:string}
 */
function radar_source_validate(array $input): array
{
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags(radar_str($input['name'] ?? ''))) ?? '');
    if ($name === '' || mb_strlen($name) > 80) {
        throw new InvalidArgumentException('Informe um nome de até 80 caracteres.');
    }
    $url = radar_feed_safe_url(radar_str($input['url'] ?? ''));
    if ($url === null) {
        throw new InvalidArgumentException('Informe um endereço HTTPS válido de feed RSS ou Atom.');
    }
    $category = radar_str($input['category'] ?? 'auto');
    if (!radar_source_category_valid($category)) {
        throw new InvalidArgumentException('Categoria inválida.');
    }
    $region = radar_str($input['region'] ?? 'BR');
    if (!in_array($region, ['BR', 'INT'], true)) {
        throw new InvalidArgumentException('Região inválida.');
    }
    $weight = (float)radar_str($input['weight'] ?? 1.0);
    if ($weight < 0.5 || $weight > 1.5) {
        throw new InvalidArgumentException('O peso editorial deve ficar entre 0,5 e 1,5.');
    }
    $kind = ($input['kind'] ?? 'rss') === 'trends' ? 'trends' : 'rss';
    return ['name' => $name, 'url' => $url, 'category' => $category, 'region' => $region, 'weight' => round($weight, 2), 'kind' => $kind];
}

function radar_source_create(array $input): int
{
    $row = radar_source_validate($input);
    if (radar_db_value('SELECT 1 FROM sources WHERE url = ?', [$row['url']]) !== null) {
        throw new InvalidArgumentException('Esta fonte já está cadastrada.');
    }
    if ((int)radar_db_value('SELECT COUNT(*) FROM sources') >= 150) {
        throw new InvalidArgumentException('Limite de 150 fontes atingido.');
    }
    $now = time();
    return radar_db_insert(
        'INSERT INTO sources (name, kind, url, category, region, weight, active, builtin, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 1, 0, ?, ?)',
        [$row['name'], 'rss', $row['url'], $row['category'], $row['region'], $row['weight'], $now, $now]
    );
}

function radar_source_update(int $id, array $input): void
{
    $current = radar_source_get($id);
    if ($current === null) {
        throw new InvalidArgumentException('Fonte não encontrada.');
    }
    $input['kind'] = $current['kind'];
    $row = radar_source_validate($input);
    $clash = radar_db_value('SELECT id FROM sources WHERE url = ? AND id <> ?', [$row['url'], $id]);
    if ($clash !== null) {
        throw new InvalidArgumentException('Outra fonte já usa este endereço.');
    }
    // A changed URL invalidates conditional-request validators.
    $reset = $row['url'] !== $current['url'];
    radar_db_exec(
        'UPDATE sources SET name = ?, url = ?, category = ?, region = ?, weight = ?, updated_at = ?' . ($reset ? ', etag = NULL, last_modified = NULL' : '') . ' WHERE id = ?',
        [$row['name'], $row['url'], $row['category'], $row['region'], $row['weight'], time(), $id]
    );
}

function radar_source_set_active(int $id, bool $active): void
{
    radar_db_exec('UPDATE sources SET active = ?, updated_at = ? WHERE id = ?', [$active ? 1 : 0, time(), $id]);
}

function radar_source_delete(int $id): void
{
    radar_db_exec('DELETE FROM sources WHERE id = ?', [$id]);
}

/* ---------- classification ---------- */

function radar_fold(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = strtr($text, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
    ]);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $text) ?? '');
}

/** @return array<string,array<int,string>> folded keyword lists per category */
function radar_category_keywords(): array
{
    return [
        'Futebol' => ['futebol', 'gol', 'gols', 'goleada', 'brasileirao', 'libertadores', 'sul americana', 'copa do brasil', 'flamengo', 'corinthians', 'palmeiras', 'santos', 'vasco', 'botafogo', 'fluminense', 'gremio', 'cruzeiro', 'atletico mg', 'selecao', 'selecao brasileira', 'neymar', 'ancelotti', 'rodada', 'escalacao', 'tecnico', 'uefa', 'champions league', 'fifa', 'copa do mundo', 'mbappe', 'messi', 'cristiano ronaldo', 'vinicius junior', 'raphinha', 'serie a', 'serie b', 'estadio', 'artilheiro', 'zagueiro', 'atacante', 'goleiro', 'campeonato', 'rebaixamento', 'paulistao', 'carioca', 'mineiro', 'campeonato paulista', 'estadual', 'copa america', 'eliminatorias', 'clube', 'torcida', 'arbitro'],
        'Esportes' => ['formula 1', 'f1', 'ufc', 'mma', 'volei', 'basquete', 'nba', 'tenis', 'olimpiadas', 'jogos olimpicos', 'paralimpiadas', 'atletismo', 'natacao', 'surf', 'judo', 'boxe', 'grande premio', 'verstappen', 'hamilton', 'atleta', 'atletas', 'medalha', 'ginastica', 'rugby', 'nfl', 'ciclismo', 'maratona', 'golfe', 'esporte', 'esportes', 'automobilismo', 'stock car', 'skate', 'handebol'],
        'Política' => ['lula', 'bolsonaro', 'governo', 'congresso', 'senado', 'camara dos deputados', 'stf', 'ministro', 'ministra', 'eleicao', 'eleicoes', 'deputado', 'deputada', 'senador', 'senadora', 'planalto', 'moraes', 'prefeito', 'governador', 'reforma tributaria', 'impeachment', 'tse', 'pec', 'partido', 'oposicao', 'pgr', 'cpi', 'ministerio publico', 'tarcisio', 'haddad', 'dino', 'fachin', 'lira', 'alcolumbre'],
        'Famosos' => ['famoso', 'famosa', 'celebridade', 'bbb', 'ator', 'atriz', 'cantor', 'cantora', 'influenciador', 'influenciadora', 'influencer', 'namoro', 'casamento', 'separacao', 'novela', 'big brother', 'anitta', 'virginia', 'apresentador', 'apresentadora', 'ex bbb', 'a fazenda', 'gravidez', 'famosos', 'fofoca'],
        'Economia' => ['dolar', 'inflacao', 'ipca', 'selic', 'juros', 'bolsa', 'ibovespa', 'pib', 'mercado', 'economia', 'banco central', 'imposto', 'salario minimo', 'desemprego', 'petroleo', 'fiscal', 'orcamento', 'tarifa', 'receita federal', 'inss', 'fgts', 'pix', 'investimento', 'acoes', 'bitcoin', 'cripto', 'varejo', 'credito', 'divida', 'consumidor', 'preco', 'precos', 'emprego', 'agronegocio'],
        'Tecnologia' => ['tecnologia', 'inteligencia artificial', 'iphone', 'android', 'apple', 'google', 'microsoft', 'whatsapp', 'instagram', 'tiktok', 'openai', 'chatgpt', 'aplicativo', 'smartphone', 'celular', 'software', 'hacker', 'ciberataque', 'startup', 'samsung', 'nvidia', 'robo', 'tesla', '5g', 'windows', 'playstation', 'xbox', 'nintendo', 'gamer', 'cibernetico', 'big tech'],
        'Notícias internacionais' => ['eua', 'estados unidos', 'trump', 'biden', 'harris', 'china', 'russia', 'ucrania', 'israel', 'gaza', 'hamas', 'europa', 'onu', 'otan', 'franca', 'alemanha', 'reino unido', 'argentina', 'milei', 'venezuela', 'maduro', 'japao', 'india', 'mexico', 'canada', 'italia', 'espanha', 'portugal', 'putin', 'zelensky', 'macron', 'papa', 'vaticano', 'internacional'],
        'Entretenimento' => ['filme', 'serie', 'cinema', 'netflix', 'streaming', 'estreia', 'trailer', 'musica', 'album', 'festival', 'show', 'teatro', 'oscar', 'grammy', 'disney', 'hbo', 'prime video', 'globoplay', 'lancamento', 'documentario', 'turne', 'rock in rio', 'carnaval', 'bilheteria'],
        'Curiosidades' => ['curiosidade', 'descoberta', 'cientistas', 'pesquisa', 'estudo', 'espaco', 'nasa', 'animal', 'planeta', 'misterio', 'incrivel', 'raro', 'recorde', 'arqueologia', 'dinossauro', 'fossil', 'galaxia', 'asteroide', 'cometa', 'oceano', 'natureza', 'insolito'],
    ];
}

/**
 * Choose a category for a headline. Keyword hits in the title decide; a source's pinned
 * category is only a weak prior (+2) and the fallback, so a sports outlet's athletics
 * story or a politics outlet's market story is not forced into the outlet's own beat.
 * Returns 'Geral' (or 'Assuntos virais' when trending) when nothing matches; never a guess.
 */
function radar_classify(string $title, string $summary = '', string $sourceCategory = 'auto', bool $trending = false): string
{
    static $compiled = null;
    if ($compiled === null) {
        $compiled = [];
        foreach (radar_category_keywords() as $category => $keywords) {
            $compiled[$category] = array_map(static fn(string $keyword): string => ' ' . radar_fold($keyword) . ' ', $keywords);
        }
    }
    $pinned = $sourceCategory !== 'auto' && in_array($sourceCategory, radar_category_list(), true) ? $sourceCategory : null;
    $haystack = ' ' . radar_fold($title) . ' ';
    $secondary = $summary === '' ? '' : ' ' . radar_fold($summary) . ' ';
    $best = null;
    $bestScore = 0;
    foreach ($compiled as $category => $needles) {
        $score = $category === $pinned ? 2 : 0;
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                $score += 3;
            } elseif ($secondary !== '' && str_contains($secondary, $needle)) {
                $score += 1;
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $category;
        }
    }
    if ($best !== null && $bestScore >= 3) {
        return $best;
    }
    if ($pinned !== null) {
        return $pinned;
    }
    return $trending ? 'Assuntos virais' : 'Geral';
}
