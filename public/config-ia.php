<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    radar_form_guard('config-ia.php');
    try {
        $provider = (string)($_POST['provider'] ?? 'none');
        if (!isset(RADAR_AI_PROVIDERS[$provider])) {
            throw new InvalidArgumentException('Provedor inválido.');
        }
        $model = trim((string)($_POST['model'] ?? ''));
        if ($model !== '' && !preg_match('/^[A-Za-z0-9._:\/-]{1,100}$/D', $model)) {
            throw new InvalidArgumentException('Nome de modelo inválido: use letras, números e . _ : / -');
        }
        $baseUrl = trim((string)($_POST['base_url'] ?? ''));
        if ($provider === 'compatible') {
            if (radar_feed_safe_url($baseUrl) === null) {
                throw new InvalidArgumentException('Informe o endereço HTTPS base da API compatível (ex.: https://openrouter.ai/api/v1).');
            }
            $baseUrl = rtrim($baseUrl, '/');
        }
        $temperature = (float)str_replace(',', '.', (string)($_POST['temperature'] ?? '0.8'));
        if ($temperature < 0 || $temperature > 1.5) {
            throw new InvalidArgumentException('A criatividade (temperatura) deve ficar entre 0 e 1,5.');
        }
        $maxTokens = (int)($_POST['max_tokens'] ?? 1500);
        if ($maxTokens < 300 || $maxTokens > 4000) {
            throw new InvalidArgumentException('O limite de tokens deve ficar entre 300 e 4000.');
        }
        radar_setting_set('ai_provider', $provider);
        radar_setting_set('ai_model', $model);
        radar_setting_set('ai_base_url', $baseUrl);
        radar_setting_set('ai_temperature', (string)$temperature);
        radar_setting_set('ai_max_tokens', (string)$maxTokens);
        $key = trim((string)($_POST['api_key'] ?? ''));
        if ($provider !== 'none' && $key !== '') {
            if (strlen($key) < 20 || strlen($key) > 400 || preg_match('/\s/', $key)) {
                throw new InvalidArgumentException('A chave de API parece inválida (sem espaços, de 20 a 400 caracteres).');
            }
            radar_secret_set('ai_' . $provider, $key);
        }
        if ($provider !== 'none' && ($_POST['remove_key'] ?? '') === '1') {
            radar_secret_delete('ai_' . $provider);
        }
        radar_flash_set('ok', 'Configurações de IA salvas.' . ($key !== '' ? ' A chave foi criptografada no servidor e não será exibida de novo.' : ''));
    } catch (InvalidArgumentException $error) {
        radar_flash_set('error', $error->getMessage());
    } catch (RuntimeException $error) {
        radar_flash_set('error', 'Não foi possível guardar a chave com segurança: ' . $error->getMessage());
    }
    radar_redirect('config-ia.php');
}

$config = radar_ai_config();
$csrf = radar_csrf_token();
radar_page_start('Configurações de IA', 'config-ia.php');
?>
<div class="page-head"><div><h1>Configurações de IA</h1><p>Quem escreve headlines, roteiros e comentários. A chave fica só no servidor.</p></div></div>

<div class="grid-2">
    <form method="post" action="config-ia.php" class="panel form-grid">
        <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>">
        <label>Provedor
            <select name="provider" id="ai-provider"><?php foreach (RADAR_AI_PROVIDERS as $value => $label): ?><option value="<?= radar_h($value) ?>"<?= $config['provider'] === $value ? ' selected' : '' ?>><?= radar_h($label) ?></option><?php endforeach; ?></select>
        </label>
        <label>Modelo<input name="model" maxlength="100" value="<?= radar_h($config['model']) ?>" placeholder="Ex.: claude-sonnet-5-5 ou gpt-4o-mini" autocomplete="off"><span class="hint">Use o nome exato do modelo na documentação do provedor.</span></label>
        <label>Endereço base (somente “compatível”)<input name="base_url" type="url" maxlength="300" value="<?= radar_h($config['base_url']) ?>" placeholder="https://openrouter.ai/api/v1"></label>
        <div class="field-row">
            <label>Criatividade (0 a 1,5)<input name="temperature" type="number" step="0.1" min="0" max="1.5" value="<?= radar_h($config['temperature']) ?>"></label>
            <label>Máx. de tokens da resposta<input name="max_tokens" type="number" step="100" min="300" max="4000" value="<?= (int)$config['max_tokens'] ?>"></label>
        </div>
        <label>Chave de API
            <input name="api_key" type="password" autocomplete="new-password" maxlength="400" placeholder="<?= $config['has_key'] ? 'Chave salva (' . radar_h($config['key_hint']) . '). Deixe vazio para manter.' : 'Cole a chave do provedor' ?>">
            <span class="hint">Criptografada no servidor; nunca volta ao navegador. Alternativa: defina a variável de ambiente <code>RADAR_ANTHROPIC_API_KEY</code>, <code>RADAR_OPENAI_API_KEY</code> ou <code>RADAR_AI_API_KEY</code> no PHP-FPM.</span>
        </label>
        <?php if ($config['has_key']): ?><label><span><input type="checkbox" name="remove_key" value="1"> Remover a chave salva deste provedor</span></label><?php endif; ?>
        <div class="row-actions">
            <button class="btn primary" type="submit">Salvar configurações</button>
            <button class="btn ghost" type="button" data-act="ai_test" data-busy="Testando…">Testar conexão</button>
        </div>
    </form>

    <div class="stack">
        <div class="panel">
            <h2>Situação</h2>
            <dl class="kv">
                <dt>Provedor</dt><dd><?= radar_h(RADAR_AI_PROVIDERS[$config['provider']]) ?></dd>
                <dt>Modelo</dt><dd><?= radar_h($config['model'] ?: '—') ?></dd>
                <dt>Chave</dt><dd><?= $config['has_key'] ? '<span class="pill ok">configurada</span> ' . radar_h($config['key_source']) : '<span class="pill warn">ausente</span>' ?></dd>
            </dl>
            <?php if (!$config['has_key'] || $config['provider'] === 'none'): ?><p class="hint section">Sem IA, o botão “Gerar react” cria um rascunho conservador a partir da manchete, sem inventar nada.</p><?php endif; ?>
        </div>
        <div class="panel">
            <h2>Como a IA é usada</h2>
            <ul>
                <li>Recebe título, fonte, link, resumo do veículo, data, categoria e o contexto que você confirmar.</li>
                <li>É instruída a não inventar números, falas ou acusações e a separar fato de opinião.</li>
                <li>Textos de notícias são tratados como dados, nunca como instruções.</li>
                <li>Números ou citações que não existem na fonte geram alerta no roteiro.</li>
                <li>O prompt de vídeo é montado pelo sistema com o personagem fixo, não pela IA.</li>
            </ul>
        </div>
    </div>
</div>
<?php radar_page_end();
