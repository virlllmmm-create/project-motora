<?php
$flashMessage = take_flash();
$user = current_user();
$classicAuth = !$user && in_array($page, ['login', 'register'], true);
$unread = 0;
if ($user) {
    $q = db()->prepare('SELECT COUNT(*) FROM notifications n WHERE n.recipient_id=? AND n.read_at IS NULL AND ' . notification_visibility_sql((int) $user['id_user'], 'n'));
    $q->execute([$user['id_user']]);
    $unread = (int) $q->fetchColumn();
}
$nav = [
    ['dashboard', '01', 'Beranda'], ['search', '02', 'Cari rider'],
    ['communities', '03', 'Komunitas'], ['garage', '04', 'Garasi'],
    ['friends', '05', 'Teman'], ['messages', '06', 'Pesan'],
    ['notifications', '07', 'Aktivitas'], ['settings', '08', 'Pengaturan'],
];
$navIcons = [
    'dashboard' => 'M3 10 12 3l9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z',
    'search' => 'M21 21l-5-5 M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
    'communities' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
    'garage' => 'M3 10l9-7 9 7v11H3z M7 21V11h10v10 M7 15h10 M7 18h10',
    'friends' => 'M20 21v-2a7 7 0 0 0-14 0v2 M17 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M2 8h4 M4 6v4',
    'messages' => 'M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7A8.4 8.4 0 0 1 4 11.5a8.5 8.5 0 0 1 8.7-7.6A8.4 8.4 0 0 1 12.5 3h.5a8.5 8.5 0 0 1 8 8Z',
    'notifications' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
    'settings' => 'M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8 M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1Z',
];
function nav_active(string $page, string $key): bool {
    if ($page === $key) return true;
    if (in_array($page, ['community', 'community_settings'], true) && $key === 'communities') return true;
    if ($page === 'motorcycle' && $key === 'garage') return true;
    if ($page === 'profile') {
        $viewerId = (int) (current_user()['id_user'] ?? 0);
        $profileId = filter_var($_GET['id'] ?? $viewerId, FILTER_VALIDATE_INT);
        return $key === ($profileId === $viewerId ? 'settings' : 'friends');
    }
    return false;
}
$brandSymbol = '<svg viewBox="0 0 40 28" fill="none" aria-hidden="true"><path d="M2 25 12 3h8l-6 14L27 3h10L26 25h-8l6-13-13 13H2Z" fill="currentColor"/></svg>';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="<?= $classicAuth ? '#0e131a' : ($user ? '#141813' : '#f3f2e9') ?>">
    <meta name="color-scheme" content="<?= $user || $classicAuth ? 'dark' : 'light' ?>">
    <title><?= e(page_title($page)) ?> · MOTORA</title>
    <meta name="description" content="Ruang temu rider Indonesia. Temukan teman sejalan, komunitas, dan cerita dari garasimu di MOTORA.">
    <link rel="icon" type="image/svg+xml" href="<?= $classicAuth ? 'assets/images/motora-classic.svg?v=1' : 'assets/images/motora.svg?v=3' ?>">
    <?php if (!$user): ?><link rel="preload" as="image" href="assets/images/auth-ride.jpg"><?php endif; ?>
    <link rel="stylesheet" href="assets/fonts/fonts.css?v=1">
    <link rel="stylesheet" href="assets/css/design.css?v=6">
    <?php if ($user && in_array($page, ['community', 'community_settings'], true)): ?><link rel="stylesheet" href="assets/css/community.css?v=6"><?php endif; ?>
    <link rel="stylesheet" href="assets/css/components.css?v=7">
    <?php if ($classicAuth): ?><link rel="stylesheet" href="assets/css/auth-classic.css?v=1"><?php endif; ?>
    <link rel="stylesheet" href="assets/css/background-video.css?v=4">
    <?php if (!$classicAuth): ?>
    <link rel="stylesheet" href="assets/css/ride-club.css?v=2">
    <?php if ($user && $page === 'dashboard'): ?><link rel="stylesheet" href="assets/css/home.css?v=1"><?php endif; ?>
    <?php if (!$user): ?><link rel="stylesheet" href="assets/css/auth.css?v=7"><?php endif; ?>
    <?php endif; ?>
