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
    'headline' => $s['headlines'][0] ?? $s['news_title'], 'spoken' => $s['spoken'], 'expression' => $s['expression'], 'scene' => $s['scene'],
    'duration' => $s['duration'], 'video_prompt' => $s['video_prompt'],
], radar_scripts_list(100));

$media = $news !== null ? $news['media'] : [];
$mediaOptions = array_map(static fn(array $m): array => ['id' => $m['id'], 'label' => $m['label'], 'kind' => $m['kind'], 'credit' => $m['credit']], array_values(array_filter($media, static fn(array $m): bool => in_array($m['kind'], ['cover', 'screenshot', 'upload'], true))));
$characters = array_map(static fn(array $m): array => ['id' => $m['id'], 'label' => $m['label'], 'primary' => (int)$m['is_primary'] === 1], radar_character_images());

$state = [
    'news' => $news === null ? null : ['id' => $news['id'], 'title' => $news['title'], 'source' => $news['source'], 'url' => $news['url'], 'image' => $news['image'], 'image_credit' => $news['image_credit']],
    'newsOptions' => $newsOptions,
    'scripts' => $scriptOptions,
    'media' => $mediaOptions,
    'characters' => $characters,
    'expressions' => array_map(static fn(array $e): string => $e['label'], radar_character_expressions()),
    'scenes' => radar_character_scenes(),
    'project' => $project,
    'scriptId' => $script['id'] ?? null,
    'preselectCover' => preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/D', radar_str($_GET['cover'] ?? '')) === 1 ? $_GET['cover'] : null,
];
$json = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);

radar_page_start('Estúdio de React', 'estudio.php', ['scripts' => ['studio.js'], 'wide' => true]);
?>
<div class="page-head">
    <div><h1>Estúdio de React</h1><p>Monte a composição vertical 9:16: notícia no topo, personagem embaixo. Prepara os materiais para a ferramenta de vídeo; não gera o vídeo final.</p></div>
    <a class="btn ghost" href="roteiros.php">Projetos e roteiros</a>
</div>
<script type="application/json" id="studio-data"><?= $json ?></script>

<div class="studio" id="studio">
    <section class="stack" aria-label="Material">
        <div class="panel form-grid">
            <h2>1 · Notícia e roteiro</h2>
            <label>Notícia<select id="st-news"><option value="">Escolha uma notícia…</option></select></label>
            <label>Roteiro<select id="st-script-select"><option value="">Sem roteiro (escrever aqui)</option></select></label>
            <?php if ($news !== null): ?><button type="button" class="btn ghost small" data-react="<?= (int)$news['id'] ?>" data-title="<?= radar_h($news['title']) ?>">Gerar novo react com IA</button><?php endif; ?>
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
    </section>

    <section class="stack" aria-label="Prévia vertical">
        <div class="phone" aria-label="Prévia 9:16"><canvas id="st-canvas" width="540" height="960" role="img" aria-label="Prévia da composição vertical"></canvas></div>
        <p class="hint">Prévia fiel ao PNG exportado (1080×1920). A imagem da notícia mantém o crédito da fonte.</p>
    </section>

    <section class="stack" aria-label="Texto e exportação">
        <div class="panel form-grid">
            <h2>4 · Texto</h2>
            <label>Título do projeto<input id="st-title" maxlength="160" placeholder="Ex.: React do Paulistão"></label>
            <label>Headline na imagem<input id="st-headline" maxlength="200" placeholder="Headline fiel à notícia"></label>
            <label>Roteiro falado<textarea id="st-script" rows="7" maxlength="4000" placeholder="O texto que o personagem fala"></textarea><span class="hint" id="st-words"></span></label>
        </div>
        <div class="panel script-block">
            <div class="head"><span>Prompt de vídeo</span><button type="button" class="btn primary small" data-copy="st-prompt">Copiar prompt</button></div>
            <textarea id="st-prompt" rows="9" readonly class="mono"></textarea>
        </div>
        <div class="panel stack">
            <button type="button" class="btn primary" id="st-save">Salvar projeto</button>
            <div class="row-actions">
                <button type="button" class="btn ghost" id="st-export-png">Exportar imagem de referência (PNG)</button>
                <button type="button" class="btn ghost" id="st-export-txt">Exportar roteiro (.txt)</button>
            </div>
            <p class="hint">O projeto guarda texto, enquadramento e imagens escolhidas. Nenhum vídeo é gerado nem publicado automaticamente.</p>
        </div>
    </section>
</div>
<?php radar_page_end();
