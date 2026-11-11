/* Radar Viral BR — Estúdio de React. The preview and the PNG exports share one draw routine. */
(() => {
  'use strict';
  const dataEl = document.getElementById('studio-data');
  if (!dataEl) return;
  const data = JSON.parse(dataEl.textContent);
  const $ = (id) => document.getElementById(id);
  const api = window.RadarApi;
  const toast = window.RadarToast || (() => {});

  const project = data.project;
  const initialScript = !project && data.scriptId ? data.scripts.find((s) => s.id === data.scriptId) : null;
  // The chosen video template survives the reload that happens when another news is picked.
  const requestedTemplate = new URLSearchParams(location.search).get('modelo') || '';
  const chosenTemplate = Object.prototype.hasOwnProperty.call(data.templates, requestedTemplate) ? requestedTemplate : data.defaultTemplate;
  // The character that covers the news category, unless the person chose another (it survives a news change).
  const requestedCharacter = new URLSearchParams(location.search).get('personagem') || '';
  const chosenCharacter = data.characterList.some((c) => c.slug === requestedCharacter) ? requestedCharacter : data.suggestedCharacter;
  const state = {
    character: project ? project.character : (initialScript ? initialScript.character : chosenCharacter),
    newsId: data.news ? data.news.id : null,
    scriptId: data.scriptId,
    projectId: project ? project.id : null,
    template: project ? project.template : (initialScript ? initialScript.layout : chosenTemplate),
    title: project ? project.title : '',
    headline: project ? project.headline : (data.news ? data.news.title : ''),
    caption: project ? project.caption : '',
    spoken: project ? project.script : '',
    expression: project ? project.expression : 'sorriso-largo',
    scene: 'carro',
    scenes: initialScript ? initialScript.scenes : data.defaultScenes,
    prompts: initialScript ? initialScript.video_prompts : [],
    direction: initialScript ? initialScript.direction : (project ? project.direction : null),
    coverId: project ? project.cover_media_id : (data.preselectCover || null),
    characterId: project ? project.character_media_id : null,
    x: project ? project.cover_x : 50,
    y: project ? project.cover_y : 50,
    zoom: project ? project.cover_zoom : 1,
    prompt: project ? project.video_prompt : '',
  };
  if (!data.templates[state.template]) state.template = data.defaultTemplate;
  const characterImages = () => {
    const stills = data.characters.filter((c) => c.character === state.character);
    if (stills.length) return stills;
    // No still of this character yet: a reference photo of the same person goes behind the template.
    return (data.references[state.character] || []).filter((r) => r.role === 'photo').map((r) => ({ id: r.id, label: r.label || 'Foto de referência', primary: false, character: state.character }));
  };
  const primaryImage = () => { const list = characterImages(); return list.find((c) => c.primary) || list[0] || null; };
  const characterName = () => (data.characterList.find((c) => c.slug === state.character) || { name: '' }).name;
  if (!state.characterId || !characterImages().some((c) => c.id === state.characterId)) {
    const primary = primaryImage();
    state.characterId = primary ? primary.id : null;
  }
  let characterTouched = false;
  if (!state.coverId && data.media.length) state.coverId = data.media[0].id;

  const canvas = $('st-canvas');
  const images = new Map();

  function loadImage(id) {
    if (!id) return Promise.resolve(null);
    if (images.has(id)) return images.get(id);
    const promise = new Promise((resolve) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => resolve(null);
      img.src = 'media.php?id=' + encodeURIComponent(id);
    });
    images.set(id, promise);
    return promise;
  }

  /* Heavy condensed lettering like the reference. Only system fonts (strict CSP): Impact when present. */
  const hasImpact = (() => {
    const probe = document.createElement('canvas').getContext('2d');
    const sample = 'WWWWiiii';
    probe.font = '40px monospace';
    const fallback = probe.measureText(sample).width;
    probe.font = '40px Impact, monospace';
    return probe.measureText(sample).width !== fallback;
  })();
  const heavy = (px) => (hasImpact
    ? `${px}px Impact, "Arial Narrow", sans-serif`
    : `800 ${Math.round(px * 0.82)}px "Arial Narrow", system-ui, -apple-system, "Segoe UI", sans-serif`);

  function wrap(context, text, maxWidth) {
    const words = text.split(/\s+/).filter(Boolean);
    const lines = [];
    let line = '';
    for (const word of words) {
      const test = line ? line + ' ' + word : word;
      if (context.measureText(test).width > maxWidth && line) { lines.push(line); line = word; } else { line = test; }
    }
    if (line) lines.push(line);
    return lines;
  }

  /** Wrap and, if needed, shrink the type until it fits `maxLines`; ellipsis as a last resort. */
  function fitText(context, text, fontFor, startSize, maxWidth, maxLines) {
    let size = startSize;
    let lines = [];
    for (let i = 0; i < 12; i++) {
      context.font = fontFor(size);
      lines = wrap(context, text, maxWidth);
      if (lines.length <= maxLines) return { lines, size };
      size *= 0.93;
    }
    lines = lines.slice(0, maxLines);
    lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, '') + '…';
    return { lines, size };
  }

  function drawCover(context, img, x, y, w, h) {
    // "cover" fit with user zoom and anchor: identical maths for preview and export.
    const base = Math.max(w / img.width, h / img.height);
    const scale = base * state.zoom;
    const dw = img.width * scale;
    const dh = img.height * scale;
    context.drawImage(img, x + (w - dw) * (state.x / 100), y + (h - dh) * (state.y / 100), dw, dh);
  }

  function sourceLine() {
    if (!data.news) return 'FONTE';
    let line = data.news.source;
    if (data.news.published_at) {
      const date = new Date(data.news.published_at);
      if (!Number.isNaN(date.getTime())) line += ' • ' + date.toLocaleDateString('pt-BR');
    }
    return line.toUpperCase();
  }

  // Where things were drawn in the last render (px of that render). The package lists these as coordinates.
  let lastLayout = null;

  /* ---- template 1: news on top, character below ---- */
  function renderSplit(c, W, H, assets, overlay) {
    const half = H / 2;
    const { cover, character } = assets;
    lastLayout = { W, H, template: 'split', news: { x: 0, y: 0, w: W, h: half }, character: { x: 0, y: half, w: W, h: half } };
    c.save();
    c.beginPath(); c.rect(0, 0, W, half); c.clip();
    if (cover) {
      drawCover(c, cover, 0, 0, W, half);
    } else {
      const g = c.createLinearGradient(0, 0, W, half);
      g.addColorStop(0, '#1a1d25'); g.addColorStop(1, '#0d0f14');
      c.fillStyle = g; c.fillRect(0, 0, W, half);
      c.fillStyle = '#9aa1ad'; c.font = `600 ${W * 0.04}px system-ui, sans-serif`; c.textAlign = 'center';
      c.fillText('Escolha a imagem da notícia', W / 2, half / 2);
    }
    const shade = c.createLinearGradient(0, half * 0.5, 0, half);
    shade.addColorStop(0, 'rgba(0,0,0,0)'); shade.addColorStop(1, 'rgba(0,0,0,0.92)');
    c.fillStyle = shade; c.fillRect(0, half * 0.5, W, half * 0.5);
    c.textAlign = 'left'; c.textBaseline = 'alphabetic';
    const fontSize = Math.round(W * 0.062);
    c.font = `800 ${fontSize}px system-ui, -apple-system, "Segoe UI", sans-serif`;
    let lines = wrap(c, state.headline || '', W * 0.88);
    const maxLines = 4;
    if (lines.length > maxLines) { lines = lines.slice(0, maxLines); lines[maxLines - 1] = lines[maxLines - 1].replace(/\s*\S*$/, '') + '…'; }
    c.fillStyle = '#fff'; c.shadowColor = 'rgba(0,0,0,.8)'; c.shadowBlur = W * 0.01;
    const lineHeight = fontSize * 1.18;
    let y = half - W * 0.05 - (lines.length - 1) * lineHeight;
    for (const line of lines) { c.fillText(line, W * 0.06, y); y += lineHeight; }
    c.shadowBlur = 0;
    if (data.news) {
      c.font = `600 ${Math.round(W * 0.028)}px system-ui, sans-serif`;
      const label = 'Fonte: ' + data.news.source;
      const tw = c.measureText(label).width + W * 0.04;
      c.fillStyle = 'rgba(0,0,0,.65)';
      c.beginPath(); c.roundRect(W * 0.04, W * 0.04, tw, W * 0.06, W * 0.03); c.fill();
      c.fillStyle = '#fff'; c.fillText(label, W * 0.06, W * 0.04 + W * 0.04);
    }
    c.restore();
    if (overlay) return;

    c.save();
    c.beginPath(); c.rect(0, half, W, half); c.clip();
    c.fillStyle = '#101216'; c.fillRect(0, half, W, half);
    if (character) {
      const scale = Math.max(W / character.width, half / character.height);
      const dw = character.width * scale; const dh = character.height * scale;
      c.drawImage(character, (W - dw) / 2, half, dw, dh); // anchored to the top: keeps the face
    } else {
      c.fillStyle = '#9aa1ad'; c.font = `600 ${W * 0.04}px system-ui, sans-serif`; c.textAlign = 'center';
      c.fillText('Cadastre o personagem em', W / 2, half + half / 2 - W * 0.02);
      c.fillText('“Meu Personagem”', W / 2, half + half / 2 + W * 0.04);
    }
    c.restore();
    c.fillStyle = '#ffc233'; c.fillRect(0, half - 2, W, 4);
  }

  /* ---- template 2: full-screen video, impact phrase on a black band, news card below ---- */
  function renderCaptionCard(c, W, H, assets, overlay) {
    const { cover, character } = assets;
    c.textBaseline = 'alphabetic';
    if (!overlay) {
      if (character) {
        const scale = Math.max(W / character.width, H / character.height);
        const dw = character.width * scale; const dh = character.height * scale;
        c.drawImage(character, (W - dw) / 2, 0, dw, dh); // anchored to the top: keeps the face
      } else {
        const g = c.createRadialGradient(W / 2, H / 2, 10, W / 2, H / 2, H * 0.6);
        g.addColorStop(0, '#6b6b6b'); g.addColorStop(1, '#202020');
        c.fillStyle = g; c.fillRect(0, 0, W, H);
        c.fillStyle = 'rgba(255,255,255,.55)'; c.textAlign = 'center'; c.font = heavy(W * 0.05);
        c.fillText('SEU VÍDEO EM TELA CHEIA', W / 2, H * 0.43);
      }
    }

    // top band with the impact phrase
    const pad = W * 0.045;
    const phrase = (state.caption || '').trim().toUpperCase();
    const capText = phrase || 'SUA FRASE DE IMPACTO SOBRE A NOTÍCIA AQUI';
    const cap = fitText(c, capText, heavy, W * (hasImpact ? 0.098 : 0.09), W - pad * 2, 3);
    const capLine = cap.size * (hasImpact ? 1.12 : 1.18);
    const bandH = Math.max(H * 0.13, pad * 1.6 + cap.lines.length * capLine);
    c.fillStyle = '#000'; c.fillRect(0, 0, W, bandH);
    const fade = c.createLinearGradient(0, bandH, 0, bandH + H * 0.035);
    fade.addColorStop(0, 'rgba(0,0,0,.85)'); fade.addColorStop(1, 'rgba(0,0,0,0)');
    c.fillStyle = fade; c.fillRect(0, bandH, W, H * 0.035);
    c.textAlign = 'center';
    c.fillStyle = phrase ? '#fff' : 'rgba(255,255,255,.45)';
    c.font = heavy(cap.size);
    let ty = (bandH - cap.lines.length * capLine) / 2 + cap.size * (hasImpact ? 0.92 : 0.86);
    for (const line of cap.lines) { c.fillText(line, W / 2, ty); ty += capLine; }

    // news card: image on top, black info bar glued below
    const cw = W * 0.71;
    const ch = cw * 0.82;
    const cx = (W - cw) / 2;
    const cy = H - H * 0.056 - ch;
    const ip = cw * 0.045;
    const headline = (state.headline || '').trim().toUpperCase() || 'MANCHETE DA NOTÍCIA EM DUAS OU TRÊS LINHAS';
    let size = cw * (hasImpact ? 0.088 : 0.074);
    let fit;
    let barH;
    const labelSize = cw * 0.042;
    for (let i = 0; i < 8; i++) {
      fit = fitText(c, headline, heavy, size, cw - ip * 2, 3);
      barH = ip + labelSize * 1.15 + ip * 0.45 + fit.lines.length * fit.size * 1.1 + ip;
      if (barH <= ch * 0.6) break;
      size *= 0.92;
    }
    const imageH = ch - barH;
    lastLayout = {
      W, H, template: 'caption_card', band: { x: 0, y: 0, w: W, h: bandH }, card: { x: cx, y: cy, w: cw, h: ch },
      image: { x: cx, y: cy, w: cw, h: imageH }, bar: { x: cx, y: cy + imageH, w: cw, h: barH }, safe: { x: 0, y: bandH, w: W, h: cy - bandH },
    };

    c.save();
    c.shadowColor = 'rgba(0,0,0,.55)'; c.shadowBlur = W * 0.03; c.shadowOffsetY = W * 0.008;
    c.fillStyle = '#000'; c.fillRect(cx, cy, cw, ch);
    c.restore();
    c.save();
    c.beginPath(); c.rect(cx, cy, cw, imageH); c.clip();
    if (cover) {
      drawCover(c, cover, cx, cy, cw, imageH);
    } else {
      const g = c.createLinearGradient(cx, cy, cx, cy + imageH);
      g.addColorStop(0, '#4a4a4a'); g.addColorStop(1, '#2b2b2b');
      c.fillStyle = g; c.fillRect(cx, cy, cw, imageH);
      c.fillStyle = 'rgba(255,255,255,.55)'; c.textAlign = 'center'; c.font = heavy(cw * 0.065);
      c.fillText('IMAGEM DA NOTÍCIA', cx + cw / 2, cy + imageH / 2 + cw * 0.02);
    }
    c.restore();
    c.fillStyle = '#000'; c.fillRect(cx, cy + imageH, cw, barH);
    c.textAlign = 'left';
    c.fillStyle = '#ffe600'; c.font = heavy(labelSize);
    c.fillText(sourceLine(), cx + ip, cy + imageH + ip + labelSize * 0.95);
    c.fillStyle = '#fff'; c.font = heavy(fit.size);
    let hy = cy + imageH + ip + labelSize * 1.15 + ip * 0.45 + fit.size * 0.92;
    for (const line of fit.lines) { c.fillText(line, cx + ip, hy); hy += fit.size * 1.1; }
    c.strokeStyle = 'rgba(255,255,255,.92)'; c.lineWidth = Math.max(2, cw * 0.005);
    c.strokeRect(cx, cy, cw, ch);
  }

  /** Draw the composition. `overlay` skips the character video so only caption band and news card remain (transparent PNG). */
  async function render(target, W, H, options = {}) {
    const c = target.getContext('2d');
    const overlay = Boolean(options.overlay);
    const [cover, character] = await Promise.all([loadImage(state.coverId), loadImage(state.characterId)]);
    c.clearRect(0, 0, W, H);
    if (!overlay) { c.fillStyle = '#0b0c10'; c.fillRect(0, 0, W, H); }
    if (state.template === 'caption_card') renderCaptionCard(c, W, H, { cover, character }, overlay);
    else renderSplit(c, W, H, { cover, character }, overlay);
  }

  let drawTimer = 0;
  function redraw() {
    cancelAnimationFrame(drawTimer);
    drawTimer = requestAnimationFrame(() => render(canvas, canvas.width, canvas.height).then(() => { if (sceneTextFn) renderPrompts(); }));
  }

  /* ---------- controls ---------- */
  const newsSelect = $('st-news');
  for (const n of data.newsOptions) {
    const option = document.createElement('option');
    option.value = n.id;
    option.textContent = (n.saved ? '★ ' : '') + '[' + n.category + '] ' + n.title.slice(0, 90);
    if (state.newsId === n.id) option.selected = true;
    newsSelect.append(option);
  }
  newsSelect.addEventListener('change', () => {
    if (newsSelect.value) location.href = 'estudio.php?news=' + encodeURIComponent(newsSelect.value) + '&modelo=' + encodeURIComponent(state.template) + (characterTouched ? '&personagem=' + encodeURIComponent(state.character) : '');
  });

  const scriptSelect = $('st-script-select');
  for (const s of data.scripts.filter((x) => !state.newsId || x.news_id === state.newsId)) {
    const option = document.createElement('option');
    option.value = s.id;
    option.textContent = '#' + s.id + ' · ' + s.headline.slice(0, 70);
    if (state.scriptId === s.id) option.selected = true;
    scriptSelect.append(option);
  }

  function fillSelect(select, entries, value) {
    for (const [key, label] of Object.entries(entries)) {
      const option = document.createElement('option');
      option.value = key; option.textContent = label;
      select.append(option);
    }
    select.value = value;
  }
  fillSelect($('st-expression'), data.expressions, state.expression);
  fillSelect($('st-scene'), data.scenes, state.scene);
  fillSelect($('st-character'), Object.fromEntries(data.characterList.map((c) => [c.slug, c.name])), state.character);
  fillSelect($('st-template'), Object.fromEntries(Object.entries(data.templates).map(([k, t]) => [k, t.label])), state.template);

  function renderPickers() {
    const covers = $('st-covers');
    covers.replaceChildren();
    for (const m of data.media) {
      const button = document.createElement('button');
      button.type = 'button';
      button.setAttribute('aria-pressed', String(state.coverId === m.id));
      button.title = m.label + (m.credit ? ' · ' + m.credit : '');
      const img = document.createElement('img');
      img.src = 'media.php?id=' + encodeURIComponent(m.id); img.alt = m.label; img.loading = 'lazy';
      button.append(img);
      button.addEventListener('click', () => { state.coverId = m.id; renderPickers(); redraw(); });
      covers.append(button);
    }
    const hint = $('st-cover-hint');
    const imp = $('st-cover-import');
    imp.replaceChildren();
    if (!data.news) {
      hint.textContent = 'Escolha uma notícia para ver as imagens dela.';
    } else if (data.media.length === 0 && data.news.image) {
      hint.textContent = 'A capa da fonte ainda não está guardada localmente. Guarde-a para usar no Estúdio (com o crédito da fonte).';
    } else if (data.media.length === 0) {
      hint.textContent = 'Esta notícia não tem imagem. Anexe um print real abaixo.';
    } else {
      hint.textContent = 'Imagem em uso: ' + (data.media.find((m) => m.id === state.coverId)?.label || '—');
    }
    if (data.news && data.news.image && !data.media.some((m) => m.kind === 'cover')) {
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'btn ghost small';
      button.dataset.act = 'import_cover'; button.dataset.newsId = String(data.news.id); button.dataset.busy = 'Baixando…';
      button.textContent = 'Guardar a capa original (' + (data.news.image_credit || data.news.source) + ')';
      imp.append(button);
    }
    const chars = $('st-chars');
    chars.replaceChildren();
    for (const c of characterImages()) {
      const button = document.createElement('button');
      button.type = 'button';
      button.setAttribute('aria-pressed', String(state.characterId === c.id));
      button.title = c.label || 'Personagem';
      const img = document.createElement('img');
      img.src = 'media.php?id=' + encodeURIComponent(c.id); img.alt = c.label || 'Personagem'; img.loading = 'lazy';
      button.append(img);
      button.addEventListener('click', () => { state.characterId = c.id; renderPickers(); redraw(); });
      chars.append(button);
    }
    $('st-char-hint').textContent = data.characters.some((c) => c.character === state.character) ? ''
      : (characterImages().length ? 'Sem imagem própria no Estúdio: usando a foto de referência de ' + characterName() + ' atrás do template.' : 'Nenhuma imagem de ' + characterName() + ' para ficar atrás do template. Cadastre em “Meus Personagens”.');
  }

  function wordCount() { return (state.spoken.match(/[\p{L}\p{N}]+/gu) || []).length; }
  function words() {
    const n = wordCount();
    $('st-words').textContent = n + ' palavras · ~' + Math.round(n / 2.5) + ' s falados';
  }

  /** One block per scene (each prompt is complete and made for one clip of at most 25 s). */
  // studio-pack.js plugs in the complete block of each scene (images, coordinates, script and prompt together).
  let sceneTextFn = null;
  let fullTextFn = null;
  function renderPrompts() {
    const box = $('st-prompts');
    if (!box) return;
    $('st-prompt').value = fullTextFn ? fullTextFn() : state.prompt;
    box.replaceChildren();
    const list = state.prompts.length ? state.prompts : (state.prompt ? [state.prompt] : []);
    list.forEach((text, index) => {
      const block = document.createElement('div');
      block.className = 'script-block';
      const head = document.createElement('div');
      head.className = 'head';
      const title = document.createElement('span');
      title.textContent = 'Cena ' + (index + 1) + ' de ' + list.length;
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'btn ghost small';
      button.dataset.copyScene = String(index); button.textContent = 'Copiar cena ' + (index + 1) + ' (tudo junto)';
      head.append(title, button);
      const look = state.direction;
      let note = null;
      if (look && look.angles && look.angles.length) {
        note = document.createElement('p');
        note.className = 'hint';
        note.textContent = 'Ângulo: ' + look.angles[index % look.angles.length] + (look.gestures && look.gestures.length ? ' · Gesto: ' + look.gestures[index % look.gestures.length] : '');
      }
      const area = document.createElement('textarea');
      area.id = 'st-prompt-' + index; area.readOnly = true; area.rows = sceneTextFn ? 18 : 8; area.className = 'mono'; area.value = sceneTextFn ? sceneTextFn(index) : text;
      if (note) block.append(head, note, area); else block.append(head, area);
      box.append(block);
    });
    $('st-scenes-hint').textContent = list.length
      ? list.length + (list.length === 1 ? ' cena' : ' cenas') + ' de até 25 s. Cada cena é um bloco completo: imagens a anexar, coordenadas, roteiro e prompt juntos. Cole uma cena por vez na ferramenta de vídeo (“Copiar cena” leva o bloco e a prancha com o template e as referências).'
        + (list.length > state.scenes ? ' O roteiro é longo: precisa de ' + list.length + ' cenas para ficar em até 25 s cada. Encurte o texto para ter menos.' : '')
      : '';
  }

  /** The character reference sheet travels with the prompts: the video tool must get it in every scene. */
  function renderReference() {
    const box = $('st-reference');
    if (!box) return;
    box.replaceChildren();
    const list = data.references[state.character] || [];
    if (list.length) {
      const title = document.createElement('p');
      const strong = document.createElement('strong');
      strong.textContent = 'Anexe ' + (list.length === 1 ? 'esta imagem' : 'estas ' + list.length + ' imagens') + ' em cada cena';
      const hint = document.createElement('span');
      hint.className = 'hint';
      hint.textContent = ' A ferramenta de vídeo pega as imagens junto com a fala e mantém o mesmo personagem e a mesma reação.';
      title.append(strong, hint);
      const grid = document.createElement('div');
      grid.className = 'reference-list';
      for (const reference of list) {
        const figure = document.createElement('figure');
        const img = document.createElement('img');
        img.src = 'media.php?id=' + encodeURIComponent(reference.id); img.alt = reference.label || 'Imagem de referência'; img.loading = 'lazy';
        const caption = document.createElement('figcaption');
        caption.append((reference.role === 'photo' ? 'Foto' : 'Folha com vistas') + (reference.label ? ' · ' + reference.label : ''), document.createElement('br'));
        const link = document.createElement('a');
        link.className = 'btn ghost small'; link.href = 'media.php?id=' + encodeURIComponent(reference.id) + '&download=1'; link.textContent = 'Baixar';
        caption.append(link);
        figure.append(img, caption);
        grid.append(figure);
      }
      box.append(title, grid);
    } else {
      const note = document.createElement('div');
      note.className = 'notice warn';
      const link = document.createElement('a');
      link.href = 'personagem.php?c=' + encodeURIComponent(state.character); link.textContent = 'Meus Personagens';
      note.append('Faltam as imagens de referência de ' + characterName() + '. Cadastre em ', link, ' para baixar e anexar aqui.');
      box.append(note);
    }
  }

  let promptTimer = 0;
  function refreshPrompt() {
    clearTimeout(promptTimer);
    promptTimer = setTimeout(async () => {
      try {
        const result = await api({
          action: 'video_prompt', character: state.character, caption: state.caption, source_line: sourceLine(), spoken: state.spoken, expression: state.expression, scene: state.scene,
          direction: state.direction, scenes: state.scenes, headline: data.news ? data.news.title : state.headline, source: data.news ? data.news.source : '', layout: state.template,
        });
        state.prompt = result.prompt; state.prompts = result.prompts;
        renderPrompts();
      } catch (_) { /* keep the previous prompt */ }
    }, 450);
  }

  /** Caption suggestions: the script's viral headlines and its hook (all faithful to the story). */
  function fillCaptionOptions() {
    const select = $('st-caption-options');
    select.replaceChildren(new Option('Escolher uma headline ou o gancho…', ''));
    const script = data.scripts.find((s) => s.id === state.scriptId);
    const options = [];
    if (script) {
      (script.headlines || []).forEach((h, i) => options.push(['Headline ' + (i + 1) + ': ' + h, h]));
      if (script.hook) options.push(['Gancho: ' + script.hook, script.hook]);
    }
    if (data.news) options.push(['Manchete da fonte: ' + data.news.title, data.news.title]);
    for (const [label, value] of options) select.append(new Option(label.slice(0, 110), value));
  }

  function applyTemplateUi() {
    const card = state.template === 'caption_card';
    $('st-caption-box').hidden = !card;
    $('st-headline-label').textContent = card ? 'Manchete no card (a real, da fonte)' : 'Headline na imagem';
    $('st-template-hint').textContent = data.templates[state.template].description;
    $('st-export-overlay').textContent = card ? 'Exportar camada de legenda e card (PNG transparente)' : 'Exportar camada da notícia (PNG transparente)';
  }

  /** The impact phrase should differ from the real headline on the card: first other headline, else the hook. */
  function pickCaption(script) {
    if (!script) return '';
    const plain = (text) => text.toLowerCase().replace(/[^\p{L}\p{N}]+/gu, ' ').trim();
    const real = data.news ? plain(data.news.title) : '';
    const own = (script.headlines || []).find((h) => plain(h) !== real);
    return own || script.hook || (script.headlines && script.headlines[0]) || '';
  }

  /** Sensible defaults when a template or script is chosen; never overwrites text the user typed. */
  function seedCaption(force) {
    if (state.template !== 'caption_card') return;
    const script = data.scripts.find((s) => s.id === state.scriptId);
    if (force || !state.caption.trim()) {
      state.caption = pickCaption(script) || state.caption || '';
      $('st-caption').value = state.caption;
    }
    // In the card template the headline is the REAL one from the source.
    if (data.news && (!state.headline.trim() || script)) {
      if (!project) { state.headline = data.news.title; $('st-headline').value = state.headline; }
    }
  }

  function bindText(id, key, after) {
    const el = $(id);
    el.value = state[key] ?? '';
    el.addEventListener('input', () => { state[key] = el.value; after && after(); });
  }
  bindText('st-title', 'title');
  bindText('st-headline', 'headline', () => redraw());
  bindText('st-caption', 'caption', () => { redraw(); refreshPrompt(); });
  bindText('st-script', 'spoken', () => { words(); refreshPrompt(); });
  $('st-caption-options').addEventListener('change', (e) => {
    if (!e.target.value) return;
    state.caption = e.target.value; $('st-caption').value = state.caption; redraw(); refreshPrompt();
  });
  $('st-template').addEventListener('change', (e) => {
    state.template = e.target.value;
    applyTemplateUi(); seedCaption(false); fillCaptionOptions(); refreshPrompt(); redraw();
  });
  /** The look of this news: outfit, accessories and light (the face never changes). */
  function fillLook() {
    const look = state.direction || {};
    $('st-outfit').value = look.outfit || '';
    $('st-accessories').value = look.accessories || '';
    $('st-lighting').value = look.lighting || '';
    const origin = { ia: 'sugerido pela IA', sorteio: 'sorteado para esta notícia', manual: 'editado por você', 'padrão': 'padrão' }[look.origin] || '';
    $('st-look-hint').textContent = look.outfit ? 'Tom do visual: ' + (data.moods[look.mood] || '—') + (origin ? ' · ' + origin : '') + '.' : '';
  }
  for (const key of ['outfit', 'accessories', 'lighting']) {
    $('st-' + key).addEventListener('input', (e) => {
      state.direction = Object.assign({ angles: [], gestures: [], mood: 'casual' }, state.direction, { [key]: e.target.value, origin: 'manual' });
      refreshPrompt();
    });
  }
  async function rerollLook() {
    const button = $('st-reroll');
    button.disabled = true;
    try {
      const result = await api({ action: 'direction', character: state.character, news_id: state.newsId || 0, scenes: state.scenes, expression: state.expression, outfit: state.direction ? state.direction.outfit : '' });
      state.direction = result.direction;
      fillLook(); refreshPrompt();
    } catch (error) { toast(error.message, true); }
    button.disabled = false;
  }
  $('st-reroll').addEventListener('click', rerollLook);

  $('st-expression').addEventListener('change', (e) => { state.expression = e.target.value; refreshPrompt(); });
  if ($('st-scenes')) $('st-scenes').addEventListener('change', (e) => { state.scenes = Number(e.target.value); refreshPrompt(); });
  $('st-scene').addEventListener('change', (e) => { state.scene = e.target.value; refreshPrompt(); });
  function characterHint() {
    const info = data.characterList.find((c) => c.slug === state.character);
    $('st-character-hint').textContent = info ? info.tagline + (data.suggestedCharacter === state.character && data.news ? ' · sugerida pela categoria “' + data.news.category + '”.' : '.') : '';
  }
  $('st-character').addEventListener('change', (e) => {
    state.character = e.target.value;
    characterTouched = true;
    const primary = primaryImage();
    state.characterId = primary ? primary.id : null;
    renderPickers(); renderReference(); characterHint(); redraw();
    rerollLook(); // another character, another wardrobe; this also rebuilds the prompts
    toast('Personagem trocado para ' + characterName() + '. O texto falado continua o mesmo; use “Gerar outro roteiro” para a fala ficar no jeito do novo personagem.');
  });
  for (const [id, key] of [['st-x', 'x'], ['st-y', 'y'], ['st-zoom', 'zoom']]) {
    const el = $(id);
    el.value = state[key];
    el.addEventListener('input', () => { state[key] = Number(el.value); redraw(); });
  }

  /** keepTemplate: keep the template chosen in the Studio instead of the one stored with the script. */
  function applyScript(script, keepTemplate) {
    state.scriptId = script.id;
    state.spoken = script.spoken; state.expression = script.expression; state.scene = script.scene;
    state.scenes = script.scenes; state.prompt = script.video_prompt; state.prompts = script.video_prompts; state.direction = script.direction;
    if (script.character && script.character !== state.character) {
      state.character = script.character;
      const primary = primaryImage();
      state.characterId = primary ? primary.id : null;
      $('st-character').value = state.character;
      renderPickers(); renderReference();
    }
    if ($('st-scenes')) $('st-scenes').value = String(state.scenes);
    if (!keepTemplate && data.templates[script.layout]) state.template = script.layout;
    state.headline = state.template === 'caption_card' && data.news ? data.news.title : script.headline;
    state.caption = state.template === 'caption_card' ? pickCaption(script) : state.caption;
    if (!state.title) state.title = script.headline.slice(0, 80);
    $('st-title').value = state.title; $('st-headline').value = state.headline; $('st-script').value = state.spoken; $('st-caption').value = state.caption;
    $('st-expression').value = state.expression; $('st-scene').value = state.scene; $('st-template').value = state.template; renderPrompts();
    applyTemplateUi(); fillCaptionOptions(); words(); fillLook(); redraw();
    if (keepTemplate && script.layout !== state.template) refreshPrompt();
  }

  scriptSelect.addEventListener('change', () => {
    const script = data.scripts.find((s) => String(s.id) === scriptSelect.value);
    if (!script) { state.scriptId = null; return; }
    applyScript(script);
  });

  /* ---------- the script is generated here as soon as a news is chosen ---------- */
  const genButton = $('st-generate');
  const genStatus = $('st-gen-status');
  const toneSelect = $('st-tone');
  let generating = false;
  if (toneSelect) fillSelect(toneSelect, data.tones, 'bem-humorado');

  function setGenStatus(text, isError) {
    if (!genStatus) return;
    genStatus.textContent = text;
    genStatus.classList.toggle('error-text', Boolean(isError));
  }

  function scriptOption(script) {
    return new Option('#' + script.id + ' · ' + script.headline.slice(0, 70), String(script.id));
  }

  async function generateScript() {
    if (!data.news || generating) return;
    generating = true;
    const box = $('st-script');
    const placeholder = box.placeholder;
    box.placeholder = 'Gerando o roteiro a partir da notícia… pode levar até um minuto.';
    if (genButton) genButton.disabled = true;
    scriptSelect.disabled = true;
    setGenStatus('Gerando o roteiro…');
    try {
      const result = await api({
        action: 'generate', character: state.character, news_id: data.news.id, scenes: Number($('st-scenes').value) || data.defaultScenes,
        tone: toneSelect ? toneSelect.value : 'bem-humorado', layout: state.template,
      });
      const item = result.item;
      const script = {
        id: item.id, news_id: item.news_id, news_title: item.news_title, news_source: item.news_source,
        headline: (item.headlines && item.headlines[0]) || item.news_title, headlines: item.headlines || [], hook: item.hook,
        spoken: item.spoken, expression: item.expression, scene: item.scene, layout: item.layout, scenes: item.scenes, character: item.character, direction: item.direction, video_prompt: item.video_prompt, video_prompts: item.video_prompts,
      };
      data.scripts.unshift(script);
      scriptSelect.append(scriptOption(script));
      scriptSelect.value = String(script.id);
      applyScript(script, true);
      history.replaceState(null, '', 'estudio.php?news=' + data.news.id + '&roteiro=' + script.id);
      setGenStatus(item.mode === 'ia'
        ? 'Roteiro gerado por IA. Confira os fatos na matéria antes de gravar.'
        : 'Rascunho conservador (sem IA). Cadastre a chave em Config IA para roteiros melhores.');
    } catch (error) {
      setGenStatus('Não foi possível gerar o roteiro: ' + error.message, true);
    }
    box.placeholder = placeholder;
    scriptSelect.disabled = false;
    if (genButton) genButton.disabled = false;
    generating = false;
  }

  if (genButton) {
    genButton.addEventListener('click', () => {
      const current = data.scripts.find((s) => s.id === state.scriptId);
      const edited = state.spoken.trim() !== '' && (!current || current.spoken !== state.spoken);
      if (edited && !window.confirm('Substituir o texto que você editou por um roteiro novo?')) return;
      generateScript();
    });
  }

  if (initialScript) applyScript(initialScript);
  if (data.news && !project && !initialScript) {
    // Reuse the newest script already made for this news; otherwise write one now.
    const existing = data.scripts.filter((s) => s.news_id === data.news.id).sort((a, b) => b.id - a.id)[0];
    if (existing) {
      scriptSelect.value = String(existing.id);
      applyScript(existing, true);
      setGenStatus('Usei o roteiro mais recente desta notícia (#' + existing.id + '). Use “Gerar outro roteiro” para criar um novo.');
    } else {
      generateScript();
    }
  }
  renderPrompts(); renderReference(); fillLook(); characterHint();
  if (!state.direction && !(data.news && !project && !initialScript)) rerollLook();
  if (project) {
    // Projects keep their text; the prompts are rebuilt so they follow today's standards and scene cuts.
    const linked = data.scripts.find((s) => s.id === project.script_id);
    state.scenes = linked ? linked.scenes : Math.max(1, Math.ceil(wordCount() / 62));
    if ($('st-scenes')) $('st-scenes').value = String(state.scenes);
  }
  if ((!state.prompt || project) && state.spoken) refreshPrompt();
  applyTemplateUi();
  seedCaption(false);
  fillCaptionOptions();

  /* ---------- save / export ---------- */
  $('st-save').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
      const result = await api({
        action: 'project_save', id: state.projectId || 0, script_id: state.scriptId || 0, news_id: state.newsId || 0,
        title: state.title, headline: state.headline, caption: state.caption, template: state.template, character: state.character, direction: state.direction, script: state.spoken, video_prompt: state.prompt,
        cover_media_id: state.coverId || '', character_media_id: state.characterId || '',
        cover_x: state.x, cover_y: state.y, cover_zoom: state.zoom, expression: state.expression,
      });
      state.projectId = result.item.id;
      history.replaceState(null, '', 'estudio.php?projeto=' + state.projectId);
      toast('Projeto salvo.');
    } catch (error) { toast(error.message, true); }
    button.disabled = false;
  });

  function download(blob, name) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = name; document.body.append(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
  }

  async function exportPng(overlay) {
    if (!state.coverId && !window.confirm('Não há imagem da notícia escolhida. Exportar assim mesmo?')) return;
    const out = document.createElement('canvas');
    out.width = 1080; out.height = 1920;
    await render(out, 1080, 1920, { overlay });
    out.toBlob((blob) => {
      if (!blob) { toast('Não foi possível gerar a imagem.', true); return; }
      download(blob, (overlay ? 'radar-overlay-' : 'radar-referencia-') + (state.projectId || 'novo') + '.png');
    }, 'image/png');
  }
  $('st-export-png').addEventListener('click', () => exportPng(false));
  $('st-export-overlay').addEventListener('click', () => exportPng(true));

  // Small hook for diagnostics and automated checks.
  window.RadarStudio = {
    state, data, render, redraw, hasImpact, sourceLine, layout: () => lastLayout,
    setSceneText(fns) { sceneTextFn = fns.scene; fullTextFn = fns.full; renderPrompts(); },
  };
  renderPickers(); words(); redraw();

  /** The article thumbnail has to be in the card: the source's cover is stored (with its credit) as soon as the Studio opens. */
  async function ensureCover() {
    if (!data.news || !data.news.image || data.media.some((m) => m.kind === 'cover')) return;
    $('st-cover-hint').textContent = 'Baixando a capa da notícia…';
    try {
      const result = await api({ action: 'import_cover', news_id: data.news.id });
      const media = result.media;
      data.media.unshift({ id: media.id, label: media.label, kind: media.kind, credit: media.credit });
      if (!state.coverId) state.coverId = media.id;
      renderPickers(); redraw();
    } catch (error) {
      renderPickers();
      $('st-cover-hint').textContent = 'Não consegui baixar a capa automaticamente (' + error.message + '). Use “Anexar print/imagem” abaixo.';
    }
  }
  ensureCover();
})();
