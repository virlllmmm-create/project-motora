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
<div class="home-intro"><p><span class="live-dot" aria-hidden="true"></span>Halo, <b><?= e(explode(' ', trim($user['nama']))[0]) ?>.</b> Ada cerita apa hari ini?</p><time datetime="<?= e(date('Y-m-d')) ?>"><?= e($homeDate) ?></time></div>
<section class="ride-hero" aria-labelledby="rideTitle">
    <div class="ride-copy">
        <span class="eyebrow">RUANG TEMU RIDER INDONESIA</span>
        <h1 id="rideTitle">KETEMU<br>DI <em>JALAN<span class="red">.</span></em></h1>
        <p>Dari obrolan di garasi sampai teman di perjalanan. Temukan orang-orang yang punya cerita sejalan.</p>
        <a class="button primary" href="<?= e(url('search')) ?>">Temukan teman riding <span aria-hidden="true">↗</span></a>
        <span class="hero-caption" aria-hidden="true">MOTORA / RIDER CULTURE</span>
    </div>
    <div class="ride-photo">
        <img src="assets/images/open-road.jpg" alt="Rider menikmati jalan terbuka dengan motor saat matahari terbenam" fetchpriority="high" width="1600" height="1067">
        <span class="ride-photo-tag" aria-hidden="true">RIDE.<br>CONNECT.<br><span>REPEAT.</span></span>
        <div class="ride-photo-bottom" aria-hidden="true"><span>DUA RODA.<br>BANYAK CERITA.</span><span>↗</span></div>
    </div>
</section>
<section class="home-stats" aria-label="Ringkasan akun">
    <a class="home-stat" href="<?= e(url('garage')) ?>"><b><?= str_pad((string) $motorCount, 2, '0', STR_PAD_LEFT) ?></b><span>Motor di garasi<small>Mesin &amp; ceritamu</small></span><i aria-hidden="true">↗</i></a>
    <a class="home-stat" href="<?= e(url('friends')) ?>"><b><?= str_pad((string) $friendCount, 2, '0', STR_PAD_LEFT) ?></b><span>Teman sejalan<small>Koneksi antar rider</small></span><i aria-hidden="true">↗</i></a>
    <a class="home-stat" href="<?= e(url('communities')) ?>"><b><?= str_pad((string) $communityCount, 2, '0', STR_PAD_LEFT) ?></b><span>Komunitasmu<small>Tempat kumpul bersama</small></span><i aria-hidden="true">↗</i></a>
</section>
<div class="home-lower">
    <section aria-labelledby="communityRecommendationsTitle">
        <div class="home-section-title"><div><span class="eyebrow">01 / TEMUKAN CIRCLE-MU</span><h2 id="communityRecommendationsTitle">KENALAN DULU.<br>JALAN BARENG NANTI.</h2></div><a class="text-link" href="<?= e(url('communities')) ?>">Lihat semua ↗</a></div>
        <div class="home-community-list">
            <?php foreach ($communities as $index => $c): ?>
            <a class="home-community" href="<?= e(url('community', ['id' => $c['id']])) ?>">
                <span class="home-community-index" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <div class="home-community-image"><?php if ($c['image']): ?><img src="<?= e($c['image']) ?>" alt="" loading="lazy" width="76" height="76"><?php else: ?><span aria-hidden="true">M.</span><?php endif; ?></div>
                <div class="home-community-copy"><span class="eyebrow"><?= e($c['motorcycle_type'] ?: 'KOMUNITAS RIDER') ?></span><h3><?= e($c['name']) ?></h3><p><?= e($c['city'] ?: 'Indonesia') ?> / <?= (int) $c['members'] ?> anggota</p></div><span class="home-community-arrow" aria-hidden="true">↗</span>
            </a>
            <?php endforeach; ?>
            <?php if (!$communities): ?><div class="empty-state"><h3>Circle pertama dimulai dari kamu.</h3><p>Belum ada komunitas publik. Ajak teman dan buat tempat kumpul kalian.</p><a class="text-link" href="<?= e(url('communities')) ?>#buat-komunitas">Buat komunitas ↗</a></div><?php endif; ?>
        </div>
    </section>
    <section class="home-garage <?= $homeMotor ? 'has-motor' : '' ?>" aria-labelledby="homeGarageTitle">
        <span class="eyebrow">02 / DARI GARASIMU</span>
        <?php if ($homeMotor): ?>
        <h2 id="homeGarageTitle"><?= e($homeMotor['brand'] . ' ' . $homeMotor['model']) ?><span>.</span></h2><p><?= e(implode(' / ', array_filter([$homeMotor['type'], $homeMotor['year']]))) ?></p>
        <?php if ($homeMotor['main_photo']): ?><img class="home-garage-photo" src="<?= e($homeMotor['main_photo']) ?>" alt="<?= e($homeMotor['brand'] . ' ' . $homeMotor['model']) ?>" loading="lazy"><?php endif; ?>
        <a class="button" href="<?= e(url('garage')) ?>">Buka garasi saya <span aria-hidden="true">↗</span></a>
        <?php else: ?>
        <h2 id="homeGarageTitle">SETIAP MOTOR<br>PUNYA<br>CERITA.</h2><p>Mulai dari motor yang kamu rawat. Isi garasimu, bagikan detailnya, dan kenalan dengan sesama rider.</p><a class="button" href="<?= e(url('garage')) ?>#form-motor">Isi garasi saya <span aria-hidden="true">↗</span></a><span class="garage-number" aria-hidden="true">00</span>
        <?php endif; ?>
    </section>
</div>
