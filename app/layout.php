<?php
declare(strict_types=1);

/** Shared HTML shell: sidebar, top bar, flash messages, news card. CSP forbids inline script/style. */

function radar_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return array<int,array{file:string,label:string,icon:string}> */
function radar_nav_items(): array
{
    return [
        ['file' => 'index.php', 'label' => 'Dashboard', 'icon' => 'home'],
        ['file' => 'noticias.php', 'label' => 'Notícias em alta', 'icon' => 'trend'],
        ['file' => 'fontes.php', 'label' => 'Fontes monitoradas', 'icon' => 'rss'],
        ['file' => 'salvas.php', 'label' => 'Notícias salvas', 'icon' => 'bookmark'],
        ['file' => 'estudio.php', 'label' => 'Estúdio de React', 'icon' => 'film'],
        ['file' => 'personagem.php', 'label' => 'Meu Personagem', 'icon' => 'user'],
        ['file' => 'roteiros.php', 'label' => 'Roteiros criados', 'icon' => 'script'],
        ['file' => 'config-ia.php', 'label' => 'Configurações de IA', 'icon' => 'spark'],
        ['file' => 'integracoes.php', 'label' => 'Integrações', 'icon' => 'plug'],
        ['file' => 'coletas.php', 'label' => 'Histórico de coleta', 'icon' => 'history'],
    ];
}

function radar_icon(string $name, string $class = 'icon'): string
{
    return '<svg class="' . radar_h($class) . '" aria-hidden="true" focusable="false"><use href="#i-' . radar_h($name) . '"></use></svg>';
}

function radar_icon_sprite(): string
{
    return '<svg width="0" height="0" aria-hidden="true" focusable="false"><defs>'
        . '<symbol id="i-home" viewBox="0 0 24 24"><path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/></symbol>'
        . '<symbol id="i-trend" viewBox="0 0 24 24"><path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/></symbol>'
        . '<symbol id="i-rss" viewBox="0 0 24 24"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1.5"/></symbol>'
        . '<symbol id="i-bookmark" viewBox="0 0 24 24"><path d="M6 3h12v18l-6-4-6 4z"/></symbol>'
        . '<symbol id="i-film" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/></symbol>'
        . '<symbol id="i-user" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></symbol>'
        . '<symbol id="i-script" viewBox="0 0 24 24"><path d="M6 3h9l4 4v14H6z"/><path d="M9 12h7M9 16h7"/></symbol>'
        . '<symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2z"/></symbol>'
        . '<symbol id="i-plug" viewBox="0 0 24 24"><path d="M9 2v6M15 2v6M6 8h12v3a6 6 0 0 1-12 0z"/><path d="M12 17v5"/></symbol>'
        . '<symbol id="i-history" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></symbol>'
        . '<symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></symbol>'
        . '<symbol id="i-refresh" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></symbol>'
        . '<symbol id="i-radar" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M7.8 7.8a6 6 0 0 0 0 8.4M16.2 7.8a6 6 0 0 1 0 8.4M4.9 4.9a10 10 0 0 0 0 14.2M19.1 4.9a10 10 0 0 1 0 14.2"/></symbol>'
        . '<symbol id="i-ext" viewBox="0 0 24 24"><path d="M14 3h7v7M10 14L21 3M19 14v7H3V5h7"/></symbol>'
        . '<symbol id="i-close" viewBox="0 0 24 24"><path d="M5 5l14 14M19 5L5 19"/></symbol>'
        . '<symbol id="i-copy" viewBox="0 0 24 24"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></symbol>'
        . '<symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></symbol>'
        . '<symbol id="i-image" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-8 8"/></symbol>'
        . '</defs></svg>';
}

/* ---------- flash messages (post/redirect/get) ---------- */

function radar_flash_set(string $type, string $message): void
{
    radar_session();
    $_SESSION['flash'][] = ['type' => $type === 'error' ? 'error' : 'ok', 'message' => $message];
}

/** @return array<int,array{type:string,message:string}> */
function radar_flash_take(): array
{
    radar_session();
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : [];
}

/** For classic form posts: returns true when the CSRF token is valid, otherwise flashes and redirects. */
function radar_form_guard(string $redirect): bool
{
    radar_session();
    $received = $_POST['csrf'] ?? '';
    $expected = $_SESSION['csrf'] ?? '';
    if (is_string($received) && is_string($expected) && $expected !== '' && hash_equals($expected, $received)) {
        return true;
    }
    radar_flash_set('error', 'Sessão expirada ou formulário inválido. Tente novamente.');
    header('Location: ' . $redirect, true, 303);
    exit;
}

function radar_redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

/**
 * Open the page: headers, sidebar, top bar. $active is the nav file name.
 *
 * @param array{scripts?:array<int,string>,body_class?:string,wide?:bool} $options
 */
