<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$id = (int)($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    radar_form_guard('roteiro.php?id=' . $id);
    try {
        radar_script_update($id, $_POST);
        radar_flash_set('ok', 'Roteiro atualizado. O prompt de vídeo foi refeito com o texto novo.');
    } catch (InvalidArgumentException $error) {
        radar_flash_set('error', $error->getMessage());
    }
    radar_redirect('roteiro.php?id=' . $id);
}
$script = $id > 0 ? radar_script_get($id) : null;
if ($script === null) {
    http_response_code(404);
    radar_page_start('Roteiro não encontrado', 'roteiros.php');
    echo '<div class="empty-state"><h3>Roteiro não encontrado</h3><p><a href="roteiros.php">Voltar aos roteiros</a></p></div>';
    radar_page_end();
    exit;
}
$csrf = radar_csrf_token();
$references = radar_character_references($script['character']);
$expressions = radar_character_expressions();
$scenes = radar_character_scenes();

radar_page_start('Roteiro de react', 'roteiros.php');
?>
<p><a href="roteiros.php">← Roteiros criados</a></p>
<div class="page-head">
    <div><h1>Roteiro de react</h1>
        <p><?= radar_h($script['character_name']) ?> · <?= radar_h($script['mode_label']) ?> · <?= count($script['video_prompts']) ?> <?= count($script['video_prompts']) === 1 ? 'cena' : 'cenas' ?> · ~<?= (int)$script['estimated_seconds'] ?> s falados · tom <?= radar_h($script['tone']) ?></p></div>
    <div class="row-actions">
        <a class="btn primary" href="estudio.php?roteiro=<?= (int)$script['id'] ?>">Abrir no Estúdio</a>
        <a class="btn ghost" href="api.php?action=script&amp;id=<?= (int)$script['id'] ?>&amp;format=txt">Baixar roteiro (.txt)</a>
        <?php if ($script['subtitles_srt'] !== ''): ?><a class="btn ghost" href="api.php?action=script&amp;id=<?= (int)$script['id'] ?>&amp;format=srt">Baixar legendas (.srt)</a><?php endif; ?>
        <?php if ($script['news_id'] !== null): ?><a class="btn ghost" href="noticia.php?id=<?= (int)$script['news_id'] ?>">Ver notícia</a><?php endif; ?>
    </div>
</div>

<div class="panel"><strong><?= radar_h($script['news_title']) ?></strong><br>
    <a href="<?= radar_h($script['news_url']) ?>" target="_blank" rel="noopener noreferrer"><?= radar_h($script['news_source']) ?> — conferir a matéria original <?= radar_icon('ext') ?></a></div>

<?php if ($script['update_of'] !== null): ?>
    <div class="notice info section"><strong>Atualização</strong> do <a href="roteiro.php?id=<?= (int)$script['update_of'] ?>">roteiro #<?= (int)$script['update_of'] ?></a>: este vídeo usa só os fatos novos.</div>
<?php endif; ?>
<?php if ($script['warnings'] !== [] || $script['fact_check'] !== []): ?>
    <div class="warnings section">
        <?php foreach ($script['warnings'] as $warning): ?><div class="notice warn" role="status"><?= radar_h($warning) ?></div><?php endforeach; ?>
        <?php if ($script['fact_check'] !== []): ?><div class="notice info"><strong>Conferir antes de gravar:</strong><ul><?php foreach ($script['fact_check'] as $note): ?><li><?= radar_h($note) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($script['format'] === 'tv3'): ?>
