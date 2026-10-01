<?php
$flashMessage = take_flash();
$user = current_user();
$unread = 0;
if ($user) { $q=db()->prepare('SELECT COUNT(*) FROM notifications n WHERE n.recipient_id=? AND n.read_at IS NULL AND '.notification_visibility_sql((int)$user['id_user'], 'n')); $q->execute([$user['id_user']]); $unread=(int)$q->fetchColumn(); }
$nav = [
  ['dashboard','01','Beranda'],
  ['search','02','Cari rider'],
  ['communities','03','Komunitas'],
  ['garage','04','Garasi'],
  ['friends','05','Teman'],
  ['messages','06','Pesan'],
  ['notifications','07','Aktivitas'],
  ['settings','08','Profil'],
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
$dateId = function(): string {
  $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
  $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
  $t = time();
  return $hari[(int)date('w',$t)] . ', ' . date('j',$t) . ' ' . $bulan[(int)date('n',$t)] . ' ' . date('Y',$t);
};
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="<?= $user ? '#f5f4f0' : (in_array($page, ['forgot', 'reset'], true) ? '#f2f3f0' : '#171a19') ?>"><meta name="color-scheme" content="<?= $user || in_array($page, ['forgot', 'reset'], true) ? 'light' : 'dark' ?>"><title><?= e(page_title($page)) ?> · MOTORA — Komunitas Rider</title>
  <meta name="description" content="Temukan rider, rawat garasi, dan bergabung dengan komunitas motor di MOTORA.">
  <link rel="stylesheet" href="assets/css/style.css?v=25">
<link rel="stylesheet" href="assets/css/dashboard-theme.css?v=10"><?php if($user && in_array($page,['community','community_settings'],true)): ?><link rel="stylesheet" href="assets/css/community.css?v=5"><?php endif; ?></head>
<body class="<?= $user ? 'authenticated' : 'public-page' ?><?= $user && $page==='messages' ? ' messages-page' : '' ?><?= $user && $page==='community' && !empty($communityChatPage) ? ' community-chat-page' : '' ?><?= $user && $page==='community_settings' ? ' community-settings-page' : '' ?><?= $user && $page==='dashboard' ? ' dashboard-page' : '' ?><?= $user && $page==='profile' ? ' rider-page' : '' ?><?= $user && $page==='settings' ? ' settings-page' : '' ?><?= in_array($page, ['login', 'register'], true) ? ' auth-compact' : '' ?><?= $page === 'register' ? ' auth-register' : '' ?><?= in_array($page, ['forgot', 'reset'], true) ? ' auth-compact auth-reset' : '' ?>">
<a class="skip" href="#isi">Lewati ke isi</a>
<?php if ($flashMessage): ?>
<div class="kilat <?= $flashMessage['type'] === 'error' ? 'error' : 'success' ?>" data-flash role="<?= $flashMessage['type'] === 'error' ? 'alert' : 'status' ?>" aria-atomic="true">
  <span class="flash-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php if ($flashMessage['type'] === 'error'): ?><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/><?php else: ?><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/><?php endif; ?></svg></span>
  <div class="flash-copy"><b><?= $flashMessage['type'] === 'error' ? 'Perlu perhatian' : 'Berhasil' ?></b><p><?= e($flashMessage['message']) ?></p></div>
  <button type="button" class="flash-close" data-flash-close aria-label="Tutup notifikasi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
  <span class="flash-timer" aria-hidden="true"></span>
</div>
<?php endif; ?>
<?php if ($user): ?>
<aside class="app-sidebar">
  <a class="sidebar-brand" href="<?= e(url('dashboard')) ?>"><span class="brand-mark" aria-hidden="true">M</span><span>MOTORA<small>Komunitas rider</small></span></a>
  <nav class="ledger-nav" id="mainNav" aria-label="Navigasi utama">
    <?php
    $icons = [
      'dashboard'=>'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
      'search'=>'M21 21l-5-5 M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
      'communities'=>'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75',
      'garage'=>'M3 10l9-7 9 7v11H3z M7 21V11h10v10 M7 15h10 M7 18h10',
      'friends'=>'M20 21v-2a7 7 0 0 0-14 0v2 M17 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M2 8h4 M4 6v4',
      'messages'=>'M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7A8.4 8.4 0 0 1 4 11.5 8.5 8.5 0 0 1 8.7 3.9a8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5',
      'notifications'=>'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M10 21h4',
      'settings'=>'M20 21v-2a7 7 0 0 0-14 0v2 M17 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
    ];
    foreach($nav as [$key,$num,$label]): ?>
      <?php if(in_array($key, ['dashboard','friends','settings'], true)): ?><span class="nav-section"><?= $key==='dashboard'?'Eksplorasi':($key==='friends'?'Koneksi':'Akun') ?></span><?php endif; ?>
      <a class="<?= nav_active($page,$key)?'aktif':'' ?>" <?= nav_active($page,$key)?'aria-current="page"':'' ?> data-nav-key="<?= e($key) ?>" href="<?= e(url($key)) ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= e($icons[$key]) ?>"/></svg>
        <?= e($label) ?><?php if($key==='notifications'&&$unread):?><em class="biji" data-notification-count><?= $unread ?></em><?php endif;?>
      </a>
    <?php endforeach; ?>
<form method="post" class="logout-inline"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button>Keluar dari akun <span aria-hidden="true">↗</span></button></form>
  </nav>
  <div class="sidebar-bottom">
    <div class="sidebar-note"><span class="eyebrow">TEMUKAN CIRCLE-MU</span><b>Jalan baru,<br> teman baru.</b><p>Perjalanan lebih seru bersama komunitas.</p><a href="<?= e(url('communities')) ?>">Jelajahi komunitas ↗</a><span class="note-wheel" aria-hidden="true">◎</span></div>
    
  </div>
</aside>
<header class="app-topbar">
  <div class="topbar-title"><button class="menu-btn" id="menuToggle" aria-expanded="false" aria-controls="mainNav" aria-label="Buka navigasi">☰</button><strong><?= e(page_title($page)) ?></strong></div>
  <div class="topbar-actions">
    <a class="topbar-search" href="<?= e(url('search')) ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="m15 15 6 6"/></svg><span>Cari rider &amp; motor</span></a>
    <a class="topbar-icon" href="<?= e(url('notifications')) ?>" aria-label="Aktivitas<?= $unread?' belum dibaca':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg><?php if($unread): ?><span class="notification-dot"></span><?php endif; ?></a>
    <a class="user-chip" href="<?= e(profile_link((int)$user['id_user'])) ?>" aria-label="Profil <?= e($user['nama']) ?>"><?= avatar($user['profile_photo'],$user['nama']) ?></a>
  </div>
</header>
<main class="kertas" id="isi">
  <div class="kolom">
    <p class="kicker"><?= e(page_title($page)) ?> · Komunitas rider</p>
    <?= $content ?>
  </div>
  <footer class="kolofon"><span>MOTORA · Tempat rider saling terhubung.</span><span>Sunmori · Kopdar · Touring</span></footer>
</main>
<?php else: ?>
<div class="auth-latar">
  <div class="auth-grid">
    <main class="auth-kartu" id="isi">
      <?= $content ?>
      <p class="auth-colophon mono">MOTORA · BUKU LOG RIDER</p>
    </main>
    <aside class="auth-manifesto" aria-hidden="false">
      <a class="plate" href="<?= e(url('login')) ?>" tabindex="-1"><span class="plate-main">MOTORA</span><span class="plate-sub">KOMUNITAS RIDER</span></a>
      <h1>Catatan perjalanan, garasi, dan kawan sejalan.</h1>
      <p>MOTORA itu buku log untuk rider Indonesia: siapa kamu, motor apa yang kamu rawat, dan komunitas mana yang kamu ikuti.</p>
      <ol>
        <li><b>01 — Daftarkan diri</b><span>Nama, kota, dan akun rider kamu.</span></li>
        <li><b>02 — Isi garasi</b><span>Merk, model, tahun, dan foto motor.</span></li>
        <li><b>03 — Temukan circle</b><span>Cari rider sedarah motor dan sedaerah.</span></li>
      </ol>
      <p class="mono kecil">SUNMORI · KOPDAR · TOURING — SELURUH INDONESIA</p>
    </aside>
  </div>
</div>
<?php endif; ?>
<?php if ($user): ?><span hidden data-delivery-poll data-poll-url="<?= e(url('messages',['ajax'=>'deliveries'])) ?>"></span><?php endif; ?>
<script src="assets/js/app.js?v=5" defer></script>
<?php if($user && $page==='community'): ?><script src="assets/js/community-chat.js?v=2" defer></script><script src="assets/js/community-calls.js?v=1" defer></script><?php endif; ?>
<?php if($user && $page==='community_settings'): ?><script src="assets/js/community-settings.js?v=1" defer></script><?php endif; ?>
</body></html>
