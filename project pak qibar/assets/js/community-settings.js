document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('[data-community-settings]');
  if (!root) return;
  const button = root.querySelector('[data-copy-invite]');
  const feedback = root.querySelector('[data-settings-feedback]');
  button?.addEventListener('click', async () => {
    const input = root.querySelector('[data-invite-link]');
    const url = new URL(input.value, location.href).href;
    try {
      if (!navigator.clipboard?.writeText) throw new Error('Clipboard unavailable');
      await navigator.clipboard.writeText(url);
    } catch {
      input.value = url;
      input.focus();
      input.select();
      if (!document.execCommand('copy')) {
        feedback.textContent = 'Pilih dan salin tautan pada kolom undangan.';
        feedback.hidden = false;
        return;
      }
    }
    feedback.textContent = 'Tautan undangan disalin.';
    feedback.hidden = false;
    button.textContent = 'Tautan disalin';
    window.setTimeout(() => {
      feedback.hidden = true;
      button.textContent = 'Salin tautan undangan';
    }, 5000);
  });
});