<?php
$facts = $script['facts'];
$factGroups = ['verified' => 'O que a fonte reporta', 'attributed' => 'Alegações atribuídas a alguém', 'uncertain' => 'Incertezas (não viram fato)', 'new' => 'Fatos novos desta atualização'];
$slotLabels = array_map(static fn(array $slot): string => $slot['label'], radar_reference_slots());
?>
<div class="detail">
    <form method="post" action="roteiro.php" class="stack">
        <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$script['id'] ?>">
        <div class="panel">
            <h2>Título e publicação</h2>
            <p class="hint"><?= radar_h($script['character_name']) ?> · id <code><?= radar_h($script['character_id']) ?></code> · versão <?= (int)$script['character_version'] ?> do cadastro<?= $script['strategy'] !== '' ? ' · estratégia: ' . radar_h($script['strategy']) : '' ?></p>
            <label>Título do vídeo<input name="video_title" maxlength="140" value="<?= radar_h($script['video_title']) ?>"></label>
            <label>Legenda de publicação<textarea name="publication_caption" rows="5" maxlength="600" id="f-caption"><?= radar_h($script['publication_caption']) ?></textarea></label>
            <p><button type="button" class="btn ghost small" data-copy="f-caption">Copiar legenda</button></p>
            <?php foreach ($script['headlines'] as $index => $headline): ?>
                <div class="headline-option"><span id="hl-<?= $index ?>"><?= radar_h($headline) ?></span><button type="button" class="btn ghost small" data-copy="hl-<?= $index ?>"><?= radar_icon('copy') ?>Copiar</button></div>
            <?php endforeach; ?>
        </div>
        <?php foreach ($script['scenes_data'] as $scene): $n = (int)$scene['number']; ?>
            <div class="panel script-block">
                <div class="head"><span>Cena <?= $n ?> de 3 — <?= radar_h($scene['role_label']) ?> · <?= (int)$scene['duration_seconds'] ?> s · <?= (int)$scene['words'] ?> palavras</span>
                    <span><button type="button" class="btn ghost small" data-copy="scene-prompt-<?= $n ?>">Copiar prompt da cena <?= $n ?></button>
                    <button type="button" class="btn small" data-act="scene_regenerate" data-id="<?= (int)$script['id'] ?>" data-scene="<?= $n ?>" data-busy="Refazendo…" data-confirm="Refazer só a cena <?= $n ?>? As outras cenas ficam como estão.">Refazer só esta cena</button></span></div>
                <p class="hint"><strong>Objetivo:</strong> <?= radar_h($scene['objective']) ?></p>
                <label>Fala exata da cena <?= $n ?><textarea name="scene_<?= $n ?>" rows="3" maxlength="420" id="f-scene-<?= $n ?>"><?= radar_h($scene['narration']) ?></textarea></label>
                <div class="field-row">
                    <label>Título na tela<input name="scene_<?= $n ?>_title" maxlength="60" value="<?= radar_h($scene['screen_title']) ?>"></label>
                    <label>Legenda (sincronizada com a fala)<input value="<?= radar_h($scene['caption']) ?>" readonly></label>
                </div>
                <div class="pills">
                    <span class="pill">Tom: <?= radar_h($scene['emotion']) ?></span><span class="pill">Expressão: <?= radar_h($scene['expression_label']) ?></span><span class="pill"><?= radar_h($scene['camera_label']) ?></span>
                </div>
                <p class="hint">Gestos: <?= radar_h($scene['gestures']) ?> · Câmera: <?= radar_h($scene['camera_movement']) ?></p>
                <p class="hint">Continuidade: <?= radar_h($scene['continuity']) ?></p>
                <p class="hint">Referência visual: <?= radar_h(implode(' · ', $scene['visual_reference'])) ?: 'nenhuma imagem cadastrada' ?></p>
                <textarea id="scene-prompt-<?= $n ?>" rows="10" readonly class="mono"><?= radar_h($scene['muse_prompt']) ?></textarea>
            </div>
        <?php endforeach; ?>
        <div class="panel">
            <div class="field-row">
                <label>Modelo do vídeo<select name="layout"><?php foreach (radar_video_templates() as $key => $template): ?><option value="<?= radar_h($key) ?>"<?= $script['layout'] === $key ? ' selected' : '' ?>><?= radar_h($template['label']) ?></option><?php endforeach; ?></select></label>
                <label>Iluminação e clima<input name="lighting" maxlength="240" value="<?= radar_h($script['direction']['lighting']) ?>"></label>
            </div>
            <p class="hint">Roupa e joias da personagem são fixas (vêm da imagem-mestre). Só a luz, a câmera e os gestos mudam.</p>
            <label class="check"><input type="checkbox" name="reroll_direction" value="1"> Sortear gestos e câmera novos ao salvar</label>
            <button class="btn primary" type="submit">Salvar alterações e refazer os prompts</button>
        </div>
    </form>

    <aside class="stack">
        <div class="panel">
            <h2>Fatos usados</h2>
            <?php foreach ($factGroups as $key => $label): if (empty($facts[$key])) { continue; } ?>
                <h3><?= radar_h($label) ?></h3><ul><?php foreach ((array)$facts[$key] as $item): ?><li><?= radar_h($item) ?></li><?php endforeach; ?></ul>
            <?php endforeach; ?>
            <?php if (!empty($facts['opinions'])): ?><h3>Opinião da apresentadora</h3><ul><?php foreach ((array)$facts['opinions'] as $item): ?><li><?= radar_h($item) ?></li><?php endforeach; ?></ul><?php endif; ?>
            <p class="hint"><?= !empty($facts['incomplete']) ? 'Só havia a manchete: confirme os fatos na matéria antes de gravar. ' : '' ?>A criatividade fica na apresentação; os fatos vêm da matéria<?= !empty($facts['article_chars']) ? ' (' . (int)$facts['article_chars'] . ' caracteres do texto foram lidos na fonte)' : '' ?>.</p>
        </div>
        <div class="panel">
            <div class="head"><h2>Anexe estas imagens em cada cena</h2></div>
            <?php if ($references !== []): ?>
                <p class="hint">A imagem-mestre vale mais do que qualquer texto. Se a ferramenta aceitar poucas imagens, anexe a mestre primeiro.</p>
                <div class="reference-list">
                    <?php foreach ($references as $reference): ?>
                        <figure><img src="media.php?id=<?= radar_h($reference['id']) ?>" alt="<?= radar_h($slotLabels[$reference['role']] ?? 'Referência') ?>" loading="lazy"><figcaption><?= radar_h($slotLabels[$reference['role']] ?? 'Referência') ?><br><a class="btn ghost small" href="media.php?id=<?= radar_h($reference['id']) ?>&amp;download=1">Baixar</a></figcaption></figure>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="notice warn">Faltam as imagens de <?= radar_h($script['character_name']) ?>. Cadastre em <a href="personagem.php?c=<?= radar_h($script['character']) ?>">Meus Personagens</a> para anexar aqui.</div>
            <?php endif; ?>
            <?php $voice = radar_character_voice_asset($script['character']); if ($voice !== null): ?>
                <p class="hint">Áudio de referência da voz (se a ferramenta aceitar): <a href="media.php?id=<?= radar_h($voice['id']) ?>&amp;download=1">baixar</a></p>
            <?php endif; ?>
        </div>
        <div class="panel script-block">
            <div class="head"><span>Todos os prompts do Muse</span><button type="button" class="btn primary small" data-copy="video-prompt">Copiar todos</button></div>
            <textarea id="video-prompt" rows="6" readonly class="mono"><?= radar_h($script['video_prompt']) ?></textarea>
        </div>
        <div class="panel script-block">
            <div class="head"><span>Texto falado completo · ~<?= (int)$script['estimated_seconds'] ?> s</span><button type="button" class="btn ghost small" data-copy="spoken">Copiar</button></div>
            <textarea id="spoken" rows="7" readonly><?= radar_h($script['spoken']) ?></textarea>
        </div>
        <div class="panel script-block">
            <div class="head"><span>Legendas (SRT) · tempos estimados</span><button type="button" class="btn ghost small" data-copy="srt">Copiar</button></div>
            <textarea id="srt" rows="8" readonly class="mono"><?= radar_h($script['subtitles_srt']) ?></textarea>
            <p class="hint">Os textos e títulos são dados para a edição: não dependa do gerador de vídeo para escrever palavras dentro do quadro. Com o áudio pronto, troque os tempos pelos reais.</p>
        </div>
    </aside>