</head>
<body class="<?= $user ? 'authenticated' : 'public-page' ?><?= $user && $page === 'messages' ? ' messages-page' : '' ?><?= $user && $page === 'community' && !empty($communityChatPage) ? ' community-chat-page' : '' ?><?= $user && $page === 'community_settings' ? ' community-settings-page' : '' ?><?= $user && $page === 'dashboard' ? ' dashboard-page' : '' ?><?= $user && $page === 'profile' ? ' rider-page' : '' ?><?= $user && $page === 'settings' ? ' settings-page' : '' ?><?= in_array($page, ['login', 'register'], true) ? ' auth-compact auth-steady' : '' ?><?= $page === 'register' ? ' auth-register' : '' ?><?= in_array($page, ['forgot', 'reset'], true) ? ' auth-compact auth-reset' : '' ?>">
<a class="skip" href="#isi">Lewati ke isi</a>
<?php if ($user || $classicAuth): ?><div class="site-background" data-site-background aria-hidden="true">
    <video data-background-video muted loop playsinline preload="none" poster="assets/images/road-motion.jpg" tabindex="-1" disablepictureinpicture disableremoteplayback>
        <source data-src="assets/videos/road-motion.mp4" type="video/mp4">
    </video>
</div><?php endif; ?>
<?php if ($flashMessage): ?>
<div class="kilat <?= $flashMessage['type'] === 'error' ? 'error' : 'success' ?>" data-flash role="<?= $flashMessage['type'] === 'error' ? 'alert' : 'status' ?>" aria-atomic="true">
    <span class="flash-icon" aria-hidden="true"><?= $flashMessage['type'] === 'error' ? '!' : '✓' ?></span>
    <div class="flash-copy"><b><?= $flashMessage['type'] === 'error' ? 'Perlu perhatian' : 'Berhasil' ?></b><p><?= e($flashMessage['message']) ?></p></div>
    <button type="button" class="flash-close" data-flash-close aria-label="Tutup notifikasi">×</button><span class="flash-timer" aria-hidden="true"></span>
</div>
<?php endif; ?>
<?php if ($user): ?>
<div class="app-header">
<header class="app-topbar">
    <a class="motora-brand" href="<?= e(url('dashboard')) ?>" aria-label="MOTORA beranda"><?= $brandSymbol ?><span>motora<span class="brand-period">.</span></span></a>
    <span class="brand-descriptor"><span class="topbar-breadcrumb">RIDER SPACE <span aria-hidden="true">/</span></span><strong><?= e(page_title($page)) ?></strong></span>
    <form class="topbar-search" method="get" role="search">
        <input type="hidden" name="page" value="search">
        <label class="sr-only" for="global-search">Cari rider, motor, atau kota</label>
        <input id="global-search" name="q" value="<?= $page === 'search' ? e($filters['q'] ?? '') : '' ?>" placeholder="Cari rider, motor, kota…" autocomplete="off">
        <button type="submit" aria-label="Cari"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/></svg></button>
    </form>
    <div class="topbar-actions">
        <a class="topbar-garage" href="<?= e(url('garage')) ?>">Garasi saya <span aria-hidden="true">↗</span></a>
        <a class="topbar-icon" href="<?= e(url('notifications')) ?>" aria-label="Aktivitas<?= $unread ? ' belum dibaca' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><?php if ($unread): ?><span class="notification-dot"></span><?php endif; ?></a>
        <a class="user-chip" href="<?= e(profile_link((int) $user['id_user'])) ?>" aria-label="Profil <?= e($user['nama']) ?>"><?= avatar($user['profile_photo'], $user['nama']) ?><span class="user-chip-copy"><strong><?= e(explode(' ', trim($user['nama']))[0]) ?></strong><small>Profil rider ↗</small></span></a>
        <button type="button" class="menu-btn" id="menuToggle" aria-expanded="false" aria-controls="mainNav" aria-label="Buka navigasi"><span></span><span></span></button>
    </div>
