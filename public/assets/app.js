'use strict';

(() => {
    const byId = (id) => document.getElementById(id);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const state = { category: 'Geral', items: [], selected: null, character: null, covers: new Map(), controller: null, request: 0, generating: false };
    const buttons = [...document.querySelectorAll('[data-cat]')];
    const cards = byId('cards');
    const status = byId('status');
    let toastTimer;

    function node(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    }

    function externalUrl(value) {
        try {
            const url = new URL(value);
            if (url.protocol !== 'https:' || url.username || url.password) return null;
            return url.href;
        } catch { return null; }
    }

    function mediaUrl(value) {
        try {
            const url = new URL(value, location.href);
            if (url.origin === location.origin && url.pathname.endsWith('/media.php') && /^[a-f0-9]{32}\.(jpg|png|webp)$/.test(url.searchParams.get('id') || '')) return url.href;
        } catch { /* Reject invalid or unexpected media paths. */ }
        return null;
    }

    function safeImage(value) { return mediaUrl(value) || externalUrl(value); }

    function setLink(element, value) {
        const url = externalUrl(value);
        element.hidden = !url;
        if (url) element.href = url;
        else element.removeAttribute('href');
    }

    function readableDate(value, full = false) {
        if (!value) return 'Data não informada';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return 'Data não informada';
        return new Intl.DateTimeFormat('pt-BR', { timeZone: 'America/Sao_Paulo', day: '2-digit', month: 'short', ...(full ? { year: 'numeric', hour: '2-digit', minute: '2-digit' } : {}) }).format(date);
    }

    function toast(message) {
        clearTimeout(toastTimer);
        byId('toast').textContent = message;
        byId('toast').hidden = false;
        toastTimer = setTimeout(() => { byId('toast').hidden = true; }, 3800);
    }

    async function request(url, options = {}) {
        const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', ...options.headers } });
        if (response.status === 401) {
            location.assign('login.php');
            throw new Error('Sua sessão expirou. Entre novamente.');
        }
        let data;
        try { data = await response.json(); }
        catch { throw new Error('O servidor não retornou uma resposta válida. Tente novamente.'); }
        if (!response.ok) {
            const error = new Error(data.error || (response.status === 429 ? 'Muitas consultas. Aguarde um momento e tente novamente.' : 'Não foi possível concluir a consulta.'));
            error.data = data;
            throw error;
        }
        return data;
    }

    function createPlaceholder() {
        const placeholder = node('div', 'image-placeholder');
        const icon = node('span', '', '▧');
        icon.setAttribute('aria-hidden', 'true');
        placeholder.append(icon, node('small', '', 'Sem imagem fornecida'));
        return placeholder;
    }

    function imageFor(item) {
        const wrapper = node('div', 'news-image-wrap');
        const imageUrl = safeImage(item.image);
        const placeholder = createPlaceholder();
        wrapper.append(placeholder);
        if (imageUrl) {
            const img = node('img', 'news-image');
            img.alt = 'Imagem fornecida pelo feed da notícia';
            img.loading = 'lazy';
            img.referrerPolicy = 'no-referrer';
            img.decoding = 'async';
            img.addEventListener('load', () => { placeholder.hidden = true; img.hidden = false; }, { once: true });
            img.addEventListener('error', () => { img.remove(); placeholder.hidden = false; }, { once: true });
            img.hidden = true;
            img.src = imageUrl;
            wrapper.append(img);
            const note = node('span', 'image-source-note', 'Imagem do feed');
            img.addEventListener('error', () => note.remove(), { once: true });
            wrapper.append(note);
        }
        wrapper.append(node('span', 'card-category', item.category || state.category));
        return wrapper;
    }

    function createCard(item) {
        const card = node('article', 'news-card');
        if (state.selected?.id === item.id) card.classList.add('selected');
        const body = node('div', 'card-body');
        const sourceRow = node('div', 'source-row');
        const source = node('span', 'source', item.source || 'Fonte não informada');
        source.title = item.source || 'Fonte não informada';
        const date = node('time', '', readableDate(item.published));
        if (item.published && !Number.isNaN(new Date(item.published).getTime())) date.dateTime = item.published;
        date.title = readableDate(item.published, true) + ' · Brasília';
        sourceRow.append(source, date);
        const title = node('h3', '', item.title || 'Manchete sem título');
        const verification = node('span', 'verification', 'Checagem pendente');
        const rank = node('p', 'rank-note');
        if (Number.isFinite(Number(item.score))) {
            rank.append(node('strong', '', `Prioridade ${Math.round(Number(item.score))}/100`), document.createTextNode(' · critério editorial'));
            rank.title = item.rank_reason || 'Pontuação heurística editorial; não é um ranking oficial de tendências.';
        } else rank.textContent = 'Prioridade editorial · confira na fonte';
        const actions = node('div', 'card-actions');
        const link = node('a', 'source-link', 'Ler na fonte ↗');
        link.target = '_blank'; link.rel = 'noopener noreferrer';
        setLink(link, item.url);
        const choose = node('button', 'create-button', state.selected?.id === item.id ? 'Pauta selecionada ✓' : 'Criar react →');
        choose.type = 'button';
        choose.setAttribute('aria-label', 'Criar react: ' + item.title);
        choose.addEventListener('click', () => selectStory(item));
        actions.append(link, choose);
        body.append(sourceRow, title, verification, rank, actions);
        card.append(imageFor(item), body);
        return card;
    }

    function renderCards() {
        const query = byId('search').value.trim().toLocaleLowerCase('pt-BR');
        const items = state.items.filter((item) => `${item.title} ${item.source}`.toLocaleLowerCase('pt-BR').includes(query));
        const sort = byId('sort').value;
        items.sort((a, b) => sort === 'recent' ? (Date.parse(b.published) || 0) - (Date.parse(a.published) || 0) : (Number(b.score) || 0) - (Number(a.score) || 0));
        const fragment = document.createDocumentFragment();
        items.forEach((item) => fragment.append(createCard(item)));
        cards.replaceChildren(fragment);
        cards.setAttribute('aria-busy', 'false');
        byId('empty-state').hidden = items.length > 0;
        byId('empty-heading').textContent = query ? 'Nenhuma pauta para essa busca' : 'Nenhuma pauta disponível';
        byId('empty-message').textContent = query ? 'Tente outro termo ou escolha uma categoria diferente.' : 'O feed não retornou notícias. Tente atualizar o radar ou consultar outra categoria.';
        byId('empty-reset').hidden = !query;
        status.textContent = `${items.length} ${items.length === 1 ? 'pauta encontrada' : 'pautas encontradas'}${query ? ` para “${byId('search').value.trim()}”` : ''}`;
    }

    function displayFeed(data) {
        state.items = Array.isArray(data.items) ? data.items.filter((item) => item && typeof item.id === 'string' && typeof item.title === 'string' && externalUrl(item.url)) : [];
        byId('metric-count').textContent = state.items.length;
        const date = data.updated ? new Date(data.updated) : null;
        byId('metric-updated').textContent = date && !Number.isNaN(date.getTime()) ? new Intl.DateTimeFormat('pt-BR', { timeZone: 'America/Sao_Paulo', hour: '2-digit', minute: '2-digit' }).format(date) + ' · Brasília' : 'Sem coleta disponível';
        byId('metric-updated').title = readableDate(data.updated, true);
        const warnings = [];
        if (data.stale) warnings.push('A fonte está indisponível. Mostrando a última coleta salva, que pode estar desatualizada.');
        if (data.error) warnings.push(String(data.error));
        if (Array.isArray(data.warnings)) warnings.push(...data.warnings.map(String));
        byId('feed-warning').textContent = [...new Set(warnings)].join(' ');
        byId('feed-warning').hidden = !warnings.length;
        renderCards();
    }

    async function loadNews(category) {
        state.controller?.abort();
        state.controller = new AbortController();
        const requestId = ++state.request;
        state.category = category;
        buttons.forEach((button) => {
            const on = button.dataset.cat === category;
            button.classList.toggle('on', on);
            button.setAttribute('aria-pressed', String(on));
        });
        byId('category-caption').textContent = category;
        byId('refresh').disabled = true;
        status.textContent = `Consultando ${category}…`;
        byId('empty-state').hidden = true;
        byId('feed-warning').hidden = true;
        cards.setAttribute('aria-busy', 'true');
        const placeholders = [node('div', 'skeleton'), node('div', 'skeleton')];
        placeholders.forEach((element) => element.setAttribute('aria-hidden', 'true'));
        cards.replaceChildren(...placeholders);
        try {
            const data = await request('api.php?categoria=' + encodeURIComponent(category), { signal: state.controller.signal });
            if (requestId === state.request) displayFeed(data);
        } catch (error) {
            if (error.name === 'AbortError' || requestId !== state.request) return;
            state.items = [];
            cards.replaceChildren();
            cards.setAttribute('aria-busy', 'false');
            byId('metric-count').textContent = '—';
            byId('metric-updated').textContent = 'Coleta indisponível';
            status.textContent = 'Não foi possível consultar esta categoria.';
            byId('empty-state').hidden = false;
            byId('empty-heading').textContent = 'A fonte está indisponível';
            byId('empty-message').textContent = error.message;
            byId('empty-reset').hidden = true;
            toast('Não foi possível consultar as notícias. Você pode tentar outra categoria.');
        } finally {
            if (requestId === state.request) byId('refresh').disabled = false;
        }
    }

    async function loadTrends() {
        byId('reload-trends').disabled = true;
        byId('trends').replaceChildren(node('p', 'muted', 'Consultando a fonte…'));
        try {
            const data = await request('api.php?action=trends');
            const fragment = document.createDocumentFragment();
            if (data.stale) fragment.append(node('p', 'muted', 'Última coleta salva:'));
            const trends = Array.isArray(data.items) ? data.items.slice(0, 12) : [];
            trends.forEach((item) => {
                const url = externalUrl(item.url);
                if (!url || !item.title) return;
                const link = node('a', 'trend-chip', item.title);
                link.href = url; link.target = '_blank'; link.rel = 'noopener noreferrer';
                if (item.traffic) link.append(node('span', '', String(item.traffic)));
                fragment.append(link);
            });
            if (!fragment.childElementCount) fragment.append(node('p', 'muted', data.error || 'A fonte não retornou tendências neste momento.'));
            byId('trends').replaceChildren(fragment);
        } catch (error) {
            byId('trends').replaceChildren(node('p', 'muted', 'Fonte indisponível. As tendências aparecerão quando a consulta for restabelecida.'));
            byId('trends').title = error.message;
        } finally { byId('reload-trends').disabled = false; }
    }

    function invalidateScript() {
        byId('script-result').hidden = true;
        ['headline', 'script', 'prompt'].forEach((id) => { byId(id).value = ''; });
        byId('generation-status').textContent = '';
        byId('generate').disabled = !state.selected || state.generating;
    }

    function selectStory(item) {
        if (state.generating) { toast('Aguarde a geração do roteiro antes de trocar a pauta.'); return; }
        state.selected = item;
        invalidateScript();
        byId('selected-empty').hidden = true;
        byId('selected-story').hidden = false;
        byId('selected-title').textContent = item.title;
        setLink(byId('selected-source'), item.url);
        byId('context').value = '';
        byId('generate').disabled = false;
        byId('open-preview').disabled = false;
        renderCards();
        updatePreview();
        if (matchMedia('(max-width: 720px)').matches) byId('studio').scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
        toast('Pauta selecionada. Confira a fonte e prepare seu roteiro.');
    }

    byId('script-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!state.selected || state.generating) return;
        if (new TextEncoder().encode(byId('context').value.trim()).length > 2000) {
            byId('generation-status').textContent = 'O contexto está muito longo. Resuma os fatos principais antes de gerar.';
            byId('context').focus(); return;
        }
        state.generating = true;
        byId('generate').disabled = true;
        byId('generation-status').textContent = 'Preparando seu rascunho editorial…';
        byId('script-result').hidden = true;
        const controls = ['duration', 'tone', 'context'].map(byId);
        controls.forEach((element) => { element.disabled = true; });
        try {
            const result = await request('api.php?action=script', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ id: state.selected.id, category: state.selected.category, duration: Number(byId('duration').value), tone: byId('tone').value, verified_context: byId('context').value.trim() }),
            });
            ['headline', 'script', 'prompt'].forEach((id) => { byId(id).value = typeof result[id] === 'string' ? result[id] : ''; });
            byId('script-caution').textContent = result.caution || 'Revise os fatos na fonte antes de gravar. Separe sua opinião da informação.';
            byId('script-result').hidden = false;
            byId('generation-status').textContent = 'Rascunho pronto. Revise antes de gravar.';
        } catch (error) { byId('generation-status').textContent = error.message; }
        finally {
            state.generating = false;
            byId('generate').disabled = !state.selected;
            controls.forEach((element) => { element.disabled = false; });
        }
    });

    ['duration', 'tone', 'context'].forEach((id) => byId(id).addEventListener('input', invalidateScript));
    document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
        const textarea = byId(button.dataset.copy);
        if (!textarea?.value) return;
        try {
            if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
            await navigator.clipboard.writeText(textarea.value);
            toast(button.dataset.copy === 'prompt' ? 'Prompt copiado!' : 'Texto copiado!');
        } catch {
            textarea.closest('details')?.setAttribute('open', '');
            textarea.focus(); textarea.select();
            toast('Texto selecionado. Use a opção Copiar do seu dispositivo.');
        }
    }));

    function updatePreview() {
        if (!state.selected) return;
        const item = state.selected;
        byId('preview-title').textContent = item.title;
        setLink(byId('preview-source'), item.url);
        byId('preview-source').textContent = (item.source || 'Abrir notícia na fonte') + ' ↗';
        setLink(byId('preview-original'), item.url);
        const cover = state.covers.get(item.id);
        const coverUrl = safeImage(cover || item.image);
        byId('preview-cover').hidden = !coverUrl;
        byId('preview-cover-empty').hidden = !!coverUrl;
        byId('clear-cover').hidden = !cover;
        if (coverUrl) {
            byId('preview-cover').referrerPolicy = 'no-referrer';
            byId('preview-cover').alt = cover ? 'Imagem real enviada manualmente para a pauta' : 'Imagem fornecida pelo feed da pauta';
            byId('preview-cover').src = coverUrl;
        } else byId('preview-cover').removeAttribute('src');
        byId('character-image').src = mediaUrl(state.character) || 'assets/character-placeholder.svg';
        byId('character-image').alt = state.character ? 'Imagem original do personagem enviada pelo administrador' : 'Espaço reservado para a imagem do seu personagem';
        byId('character-label').textContent = state.character ? 'Seu personagem · imagem enviada' : 'Seu personagem aparece aqui';
    }

    byId('preview-cover').addEventListener('error', () => { byId('preview-cover').hidden = true; byId('preview-cover-empty').hidden = false; });
    byId('character-image').addEventListener('error', () => {
        if (!state.character) return;
        state.character = null;
        byId('character-image').src = 'assets/character-placeholder.svg';
        byId('character-label').textContent = 'Imagem indisponível. Envie seu personagem novamente.';
    });
    byId('open-preview').addEventListener('click', () => {
        updatePreview();
        byId('upload-status').textContent = '';
        byId('preview-dialog').showModal();
    });
    byId('close-preview').addEventListener('click', () => byId('preview-dialog').close());
    byId('preview-dialog').addEventListener('click', (event) => {
        if (event.target === byId('preview-dialog')) {
            const rect = byId('preview-dialog').getBoundingClientRect();
            if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) byId('preview-dialog').close();
        }
    });
    byId('clear-cover').addEventListener('click', () => {
        if (state.selected) state.covers.delete(state.selected.id);
        byId('cover-file').value = '';
        updatePreview();
    });

    async function uploadImage(input, kind) {
        const file = input.files?.[0];
        if (!file || (kind === 'cover' && !state.selected)) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024 || file.size === 0) {
            byId('upload-status').textContent = 'Escolha uma imagem JPEG, PNG ou WebP de até 5 MB.';
            input.value = ''; return;
        }
        const storyId = state.selected?.id;
        const body = new FormData();
        body.append('file', file); body.append('kind', kind); body.append('csrf', csrf);
        byId('cover-file').disabled = true;
        byId('character-file').disabled = true;
        byId('upload-status').textContent = 'Verificando e enviando sua imagem…';
        try {
            const result = await request('upload.php', { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body });
            const url = mediaUrl(result.url);
            if (!url) throw new Error('O servidor não retornou uma imagem válida.');
            if (kind === 'character') state.character = url;
            else state.covers.set(storyId, url);
            updatePreview();
            byId('upload-status').textContent = kind === 'character' ? 'Personagem salvo. Ele estará disponível nas próximas prévias.' : 'Imagem adicionada à prévia desta pauta.';
        } catch (error) { byId('upload-status').textContent = error.message; }
        finally { input.value = ''; byId('cover-file').disabled = false; byId('character-file').disabled = false; }
    }

    byId('cover-file').addEventListener('change', (event) => uploadImage(event.target, 'cover'));
    byId('character-file').addEventListener('change', (event) => uploadImage(event.target, 'character'));
    async function restoreCharacter() {
        try { const result = await request('upload.php?kind=character'); state.character = mediaUrl(result.url); updatePreview(); }
        catch { /* An absent optional character must not block news curation. */ }
    }
    buttons.forEach((button) => button.addEventListener('click', () => loadNews(button.dataset.cat)));
    byId('refresh').addEventListener('click', () => loadNews(state.category));
    byId('reload-trends').addEventListener('click', loadTrends);
    byId('search').addEventListener('input', renderCards);
    byId('sort').addEventListener('change', renderCards);
    byId('empty-reset').addEventListener('click', () => { byId('search').value = ''; renderCards(); byId('search').focus(); });
    loadNews(state.category);
    loadTrends();
    restoreCharacter();
})();
