<div class="auth-heading center"><span class="auth-badge" aria-hidden="true"><svg
            viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor"
            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="8.5" cy="7" r="4" />
            <line x1="20" y1="8" x2="20" y2="14" />
            <line x1="23" y1="11" x2="17" y2="11" />
        </svg></span>
    <h1>Buat Akun Rider</h1>
    <p>Daftar untuk mulai riding bersama</p>
</div>
<form method="post" class="auth-form"><input type="hidden" name="csrf"
        value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="register">
    <div class="field-duo"><label class="field">Nama lengkap<input required name="nama"
                maxlength="100" placeholder="Nama kamu"></label><label class="field">Username<input
                required name="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]+"
                placeholder="rider_andi"></label></div>
    <div class="field-duo"><label class="field">Email<span class="field-wrap"><input required
                    type="email" name="email" placeholder="you@example.com"><span class="field-icon"
                    aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2" />
                        <polyline points="22,6 12,13 2,6" />
                    </svg></span></span></label><label class="field">Kota / daerah<input required
                name="city" placeholder="Jakarta"></label></div>
    <div class="field-duo"><label class="field">Password<span class="field-wrap"><input required
                    type="password" name="password" minlength="10" autocomplete="new-password"
                    placeholder="Min. 10 karakter"><button type="button" class="pw-toggle"
                    aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24"
                        width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg></button></span></label><label class="field">Konfirmasi password<span
                class="field-wrap"><input required type="password" name="confirm_password"
                    minlength="10" autocomplete="new-password" placeholder="Ulangi password"><button
                    type="button" class="pw-toggle" aria-label="Tampilkan password"
                    aria-pressed="false"><svg viewBox="0 0 24 24" width="18" height="18" fill="none"
                        stroke="currentColor" stroke-width="1.8" stroke-linecap="round"
                        stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                        <circle cx="12" cy="12" r="3" />
                    </svg></button></span></label></div><button
        class="button primary full auth-submit">Buat Akun</button>
</form>
<p class="auth-bottom">Sudah punya akun? <a href="<?= e(url('login')) ?>">Masuk</a></p>