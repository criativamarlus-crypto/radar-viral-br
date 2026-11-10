<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$stats = radar_dashboard_stats();
$trends = array_slice(radar_trends_latest(), 0, 12);
$top = radar_news_query(radar_news_filters(['janela' => 24, 'ordem' => 'viral', 'limite' => 6]));
$character = radar_character_primary();
$broken = radar_db_all("SELECT name, last_error, last_fetched_at FROM sources WHERE active = 1 AND last_status = 'error' ORDER BY name LIMIT 8");
$last = $stats['last_collect_at'];
$run = $stats['last_run'];

radar_page_start('Dashboard', 'index.php');
?>
<div class="page-head">
    <div>
        <h1>Notícias para seus próximos reacts</h1>
        <p>Coleta automática a cada 30 minutos nas fontes ativas. Todos os números abaixo vêm das coletas reais.</p>
    </div>
    <a class="btn ghost" href="noticias.php">Ver todas as notícias</a>
</div>

<section class="stats" aria-label="Indicadores">
    <div class="stat blue"><b><?= (int)$stats['collected_today'] ?></b><span>Notícias coletadas hoje</span></div>
    <div class="stat red"><b><?= (int)$stats['new_last_3h'] ?></b><span>Novidades das últimas 3 horas</span></div>
    <div class="stat yellow"><b><?= (int)$stats['saved'] ?></b><span>Notícias selecionadas</span><small>salvas por você</small></div>
    <div class="stat"><b><?= (int)$stats['reacts_prepared'] ?></b><span>Reacts preparados</span><small><?= (int)$stats['projects'] ?> projeto(s) no Estúdio</small></div>
    <div class="stat"><b><?= $last === null ? '—' : radar_h(radar_age_label(max(0, (int)round((time() - $last) / 60)))) ?></b><span>Última atualização</span>
        <small><?= $run === null ? 'Nenhuma coleta ainda' : radar_h($run['sources_ok'] . ' fontes ok · ' . $run['sources_failed'] . ' com falha') ?></small></div>
</section>

<?php if ($stats['total_news'] === 0): ?>
    <section class="section">
        <div class="empty-state">
            <h3>Ainda não há notícias coletadas</h3>
            <p>O painel buscará as fontes automaticamente ao abrir. Você também pode iniciar agora.</p>
            <button type="button" class="btn primary" id="first-collect" data-act="collect" data-busy="Coletando…">Fazer a primeira coleta</button>
        </div>
    </section>
<?php endif; ?>

<?php if ($trends !== []): ?>
    <section class="section" aria-labelledby="trends-h">
        <div class="section-head"><h2 id="trends-h">Em alta no Google Trends Brasil</h2><span class="muted">Buscas populares, não verificação de fatos</span></div>
        <div class="trend-list">
            <?php foreach ($trends as $trend): ?>
                <a class="trend" href="<?= radar_h(radar_url('noticias.php', ['q' => $trend['query']])) ?>">
                    <b><span class="rank">#<?= (int)$trend['rank'] ?></span><?= radar_h($trend['query']) ?></b>
                    <small><?= $trend['traffic'] !== null ? radar_h($trend['traffic']) . ' buscas' : 'em alta' ?></small>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="section" aria-labelledby="top-h">
    <div class="section-head">
        <h2 id="top-h">Maior potencial viral · últimas 24 h</h2>
        <span class="muted">Pontuação editorial estimada pelo Radar (0–100); não é audiência medida.</span>
    </div>
    <?php if ($top['items'] === []): ?>
        <div class="empty-state"><h3>Nada nas últimas 24 horas</h3><p>Atualize as fontes ou ative mais fontes em “Fontes monitoradas”.</p></div>
    <?php else: ?>
        <div class="cards">
            <?php foreach ($top['items'] as $news) { echo radar_news_card($news); } ?>
        </div>
    <?php endif; ?>
</section>

<section class="section grid-2">
    <div class="promo">
        <?php if ($character !== null): ?>
            <img src="media.php?id=<?= radar_h($character['id']) ?>" alt="Imagem de referência do personagem">
        <?php else: ?>
            <img src="assets/character-placeholder.svg" alt="Espaço reservado para o personagem">
        <?php endif; ?>
        <div>
            <h2>Estúdio de React</h2>
            <p class="muted">Personagem fixo + manchete + roteiro + prompt de vídeo.</p>
            <a class="btn primary small" href="estudio.php">Abrir estúdio</a>
            <?php if ($character === null): ?><a class="btn ghost small" href="personagem.php">Cadastrar personagem</a><?php endif; ?>
        </div>
    </div>
    <div class="panel">
        <h2>Fontes com problema</h2>
        <?php if ($broken === []): ?>
            <p class="muted">Nenhuma fonte ativa com erro na última coleta.</p>
        <?php else: ?>
            <ul class="reasons">
                <?php foreach ($broken as $source): ?>
                    <li><span><?= radar_h($source['name']) ?></span><span class="pill bad">erro</span><small><?= radar_h((string)$source['last_error']) ?></small></li>
                <?php endforeach; ?>
            </ul>
            <p class="muted">Veja detalhes em <a href="coletas.php">Histórico de coleta</a>.</p>
        <?php endif; ?>
    </div>
</section>
<p class="footer-note">As notícias são lidas de feeds RSS/Atom públicos e do Google Trends. O Radar não verifica fatos: confira sempre a matéria original antes de gravar.</p>
<?php radar_page_end();
