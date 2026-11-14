<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$filters = radar_news_filters($_GET);
if ($_GET === []) {
    $filters['window'] = 24;
}
$result = radar_news_query($filters);
$counts = radar_news_category_counts($filters);
$pages = max(1, (int)ceil($result['total'] / $filters['limit']));
$base = [
    'janela' => $filters['window'] ?: 'tudo', 'ordem' => $filters['sort'], 'categoria' => $filters['category'], 'q' => $filters['q'],
    'com_imagem' => $filters['with_image'] ? 1 : 0,
    'regiao' => $filters['job_region'], 'estado' => $filters['job_state'], 'cidade' => $filters['job_city'], 'idade' => $filters['job_age'],
];
// Changing the category drops the vacancy filters (region, state, city, age).
$noJob = ['regiao' => '', 'estado' => '', 'cidade' => '', 'idade' => ''];
$jobOn = in_array(RADAR_JOB_CATEGORY, $filters['categories'], true);
$windows = [0 => 'Tudo (72 h)', 1 => 'Última hora', 3 => '3 horas', 6 => '6 horas', 24 => '24 horas'];

radar_page_start('Notícias em alta', 'noticias.php');
?>
<div class="page-head">
    <div>
        <h1>Notícias em alta</h1>
        <p><?= (int)$result['total'] ?> assunto(s) encontrados. Cada card reúne as matérias do mesmo assunto em vários veículos.</p>
    </div>
</div>

