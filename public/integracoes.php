<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    radar_form_guard('integracoes.php');
    $op = (string)($_POST['op'] ?? '');
    try {
        switch ($op) {
            case 'token_create':
                $created = radar_token_create((string)($_POST['name'] ?? ''), (string)($_POST['scope'] ?? 'read'));
                radar_session();
                $_SESSION['new_token'] = $created['token'];
                radar_flash_set('ok', 'Token criado. Copie agora: ele não será mostrado de novo.');
                break;
            case 'token_revoke':
                radar_token_revoke((int)($_POST['id'] ?? 0));
                radar_flash_set('ok', 'Token revogado. Quem o usava perde o acesso imediatamente.');
                break;
            case 'shot_save':
                $template = trim((string)($_POST['template'] ?? ''));
                if ($template !== '' && !radar_screenshot_template_valid($template)) {
                    throw new InvalidArgumentException('O endereço do serviço deve ser HTTPS e conter {url} (e {key}, se o serviço pedir chave).');
                }
                radar_setting_set('shot_url', $template);
                $key = trim((string)($_POST['key'] ?? ''));
                if ($key !== '') {
                    if (strlen($key) < 6 || strlen($key) > 300 || preg_match('/\s/', $key)) {
                        throw new InvalidArgumentException('Chave do serviço inválida.');
                    }
                    radar_secret_set('shot_key', $key);
                }
                if (($_POST['remove_key'] ?? '') === '1') {
                    radar_secret_delete('shot_key');
                }
                radar_flash_set('ok', $template === '' ? 'Captura de tela desativada.' : 'Serviço de captura salvo.');
                break;
            default:
                radar_flash_set('error', 'Ação inválida.');
        }
    } catch (InvalidArgumentException $error) {
        radar_flash_set('error', $error->getMessage());
    }
    radar_redirect('integracoes.php');
}

radar_session();
$newToken = $_SESSION['new_token'] ?? null;
unset($_SESSION['new_token']);
$siteUrl = rtrim((string)radar_config()['site_url'], '/');
$tokens = radar_tokens_list();
$shot = radar_screenshot_config();
$checks = radar_diagnostics();
$csrf = radar_csrf_token();

radar_page_start('Integrações', 'integracoes.php');
?>
<div class="page-head"><div><h1>Configurações de integração</h1><p>API segura, servidor MCP para o ChatGPT, captura de tela e diagnóstico do servidor.</p></div></div>

<?php if (is_string($newToken)): ?>
    <div class="notice warn">
        <strong>Novo token (mostrado uma única vez):</strong>
        <div class="headline-option"><code id="new-token"><?= radar_h($newToken) ?></code><button type="button" class="btn primary small" data-copy="new-token">Copiar</button></div>
        Guarde em um gerenciador de senhas. Só o hash é armazenado.
    </div>
<?php endif; ?>

