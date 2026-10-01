<div class="auth-heading center"><span class="auth-badge" aria-hidden="true"><svg
            viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg></span>
    <h1>Password Baru</h1>
    <p>Buat password yang kuat untuk akunmu.</p>
</div>
<form method="post" class="auth-form"><input type="hidden" name="csrf"
        value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reset"><input
        type="hidden" name="token" value="<?= e($_GET['token']??'') ?>"><label
        class="field">Password baru<span class="field-wrap"><input required type="password"
                name="password" minlength="10" autocomplete="new-password"
                placeholder="Min. 10 karakter"><button type="button" class="pw-toggle"
                aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24"
                    width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                </svg></button></span></label><label class="field">Ulangi password<span
            class="field-wrap"><input required type="password" name="confirm_password"
                minlength="10" autocomplete="new-password" placeholder="Ulangi password"><button
                type="button" class="pw-toggle" aria-label="Tampilkan password"
                aria-pressed="false"><svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                </svg></button></span></label><button class="button primary full auth-submit">Simpan
        Password</button></form>