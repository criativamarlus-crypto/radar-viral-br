<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$runs = radar_db_all('SELECT * FROM collection_runs ORDER BY id DESC LIMIT 40');
$selected = isset($_GET['id']) ? (int)$_GET['id'] : (int)($runs[0]['id'] ?? 0);
$details = $selected > 0 ? radar_db_all('SELECT * FROM collection_run_sources WHERE run_id = ? ORDER BY (status = \'error\') DESC, source_name', [$selected]) : [];
$labels = ['ok' => ['ok', 'concluída'], 'partial' => ['warn', 'parcial'], 'error' => ['bad', 'falhou'], 'running' => ['', 'em andamento']];

radar_page_start('Histórico de coleta', 'coletas.php');
?>
<div class="page-head"><div><h1>Histórico de coleta</h1><p>Cada coleta registra quais fontes responderam, quantas matérias vieram e o erro de quem falhou. Nada é inventado quando uma fonte cai.</p></div></div>
<?php if ($runs === []): ?>
    <div class="empty-state"><h3>Nenhuma coleta registrada</h3><p>Use “Atualizar agora” no topo para iniciar.</p></div>
<?php else: ?>
    <div class="grid-2">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Início</th><th>Origem</th><th>Status</th><th>Fontes</th><th>Novas</th></tr></thead>
                <tbody>
                <?php foreach ($runs as $run): [$class, $text] = $labels[$run['status']] ?? ['', $run['status']]; ?>
                    <tr>
                        <td><a href="coletas.php?id=<?= (int)$run['id'] ?>"><?= radar_h(radar_date((int)$run['started_at'])) ?></a></td>
                        <td><?= radar_h(['manual' => 'manual', 'auto' => 'automática (painel)', 'cron' => 'agendador (cron)'][$run['trigger_kind']] ?? $run['trigger_kind']) ?></td>
                        <td><span class="pill <?= radar_h($class) ?>"><?= radar_h($text) ?></span></td>
                        <td><?= (int)$run['sources_ok'] ?> ok / <?= (int)$run['sources_failed'] ?> falha</td>
                        <td><?= (int)$run['items_new'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Fonte (coleta #<?= (int)$selected ?>)</th><th>Status</th><th>Itens</th><th>Tempo</th></tr></thead>
                <tbody>
                <?php foreach ($details as $detail): ?>
                    <tr>
                        <td><?= radar_h($detail['source_name']) ?><?php if ($detail['error']): ?><br><span class="meta"><?= radar_h($detail['error']) ?></span><?php endif; ?></td>
                        <td><span class="pill <?= $detail['status'] === 'error' ? 'bad' : 'ok' ?>"><?= radar_h($detail['status'] === 'unchanged' ? 'sem mudanças' : $detail['status']) ?></span><?= $detail['http_status'] ? '<br><span class="meta">HTTP ' . (int)$detail['http_status'] . '</span>' : '' ?></td>
                        <td><?= (int)$detail['items'] ?> (<?= (int)$detail['new_items'] ?> novas)</td>
                        <td><?= (int)$detail['duration_ms'] ?> ms</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<?php radar_page_end();