<div class="grid-2">
    <section class="panel">
        <h2>Servidor MCP (ChatGPT)</h2>
        <dl class="kv">
            <dt>URL do MCP</dt><dd><code id="mcp-url"><?= radar_h($siteUrl) ?>/mcp.php</code> <button type="button" class="btn ghost small" data-copy="mcp-url">Copiar</button></dd>
            <dt>Transporte</dt><dd>HTTP (JSON-RPC, sem estado), método POST</dd>
            <dt>Autenticação</dt><dd>Cabeçalho <code>Authorization: Bearer &lt;token&gt;</code></dd>
            <dt>API REST</dt><dd><code><?= radar_h($siteUrl) ?>/api.php</code> — documentação em <code>docs/API.md</code></dd>
        </dl>
        <p class="hint section">Ferramentas: <code>listar_noticias</code>, <code>buscar_noticias</code>, <code>consultar_tendencias</code>, <code>obter_noticia</code>, <code>obter_imagens</code>, <code>listar_reacts</code> e <code>preparar_react</code> (token com “preparar react”). Passo a passo e o que depende de autorização manual: <code>docs/CHATGPT.md</code>.</p>
    </section>

    <section class="panel">
        <h2>Tokens de acesso</h2>
        <form method="post" action="integracoes.php" class="form-grid">
            <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="op" value="token_create">
            <div class="field-row">
                <label>Nome do token<input name="name" required maxlength="80" placeholder="Ex.: ChatGPT"></label>
                <label>Acesso<select name="scope"><?php foreach (RADAR_TOKEN_SCOPES as $value => $label): ?><option value="<?= radar_h($value) ?>"><?= radar_h($label) ?></option><?php endforeach; ?></select></label>
            </div>
            <button class="btn primary" type="submit">Criar token</button>
        </form>
        <?php if ($tokens !== []): ?>
            <div class="table-wrap section"><table>
                <thead><tr><th>Nome</th><th>Acesso</th><th>Último uso</th><th></th></tr></thead>
                <tbody><?php foreach ($tokens as $token): ?>
                    <tr><td><?= radar_h($token['name']) ?></td><td><?= radar_h(str_contains((string)$token['scopes'], 'react') ? 'leitura + react' : 'leitura') ?></td>
                        <td><?= $token['revoked_at'] !== null ? '<span class="pill bad">revogado</span>' : radar_h(radar_date($token['last_used_at'] !== null ? (int)$token['last_used_at'] : null)) ?></td>
                        <td><?php if ($token['revoked_at'] === null): ?><form method="post" action="integracoes.php" data-confirm="Revogar este token?"><input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="op" value="token_revoke"><input type="hidden" name="id" value="<?= (int)$token['id'] ?>"><button class="btn danger small" type="submit">Revogar</button></form><?php endif; ?></td></tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</div>

<div class="grid-2 section">
    <section class="panel">
        <h2>Captura de tela real da matéria</h2>
        <p class="hint">Opcional. Use um serviço de screenshots de terceiros que aceite uma URL por HTTPS. O Radar nunca cria prints falsos: sem serviço, a captura fica desativada e você anexa o print manualmente.</p>
        <form method="post" action="integracoes.php" class="form-grid">
            <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="op" value="shot_save">
            <label>Endereço do serviço (com <code>{url}</code> e, se preciso, <code>{key}</code>)<input name="template" maxlength="600" value="<?= radar_h($shot['template']) ?>" placeholder="https://servico.exemplo/take?access_key={key}&amp;url={url}&amp;format=jpg"><span class="hint">Consulte a documentação do seu serviço para os parâmetros. A página capturada é enviada ao serviço escolhido.</span></label>
            <label>Chave do serviço<input name="key" type="password" autocomplete="new-password" maxlength="300" placeholder="<?= $shot['has_key'] ? 'Salva (' . radar_h($shot['key_hint']) . '). Vazio mantém.' : 'Opcional' ?>"></label>
            <?php if ($shot['has_key']): ?><label><span><input type="checkbox" name="remove_key" value="1"> Remover a chave salva</span></label><?php endif; ?>
            <button class="btn primary" type="submit">Salvar</button>
        </form>
    </section>

    <section class="panel">
        <h2>Diagnóstico do servidor</h2>
        <ul class="reasons">
            <?php foreach ($checks as $check): ?>
                <li><span><?= radar_h($check['label']) ?></span><span class="pill <?= $check['ok'] === true ? 'ok' : ($check['ok'] === false ? 'bad' : 'warn') ?>"><?= $check['ok'] === true ? 'ok' : ($check['ok'] === false ? 'problema' : 'atenção') ?></span><small><?= radar_h($check['detail']) ?></small></li>
            <?php endforeach; ?>
        </ul>
        <p class="hint">Implantação, cron e Nginx: <code>docs/CLOUDPANEL.md</code>.</p>
    </section>
</div>
<?php radar_page_end();
