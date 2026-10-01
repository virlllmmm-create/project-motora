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
<div class="home-welcome">
    <div><h1>Halo, <?= e(explode(' ', trim($user['nama']))[0]) ?>.</h1><p>Temukan teman baru untuk perjalanan berikutnya.</p></div>
    <time class="home-date" datetime="<?= e(date('Y-m-d')) ?>"><?= e($homeDate) ?></time>
</div>
<section class="ride-hero" aria-labelledby="rideTitle">
    <div class="ride-photo"><img src="assets/images/open-road.jpg" alt="Rider menikmati jalan terbuka saat matahari terbenam" fetchpriority="high" width="1600" height="1067"></div>
    <div class="ride-copy">
        <span class="ride-label"><span aria-hidden="true"></span> Ruang temu rider Indonesia</span>
        <h2 id="rideTitle">Rute berbeda.<br>Cerita yang sama.</h2>
        <p>Dari obrolan soal motor sampai teman di perjalanan. Temukan rider yang cocok dengan ceritamu.</p>
        <a class="button" href="<?= e(url('search')) ?>">Temukan teman riding <span aria-hidden="true">↗</span></a>
    </div>
    <span class="ride-photo-note">Sunmori · Kopdar · Touring</span>
</section>
<section class="home-stats" aria-label="Ringkasan akun">
    <a class="home-stat" href="<?= e(url('garage')) ?>"><span class="home-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10l9-7 9 7v11H3z M7 21V11h10v10 M7 15h10 M7 18h10"/></svg></span><span class="home-stat-copy"><b><?= $motorCount ?></b><span>Motor di garasi</span></span><i aria-hidden="true">↗</i></a>
    <a class="home-stat" href="<?= e(url('friends')) ?>"><span class="home-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a7 7 0 0 0-14 0v2 M17 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M2 8h4 M4 6v4"/></svg></span><span class="home-stat-copy"><b><?= $friendCount ?></b><span>Teman sejalan</span></span><i aria-hidden="true">↗</i></a>
    <a class="home-stat" href="<?= e(url('communities')) ?>"><span class="home-stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0 M22 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 0 1 0 7.75"/></svg></span><span class="home-stat-copy"><b><?= $communityCount ?></b><span>Komunitasmu</span></span><i aria-hidden="true">↗</i></a>
</section>
<div class="home-lower">
    <section class="home-discovery" aria-labelledby="communityRecommendationsTitle">
        <div class="home-section-title"><div><h2 id="communityRecommendationsTitle">Temukan circle-mu</h2><p>Kenalan dulu, jalan bareng nanti.</p></div><a class="text-link" href="<?= e(url('communities')) ?>">Lihat semua ↗</a></div>
        <div class="home-community-list">
            <?php foreach ($communities as $c): ?>
            <a class="home-community" href="<?= e(url('community', ['id' => $c['id']])) ?>">
                <div class="home-community-image"><?php if ($c['image']): ?><img src="<?= e($c['image']) ?>" alt="" loading="lazy" width="58" height="58"><?php else: ?><span aria-hidden="true">m.</span><?php endif; ?></div>
                <div class="home-community-copy"><span class="eyebrow"><?= e($c['motorcycle_type'] ?: 'Komunitas rider') ?></span><h3><?= e($c['name']) ?></h3><p><?= e($c['city'] ?: 'Indonesia') ?> · <?= (int) $c['members'] ?> anggota</p></div><span class="home-community-arrow" aria-hidden="true">›</span>
            </a>
            <?php endforeach; ?>
            <?php if (!$communities): ?><div class="empty-state"><h3>Mulai circle pertamamu.</h3><p>Ajak teman dan buat tempat kumpul kalian.</p><a class="text-link" href="<?= e(url('communities')) ?>#buat-komunitas">Buat komunitas ↗</a></div><?php endif; ?>
        </div>
    </section>
    <section class="home-garage <?= $homeMotor ? 'has-motor' : '' ?>" aria-labelledby="homeGarageTitle">
        <div class="home-garage-head"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 10l9-7 9 7v11H3z M7 21V11h10v10 M7 15h10 M7 18h10"/></svg><h2>Garasi saya</h2></div>
        <?php if ($homeMotor): ?>
            <?php if ($homeMotor['main_photo']): ?><img class="home-garage-photo" src="<?= e($homeMotor['main_photo']) ?>" alt="<?= e($homeMotor['brand'] . ' ' . $homeMotor['model']) ?>" loading="lazy"><?php endif; ?>
            <h3 id="homeGarageTitle"><?= e($homeMotor['brand'] . ' ' . $homeMotor['model']) ?></h3><p><?= e(implode(' · ', array_filter([$homeMotor['type'], $homeMotor['year']]))) ?></p>
            <a class="button primary" href="<?= e(url('garage')) ?>">Buka garasi saya <span aria-hidden="true">↗</span></a>
        <?php else: ?>
            <div class="home-garage-placeholder" aria-hidden="true"><svg viewBox="0 0 180 96" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="41" cy="67" r="21"/><circle cx="137" cy="67" r="21"/><path d="M68 37c2-15 28-19 39-4l6 10H68Z" fill="currentColor" fill-opacity=".15"/><path d="m137 67-15-46h-15 M118 31h17 M38 67l25-25h47 M41 67h55l15-24 M29 42c10-7 25-8 39-5l12 18 M52 27h30 M70 45l7 22h18l11-22 M85 52v9 M32 48c-12 5-16 13-16 20 M118 51c10-11 25-10 35 0"/></svg></div>
            <h3 id="homeGarageTitle">Motor pertamamu<br>mulai di sini.</h3><p>Tambahkan motor yang kamu rawat dan kenalan dengan sesama rider.</p>
            <a class="button primary" href="<?= e(url('garage')) ?>#form-motor">Tambah motor <span aria-hidden="true">+</span></a>
        <?php endif; ?>
    </section>
</div>
