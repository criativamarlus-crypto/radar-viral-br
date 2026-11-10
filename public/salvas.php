<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    radar_form_guard('salvas.php');
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['op'] ?? '') === 'note') {
        radar_news_set_saved($id, true, (string)($_POST['note'] ?? ''));
        radar_flash_set('ok', 'Anotação salva.');
    } elseif (($_POST['op'] ?? '') === 'remove') {
        radar_news_set_saved($id, false);
        radar_flash_set('ok', 'Notícia removida das salvas.');
    }
    radar_redirect('salvas.php');
}

// Saved stories are kept until removed, regardless of age.
$rows = radar_db_all('SELECT * FROM news WHERE saved = 1 ORDER BY saved_at DESC LIMIT 200');
$items = array_map('radar_news_decode', $rows);
$csrf = radar_csrf_token();

radar_page_start('Notícias salvas', 'salvas.php');
?>
<div class="page-head"><div><h1>Notícias salvas</h1><p>Pautas que você selecionou. Ficam guardadas até você remover.</p></div></div>
<?php if ($items === []): ?>
    <div class="empty-state"><h3>Nenhuma notícia salva ainda</h3><p>Use “Salvar” nos cards de <a href="noticias.php">Notícias em alta</a>.</p></div>
<?php else: ?>
    <div class="cards">
        <?php foreach ($items as $news): ?>
            <div class="stack">
                <?= radar_news_card($news) ?>
                <form method="post" action="salvas.php" class="panel form-grid">
                    <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="op" value="note"><input type="hidden" name="id" value="<?= (int)$news['id'] ?>">
                    <label>Anotação<textarea name="note" rows="2" maxlength="1000" placeholder="Ângulo, o que conferir, ideia de piada…"><?= radar_h($news['note']) ?></textarea></label>
                    <button class="btn ghost small" type="submit">Salvar anotação</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php radar_page_end();
