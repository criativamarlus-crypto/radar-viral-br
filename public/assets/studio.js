/* Radar Viral BR — Estúdio de React. The preview and the PNG export use the same draw routine. */
(() => {
  'use strict';
  const dataEl = document.getElementById('studio-data');
  if (!dataEl) return;
  const data = JSON.parse(dataEl.textContent);
  const $ = (id) => document.getElementById(id);
  const api = window.RadarApi;
  const toast = window.RadarToast || (() => {});

  const project = data.project;
  const state = {
    newsId: data.news ? data.news.id : null,
    scriptId: data.scriptId,
    projectId: project ? project.id : null,
    title: project ? project.title : '',
    headline: project ? project.headline : (data.news ? data.news.title : ''),
    spoken: project ? project.script : '',
    expression: project ? project.expression : 'sorriso-largo',
    scene: 'carro',
    duration: 25,
    coverId: project ? project.cover_media_id : (data.preselectCover || null),
    characterId: project ? project.character_media_id : null,
    x: project ? project.cover_x : 50,
    y: project ? project.cover_y : 50,
    zoom: project ? project.cover_zoom : 1,
    prompt: project ? project.video_prompt : '',
  };
  if (!state.characterId) {
    const primary = data.characters.find((c) => c.primary) || data.characters[0];
    state.characterId = primary ? primary.id : null;
  }
  if (!state.coverId && data.media.length) state.coverId = data.media[0].id;

  const canvas = $('st-canvas');
  const ctx = canvas.getContext('2d');
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

  function drawCover(context, img, x, y, w, h) {
    // "cover" fit with user zoom and anchor: identical maths for preview and export.
    const base = Math.max(w / img.width, h / img.height);
    const scale = base * state.zoom;
    const dw = img.width * scale;
    const dh = img.height * scale;
    const dx = x + (w - dw) * (state.x / 100);
    const dy = y + (h - dh) * (state.y / 100);
    context.drawImage(img, dx, dy, dw, dh);
  }

  async function render(target, W, H) {
    const c = target.getContext('2d');
    const half = H / 2;
    const [cover, character] = await Promise.all([loadImage(state.coverId), loadImage(state.characterId)]);
    c.fillStyle = '#0b0c10';
    c.fillRect(0, 0, W, H);

    // top: news image
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

    // bottom: character
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

  let drawTimer = 0;
  function redraw() {
    cancelAnimationFrame(drawTimer);
    drawTimer = requestAnimationFrame(() => render(canvas, canvas.width, canvas.height));
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
  newsSelect.addEventListener('change', () => { if (newsSelect.value) location.href = 'estudio.php?news=' + encodeURIComponent(newsSelect.value); });

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
    for (const c of data.characters) {
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
    $('st-char-hint').textContent = data.characters.length ? '' : 'Nenhuma imagem do personagem. Cadastre em “Meu Personagem”.';
  }

  function words() {
    const n = (state.spoken.match(/[\p{L}\p{N}]+/gu) || []).length;
    $('st-words').textContent = n + ' palavras · ~' + Math.round(n / 2.5) + ' s falados';
  }

  let promptTimer = 0;
  function refreshPrompt() {
    clearTimeout(promptTimer);
    promptTimer = setTimeout(async () => {
      try {
        const result = await api({
          action: 'video_prompt', spoken: state.spoken, expression: state.expression, scene: state.scene,
          duration: state.duration, headline: state.headline, source: data.news ? data.news.source : '',
        });
        state.prompt = result.prompt;
        $('st-prompt').value = state.prompt;
      } catch (_) { /* keep the previous prompt */ }
    }, 450);
  }

  function bindText(id, key, after) {
    const el = $(id);
    el.value = state[key] ?? '';
    el.addEventListener('input', () => { state[key] = el.value; after && after(); });
  }
  bindText('st-title', 'title');
  bindText('st-headline', 'headline', () => redraw());
  bindText('st-script', 'spoken', () => { words(); refreshPrompt(); });
  $('st-expression').addEventListener('change', (e) => { state.expression = e.target.value; refreshPrompt(); });
  $('st-scene').addEventListener('change', (e) => { state.scene = e.target.value; refreshPrompt(); });
  for (const [id, key] of [['st-x', 'x'], ['st-y', 'y'], ['st-zoom', 'zoom']]) {
    const el = $(id);
    el.value = state[key];
    el.addEventListener('input', () => { state[key] = Number(el.value); redraw(); });
  }

  scriptSelect.addEventListener('change', () => {
    const script = data.scripts.find((s) => String(s.id) === scriptSelect.value);
    state.scriptId = script ? script.id : null;
    if (!script) return;
    state.headline = script.headline; state.spoken = script.spoken; state.expression = script.expression;
    state.scene = script.scene; state.duration = script.duration; state.prompt = script.video_prompt;
    $('st-headline').value = state.headline; $('st-script').value = state.spoken;
    $('st-expression').value = state.expression; $('st-scene').value = state.scene; $('st-prompt').value = state.prompt;
    if (!state.title) { state.title = script.headline.slice(0, 80); $('st-title').value = state.title; }
    words(); redraw();
  });

  // Initial values from a chosen script (when opened from "Abrir no Estúdio").
  if (!project && state.scriptId) {
    const script = data.scripts.find((s) => s.id === state.scriptId);
    if (script) {
      state.headline = script.headline; state.spoken = script.spoken; state.expression = script.expression;
      state.scene = script.scene; state.duration = script.duration; state.prompt = script.video_prompt;
      state.title = script.headline.slice(0, 80);
      $('st-title').value = state.title; $('st-headline').value = state.headline; $('st-script').value = state.spoken;
      $('st-expression').value = state.expression; $('st-scene').value = state.scene;
    }
  }
  $('st-prompt').value = state.prompt;
  if (!state.prompt && state.spoken) refreshPrompt();

  /* ---------- save / export ---------- */
  $('st-save').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
      const result = await api({
        action: 'project_save', id: state.projectId || 0, script_id: state.scriptId || 0, news_id: state.newsId || 0,
        title: state.title, headline: state.headline, script: state.spoken, video_prompt: state.prompt,
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

  $('st-export-png').addEventListener('click', async () => {
    if (!state.coverId && !window.confirm('Não há imagem da notícia escolhida. Exportar assim mesmo?')) return;
    const out = document.createElement('canvas');
    out.width = 1080; out.height = 1920;
    await render(out, 1080, 1920);
    out.toBlob((blob) => {
      if (!blob) { toast('Não foi possível gerar a imagem.', true); return; }
      download(blob, 'radar-referencia-' + (state.projectId || 'novo') + '.png');
    }, 'image/png');
  });

  $('st-export-txt').addEventListener('click', () => {
    const lines = [
      'RADAR VIRAL BR · PROJETO DE REACT', '='.repeat(40),
      'Título: ' + (state.title || '—'),
      data.news ? 'Notícia: ' + data.news.title : '', data.news ? 'Fonte: ' + data.news.source + ' — ' + data.news.url : '',
      '', 'HEADLINE NA IMAGEM', state.headline, '', 'ROTEIRO FALADO', state.spoken, '', 'PROMPT DE VÍDEO', state.prompt, '',
    ].filter((l, i, a) => l !== '' || a[i - 1] !== '');
    download(new Blob([lines.join('\n') + '\n'], { type: 'text/plain;charset=utf-8' }), 'radar-roteiro-' + (state.projectId || 'novo') + '.txt');
  });

  renderPickers(); words(); redraw();
})();
