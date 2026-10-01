<?php
ensure_settings($uid);
$settingsQuery = $pdo->prepare('SELECT * FROM user_settings WHERE user_id=?');
$settingsQuery->execute([$uid]);
$prefs = $settingsQuery->fetch() ?: [];
$settingsValues = [];
foreach (['nama', 'username', 'email', 'city', 'region', 'bio'] as $field) {
    $settingsValues[$field] = (string) ($user[$field] ?? '');
}
$settingsDraft = $_SESSION['profile_draft'] ?? null;
if (
    is_array($settingsDraft) &&
    (int) ($settingsDraft['user_id'] ?? 0) === (int) $uid &&
    is_array($settingsDraft['values'] ?? null)
) {
    foreach (array_keys($settingsValues) as $field) {
        if (isset($settingsDraft['values'][$field]) && is_scalar($settingsDraft['values'][$field])) {
            $settingsValues[$field] = (string) $settingsDraft['values'][$field];
        }
    }
}
$settingsVisibilityLabels = ['public' => 'Semua rider', 'friends' => 'Hanya teman', 'private' => 'Hanya saya'];
?>
<section class="page-heading rider-settings-heading">
    <div>
        <div class="eyebrow">RUANG RIDER KAMU</div>
        <h1>Profil &amp; pengaturan</h1>
        <p>Kenalkan dirimu, atur privasi, dan jaga akun tetap aman.</p>
    </div>
    <a class="button subtle" href="<?= e(profile_link((int) $uid)) ?>">Lihat profil saya <span aria-hidden="true">↗</span></a>
</section>

