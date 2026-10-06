<?php
$requestedId = $_GET['id'] ?? $uid;
$id = is_string($requestedId) || is_int($requestedId)
    ? filter_var($requestedId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$profile = false;
if ($id !== false) {
    $query = $pdo->prepare('SELECT id_user,nama,username,city,region,bio,profile_photo,created_at FROM `user` WHERE id_user=?');
    $query->execute([$id]);
    $profile = $query->fetch();
}
if (!$profile || !can_view($id, 'profile_visibility')) {
    http_response_code(404);
    ?>
    <section class="rider-unavailable panel">
        <span class="rider-empty-icon" aria-hidden="true">◎</span>
        <div class="eyebrow">PROFIL RIDER</div>
        <h1>Profil belum tersedia</h1>
        <p>Profil tidak ditemukan atau aksesnya dibatasi oleh pemilik akun.</p>
        <div class="rider-empty-actions"><a class="button primary" href="<?= e(url('search')) ?>">Cari rider</a><a class="button subtle" href="<?= e(url('friends')) ?>">Kembali ke teman</a></div>
    </section>
    <?php
    return;
}
$isSelf = $id === $uid;
$friend = !$isSelf && are_friends($uid, $id);
$showCity = $isSelf || (int) setting($id, 'show_city', 1) === 1;
$canRequestFriend = !$isSelf && (int) setting($id, 'is_searchable', 1) === 1;
$showMotors = can_view($id, 'motorcycles_visibility');
$showGallery = $showMotors && can_view($id, 'gallery_visibility');
$relation = false;
if (!$isSelf) {
    $query = $pdo->prepare("SELECT id,status,sender_id,receiver_id FROM friend_requests WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY (status='pending') DESC,updated_at DESC,id DESC LIMIT 1");
    $query->execute([$uid, $id, $id, $uid]);
    $relation = $query->fetch();
}
$query = $pdo->prepare("SELECT COUNT(DISTINCT u.id_user) FROM friend_requests f JOIN `user` u ON u.id_user=IF(f.sender_id=?,f.receiver_id,f.sender_id) LEFT JOIN user_settings s ON s.user_id=u.id_user WHERE f.status='accepted' AND (f.sender_id=? OR f.receiver_id=?) AND u.id_user<>? AND (u.id_user=? OR (" . visibility_sql('s', 'profile_visibility', 'u.id_user', $uid) . '))');
$query->execute([$id, $id, $id, $id, $uid]);
$profileFriends = (int) $query->fetchColumn();
$profileMotors = [];
if ($showMotors) {
    $query = $pdo->prepare('SELECT m.* FROM motorcycles m LEFT JOIN user_settings owner_settings ON owner_settings.user_id=m.user_id WHERE m.user_id=? AND ' . visibility_sql('owner_settings', 'motorcycles_visibility', 'm.user_id', $uid) . ' ORDER BY m.is_primary DESC,m.created_at DESC,m.id DESC');
    $query->execute([$id]);
    $profileMotors = $query->fetchAll();
}
$communityScope = "FROM community_members cm JOIN communities c ON c.id=cm.community_id LEFT JOIN community_members mine ON mine.community_id=c.id AND mine.user_id=? AND mine.status='approved' WHERE cm.user_id=? AND cm.status='approved' AND " . community_access_sql('c', 'mine', $uid) . ' AND ' . not_blocked_sql('cm.user_id', $uid);
$query = $pdo->prepare('SELECT COUNT(DISTINCT c.id) ' . $communityScope);
$query->execute([$uid, $id]);
$profileCommunities = (int) $query->fetchColumn();
$query = $pdo->prepare('SELECT c.id,c.name,c.city,c.image,c.motorcycle_type ' . $communityScope . ' ORDER BY c.name,c.id LIMIT 12');
$query->execute([$uid, $id]);
$profileCommunityRows = $query->fetchAll();
$profileGallery = [];
if ($showGallery) {
    $gallerySql = visibility_sql('gallery_settings', 'gallery_visibility', 'gm.user_id', $uid) . ' AND ' . visibility_sql('gallery_settings', 'motorcycles_visibility', 'gm.user_id', $uid);
    $query = $pdo->prepare('SELECT gp.path FROM motorcycle_photos gp JOIN motorcycles gm ON gm.id=gp.motorcycle_id LEFT JOIN user_settings gallery_settings ON gallery_settings.user_id=gm.user_id WHERE gm.user_id=? AND ' . $gallerySql . ' ORDER BY gp.id DESC LIMIT 24');
    $query->execute([$id]);
    $profileGallery = array_slice(array_values(array_unique(array_filter(array_merge(array_column($profileMotors, 'main_photo'), $query->fetchAll(PDO::FETCH_COLUMN))))), 0, 24);
}
$location = implode(', ', array_filter([$profile['city'] ?? '', $profile['region'] ?? ''], static fn($part) => trim((string) $part) !== ''));
$joinedTimestamp = empty($profile['created_at']) || str_starts_with((string) $profile['created_at'], '0000-') ? false : strtotime((string) $profile['created_at']);
$months = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$joinedLabel = $joinedTimestamp !== false ? $months[(int) date('n', $joinedTimestamp)] . ' ' . date('Y', $joinedTimestamp) : null;
?>
<section class="profile-banner rider-hero">
    <div class="profile-cover" aria-hidden="true"><span class="rider-cover-ring"></span></div>
    <div class="profile-main">
        <div class="rider-avatar" data-profile-avatar data-initial="<?= e(mb_strtoupper(mb_substr($profile['nama'], 0, 1))) ?>"><?= avatar($profile['profile_photo'], $profile['nama']) ?></div>
        <div class="profile-identity"><div class="eyebrow"><?= $isSelf ? 'PROFIL SAYA' : 'RIDER MOTORA' ?></div><h1><?= e($profile['nama']) ?></h1><span>@<?= e($profile['username']) ?></span><?php if ($friend): ?><span class="rider-friend-badge">✓ Teman riding</span><?php endif; ?></div>
        <div class="profile-buttons">
            <?php if ($isSelf): ?>
                <a class="button primary" href="<?= e(url('settings')) ?>">Edit profil <span aria-hidden="true">↗</span></a>
            <?php elseif ($friend): ?>
                <a class="button primary" href="<?= e(url('messages', ['to'=>$id])) ?>">Kirim pesan <span aria-hidden="true">↗</span></a>
            <?php elseif ($relation && $relation['status'] === 'pending' && (int) $relation['sender_id'] === $uid): ?>
                <span class="rider-status">Permintaan terkirim</span><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="friend_action"><input type="hidden" name="request_id" value="<?= (int) $relation['id'] ?>"><button class="button subtle" name="op" value="cancel">Batalkan</button></form>
            <?php elseif ($relation && $relation['status'] === 'pending'): ?>
                <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="friend_action"><input type="hidden" name="request_id" value="<?= (int) $relation['id'] ?>"><button class="button primary" name="op" value="accept">Terima teman</button><button class="button subtle" name="op" value="reject">Tolak</button></form>
            <?php elseif ($canRequestFriend): ?>
                <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="friend_request"><input type="hidden" name="user_id" value="<?= $id ?>"><button class="button primary">+ Tambah teman</button></form>
            <?php else: ?><span class="rider-status">Permintaan teman tidak tersedia</span><?php endif; ?>
            <?php if (!$isSelf): ?><details class="rider-more"><summary aria-label="Pilihan lainnya untuk rider">•••</summary><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="friend_block"><input type="hidden" name="user_id" value="<?= $id ?>"><button type="submit">Blokir rider</button></form></details><?php endif; ?>
        </div>
    </div>
    <div class="profile-stats"><div><b><?= $profileFriends ?></b><span>Teman rider</span></div><div><b><?= $profileCommunities ?></b><span>Komunitas</span></div><div><b><?= $showMotors ? count($profileMotors) : '—' ?></b><span>Motor <?= $showMotors ? 'di garasi' : 'privat' ?></span></div></div>
</section>
<div class="rider-layout">
    <section class="panel rider-about">
        <div class="section-head"><div><div class="eyebrow">CERITA DI BALIK RIDER</div><h2>Tentang <?= $isSelf ? 'saya' : 'rider' ?></h2></div></div>
        <p class="rider-bio"><?= trim((string) ($profile['bio'] ?? '')) !== '' ? nl2br(e($profile['bio'])) : ($isSelf ? 'Tambahkan ceritamu di Edit profil. Motor favorit, rute pilihan, atau alasan kamu suka riding.' : 'Rider ini belum menambahkan cerita. Kenalan lewat pesan dan temukan kesamaan kalian.') ?></p>
        <div class="rider-meta"><?php if ($showCity): ?><span><span aria-hidden="true">⌖</span> <?= e($location !== '' ? $location : ($isSelf ? 'Tambahkan lokasi di Edit profil' : 'Lokasi belum ditambahkan')) ?></span><?php else: ?><span><span aria-hidden="true">⌖</span> Lokasi disembunyikan</span><?php endif; ?><?php if ($joinedLabel): ?><span><span aria-hidden="true">◷</span> Bergabung <?= e($joinedLabel) ?></span><?php endif; ?></div>
        <?php if (!$isSelf): ?><form method="post" class="message-box"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="message_send"><input type="hidden" name="to_user" value="<?= $id ?>"><label for="riderMessage">Sapa rider ini</label><textarea id="riderMessage" name="body" required rows="3" maxlength="4000" placeholder="Hai, salam kenal! Biasanya riding ke mana?"></textarea><button class="button subtle" type="submit">Kirim pesan <span aria-hidden="true">→</span></button></form><?php endif; ?>
    </section>
    <section class="panel rider-garage" id="riderGarage">
        <div class="section-head"><div><div class="eyebrow">KAWAN DI PERJALANAN</div><h2>Garasi rider</h2></div><?php if ($isSelf): ?><a class="text-link" href="<?= e(url('garage')) ?>">Kelola garasi ↗</a><?php endif; ?></div>
        <?php if (!$showMotors): ?><div class="rider-section-empty"><span aria-hidden="true">◎</span><h3>Garasi bersifat privat</h3><p>Pemilik membatasi siapa yang dapat melihat koleksi motornya.</p></div>
        <?php elseif ($profileMotors): ?><div class="profile-motor-list"><?php foreach ($profileMotors as $motor): ?><a class="profile-motor" href="<?= e(url('motorcycle', ['id'=>$motor['id']])) ?>"><?php if ($motor['main_photo'] && $showGallery): ?><img src="<?= e($motor['main_photo']) ?>" alt="<?= e($motor['brand'].' '.$motor['model']) ?>" loading="lazy"><?php else: ?><span class="bike-placeholder" aria-hidden="true">◉</span><?php endif; ?><span><b><?= e($motor['brand'].' '.$motor['model']) ?></b><small><?= e(implode(' · ', array_filter([$motor['type'] ?: 'Motor', $motor['year']]))) ?></small><?php if ($motor['is_primary']): ?><span class="rider-primary">Motor utama</span><?php endif; ?></span><span class="rider-motor-arrow" aria-hidden="true">↗</span></a><?php endforeach; ?></div>
        <?php else: ?><div class="rider-section-empty"><span aria-hidden="true">◎</span><h3>Garasi masih kosong</h3><p><?= $isSelf ? 'Tambahkan motor pertama dan ceritakan koleksimu.' : 'Belum ada motor yang ditampilkan oleh rider ini.' ?></p><?php if ($isSelf): ?><a class="button subtle" href="<?= e(url('garage')) ?>">Tambah motor →</a><?php endif; ?></div><?php endif; ?>
    </section>
</div>
<section class="panel rider-section" id="riderGallery"><div class="section-head"><div><div class="eyebrow">MOMEN DALAM PERJALANAN</div><h2>Galeri motor</h2></div><?php if ($showGallery && $profileGallery): ?><span class="rider-count"><?= count($profileGallery) ?> foto</span><?php endif; ?></div>
    <?php if (!$showGallery): ?><div class="rider-section-empty"><span aria-hidden="true">▧</span><h3>Galeri bersifat privat</h3><p>Foto mengikuti pengaturan privasi galeri dan garasi rider.</p></div>
    <?php elseif ($profileGallery): ?><div class="rider-gallery"><?php foreach ($profileGallery as $path): ?><a href="<?= e($path) ?>" target="_blank" rel="noopener" aria-label="Buka foto motor <?= e($profile['nama']) ?>"><img src="<?= e($path) ?>" alt="Foto koleksi motor <?= e($profile['nama']) ?>" loading="lazy"><span aria-hidden="true">↗</span></a><?php endforeach; ?></div>
    <?php else: ?><div class="rider-section-empty"><span aria-hidden="true">▧</span><h3>Belum ada foto motor</h3><p><?= $isSelf ? 'Foto utama dan foto tambahan motormu akan tampil di sini.' : 'Rider ini belum menambahkan foto ke koleksinya.' ?></p></div><?php endif; ?>
</section>
<section class="panel rider-section" id="riderCommunities"><div class="section-head"><div><div class="eyebrow">CIRCLE DAN KAWAN SEJALAN</div><h2>Komunitas rider</h2></div><span class="rider-count"><?= $profileCommunities ?> komunitas</span></div>
    <?php if ($profileCommunityRows): ?><div class="rider-communities"><?php foreach ($profileCommunityRows as $community): ?><a class="rider-community" href="<?= e(url('community', ['id'=>$community['id']])) ?>"><span class="rider-community-cover"<?= $community['image'] ? ' style="background-image:url('.e($community['image']).')"' : '' ?> aria-hidden="true">◈</span><span><small><?= e($community['motorcycle_type'] ?: 'Komunitas rider') ?></small><b><?= e($community['name']) ?></b><span>⌖ <?= e($community['city']) ?></span></span><span aria-hidden="true">↗</span></a><?php endforeach; ?></div><?php if ($profileCommunities > count($profileCommunityRows)): ?><p class="rider-section-description">Menampilkan <?= count($profileCommunityRows) ?> dari <?= $profileCommunities ?> komunitas yang dapat kamu lihat.</p><?php endif; ?>
    <?php else: ?><div class="rider-section-empty"><span aria-hidden="true">◈</span><h3>Belum ada komunitas</h3><p><?= $isSelf ? 'Temukan circle untuk sunmori, kopdar, dan perjalanan berikutnya.' : 'Belum ada komunitas rider ini yang dapat ditampilkan.' ?></p><?php if ($isSelf): ?><a class="button subtle" href="<?= e(url('communities')) ?>">Jelajahi komunitas →</a><?php endif; ?></div><?php endif; ?>
</section>
