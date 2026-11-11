<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$slug = radar_character_slug(radar_str($_GET['c'] ?? $_POST['slug'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    radar_form_guard('personagem.php?c=' . $slug);
    try {
        $submitted = $_POST['categories'] ?? [];
        radar_character_save($slug, radar_str($_POST['name'] ?? ''), is_array($submitted) ? array_map('radar_str', $submitted) : []);
        radar_flash_set('ok', 'Personagem atualizado. As próximas notícias já usam as categorias novas.');
    } catch (InvalidArgumentException $error) {
        radar_flash_set('error', $error->getMessage());
    }
    radar_redirect('personagem.php?c=' . $slug);
}

$character = radar_character_get($slug);
$images = radar_character_images($slug);
$references = radar_character_references($slug);
$expressions = radar_character_expressions();
$csrf = radar_csrf_token();

radar_page_start('Meus Personagens', 'personagem.php');
?>
<div class="page-head"><div><h1>Meus Personagens</h1><p>Cada personagem tem identidade, voz e visual próprios. A notícia sugere quem fala pela categoria.</p></div></div>

<div class="chips" role="group" aria-label="Personagens">
    <?php foreach (array_keys(radar_characters()) as $other): ?>
        <a class="chip<?= $other === $slug ? ' on' : '' ?>" href="personagem.php?c=<?= radar_h($other) ?>"><?= radar_h(radar_character_get($other)['name']) ?></a>
    <?php endforeach; ?>
</div>

<div class="grid-2 section">
    <section class="panel" aria-labelledby="id-h">
        <h2 id="id-h"><?= radar_h($character['name']) ?></h2>
        <p><strong><?= radar_h($character['tagline']) ?></strong></p>
        <p><?= radar_h($character['bio']) ?></p>
        <h3>Personalidade</h3>
        <ul><?php foreach (radar_character_personality($slug) as $trait): ?><li><?= radar_h($trait) ?></li><?php endforeach; ?></ul>
        <h3>Jeito de falar</h3>
        <p><?= radar_h($character['voice']) ?></p>
        <p class="hint">Bordões: <?= radar_h(implode(' · ', $character['catchphrases'])) ?></p>
        <h3>Assuntos do perfil</h3>
        <div class="pills"><?php foreach ($character['pillars'] as $pillar): ?><span class="pill"><?= radar_h($pillar) ?></span><?php endforeach; ?></div>
    </section>
    <section class="panel" aria-labelledby="cat-h">
        <h2 id="cat-h">Nome e categorias</h2>
        <form method="post" action="personagem.php" class="form-grid">
            <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="slug" value="<?= radar_h($slug) ?>">
            <label>Nome<input name="name" maxlength="40" value="<?= radar_h($character['name']) ?>" required></label>
            <fieldset class="check-grid">
                <legend>Categorias que <?= radar_h($character['name']) ?> cobre</legend>
                <?php foreach (radar_category_list() as $category): ?>
                    <label class="check"><input type="checkbox" name="categories[]" value="<?= radar_h($category) ?>"<?= in_array($category, $character['categories'], true) ? ' checked' : '' ?>> <?= radar_h($category) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <p class="hint">Sem nenhuma marcada, o personagem pega tudo o que os outros não cobrem. Cada notícia sugere quem fala pela sua categoria, e você pode trocar na hora de gerar o react.</p>
            <button class="btn primary" type="submit">Salvar</button>
        </form>
    </section>
</div>

<div class="grid-2">
    <section class="panel script-block" aria-labelledby="master-h">
        <div class="head"><h2 id="master-h">Prompt mestre (imutável)</h2><button type="button" class="btn ghost small" data-copy="master-pt">Copiar</button></div>
        <textarea id="master-pt" rows="9" readonly><?= radar_h(radar_character_master_prompt('pt', $slug)) ?></textarea>
        <p class="hint">Faz parte do código (versão <?= (int)RADAR_CHARACTER_VERSION ?>): não pode ser alterado pelo painel, para que a identidade seja sempre a mesma.</p>
    </section>
    <section class="panel script-block" aria-labelledby="master-en-h">
        <div class="head"><h2 id="master-en-h">Versão para ferramentas de vídeo (inglês)</h2><button type="button" class="btn ghost small" data-copy="master-en">Copiar</button></div>
        <textarea id="master-en" rows="9" readonly><?= radar_h(radar_character_master_prompt('en', $slug)) ?></textarea>
        <p class="hint">É este bloco que entra no prompt de vídeo gerado.</p>
    </section>
</div>

<section class="panel section" aria-labelledby="padroes-h">
    <h2 id="padroes-h">Padrões do vídeo (fixos)</h2>
    <p class="hint">Regras que entram automaticamente em <strong>todo</strong> prompt de vídeo, inclusive nos roteiros já criados.</p>
    <?php foreach (radar_video_standards($slug) as $standard): ?>
        <div class="notice info"><strong><?= radar_h($standard['label']) ?></strong><br><?= radar_h($standard['description']) ?></div>
    <?php endforeach; ?>
</section>

<section class="panel section" aria-labelledby="sheet-h">
    <h2 id="sheet-h">Imagens de referência de <?= radar_h($character['name']) ?> (vão junto com o prompt de vídeo)</h2>
    <p class="hint">Folha com as vistas (frente, perfis e 3/4) e fotos. <strong>Todas</strong> entram na lista de imagens de cada prompt de vídeo: a ferramenta precisa receber todas em cada cena, só para manter o rosto e a mesma reação. Até <?= (int)RADAR_REFERENCES_MAX ?> imagens.</p>
    <?php if ($references !== []): ?>
        <div class="reference-list">
            <?php foreach ($references as $reference): ?>
                <figure>
                    <img src="media.php?id=<?= radar_h($reference['id']) ?>" alt="<?= radar_h($reference['label'] ?: 'Imagem de referência') ?>" loading="lazy">
                    <figcaption><?= radar_h($reference['role'] === 'photo' ? 'Foto' : 'Folha com vistas') ?><?= $reference['label'] !== '' ? ' · ' . radar_h($reference['label']) : '' ?> · <?= (int)$reference['width'] ?>×<?= (int)$reference['height'] ?><br>
                        <a class="btn ghost small" href="media.php?id=<?= radar_h($reference['id']) ?>&amp;download=1">Baixar</a>
                        <button type="button" class="btn danger small" data-act="media_delete" data-id="<?= radar_h($reference['id']) ?>" data-confirm="Excluir esta imagem de referência?">Excluir</button></figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="notice warn">Nenhuma imagem de referência de <?= radar_h($character['name']) ?>. Envie abaixo a folha com as vistas e/ou uma foto.</div>
    <?php endif; ?>
    <?php if (count($references) < RADAR_REFERENCES_MAX): ?>
        <form class="form-grid section" data-upload enctype="multipart/form-data">
            <input type="hidden" name="kind" value="reference"><input type="hidden" name="character" value="<?= radar_h($slug) ?>">
            <div class="field-row">
                <label>Imagem (JPEG, PNG ou WebP, até 5 MB)<input type="file" name="file" accept="image/jpeg,image/png,image/webp" required></label>
                <label>Tipo<select name="role"><option value="sheet">Folha com várias vistas</option><option value="photo">Foto do personagem</option></select></label>
                <label>Descrição <span class="optional">opcional</span><input name="label" maxlength="80" placeholder="Ex.: selfie ao ar livre"></label>
            </div>
            <button class="btn primary" type="submit">Adicionar imagem de referência</button>
        </form>
    <?php endif; ?>
</section>

<div class="grid-2 section">
    <section class="panel">
        <h2>Expressões disponíveis no Estúdio</h2>
        <div class="pills"><?php foreach ($expressions as $info): ?><span class="pill"><?= radar_h($info['label']) ?></span><?php endforeach; ?></div>
        <h2 class="section">Cenários</h2>
        <div class="pills"><?php foreach (radar_character_scenes() as $label): ?><span class="pill"><?= radar_h($label) ?></span><?php endforeach; ?></div>
    </section>
    <section class="panel">
        <h2>Visual de partida</h2>
        <p class="hint">O rosto nunca muda, mas a roupa e os acessórios são criados a cada notícia. Sem um visual escolhido, o prompt parte de:</p>
        <p><?= radar_h(ucfirst($character['default_outfit'])) ?>; <?= radar_h($character['default_accessories']) ?>.</p>
    </section>
</div>

<section class="section" aria-labelledby="ref-h">
    <div class="section-head"><h2 id="ref-h">Imagens de <?= radar_h($character['name']) ?> para o Estúdio</h2><span class="muted"><?= count($images) ?> cadastrada(s)</span></div>
    <?php if ($images === []): ?>
        <div class="notice warn">Nenhuma imagem de <?= radar_h($character['name']) ?>. Envie fotos/ilustrações do personagem que você tem direito de usar: elas aparecem no Estúdio.</div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($images as $image): ?>
                <div class="media-item<?= (int)$image['is_primary'] === 1 ? ' primary' : '' ?>">
                    <img src="media.php?id=<?= radar_h($image['id']) ?>" alt="<?= radar_h($image['label'] ?: 'Imagem do personagem') ?>" loading="lazy">
                    <div class="info">
                        <span><?= radar_h($image['label'] ?: 'Imagem') ?><?= (int)$image['is_primary'] === 1 ? ' · principal' : '' ?></span>
                        <span><?= (int)$image['width'] ?>×<?= (int)$image['height'] ?></span>
                        <?php if ((int)$image['is_primary'] !== 1): ?><button type="button" class="btn ghost small" data-act="character_primary" data-id="<?= radar_h($image['id']) ?>">Tornar principal</button><?php endif; ?>
                        <a class="btn ghost small" href="media.php?id=<?= radar_h($image['id']) ?>&amp;download=1">Baixar</a>
                        <button type="button" class="btn danger small" data-act="media_delete" data-id="<?= radar_h($image['id']) ?>" data-confirm="Excluir esta imagem?">Excluir</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form class="panel form-grid section" data-upload enctype="multipart/form-data">
        <h2>Enviar nova imagem</h2>
        <input type="hidden" name="kind" value="character"><input type="hidden" name="character" value="<?= radar_h($slug) ?>">
        <div class="field-row">
            <label>Imagem (JPEG, PNG ou WebP, até 5 MB)<input type="file" name="file" accept="image/jpeg,image/png,image/webp" required></label>
            <label>Descrição<input name="label" maxlength="120" placeholder="Ex.: frente, sorrindo"></label>
        </div>
        <button class="btn primary" type="submit">Enviar imagem</button>
        <p class="hint">Imagens ficam em área privada, fora da pasta pública, e só aparecem para você logado.</p>
    </form>
</section>
<?php radar_page_end();
