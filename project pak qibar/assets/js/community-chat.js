document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-community]');
  if (!root) return;
  const csrf = root.dataset.csrf;
  const feedback = root.querySelector('[data-group-feedback]');
  const report = (message = '') => { feedback.textContent = message; feedback.hidden = !message; };
  const request = async (url, data) => {
    const options = { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'fetch' } };
    if (data) {
      options.method = 'POST';
      const body = data instanceof FormData ? data : new URLSearchParams(data);
      body.set('csrf', csrf);
      options.body = body;
    }
    const response = await fetch(url, options);
    if (!response.headers.get('Content-Type')?.includes('application/json')) {
      throw new Error(response.status === 400 ? 'Form kedaluwarsa. Muat ulang halaman dan coba lagi.' : 'Sesi akun berakhir. Silakan masuk kembali.');
    }
    const result = await response.json();
    if (!response.ok || result.error) throw new Error(result.error || 'Permintaan gagal. Coba kembali.');
    return result;
  };
  const group = window.MotoraGroup = { root, request, report, state: null, uid: Number(root.dataset.userId) };
  const form = root.querySelector('[data-group-compose]');
  const textarea = form.elements.body;
  const attachment = form.elements.attachment;
  const items = root.querySelector('[data-group-items]');
  const list = root.querySelector('[data-group-messages]');
  const search = root.querySelector('[data-group-search]');
  const loadMore = root.querySelector('[data-load-more]');
  const dialog = document.querySelector('[data-message-dialog]');
  const messages = new Map();
  let replyId = 0;
  let previewUrl = null;
  let initial = true;
  let searching = '';
  let searchTimer;
  let refreshRunning = false;
  let previousSignature = '';
  const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };
  const choose = (title, content, confirm = 'Simpan') => new Promise((resolve) => {
    if (dialog.open) return resolve(null);
    dialog.querySelector('[data-dialog-title]').textContent = title;
    dialog.querySelector('[data-dialog-content]').replaceChildren(content);
    dialog.querySelector('[value="confirm"]').textContent = confirm;
    dialog.returnValue = 'cancel';
    dialog.addEventListener('close', () => resolve(dialog.returnValue === 'confirm' ? content : null), { once: true });
    dialog.showModal();
  });
  const action = async (name, fields) => {
    const result = await request(root.dataset.chatUrl, { chat_action: name, ...fields });
    report();
    await refresh(true);
    return result;
  };
  const jump = async (id) => {
    let target = items.querySelector(`[data-message-id="${Number(id)}"]`);
    if (!target) {
      const state = await request(root.dataset.chatUrl + '&before=' + (Number(id) + 1));
      state.messages.forEach((message) => messages.set(message.id, message));
      render(true);
      target = items.querySelector(`[data-message-id="${Number(id)}"]`);
    }
    target?.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
  };
  const cancelReply = () => { replyId = 0; root.querySelector('[data-reply-draft]').hidden = true; };
  const clearPhoto = () => {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = null;
    attachment.value = '';
    root.querySelector('[data-attachment-preview]').hidden = true;
  };
  const addAction = (container, label, callback) => {
    const button = element('button', '', label); button.type = 'button';
    button.addEventListener('click', async () => { try { await callback(); } catch (error) { report(error.message); } });
    container.append(button);
  };
  const render = (force = false) => {
    const sorted = [...messages.values()].sort((a, b) => a.id - b.id);
    const signature = JSON.stringify(sorted);
    if (!force && signature === previousSignature) return;
    previousSignature = signature;
    const atBottom = list.scrollHeight - list.scrollTop - list.clientHeight < 100;
    const previousTop = list.scrollTop;
    const nodes = [];
    let previousDate = '';
    sorted.forEach((message) => {
      if (message.date !== previousDate) { nodes.push(element('div', 'group-date', message.date)); previousDate = message.date; }
      if (message.kind === 'system') { nodes.push(element('p', 'group-system', message.body)); return; }
      const article = element('article', 'group-message' + (message.mine ? ' mine' : '') + (message.deleted ? ' deleted' : ''));
      article.dataset.messageId = message.id;
      const bubble = element('div', 'group-message-bubble');
      const sender = element('span', 'group-sender', message.mine ? 'Anda' : message.name);
      sender.append(element('small', '', message.role)); bubble.append(sender);
      if (message.reply && !message.deleted) {
        const quote = element('button', 'group-reply'); quote.type = 'button';
        quote.append(element('b', '', message.reply.name), document.createTextNode(message.reply.body));
        quote.addEventListener('click', () => jump(message.reply.id).catch((error) => report(error.message))); bubble.append(quote);
      }
      if (message.attachment) {
        const link = element('a'); link.href = message.attachment; link.target = '_blank'; link.rel = 'noopener';
        const photo = element('img', 'group-message-photo'); photo.src = message.attachment; photo.alt = 'Foto dari ' + message.name; photo.loading = 'lazy';
        photo.addEventListener('load', () => { if (atBottom) list.scrollTop = list.scrollHeight; }, { once: true });
        link.append(photo); bubble.append(link);
      }
      bubble.append(element('p', 'group-message-body', message.body));
      const meta = element('div', 'group-message-meta');
      if (message.pinned) meta.append(element('span', '', 'Disematkan'));
      if (message.edited) meta.append(element('span', '', 'Diedit'));
      meta.append(element('span', '', message.time));
      if (message.mine && !message.deleted) {
        const allRead = message.recipient_count > 0 && message.read_count >= message.recipient_count;
        const checks = element('span', allRead ? 'read' : '', allRead ? '✓✓' : '✓');
        checks.title = `Dibaca ${message.read_count} dari ${message.recipient_count} anggota`; checks.setAttribute('aria-label', checks.title); meta.append(checks);
      }
      bubble.append(meta); article.append(bubble);
      if (message.reactions.length) {
        const reactions = element('div', 'group-reactions');
        message.reactions.forEach((reaction) => addAction(reactions, `${reaction.emoji} ${reaction.total}`, () => action('react', { message_id: message.id, emoji: reaction.emoji })));
        message.reactions.forEach((reaction, index) => { if (Number(reaction.mine)) reactions.children[index].classList.add('mine'); });
        article.append(reactions);
      }
      if (!group.state?.closed) {
        const menu = element('details', 'group-message-actions'); menu.append(element('summary', '', '•••'));
        const options = element('div', 'group-action-options');
        if (!message.deleted) {
          if (group.state?.can_send) addAction(options, 'Balas', () => {
            replyId = message.id;
            const draft = root.querySelector('[data-reply-draft]'); draft.querySelector('span').textContent = `${message.name}: ${message.body || 'Foto'}`; draft.hidden = false; textarea.focus();
          });
          ['👍', '❤️', '😂', '😮', '😢', '🙏'].forEach((emoji) => addAction(options, emoji, () => action('react', { message_id: message.id, emoji })));
          if (message.can_edit) addAction(options, 'Edit', async () => {
            const input = element('textarea'); input.value = message.body; input.maxLength = 4000; input.setAttribute('aria-label', 'Edit isi pesan');
            if (await choose('Edit pesan', input)) await action('edit', { message_id: message.id, body: input.value });
          });
          if (message.can_delete) addAction(options, 'Hapus untuk semua', async () => {
            if (await choose('Hapus untuk semua?', element('p', '', 'Pesan akan diganti dengan keterangan dihapus untuk seluruh anggota.'), 'Hapus')) await action('delete', { message_id: message.id });
          });
          if (group.state?.can_pin) addAction(options, message.pinned ? 'Lepas sematan' : 'Sematkan', async () => {
            if (message.pinned) return action('unpin', { message_id: message.id });
            const select = element('select'); select.setAttribute('aria-label', 'Durasi sematan');
            [[24, '24 jam'], [168, '7 hari'], [720, '30 hari']].forEach(([value, label]) => { const option = element('option', '', label); option.value = value; option.selected = value === 168; select.append(option); });
            if (await choose('Sematkan pesan', select)) await action('pin', { message_id: message.id, hours: select.value });
          });
        }
        addAction(options, 'Hapus untuk saya', async () => { await action('hide', { message_id: message.id }); messages.delete(message.id); render(true); });
        menu.append(options); article.append(menu);
      }
      nodes.push(article);
    });
    if (!sorted.length) nodes.push(element('p', 'group-empty', searching ? 'Tidak ada pesan yang cocok.' : 'Mulai percakapan dengan komunitasmu.'));
    items.replaceChildren(...nodes);
    if (initial || atBottom) list.scrollTop = list.scrollHeight;
    else list.scrollTop = previousTop;
    initial = false;
  };
  const refresh = group.refresh = async (force = false) => {
    if (refreshRunning && !force) return;
    refreshRunning = true;
    try {
      const state = await request(root.dataset.chatUrl + (searching ? '&q=' + encodeURIComponent(searching) : ''));
      group.state = state;
      if (searching || force) messages.clear();
      state.messages.forEach((message) => messages.set(message.id, message));
      loadMore.hidden = !state.has_more || !!searching;
      form.querySelectorAll('textarea,input,button').forEach((control) => { control.disabled = !state.can_send; });
      root.querySelector('[data-group-restriction]').hidden = state.can_send;
      root.querySelector('[data-group-restriction]').textContent = state.closed ? 'Komunitas ditutup. Riwayat masih dapat dibaca.' : 'Hanya leader dan admin yang dapat mengirim pesan.';
      root.querySelectorAll('[data-call-start]').forEach((button) => { button.hidden = !state.can_start_call; });
      const pins = root.querySelector('[data-group-pins]');
      pins.replaceChildren(...state.pins.map((pin) => { const button = element('button', '', '⌖ ' + (pin.body || 'Foto')); button.type = 'button'; button.addEventListener('click', () => jump(pin.id).catch((error) => report(error.message))); return button; }));
      pins.hidden = state.pins.length === 0;
      render(force);
      window.dispatchEvent(new CustomEvent('community:state', { detail: state }));
    } catch (error) {
      report(error.message);
      if (/anggota aktif|aksesnya terbatas/.test(error.message)) {
        form.querySelectorAll('textarea,input,button').forEach((control) => { control.disabled = true; });
        items.replaceChildren(element('p', 'group-empty', 'Akses komunitas Anda sudah berakhir.'));
        window.dispatchEvent(new Event('community:access-lost'));
      }
    } finally { refreshRunning = false; }
  };
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('[type="submit"]'); if (button.disabled) return;
    button.disabled = true;
    try {
      const data = new FormData(form); data.set('chat_action', 'send'); if (replyId) data.set('reply_to', replyId);
      await request(root.dataset.chatUrl, data); textarea.value = ''; textarea.style.height = ''; clearPhoto(); cancelReply(); report(); initial = true; searching = ''; search.value = ''; await refresh(true);
    } catch (error) { report(error.message); } finally { button.disabled = !group.state?.can_send; }
  });
  textarea.addEventListener('keydown', (event) => { if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); form.requestSubmit(); } });
  textarea.addEventListener('input', () => { textarea.style.height = 'auto'; textarea.style.height = Math.min(textarea.scrollHeight, 105) + 'px'; });
  attachment.addEventListener('change', () => {
    const file = attachment.files[0];
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = null;
    if (!file) return clearPhoto();
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) { clearPhoto(); return report('Pilih foto JPG, PNG, atau WebP maksimal 5 MB.'); }
    previewUrl = URL.createObjectURL(file);
    const preview = root.querySelector('[data-attachment-preview]'); preview.querySelector('img').src = previewUrl; preview.querySelector('span').textContent = file.name; preview.hidden = false; report();
  });
  root.querySelector('[data-reply-cancel]').addEventListener('click', cancelReply);
  root.querySelector('[data-attachment-cancel]').addEventListener('click', clearPhoto);
  search.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { searching = search.value.trim(); messages.clear(); initial = true; refresh(true); }, 300); });
  loadMore.addEventListener('click', async () => {
    try {
      loadMore.disabled = true;
      const height = list.scrollHeight; const top = list.scrollTop;
      const before = Math.min(...messages.keys());
      const state = await request(root.dataset.chatUrl + '&before=' + before);
      state.messages.forEach((message) => messages.set(message.id, message)); render(true);
      list.scrollTop = top + list.scrollHeight - height; loadMore.hidden = !state.has_more;
    } catch (error) { report(error.message); } finally { loadMore.disabled = false; }
  });
  const timer = window.setInterval(() => { if (!document.hidden) refresh(); }, 3000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  window.addEventListener('pagehide', () => { clearInterval(timer); if (previewUrl) URL.revokeObjectURL(previewUrl); });
  refresh();
});
