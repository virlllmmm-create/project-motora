<?php
$term = trim((string) ($_GET['q'] ?? ''));
$city = trim((string) ($_GET['city'] ?? ''));
$perPage = 9;
$requestedPage = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT);
$requestedPage = $requestedPage === false ? 1 : max(1, (int) $requestedPage);
$filters = ['q' => $term, 'city' => $city];
$where = [community_access_sql('c', 'access_member', $uid)];
$where[] = 'c.closed_at IS NULL';
$filterArgs = [];
if ($term !== '') {
    $where[] = '(c.name LIKE ? OR c.motorcycle_type LIKE ?)';
    $filterArgs[] = '%' . $term . '%';
    $filterArgs[] = '%' . $term . '%';
}
if ($city !== '') {
    $where[] = '(c.city LIKE ? OR c.region LIKE ?)';
    $filterArgs[] = '%' . $city . '%';
    $filterArgs[] = '%' . $city . '%';
}
$where[] = not_blocked_sql('c.owner_id', $uid);
$whereSql = implode(' AND ', $where);
$count = $pdo->prepare(
    'SELECT COUNT(*) FROM communities c LEFT JOIN community_members access_member ON access_member.community_id=c.id AND access_member.user_id=? AND access_member.status=\'approved\' WHERE ' . $whereSql,
);
$count->execute(array_merge([$uid], $filterArgs));
$pagination = pagination_state((int) $count->fetchColumn(), $perPage, $requestedPage);
$sql = 'SELECT c.*,COUNT(DISTINCT CASE WHEN cm.status=\'approved\' THEN cm.user_id END) AS members,
        mine.status AS my_status,mine.role AS my_role,
        (SELECT COUNT(*) FROM community_messages gm WHERE gm.community_id=c.id AND gm.id>COALESCE(mine.last_read_message_id,0) AND gm.sender_id<>?) AS unread_messages
    FROM communities c
    LEFT JOIN community_members cm ON cm.community_id=c.id
    LEFT JOIN community_members mine ON mine.community_id=c.id AND mine.user_id=?
    LEFT JOIN community_members access_member ON access_member.community_id=c.id AND access_member.user_id=? AND access_member.status=\'approved\'
    WHERE ' . $whereSql . '
    GROUP BY c.id
    ORDER BY members DESC,c.created_at DESC,c.id DESC
    LIMIT ' . $pagination['per_page'] . ' OFFSET ' . $pagination['offset'];
$query = $pdo->prepare($sql);
$query->execute(array_merge([$uid, $uid, $uid], $filterArgs));
$communities = $query->fetchAll();
?>
<section class="page-heading">
    <div>
        <div class="eyebrow">TEMUKAN CIRCLE-MU</div>
        <h1>Komunitas rider</h1>
        <p>Temukan rider dengan minat motor dan rute yang sama.</p>
    </div><a class="button primary" href="#buat-komunitas">+ Buat komunitas</a>
</section>
<form method="get" class="inline-filter">
    <input type="hidden" name="page" value="communities">
    <input type="hidden" name="p" value="1">
    <input name="q" value="<?= e($term) ?>" aria-label="Cari nama komunitas atau tipe motor" placeholder="Nama komunitas atau tipe motor">
    <input name="city" value="<?= e($city) ?>" aria-label="Filter berdasarkan kota atau daerah" placeholder="Kota / daerah">
    <button class="button subtle">Filter</button>
</form>
<section class="community-grid">
    <?php foreach ($communities as $community): ?>
        <article class="community-card">
            <a href="<?= e(url('community', ['id' => $community['id']])) ?>">
                <div class="community-cover" style="<?= $community['image'] ? 'background-image:url(' . e($community['image']) . ')' : '' ?>">
                    <span class="community-symbol">◈</span>
                    <?php if ($community['is_private']): ?><span class="cover-badge">PRIVATE</span><?php endif; ?>
                </div>
            </a>
            <div class="community-info">
                <span class="eyebrow"><?= e($community['motorcycle_type'] ?: 'KOMUNITAS RIDER') ?></span>
                <h3><a href="<?= e(url('community', ['id' => $community['id']])) ?>"><?= e($community['name']) ?></a></h3>
                <p>⌖ <?= e($community['city'] . ($community['region'] ? ', ' . $community['region'] : '')) ?> · <?= (int) $community['members'] ?> anggota</p>
                <div class="community-actions">
                    <?php if ($community['my_status'] === 'approved'): ?>
                        <span class="tag"><?= (int)$community['owner_id']===$uid?'Leader':community_role_label($community['my_role']) ?></span><?php if($community['unread_messages']): ?><span class="tag"><?= (int)$community['unread_messages'] ?> pesan baru</span><?php endif; ?><a class="text-link" href="<?= e(url('community', ['id' => $community['id']])) ?>">Lihat →</a>
                    <?php elseif ($community['my_status'] === 'pending'): ?>
                        <span class="tag">Menunggu persetujuan</span>
                    <?php elseif ($community['my_status'] === 'rejected'): ?>
                        <span class="tag">Permintaan ditolak</span>
                    <?php else: ?>
                        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="community_join"><input type="hidden" name="community_id" value="<?= (int) $community['id'] ?>"><button class="button subtle">Gabung <?= $community['requires_approval'] ? '· minta persetujuan' : '' ?></button></form>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$communities): ?><div class="empty-state">Belum ada komunitas yang cocok. Buat komunitas pertama di bawah.</div><?php endif; ?>
</section>
<?php if ($pagination['total_pages'] > 1): ?>
    <nav class="pagination" aria-label="Halaman komunitas">
        <?php foreach (pagination_pages($pagination['page'], $pagination['total_pages']) as $number): ?>
            <a class="<?= $number === $pagination['page'] ? 'selected' : '' ?>" href="<?= e(url('communities', array_merge($filters, ['p' => $number]))) ?>" <?= $number === $pagination['page'] ? 'aria-current="page"' : '' ?>><?= $number ?></a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
<section class="panel create-panel" id="buat-komunitas">
    <div class="section-head">
        <div><div class="eyebrow">MULAI CIRCLE KAMU</div><h2>Buat komunitas</h2></div>
    </div>
    <form method="post" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="community_create">
        <label>Nama komunitas<input required name="name" maxlength="120"></label>
        <label>Kota<input required name="city"></label>
        <label>Daerah<input name="region"></label>
        <label>Tipe motor<input name="motorcycle_type" placeholder="Contoh: sport 150 cc"></label>
        <label class="span-2">Deskripsi<textarea required name="description" rows="3"></textarea></label>
        <label>Logo / foto<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
        <div class="checks"><label><input type="checkbox" name="is_private"> Komunitas private</label><label><input type="checkbox" name="requires_approval"> Persetujuan untuk bergabung</label></div>
        <button class="button primary">Buat komunitas →</button>
    </form>
</section>
