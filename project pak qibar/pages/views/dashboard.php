<?php
$query = $pdo->prepare('SELECT COUNT(*) FROM motorcycles WHERE user_id = ?');
$query->execute([$uid]);
$motorCount = (int) $query->fetchColumn();
$query = $pdo->prepare("SELECT COUNT(*) FROM friend_requests f JOIN `user` other ON other.id_user=IF(f.sender_id=?,f.receiver_id,f.sender_id) WHERE f.status='accepted' AND (f.sender_id=? OR f.receiver_id=?) AND " . not_blocked_sql('other.id_user', $uid));
$query->execute([$uid, $uid, $uid]);
$friendCount = (int) $query->fetchColumn();
$query = $pdo->prepare("SELECT COUNT(*) FROM community_members cm JOIN communities c ON c.id=cm.community_id WHERE cm.user_id=? AND cm.status='approved' AND " . community_access_sql('c', 'cm', $uid));
$query->execute([$uid]);
$communityCount = (int) $query->fetchColumn();
$query = $pdo->prepare("SELECT c.*, COUNT(DISTINCT CASE WHEN cm.status='approved' THEN cm.user_id END) AS members FROM communities c LEFT JOIN community_members cm ON cm.community_id=c.id AND cm.status='approved' WHERE c.is_private=0 AND c.closed_at IS NULL AND " . community_access_sql('c', 'cm', $uid) . ' AND ' . not_blocked_sql('c.owner_id', $uid) . ' GROUP BY c.id ORDER BY members DESC,c.created_at DESC LIMIT 3');
$query->execute();
$communities = $query->fetchAll();
$query = $pdo->prepare('SELECT * FROM motorcycles WHERE user_id=? ORDER BY is_primary DESC,created_at DESC LIMIT 1');
$query->execute([$uid]);
$homeMotor = $query->fetch();
$dayNames = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$monthNames = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$homeDate = $dayNames[(int) date('w')] . ', ' . date('j') . ' ' . $monthNames[(int) date('n')] . ' ' . date('Y');
?>
<div class="home-welcome mt-home-welcome">
    <div><h1>Halo, <?= e(explode(' ', trim($user['nama']))[0]) ?>.</h1><p>Temukan teman baru untuk perjalanan berikutnya.</p></div>
    <time class="home-date" datetime="<?= e(date('Y-m-d')) ?>"><?= e($homeDate) ?></time>
</div>
<section class="mt-home-hero" aria-labelledby="rideTitle">
    <div class="mt-home-copy">
        <div class="mt-home-kicker"><span class="mt-home-signal" aria-hidden="true"></span> RUANG TEMU RIDER INDONESIA <span class="mt-home-edition" aria-hidden="true">/ 01</span></div>
        <h2 id="rideTitle">KELUAR<br>GARASI.<br><em>TEMUKAN CERITA.</em></h2>
        <p>Motor boleh berbeda. Arah kita sama.<br>Kenalan dengan teman untuk perjalanan berikutnya.</p>
        <div class="mt-home-actions"><a class="button primary" href="<?= e(url('search')) ?>">Temukan teman riding <span aria-hidden="true">↗</span></a><a class="mt-home-secondary" href="<?= e(url('communities')) ?>">Jelajahi komunitas <span aria-hidden="true">→</span></a></div>
    </div>
    <figure class="mt-home-photo">
        <img src="assets/images/open-road.jpg" alt="Rider menikmati perjalanan dengan motor klasik menjelang matahari terbenam" fetchpriority="high" width="1600" height="1067">
        <span class="mt-home-photo-top">BERANGKAT DARI RASA YANG SAMA.</span>
        <svg class="mt-home-route" viewBox="0 0 130 175" fill="none" aria-hidden="true"><path d="M106 13v30c0 27-81 16-81 48s74 16 74 48v19" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 5"/><circle cx="106" cy="13" r="5" fill="currentColor"/><circle cx="99" cy="158" r="8" stroke="currentColor" stroke-width="1.5"/><circle cx="99" cy="158" r="3" fill="currentColor"/></svg>
        <figcaption><span>JALAN SELALU PUNYA CERITA.</span><strong>KITA CARI<br>BARENG.</strong><span class="mt-home-photo-arrow" aria-hidden="true">↗</span></figcaption>
    </figure>
</section>
<section class="mt-home-stats" aria-label="Ringkasan akun">
    <a class="mt-home-stat" href="<?= e(url('garage')) ?>"><span class="mt-home-stat-number"><?= str_pad((string) $motorCount, 2, '0', STR_PAD_LEFT) ?></span><span class="mt-home-stat-copy"><span class="mt-home-stat-label">GARASI PRIBADI</span><span>Motor yang kamu rawat</span></span><span class="mt-home-stat-arrow" aria-hidden="true">↗</span></a>
    <a class="mt-home-stat" href="<?= e(url('friends')) ?>"><span class="mt-home-stat-number"><?= str_pad((string) $friendCount, 2, '0', STR_PAD_LEFT) ?></span><span class="mt-home-stat-copy"><span class="mt-home-stat-label">TEMAN SEJALAN</span><span>Kenalan, lalu jalan bareng</span></span><span class="mt-home-stat-arrow" aria-hidden="true">↗</span></a>
    <a class="mt-home-stat" href="<?= e(url('communities')) ?>"><span class="mt-home-stat-number"><?= str_pad((string) $communityCount, 2, '0', STR_PAD_LEFT) ?></span><span class="mt-home-stat-copy"><span class="mt-home-stat-label">KOMUNITASMU</span><span>Tempat berbagi cerita</span></span><span class="mt-home-stat-arrow" aria-hidden="true">↗</span></a>
