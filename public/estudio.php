<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$projectId = (int)($_GET['projeto'] ?? 0);
$project = $projectId > 0 ? radar_project_get($projectId) : null;
$scriptId = (int)($_GET['roteiro'] ?? ($project['script_id'] ?? 0));
$script = $scriptId > 0 ? radar_script_get($scriptId) : null;
$newsId = (int)($_GET['news'] ?? ($script['news_id'] ?? $project['news_id'] ?? 0));
$news = $newsId > 0 ? radar_news_get($newsId) : null;

// Choices for the news selector: saved stories first, then the strongest of the last 24 h.
$choices = [];
foreach (radar_db_all('SELECT * FROM news WHERE saved = 1 ORDER BY saved_at DESC LIMIT 40') as $row) {
    $choices[(int)$row['id']] = radar_news_decode($row);
}
foreach (radar_news_query(radar_news_filters(['janela' => 24, 'ordem' => 'viral', 'limite' => 40]))['items'] as $item) {
    $choices[$item['id']] ??= $item;
}
if ($news !== null) {
    $choices[$news['id']] = $news;
}
$newsOptions = array_values(array_map(static fn(array $n): array => [
    'id' => $n['id'], 'title' => $n['title'], 'source' => $n['source'], 'category' => $n['category'], 'saved' => $n['saved'],
], $choices));

$scriptOptions = array_map(static fn(array $s): array => [
    'id' => $s['id'], 'news_id' => $s['news_id'], 'news_title' => $s['news_title'], 'news_source' => $s['news_source'],
    'headline' => $s['headlines'][0] ?? $s['news_title'], 'headlines' => $s['headlines'], 'hook' => $s['hook'], 'spoken' => $s['spoken'], 'expression' => $s['expression'], 'scene' => $s['scene'],
    'layout' => $s['layout'], 'duration' => $s['duration'], 'scenes' => $s['scenes'], 'direction' => $s['direction'], 'video_prompt' => $s['video_prompt'], 'video_prompts' => $s['video_prompts'],
], radar_scripts_list(100));

$media = $news !== null ? $news['media'] : [];
$mediaOptions = array_map(static fn(array $m): array => ['id' => $m['id'], 'label' => $m['label'], 'kind' => $m['kind'], 'credit' => $m['credit']], array_values(array_filter($media, static fn(array $m): bool => in_array($m['kind'], ['cover', 'screenshot', 'upload'], true))));
$characters = array_map(static fn(array $m): array => ['id' => $m['id'], 'label' => $m['label'], 'primary' => (int)$m['is_primary'] === 1], radar_character_images());

$state = [
    'news' => $news === null ? null : ['id' => $news['id'], 'title' => $news['title'], 'source' => $news['source'], 'url' => $news['url'], 'image' => $news['image'], 'image_credit' => $news['image_credit'], 'category' => $news['category'], 'published_at' => $news['published_known'] ? $news['published_at'] : null],
    'newsOptions' => $newsOptions,
    'scripts' => $scriptOptions,
    'media' => $mediaOptions,
    'characters' => $characters,
    'expressions' => array_map(static fn(array $e): string => $e['label'], radar_character_expressions()),
    'scenes' => radar_character_scenes(),
    'tones' => RADAR_TONES,
    'defaultTemplate' => radar_video_default_template(),
    'moods' => radar_direction_moods(),
    'defaultScenes' => RADAR_SCENES_DEFAULT,
    'reference' => ($reference = radar_character_reference()) === null ? null : ['id' => $reference['id'], 'width' => $reference['width'], 'height' => $reference['height']],
    'templates' => array_map(static fn(array $t): array => ['label' => $t['label'], 'description' => $t['description']], radar_video_templates()),
    'project' => $project,
    'scriptId' => $script['id'] ?? null,
    'preselectCover' => preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/D', radar_str($_GET['cover'] ?? '')) === 1 ? $_GET['cover'] : null,
];
$json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

