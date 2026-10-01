<?php
$perPage = 20;
$visibilitySql = visibility_sql('s', 'profile_visibility', 'u.id_user', $uid);
$loadPagedList = static function (
    PDO $pdo,
    string $pageKey,
    string $countSql,
    array $countArgs,
    string $selectSql,
    array $selectArgs,
    int $perPage,
): array {
    $requestedPage = filter_var($_GET[$pageKey] ?? 1, FILTER_VALIDATE_INT);
    $requestedPage = $requestedPage === false ? 1 : max(1, (int) $requestedPage);

    $count = $pdo->prepare($countSql);
    $count->execute($countArgs);
    $total = (int) $count->fetchColumn();
    $pagination = pagination_state($total, $perPage, $requestedPage);
    $pagination['total'] = $total;

    $query = $pdo->prepare(
        $selectSql . ' LIMIT ' . $pagination['per_page'] . ' OFFSET ' . $pagination['offset'],
    );
    $query->execute($selectArgs);

    return [$query->fetchAll(), $pagination];
};
$renderPagination = static function (array $pagination, string $pageKey): void {
    if ($pagination['total_pages'] <= 1) {
        return;
    }

    $labels = [
        'p_friends' => 'Halaman teman',
        'p_incoming' => 'Halaman permintaan masuk',
        'p_outgoing' => 'Halaman permintaan terkirim',
        'p_blocked' => 'Halaman rider diblokir',
    ];
    echo '<nav class="pagination" aria-label="' . e($labels[$pageKey] ?? 'Halaman daftar') . '">';
    foreach (pagination_pages($pagination['page'], $pagination['total_pages']) as $number) {
        $current = $number === $pagination['page'];
        echo '<a class="' . ($current ? 'selected' : '') . '" href="' .
            e(url('friends', [$pageKey => $number])) . '"' .
            ($current ? ' aria-current="page"' : '') . '>' . $number . '</a>';
    }
    echo '</nav>';
};

[$friends, $friendsPagination] = $loadPagedList(
    $pdo,
    'p_friends',
    "SELECT COUNT(*) FROM friend_requests f JOIN `user` u ON u.id_user=IF(f.sender_id=?,f.receiver_id,f.sender_id) LEFT JOIN user_settings s ON s.user_id=u.id_user WHERE (f.sender_id=? OR f.receiver_id=?) AND f.status='accepted' AND u.id_user<>? AND " . $visibilitySql,
    [$uid, $uid, $uid, $uid],
    "SELECT f.id,f.sender_id,f.receiver_id,f.status,u.id_user,u.nama,u.username,u.profile_photo,CASE WHEN COALESCE(s.show_city,1)=1 THEN u.city ELSE NULL END AS city FROM friend_requests f JOIN `user` u ON u.id_user=IF(f.sender_id=?,f.receiver_id,f.sender_id) LEFT JOIN user_settings s ON s.user_id=u.id_user WHERE (f.sender_id=? OR f.receiver_id=?) AND f.status='accepted' AND u.id_user<>? AND " . $visibilitySql . ' ORDER BY u.nama,u.id_user',
    [$uid, $uid, $uid, $uid],
    $perPage,
);
[$incoming, $incomingPagination] = $loadPagedList(
    $pdo,
    'p_incoming',
    "SELECT COUNT(*) FROM friend_requests f JOIN `user` u ON u.id_user=f.sender_id LEFT JOIN user_settings s ON s.user_id=u.id_user WHERE f.receiver_id=? AND f.status='pending' AND " . $visibilitySql,
    [$uid],
    "SELECT f.*,u.nama,u.username,u.profile_photo,CASE WHEN COALESCE(s.show_city,1)=1 THEN u.city ELSE NULL END AS city FROM friend_requests f JOIN `user` u ON u.id_user=f.sender_id LEFT JOIN user_settings s ON s.user_id=u.id_user WHERE f.receiver_id=? AND f.status='pending' AND " . $visibilitySql . ' ORDER BY f.created_at DESC,f.id DESC',
    [$uid],
    $perPage,
);
[$outgoing, $outgoingPagination] = $loadPagedList(
    $pdo,
    'p_outgoing',
    "SELECT COUNT(*) FROM friend_requests f JOIN `user` u ON u.id_user=f.receiver_id LEFT JOIN user_settings s ON s.user_id=u.id_user WHERE f.sender_id=? AND f.status='pending' AND " . $visibilitySql,
    [$uid],
    "SELECT f.*,u.nama,u.username,u.profile_photo FROM friend_requests f JOIN `user` u ON u.id_user=f.receiver_id LEFT JOIN user_settings s ON s.user_id=u.id_user WHERE f.sender_id=? AND f.status='pending' AND " . $visibilitySql . ' ORDER BY f.created_at DESC,f.id DESC',
    [$uid],
    $perPage,
);
[$blockedRiders, $blockedPagination] = $loadPagedList(
    $pdo,
    'p_blocked',
    "SELECT COUNT(*) FROM friend_requests WHERE sender_id=? AND status='blocked'",
    [$uid],
    "SELECT f.id,u.id_user,u.nama,u.username,u.profile_photo FROM friend_requests f JOIN `user` u ON u.id_user=f.receiver_id WHERE f.sender_id=? AND f.status='blocked' ORDER BY u.nama,u.id_user",
    [$uid],
    $perPage,
);
?>
<section class="page-heading">
    <div>
        <div class="eyebrow">JARINGANMU</div>
        <h1>Teman riding</h1>
        <p>Kelola koneksi dan permintaan pertemanan.</p>
    </div><a class="button subtle" href="<?= e(url('search')) ?>">Cari rider →</a>