</header>
<div class="app-navigation">
    <nav class="ledger-nav" id="mainNav" aria-label="Navigasi utama">
        <span class="nav-section-label">Ruang rider <span>01 — 08</span></span>
        <?php foreach ($nav as [$key, $num, $label]): ?>
        <a class="<?= nav_active($page, $key) ? 'aktif' : '' ?>" <?= nav_active($page, $key) ? 'aria-current="page"' : '' ?> data-nav-key="<?= e($key) ?>" href="<?= e(url($key)) ?>"><svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($navIcons[$key]) ?>"/></svg><span><?= e($label) ?></span><?php if ($key === 'notifications' && $unread): ?><em class="biji" data-notification-count><?= $unread ?></em><?php endif; ?></a>
        <?php endforeach; ?>
        <div class="nav-manifesto" aria-hidden="true"><svg viewBox="0 0 100 58" fill="none"><path d="M5 48h27c28 0 6-38 34-38h28" stroke="currentColor" stroke-width="1.5" stroke-dasharray="3 4"/><circle cx="5" cy="48" r="4" fill="currentColor"/><circle cx="94" cy="10" r="4" fill="currentColor"/></svg><span>JARAK BOLEH JAUH.</span><strong>SEJALAN<br>TETAP DEKAT.</strong><small>Motor boleh beda. Kita tetap satu jalan.</small></div>
        <form method="post" class="logout-inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button aria-label="Keluar dari akun">Keluar akun <span aria-hidden="true">↗</span></button></form>
    </nav>
</div>
</div>
<main class="kertas" id="isi">
    <div class="kolom"><?= $content ?></div>
    <footer class="kolofon"><a class="footer-wordmark" href="<?= e(url('dashboard')) ?>">motora.</a><span>Dua roda. Banyak cerita.</span><span>Ruang temu rider Indonesia.</span></footer>
</main>
<?php else: ?>
<div class="auth-latar">
    <div class="auth-grid">
        <aside class="auth-manifesto">
            <img class="auth-photo" src="assets/images/auth-ride.jpg" alt="Rider dengan motor klasik di jalan hutan yang rindang" fetchpriority="high">
            <?php if ($classicAuth): ?>
            <div class="auth-visual-top"><span class="auth-visual-tagline">Komunitas rider Indonesia</span><span class="auth-visual-index">Dua roda.<br>Banyak cerita.</span></div>
            <div class="auth-visual-copy"><span class="eyebrow">Temukan teman sejalan</span><h1>Teman baru.<br>Cerita baru<span>.</span></h1><p>Motor boleh beda.<br>Cerita kita ketemu di sini.</p></div>
            <div class="auth-visual-bottom"><span>Sunmori / Kopdar / Touring</span><span>Dua roda.<br>Banyak cerita.</span></div>
            <?php else: ?>
            <div class="auth-visual-top"><a class="motora-brand" href="<?= e(url('login')) ?>" aria-label="MOTORA beranda"><?= $brandSymbol ?><span>motora.</span></a><span class="auth-visual-index">INDEPENDENT SPIRITS.<br>SHARED ROADS.</span></div>
            <div class="auth-visual-copy"><span class="eyebrow">Bukan sekadar perjalanan</span><h2>SATU ASPAL.<br>SERIBU<br>CERITA<span>.</span></h2><p>Untuk yang percaya, perjalanan terbaik<br>selalu punya teman sejalan.</p></div>
            <div class="auth-visual-bottom"><span>Sunmori / Kopdar / Touring</span><span>Ruang temu rider<br>Indonesia ↗</span></div>
            <?php endif; ?>
        </aside>
        <main class="auth-kartu" id="isi">
            <div class="auth-form-top">
                <a class="auth-home" href="<?= e(url('login')) ?>" aria-label="MOTORA beranda"><?= $brandSymbol ?><span>motora.</span></a>
                <?php if ($page === 'login'): ?>
                    <a class="auth-switch" href="<?= e(url('register')) ?>">Daftar <span aria-hidden="true">↗</span></a>
                <?php else: ?>
                    <span>Tempat rider terhubung</span>
                <?php endif; ?>
            </div>
            <div class="auth-form-content"><?= $content ?></div>
            <p class="auth-colophon">© <?= e(date('Y')) ?> MOTORA<span>Ruang temu rider Indonesia.</span></p>
        </main>
    </div>
</div>
<?php endif; ?>
<?php if ($user): ?><span hidden data-delivery-poll data-poll-url="<?= e(url('messages', ['ajax' => 'deliveries'])) ?>"></span><?php endif; ?>
<script src="assets/js/app.js?v=5" defer></script>
<script src="assets/js/background-video.js?v=2" defer></script>
<?php if ($user && $page === 'community'): ?><script src="assets/js/community-chat.js?v=2" defer></script><script src="assets/js/community-calls.js?v=1" defer></script><?php endif; ?>
<?php if ($user && $page === 'community_settings'): ?><script src="assets/js/community-settings.js?v=1" defer></script><?php endif; ?>
</body>
</html>
