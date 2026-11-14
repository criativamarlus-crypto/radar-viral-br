<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    radar_form_guard('vagas.php');
    try {
        switch ((string)($_POST['op'] ?? '')) {
            case 'save':
                radar_adzuna_save((string)($_POST['app_id'] ?? ''), (string)($_POST['app_key'] ?? ''), (int)($_POST['daily_limit'] ?? 60));
                radar_flash_set('ok', 'Chave da Adzuna salva, criptografada. Use “Testar a chave” e depois “Atualizar as vagas agora”.');
                break;
            case 'forget':
                radar_adzuna_forget();
                radar_flash_set('ok', 'Chave da Adzuna removida. As vagas já guardadas continuam na lista até vencerem.');
                break;
            default:
                radar_flash_set('error', 'Ação inválida.');
        }
    } catch (InvalidArgumentException $error) {
        radar_flash_set('error', $error->getMessage());
    }
    radar_redirect('vagas.php');
}

$status = radar_adzuna_status();
$filters = radar_jobs_filters($_GET);
$result = radar_jobs_query($filters);
$facets = radar_jobs_facets($filters['state']);
$pages = max(1, (int)ceil($result['total'] / $filters['limit']));
$stateInfo = radar_job_states();
$ageLabels = radar_job_ages();
$base = ['q' => $filters['q'], 'regiao' => $filters['region'], 'estado' => $filters['state'], 'cidade' => $filters['city'], 'idade' => $filters['age'], 'contrato' => $filters['contract'], 'ordem' => $filters['sort']];
$csrf = radar_csrf_token();
$contracts = ['permanent' => 'Efetivo', 'contract' => 'Contrato'];

radar_page_start('Vagas (Adzuna)', 'vagas.php');
?>
<div class="page-head">
    <div>
        <h1>Vagas de emprego reais</h1>
        <p>Vagas publicadas na <a href="https://www.adzuna.com.br" target="_blank" rel="noopener noreferrer">Adzuna</a>, por região, estado, cidade e idade. Cada vaga leva ao anúncio original. <?= (int)$result['total'] ?> vaga(s) com esses filtros.</p>
    </div>
    <a class="btn ghost" href="noticias.php?categoria=<?= rawurlencode(RADAR_JOB_CATEGORY) ?>">Matérias sobre vagas</a>
</div>

<?php if (!$status['configured']): ?>
    <section class="panel" aria-labelledby="adz-h">
        <h2 id="adz-h">Ligar a Adzuna (grátis, 2 minutos)</h2>
        <ol>
            <li>Abra <a href="https://developer.adzuna.com" target="_blank" rel="noopener noreferrer">developer.adzuna.com</a> e crie uma conta (Register). Confirme o e-mail.</li>
            <li>No painel deles, copie o <strong>Application ID</strong> e a <strong>Application Key</strong>.</li>
            <li>Cole os dois aqui embaixo e salve. A chave fica criptografada no servidor e nunca volta para a tela.</li>
        </ol>
        <form method="post" action="vagas.php" class="form-grid">
            <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="op" value="save">
            <div class="field-row">
                <label>Application ID<input name="app_id" maxlength="64" autocomplete="off" required placeholder="Ex.: 1a2b3c4d"></label>
                <label>Application Key<input name="app_key" type="password" maxlength="128" autocomplete="off" required placeholder="Cole a chave"></label>
                <label>Limite de chamadas por dia<input name="daily_limit" type="number" min="10" max="500" value="60"></label>
            </div>
            <button class="btn primary" type="submit">Salvar a chave</button>
        </form>
        <p class="hint">A Adzuna limita as chamadas e o plano grátis é para avaliação: confira o seu limite e as regras de uso em developer.adzuna.com. O painel busca 9 estados por vez (1 chamada por estado) e para quando chega ao limite diário que você definiu. Todo anúncio mostra “Jobs by Adzuna”, como os termos deles pedem.</p>
    </section>
