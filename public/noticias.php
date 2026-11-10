<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$filters = radar_news_filters($_GET);
if ($_GET === []) {
    $filters['window'] = 24;
}
$result = radar_news_query($filters);
$pages = max(1, (int)ceil($result['total'] / $filters['limit']));
$base = [
    'janela' => $filters['window'] ?: 'tudo', 'ordem' => $filters['sort'], 'categoria' => $filters['category'], 'q' => $filters['q'],
    'com_imagem' => $filters['with_image'] ? 1 : 0,
];
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
        <a class="chip<?= $filters['category'] === '' ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['categoria' => ''] + $base)) ?>">Todas</a>
        <?php foreach (radar_category_list() as $category): ?>
            <a class="chip<?= $filters['category'] === $category ? ' on' : '' ?>" href="<?= radar_h(radar_url('noticias.php', ['categoria' => $category] + $base)) ?>"><?= radar_h($category) ?></a>
        <?php endforeach; ?>
    </div>
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