radar_page_start('Estúdio de React', 'estudio.php', ['scripts' => ['studio.js', 'studio-pack.js'], 'wide' => true]);
?>
<div class="page-head">
    <div><h1>Estúdio de React</h1><p>Monte a composição vertical 9:16 em um dos modelos. Prepara a imagem de referência, a camada de legenda/card e o prompt para a ferramenta de vídeo; não gera o vídeo final.</p></div>
    <a class="btn ghost" href="roteiros.php">Projetos e roteiros</a>
</div>
<script type="application/json" id="studio-data"><?= $json ?></script>

<div class="studio" id="studio">
    <section class="stack" aria-label="Material">
        <div class="panel form-grid">
            <h2>Modelo do vídeo</h2>
            <label>Layout<select id="st-template"></select></label>
            <p class="hint" id="st-template-hint"></p>
        </div>
        <div class="panel form-grid">
            <h2>1 · Notícia e roteiro</h2>
            <label>Notícia<select id="st-news"><option value="">Escolha uma notícia…</option></select></label>
            <label>Roteiro<select id="st-script-select"><option value="">Sem roteiro (escrever aqui)</option></select></label>
            <?php if ($news !== null): ?>
                <div class="field-row">
                    <label>Cenas<select id="st-scenes"><option value="1">1 cena (até 25 s)</option><option value="2">2 cenas (até 50 s)</option><option value="3" selected>3 cenas (até 75 s)</option><option value="4">4 cenas (até 100 s)</option><option value="5">5 cenas (até 125 s)</option></select></label>
                    <label>Tom<select id="st-tone"></select></label>
                </div>
                <button type="button" class="btn small" id="st-generate">Gerar outro roteiro</button>
                <p class="hint" id="st-gen-status" role="status" aria-live="polite"></p>
                <button type="button" class="btn ghost small" data-react="<?= (int)$news['id'] ?>" data-title="<?= radar_h($news['title']) ?>">Gerar react com contexto conferido (IA)</button>
            <?php else: ?>
                <p class="hint">Ao escolher a notícia, o roteiro já é gerado aqui.</p>
            <?php endif; ?>
        </div>
        <div class="panel form-grid">
            <h2>2 · Imagem da notícia</h2>
            <div class="picker" id="st-covers" role="group" aria-label="Imagens da notícia"></div>
            <p class="hint" id="st-cover-hint"></p>
            <div id="st-cover-import"></div>
            <label>Enquadramento horizontal<input type="range" id="st-x" min="0" max="100" value="50"></label>
            <label>Enquadramento vertical<input type="range" id="st-y" min="0" max="100" value="50"></label>
            <label>Zoom<input type="range" id="st-zoom" min="1" max="3" step="0.05" value="1"></label>
            <?php if ($news !== null): ?>
                <form data-upload class="form-grid" enctype="multipart/form-data">
                    <input type="hidden" name="kind" value="cover"><input type="hidden" name="news_id" value="<?= (int)$news['id'] ?>">
                    <label>Anexar print/imagem<input type="file" name="file" accept="image/jpeg,image/png,image/webp"></label>
                    <label>Crédito<input name="credit" maxlength="200" placeholder="Ex.: print do site G1"></label>
                    <button class="btn ghost small" type="submit">Enviar</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="panel form-grid">
            <h2>3 · Personagem</h2>
            <div class="picker" id="st-chars" role="group" aria-label="Imagens do personagem"></div>
            <p class="hint" id="st-char-hint"></p>
            <label>Expressão<select id="st-expression"></select></label>
            <label>Cenário<select id="st-scene"></select></label>
        </div>
        <div class="panel form-grid" id="st-look">
            <h2>Visual desta notícia</h2>
            <p class="hint">O rosto fica sempre igual: a folha de referência só serve para isso. A roupa, os acessórios e a luz são criados para cada notícia, combinando com o tom do assunto, e o ângulo muda de cena em cena. Pode editar à vontade.</p>
            <label>Roupa<textarea id="st-outfit" rows="2" maxlength="240" placeholder="Ex.: a navy blazer over a plain white t-shirt"></textarea></label>
            <label>Acessórios<input id="st-accessories" maxlength="240" placeholder="Ex.: a slim steel watch"></label>
            <label>Iluminação e clima<input id="st-lighting" maxlength="240" placeholder="Ex.: soft warm daylight from the side"></label>
            <button type="button" class="btn ghost small" id="st-reroll">Sortear outro visual</button>
            <p class="hint" id="st-look-hint"></p>
        </div>
    </section>

    <section class="stack" aria-label="Prévia vertical">
        <div class="phone" aria-label="Prévia 9:16"><canvas id="st-canvas" width="540" height="960" role="img" aria-label="Prévia da composição vertical"></canvas></div>
        <p class="hint">Prévia fiel ao PNG exportado (1080×1920). A imagem da notícia mantém o crédito da fonte.</p>
    </section>

    <section class="stack" aria-label="Texto e exportação">
        <div class="panel form-grid">
            <h2>4 · Texto</h2>
            <label>Título do projeto<input id="st-title" maxlength="160" placeholder="Ex.: React do Paulistão"></label>
            <div id="st-caption-box" hidden class="form-grid">
                <label>Frase de impacto (faixa preta do topo)<textarea id="st-caption" rows="2" maxlength="200" placeholder="Ex.: Senado vai gastar R$ 6 milhões com iPhones"></textarea></label>
                <label>Sugestões do roteiro<select id="st-caption-options"><option value="">Escolher uma headline ou o gancho…</option></select></label>
                <p class="hint">A frase de impacto pode ser mais chamativa, mas não pode mudar os fatos. A manchete real da fonte fica no card.</p>
            </div>
            <label><span id="st-headline-label">Headline na imagem</span><input id="st-headline" maxlength="200" placeholder="Headline fiel à notícia"></label>
            <label>Roteiro falado<textarea id="st-script" rows="7" maxlength="4000" placeholder="O texto que o personagem fala"></textarea><span class="hint" id="st-words"></span></label>
        </div>
        <div class="panel script-block">
            <div class="head"><span>Prompts de vídeo</span><button type="button" class="btn primary small" data-copy="st-prompt">Copiar todas as cenas</button></div>
            <div class="reference-box" id="st-reference"></div>
            <p class="hint" id="st-scenes-hint"></p>
            <div class="stack" id="st-prompts"></div>
            <textarea id="st-prompt" rows="3" readonly class="mono" hidden></textarea>
        </div>
        <div class="panel stack">
            <button type="button" class="btn primary" id="st-copy-all">Copiar tudo (roteiro, prompts, coordenadas e imagens)</button>
            <button type="button" class="btn ghost" id="st-download-pack">Baixar pacote completo (.zip)</button>
            <p class="hint" id="st-pack-status" role="status" aria-live="polite">Um clique e vai tudo: a fala completa, o visual, as coordenadas, os prompts de cada cena e as imagens. Ao colar em um documento ou editor, o texto e as imagens vêm juntos.</p>
        </div>
        <div class="panel stack">
            <button type="button" class="btn primary" id="st-save">Salvar projeto</button>
            <div class="row-actions">
                <button type="button" class="btn ghost" id="st-export-png">Exportar imagem de referência (PNG)</button>
                <button type="button" class="btn ghost" id="st-export-overlay">Exportar camada de legenda e card (PNG transparente)</button>
                <button type="button" class="btn ghost" id="st-export-txt">Exportar roteiro (.txt)</button>
            </div>
            <p class="hint">O projeto guarda texto, enquadramento e imagens escolhidas. Nenhum vídeo é gerado nem publicado automaticamente.</p>
        </div>
    </section>
</div>
<?php radar_page_end();