<?php else: ?>
    <section class="panel" aria-labelledby="adz-h">
        <h2 id="adz-h">Adzuna ligada</h2>
        <p class="hint">Chave <?= radar_h($status['key_hint']) ?> · <?= (int)$status['calls_today'] ?> de <?= (int)$status['daily_limit'] ?> chamadas hoje · <?= (int)$status['total'] ?> vagas guardadas ·
            <?= $status['last_batch_at'] !== null ? 'última leitura em ' . radar_h(date('d/m H:i', (int)$status['last_batch_at'])) : 'ainda não leu nenhuma vaga' ?> · próximo lote: <?= (int)$status['cursor'] + 1 ?>º de <?= (int)$status['states'] ?> estados</p>
        <?php if ($status['last_error'] !== ''): ?><div class="notice warn" role="status"><?= radar_h($status['last_error']) ?></div><?php endif; ?>
        <div class="row-actions">
            <button type="button" class="btn primary" data-act="adzuna_refresh" data-busy="Lendo as vagas…">Atualizar as vagas agora</button>
            <button type="button" class="btn ghost" data-act="adzuna_test" data-reload="no" data-busy="Testando…">Testar a chave</button>
            <button type="button" class="btn danger small" data-act="adzuna_forget" data-confirm="Remover a chave da Adzuna?">Remover a chave</button>
        </div>
        <p class="hint" id="jobs-auto" data-stale="<?= radar_adzuna_stale() ? '1' : '0' ?>" role="status"><?= radar_adzuna_stale() ? 'Atualizando as vagas em segundo plano…' : 'A cada visita, se já passaram 2 horas da última leitura e há chamadas sobrando, um novo lote é lido sozinho.' ?></p>
    </section>
<?php endif; ?>

