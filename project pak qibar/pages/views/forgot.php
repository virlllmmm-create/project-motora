<div class="auth-heading center"><span class="auth-badge" aria-hidden="true"><svg
            viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
        </svg></span>
    <h1>Atur Ulang Password</h1>
    <p>Masukkan email akun. Jika email valid dan reset lokal diaktifkan, tautan akan muncul pada sesi localhost ini.</p>
</div>
<form method="post" class="auth-form"><input type="hidden" name="csrf"
        value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="forgot"><label
        class="field">Email akun<span class="field-wrap"><input required type="email" name="email"
                placeholder="you@example.com"><span class="field-icon" aria-hidden="true"><svg
                    viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor"
                    stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="4" width="20" height="16" rx="2" />
                    <polyline points="22,6 12,13 2,6" />
                </svg></span></span></label><button class="button primary full auth-submit">Buat
        Tautan Reset</button></form>
<?php if (local_reset_links_enabled() && !empty($_SESSION['local_reset_url'])): ?><div class="reset-local"><b>Tautan reset
        lokal</b><a href="<?= e($_SESSION['local_reset_url']) ?>">Buka form reset
        password</a><small>Hanya tersedia melalui loopback localhost ketika MOTORA_LOCAL_RESET_LINKS=1.</small></div>
<?php unset($_SESSION['local_reset_url']); elseif (!local_reset_links_enabled()): unset($_SESSION['local_reset_url']); endif; ?>
<p class="auth-bottom"><a href="<?= e(url('login')) ?>">Kembali masuk</a></p>