/* Radar Viral BR — Estúdio: cada cena como UM bloco completo (imagens a anexar, coordenadas, roteiro e prompt) e
   "Copiar todos os textos" (só texto, para colar junto com o print do template escolhido), ou o pacote em .zip.
   Roda depois do studio.js e lê o estado dele. */
(() => {
  'use strict';
  const S = window.RadarStudio;
  if (!S) return;
  const { state, data } = S;
  const $ = (id) => document.getElementById(id);
  const toast = window.RadarToast || (() => {});
  const SEPARATOR = '='.repeat(60);

  const characterName = () => (data.characterList.find((c) => c.slug === state.character) || { name: '' }).name;
  const px = (n) => Math.round(n);
  const pct = (n, total) => (n / total * 100).toFixed(1).replace('.', ',') + '%';
  /** A rectangle in the 1080 × 1920 frame, whatever size it was last drawn at (the preview is smaller). */
  const box = (r, L) => {
    const k = 1080 / L.W;
    return 'x ' + px(r.x * k) + ' px (' + pct(r.x, L.W) + '), y ' + px(r.y * k) + ' px (' + pct(r.y, L.H) + '), largura ' + px(r.w * k) + ' px (' + pct(r.w, L.W) + '), altura ' + px(r.h * k) + ' px (' + pct(r.h, L.H) + ')';
  };

  const mediaExt = (id) => { const m = /\.([a-z0-9]+)$/i.exec(id || ''); return m ? m[1].toLowerCase() : 'img'; };
  const read = (blob) => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(blob);
  });
  const escapeHtml = (text) => String(text).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  async function mediaBlob(id) {
    if (!id) return null;
    try {
      const response = await fetch('media.php?id=' + encodeURIComponent(id), { credentials: 'same-origin' });
      return response.ok ? await response.blob() : null;
    } catch (_) { return null; }
  }

  function canvasBlob(canvas) {
    return new Promise((resolve) => canvas.toBlob((blob) => resolve(blob), 'image/png'));
  }

  async function composition(overlay) {
    const canvas = document.createElement('canvas');
    canvas.width = 1080; canvas.height = 1920;
    await S.render(canvas, 1080, 1920, { overlay });
    return canvasBlob(canvas);
  }

  /* ---------- what goes with the prompts ---------- */

  /** Every image of this react with its file name, in the order they are numbered. No downloads: names come from the state. */
  function plannedFiles() {
    const plan = [];
    let count = 0;
    const next = () => String(++count).padStart(2, '0');
    // The template comes first: it is the frame the video AI must deliver (it only swaps the person in the background).
    const tvStudio = state.template === 'tv_studio';
    plan.push({ kind: 'template', name: next() + '-template-1080x1920.png', label: 'IMAGEM DO TEMPLATE (a principal)', note: tvStudio ? 'primeiro quadro (start frame) da cena: o estúdio, o letreiro e o enquadramento como estão; só a personagem se move e fala' : 'primeiro quadro (start frame) da cena: a IA mantém a faixa, o título e o card exatamente assim e troca só a personagem do fundo' });
    // Every reference image of the character goes with the prompts (the video tool needs all of them in every scene).
    for (const reference of data.references[state.character] || []) {
      const photo = reference.role === 'photo';
      const slot = (data.slots || {})[reference.role];
      const slug = reference.role === 'photo' ? 'foto' : (reference.role === 'sheet' ? 'folha-de-vistas' : reference.role.replace(/_/g, '-'));
      plan.push({ kind: 'reference', role: reference.role, mediaId: reference.id, name: next() + '-referencia-' + slug + '.' + mediaExt(reference.id),
        label: (slot && reference.role !== 'photo' && reference.role !== 'sheet' ? slot : (photo ? 'Foto de referência' : 'Folha de referência')) + ' de ' + characterName() + (reference.label && !slot ? ' (' + reference.label + ')' : ''),
        note: reference.role === 'master' ? 'a imagem oficial: vale mais do que qualquer descrição; anexe esta primeiro' : 'só serve para manter o rosto' });
    }
    if (!tvStudio) plan.push({ kind: 'overlay', name: next() + '-camada-' + (state.template === 'caption_card' ? 'legenda-e-card' : 'noticia') + '-transparente.png', label: 'Camada transparente', note: 'vai por cima do vídeo gerado, na edição' });
    if (state.coverId) plan.push({ kind: 'news', mediaId: state.coverId, name: next() + '-imagem-da-noticia.' + mediaExt(state.coverId), label: 'Imagem da notícia (miniatura do artigo)', note: tvStudio ? 'opcional: vai no telão do estúdio, sem texto' : 'vai dentro do card, com o crédito da fonte' });
    if (state.characterId) plan.push({ kind: 'other', mediaId: state.characterId, name: next() + '-personagem-no-estudio.' + mediaExt(state.characterId), label: 'Imagem do personagem usada no Estúdio', note: '' });
    return plan;
  }

  async function collectFiles() {
    const files = [];
    for (const item of plannedFiles()) {
      const blob = item.kind === 'template' ? await composition(false) : item.kind === 'overlay' ? await composition(true) : await mediaBlob(item.mediaId);
      if (blob) files.push({ ...item, blob });
    }
    return files;
  }

  const scenePrompts = () => (state.prompts.length ? state.prompts : (state.prompt ? [state.prompt] : []));

  /** What this scene says out loud (read back from its own prompt, which is where the server cut the text). */
  function dialogueOf(prompt) {
    const match = /lip-sync, exactly as written\):\n"([\s\S]*?)"\n\n/.exec(prompt || '');
    return match ? match[1] : '';
  }

  function coordinateLines() {
    const L = S.layout();
    const lines = [];
    if (state.template === 'caption_card') {
      lines.push('Frase de impacto (faixa preta do topo, MAIÚSCULAS): ' + ((state.caption || '').toUpperCase() || '—'),
        'Manchete no card (a real, em MAIÚSCULAS): ' + ((state.headline || '').toUpperCase() || '—'),
        'Linha amarela do card: ' + S.sourceLine());
      if (L && L.template === 'caption_card') {
        lines.push('Faixa preta do topo: ' + box(L.band, L), 'Card da notícia: ' + box(L.card, L),
          '  Imagem da notícia dentro do card: ' + box(L.image, L), '  Barra preta com FONTE • DATA e manchete: ' + box(L.bar, L),
          'Área livre para o rosto e os gestos: ' + box(L.safe, L));
      }
    } else if (state.template === 'tv_studio') {
      lines.push('Modelo: estúdio de TV, sem texto dentro do vídeo. Título e legendas entram na edição.', 'Imagem da notícia (opcional): vai no telão do estúdio, sem texto.');
    } else {
      lines.push('Headline sobre a imagem: ' + (state.headline || '—'));
      if (L && L.template === 'split') lines.push('Metade de cima (notícia): ' + box(L.news, L), 'Metade de baixo (personagem): ' + box(L.character, L));
    }
    lines.push('Enquadramento da imagem da notícia: horizontal ' + Math.round(state.x) + '%, vertical ' + Math.round(state.y) + '%, zoom ' + Number(state.zoom).toFixed(2).replace('.', ','));
    const cover = data.media.find((m) => m.id === state.coverId);
    if (cover) lines.push('Imagem da notícia: ' + cover.label + (cover.credit ? ' (crédito: ' + cover.credit + ')' : ''));
    return lines;
  }

  /** ONE self-contained block for a scene: the images to attach, the coordinates, the whole script and the prompt. */
  function sceneText(index) {
    const prompts = scenePrompts();
    const total = prompts.length;
    const lines = ['########## CENA ' + (index + 1) + ' DE ' + total + ' · tudo desta cena junto ##########', ''];
    lines.push('1) IMAGENS PARA ANEXAR NESTA CENA (o print do template escolhido e as fotos de referência):');
    plannedFiles().filter((f) => ['template', 'reference', 'news'].includes(f.kind))
      .forEach((f, i) => lines.push('   ' + (i + 1) + '. ' + f.label + (f.note ? ' — ' + f.note : '') + ' [' + f.name + ']'));
    const look = state.direction;
    if (look && look.outfit) {
      lines.push('', state.template === 'tv_studio' ? '2) ROUPA, ESTÚDIO E CÂMERA DESTA CENA (roupa e joias são fixas: vêm da imagem-mestre; só a luz, a câmera e os gestos mudam)' : '2) ROUPA, CENÁRIO E ÂNGULO DESTA CENA (mudam a cada notícia: use estes, não os do vídeo anterior)',
        'Roupa: ' + look.outfit, 'Acessórios: ' + (look.accessories || 'nenhum extra'), 'Cenário: ' + (data.scenes[state.scene] || state.scene), 'Iluminação: ' + (look.lighting || '—'),
        'Ângulo desta cena: ' + (look.angles && look.angles.length ? look.angles[index % look.angles.length] : '—') + (look.gestures && look.gestures.length ? ' · gesto: ' + look.gestures[index % look.gestures.length] : ''),
        'O rosto da personagem nunca muda (vem das imagens de referência).');
    }
    lines.push('', '3) COORDENADAS DA COMPOSIÇÃO (tela de 1080 × 1920 px; origem no canto superior esquerdo)', ...coordinateLines());
    lines.push('', '4) ROTEIRO COMPLETO (a fala desta cena está marcada com >>)');
    prompts.forEach((prompt, j) => lines.push((j === index ? '>> ' : '   ') + 'Cena ' + (j + 1) + ': ' + (dialogueOf(prompt) || state.spoken)));
    lines.push('', '5) PROMPT PARA A IA DE VÍDEO (cole este texto)', '', prompts[index] || '');
    return lines.join('\n');
  }

  /** The text of the whole react: the story and the look once, then every scene as one complete block. */
  function buildText() {
    const script = data.scripts.find((s) => s.id === state.scriptId);
    const lines = [];
    const add = (...parts) => lines.push(...parts);
    add('RADAR VIRAL BR · PACOTE COMPLETO DO REACT', SEPARATOR, '',
      state.template === 'tv_studio'
        ? 'COMO USAR: cada cena abaixo é um bloco completo (imagens, roupa e estúdio, roteiro e prompt). O template é o primeiro quadro de cada cena: a IA mantém o estúdio, o letreiro e o enquadramento e só a personagem se move e fala. A imagem-mestre é a referência oficial: rosto, roupa e joias da personagem não mudam. Não há texto dentro do vídeo: título e legendas entram na edição.' + ' ' + 'Cole este texto junto com o print do template escolhido.'
        : 'COMO USAR: cada cena abaixo é um bloco completo (imagens, roupa e cenário, coordenadas, roteiro e prompt). A roupa e o cenário mudam a cada notícia: valem os desta notícia, não os do vídeo anterior. O template é o primeiro quadro de cada cena: a IA mantém a faixa, o título e o card e troca só a personagem do fundo. Cole este texto junto com o print do template escolhido.', '');
    add('Personagem: ' + characterName(), 'Título: ' + (state.title || '—'), 'Modelo do vídeo: ' + data.templates[state.template].label);
    if (data.news) {
      add('', 'NOTÍCIA', 'Manchete real: ' + data.news.title, 'Fonte: ' + data.news.source, 'Categoria: ' + (data.news.category || '—'), 'Link: ' + data.news.url);
      if (data.news.published_at) add('Publicada em: ' + data.news.published_at);
    }
    if (script && script.headlines && script.headlines.length) add('', 'Headlines sugeridas:', ...script.headlines.map((h) => '- ' + h));
    const d = state.direction;
    if (d && d.outfit) {
      add('', 'VISUAL DESTA NOTÍCIA (o rosto nunca muda; o resto foi criado para o tom do assunto)',
        'Tom: ' + (data.moods[d.mood] || d.mood), 'Roupa: ' + d.outfit, 'Acessórios: ' + (d.accessories || 'nenhum extra'), 'Iluminação: ' + (d.lighting || '—'));
      (d.angles || []).forEach((angle, i) => add('Cena ' + (i + 1) + ' · ângulo: ' + angle + (d.gestures && d.gestures[i] ? ' · gesto: ' + d.gestures[i] : '')));
    }
    const extras = plannedFiles().filter((f) => f.kind === 'overlay' || f.kind === 'other');
    if (extras.length) add('', 'EXTRAS PARA A EDIÇÃO (não vão para a IA de vídeo)', ...extras.map((f) => '- ' + f.label + (f.note ? ' — ' + f.note : '') + ' [' + f.name + ']'));
    const prompts = scenePrompts();
    prompts.forEach((_, i) => add('', SEPARATOR, sceneText(i)));
    return lines.join('\n') + '\n';
  }

  /**
   * ONE image with everything the video AI needs to see: the template on the left (the frame to keep) and the
   * character references on the right (only for the face). It is the single image to attach when the tool takes one.
   */
  async function makeBoard(files) {
    const template = files.find((f) => f.kind === 'template');
    if (!template) return null;
    const references = files.filter((f) => f.kind === 'reference');
    const bitmaps = [await createImageBitmap(template.blob), ...(await Promise.all(references.map((f) => createImageBitmap(f.blob))))];
    const M = 40; const head = 80; const colW = references.length ? 1000 : 0;
    const tw = bitmaps[0].width; const th = bitmaps[0].height;
    const canvas = document.createElement('canvas');
    canvas.width = M + tw + (references.length ? M + colW : 0) + M;
    canvas.height = head + th + M;
    const c = canvas.getContext('2d');
    c.fillStyle = '#101216'; c.fillRect(0, 0, canvas.width, canvas.height);
    c.fillStyle = '#ffc233'; c.font = '800 38px system-ui, sans-serif'; c.textBaseline = 'middle';
    c.fillText('TEMPLATE · manter a faixa, o título e o card iguais; trocar só a personagem do fundo', M, head / 2 + 4, tw);
    c.drawImage(bitmaps[0], M, head, tw, th);
    if (references.length) {
      const x0 = M + tw + M;
      c.fillStyle = '#ffc233';
      c.fillText('PERSONAGEM · só para manter o rosto', x0, head / 2 + 4, colW);
      const gap = 30; const label = 44;
      const slot = (th - gap * (references.length - 1)) / references.length;
      references.forEach((file, i) => {
        const bmp = bitmaps[i + 1];
        const maxH = slot - label;
        const scale = Math.min((colW - 40) / bmp.width, maxH / bmp.height);
        const w = bmp.width * scale; const h = bmp.height * scale;
        const y = head + i * (slot + gap);
        c.fillStyle = '#ffffff'; c.font = '600 28px system-ui, sans-serif';
        c.fillText((file.role === 'photo' ? 'Foto' : 'Folha com vistas') + ' · ' + characterName(), x0, y + label / 2, colW);
        c.drawImage(bmp, x0 + (colW - w) / 2, y + label, w, h);
      });
    }
    return canvasBlob(canvas);
  }

  /** `only`: the index of one scene (its block alone), or undefined for the whole react. */
  async function buildPack(only, withHtml = true) {
    const files = await collectFiles();
    const board = await makeBoard(files);
    const text = only === undefined ? buildText() : sceneText(only);
    if (!withHtml) return { text, html: '', files, board };
    const parts = ['<h1>Radar Viral BR · ' + (only === undefined ? 'pacote completo do react' : 'cena ' + (only + 1)) + '</h1>'];
    if (board) parts.push('<h3>Prancha única: template + referências da personagem</h3><img src="' + (await read(board)) + '" alt="Prancha com o template e as referências" style="max-width:640px;height:auto">');
    parts.push('<pre style="white-space:pre-wrap;font-family:Consolas,monospace;font-size:12px">' + escapeHtml(text) + '</pre>');
    for (const file of files) {
      const url = await read(file.blob);
      parts.push('<h3>' + escapeHtml(file.label) + '</h3><p>' + escapeHtml(file.name) + (file.note ? ' — ' + escapeHtml(file.note) : '') + '</p><img src="' + url + '" alt="' + escapeHtml(file.label) + '" style="max-width:520px;height:auto">');
    }
    return { text, html: '<!doctype html><html><body>' + parts.join('\n') + '</body></html>', files, board };
  }

  /* ---- minimal ZIP (stored, no compression) ---- */
  const crcTable = (() => {
    const table = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
      let c = n;
      for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
      table[n] = c >>> 0;
    }
    return table;
  })();
  function crc32(bytes) {
    let crc = 0xFFFFFFFF;
    for (let i = 0; i < bytes.length; i++) crc = crcTable[(crc ^ bytes[i]) & 0xFF] ^ (crc >>> 8);
    return (crc ^ 0xFFFFFFFF) >>> 0;
  }
  function makeZip(entries) {
    const encoder = new TextEncoder();
    const chunks = [];
    const central = [];
    let offset = 0;
    const now = new Date();
    const dosTime = (now.getHours() << 11) | (now.getMinutes() << 5) | (now.getSeconds() >> 1);
    const dosDate = ((now.getFullYear() - 1980) << 9) | ((now.getMonth() + 1) << 5) | now.getDate();
    for (const entry of entries) {
      const name = encoder.encode(entry.name);
      const crc = crc32(entry.bytes);
      const local = new DataView(new ArrayBuffer(30));
      local.setUint32(0, 0x04034b50, true); local.setUint16(4, 20, true); local.setUint16(6, 0x0800, true); local.setUint16(8, 0, true);
      local.setUint16(10, dosTime, true); local.setUint16(12, dosDate, true); local.setUint32(14, crc, true);
      local.setUint32(18, entry.bytes.length, true); local.setUint32(22, entry.bytes.length, true); local.setUint16(26, name.length, true); local.setUint16(28, 0, true);
      chunks.push(new Uint8Array(local.buffer), name, entry.bytes);
      const head = new DataView(new ArrayBuffer(46));
      head.setUint32(0, 0x02014b50, true); head.setUint16(4, 20, true); head.setUint16(6, 20, true); head.setUint16(8, 0x0800, true); head.setUint16(10, 0, true);
      head.setUint16(12, dosTime, true); head.setUint16(14, dosDate, true); head.setUint32(16, crc, true);
      head.setUint32(20, entry.bytes.length, true); head.setUint32(24, entry.bytes.length, true); head.setUint16(28, name.length, true);
      head.setUint32(42, offset, true);
      central.push(new Uint8Array(head.buffer), name);
      offset += 30 + name.length + entry.bytes.length;
    }
    const centralSize = central.reduce((sum, part) => sum + part.length, 0);
    const end = new DataView(new ArrayBuffer(22));
    end.setUint32(0, 0x06054b50, true); end.setUint16(8, entries.length, true); end.setUint16(10, entries.length, true);
    end.setUint32(12, centralSize, true); end.setUint32(16, offset, true);
    return new Blob([...chunks, ...central, new Uint8Array(end.buffer)], { type: 'application/zip' });
  }

  function download(blob, name) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = name; document.body.append(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
  }

  const status = (text, isError) => {
    const el = $('st-pack-status');
    if (!el) return;
    el.textContent = text;
    el.classList.toggle('error-text', Boolean(isError));
  };

  async function withBusy(button, label, job) {
    const original = button.textContent;
    button.disabled = true; button.textContent = label;
    try { await job(); } catch (error) { status('Não deu certo: ' + error.message + ' Se o navegador bloqueou a cópia, use “Baixar pacote completo (.zip)”.', true); toast(error.message, true); }
    button.disabled = false; button.textContent = original;
  }

  // Small hook for diagnostics and automated checks.
  window.RadarStudioPack = { buildPack, buildText, sceneText, makeZip, crc32, makeBoard, collectFiles, plannedFiles };

  /**
   * One way to copy: the TEXT only (no image), for pasting together with a print of the template the person chose.
   * `only` is the index of one scene, or undefined for all of them.
   */
  const baseLabel = (only) => (only === undefined ? 'Copiar todos os textos' : 'Copiar cena ' + (only + 1) + ' (só o texto)');
  function refreshLabels() {
    const button = $('st-copy-all');
    if (button) button.textContent = baseLabel(undefined);
    S.setSceneText({ scene: sceneText, full: buildText, label: (index) => baseLabel(index) });
  }

  async function copyText(button, only) {
    if (!state.spoken.trim() && !state.prompt) { status('Escreva ou gere o roteiro antes de copiar.', true); return; }
    button.disabled = true;
    try {
      await navigator.clipboard.writeText(only === undefined ? buildText() : sceneText(only));
      status('Texto copiado' + (only === undefined ? ' das ' + scenePrompts().length + ' cenas' : ' da cena ' + (only + 1)) + ' (imagens a anexar, roupa e estúdio, roteiro e prompt). Cole junto com o print do template.');
      toast('Texto copiado.');
    } catch (error) {
      status('Não deu certo: ' + error.message + ' Se o navegador bloqueou a cópia, use “Baixar pacote completo (.zip)”.', true);
      toast(error.message, true);
    } finally {
      button.disabled = false;
    }
  }
  $('st-copy-all').addEventListener('click', (event) => copyText(event.currentTarget));
  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-copy-scene]');
    if (button) copyText(button, Number(button.dataset.copyScene));
  });
  $('st-download-pack').addEventListener('click', (event) => withBusy(event.currentTarget, 'Montando…', async () => {
    const pack = await buildPack();
    const encoder = new TextEncoder();
    const entries = [{ name: '00-LEIA-ME-prompts-e-roteiro.txt', bytes: encoder.encode(pack.text) }];
    if (pack.board) entries.push({ name: '00-prancha-template-e-referencias.png', bytes: new Uint8Array(await pack.board.arrayBuffer()) });
    for (const file of pack.files) entries.push({ name: file.name, bytes: new Uint8Array(await file.blob.arrayBuffer()) });
    download(makeZip(entries), 'radar-pacote-' + (state.projectId || state.scriptId || 'novo') + '.zip');
    status('Pacote baixado: o texto completo e ' + pack.files.length + ' imagem(ns) em um .zip.');
  }));

  $('st-export-txt').addEventListener('click', (event) => withBusy(event.currentTarget, 'Montando…', async () => {
    download(new Blob([buildText()], { type: 'text/plain;charset=utf-8' }), 'radar-roteiro-' + (state.projectId || state.scriptId || 'novo') + '.txt');
  }));

  // From now on every scene block of the Studio shows (and copies) the complete text.
  refreshLabels();
  // The coordinates come from a drawing of the frame: make one right away, so the blocks have them even before the first redraw.
  const warm = document.createElement('canvas');
  warm.width = 540; warm.height = 960;
  S.render(warm, 540, 960).then(() => refreshLabels());
})();
