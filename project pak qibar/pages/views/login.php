<div class="auth-heading center"><span class="auth-badge" aria-hidden="true"><svg
            viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M15 3h4a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-4" />
            <polyline points="10 17 15 12 10 7" />
            <line x1="15" y1="12" x2="3" y2="12" />
        </svg></span>
    <h1>Selamat Datang Kembali!</h1>
    <p>Masuk untuk melanjutkan</p>
</div>
<form method="post" class="auth-form"><input type="hidden" name="csrf"
        value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="login"><label
        class="field">Email atau Username<span class="field-wrap"><input required name="identity"
                autocomplete="username" placeholder="you@example.com"><span class="field-icon"
                aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                    stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2" />
                    <polyline points="22,6 12,13 2,6" />
                </svg></span></span></label><label class="field">Password<span
            class="field-wrap"><input required type="password" name="password"
                autocomplete="current-password" placeholder="Enter your password"><button
                type="button" class="pw-toggle" aria-label="Tampilkan password"
                aria-pressed="false"><svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                    stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                </svg></button></span></label>
    <div class="auth-row"><a class="text-link" href="<?= e(url('forgot')) ?>">Lupa password?</a>
    </div><button class="button primary full auth-submit">Masuk</button>
</form>
<p class="auth-bottom">Belum punya akun? <a href="<?= e(url('register')) ?>">Daftar</a></p>