<div class="rider-settings-layout">
    <section class="panel rider-settings-card settings-identity" aria-labelledby="settings-identity-title">
        <div class="rider-settings-card-head">
            <span class="settings-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/></svg></span>
            <div>
                <h2 id="settings-identity-title">Identitas rider</h2>
                <p>Detail kecil yang bikin kamu mudah dikenali.</p>
            </div>
        </div>
        <form method="post" enctype="multipart/form-data" class="rider-settings-form" data-profile-form>
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="profile_save">
            <div class="settings-photo-row">
                <div class="settings-photo-preview" data-profile-photo-preview data-profile-avatar data-initial="<?= e(mb_strtoupper(mb_substr($user['nama'], 0, 1))) ?>"><?= avatar($user['profile_photo'], $user['nama']) ?></div>
                <div class="settings-photo-copy">
                    <strong><?= e($user['nama']) ?></strong>
                    <span class="settings-photo-handle">@<?= e($user['username']) ?></span>
                    <label class="settings-photo-label" for="profile-photo">Ganti foto profil</label>
                    <input id="profile-photo" class="settings-file-input" type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-photo-help" data-profile-photo-input>
                    <small id="profile-photo-help">JPG, PNG, atau WebP. Maksimal 5 MB.</small>
                    <span class="settings-photo-status" data-profile-photo-status role="status" aria-live="polite"></span>
                </div>
            </div>
            <div class="settings-fields-grid">
                <label for="profile-name">Nama lengkap <input id="profile-name" required name="nama" minlength="2" maxlength="100" autocomplete="name" value="<?= e($settingsValues['nama']) ?>" placeholder="Nama yang dikenal rider lain"></label>
                <label for="profile-username">Username <input id="profile-username" required name="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_]{3,30}" autocomplete="username" spellcheck="false" autocapitalize="none" aria-describedby="profile-username-help" title="Gunakan 3–30 huruf, angka, atau garis bawah." value="<?= e($settingsValues['username']) ?>"><small id="profile-username-help">3–30 huruf, angka, atau garis bawah.</small></label>
                <label class="settings-field-wide" for="profile-email">Email <input id="profile-email" required type="email" name="email" maxlength="150" autocomplete="email" value="<?= e($settingsValues['email']) ?>" aria-describedby="profile-email-help"><small id="profile-email-help">Email yang kamu gunakan untuk masuk.</small></label>
                <label for="profile-city">Kota <input id="profile-city" name="city" maxlength="100" autocomplete="address-level2" value="<?= e($settingsValues['city']) ?>" placeholder="Contoh: Tangerang"></label>
                <label for="profile-region">Provinsi / daerah <input id="profile-region" name="region" maxlength="100" autocomplete="address-level1" value="<?= e($settingsValues['region']) ?>" placeholder="Contoh: Banten"></label>
                <label class="settings-field-wide" for="profile-bio">Tentang kamu <textarea id="profile-bio" name="bio" rows="3" maxlength="1000" aria-describedby="profile-bio-help" placeholder="Ceritakan motor, rute favorit, atau gaya riding kamu."><?= e($settingsValues['bio']) ?></textarea><small id="profile-bio-help">Maksimal 1.000 karakter. Kota, daerah, dan bio boleh dikosongkan.</small></label>
            </div>
            <div class="rider-settings-form-footer">
                <span>Nama, username, dan email wajib diisi.</span>
                <button type="submit" class="button primary">Simpan profil <span aria-hidden="true">→</span></button>
            </div>
        </form>
    </section>

    <section class="panel rider-settings-card settings-preferences" aria-labelledby="settings-preferences-title">
        <div class="rider-settings-card-head">
            <span class="settings-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg></span>
            <div>
                <h2 id="settings-preferences-title">Privasi &amp; notifikasi</h2>
                <p>Kamu yang menentukan siapa bisa melihat.</p>
            </div>
        </div>
        <form method="post" class="rider-settings-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="settings_save">
            <fieldset class="settings-fieldset settings-visibility">
                <legend>Visibilitas</legend>
                <?php foreach (['profile_visibility' => 'Profil rider', 'motorcycles_visibility' => 'Motor di garasi', 'gallery_visibility' => 'Galeri foto'] as $field => $label): ?>
                    <label class="settings-visibility-row" for="<?= e($field) ?>"><span><?= e($label) ?></span><select id="<?= e($field) ?>" name="<?= e($field) ?>"><?php foreach ($settingsVisibilityLabels as $value => $optionLabel): ?><option value="<?= e($value) ?>" <?= ($prefs[$field] ?? 'public') === $value ? 'selected' : '' ?>><?= e($optionLabel) ?></option><?php endforeach; ?></select></label>
                <?php endforeach; ?>
                <p class="settings-section-help">Pilihan “Hanya saya” menyimpan bagian tersebut untuk kamu sendiri.</p>
            </fieldset>
            <fieldset class="settings-fieldset">
                <legend>Profil &amp; pencarian</legend>
                <label class="settings-toggle"><span class="settings-toggle-copy"><strong>Tampilkan kota</strong><small>Bagikan lokasi kota di profil rider.</small></span><span class="settings-switch"><input type="checkbox" name="show_city" <?= !empty($prefs['show_city']) ? 'checked' : '' ?>><span aria-hidden="true"></span></span></label>
                <label class="settings-toggle"><span class="settings-toggle-copy"><strong>Muncul di pencarian</strong><small>Rider lain bisa menemukanmu dan mengirim permintaan teman.</small></span><span class="settings-switch"><input type="checkbox" name="is_searchable" <?= !empty($prefs['is_searchable']) ? 'checked' : '' ?>><span aria-hidden="true"></span></span></label>
            </fieldset>
            <fieldset class="settings-fieldset">
                <legend>Notifikasi yang kamu terima</legend>
                <?php foreach ([
                    'notify_friend_requests' => ['Permintaan teman', 'Saat rider ingin terhubung denganmu.'],
                    'notify_community' => ['Komunitas', 'Kabar dan aktivitas komunitas kamu.'],
                    'notify_messages' => ['Pesan baru', 'Saat ada pesan masuk dari rider lain.'],
                    'notify_activity' => ['Aktivitas lainnya', 'Pembaruan aktivitas akun kamu.'],
                ] as $field => [$label, $description]): ?>
                    <label class="settings-toggle"><span class="settings-toggle-copy"><strong><?= e($label) ?></strong><small><?= e($description) ?></small></span><span class="settings-switch"><input type="checkbox" name="<?= e($field) ?>" <?= !empty($prefs[$field]) ? 'checked' : '' ?>><span aria-hidden="true"></span></span></label>
                <?php endforeach; ?>
            </fieldset>
            <div class="rider-settings-form-footer settings-preferences-footer"><button type="submit" class="button primary">Simpan preferensi <span aria-hidden="true">→</span></button></div>
        </form>
    </section>

    <section class="panel rider-settings-card settings-security" aria-labelledby="settings-security-title">
        <div class="rider-settings-card-head">
            <span class="settings-section-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg></span>
            <div><h2 id="settings-security-title">Keamanan akun</h2><p>Perbarui password untuk menjaga akunmu.</p></div>
        </div>
        <form method="post" class="rider-settings-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="password_change">
            <div class="settings-fields-grid">
                <label class="settings-field-wide" for="current-password">Password saat ini <span class="field-wrap"><input id="current-password" required type="password" name="current_password" autocomplete="current-password"><button class="pw-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
                <label for="new-password">Password baru <span class="field-wrap"><input id="new-password" required type="password" name="new_password" minlength="10" autocomplete="new-password" aria-describedby="new-password-help"><button class="pw-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span><small id="new-password-help">Gunakan minimal 10 karakter.</small></label>
                <label for="confirm-password">Ulangi password baru <span class="field-wrap"><input id="confirm-password" required type="password" name="confirm_password" minlength="10" autocomplete="new-password"><button class="pw-toggle" type="button" aria-label="Tampilkan password" aria-pressed="false"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></span></label>
            </div>
            <div class="rider-settings-form-footer"><span>Gunakan password yang sulit ditebak.</span><button type="submit" class="button subtle">Perbarui password <span aria-hidden="true">→</span></button></div>
        </form>
    </section>
</div>