<form class="filters section" method="get" action="vagas.php" role="search">
    <div class="search-row">
        <input type="search" name="q" value="<?= radar_h($filters['q']) ?>" placeholder="Buscar por cargo, empresa ou palavra da vaga" aria-label="Buscar vagas" maxlength="100">
        <?php foreach (['regiao' => 'region', 'estado' => 'state', 'idade' => 'age', 'contrato' => 'contract'] as $field => $key): if ($filters[$key] !== ''): ?><input type="hidden" name="<?= $field ?>" value="<?= radar_h($filters[$key]) ?>"><?php endif; endforeach; ?>
        <input type="hidden" name="ordem" value="<?= radar_h($filters['sort']) ?>">
        <button class="btn primary" type="submit">Buscar</button>
    </div>
    <div class="chips" role="group" aria-label="Região">
        <span class="label">Região</span>
        <a class="chip<?= $filters['region'] === '' ? ' on' : '' ?>" href="<?= radar_h(radar_url('vagas.php', ['regiao' => '', 'estado' => '', 'cidade' => ''] + $base)) ?>">Todo o Brasil</a>
        <?php foreach (radar_job_regions() as $region): ?>
            <a class="chip<?= $filters['region'] === $region ? ' on' : '' ?>" href="<?= radar_h(radar_url('vagas.php', ['regiao' => $region, 'estado' => '', 'cidade' => ''] + $base)) ?>"><?= radar_h($region) ?><span class="count"><?= (int)($facets['regions'][$region] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </div>
    <div class="chips" role="group" aria-label="Estado">
        <span class="label">Estado</span>
        <a class="chip<?= $filters['state'] === '' ? ' on' : '' ?>" href="<?= radar_h(radar_url('vagas.php', ['estado' => '', 'cidade' => ''] + $base)) ?>">Todos</a>
        <?php foreach ($facets['states'] as $uf => $total): if (!isset($stateInfo[$uf])) { continue; } ?>
            <a class="chip<?= $filters['state'] === $uf ? ' on' : '' ?>" title="<?= radar_h($stateInfo[$uf]['name']) ?>" href="<?= radar_h(radar_url('vagas.php', ['estado' => $uf, 'regiao' => '', 'cidade' => ''] + $base)) ?>"><?= radar_h($uf) ?><span class="count"><?= (int)$total ?></span></a>
        <?php endforeach; ?>
    </div>
    <div class="chips" role="group" aria-label="Idade">
        <span class="label">Idade</span>
        <a class="chip<?= $filters['age'] === '' ? ' on' : '' ?>" href="<?= radar_h(radar_url('vagas.php', ['idade' => ''] + $base)) ?>">Todas</a>
        <?php foreach ($ageLabels as $key => $label): ?>
            <a class="chip<?= $filters['age'] === $key ? ' on' : '' ?>" href="<?= radar_h(radar_url('vagas.php', ['idade' => $key] + $base)) ?>"><?= radar_h($label) ?><span class="count"><?= (int)($facets['ages'][$key] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </div>
    <div class="chips" role="group" aria-label="Contrato e ordem">
        <span class="label">Contrato</span>
        <a class="chip<?= $filters['contract'] === '' ? ' on' : '' ?>" href="<?= radar_h(radar_url('vagas.php', ['contrato' => ''] + $base)) ?>">Todos</a>
        <?php foreach ($contracts as $key => $label): ?>
            <a class="chip<?= $filters['contract'] === $key ? ' on' : '' ?>" href="<?= radar_h(radar_url('vagas.php', ['contrato' => $key] + $base)) ?>"><?= radar_h($label) ?></a>
        <?php endforeach; ?>
        <span class="label">Ordem</span>
        <a class="chip<?= $filters['sort'] === 'recente' ? ' on' : '' ?>" href="<?= radar_h(radar_url('vagas.php', ['ordem' => 'recente'] + $base)) ?>">Mais recentes</a>
        <a class="chip<?= $filters['sort'] === 'salario' ? ' on' : '' ?>" href="<?= radar_h(radar_url('vagas.php', ['ordem' => 'salario'] + $base)) ?>">Maior salário</a>
    </div>
    <div class="search-row">
        <label class="inline-label">Cidade
            <select name="cidade" data-autosubmit>
                <option value="">Todas as cidades</option>
                <?php foreach ($facets['cities'] as $city): ?>
                    <option value="<?= radar_h($city['city']) ?>"<?= $filters['city'] === $city['city'] ? ' selected' : '' ?>><?= radar_h($city['city'] . ($city['state'] !== '' ? ' (' . $city['state'] . ')' : '') . ' · ' . $city['total']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn ghost small" type="submit">Filtrar</button>
    </div>
</form>

<?php if ($result['items'] === []): ?>
    <div class="empty-state"><h3>Nenhuma vaga com esses filtros</h3><p><?= $status['configured'] ? 'Tente outro estado, outra idade ou <a href="vagas.php">limpe os filtros</a>. As vagas chegam em lotes de 9 estados.' : 'Ligue a Adzuna acima para ver vagas reais aqui.' ?></p></div>
<?php else: ?>
    <div class="stack">
        <?php foreach ($result['items'] as $job):
            $place = implode(' · ', array_filter([$job['city'], $job['state'], $job['region']])) ?: ($job['location'] !== '' ? $job['location'] : 'Local não informado'); ?>
            <article class="panel job" data-job="<?= (int)$job['id'] ?>">
                <h3><a href="<?= radar_h($job['url']) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= radar_h($job['title']) ?></a></h3>
                <p class="meta"><?= radar_h($job['company'] !== '' ? $job['company'] : 'Empresa não informada') ?> · <?= radar_h($place) ?> · <?= radar_h(radar_age_label($job['age_minutes'])) ?></p>
                <div class="pills">
                    <?php if ($job['salary'] !== ''): ?><span class="pill"><?= radar_h($job['salary']) ?><?= $job['salary_predicted'] ? ' · estimativa <a href="https://www.adzuna.com.br" target="_blank" rel="noopener noreferrer">Adzuna Jobsworth</a>' : '' ?></span><?php endif; ?>
                    <?php if ($job['contract_type'] !== ''): ?><span class="pill"><?= radar_h($contracts[$job['contract_type']] ?? $job['contract_type']) ?></span><?php endif; ?>
                    <?php if ($job['age_label'] !== ''): ?><span class="pill"><?= radar_h($job['age_label']) ?></span><?php endif; ?>
                    <?php if ($job['category'] !== ''): ?><span class="pill"><?= radar_h($job['category']) ?></span><?php endif; ?>
                </div>
                <?php if ($job['description'] !== ''): ?><p class="why"><?= radar_h(mb_substr($job['description'], 0, 260)) ?><?= mb_strlen($job['description']) > 260 ? '…' : '' ?></p><?php endif; ?>
                <div class="card-actions">
                    <a class="btn primary" href="<?= radar_h($job['url']) ?>" target="_blank" rel="noopener noreferrer nofollow">Ver a vaga e se candidatar</a>
                    <span class="hint"><a href="https://www.adzuna.com.br" target="_blank" rel="noopener noreferrer">Jobs</a> by Adzuna</span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Paginação">
            <?php if ($filters['page'] > 1): ?><a class="btn ghost small" href="<?= radar_h(radar_url('vagas.php', ['pagina' => $filters['page'] - 1] + $base)) ?>">← Anterior</a><?php endif; ?>
            <span class="muted">Página <?= (int)$filters['page'] ?> de <?= (int)$pages ?></span>
            <?php if ($filters['page'] < $pages): ?><a class="btn ghost small" href="<?= radar_h(radar_url('vagas.php', ['pagina' => $filters['page'] + 1] + $base)) ?>">Próxima →</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<p class="hint"><a href="https://www.adzuna.com.br" target="_blank" rel="noopener noreferrer">Jobs</a> by Adzuna. As vagas e os salários são os publicados pelos anunciantes; salário marcado como estimativa é cálculo da Adzuna. O lugar e a idade aparecem quando o anúncio informa; idade vem de palavras como “jovem aprendiz”, “estágio”, “primeiro emprego” ou “50+”.</p>
<?php radar_page_end();
