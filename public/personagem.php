<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$images = radar_character_images();
$expressions = radar_character_expressions();
$csrf = radar_csrf_token();

radar_page_start('Meu Personagem', 'personagem.php');
?>
<div class="page-head"><div><h1>Meu Personagem</h1><p>Identidade fixa usada em todos os roteiros e prompts de vídeo.</p></div></div>

<div class="grid-2">
    <section class="panel script-block" aria-labelledby="master-h">
        <div class="head"><h2 id="master-h">Prompt mestre (imutável)</h2><button type="button" class="btn ghost small" data-copy="master-pt">Copiar</button></div>
        <textarea id="master-pt" rows="9" readonly><?= radar_h(radar_character_master_prompt('pt')) ?></textarea>
        <p class="hint">Faz parte do código (versão <?= (int)RADAR_CHARACTER_VERSION ?>): não pode ser alterado pelo painel, para que a identidade seja sempre a mesma.</p>
    </section>
    <section class="panel script-block" aria-labelledby="master-en-h">
        <div class="head"><h2 id="master-en-h">Versão para ferramentas de vídeo (inglês)</h2><button type="button" class="btn ghost small" data-copy="master-en">Copiar</button></div>
        <textarea id="master-en" rows="9" readonly><?= radar_h(radar_character_master_prompt('en')) ?></textarea>
        <p class="hint">É este bloco que entra no prompt de vídeo gerado.</p>
    </section>
</div>

<section class="panel section" aria-labelledby="padroes-h">
    <h2 id="padroes-h">Padrões do vídeo (fixos)</h2>
    <p class="hint">Regras que entram automaticamente em <strong>todo</strong> prompt de vídeo, inclusive nos roteiros já criados.</p>
    <?php foreach (radar_video_standards() as $standard): ?>
        <div class="notice info"><strong><?= radar_h($standard['label']) ?></strong><br><?= radar_h($standard['description']) ?></div>
    <?php endforeach; ?>
</section>

<?php $reference = radar_character_reference(); ?>
<section class="panel section" aria-labelledby="sheet-h">
    <h2 id="sheet-h">Folha de referência (vai junto com o prompt de vídeo)</h2>
    <p class="hint">Uma imagem com as vistas do personagem (frente, perfis e 3/4). Todo prompt de vídeo manda anexá-la em cada cena, para a ferramenta manter o mesmo personagem e a mesma reação.</p>
    <?php if ($reference !== null): ?>
        <div class="reference-box"><img src="media.php?id=<?= radar_h($reference['id']) ?>" alt="Folha de referência do personagem"><div><strong><?= radar_h($reference['label'] ?: 'Folha de referência') ?></strong><br><span class="hint"><?= (int)$reference['width'] ?>×<?= (int)$reference['height'] ?></span><br>
            <a class="btn ghost small" href="media.php?id=<?= radar_h($reference['id']) ?>&amp;download=1">Baixar</a>
            <button type="button" class="btn danger small" data-act="media_delete" data-id="<?= radar_h($reference['id']) ?>" data-confirm="Excluir a folha de referência?">Excluir</button></div></div>
    <?php else: ?>
        <div class="notice warn">Nenhuma folha de referência. Envie abaixo a imagem com as vistas do personagem.</div>
    <?php endif; ?>
    <form class="form-grid section" data-upload enctype="multipart/form-data">
        <input type="hidden" name="kind" value="reference"><input type="hidden" name="label" value="Folha de referência do personagem">
        <label><?= $reference !== null ? 'Substituir a folha' : 'Enviar a folha' ?> (JPEG, PNG ou WebP, até 5 MB)<input type="file" name="file" accept="image/jpeg,image/png,image/webp" required></label>
        <button class="btn primary" type="submit"><?= $reference !== null ? 'Substituir' : 'Enviar' ?></button>
    </form>
</section>

<div class="grid-2 section">
    <section class="panel">
        <h2>Personalidade</h2>
        <ul><?php foreach (radar_character_personality() as $trait): ?><li><?= radar_h($trait) ?></li><?php endforeach; ?></ul>
        <p class="hint">Parece uma pessoa comum gravando no carro, em casa ou no escritório, não um apresentador de telejornal.</p>
    </section>
    <section class="panel">
        <h2>Expressões disponíveis no Estúdio</h2>
        <div class="pills"><?php foreach ($expressions as $info): ?><span class="pill"><?= radar_h($info['label']) ?></span><?php endforeach; ?></div>
        <h2 class="section">Cenários</h2>
        <div class="pills"><?php foreach (radar_character_scenes() as $label): ?><span class="pill"><?= radar_h($label) ?></span><?php endforeach; ?></div>
    </section>
</div>

<section class="section" aria-labelledby="ref-h">
    <div class="section-head"><h2 id="ref-h">Imagens de referência</h2><span class="muted"><?= count($images) ?> cadastrada(s)</span></div>
    <?php if ($images === []): ?>
        <div class="notice warn">Nenhuma imagem de referência. Envie fotos/ilustrações do personagem que você tem direito de usar: elas aparecem no Estúdio e devem acompanhar o prompt na ferramenta de vídeo.</div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($images as $image): ?>
                <div class="media-item<?= (int)$image['is_primary'] === 1 ? ' primary' : '' ?>">
                    <img src="media.php?id=<?= radar_h($image['id']) ?>" alt="<?= radar_h($image['label'] ?: 'Referência do personagem') ?>" loading="lazy">
                    <div class="info">
                        <span><?= radar_h($image['label'] ?: 'Referência') ?><?= (int)$image['is_primary'] === 1 ? ' · principal' : '' ?></span>
                        <span><?= (int)$image['width'] ?>×<?= (int)$image['height'] ?></span>
                        <?php if ((int)$image['is_primary'] !== 1): ?><button type="button" class="btn ghost small" data-act="character_primary" data-id="<?= radar_h($image['id']) ?>">Tornar principal</button><?php endif; ?>
                        <a class="btn ghost small" href="media.php?id=<?= radar_h($image['id']) ?>&amp;download=1">Baixar</a>
                        <button type="button" class="btn danger small" data-act="media_delete" data-id="<?= radar_h($image['id']) ?>" data-confirm="Excluir esta imagem de referência?">Excluir</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form class="panel form-grid section" data-upload enctype="multipart/form-data">
        <h2>Enviar nova referência</h2>
        <input type="hidden" name="kind" value="character">
        <div class="field-row">
            <label>Imagem (JPEG, PNG ou WebP, até 5 MB)<input type="file" name="file" accept="image/jpeg,image/png,image/webp" required></label>
            <label>Descrição<input name="label" maxlength="120" placeholder="Ex.: frente, sorrindo, camiseta preta"></label>
        </div>
        <button class="btn primary" type="submit">Enviar imagem</button>
        <p class="hint">Imagens ficam em área privada, fora da pasta pública, e só aparecem para você logado.</p>
    </form>
</section>
<?php radar_page_end();