</section>
<div class="mt-home-lower">
    <section class="mt-home-discovery" aria-labelledby="communityRecommendationsTitle">
        <div class="mt-home-section-meta"><span>01 / CARI TEMAN SATU FREKUENSI</span><span aria-hidden="true">↘</span></div>
        <div class="mt-home-section-title"><div><h2 id="communityRecommendationsTitle">ADA TEMPAT<br>BUAT CERITAMU.</h2><p>Temukan komunitas yang terasa seperti rumah.</p></div><a class="text-link" href="<?= e(url('communities')) ?>">Lihat semua <span aria-hidden="true">↗</span></a></div>
        <div class="mt-home-community-list">
            <?php foreach ($communities as $c): ?>
            <a class="mt-home-community" href="<?= e(url('community', ['id' => $c['id']])) ?>">
                <div class="mt-home-community-image"><?php if ($c['image']): ?><img src="<?= e($c['image']) ?>" alt="" loading="lazy" width="64" height="64"><?php else: ?><span aria-hidden="true">m.</span><?php endif; ?></div>
                <div class="mt-home-community-copy"><span class="eyebrow"><?= e($c['motorcycle_type'] ?: 'Komunitas rider') ?></span><h3><?= e($c['name']) ?></h3><p><?= e($c['city'] ?: 'Indonesia') ?> <span aria-hidden="true">/</span> <?= (int) $c['members'] ?> anggota</p></div><span class="mt-home-community-arrow" aria-hidden="true">↗</span>
            </a>
            <?php endforeach; ?>
            <?php if (!$communities): ?><div class="mt-home-community-empty"><span class="mt-home-empty-mark" aria-hidden="true">↗</span><div><h3>Perjalanan besar dimulai dari satu ajakan.</h3><p>Belum ada komunitas untuk ditampilkan. Jadilah yang pertama mengajak teman berkumpul.</p><a class="text-link" href="<?= e(url('communities')) ?>#buat-komunitas">Buat komunitas <span aria-hidden="true">↗</span></a></div></div><?php endif; ?>
        </div>
    </section>
    <section class="mt-home-garage <?= $homeMotor ? 'has-motor' : '' ?>" aria-labelledby="homeGarageTitle">
        <div class="mt-home-section-meta"><span>02 / GARASI SAYA</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10l9-7 9 7v11H3z M7 21V11h10v10 M7 15h10 M7 18h10"/></svg></div>
        <?php if ($homeMotor): ?>
            <?php if ($homeMotor['main_photo']): ?><img class="mt-home-garage-photo" src="<?= e($homeMotor['main_photo']) ?>" alt="<?= e($homeMotor['brand'] . ' ' . $homeMotor['model']) ?>" loading="lazy"><?php endif; ?>
            <h3 id="homeGarageTitle"><?= e($homeMotor['brand'] . ' ' . $homeMotor['model']) ?></h3><p><?= e(implode(' · ', array_filter([$homeMotor['type'], $homeMotor['year']]))) ?></p>
            <a class="mt-home-garage-link" href="<?= e(url('garage')) ?>">Buka garasi saya <span aria-hidden="true">↗</span></a>
        <?php else: ?>
            <h3 id="homeGarageTitle">SETIAP MOTOR<br>PUNYA CERITA.</h3><p>Kenalkan motor yang kamu rawat.<br>Di sinilah ceritanya dimulai.</p>
            <div class="mt-home-garage-placeholder" aria-hidden="true"><span>GARASI / 00</span><svg viewBox="0 0 180 96" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="41" cy="67" r="21"/><circle cx="137" cy="67" r="21"/><circle cx="41" cy="67" r="15" stroke-width=".75"/><circle cx="137" cy="67" r="15" stroke-width=".75"/><path d="M68 37c2-15 28-19 39-4l6 10H68Z" fill="currentColor" fill-opacity=".1"/><path d="m137 67-15-46h-15 M118 31h17 M38 67l25-25h47 M41 67h55l15-24 M29 42c10-7 25-8 39-5l12 18 M52 27h30 M70 45l7 22h18l11-22 M85 52v9 M32 48c-12 5-16 13-16 20 M118 51c10-11 25-10 35 0"/></svg><span>SIAP DIISI CERITA</span></div>
            <a class="mt-home-garage-link" href="<?= e(url('garage')) ?>#form-motor">Tambahkan motor pertama <span aria-hidden="true">+</span></a>
        <?php endif; ?>
    </section>
</div>
