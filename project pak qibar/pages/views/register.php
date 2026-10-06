<div class="auth-heading">
    <p class="auth-eyebrow">Bergabung dengan Motora</p>
    <h1>Mulai cerita<br><span>barumu.</span></h1>
    <p>Buat profilmu, tambahkan motor, dan temukan teman riding.</p>
</div>
<form method="post" class="auth-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="register">
    <div class="field-duo">
        <label class="field" for="register-name">Nama lengkap<input id="register-name" required name="nama" maxlength="100" autocomplete="name" placeholder="Nama kamu"></label>
        <label class="field" for="register-username">Username<input id="register-username" required name="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+" autocomplete="username" spellcheck="false" autocapitalize="none" placeholder="rider_andi" title="Gunakan huruf, angka, atau garis bawah."></label>
    </div>
    <div class="field-duo">
        <label class="field" for="register-email">Email<input id="register-email" required type="email" name="email" autocomplete="email" placeholder="nama@email.com"></label>
        <label class="field" for="register-city">Kota / daerah<input id="register-city" required name="city" autocomplete="address-level2" placeholder="Contoh: Bandung"></label>
    </div>
    <div class="field-duo">
        <label class="field" for="register-password">Password
            <span class="field-wrap"><input id="register-password" required type="password" name="password" minlength="10" autocomplete="new-password" placeholder="Min. 10 karakter"><button type="button" class="pw-toggle" aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span>
        </label>
        <label class="field" for="register-confirm">Ulangi password
            <span class="field-wrap"><input id="register-confirm" required type="password" name="confirm_password" minlength="10" autocomplete="new-password" placeholder="Ketik sekali lagi"><button type="button" class="pw-toggle" aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span>
        </label>
    </div>
    <div class="auth-actions">
        <a class="auth-account-link" href="<?= e(url('login')) ?>">Sudah punya akun? <strong>Masuk</strong></a>
        <button type="submit" class="button primary auth-submit"><span>Buat akun rider</span><span aria-hidden="true">↗</span></button>
    </div>
</form>
