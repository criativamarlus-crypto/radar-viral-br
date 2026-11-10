<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/feeds.php';
radar_require_login();
$csrf = htmlspecialchars(radar_csrf_token(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= $csrf ?>">
    <meta name="color-scheme" content="dark">
    <title>Radar Viral BR · Seu próximo react</title>
    <link rel="stylesheet" href="assets/style.css">
    <script src="assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Pular para as notícias</a>
<header class="topbar">
    <a class="brand" href="index.php" aria-label="Radar Viral BR, início"><span class="brand-icon" aria-hidden="true">◉</span><span>Radar <strong>Viral BR</strong><small>PAUTAS QUE MERECEM SUA REAÇÃO</small></span></a>
    <nav class="topnav" aria-label="Navegação principal"><a href="#radar" class="active">Radar de notícias</a><a href="#studio">Estúdio de react</a></nav>
    <div class="account"><span class="private-badge"><span aria-hidden="true">▣</span> Painel privado</span><form method="post" action="logout.php"><input type="hidden" name="csrf" value="<?= $csrf ?>"><button class="text-button" type="submit">Sair <span aria-hidden="true">↗</span></button></form></div>
</header>
<main id="main" class="page-wrap">
    <section class="hero" aria-labelledby="page-title">
        <div><div class="eyebrow"><span class="status-dot" aria-hidden="true"></span> SEU RADAR EDITORIAL</div><h1 id="page-title">O próximo react<br>começa <span>aqui.</span></h1><p>Encontre uma pauta. Confira a fonte.<br>Transforme informação em uma boa conversa.</p></div>
        <div class="hero-card"><span class="hero-card-mark" aria-hidden="true">↗</span><span class="eyebrow">DO RADAR PARA O VÍDEO</span><strong>Uma pauta.<br>Seu ponto de vista.</strong><p>Roteiros de 15 a 35 segundos, com seu personagem e os fatos em primeiro lugar.</p><a href="#studio">Abrir estúdio <span aria-hidden="true">→</span></a></div>
    </section>
    <section class="overview" aria-label="Resumo do radar">
        <div><span class="metric-icon" aria-hidden="true">▤</span><p><strong id="metric-count">—</strong><span>pautas nesta categoria</span></p></div>
        <div><span class="metric-icon" aria-hidden="true">◷</span><p><strong id="metric-updated">Aguardando consulta</strong><span>última coleta do feed</span></p></div>
        <div><span class="metric-icon" aria-hidden="true">▣</span><p><strong>9:16</strong><span>prévia vertical para seu react</span></p></div>
    </section>
    <section class="trends-strip" aria-labelledby="trends-heading">
        <div class="trends-heading"><span aria-hidden="true">↗</span><h2 id="trends-heading">Google Trends</h2><small>Buscas em alta · Brasil</small></div>
        <div id="trends" class="trends-list" aria-live="polite"><p class="muted">Consultando a fonte…</p></div>
        <button id="reload-trends" class="icon-button" type="button" aria-label="Atualizar Google Trends">↻</button>
    </section>
    <div class="workspace">
        <section id="radar" class="radar" aria-labelledby="radar-heading">
            <div class="section-heading"><div><span class="eyebrow">CURADORIA PARA CRIADORES</span><h2 id="radar-heading">No seu radar <span class="gold-dot" aria-hidden="true">●</span></h2></div><button type="button" id="refresh" class="secondary-button"><span aria-hidden="true">↻</span> Atualizar</button></div>
            <div class="tabs" role="group" aria-label="Categoria de notícias"><?php foreach (array_keys(radar_categories()) as $category): ?><button type="button" data-cat="<?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?>" aria-pressed="<?= $category === 'Geral' ? 'true' : 'false' ?>" class="<?= $category === 'Geral' ? 'on' : '' ?>"><?= htmlspecialchars($category, ENT_QUOTES, 'UTF-8') ?></button><?php endforeach; ?></div>
            <div class="filter-row"><label class="search"><span aria-hidden="true">⌕</span><input type="search" id="search" placeholder="Buscar manchete ou fonte" aria-label="Buscar manchete ou fonte" autocomplete="off"></label><label class="sort-label"><span class="sr-only">Ordenar pautas</span><select id="sort"><option value="editorial">Prioridade editorial</option><option value="recent">Mais recentes</option></select></label></div>
            <p class="editorial-note">Prioridade calculada por critérios editoriais. Não é um ranking oficial de viralização. Confira cada notícia na fonte.</p>
            <div class="results-line"><p id="status" role="status">Carregando notícias…</p><span id="category-caption">Geral</span></div>
            <div id="feed-warning" class="notice" hidden></div>
            <section id="cards" class="cards" aria-label="Notícias" aria-busy="true"></section>
            <div id="empty-state" class="empty-state" hidden><span aria-hidden="true">◎</span><h3 id="empty-heading">Nenhuma pauta encontrada</h3><p id="empty-message">Tente outra busca ou escolha uma categoria diferente.</p><button type="button" id="empty-reset" class="secondary-button">Limpar busca</button></div>
        </section>
        <aside id="studio" class="studio" aria-labelledby="studio-heading">
            <div class="studio-top"><span class="eyebrow">DA MANCHETE AO ROTEIRO</span><h2 id="studio-heading">Estúdio de react <span aria-hidden="true">✦</span></h2><p>Seu personagem. Sua voz. Fatos primeiro.</p></div>
            <div class="studio-content">
                <div id="selected-empty" class="selected-empty"><span aria-hidden="true">↖</span><strong>Qual pauta merece sua reação?</strong><p>Escolha “Criar react” em uma notícia para começar.</p></div>
                <div id="selected-story" class="selected-story" hidden><span class="field-caption">PAUTA SELECIONADA</span><h3 id="selected-title"></h3><a id="selected-source" target="_blank" rel="noopener noreferrer">Conferir notícia na fonte ↗</a><span class="verification">Checagem pendente · leia a matéria</span></div>
                <form id="script-form">
                    <div class="field-pair"><label>Duração<select id="duration" name="duration"><option value="15">15 segundos</option><option value="20">20 segundos</option><option value="25" selected>25 segundos</option><option value="30">30 segundos</option><option value="35">35 segundos</option></select></label><label>Tom<select id="tone" name="tone"><option value="bem-humorado">Bem-humorado</option><option value="direto">Direto</option></select></label></div>
                    <label for="context">O que você já conferiu? <span class="optional">opcional</span></label><textarea id="context" name="verified_context" rows="3" maxlength="2000" placeholder="Adicione apenas fatos confirmados na matéria. Não cole o artigo inteiro."></textarea>
                    <p class="field-help">Sem contexto, o roteiro usa apenas a manchete e pede checagem antes da gravação.</p>
                    <button type="submit" id="generate" class="primary-button full" disabled><span aria-hidden="true">✦</span> Gerar roteiro e prompt</button>
                </form>
                <p id="generation-status" class="generation-status" role="status"></p>
                <div id="script-result" class="script-result" hidden>
                    <div class="output-heading"><label for="headline">Headline sugerida</label><button type="button" class="copy-button" data-copy="headline">Copiar</button></div><textarea id="headline" rows="2" readonly></textarea>
                    <div class="output-heading"><label for="script">Roteiro de react</label><button type="button" class="copy-button" data-copy="script">Copiar</button></div><textarea id="script" rows="8" readonly></textarea>
                    <p id="script-caution" class="caution"></p>
                    <details class="prompt-details"><summary>Prompt completo do personagem</summary><div class="output-heading"><label class="sr-only" for="prompt">Prompt completo</label><button type="button" class="copy-button" data-copy="prompt">Copiar prompt</button></div><textarea id="prompt" rows="10" readonly></textarea></details>
                </div>
                <button type="button" id="open-preview" class="secondary-button full" disabled><span aria-hidden="true">▣</span> Montar prévia 9:16</button>
                <p class="studio-footnote">Rascunhos editoriais para revisão. Nenhum vídeo é gerado ou publicado automaticamente.</p>
            </div>
        </aside>
    </div>
    <footer><a class="brand brand-small" href="index.php">Radar <strong>Viral BR</strong></a><p>Uma boa reação começa com uma fonte confiável.</p><span>Curadoria editorial · Brasil</span></footer>
</main>
<dialog id="preview-dialog" aria-labelledby="preview-heading">
    <div class="dialog-header"><div><span class="eyebrow">MONTE SUA COMPOSIÇÃO</span><h2 id="preview-heading">Prévia vertical <span>9:16</span></h2></div><button id="close-preview" class="icon-button" type="button" aria-label="Fechar prévia">✕</button></div>
    <div class="preview-layout"><div class="vertical-preview" aria-label="Prévia de composição vertical"><div class="preview-news"><span class="preview-label">A PAUTA</span><img id="preview-cover" alt="Imagem da pauta selecionada" hidden><div id="preview-cover-empty" class="preview-image-empty"><span aria-hidden="true">▧</span><p>Envie um print real ou uma capa permitida</p></div><h3 id="preview-title"></h3><a id="preview-source" target="_blank" rel="noopener noreferrer">Abrir fonte ↗</a></div><div class="preview-character"><img id="character-image" src="assets/character-placeholder.svg" alt="Espaço reservado para a imagem do seu personagem"><span id="character-label">Seu personagem aparece aqui</span></div></div>
    <div class="preview-settings"><h3>A notícia e o seu personagem.</h3><p>Use uma imagem que você tem autorização para usar. A prévia organiza a composição; não gera um print automático nem um vídeo.</p><label for="cover-file" class="upload-label">Imagem da pauta<span>Print real ou capa permitida</span></label><input type="file" id="cover-file" accept="image/jpeg,image/png,image/webp"><button type="button" id="clear-cover" class="text-button" hidden>Remover imagem desta prévia</button><label for="character-file" class="upload-label">Imagem do personagem<span>Envie o personagem original</span></label><input type="file" id="character-file" accept="image/jpeg,image/png,image/webp"><p class="field-help">JPEG, PNG ou WebP, até 5 MB. As imagens são armazenadas em área privada.</p><p class="character-description">Personagem: homem brasileiro negro, adulto, robusto, de óculos, corrente de ouro, relógio e sorriso marcante.</p><p id="upload-status" role="status"></p><a id="preview-original" class="secondary-button" target="_blank" rel="noopener noreferrer">Ler a notícia original ↗</a></div></div>
</dialog>
<div id="toast" class="toast" role="status" hidden></div>
</body>
</html>
