<div class="auth-heading">
    <p class="auth-eyebrow">Masuk ke Motora</p>
    <h1>Selamat datang<br><span>kembali.</span></h1>
    <p>Masuk untuk membuka garasi dan terhubung dengan rider lain.</p>
</div>
<form method="post" class="auth-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="login">
    <label class="field" for="login-identity">Email atau username
        <span class="field-wrap"><input id="login-identity" required name="identity" autocomplete="username" placeholder="Email atau username kamu"></span>
    </label>
    <label class="field" for="login-password">Password
        <span class="field-wrap">
            <input id="login-password" required type="password" name="password" autocomplete="current-password" placeholder="Masukkan password">
            <button type="button" class="pw-toggle" aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </span>
    </label>
    <div class="auth-actions">
        <a class="auth-account-link" href="<?= e(url('forgot')) ?>">Lupa password?</a>
        <button type="submit" class="button primary auth-submit"><span>Masuk ke Motora</span><span aria-hidden="true">↗</span></button>
    </div>
</form>
