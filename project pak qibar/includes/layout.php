<?php
$flashMessage = take_flash();
$user = current_user();
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
    <meta name="theme-color" content="<?= $user ? '#161815' : '#f1f0e9' ?>">
    <meta name="color-scheme" content="light">
    <title><?= e(page_title($page)) ?> · MOTORA</title>
    <meta name="description" content="Ruang temu rider Indonesia. Temukan teman sejalan, komunitas, dan cerita dari garasimu di MOTORA.">
    <link rel="icon" type="image/svg+xml" href="assets/images/motora.svg">
    <?php if (!$user || $page === 'dashboard'): ?><link rel="preload" as="image" href="assets/images/open-road.jpg"><?php endif; ?>
    <link rel="stylesheet" href="assets/fonts/fonts.css?v=1">
    <link rel="stylesheet" href="assets/css/design.css?v=1">
    <?php if ($user && in_array($page, ['community', 'community_settings'], true)): ?><link rel="stylesheet" href="assets/css/community.css?v=5"><?php endif; ?>
    <link rel="stylesheet" href="assets/css/components.css?v=1">
    <?php if (!$user): ?><link rel="stylesheet" href="assets/css/auth.css?v=1"><?php endif; ?>
</head>
<body class="<?= $user ? 'authenticated' : 'public-page' ?><?= $user && $page === 'messages' ? ' messages-page' : '' ?><?= $user && $page === 'community' && !empty($communityChatPage) ? ' community-chat-page' : '' ?><?= $user && $page === 'community_settings' ? ' community-settings-page' : '' ?><?= $user && $page === 'dashboard' ? ' dashboard-page' : '' ?><?= $user && $page === 'profile' ? ' rider-page' : '' ?><?= $user && $page === 'settings' ? ' settings-page' : '' ?><?= in_array($page, ['login', 'register'], true) ? ' auth-compact' : '' ?><?= $page === 'register' ? ' auth-register' : '' ?><?= in_array($page, ['forgot', 'reset'], true) ? ' auth-compact auth-reset' : '' ?>">
<a class="skip" href="#isi">Lewati ke isi</a>
<?php if ($flashMessage): ?>
<div class="kilat <?= $flashMessage['type'] === 'error' ? 'error' : 'success' ?>" data-flash role="<?= $flashMessage['type'] === 'error' ? 'alert' : 'status' ?>" aria-atomic="true">
    <span class="flash-icon" aria-hidden="true"><?= $flashMessage['type'] === 'error' ? '!' : '✓' ?></span>
    <div class="flash-copy"><b><?= $flashMessage['type'] === 'error' ? 'Perlu perhatian' : 'Berhasil' ?></b><p><?= e($flashMessage['message']) ?></p></div>
    <button type="button" class="flash-close" data-flash-close aria-label="Tutup notifikasi">×</button><span class="flash-timer" aria-hidden="true"></span>
</div>
<?php endif; ?>
<?php if ($user): ?>
<header class="app-topbar">
    <a class="motora-brand" href="<?= e(url('dashboard')) ?>" aria-label="MOTORA beranda"><?= $brandSymbol ?><span>MOTORA<span class="brand-period">.</span></span></a>
    <span class="brand-descriptor">RUANG TEMU<br>RIDER INDONESIA</span>
    <form class="topbar-search" method="get" role="search">
        <input type="hidden" name="page" value="search">
        <label class="sr-only" for="global-search">Cari rider, motor, atau kota</label>
        <input id="global-search" name="q" value="<?= $page === 'search' ? e($filters['q'] ?? '') : '' ?>" placeholder="Cari rider, motor, kota…" autocomplete="off">
        <button type="submit" aria-label="Cari"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/></svg></button>
    </form>
    <div class="topbar-actions">
        <a class="topbar-garage" href="<?= e(url('garage')) ?>">Garasi saya <span aria-hidden="true">↗</span></a>
        <a class="topbar-icon" href="<?= e(url('notifications')) ?>" aria-label="Aktivitas<?= $unread ? ' belum dibaca' : '' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><?php if ($unread): ?><span class="notification-dot"></span><?php endif; ?></a>
        <a class="user-chip" href="<?= e(profile_link((int) $user['id_user'])) ?>" aria-label="Profil <?= e($user['nama']) ?>"><?= avatar($user['profile_photo'], $user['nama']) ?></a>
        <button type="button" class="menu-btn" id="menuToggle" aria-expanded="false" aria-controls="mainNav" aria-label="Buka navigasi"><span></span><span></span></button>
    </div>
