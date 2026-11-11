/* Radar Viral BR — Estúdio: "Copiar tudo". One click puts the whole react on the clipboard (the full text and the
   images), or downloads the same package as a .zip. Runs after studio.js and reads its state. */
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
  const box = (r, W, H) => 'x ' + px(r.x) + ' px (' + pct(r.x, W) + '), y ' + px(r.y) + ' px (' + pct(r.y, H) + '), largura ' + px(r.w) + ' px (' + pct(r.w, W) + '), altura ' + px(r.h) + ' px (' + pct(r.h, H) + ')';

  const extension = (mime) => ({ 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' }[mime] || 'img');
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

  /** The text of the whole react: story, speech, look, every coordinate, the images to attach and every scene prompt. */
  function buildText(files) {
    const script = data.scripts.find((s) => s.id === state.scriptId);
    const card = state.template === 'caption_card';
    const lines = [];
    const add = (...parts) => lines.push(...parts);
    add('RADAR VIRAL BR · PACOTE COMPLETO DO REACT', SEPARATOR, '', 'COMO USAR: o template é o primeiro quadro de cada cena (a IA mantém a faixa, o título e o card e troca só a personagem do fundo). Se a ferramenta aceitar uma imagem só, use a PRANCHA (template + referências na mesma imagem).', '');
    add('Personagem: ' + characterName(), 'Título: ' + (state.title || '—'), 'Modelo do vídeo: ' + data.templates[state.template].label);
    if (data.news) {
      add('', 'NOTÍCIA', 'Manchete real: ' + data.news.title, 'Fonte: ' + data.news.source, 'Categoria: ' + (data.news.category || '—'), 'Link: ' + data.news.url);
      if (data.news.published_at) add('Publicada em: ' + data.news.published_at);
    }
    add('', SEPARATOR, 'ROTEIRO FALADO (completo, em português)', SEPARATOR, state.spoken || '—');
    if (script && script.headlines && script.headlines.length) add('', 'Headlines sugeridas:', ...script.headlines.map((h) => '- ' + h));

    const d = state.direction;
    if (d && d.outfit) {
      add('', SEPARATOR, 'VISUAL DESTA NOTÍCIA (o rosto nunca muda; o resto foi criado para o tom do assunto)', SEPARATOR,
        'Tom: ' + (data.moods[d.mood] || d.mood), 'Roupa: ' + d.outfit, 'Acessórios: ' + (d.accessories || 'nenhum extra'), 'Iluminação: ' + (d.lighting || '—'));
      (d.angles || []).forEach((angle, i) => add('Cena ' + (i + 1) + ' · ângulo: ' + angle + (d.gestures && d.gestures[i] ? ' · gesto: ' + d.gestures[i] : '')));
    }

    add('', SEPARATOR, 'COMPOSIÇÃO 9:16 (tela de 1080 × 1920 px; origem no canto superior esquerdo)', SEPARATOR);
    const L = S.layout();
    if (card) {
      add('Frase de impacto (faixa preta do topo, MAIÚSCULAS): ' + ((state.caption || '').toUpperCase() || '—'),
        'Manchete no card (a real, em MAIÚSCULAS): ' + ((state.headline || '').toUpperCase() || '—'),
        'Linha amarela do card: ' + S.sourceLine());
      if (L && L.template === 'caption_card') {
        add('Faixa preta do topo: ' + box(L.band, L.W, L.H), 'Card da notícia: ' + box(L.card, L.W, L.H),
          '  Imagem da notícia dentro do card: ' + box(L.image, L.W, L.H), '  Barra preta com FONTE • DATA e manchete: ' + box(L.bar, L.W, L.H),
          'Área livre para o rosto e os gestos: ' + box(L.safe, L.W, L.H));
      }
    } else {
      add('Headline sobre a imagem: ' + (state.headline || '—'));
      if (L && L.template === 'split') add('Metade de cima (notícia): ' + box(L.news, L.W, L.H), 'Metade de baixo (personagem): ' + box(L.character, L.W, L.H));
    }
    add('Enquadramento da imagem da notícia: horizontal ' + Math.round(state.x) + '%, vertical ' + Math.round(state.y) + '%, zoom ' + Number(state.zoom).toFixed(2).replace('.', ','));
    const cover = data.media.find((m) => m.id === state.coverId);
    if (cover) add('Imagem da notícia: ' + cover.label + (cover.credit ? ' (crédito: ' + cover.credit + ')' : ''));

    add('', SEPARATOR, 'IMAGENS PARA ANEXAR NA FERRAMENTA DE VÍDEO / EDIÇÃO', SEPARATOR);
    files.forEach((file, i) => add((i + 1) + ') ' + file.label + (file.note ? ' — ' + file.note : '') + ' [' + file.name + ']'));
    if (!files.length) add('Nenhuma imagem disponível.');

    const prompts = state.prompts.length ? state.prompts : (state.prompt ? [state.prompt] : []);
    add('', SEPARATOR, 'PROMPTS DE VÍDEO (' + prompts.length + (prompts.length === 1 ? ' cena' : ' cenas') + ' de até 25 s; cole uma por vez, sempre com a imagem do template, as imagens de referência e a imagem da notícia anexadas)', SEPARATOR);
    prompts.forEach((prompt, i) => add('', '##### CENA ' + (i + 1) + ' DE ' + prompts.length + ' #####', prompt));
    return lines.join('\n') + '\n';
  }

  /** Every image that belongs to this react, with a plain file name. */
  async function collectFiles() {
    const files = [];
    const push = (blob, name, label, note, kind, role) => { if (blob) files.push({ blob, name, label, note, kind: kind || 'other', role }); };
    let count = 0;
    const next = () => String(++count).padStart(2, '0');
    // The template comes first: it is the frame the video AI must deliver (it only swaps the person in the background).
    push(await composition(false), next() + '-template-1080x1920.png', 'IMAGEM DO TEMPLATE (a principal)', 'use como primeiro quadro (start frame) de cada cena: a IA mantém a faixa, o título e o card exatamente assim e troca só a personagem do fundo', 'template');
    // Every reference image of the character goes with the prompts (the video tool needs all of them in every scene).
    for (const reference of data.references[state.character] || []) {
      const blob = await mediaBlob(reference.id);
      if (blob) {
        const photo = reference.role === 'photo';
        push(blob, next() + '-referencia-' + (photo ? 'foto' : 'folha-de-vistas') + '.' + extension(blob.type), (photo ? 'Foto de referência' : 'Folha de referência') + ' de ' + characterName() + (reference.label ? ' (' + reference.label + ')' : ''), 'anexe em TODAS as cenas (só serve para manter o rosto)', 'reference', reference.role);
      }
    }
    push(await composition(true), next() + '-camada-' + (state.template === 'caption_card' ? 'legenda-e-card' : 'noticia') + '-transparente.png', 'Camada transparente', 'vai por cima do vídeo gerado', 'overlay');
    const coverBlob = await mediaBlob(state.coverId);
    if (coverBlob) push(coverBlob, next() + '-imagem-da-noticia.' + extension(coverBlob.type), 'Imagem da notícia (miniatura do artigo)', 'anexe também: vai dentro do card, com o crédito da fonte', 'news');
    const characterBlob = await mediaBlob(state.characterId);
    if (characterBlob) push(characterBlob, next() + '-personagem-no-estudio.' + extension(characterBlob.type), 'Imagem do personagem usada no Estúdio', '');
    return files;
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

  async function buildPack() {
    const files = await collectFiles();
    const board = await makeBoard(files);
    const text = buildText(files);
    const parts = ['<h1>Radar Viral BR · pacote completo do react</h1>'];
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
  window.RadarStudioPack = { buildPack, buildText, makeZip, crc32, makeBoard, collectFiles };

  $('st-copy-all').addEventListener('click', (event) => withBusy(event.currentTarget, 'Preparando…', async () => {
    if (!state.spoken.trim() && !state.prompt) { status('Escreva ou gere o roteiro antes de copiar.', true); return; }
    const packPromise = buildPack();
    if (navigator.clipboard && window.ClipboardItem) {
      // One copy carries the text, the same text with the images, and the board as an image: each place you paste into takes what it understands.
      await navigator.clipboard.write([new ClipboardItem({
        'text/plain': packPromise.then((pack) => new Blob([pack.text], { type: 'text/plain' })),
        'text/html': packPromise.then((pack) => new Blob([pack.html], { type: 'text/html' })),
        'image/png': packPromise.then((pack) => pack.board),
      })]);
    } else {
      await navigator.clipboard.writeText((await packPromise).text);
    }
    const pack = await packPromise;
    status('Copiado de uma vez: o texto completo (' + state.prompts.length + ' prompt(s), coordenadas e visual) e a prancha com o template e as referências como imagem. Cole na ferramenta de vídeo: o texto vai no prompt e, onde ela aceitar imagem, a prancha vem junto. Em um documento ou editor vêm todas as ' + pack.files.length + ' imagens.');
    toast('Pacote completo copiado.');
  }));

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
    download(new Blob([buildText(await collectFiles())], { type: 'text/plain;charset=utf-8' }), 'radar-roteiro-' + (state.projectId || state.scriptId || 'novo') + '.txt');
  }));
})();
