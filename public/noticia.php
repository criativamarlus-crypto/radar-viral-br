<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$id = (int)($_GET['id'] ?? 0);
$news = $id > 0 ? radar_news_get($id) : null;
if ($news === null) {
    http_response_code(404);
    radar_page_start('Notícia não encontrada', 'noticias.php');
    echo '<div class="empty-state"><h3>Notícia não encontrada</h3><p>Ela pode ter sido removida depois de 14 dias sem aparecer nos feeds. <a href="noticias.php">Voltar às notícias</a>.</p></div>';
    radar_page_end();
    exit;
}
$scripts = radar_scripts_list(10, $id);
$shot = radar_screenshot_config();
$canOg = radar_og_fetchable($news['url']);
$cover = $news['image'];
$published = $news['published_at'] !== null ? date('d/m/Y H:i', (int)strtotime($news['published_at'])) : null;

radar_page_start($news['title'], 'noticias.php');
?>
<p><a href="noticias.php">← Notícias em alta</a></p>
<div class="detail">
    <div class="stack">
        <div>
            <span class="eyebrow"><?= radar_h($news['category']) ?></span>
            <h1><?= radar_h($news['title']) ?></h1>
            <p class="meta"><?= radar_h($news['source']) ?> ·
                <?= $published !== null ? 'publicada em ' . radar_h($published) : 'horário de publicação não informado pela fonte (visto ' . radar_h(radar_age_label($news['age_minutes'])) . ')' ?></p>
        </div>

        <?php if ($cover !== null): ?>
            <div>
                <div class="hero-image"><img src="<?= radar_h($cover) ?>" alt="Capa da matéria" referrerpolicy="no-referrer"></div>
                <p class="credit">Crédito/fonte: <?= radar_h($news['image_credit'] ?? $news['source']) ?>. <?= radar_h((string)$news['image_note']) ?></p>
            </div>
        <?php endif; ?>

        <?php if ($news['summary'] !== ''): ?>
            <div class="panel"><h2>Resumo do veículo</h2><p><?= radar_h($news['summary']) ?></p><p class="hint"><?= radar_h((string)$news['summary_origin']) ?></p></div>
        <?php else: ?>
            <div class="notice warn">A fonte não forneceu resumo. Abra a matéria original e confira os fatos antes de gravar.</div>
        <?php endif; ?>

        <div class="panel">
            <h2>Fonte original</h2>
            <p><a href="<?= radar_h($news['url']) ?>" target="_blank" rel="noopener noreferrer"><?= radar_h($news['source']) ?> — abrir matéria <?= radar_icon('ext') ?></a></p>
            <?php if (!$canOg): ?><p class="hint">Este link passa pelo Google Notícias, que não revela a página original. Veja abaixo outros veículos que cobrem o mesmo assunto.</p><?php endif; ?>
            <?php if ($news['earliest_in_cluster'] !== null): ?>
                <p class="hint">Veículo com horário mais antigo neste assunto: <strong><?= radar_h($news['earliest_in_cluster']['source']) ?></strong> (<?= radar_h(date('d/m H:i', (int)strtotime($news['earliest_in_cluster']['published_at']))) ?>). É uma pista, não prova de quem deu primeiro.</p>
            <?php endif; ?>
            <?php if ($news['trend_hits'] !== []): ?>
                <p class="hint">Em alta no Google Trends Brasil: <?= radar_h(implode(', ', array_map(static fn(array $t): string => '“' . $t['query'] . '” (#' . $t['rank'] . ($t['traffic'] ? ', ' . $t['traffic'] : '') . ')', $news['trend_hits']))) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($news['same_story'] !== [] || $news['related'] !== []): ?>
            <div class="panel">
                <h2>Assuntos relacionados · mesma história em outros veículos</h2>
                <ul class="reasons">
                    <?php foreach ($news['same_story'] as $mate): ?>
                        <li><a href="<?= radar_h($mate['url']) ?>" target="_blank" rel="noopener noreferrer"><?= radar_h($mate['title']) ?></a><span class="meta"><?= radar_h($mate['source']) ?></span></li>
                    <?php endforeach; ?>
                    <?php foreach ($news['related'] as $rel): ?>
                        <li><a href="<?= radar_h($rel['url']) ?>" target="_blank" rel="noopener noreferrer"><?= radar_h($rel['title']) ?></a><span class="meta"><?= radar_h($rel['source']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="panel">
            <h2>Roteiros desta notícia</h2>
            <?php if ($scripts === []): ?>
                <p class="muted">Nenhum roteiro ainda.</p>
            <?php else: ?>
                <ul class="reasons">
                    <?php foreach ($scripts as $script): ?>
                        <li><a href="roteiro.php?id=<?= (int)$script['id'] ?>"><?= radar_h(mb_substr($script['hook'], 0, 70)) ?></a><span class="meta"><?= radar_h(date('d/m H:i', (int)strtotime($script['created_at']))) ?> · <?= radar_h($script['mode'] === 'ia' ? 'IA' : 'rascunho') ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <aside class="stack">
        <div class="panel">
            <div class="section-head"><h2>Potencial viral</h2><span class="score inline <?= radar_score_class($news['score']) ?>"><b><?= (int)$news['score'] ?></b><small>de 100</small></span></div>
            <p class="hint"><?= radar_h($news['score_note']) ?></p>
            <p><?= radar_h($news['score_summary']) ?></p>
            <ul class="reasons">
                <?php foreach ($news['score_reasons'] as $reason): ?>
                    <li><span><strong><?= radar_h($reason['label']) ?></strong></span><span><?= (int)$reason['points'] ?>/<?= (int)$reason['max'] ?></span>
                        <progress max="<?= (int)$reason['max'] ?>" value="<?= (int)$reason['points'] ?>"></progress><small><?= radar_h($reason['text']) ?></small></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="panel">
            <h2>Preparar vídeo</h2>
            <div class="stack">
                <a class="btn primary" href="estudio.php?news=<?= (int)$news['id'] ?>">Gerar react</a>
                <button type="button" class="btn ghost" data-save="<?= (int)$news['id'] ?>" aria-pressed="<?= $news['saved'] ? 'true' : 'false' ?>"><?= $news['saved'] ? 'Salva' : 'Salvar' ?></button>
            </div>
        </div>

        <div class="panel">
            <h2>Imagens desta notícia</h2>
            <p class="hint">Use capa da fonte, captura real da página ou um print que você mesmo anexa. O Radar nunca fabrica prints.</p>
            <div class="stack">
                <?php if ($cover !== null): ?>
                    <button type="button" class="btn ghost" data-act="import_cover" data-news-id="<?= (int)$news['id'] ?>" data-busy="Baixando…">Guardar a capa original no Estúdio</button>
                <?php endif; ?>
                <?php if ($canOg): ?>
                    <button type="button" class="btn ghost" data-act="fetch_og" data-news-id="<?= (int)$news['id'] ?>" data-busy="Lendo a página…">Buscar imagem Open Graph da matéria</button>
                <?php endif; ?>
                <button type="button" class="btn ghost" data-act="screenshot" data-news-id="<?= (int)$news['id'] ?>" data-busy="Capturando…" <?= $shot['template'] === '' ? 'title="Configure um serviço em Integrações"' : '' ?>>Capturar tela da página (serviço externo)</button>
                <?php if ($shot['template'] === ''): ?><p class="hint">Captura automática desativada: configure um serviço em <a href="integracoes.php">Integrações</a>.</p><?php endif; ?>
                <form data-upload class="form-grid" enctype="multipart/form-data">
                    <input type="hidden" name="kind" value="cover"><input type="hidden" name="news_id" value="<?= (int)$news['id'] ?>">
                    <label>Anexar imagem manualmente<input type="file" name="file" accept="image/jpeg,image/png,image/webp"></label>
                    <label>Crédito / origem<input name="credit" maxlength="200" placeholder="Ex.: print do G1 em 10/11"></label>
                    <button class="btn ghost" type="submit">Enviar imagem</button>
                </form>
            </div>
            <?php if ($news['media'] !== []): ?>
                <div class="media-grid">
                    <?php foreach ($news['media'] as $media): ?>
                        <div class="media-item">
                            <img src="media.php?id=<?= radar_h($media['id']) ?>" alt="<?= radar_h($media['label']) ?>" loading="lazy">
                            <div class="info"><span><?= radar_h($media['label']) ?></span><?php if ($media['credit']): ?><span>Crédito: <?= radar_h($media['credit']) ?></span><?php endif; ?>
                                <a class="btn ghost small" href="estudio.php?news=<?= (int)$news['id'] ?>&amp;cover=<?= radar_h($media['id']) ?>">Usar no Estúdio</a>
                                <button type="button" class="btn danger small" data-act="media_delete" data-id="<?= radar_h($media['id']) ?>" data-confirm="Excluir esta imagem?">Excluir</button></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="panel"><p class="hint"><?= radar_h($news['verification']) ?></p></div>
    </aside>
</div>
<?php radar_page_end();