<form class="filters" method="get" action="noticias.php" role="search">
    <div class="search-row">
        <input type="search" name="q" value="<?= radar_h($filters['q']) ?>" placeholder="Buscar por manchete, resumo ou veículo" aria-label="Buscar notícias" maxlength="100">
        <input type="hidden" name="janela" value="<?= radar_h($filters['window'] ?: 'tudo') ?>">
        <input type="hidden" name="ordem" value="<?= radar_h($filters['sort']) ?>">
        <input type="hidden" name="categoria" value="<?= radar_h($filters['category']) ?>">
        <?php foreach (['regiao' => 'job_region', 'estado' => 'job_state', 'idade' => 'job_age'] as $field => $key): if ($filters[$key] !== ''): ?><input type="hidden" name="<?= $field ?>" value="<?= radar_h($filters[$key]) ?>"><?php endif; endforeach; ?>
        <button class="btn primary" type="submit">Buscar</button>
    </div>
    <div class="chips" role="group" aria-label="Período">
        <span class="label">Período</span>
        <?php foreach ($windows as $hours => $label): ?>
            <a class="chip<?= $filters['window'] === $hours ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['janela' => $hours ?: 'tudo'] + $base)) ?>"><?= radar_h($label) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="chips" role="group" aria-label="Ordenação">
        <span class="label">Ordem</span>
        <a class="chip<?= $filters['sort'] === 'viral' ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['ordem' => 'viral'] + $base)) ?>">Maior potencial viral</a>
        <a class="chip<?= $filters['sort'] === 'recent' ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['ordem' => 'recent'] + $base)) ?>">Mais recentes</a>
        <a class="chip<?= $filters['with_image'] ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['com_imagem' => $filters['with_image'] ? 0 : 1] + $base)) ?>">Só com imagem</a>
    </div>
    <div class="chips" role="group" aria-label="Categoria">
        <span class="label">Categoria</span>
        <a class="chip<?= $filters['category'] === '' ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['categoria' => ''] + $noJob + $base)) ?>">Todas</a>
        <?php foreach (radar_category_list() as $category):
            $isOn = in_array($category, $filters['categories'], true);
            $next = $isOn ? array_values(array_diff($filters['categories'], [$category])) : array_merge($filters['categories'], [$category]); ?>
            <a class="chip<?= $isOn ? ' on' : '' ?>" aria-pressed="<?= $isOn ? 'true' : 'false' ?>" href="<?= radar_h(radar_url('noticias.php', ['categoria' => implode(',', $next)] + $noJob + $base)) ?>"><?= radar_h($category) ?><span class="count"><?= (int)($counts[$category] ?? 0) ?></span></a>
        <?php endforeach; ?>
        <span class="hint">Marque uma ou várias categorias.</span>
    </div>
    <?php if ($jobOn):
        $since = time() - ($filters['window'] > 0 ? $filters['window'] : 72) * 3600;
        $facets = radar_job_facets($since, $filters['job_state']);
        $stateInfo = radar_job_states();
        $ageLabels = radar_job_ages(); ?>
        <div class="chips" role="group" aria-label="Região das vagas">
            <span class="label">Região</span>
            <a class="chip<?= $filters['job_region'] === '' ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['regiao' => '', 'estado' => '', 'cidade' => ''] + $base)) ?>">Todo o Brasil</a>
            <?php foreach (radar_job_regions() as $region): ?>
                <a class="chip<?= $filters['job_region'] === $region ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['regiao' => $region, 'estado' => '', 'cidade' => ''] + $base)) ?>"><?= radar_h($region) ?><span class="count"><?= (int)($facets['regions'][$region] ?? 0) ?></span></a>
            <?php endforeach; ?>
        </div>
        <div class="chips" role="group" aria-label="Estado das vagas">
            <span class="label">Estado</span>
            <a class="chip<?= $filters['job_state'] === '' ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['estado' => '', 'cidade' => ''] + $base)) ?>">Todos</a>
            <?php foreach ($facets['states'] as $uf => $total): if (!isset($stateInfo[$uf])) { continue; } ?>
                <a class="chip<?= $filters['job_state'] === $uf ? ' on' : '' ?>" title="<?= radar_h($stateInfo[$uf]['name']) ?>" href="<?= radar_h(radar_url('noticias.php', ['estado' => $uf, 'regiao' => '', 'cidade' => ''] + $base)) ?>"><?= radar_h($uf) ?><span class="count"><?= (int)$total ?></span></a>
            <?php endforeach; ?>
        </div>
        <div class="chips" role="group" aria-label="Idade das vagas">
            <span class="label">Idade</span>
            <a class="chip<?= $filters['job_age'] === '' ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['idade' => ''] + $base)) ?>">Todas</a>
            <?php foreach ($ageLabels as $key => $label): ?>
                <a class="chip<?= $filters['job_age'] === $key ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['idade' => $key] + $base)) ?>"><?= radar_h($label) ?><span class="count"><?= (int)($facets['ages'][$key] ?? 0) ?></span></a>
            <?php endforeach; ?>
        </div>
        <div class="search-row">
            <label class="inline-label">Cidade
                <select name="cidade" data-autosubmit>
                    <option value="">Todas as cidades</option>
                    <?php foreach ($facets['cities'] as $city): ?>
                        <option value="<?= radar_h($city['city']) ?>"<?= $filters['job_city'] === $city['city'] ? ' selected' : '' ?>><?= radar_h($city['city'] . ' (' . $city['state'] . ') · ' . $city['total']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn ghost small" type="submit">Filtrar</button>
        </div>
        <p class="hint">São matérias reais de veículos que anunciam vagas (o link leva à matéria original, onde está como se candidatar). Região, estado, cidade e idade são lidos do texto de cada matéria; as que não dizem ficam sem local ou sem idade. Não é um site de vagas: confira a vaga na fonte.</p>
    <?php endif; ?>
</form>

<p class="hint">A pontuação 0–100 é uma estimativa própria do Radar Viral BR (atualidade, presença em várias fontes, tendências, interesse, discussão, clareza, imagem e viabilidade de react). Não é audiência comprovada nem ranking oficial do Google Trends.</p>

<?php if ($result['items'] === []): ?>
    <div class="empty-state"><h3>Nenhuma notícia com esses filtros</h3><p>Tente outro período ou categoria, ou <a href="noticias.php?janela=0">limpe os filtros</a>.</p></div>
<?php else: ?>
    <div class="cards">
        <?php foreach ($result['items'] as $news) { echo radar_news_card($news); } ?>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Paginação">
            <?php if ($filters['page'] > 1): ?><a class="btn ghost small" href="<?= radar_h(radar_url('noticias.php', ['pagina' => $filters['page'] - 1] + $base)) ?>">← Anterior</a><?php endif; ?>
            <span>Página <?= (int)$filters['page'] ?> de <?= (int)$pages ?></span>
            <?php if ($filters['page'] < $pages): ?><a class="btn ghost small" href="<?= radar_h(radar_url('noticias.php', ['pagina' => $filters['page'] + 1] + $base)) ?>">Próxima →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php radar_page_end();
