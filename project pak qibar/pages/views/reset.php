<div class="auth-heading">
    <p class="auth-eyebrow">Amankan akunmu</p>
    <h1>Buat password<br><span>baru.</span></h1>
    <p>Gunakan minimal 10 karakter dan password yang belum kamu pakai sebelumnya.</p>
</div>
<form method="post" class="auth-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="reset">
    <input type="hidden" name="token" value="<?= e($_GET['token'] ?? '') ?>">
    <label class="field" for="reset-password">Password baru
        <span class="field-wrap"><input id="reset-password" required type="password" name="password" minlength="10" autocomplete="new-password" placeholder="Min. 10 karakter"><button type="button" class="pw-toggle" aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span>
    </label>
    <label class="field" for="reset-confirm">Ulangi password
        <span class="field-wrap"><input id="reset-confirm" required type="password" name="confirm_password" minlength="10" autocomplete="new-password" placeholder="Ketik sekali lagi"><button type="button" class="pw-toggle" aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span>
    </label>
    <button type="submit" class="button primary full auth-submit"><span>Simpan password</span><span aria-hidden="true">↗</span></button>
</form>
<p class="auth-bottom"><a href="<?= e(url('login')) ?>"><span aria-hidden="true">←</span> Kembali masuk</a></p>