function radar_page_start(string $title, string $active, array $options = []): void
{
    $csrf = radar_csrf_token();
    radar_sources_seed();
    $stats = radar_dashboard_stats();
    $stale = radar_collect_is_stale();
    $last = $stats['last_collect_at'];
    $scripts = array_merge(['app.js'], $options['scripts'] ?? []);
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="dark"><meta name="robots" content="noindex,nofollow">'
        . '<meta name="csrf-token" content="' . radar_h($csrf) . '">'
        . '<meta name="auto-collect" content="' . ($stale ? '1' : '0') . '">'
        . '<title>' . radar_h($title) . ' · Radar Viral BR</title>'
        . '<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">'
        . '<link rel="stylesheet" href="assets/app.css">';
    foreach ($scripts as $script) {
        echo '<script src="assets/' . radar_h($script) . '" defer></script>';
    }
    echo '</head><body class="' . radar_h($options['body_class'] ?? '') . '">' . radar_icon_sprite();
    echo '<a class="skip-link" href="#main">Pular para o conteúdo</a>';
    echo '<div class="app"><aside class="sidebar" id="sidebar" aria-label="Menu principal">';
    echo '<a class="brand" href="index.php" aria-label="Radar Viral BR, início">' . radar_icon('radar', 'icon brand-icon') . '<span>RADAR <strong>VIRAL</strong> BR</span></a><nav><ul>';
    foreach (radar_nav_items() as $item) {
        $current = $item['file'] === $active;
        echo '<li><a href="' . radar_h($item['file']) . '"' . ($current ? ' class="active" aria-current="page"' : '') . '>' . radar_icon($item['icon']) . '<span>' . radar_h($item['label']) . '</span></a></li>';
    }
    echo '</ul></nav><form method="post" action="logout.php" class="logout"><input type="hidden" name="csrf" value="' . radar_h($csrf) . '"><button type="submit" class="btn ghost small">Sair</button></form></aside>';
    echo '<div class="scrim" id="scrim" hidden></div>';
    echo '<div class="content"><header class="topbar">';
    echo '<button type="button" class="icon-btn menu-btn" id="menu-btn" aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">' . radar_icon('menu') . '</button>';
    echo '<a class="brand brand-mobile" href="index.php">' . radar_icon('radar', 'icon brand-icon') . '<span>RADAR <strong>VIRAL</strong> BR</span></a>';
    echo '<div class="topbar-status"><span class="update-label" id="update-label" data-ts="' . radar_h($last ?? '') . '">'
        . ($last === null ? 'Ainda sem coleta' : 'Atualizado ' . radar_h(radar_age_label(max(0, (int)round((time() - $last) / 60))))) . '</span>'
        . '<button type="button" class="btn primary small" id="collect-btn">' . radar_icon('refresh') . '<span>Atualizar agora</span></button></div>';
    echo '</header><main id="main" class="main' . (!empty($options['wide']) ? ' wide' : '') . '" tabindex="-1">';
    foreach (radar_flash_take() as $flash) {
        echo '<div class="notice ' . ($flash['type'] === 'error' ? 'error' : 'ok') . '" role="' . ($flash['type'] === 'error' ? 'alert' : 'status') . '">' . radar_h($flash['message']) . '</div>';
    }
    echo '<div id="live-status" class="notice info" role="status" hidden></div>';
}

function radar_page_end(): void
{
    echo '</main></div></div>';
    echo radar_react_dialog();
    echo '<div class="toast" id="toast" role="status" aria-live="polite" hidden></div></body></html>';
}

/** Minimal centred card page (OAuth consent, errors). Same CSP: no inline code. */
function radar_simple_page(string $title, string $bodyHtml, array $options = []): never
{
    $meta = '';
    if (!empty($options['redirect'])) {
        $meta = '<meta name="oauth-redirect" content="' . radar_h($options['redirect']) . '">';
    }
    http_response_code((int)($options['status'] ?? 200));
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="dark"><meta name="robots" content="noindex,nofollow">' . $meta
        . '<title>' . radar_h($title) . ' · Radar Viral BR</title><link rel="icon" href="/assets/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/app.css">'
        . '<script src="/assets/oauth.js" defer></script></head><body class="login-page"><main><section class="auth-card">'
        . '<p class="eyebrow">RADAR VIRAL BR</p>' . $bodyHtml . '</section></main></body></html>';
    exit;
}

