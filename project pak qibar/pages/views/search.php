<?php
$filters = [
    'type' => trim((string) ($_GET['type'] ?? '')),
    'brand' => trim((string) ($_GET['brand'] ?? '')),
    'model' => trim((string) ($_GET['model'] ?? '')),
    'city' => trim((string) ($_GET['city'] ?? '')),
    'region' => trim((string) ($_GET['region'] ?? '')),
];
$perPage = 9;
$requestedPage = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT);
$requestedPage = $requestedPage === false ? 1 : max(1, (int) $requestedPage);
$where = [
    'u.id_user<>?',
    'COALESCE(s.is_searchable,1)=1',
    visibility_sql('s', 'profile_visibility', 'u.id_user', $uid),
    not_blocked_sql('u.id_user', $uid),
];
$args = [$uid];
$bikeConditions = [];
$bikeArgs = [];
foreach (['type' => 'type', 'brand' => 'brand', 'model' => 'model'] as $key => $column) {
    if ($filters[$key] !== '') {
        $bikeConditions[] = "mf.{$column} LIKE ?";
        $bikeArgs[] = '%' . $filters[$key] . '%';
    }
}
if ($bikeConditions) {
    $where[] = visibility_sql('s', 'motorcycles_visibility', 'u.id_user', $uid);
    $where[] = 'EXISTS (SELECT 1 FROM motorcycles mf WHERE mf.user_id=u.id_user AND ' . implode(' AND ', $bikeConditions) . ')';
    array_push($args, ...$bikeArgs);
}
$locationConditions = [];
foreach (['city' => 'u.city', 'region' => 'u.region'] as $key => $column) {
    if ($filters[$key] !== '') {
        $locationConditions[] = "{$column} LIKE ?";
        $args[] = '%' . $filters[$key] . '%';
    }
}
if ($locationConditions) {
    $where[] = 'COALESCE(s.show_city,1)=1';
    $where[] = '(' . implode(' OR ', $locationConditions) . ')';
}
$whereSql = implode(' AND ', $where);
$count = $pdo->prepare(
    'SELECT COUNT(*) FROM `user` u LEFT JOIN user_settings s ON s.user_id=u.id_user WHERE ' . $whereSql,
);
$count->execute($args);
$total = (int) $count->fetchColumn();
$pagination = pagination_state($total, $perPage, $requestedPage);
$motorVisible = visibility_sql('s', 'motorcycles_visibility', 'u.id_user', $uid);
$galleryVisible = visibility_sql('s', 'gallery_visibility', 'u.id_user', $uid) . ' AND ' . not_blocked_sql('u.id_user', $uid);
$query = $pdo->prepare(
    'SELECT u.id_user,u.nama,u.username,
        CASE WHEN COALESCE(s.show_city,1)=1 THEN u.city ELSE NULL END AS city,
        CASE WHEN COALESCE(s.show_city,1)=1 THEN u.region ELSE NULL END AS region,
        u.profile_photo,
        GROUP_CONCAT(DISTINCT CASE WHEN ' . $motorVisible . ' THEN CONCAT(m.brand,\' \',m.model) END SEPARATOR \' · \') AS bikes,
        GROUP_CONCAT(DISTINCT CASE WHEN (' . $galleryVisible . ') AND (' . $motorVisible . ') THEN m.main_photo END) AS photos
     FROM `user` u
     LEFT JOIN user_settings s ON s.user_id=u.id_user
     LEFT JOIN motorcycles m ON m.user_id=u.id_user
     WHERE ' . $whereSql . '
     GROUP BY u.id_user
     ORDER BY u.nama,u.id_user
     LIMIT ' . $pagination['per_page'] . ' OFFSET ' . $pagination['offset'],
);
$query->execute($args);
$results = $query->fetchAll();
?>
<section class="page-heading">
    <div>
        <div class="eyebrow">TEMUKAN TEMAN SEJALAN</div>
        <h1>Pencarian rider</h1>
        <p>Temukan rider lewat motor dan daerahnya.</p>
    </div>
</section>
<form method="get" class="filter-panel">
    <input type="hidden" name="page" value="search">
    <label>Tipe motor<input name="type" value="<?= e($filters['type']) ?>" placeholder="Sport, touring, matic"></label>
    <label>Merk<input name="brand" value="<?= e($filters['brand']) ?>" placeholder="Yamaha"></label>
    <label>Model<input name="model" value="<?= e($filters['model']) ?>" placeholder="R15"></label>
    <label>Kota<input name="city" value="<?= e($filters['city']) ?>" placeholder="Jakarta"></label>
    <label>Daerah<input name="region" value="<?= e($filters['region']) ?>" placeholder="Jakarta Barat"></label>
    <button class="button primary">Cari rider →</button>
</form>
<div class="result-caption"><?= $total ?> rider ditemukan</div>
<section class="rider-grid">
    <?php foreach ($results as $r): ?>
        <article class="rider-card">
            <div class="rider-card-top"><?= avatar($r['profile_photo'], $r['nama']) ?></div>
            <h3><?= e($r['nama']) ?></h3>
            <div class="muted">@<?= e($r['username']) ?></div>
            <p class="location">⌖ <?= e(implode(', ', array_filter([$r['city'], $r['region']]))) ?: 'Lokasi privat' ?></p>
            <?php if ($r['bikes']): ?><span class="tag"><?= e($r['bikes']) ?></span><?php endif; ?>
            <?php if ($r['photos']): ?>
                <div class="search-thumbs">
                    <?php foreach (array_slice(explode(',', $r['photos']), 0, 3) as $img): if ($img): ?>
                        <img src="<?= e($img) ?>" alt="Foto motor">
                    <?php endif; endforeach; ?>
                </div>
            <?php endif; ?>
            <a class="button subtle full" href="<?= e(profile_link((int) $r['id_user'])) ?>">Lihat profil <span>→</span></a>
        </article>
    <?php endforeach; ?>
    <?php if (!$results): ?><div class="empty-state">Tidak ada rider yang cocok. Coba ubah filter pencarian.</div><?php endif; ?>
</section>
<?php if ($pagination['total_pages'] > 1): ?>
    <nav class="pagination" aria-label="Halaman pencarian">
        <?php foreach (pagination_pages($pagination['page'], $pagination['total_pages']) as $number): ?>
            <a class="<?= $number === $pagination['page'] ? 'selected' : '' ?>"
                href="<?= e(url('search', array_merge($filters, ['p' => $number]))) ?>"
                <?= $number === $pagination['page'] ? 'aria-current="page"' : '' ?>><?= $number ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