</header>
<div class="app-navigation">
    <nav class="ledger-nav" id="mainNav" aria-label="Navigasi utama">
        <?php foreach ($nav as [$key, $num, $label]): ?>
        <a class="<?= nav_active($page, $key) ? 'aktif' : '' ?>" <?= nav_active($page, $key) ? 'aria-current="page"' : '' ?> data-nav-key="<?= e($key) ?>" href="<?= e(url($key)) ?>"><span class="nav-number" aria-hidden="true"><?= $num ?></span><span><?= e($label) ?></span><?php if ($key === 'notifications' && $unread): ?><em class="biji" data-notification-count><?= $unread ?></em><?php endif; ?></a>
        <?php endforeach; ?>
        <form method="post" class="logout-inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button aria-label="Keluar dari akun">Keluar <span aria-hidden="true">↗</span></button></form>
    </nav>
</div>
<main class="kertas" id="isi">
    <div class="kolom"><?= $content ?></div>
    <footer class="kolofon"><a class="footer-wordmark" href="<?= e(url('dashboard')) ?>">MOTORA.</a><span>Dua roda. Banyak cerita.</span><span>BUILT FOR THE RIDE <span aria-hidden="true">↗</span></span></footer>
</main>
<?php else: ?>
<div class="auth-latar">
    <div class="auth-grid">
        <aside class="auth-manifesto">
            <img class="auth-photo" src="assets/images/open-road.jpg" alt="Rider mengendarai motor di jalan terbuka saat matahari terbenam" fetchpriority="high">
            <div class="auth-visual-top"><a class="motora-brand plate" href="<?= e(url('login')) ?>" aria-label="MOTORA"><?= $brandSymbol ?><span>MOTORA.</span></a><span class="auth-visual-index">RIDER CULTURE<br>INDONESIA</span></div>
            <div class="auth-visual-copy"><span class="eyebrow">UNTUK MEREKA YANG SUKA JALAN</span><h1>KETEMU<br>DI JALAN<span>.</span></h1><p>Motor boleh beda.<br>Cerita kita ketemu di sini.</p></div>
            <div class="auth-visual-bottom"><span>SUNMORI / KOPDAR / TOURING</span><span class="auth-road-mark" aria-hidden="true">↗</span><span>DUA RODA.<br>BANYAK CERITA.</span></div>
        </aside>
        <main class="auth-kartu" id="isi">
            <div class="auth-form-top"><a class="auth-home" href="<?= e(url('login')) ?>">MOTORA<span>.</span></a><span>RIDER ACCESS <span aria-hidden="true">↗</span></span></div>
            <div class="auth-form-content"><?= $content ?></div>
            <p class="auth-colophon">© <?= e(date('Y')) ?> MOTORA<span>Ruang temu rider Indonesia.</span></p>
        </main>
    </div>
</div>
<?php endif; ?>
<?php if ($user): ?><span hidden data-delivery-poll data-poll-url="<?= e(url('messages', ['ajax' => 'deliveries'])) ?>"></span><?php endif; ?>
<script src="assets/js/app.js?v=5" defer></script>
<?php if ($user && $page === 'community'): ?><script src="assets/js/community-chat.js?v=2" defer></script><script src="assets/js/community-calls.js?v=1" defer></script><?php endif; ?>
<?php if ($user && $page === 'community_settings'): ?><script src="assets/js/community-settings.js?v=1" defer></script><?php endif; ?>
</body>
</html>
