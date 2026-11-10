<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$id = (int)($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    radar_form_guard('roteiro.php?id=' . $id);
    try {
        radar_script_update($id, $_POST);
        radar_flash_set('ok', 'Roteiro atualizado. O prompt de vídeo foi refeito com o texto novo.');
    } catch (InvalidArgumentException $error) {
        radar_flash_set('error', $error->getMessage());
    }
    radar_redirect('roteiro.php?id=' . $id);
}
$script = $id > 0 ? radar_script_get($id) : null;
if ($script === null) {
    http_response_code(404);
    radar_page_start('Roteiro não encontrado', 'roteiros.php');
    echo '<div class="empty-state"><h3>Roteiro não encontrado</h3><p><a href="roteiros.php">Voltar aos roteiros</a></p></div>';
    radar_page_end();
    exit;
}
$csrf = radar_csrf_token();
$expressions = radar_character_expressions();
$scenes = radar_character_scenes();

radar_page_start('Roteiro de react', 'roteiros.php');
?>
<p><a href="roteiros.php">← Roteiros criados</a></p>
<div class="page-head">
    <div><h1>Roteiro de react</h1>
        <p><?= radar_h($script['mode_label']) ?> · <?= (int)$script['duration'] ?> s pedidos · ~<?= (int)$script['estimated_seconds'] ?> s falados · tom <?= radar_h($script['tone']) ?></p></div>
    <div class="row-actions">
        <a class="btn primary" href="estudio.php?roteiro=<?= (int)$script['id'] ?>">Abrir no Estúdio</a>
        <a class="btn ghost" href="api.php?action=script&amp;id=<?= (int)$script['id'] ?>&amp;format=txt">Exportar .txt</a>
        <?php if ($script['news_id'] !== null): ?><a class="btn ghost" href="noticia.php?id=<?= (int)$script['news_id'] ?>">Ver notícia</a><?php endif; ?>
    </div>
</div>

<div class="panel"><strong><?= radar_h($script['news_title']) ?></strong><br>
    <a href="<?= radar_h($script['news_url']) ?>" target="_blank" rel="noopener noreferrer"><?= radar_h($script['news_source']) ?> — conferir a matéria original <?= radar_icon('ext') ?></a></div>

<?php if ($script['warnings'] !== [] || $script['fact_check'] !== []): ?>
    <div class="warnings section">
        <?php foreach ($script['warnings'] as $warning): ?><div class="notice warn" role="status"><?= radar_h($warning) ?></div><?php endforeach; ?>
        <?php if ($script['fact_check'] !== []): ?><div class="notice info"><strong>Conferir antes de gravar:</strong><ul><?php foreach ($script['fact_check'] as $note): ?><li><?= radar_h($note) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    </div>
<?php endif; ?>

<div class="detail">
    <form method="post" action="roteiro.php" class="stack">
        <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$script['id'] ?>">
        <div class="panel">
            <h2>A. Headlines virais <span class="muted">(fiéis aos fatos)</span></h2>
            <?php foreach ($script['headlines'] as $index => $headline): ?>
                <div class="headline-option"><span id="hl-<?= $index ?>"><?= radar_h($headline) ?></span><button type="button" class="btn ghost small" data-copy="hl-<?= $index ?>"><?= radar_icon('copy') ?>Copiar</button></div>
            <?php endforeach; ?>
            <label>Adicionar/usar outra headline<input name="headline" maxlength="160" placeholder="Opcional: sua própria headline"></label>
        </div>
        <div class="panel script-block">
            <div class="head"><span>B. Gancho (3 primeiros segundos)</span><button type="button" class="btn ghost small" data-copy="f-hook">Copiar</button></div>
            <textarea id="f-hook" name="hook" rows="2" maxlength="200"><?= radar_h($script['hook']) ?></textarea>
        </div>
        <div class="panel script-block">
            <div class="head"><span>C. Roteiro principal</span><button type="button" class="btn ghost small" data-copy="f-script">Copiar</button></div>
            <textarea id="f-script" name="script" rows="6" maxlength="1800"><?= radar_h($script['script']) ?></textarea>
        </div>
        <div class="panel script-block">
            <div class="head"><span>D. Comentário do personagem (opinião)</span><button type="button" class="btn ghost small" data-copy="f-commentary">Copiar</button></div>
            <textarea id="f-commentary" name="commentary" rows="3" maxlength="600"><?= radar_h($script['commentary']) ?></textarea>
            <p class="hint">Opinião fica separada da informação: não afirme culpa nem transforme suspeita em fato.</p>
        </div>
        <div class="panel script-block">
            <div class="head"><span>E. CTA</span><button type="button" class="btn ghost small" data-copy="f-cta">Copiar</button></div>
            <textarea id="f-cta" name="cta" rows="2" maxlength="250"><?= radar_h($script['cta']) ?></textarea>
        </div>
        <div class="panel">
            <div class="field-row">
                <label>Expressão do personagem<select name="expression"><?php foreach ($expressions as $key => $info): ?><option value="<?= radar_h($key) ?>"<?= $script['expression'] === $key ? ' selected' : '' ?>><?= radar_h($info['label']) ?></option><?php endforeach; ?></select></label>
                <label>Cenário<select name="scene"><?php foreach ($scenes as $key => $label): ?><option value="<?= radar_h($key) ?>"<?= $script['scene'] === $key ? ' selected' : '' ?>><?= radar_h($label) ?></option><?php endforeach; ?></select></label>
            </div>
            <button class="btn primary" type="submit">Salvar alterações e refazer o prompt</button>
        </div>
    </form>

    <aside class="stack">
        <div class="panel script-block">
            <div class="head"><span>Texto falado completo · ~<?= (int)$script['estimated_seconds'] ?> s</span><button type="button" class="btn ghost small" data-copy="spoken">Copiar</button></div>
            <textarea id="spoken" rows="8" readonly><?= radar_h($script['spoken']) ?></textarea>
        </div>
        <div class="panel script-block">
            <div class="head"><span>F. Prompt de vídeo (personagem fixo)</span><button type="button" class="btn primary small" data-copy="video-prompt">Copiar prompt</button></div>
            <textarea id="video-prompt" rows="14" readonly class="mono"><?= radar_h($script['video_prompt']) ?></textarea>
            <p class="hint">Envie junto as imagens de referência de <a href="personagem.php">Meu Personagem</a> à ferramenta de vídeo. O bloco do personagem é fixo e não muda.</p>
        </div>
    </aside>
</div>
<?php radar_page_end();