</div>
<?php radar_page_end(); exit; ?>
<?php endif; ?>

<div class="detail">
    <form method="post" action="roteiro.php" class="stack">
        <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$script['id'] ?>">
        <div class="panel">
            <h2>A. Headlines virais <span class="muted">(fiéis aos fatos)</span></h2>
            <?php foreach ($script['headlines'] as $index => $headline): ?>
                <div class="headline-option"><span id="hl-<?= $index ?>"><?= radar_h($headline) ?></span><button type="button" class="btn ghost small" data-copy="hl-<?= $index ?>"><?= radar_icon('copy') ?>Copiar</button></div>
            <?php endforeach; ?>
            <label>Adicionar/usar outra headline<input name="headline" maxlength="160" placeholder="Opcional: sua própria headline"></label>
        </div>
        <div class="panel script-block">
            <div class="head"><span>B. Gancho (3 primeiros segundos)</span><button type="button" class="btn ghost small" data-copy="f-hook">Copiar</button></div>
            <textarea id="f-hook" name="hook" rows="2" maxlength="200"><?= radar_h($script['hook']) ?></textarea>
        </div>
        <div class="panel script-block">
            <div class="head"><span>C. Roteiro principal</span><button type="button" class="btn ghost small" data-copy="f-script">Copiar</button></div>
            <textarea id="f-script" name="script" rows="6" maxlength="2600"><?= radar_h($script['script']) ?></textarea>
        </div>
        <div class="panel script-block">
            <div class="head"><span>D. Comentário do personagem (opinião)</span><button type="button" class="btn ghost small" data-copy="f-commentary">Copiar</button></div>
            <textarea id="f-commentary" name="commentary" rows="3" maxlength="600"><?= radar_h($script['commentary']) ?></textarea>
            <p class="hint">Opinião fica separada da informação: não afirme culpa nem transforme suspeita em fato.</p>
        </div>
        <div class="panel script-block">
            <div class="head"><span>E. CTA</span><button type="button" class="btn ghost small" data-copy="f-cta">Copiar</button></div>
            <textarea id="f-cta" name="cta" rows="2" maxlength="250"><?= radar_h($script['cta']) ?></textarea>
        </div>
        <div class="panel">
            <div class="field-row">
                <label>Expressão do personagem<select name="expression"><?php foreach ($expressions as $key => $info): ?><option value="<?= radar_h($key) ?>"<?= $script['expression'] === $key ? ' selected' : '' ?>><?= radar_h($info['label']) ?></option><?php endforeach; ?></select></label>
                <label>Cenário<select name="scene"><?php foreach ($scenes as $key => $label): ?><option value="<?= radar_h($key) ?>"<?= $script['scene'] === $key ? ' selected' : '' ?>><?= radar_h($label) ?></option><?php endforeach; ?></select></label>
                <label>Personagem<select name="character"><?php foreach (radar_characters() as $slug => $preset): if (radar_character_is_archived($slug) && $script['character'] !== $slug) { continue; } ?><option value="<?= radar_h($slug) ?>"<?= $script['character'] === $slug ? ' selected' : '' ?>><?= radar_h(radar_character_get($slug)['name']) ?></option><?php endforeach; ?></select></label>
                <label>Cenas<select name="scenes"><?php for ($n = 1; $n <= RADAR_SCENE_MAX_COUNT; $n++): ?><option value="<?= $n ?>"<?= $script['scenes'] === $n ? ' selected' : '' ?>><?= $n ?> <?= $n === 1 ? 'cena' : 'cenas' ?> (até <?= $n * RADAR_SCENE_MAX_SECONDS ?> s)</option><?php endfor; ?></select></label>
                <label>Modelo do vídeo<select name="layout"><?php foreach (radar_video_templates() as $key => $template): ?><option value="<?= radar_h($key) ?>"<?= $script['layout'] === $key ? ' selected' : '' ?>><?= radar_h($template['label']) ?></option><?php endforeach; ?></select></label>
            </div>
            <h3>Visual desta notícia <span class="muted">(o rosto nunca muda)</span></h3>
            <label>Roupa<textarea name="outfit" rows="2" maxlength="240"><?= radar_h($script['direction']['outfit']) ?></textarea></label>
            <div class="field-row">
                <label>Acessórios<input name="accessories" maxlength="240" value="<?= radar_h($script['direction']['accessories']) ?>"></label>
                <label>Iluminação e clima<input name="lighting" maxlength="240" value="<?= radar_h($script['direction']['lighting']) ?>"></label>
            </div>
            <label class="check"><input type="checkbox" name="reroll_direction" value="1"> Sortear um visual novo ao salvar (roupa, acessórios, luz e ângulos)</label>
            <button class="btn primary" type="submit">Salvar alterações e refazer o prompt</button>
        </div>
    </form>

    <aside class="stack">
        <div class="panel script-block">
            <div class="head"><span>Texto falado completo · ~<?= (int)$script['estimated_seconds'] ?> s</span><button type="button" class="btn ghost small" data-copy="spoken">Copiar</button></div>
            <textarea id="spoken" rows="8" readonly><?= radar_h($script['spoken']) ?></textarea>
        </div>
        <div class="panel script-block">
            <div class="head"><span>F. Prompts de vídeo · <?= count($script['video_prompts']) ?> <?= count($script['video_prompts']) === 1 ? 'cena' : 'cenas' ?></span><button type="button" class="btn primary small" data-copy="video-prompt">Copiar todas</button></div>
            <?php if ($references !== []): ?>
                <p><strong>Anexe <?= count($references) === 1 ? 'esta imagem' : 'estas ' . count($references) . ' imagens' ?> em cada cena</strong> <span class="hint">A ferramenta de vídeo pega as imagens junto com a fala e mantém o mesmo personagem e a mesma reação.</span></p>
                <div class="reference-list">
                    <?php foreach ($references as $reference): ?>
                        <figure><img src="media.php?id=<?= radar_h($reference['id']) ?>" alt="<?= radar_h($reference['label'] ?: 'Imagem de referência') ?>" loading="lazy"><figcaption><?= radar_h($reference['role'] === 'photo' ? 'Foto' : 'Folha com vistas') ?><?= $reference['label'] !== '' ? ' · ' . radar_h($reference['label']) : '' ?><br><a class="btn ghost small" href="media.php?id=<?= radar_h($reference['id']) ?>&amp;download=1">Baixar</a></figcaption></figure>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="notice warn">Faltam as imagens de referência de <?= radar_h($script['character_name']) ?>. Cadastre em <a href="personagem.php?c=<?= radar_h($script['character']) ?>">Meus Personagens</a> para baixar e anexar aqui.</div>
            <?php endif; ?>
            <p class="hint">Visual: <strong><?= radar_h($script['direction']['outfit']) ?></strong><?= $script['direction']['accessories'] !== '' ? ' · ' . radar_h($script['direction']['accessories']) : '' ?></p>
            <?php foreach ($script['video_prompts'] as $index => $scenePrompt): ?>
                <div class="script-block">
                    <div class="head"><span>Cena <?= $index + 1 ?> de <?= count($script['video_prompts']) ?></span><button type="button" class="btn ghost small" data-copy="scene-prompt-<?= $index ?>">Copiar cena <?= $index + 1 ?></button></div>
                    <?php $angles = $script['direction']['angles']; if ($angles !== []): ?><p class="hint">Ângulo: <?= radar_h($angles[$index % count($angles)]) ?></p><?php endif; ?>
                    <textarea id="scene-prompt-<?= $index ?>" rows="10" readonly class="mono"><?= radar_h($scenePrompt) ?></textarea>
                </div>
            <?php endforeach; ?>
            <textarea id="video-prompt" hidden readonly><?= radar_h($script['video_prompt']) ?></textarea>
            <p class="hint">Cada cena é um prompt completo e tem até 25 s. O bloco do personagem é fixo. Padrões: <strong>mesmo personagem e mesma reação em todas as cenas</strong> e <strong>ele não sorri depois da última fala</strong>.</p>
        </div>
    </aside>
</div>
<?php radar_page_end();
