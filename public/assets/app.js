/* Radar Viral BR — shared behaviour. No inline scripts (CSP: script-src 'self'). */
(() => {
  'use strict';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

  /** POST JSON (or FormData) to api.php. Rejects with a readable message. */
  async function api(payload) {
    const isForm = payload instanceof FormData;
    const response = await fetch('api.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: isForm ? { 'X-CSRF-Token': csrf } : { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: isForm ? payload : JSON.stringify(payload),
    });
    let data = null;
    try { data = await response.json(); } catch (_) { /* non-JSON error page */ }
    if (response.status === 401) { location.href = 'login.php'; throw new Error('Sessão expirada.'); }
    if (!response.ok) {
      const error = new Error((data && data.error) || `Erro ${response.status}`);
      error.status = response.status;
      error.data = data;
      throw error;
    }
    return data || {};
  }
  window.RadarApi = api;

  let toastTimer = 0;
  function toast(message, isError = false) {
    const el = $('#toast');
    if (!el) return;
    el.textContent = message;
    el.style.borderColor = isError ? 'var(--red)' : 'var(--border)';
    el.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { el.hidden = true; }, isError ? 7000 : 3500);
  }
  window.RadarToast = toast;

  function setBusy(button, busy, label) {
    if (!button) return;
    if (busy) {
      button.dataset.originalHtml = button.innerHTML;
      button.disabled = true;
      button.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>' + (label || 'Aguarde…') + '</span>';
    } else {
      button.disabled = false;
      if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
    }
  }

  /* ---- mobile menu ---- */
  const menuBtn = $('#menu-btn');
  const scrim = $('#scrim');
  function setMenu(open) {
    document.body.classList.toggle('menu-open', open);
    if (scrim) scrim.hidden = !open;
    if (menuBtn) menuBtn.setAttribute('aria-expanded', String(open));
  }
  menuBtn?.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-open')));
  scrim?.addEventListener('click', () => setMenu(false));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setMenu(false); });

  /* ---- collection ---- */
  const live = $('#live-status');
  async function collect(trigger, button) {
    setBusy(button, true, 'Coletando…');
    if (live) { live.hidden = false; live.textContent = 'Buscando notícias nas fontes… isso leva alguns segundos.'; }
    try {
      const result = await api({ action: 'collect', trigger });
      if (result.status === 'fresh') { if (live) live.hidden = true; return; }
      if (live) live.textContent = result.message || 'Coleta concluída.';
      sessionStorage.setItem('rvb-auto', String(Date.now()));
      setTimeout(() => location.reload(), 900);
    } catch (error) {
      if (live) { live.className = 'notice error'; live.textContent = 'Não foi possível atualizar: ' + error.message; }
      toast(error.message, true);
    } finally {
      setBusy(button, false);
    }
  }
  $('#collect-btn')?.addEventListener('click', (event) => collect('manual', event.currentTarget));
  if ($('meta[name="auto-collect"]')?.content === '1') {
    const last = Number(sessionStorage.getItem('rvb-auto') || 0);
    if (Date.now() - last > 10 * 60 * 1000) {
      sessionStorage.setItem('rvb-auto', String(Date.now()));
      collect('auto', null);
    }
  }

  /* ---- generate react dialog ---- */
  const dialog = $('#react-dialog');
  let reactNewsId = 0;
  function openReact(newsId, title) {
    if (!dialog) return;
    reactNewsId = Number(newsId);
    $('#react-news-title').textContent = title || '';
    $('#react-error').hidden = true;
    if ($('#react-duplicates')) $('#react-duplicates').hidden = true;
    syncReactCharacter();
    dialog.showModal();
    $('#react-character')?.focus();
  }
  /** A TV character always makes three scenes: the number of scenes is not a choice for her. */
  function syncReactCharacter() {
    const select = $('#react-character');
    const tv = select ? select.selectedOptions[0]?.dataset.format === 'tv3' : false;
    if ($('#react-scenes')) $('#react-scenes').disabled = tv;
    if ($('#react-tv-hint')) $('#react-tv-hint').hidden = !tv;
  }
  $('#react-character')?.addEventListener('change', syncReactCharacter);
  window.RadarOpenReact = openReact;
  dialog?.addEventListener('click', (event) => {
    if (event.target === dialog || event.target.closest('[data-close]')) dialog.close();
  });
  /** A react already exists for this news: show it and let the user decide (open it, generate anyway, or generate an update). */
  function showDuplicates(error) {
    const box = $('#react-duplicates');
    if (!box) return false;
    box.replaceChildren();
    const message = document.createElement('p');
    message.textContent = error.message;
    box.append(message);
    for (const match of error.data.duplicates || []) {
      const row = document.createElement('p');
      const link = document.createElement('a');
      link.href = 'roteiro.php?id=' + match.script_id;
      link.textContent = 'Abrir o roteiro #' + match.script_id + ' (' + match.character_name + ')';
      row.append(link, ' · ' + match.type_label + (match.is_update ? ' (atualização)' : ''));
      const update = document.createElement('button');
      update.type = 'button';
      update.className = 'btn ghost small';
      update.textContent = 'Gerar como atualização';
      update.addEventListener('click', () => {
        if (!$('#react-context').value.trim() && !window.confirm('Sem o fato novo no campo de contexto, só gero a atualização se eu achar algo novo na matéria. Continuar?')) { $('#react-context').focus(); return; }
        submitReact({ update_of: match.script_id });
      });
      row.append(' ', update);
      box.append(row);
    }
    const again = document.createElement('button');
    again.type = 'button';
    again.className = 'btn small';
    again.textContent = 'Gerar mesmo assim';
    again.addEventListener('click', () => submitReact({ allow_duplicate: true }));
    box.append(again);
    box.hidden = false;
    return true;
  }
  async function submitReact(extra = {}) {
    const submit = $('#react-submit');
    setBusy(submit, true, 'Gerando…');
    $('#react-error').hidden = true;
    if ($('#react-duplicates')) $('#react-duplicates').hidden = true;
    try {
      const result = await api({
        action: 'generate',
        news_id: reactNewsId,
        scenes: Number($('#react-scenes').value),
        tone: $('#react-tone').value,
        layout: $('#react-layout')?.value || '',
        character: $('#react-character')?.value || '',
        context: $('#react-context').value,
        ...extra,
      });
      location.href = 'estudio.php?news=' + reactNewsId + '&roteiro=' + result.item.id;
    } catch (error) {
      if (error.status === 409 && error.data && error.data.duplicates && showDuplicates(error)) { setBusy(submit, false); return; }
      const box = $('#react-error');
      box.textContent = error.message;
      box.hidden = false;
      setBusy(submit, false);
    }
  }
  $('#react-form')?.addEventListener('submit', (event) => {
    event.preventDefault();
    submitReact();
  });

  /* ---- delegated actions ---- */
  document.addEventListener('click', async (event) => {
    const react = event.target.closest('[data-react]');
    if (react) { openReact(react.dataset.react, react.dataset.title); return; }

    const save = event.target.closest('[data-save]');
    if (save) {
      const wantSaved = save.getAttribute('aria-pressed') !== 'true';
      save.disabled = true;
      try {
        await api({ action: 'save_news', id: Number(save.dataset.save), saved: wantSaved });
        save.setAttribute('aria-pressed', String(wantSaved));
        save.textContent = wantSaved ? 'Salva' : 'Salvar';
        toast(wantSaved ? 'Notícia salva.' : 'Notícia removida das salvas.');
      } catch (error) { toast(error.message, true); }
      save.disabled = false;
      return;
    }

    const copy = event.target.closest('[data-copy]');
    if (copy) {
      const target = document.getElementById(copy.dataset.copy);
      const text = target ? (target.value ?? target.textContent) : (copy.dataset.copyText || '');
      try {
        await navigator.clipboard.writeText(text);
      } catch (_) {
        if (target && target.select) { target.select(); document.execCommand('copy'); }
        else window.prompt('Copie o texto:', text);
      }
      toast('Copiado!');
      return;
    }

    const act = event.target.closest('[data-act]');
    if (act) {
      if (act.dataset.confirm && !window.confirm(act.dataset.confirm)) return;
      const body = { action: act.dataset.act };
      for (const [key, value] of Object.entries(act.dataset)) {
        if (['act', 'confirm', 'reload', 'redirect', 'originalHtml', 'busy'].includes(key)) continue;
        body[key.replace(/[A-Z]/g, (c) => '_' + c.toLowerCase())] = /^\d+$/.test(value) ? Number(value) : value;
      }
      setBusy(act, true, act.dataset.busy || 'Aguarde…');
      try {
        const result = await api(body);
        toast(result.message || 'Pronto.', result.ok === false);
        if (act.dataset.redirect) { location.href = act.dataset.redirect.replace('{id}', result.item?.id ?? ''); return; }
        if (act.dataset.reload !== 'no' && result.ok !== false && body.action !== 'source_test' && body.action !== 'ai_test') setTimeout(() => location.reload(), 600);
      } catch (error) { toast(error.message, true); }
      setBusy(act, false);
    }
  });

  /* ---- confirm on destructive forms ---- */
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) event.preventDefault();
  });

  /* ---- upload forms (multipart through the API) ---- */
  $$('form[data-upload]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const file = $('input[type="file"]', form);
      if (!file?.files?.length) { toast('Escolha um arquivo primeiro.', true); return; }
      const limit = Number(form.dataset.max || 5 * 1024 * 1024);
      if (file.files[0].size > limit) { toast((form.dataset.maxLabel || 'A imagem deve ter até 5 MB.'), true); return; }
      const data = new FormData(form);
      data.set('action', 'upload');
      const button = $('button[type="submit"]', form);
      setBusy(button, true, 'Enviando…');
      try {
        await api(data);
        toast('Enviado.');
        setTimeout(() => location.reload(), 500);
      } catch (error) { toast(error.message, true); setBusy(button, false); }
    });
  });
})();