function radar_react_dialog(): string
{
    $tones = '';
    foreach (RADAR_TONES as $key => $label) {
        $tones .= '<option value="' . radar_h($key) . '">' . radar_h($label) . '</option>';
    }
    $layouts = '';
    foreach (radar_video_templates() as $key => $template) {
        $layouts .= '<option value="' . radar_h($key) . '">' . radar_h($template['label']) . '</option>';
    }
    $config = radar_ai_config();
    $mode = $config['provider'] !== 'none' && $config['has_key']
        ? 'A IA configurada (' . RADAR_AI_PROVIDERS[$config['provider']] . ') escreverá o roteiro.'
        : 'Nenhuma IA configurada: será gerado um rascunho conservador. Configure em Configurações de IA.';
    return '<dialog id="react-dialog" aria-labelledby="react-dialog-title"><form method="dialog" id="react-form" class="dialog-body">'
        . '<div class="dialog-head"><h2 id="react-dialog-title">Gerar react com IA</h2><button type="button" class="icon-btn" data-close aria-label="Fechar">' . radar_icon('close') . '</button></div>'
        . '<p class="muted" id="react-news-title"></p><p class="hint">' . radar_h($mode) . '</p>'
        . '<div class="field-row"><label>Duração<select id="react-duration"><option>15</option><option>20</option><option selected>25</option><option>30</option><option>35</option></select></label>'
        . '<label>Tom<select id="react-tone">' . $tones . '</select></label></div>'
        . '<label>Modelo do vídeo<select id="react-layout">' . $layouts . '</select></label>'
        . '<label>O que você já conferiu? <span class="optional">opcional</span><textarea id="react-context" rows="3" maxlength="2000" placeholder="Fatos confirmados na matéria. Sem isso, o roteiro usa só a manchete e o resumo do veículo."></textarea></label>'
        . '<p class="error-text" id="react-error" role="alert" hidden></p>'
        . '<div class="dialog-actions"><button type="button" class="btn ghost" data-close>Cancelar</button><button type="submit" class="btn primary" id="react-submit">Gerar react</button></div>'
        . '</form></dialog>';
}

function radar_score_class(int $score): string
{
    return $score >= 70 ? 'hot' : ($score >= 50 ? 'warm' : 'cool');
}

/** One news card. Images are the publisher's own (hotlinked) until a copy is saved on purpose. */
function radar_news_card(array $n, bool $showSave = true): string
{
    $image = $n['image'] !== null
        ? '<img src="' . radar_h($n['image']) . '" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">'
        : '<div class="no-image">' . radar_icon('image') . '<span>Sem imagem</span></div>';
    $age = $n['published_known'] ? radar_age_label($n['age_minutes']) : 'visto ' . radar_age_label($n['age_minutes']);
    $coverage = $n['source_count'] > 1 ? '<span class="pill">' . (int)$n['source_count'] . ' veículos</span>' : '';
    $trend = $n['trend_hits'] !== [] ? '<span class="pill trend">Em alta: ' . radar_h($n['trend_hits'][0]['query']) . '</span>' : '';
    $html = '<article class="card" data-news="' . (int)$n['id'] . '">'
        . '<a class="card-media" href="noticia.php?id=' . (int)$n['id'] . '" tabindex="-1" aria-hidden="true">' . $image
        . '<span class="score ' . radar_score_class($n['score']) . '" title="Estimativa própria do Radar Viral BR (0 a 100)"><b>' . (int)$n['score'] . '</b><small>viral</small></span></a>'
        . '<div class="card-body"><span class="eyebrow">' . radar_h($n['category']) . '</span>'
        . '<h3><a href="noticia.php?id=' . (int)$n['id'] . '">' . radar_h($n['title']) . '</a></h3>'
        . '<p class="meta">' . radar_h($n['source']) . ' · ' . radar_h($age) . '</p>'
        . '<div class="pills">' . $coverage . $trend . '</div>';
    if ($n['score_summary'] !== '') {
        $html .= '<p class="why">' . radar_h($n['score_summary']) . '</p>';
    }
    $html .= '<div class="card-actions"><a class="btn primary" href="estudio.php?news=' . (int)$n['id'] . '">Gerar react</a>';
    if ($showSave) {
        $html .= '<button type="button" class="btn ghost" data-save="' . (int)$n['id'] . '" aria-pressed="' . ($n['saved'] ? 'true' : 'false') . '">' . ($n['saved'] ? 'Salva' : 'Salvar') . '</button>';
    }
    $html .= '<a class="btn ghost" href="noticia.php?id=' . (int)$n['id'] . '">Detalhes</a></div></div></article>';
    return $html;
}

function radar_url(string $file, array $params = []): string
{
    $params = array_filter($params, static fn($v): bool => $v !== '' && $v !== null && $v !== false && $v !== 0);
    return $file . ($params === [] ? '' : '?' . http_build_query($params));
}

function radar_date(?int $timestamp): string
{
    return $timestamp === null ? '—' : date('d/m/Y H:i', $timestamp);
}
