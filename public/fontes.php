<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();
radar_sources_seed();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    radar_form_guard('fontes.php');
    $op = (string)($_POST['op'] ?? '');
    $sourceId = (int)($_POST['id'] ?? 0);
    try {
        switch ($op) {
            case 'create':
                radar_source_create($_POST);
                radar_flash_set('ok', 'Fonte adicionada. Ela entra na próxima coleta.');
                break;
            case 'update':
                radar_source_update($sourceId, $_POST);
                radar_flash_set('ok', 'Fonte atualizada.');
                break;
            case 'toggle':
                radar_source_set_active($sourceId, ($_POST['active'] ?? '') === '1');
                radar_flash_set('ok', ($_POST['active'] ?? '') === '1' ? 'Fonte ativada.' : 'Fonte desativada.');
                break;
            case 'delete':
                radar_source_delete($sourceId);
                radar_flash_set('ok', 'Fonte excluída.');
                break;
            default:
                radar_flash_set('error', 'Ação inválida.');
        }
    } catch (InvalidArgumentException $error) {
        radar_flash_set('error', $error->getMessage());
    }
    radar_redirect('fontes.php' . ($op === 'update' ? '' : ''));
}

$editing = isset($_GET['editar']) ? radar_source_get((int)$_GET['editar']) : null;
$sources = radar_sources_all();
$active = count(array_filter($sources, static fn(array $s): bool => (int)$s['active'] === 1));
$categories = array_merge(['auto' => 'Automática (por palavras-chave)'], array_combine(radar_category_list(), radar_category_list()));
$csrf = radar_csrf_token();

radar_page_start('Fontes monitoradas', 'fontes.php');
?>
<div class="page-head">
    <div><h1>Fontes monitoradas</h1><p><?= (int)$active ?> de <?= count($sources) ?> fontes ativas. Use apenas feeds RSS/Atom públicos; respeite os termos de cada veículo.</p></div>
</div>

<div class="notice info">
    O feed do <strong>Google Notícias</strong> informa que é para leitura pessoal e não comercial. Mantenha este painel privado. Se preferir não usá-lo, desative as fontes “Google Notícias”.
</div>

<section class="panel" aria-labelledby="form-h">
    <h2 id="form-h"><?= $editing !== null ? 'Editar fonte' : 'Adicionar fonte' ?></h2>
    <form method="post" action="fontes.php" class="form-grid">
        <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>">
        <input type="hidden" name="op" value="<?= $editing !== null ? 'update' : 'create' ?>">
        <?php if ($editing !== null): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="field-row">
            <label>Nome<input name="name" required maxlength="80" value="<?= radar_h($editing['name'] ?? '') ?>" placeholder="Ex.: Portal X · Política"></label>
            <label>Endereço do feed (HTTPS)<input name="url" type="url" required maxlength="500" value="<?= radar_h($editing['url'] ?? '') ?>" placeholder="https://exemplo.com.br/feed/"></label>
        </div>
        <div class="field-row">
            <label>Categoria<select name="category"><?php foreach ($categories as $value => $label): ?><option value="<?= radar_h($value) ?>"<?= ($editing['category'] ?? 'auto') === $value ? ' selected' : '' ?>><?= radar_h($label) ?></option><?php endforeach; ?></select></label>
            <label>Região<select name="region"><option value="BR"<?= ($editing['region'] ?? 'BR') === 'BR' ? ' selected' : '' ?>>Brasil</option><option value="INT"<?= ($editing['region'] ?? '') === 'INT' ? ' selected' : '' ?>>Internacional</option></select></label>
            <label>Peso editorial (0,5–1,5)<input name="weight" type="number" step="0.1" min="0.5" max="1.5" value="<?= radar_h($editing['weight'] ?? '1.0') ?>"></label>
        </div>
        <div class="row-actions">
            <button class="btn primary" type="submit"><?= $editing !== null ? 'Salvar alterações' : 'Adicionar fonte' ?></button>
            <?php if ($editing !== null): ?><a class="btn ghost" href="fontes.php">Cancelar</a><?php endif; ?>
        </div>
    </form>
</section>

<section class="section">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fonte</th><th>Categoria</th><th>Status</th><th>Última coleta</th><th>Itens</th><th>Ações</th></tr></thead>
            <tbody>
            <?php foreach ($sources as $source): ?>
                <tr>
                    <td><strong><?= radar_h($source['name']) ?></strong><br><span class="meta"><?= radar_h(parse_url((string)$source['url'], PHP_URL_HOST)) ?><?= $source['kind'] === 'trends' ? ' · Tendências' : '' ?><?= (int)$source['builtin'] === 1 ? ' · padrão' : '' ?></span></td>
                    <td><?= radar_h($source['category'] === 'auto' ? 'Automática' : $source['category']) ?><br><span class="meta"><?= $source['region'] === 'INT' ? 'Internacional' : 'Brasil' ?> · peso <?= radar_h($source['weight']) ?></span></td>
                    <td>
                        <?php if ((int)$source['active'] === 0): ?><span class="pill">desativada</span>
                        <?php elseif ($source['last_status'] === 'error'): ?><span class="pill bad">erro</span><br><span class="meta"><?= radar_h((string)$source['last_error']) ?></span>
                        <?php elseif ($source['last_status'] === null): ?><span class="pill">aguardando</span>
                        <?php else: ?><span class="pill ok"><?= $source['last_status'] === 'unchanged' ? 'sem mudanças' : 'ok' ?></span><?php endif; ?>
                    </td>
                    <td><?= radar_h(radar_date($source['last_fetched_at'] !== null ? (int)$source['last_fetched_at'] : null)) ?></td>
                    <td><?= (int)$source['last_item_count'] ?></td>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="btn ghost small" data-act="source_test" data-id="<?= (int)$source['id'] ?>" data-busy="Testando…">Testar</button>
                            <a class="btn ghost small" href="fontes.php?editar=<?= (int)$source['id'] ?>#form-h">Editar</a>
                            <form method="post" action="fontes.php"><input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="op" value="toggle"><input type="hidden" name="id" value="<?= (int)$source['id'] ?>"><input type="hidden" name="active" value="<?= (int)$source['active'] === 1 ? '0' : '1' ?>"><button class="btn ghost small" type="submit"><?= (int)$source['active'] === 1 ? 'Desativar' : 'Ativar' ?></button></form>
                            <form method="post" action="fontes.php" data-confirm="Excluir esta fonte? As notícias já coletadas continuam salvas."><input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="op" value="delete"><input type="hidden" name="id" value="<?= (int)$source['id'] ?>"><button class="btn danger small" type="submit">Excluir</button></form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php radar_page_end();
