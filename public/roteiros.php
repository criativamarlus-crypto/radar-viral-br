<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$scripts = radar_scripts_list(100);
$projects = radar_projects_list(50);

radar_page_start('Roteiros criados', 'roteiros.php');
?>
<div class="page-head"><div><h1>Roteiros criados</h1><p>Tudo que o Radar já gerou para você. Abra para editar, copiar o prompt ou exportar.</p></div></div>
<?php if ($scripts === []): ?>
    <div class="empty-state"><h3>Nenhum roteiro ainda</h3><p>Use “Gerar react com IA” em qualquer card de <a href="noticias.php">Notícias em alta</a>.</p></div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Criado</th><th>Notícia</th><th>Gancho</th><th>Personagem</th><th>Origem</th><th>Duração</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($scripts as $script): ?>
                <tr>
                    <td><?= radar_h(date('d/m H:i', (int)strtotime($script['created_at']))) ?></td>
                    <td><?= radar_h(mb_substr($script['news_title'], 0, 90)) ?><br><span class="meta"><?= radar_h($script['news_source']) ?></span></td>
                    <td><?= radar_h(mb_substr($script['hook'], 0, 80)) ?></td>
                    <td><?= radar_h($script['character_name']) ?><br><span class="meta">versão <?= (int)$script['character_version'] ?><?= $script['format'] === 'tv3' ? ' · 3 cenas' : '' ?><?= $script['update_of'] !== null ? ' · atualização do #' . (int)$script['update_of'] : '' ?></span></td>
                    <td><span class="pill <?= $script['mode'] === 'ia' ? 'ok' : 'warn' ?>"><?= $script['mode'] === 'ia' ? 'IA' : 'rascunho' ?></span></td>
                    <td>~<?= (int)$script['estimated_seconds'] ?>s</td>
                    <td><div class="row-actions"><a class="btn ghost small" href="roteiro.php?id=<?= (int)$script['id'] ?>">Abrir</a>
                        <button type="button" class="btn danger small" data-act="script_delete" data-id="<?= (int)$script['id'] ?>" data-confirm="Excluir este roteiro?">Excluir</button></div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<section class="section">
    <div class="section-head"><h2>Projetos do Estúdio</h2><a class="btn ghost small" href="estudio.php">Novo projeto</a></div>
    <?php if ($projects === []): ?>
        <p class="muted">Nenhum projeto salvo no Estúdio.</p>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Atualizado</th><th>Título</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($projects as $project): ?>
                <tr><td><?= radar_h(date('d/m H:i', (int)strtotime($project['updated_at']))) ?></td><td><?= radar_h($project['title']) ?></td>
                    <td><div class="row-actions"><a class="btn ghost small" href="estudio.php?projeto=<?= (int)$project['id'] ?>">Abrir</a>
                        <button type="button" class="btn danger small" data-act="project_delete" data-id="<?= (int)$project['id'] ?>" data-confirm="Excluir este projeto?">Excluir</button></div></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
<?php radar_page_end();
