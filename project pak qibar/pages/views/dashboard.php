<?php
$query = $pdo->prepare('SELECT COUNT(*) FROM motorcycles WHERE user_id = ?');
$query->execute([$uid]);
$motorCount = (int) $query->fetchColumn();

$query = $pdo->prepare(
    "SELECT COUNT(*) FROM friend_requests f JOIN `user` other ON other.id_user=IF(f.sender_id=?,f.receiver_id,f.sender_id) WHERE f.status='accepted' AND (f.sender_id=? OR f.receiver_id=?) AND " . not_blocked_sql('other.id_user', $uid),
);
$query->execute([$uid, $uid, $uid]);
$friendCount = (int) $query->fetchColumn();

$query = $pdo->prepare(
    "SELECT COUNT(*) FROM community_members cm JOIN communities c ON c.id=cm.community_id WHERE cm.user_id=? AND cm.status='approved' AND " . community_access_sql('c', 'cm', $uid),
);
$query->execute([$uid]);
$communityCount = (int) $query->fetchColumn();

$query = $pdo->prepare(
    "SELECT c.*, COUNT(DISTINCT CASE WHEN cm.status = 'approved' THEN cm.user_id END) AS members
    FROM communities c
    LEFT JOIN community_members cm ON cm.community_id = c.id AND cm.status = 'approved'
    WHERE c.is_private = 0 AND " . community_access_sql('c', 'cm', $uid) . "
    GROUP BY c.id
    ORDER BY members DESC, c.created_at DESC",
);
$query->execute();
$communities = $query->fetchAll();
?>
<section class="page-heading">
    <div>
        <div class="eyebrow">SELAMAT DATANG DI MOTORA</div>
        <h1>Halo, <?= e(explode(' ', $user['nama'])[0]) ?>.</h1>
        <p>Temui rider sekitar, obrolkan motor, dan temukan komunitasmu.</p>
    </div><a class="button primary" href="<?= e(url('search')) ?>">Cari teman riding <span>→</span></a>
</section>
<div class="dashboard-summary"><div class="summary-heading"><span>Ringkasan akun</span><span class="date-pill">◷ <?= e(date('d M Y')) ?></span></div><section class="stat-grid">
    <a class="stat-card" href="<?= e(url('garage')) ?>"><span>GARASI</span><b><?= $motorCount ?></b><small>motor terdaftar</small><i>▰</i></a>
    <a class="stat-card" href="<?= e(url('friends')) ?>"><span>JARINGAN</span><b><?= $friendCount ?></b><small>teman rider</small><i>♧</i></a>
    <a class="stat-card" href="<?= e(url('communities')) ?>"><span>KOMUNITAS</span><b><?= $communityCount ?></b><small>komunitas diikuti</small><i>◈</i></a>
</section>
</div>
<section class="discovery-panel community-recommendations" aria-labelledby="communityRecommendationsTitle"><div class="section-head">
    <div>
        <div class="eyebrow">CIRCLE PILIHAN</div>
        <h2 id="communityRecommendationsTitle">Komunitas pilihan</h2>
    </div><a class="text-link" href="<?= e(url('communities')) ?>">Jelajahi →</a>
</div>
<section class="community-grid compact" style="--recommendation-count:<?= max(1, min(3, count($communities))) ?>"<?php if (count($communities) > 3): ?> tabindex="0" aria-label="Daftar komunitas pilihan"<?php endif; ?>><?php foreach($communities as $c): ?><a
        class="community-card" href="<?= e(url('community',['id'=>$c['id']])) ?>">
        <div class="community-cover"
            style="<?= $c['image']?'background-image:url('.e($c['image']).')':'' ?>"><span
                class="community-symbol">◈</span></div>
        <div class="community-info"><span
                class="eyebrow" title="<?= e($c['motorcycle_type']?:'KOMUNITAS RIDER') ?>"><?= e($c['motorcycle_type']?:'KOMUNITAS RIDER') ?></span>
            <h3 title="<?= e($c['name']) ?>"><?= e($c['name']) ?></h3>
            <p title="<?= e($c['city']) ?> · <?= (int)$c['members'] ?> anggota">⌖ <?= e($c['city']) ?> · <?= (int)$c['members'] ?> anggota</p>
        </div>
    </a><?php endforeach; ?><?php if(!$communities): ?><div class="empty-state">Belum ada komunitas
        publik. Jadilah yang pertama membuat.</div><?php endif; ?></section></section>