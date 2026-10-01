<div class="auth-heading">
    <p class="auth-eyebrow">Pemulihan akun</p>
    <h1>KEMBALI<br><span>TERHUBUNG.</span></h1>
    <p>Gunakan email yang kamu daftarkan untuk meminta pemulihan akses akun.</p>
</div>
<form method="post" class="auth-form">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="forgot">
    <label class="field" for="forgot-email">Email akun<span class="field-wrap"><input id="forgot-email" required type="email" name="email" autocomplete="email" placeholder="nama@email.com"></span></label>
    <button type="submit" class="button primary full auth-submit" <?= local_reset_links_enabled() ? '' : 'disabled' ?>><span>Buat tautan pemulihan</span><span aria-hidden="true">↗</span></button>
</form>
<?php if (local_reset_links_enabled() && !empty($_SESSION['local_reset_url'])): ?>
<div class="reset-local" role="status"><b>Tautan pemulihan siap</b><a href="<?= e($_SESSION['local_reset_url']) ?>">Atur password baru <span aria-hidden="true">↗</span></a><small>Tautan tersedia pada sesi pemulihan lokal ini.</small></div>
<?php unset($_SESSION['local_reset_url']); elseif (!local_reset_links_enabled()): unset($_SESSION['local_reset_url']); ?>
<p class="auth-help">Pemulihan belum diaktifkan. Hubungi pengelola untuk bantuan akses akun.</p>
<?php endif; ?>
<p class="auth-bottom"><a href="<?= e(url('login')) ?>"><span aria-hidden="true">←</span> Kembali masuk</a></p>