</section>
<?php if ($incoming): ?><section class="panel">
    <div class="section-head">
        <div>
            <div class="eyebrow">PERLU TINDAKAN</div>
            <h2>Permintaan masuk</h2>
        </div><span class="tag"><?= $incomingPagination['total'] ?></span>
    </div><?php foreach ($incoming as $f): ?><div class="member-row">
        <?= avatar($f['profile_photo'], $f['nama']) ?><span><b><?= e($f['nama']) ?></b><small>@<?= e($f['username'] . ($f['city'] ? ' · ' . $f['city'] : '')) ?></small></span><a
            class="text-link" href="<?= e(profile_link((int) $f['sender_id'])) ?>">Profil</a>
        <form class="inline-actions" method="post"><input type="hidden" name="csrf"
                value="<?= e(csrf_token()) ?>"><input type="hidden" name="action"
                value="friend_action"><input type="hidden" name="request_id"
                value="<?= (int) $f['id'] ?>"><button class="button tiny primary" name="op"
                value="accept">Terima</button><button class="button tiny subtle" name="op"
                value="reject">Tolak</button><button class="button tiny subtle" name="op"
                value="block">Blokir</button></form>
    </div><?php endforeach; ?><?php $renderPagination($incomingPagination, 'p_incoming'); ?>
</section><?php endif; ?>
<?php if ($outgoing): ?><section class="panel">
    <div class="section-head">
        <div>
            <div class="eyebrow">MENUNGGU JAWABAN</div>
            <h2>Permintaan terkirim</h2>
        </div><span class="tag"><?= $outgoingPagination['total'] ?></span>
    </div><?php foreach ($outgoing as $f): ?><div class="member-row">
        <?= avatar($f['profile_photo'], $f['nama']) ?><span><b><?= e($f['nama']) ?></b><small>@<?= e($f['username']) ?></small></span>
        <form class="inline-actions" method="post"><input type="hidden" name="csrf"
                value="<?= e(csrf_token()) ?>"><input type="hidden" name="action"
                value="friend_action"><input type="hidden" name="request_id"
                value="<?= (int) $f['id'] ?>"><button class="button tiny subtle" name="op"
                value="cancel">Batalkan</button><button class="button tiny subtle" name="op"
                value="block">Blokir</button></form>
    </div><?php endforeach; ?><?php $renderPagination($outgoingPagination, 'p_outgoing'); ?>
</section><?php endif; ?>
<section class="panel">
    <div class="section-head">
        <div>
            <div class="eyebrow">NETWORK</div>
            <h2>Teman kamu</h2>
        </div><span class="tag"><?= $friendsPagination['total'] ?></span>
    </div><?php if (!$friends): ?><div class="empty-state">Belum ada teman. Cari rider dan kirim
        permintaan pertama.</div><?php else: foreach ($friends as $f): ?><div class="member-row">
        <?= avatar($f['profile_photo'], $f['nama']) ?><span><b><?= e($f['nama']) ?></b><small>@<?= e($f['username'] . ($f['city'] ? ' · ' . $f['city'] : '')) ?></small></span><a
            class="button tiny subtle"
            href="<?= e(profile_link((int) $f['id_user'])) ?>">Profil</a><a
            class="button tiny subtle"
            href="<?= e(url('messages', ['conversation' => 0, 'to' => $f['id_user']])) ?>">Chat</a>
        <form class="inline-actions" method="post"><input type="hidden" name="csrf"
                value="<?= e(csrf_token()) ?>"><input type="hidden" name="action"
                value="friend_action"><input type="hidden" name="request_id"
                value="<?= (int) $f['id'] ?>"><button class="button tiny subtle" name="op"
                value="remove">Hapus teman</button><button class="button tiny subtle" name="op"
                value="block">Blokir</button></form>
    </div><?php endforeach; endif; ?><?php $renderPagination($friendsPagination, 'p_friends'); ?>
</section>
<?php if ($blockedRiders): ?><section class="panel">
    <div class="section-head">
        <div>
            <div class="eyebrow">PREFERENSI INTERAKSI</div>
            <h2>Rider diblokir</h2>
        </div><span class="tag"><?= $blockedPagination['total'] ?></span>
    </div><?php foreach ($blockedRiders as $f): ?><div class="member-row">
        <?= avatar($f['profile_photo'], $f['nama']) ?><span><b><?= e($f['nama']) ?></b><small>@<?= e($f['username']) ?></small></span>
        <form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input
                type="hidden" name="action" value="friend_action"><input type="hidden"
                name="request_id" value="<?= (int) $f['id'] ?>"><button class="button tiny subtle"
                name="op" value="unblock">Buka blokir</button></form>
    </div><?php endforeach; ?><?php $renderPagination($blockedPagination, 'p_blocked'); ?>
</section><?php endif; ?>
