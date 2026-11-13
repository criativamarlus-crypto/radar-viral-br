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
            case 'oauth_client_create':
                $client = radar_oauth_client_create((string)($_POST['name'] ?? ''), (string)($_POST['redirects'] ?? ''));
                radar_session();
                $_SESSION['new_oauth_client'] = $client;
                radar_flash_set('ok', 'Cliente OAuth criado. Copie o segredo agora: ele não será mostrado de novo.');
                break;
            case 'oauth_client_delete':
                radar_oauth_client_delete((string)($_POST['client_id'] ?? ''));
                radar_flash_set('ok', 'Cliente removido e as conexões dele foram revogadas.');
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
$newClient = $_SESSION['new_oauth_client'] ?? null;
unset($_SESSION['new_token'], $_SESSION['new_oauth_client']);
$siteUrl = rtrim((string)radar_config()['site_url'], '/');
$oauthClients = radar_oauth_clients();
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

<?php if (is_array($newClient)): ?>
    <div class="notice warn">
        <strong>Novo cliente OAuth (o segredo aparece só agora):</strong>
        <dl class="kv">
            <dt>Client ID</dt><dd><code id="new-client-id"><?= radar_h($newClient['client_id']) ?></code> <button type="button" class="btn ghost small" data-copy="new-client-id">Copiar</button></dd>
            <dt>Client secret</dt><dd><code id="new-client-secret"><?= radar_h($newClient['client_secret']) ?></code> <button type="button" class="btn primary small" data-copy="new-client-secret">Copiar</button></dd>
        </dl>
    </div>
<?php endif; ?>

<section class="panel section" aria-labelledby="oauth-h">
    <h2 id="oauth-h">OAuth (conectar o ChatGPT)</h2>
    <p class="hint">O ChatGPT conecta por OAuth 2.1 com PKCE: ele se registra sozinho (registro dinâmico), você aprova na tela de consentimento deste painel e ele recebe um token que expira em 1 hora e se renova. A senha nunca sai daqui.</p>
    <div class="grid-2">
        <dl class="kv">
            <dt>URL do MCP</dt><dd><code><?= radar_h($siteUrl) ?>/mcp.php</code></dd>
            <dt>Descoberta</dt><dd><code><?= radar_h($siteUrl) ?>/.well-known/oauth-authorization-server</code></dd>
            <dt>Autorização</dt><dd><code id="oauth-auth-url"><?= radar_h($siteUrl) ?>/oauth-authorize.php</code></dd>
            <dt>Token</dt><dd><code id="oauth-token-url"><?= radar_h($siteUrl) ?>/oauth-token.php</code></dd>
            <dt>Registro</dt><dd><code><?= radar_h($siteUrl) ?>/oauth-register.php</code></dd>
            <dt>Escopos</dt><dd><code>read</code>, <code>react</code></dd>
        </dl>
        <form method="post" action="integracoes.php" class="form-grid">
            <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="op" value="oauth_client_create">
            <p class="hint">Se a tela do ChatGPT pedir <em>Client ID</em> e <em>Client secret</em> em vez de registrar sozinha, crie um cliente manual com o endereço de retorno que ele mostrar.</p>
            <label>Nome<input name="name" required maxlength="80" placeholder="Ex.: ChatGPT"></label>
            <label>Endereços de retorno (um por linha, HTTPS)<textarea name="redirects" rows="2" required maxlength="1500" placeholder="https://chatgpt.com/connector_platform_oauth_redirect"></textarea></label>
            <button class="btn ghost" type="submit">Criar cliente manual</button>
        </form>
    </div>
    <?php if ($oauthClients !== []): ?>
        <div class="table-wrap section"><table>
            <thead><tr><th>Cliente</th><th>Tipo</th><th>Retorno</th><th>Conexões ativas</th><th></th></tr></thead>
            <tbody><?php foreach ($oauthClients as $client): ?>
                <tr><td><?= radar_h($client['name']) ?><br><span class="meta"><?= radar_h($client['client_id']) ?></span></td>
                    <td><?= $client['kind'] === 'manual' ? 'manual (com segredo)' : 'registro dinâmico' ?></td>
                    <td><?= radar_h(implode(', ', array_map(static fn(string $u): string => (string)parse_url($u, PHP_URL_HOST), json_decode((string)$client['redirect_uris'], true) ?: []))) ?></td>
                    <td><?= (int)$client['active_tokens'] ?></td>
                    <td><form method="post" action="integracoes.php" data-confirm="Remover este cliente e revogar as conexões dele?"><input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="op" value="oauth_client_delete"><input type="hidden" name="client_id" value="<?= radar_h($client['client_id']) ?>"><button class="btn danger small" type="submit">Remover</button></form></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<div class="grid-2 section">
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
                    <tr><td><?= radar_h($token['name']) ?><?= $token['kind'] === 'oauth' ? ' <span class="pill">OAuth</span>' : '' ?></td><td><?= radar_h(str_contains((string)$token['scopes'], 'react') ? 'leitura + react' : 'leitura') ?></td>
                        <td><?= $token['revoked_at'] !== null ? '<span class="pill bad">revogado</span>' : radar_h(radar_date($token['last_used_at'] !== null ? (int)$token['last_used_at'] : null)) ?><?= $token['kind'] === 'oauth' && $token['revoked_at'] === null ? '<br><span class="meta">renovável até ' . radar_h(radar_date((int)$token['refresh_expires_at'])) . '</span>' : '' ?></td>
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
