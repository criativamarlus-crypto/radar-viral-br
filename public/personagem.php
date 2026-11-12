<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
radar_require_login();

$slug = radar_character_slug(radar_str($_GET['c'] ?? $_POST['slug'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    radar_form_guard('personagem.php?c=' . $slug);
    try {
        $form = radar_str($_POST['form'] ?? 'basic');
        if ($form === 'profile') {
            $posted = $_POST['profile'] ?? [];
            $version = radar_character_profile_save($slug, is_array($posted) ? array_map(static fn(mixed $v): mixed => is_array($v) ? $v : (string)$v, $posted) : []);
            radar_flash_set('ok', 'Cadastro salvo. Versão atual da configuração: ' . $version . '. Os próximos roteiros e prompts já usam o cadastro novo.');
        } elseif ($form === 'default') {
            radar_character_default_set($slug);
            radar_flash_set('ok', radar_character_get($slug)['name'] . ' agora é a personagem padrão: vem selecionada ao gerar um react.');
        } else {
            $submitted = $_POST['categories'] ?? [];
            radar_character_save($slug, radar_str($_POST['name'] ?? ''), is_array($submitted) ? array_map('radar_str', $submitted) : []);
            radar_flash_set('ok', 'Personagem atualizado. As próximas notícias já usam as categorias novas.');
        }
    } catch (InvalidArgumentException $error) {
        radar_flash_set('error', $error->getMessage());
    }
    radar_redirect('personagem.php?c=' . $slug);
}

$character = radar_character_get($slug);
$images = radar_character_images($slug);
$references = radar_character_references($slug);
$isDefault = radar_character_default_slug() === $slug;
$gallerySlots = array_intersect_key(radar_reference_slots(), array_flip(['master', 'frontal', 'profile_right', 'profile_left', 'fullbody', 'seated', 'surprise']));
$bySlot = [];
$others = [];
foreach ($references as $reference) {
    if (isset($gallerySlots[$reference['role']])) {
        $bySlot[$reference['role']] = $reference;
    } else {
        $others[] = $reference;
    }
}
$voiceAsset = radar_character_voice_asset($slug);
$profile = radar_character_get($slug);
$expressions = radar_character_expressions();
$csrf = radar_csrf_token();

radar_page_start('Meus Personagens', 'personagem.php');
?>
<div class="page-head"><div><h1>Meus Personagens</h1><p>Cada personagem tem identidade, voz e visual próprios. A notícia sugere quem fala pela categoria.</p></div></div>

<div class="chips" role="group" aria-label="Personagens">
    <?php foreach (array_keys(radar_characters_visible()) as $other): ?>
        <a class="chip<?= $other === $slug ? ' on' : '' ?>" href="personagem.php?c=<?= radar_h($other) ?>"><?= radar_h(radar_character_get($other)['name']) ?><?= radar_character_default_slug() === $other ? ' ★' : '' ?></a>
    <?php endforeach; ?>
</div>
<?php $archivedList = radar_character_archived(); if ($archivedList !== []): ?>
    <p class="hint">Arquivados (fora do painel, o que fizeram continua guardado): <?php foreach ($archivedList as $archivedSlug): ?><a href="personagem.php?c=<?= radar_h($archivedSlug) ?>"><?= radar_h(radar_character_get($archivedSlug)['name']) ?></a> <?php endforeach; ?></p>
<?php endif; ?>

<div class="grid-2 section">
    <section class="panel" aria-labelledby="id-h">
        <h2 id="id-h"><?= radar_h($character['name']) ?></h2>
        <p class="hint">Identificador permanente: <code><?= radar_h($slug) ?></code> · versão da configuração: <strong><?= (int)$character['version'] ?></strong> · <?= radar_character_format($slug) === 'tv3' ? 'react em 3 cenas, ' . (int)$character['narration']['scene_seconds'] . ' s cada' : 'react em cenas de até 25 s' ?></p>
        <?php if ($isDefault): ?><p><span class="pill">★ Personagem padrão</span></p>
        <?php elseif (!radar_character_is_archived($slug)): ?><form method="post" action="personagem.php"><input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="slug" value="<?= radar_h($slug) ?>"><input type="hidden" name="form" value="default"><button class="btn ghost small" type="submit">Tornar a personagem padrão</button></form><?php endif; ?>
        <?php if (radar_character_is_archived($slug)): ?>
            <div class="notice warn"><strong>Arquivado:</strong> não aparece mais para escolher nem é sugerido. Os roteiros e imagens que ele já tem continuam aqui. <button type="button" class="btn small" data-act="character_restore" data-character="<?= radar_h($slug) ?>">Restaurar no painel</button></div>
        <?php elseif (count(radar_characters_visible()) > 1): ?>
            <p><button type="button" class="btn danger small" data-act="character_archive" data-character="<?= radar_h($slug) ?>" data-confirm="Tirar <?= radar_h($character['name']) ?> do painel? Ele deixa de aparecer para escolher e de ser sugerido. Os roteiros e imagens dele continuam guardados e dá para restaurar.">Arquivar (tirar do painel)</button></p>
        <?php endif; ?>
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

<details class="panel section"<?= radar_character_format($slug) === 'tv3' ? ' open' : '' ?>>
    <summary><strong>Cadastro completo</strong> <span class="muted">(descrição, personalidade, voz, bordões, cenário, regras negativas e narração)</span></summary>
    <form method="post" action="personagem.php" class="form-grid">
        <input type="hidden" name="csrf" value="<?= radar_h($csrf) ?>"><input type="hidden" name="slug" value="<?= radar_h($slug) ?>"><input type="hidden" name="form" value="profile">
        <label>Descrição física (português)<textarea name="profile[identity_pt]" rows="6" maxlength="2500"><?= radar_h($profile['identity_pt']) ?></textarea></label>
        <label>Descrição física para a ferramenta de vídeo (inglês)<textarea name="profile[identity_en]" rows="6" maxlength="2500"><?= radar_h($profile['identity_en']) ?></textarea></label>
        <label>Personalidade (uma característica por linha)<textarea name="profile[personality]" rows="5" maxlength="2000"><?= radar_h(implode("\n", $profile['personality'])) ?></textarea></label>
        <label>Voz (português)<textarea name="profile[voice]" rows="3" maxlength="900"><?= radar_h($profile['voice']) ?></textarea></label>
        <?php if (isset($profile['voice_en'])): ?><label>Voz para a ferramenta de vídeo (inglês)<textarea name="profile[voice_en]" rows="3" maxlength="900"><?= radar_h($profile['voice_en']) ?></textarea></label><?php endif; ?>
        <label>Bordões (um por linha)<textarea name="profile[catchphrases]" rows="5" maxlength="1300"><?= radar_h(implode("\n", $profile['catchphrases'])) ?></textarea></label>
        <?php if (isset($profile['scenery'])): ?>
            <label>Preferências de cenário (português)<textarea name="profile[scenery]" rows="3" maxlength="900"><?= radar_h($profile['scenery']) ?></textarea></label>
            <label>Cenário para a ferramenta de vídeo (inglês)<textarea name="profile[scenery_en]" rows="3" maxlength="900"><?= radar_h($profile['scenery_en']) ?></textarea></label>
            <label>Regras negativas de geração (uma por linha, em inglês)<textarea name="profile[negative_rules]" rows="6" maxlength="4000"><?= radar_h(implode("\n", $profile['negative_rules'])) ?></textarea></label>
            <label>Duração de cada cena (segundos, de 4 a 15)<input type="number" name="profile[scene_seconds]" min="4" max="15" value="<?= (int)$profile['narration']['scene_seconds'] ?>"></label>
        <?php endif; ?>
        <button class="btn primary" type="submit">Salvar cadastro</button>
        <p class="hint">Cada alteração real sobe a versão da configuração e fica registrada nos roteiros gerados depois. Campo vazio volta ao padrão. A imagem-mestre vale mais do que qualquer descrição em texto.</p>
    </form>
</details>

<div class="grid-2">
    <section class="panel script-block" aria-labelledby="master-h">
        <div class="head"><h2 id="master-h">Descrição que vai para o prompt</h2><button type="button" class="btn ghost small" data-copy="master-pt">Copiar</button></div>
        <textarea id="master-pt" rows="9" readonly><?= radar_h(radar_character_master_prompt('pt', $slug)) ?></textarea>
        <p class="hint">Versão <?= (int)$character['version'] ?> da configuração. Para mudar, use o cadastro completo acima.</p>
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

<?php if (!empty($character['gallery'])): ?>
<section class="panel section" aria-labelledby="gal-h">
    <h2 id="gal-h">Galeria de referências de <?= radar_h($character['name']) ?></h2>
    <p class="hint">A <strong>imagem-mestre</strong> é obrigatória e vale mais do que qualquer descrição: o gerador não pode reinventar o rosto, a idade, o cabelo, os óculos, a roupa nem as joias. As outras imagens confirmam a mesma identidade por outros ângulos. Enviar de novo em um espaço <strong>substitui</strong> a imagem. As imagens vão junto de todos os prompts, nas três cenas.</p>
    <?php if (!isset($bySlot['master'])): ?><div class="notice warn">Falta a imagem-mestre. Envie abaixo a imagem oficial.</div><?php endif; ?>
    <div class="reference-list">
        <?php foreach ($gallerySlots as $role => $slot): $reference = $bySlot[$role] ?? null; ?>
            <figure>
                <?php if ($reference !== null): ?><img src="media.php?id=<?= radar_h($reference['id']) ?>" alt="<?= radar_h($slot['label']) ?>" loading="lazy"><?php else: ?><div class="notice warn">Vazio</div><?php endif; ?>
                <figcaption><strong><?= radar_h($slot['label']) ?></strong><?= $reference !== null ? ' · ' . (int)$reference['width'] . '×' . (int)$reference['height'] : '' ?><br>
                    <form class="form-grid" data-upload enctype="multipart/form-data">
                        <input type="hidden" name="kind" value="reference"><input type="hidden" name="character" value="<?= radar_h($slug) ?>"><input type="hidden" name="role" value="<?= radar_h($role) ?>"><input type="hidden" name="label" value="<?= radar_h($slot['label']) ?>">
                        <input type="file" name="file" accept="image/jpeg,image/png,image/webp" aria-label="<?= radar_h($slot['label']) ?>">
                        <button class="btn small" type="submit"><?= $reference !== null ? 'Substituir' : 'Enviar' ?></button>
                    </form>
                    <?php if ($reference !== null): ?>
                        <a class="btn ghost small" href="media.php?id=<?= radar_h($reference['id']) ?>&amp;download=1">Baixar</a>
                        <button type="button" class="btn danger small" data-act="media_delete" data-id="<?= radar_h($reference['id']) ?>" data-confirm="Excluir esta imagem?">Excluir</button>
                    <?php endif; ?></figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
    <h3>Outras poses e expressões</h3>
    <?php if ($others !== []): ?>
        <div class="reference-list">
            <?php foreach ($others as $reference): ?>
                <figure><img src="media.php?id=<?= radar_h($reference['id']) ?>" alt="<?= radar_h($reference['label'] ?: 'Outra pose') ?>" loading="lazy"><figcaption><?= radar_h((radar_reference_slots()[$reference['role']]['label'] ?? 'Referência') . ($reference['label'] !== '' ? ' · ' . $reference['label'] : '')) ?><br>
                    <a class="btn ghost small" href="media.php?id=<?= radar_h($reference['id']) ?>&amp;download=1">Baixar</a>
                    <button type="button" class="btn danger small" data-act="media_delete" data-id="<?= radar_h($reference['id']) ?>" data-confirm="Excluir esta imagem?">Excluir</button></figcaption></figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (count($references) < RADAR_REFERENCES_MAX): ?>
        <form class="form-grid" data-upload enctype="multipart/form-data">
            <input type="hidden" name="kind" value="reference"><input type="hidden" name="character" value="<?= radar_h($slug) ?>"><input type="hidden" name="role" value="extra">
            <div class="field-row">
                <label>Imagem (JPEG, PNG ou WebP, até 5 MB)<input type="file" name="file" accept="image/jpeg,image/png,image/webp" required></label>
                <label>Descrição <span class="optional">opcional</span><input name="label" maxlength="80" placeholder="Ex.: apontando para a câmera"></label>
            </div>
            <button class="btn primary" type="submit">Adicionar outra pose</button>
        </form>
    <?php endif; ?>
    <h3>Áudio de referência da voz <span class="optional">opcional</span></h3>
    <p class="hint">Um áudio que você tem autorização para usar. Serve para ajudar a manter a voz quando o serviço de áudio ou vídeo aceitar uma voz de referência; se não aceitar, só a descrição da voz vale. MP3, WAV, OGG ou M4A, até 8 MB.</p>
    <?php if ($voiceAsset !== null): ?>
        <p><audio controls preload="none" src="media.php?id=<?= radar_h($voiceAsset['id']) ?>"></audio><br>
            <a class="btn ghost small" href="media.php?id=<?= radar_h($voiceAsset['id']) ?>&amp;download=1">Baixar</a>
            <button type="button" class="btn danger small" data-act="media_delete" data-id="<?= radar_h($voiceAsset['id']) ?>" data-confirm="Excluir o áudio de referência?">Excluir</button></p>
    <?php endif; ?>
    <form class="form-grid" data-upload data-max="8388608" data-max-label="O áudio deve ter até 8 MB." enctype="multipart/form-data">
        <input type="hidden" name="kind" value="voice"><input type="hidden" name="character" value="<?= radar_h($slug) ?>">
        <label>Áudio<input type="file" name="file" accept="audio/mpeg,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/x-m4a,.mp3,.wav,.ogg,.m4a" required></label>
        <button class="btn primary" type="submit"><?= $voiceAsset !== null ? 'Substituir o áudio' : 'Enviar o áudio' ?></button>
    </form>
</section>
<?php else: ?>
<section class="panel section" aria-labelledby="sheet-h">
    <h2 id="sheet-h">Imagens de referência de <?= radar_h($character['name']) ?> (vão junto com o prompt de vídeo)</h2>
    <p class="hint">Folha com as vistas (frente, perfis e 3/4) e fotos. <strong>Todas</strong> entram na lista de imagens de cada prompt de vídeo: a ferramenta precisa receber todas em cada cena, só para manter o rosto e a mesma reação. Até <?= (int)RADAR_REFERENCES_MAX ?> imagens.</p>
    <?php if ($references !== []): ?>
        <div class="reference-list">
            <?php foreach ($references as $reference): ?>
                <figure>
                    <img src="media.php?id=<?= radar_h($reference['id']) ?>" alt="<?= radar_h($reference['label'] ?: 'Imagem de referência') ?>" loading="lazy">
                    <figcaption><?= radar_h($reference['role'] === 'photo' ? 'Foto' : (radar_reference_slots()[$reference['role']]['label'] ?? 'Folha com vistas')) ?><?= $reference['label'] !== '' ? ' · ' . radar_h($reference['label']) : '' ?> · <?= (int)$reference['width'] ?>×<?= (int)$reference['height'] ?><br>
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
<?php endif; ?>

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
