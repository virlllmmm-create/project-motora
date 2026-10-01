document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-flash]').forEach((notice) => {
    let dismissing = false;
    const dismiss = () => {
      if (dismissing) return;
      dismissing = true;
      window.clearTimeout(timer);
      if (notice.contains(document.activeElement)) {
        const content = document.getElementById('isi');
        if (content) {
          const previousTabIndex = content.getAttribute('tabindex');
          content.setAttribute('tabindex', '-1');
          content.addEventListener('blur', () => {
            if (previousTabIndex === null) content.removeAttribute('tabindex');
            else content.setAttribute('tabindex', previousTabIndex);
          }, { once: true });
          content.focus({ preventScroll: true });
        }
      }
      notice.classList.add('is-leaving');
      window.setTimeout(() => notice.remove(), 200);
    };
    const timer = window.setTimeout(dismiss, 5000);
    notice.querySelector('[data-flash-close]')?.addEventListener('click', dismiss);
  });
  const toggle = document.getElementById('menuToggle');
  const nav = document.getElementById('mainNav');
  if (toggle && nav)
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('buka');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Tutup navigasi' : 'Buka navigasi');
    });
  if (toggle && nav) {
    nav.querySelectorAll('a').forEach((link) =>
      link.addEventListener('click', () => {
        nav.classList.remove('buka');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Buka navigasi');
      }),
    );
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && nav.classList.contains('buka')) {
        nav.classList.remove('buka');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Buka navigasi');
        toggle.focus();
      }
    });
  }
  document.querySelectorAll('.pw-toggle').forEach((btn) =>
    btn.addEventListener('click', () => {
      const input = btn.closest('.field-wrap')?.querySelector('input');
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
    }),
  );
  const prepareProfileAvatar = (container) => {
    const photo = container.querySelector('img.avatar');
    if (!photo) return;
    const showInitial = () => {
      if (!photo.isConnected) return;
      const fallback = document.createElement('span');
      fallback.className = 'avatar avatar-fallback';
      fallback.textContent = container.dataset.initial || 'R';
      photo.replaceWith(fallback);
    };
    photo.addEventListener('error', showInitial, { once: true });
    if (photo.complete && photo.naturalWidth === 0) showInitial();
  };
  document.querySelectorAll('[data-profile-avatar]').forEach(prepareProfileAvatar);
  const photoInput = document.querySelector('[data-profile-photo-input]');
  const photoPreview = document.querySelector('[data-profile-photo-preview]');
  const photoStatus = document.querySelector('[data-profile-photo-status]');
  if (photoInput && photoPreview) {
    const originalPhoto = [...photoPreview.childNodes].map((node) => node.cloneNode(true));
    let previewUrl = null;
    const resetPreview = () => {
      photoPreview.replaceChildren(...originalPhoto.map((node) => node.cloneNode(true)));
      prepareProfileAvatar(photoPreview);
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = null;
    };
    photoInput.addEventListener('change', () => {
      resetPreview();
      photoInput.setCustomValidity('');
      if (photoStatus) photoStatus.textContent = '';
      const file = photoInput.files?.[0];
      if (!file) return;
      let error = '';
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) error = 'Pilih foto berformat JPG, PNG, atau WebP.';
      else if (file.size > 5 * 1024 * 1024) error = 'Ukuran foto maksimal 5 MB. Pilih foto yang lebih kecil.';
      if (error) {
        photoInput.setCustomValidity(error);
        if (photoStatus) photoStatus.textContent = error;
        return;
      }
      previewUrl = URL.createObjectURL(file);
      const image = document.createElement('img');
      image.className = 'avatar';
      image.alt = 'Pratinjau foto profil baru';
      image.addEventListener('load', () => {
        if (photoInput.files?.[0] === file && photoStatus) photoStatus.textContent = 'Foto siap dipakai. Tekan Simpan profil untuk menyimpannya.';
      }, { once: true });
      image.addEventListener('error', () => {
        if (photoInput.files?.[0] !== file) return;
        resetPreview();
        const message = 'Foto tidak dapat dibaca. Pilih gambar lain yang valid.';
        photoInput.setCustomValidity(message);
        if (photoStatus) photoStatus.textContent = message;
      }, { once: true });
      image.src = previewUrl;
      photoPreview.replaceChildren(image);
    });
    window.addEventListener('pagehide', () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
    });
  }
  const passwordForm = document.querySelector('input[name="action"][value="password_change"]')?.form;
  if (passwordForm) {
    const newPassword = passwordForm.querySelector('[name="new_password"]');
    const confirmation = passwordForm.querySelector('[name="confirm_password"]');
    const validateConfirmation = () => {
      confirmation.setCustomValidity(confirmation.value && confirmation.value !== newPassword.value ? 'Ulangi password yang sama dengan password baru.' : '');
    };
    newPassword.addEventListener('input', validateConfirmation);
    confirmation.addEventListener('input', validateConfirmation);
  }
  const deliveryPoll = document.querySelector('[data-delivery-poll]');
  const acknowledgeDelivery = async () => {
    if (!deliveryPoll) return;
    try {
      const response = await fetch(deliveryPoll.dataset.pollUrl, {
        headers: { 'X-Requested-With': 'fetch' },
        cache: 'no-store',
      });
      if (!response.ok) return;
      const state = await response.json();
      const notificationsLink = document.querySelector('[data-nav-key="notifications"]');
      if (notificationsLink) {
        let notificationBadge = notificationsLink.querySelector('[data-notification-count]');
        if (state.notification_count > 0) {
          if (!notificationBadge) {
            notificationBadge = document.createElement('i');
            notificationBadge.className = 'nav-count';
            notificationBadge.dataset.notificationCount = '';
            notificationsLink.append(notificationBadge);
          }
          notificationBadge.textContent = state.notification_count;
        } else if (notificationBadge) notificationBadge.remove();
      }
      document.querySelectorAll('.chat-thread[data-conversation-id]').forEach((thread) => {
        const count = state.unread?.[thread.dataset.conversationId] || 0;
        let badge = thread.querySelector('[data-thread-unread]');
        if (count > 0) {
          if (!badge) {
            badge = document.createElement('i');
            badge.dataset.threadUnread = '';
            thread.append(badge);
          }
          badge.textContent = count;
        } else if (badge) badge.remove();
      });
    } catch (_) {}
  };
  if (deliveryPoll) {
    acknowledgeDelivery();
    window.setInterval(acknowledgeDelivery, 5000);
  }
  const chatBack = document.querySelector('.chat-back');
  if (chatBack)
    chatBack.addEventListener('click', (event) => {
      if (window.matchMedia('(max-width: 760px)').matches) {
        event.preventDefault();
        window.history.back();
      }
    });
  const chat = document.querySelector('[data-chat]');
  if (chat) {
    const url = chat.dataset.pollUrl;
    const refresh = async () => {
      try {
        const response = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
        if (!response.ok) return;
        const messages = await response.json();
        const list = document.querySelector('[data-chat-messages]');
        if (!list) return;
        const previousScrollTop = list.scrollTop;
        const firstRefresh = list.dataset.initialized !== 'true';
        const bottom = list.scrollHeight - list.scrollTop - list.clientHeight < 90;
        const last = list.dataset.lastId;
        const newest = messages.length ? String(messages[messages.length - 1].id) : '';
        const hasNew = newest !== last;
        list.replaceChildren(
          ...messages.map((m) => {
            const item = document.createElement('article');
            item.className = 'chat-message ' + (m.mine ? 'mine' : 'theirs');
            const text = document.createElement('p');
            text.textContent = m.body;
            const meta = document.createElement('div');
            meta.className = 'message-meta';
            const time = document.createElement('small');
            time.textContent = m.created_at;
            meta.append(time);
            const status = m.status || 'sent';
            const checks = document.createElement('span');
            checks.className = 'message-checks ' + status;
            checks.setAttribute(
              'aria-label',
              status === 'read'
                ? 'Dibaca'
                : status === 'delivered'
                  ? 'Terkirim ke perangkat'
                  : 'Terkirim',
            );
            checks.textContent = status === 'sent' ? '✓' : '✓✓';
            meta.append(checks);
            item.append(text, meta);
            return item;
          }),
        );
        list.dataset.lastId = newest;
        if (firstRefresh || (bottom && hasNew)) list.scrollTop = list.scrollHeight;
        else list.scrollTop = previousScrollTop;
        list.dataset.initialized = 'true';
        acknowledgeDelivery();
      } catch (_) {}
    };
    refresh();
    window.setInterval(refresh, 5000);
  }
});
