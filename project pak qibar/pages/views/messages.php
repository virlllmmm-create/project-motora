<?php
$perPage = 20;
$requestedPage = filter_var($_GET['p'] ?? 1, FILTER_VALIDATE_INT);
$requestedPage = $requestedPage === false ? 1 : max(1, (int) $requestedPage);
$countSql = 'SELECT COUNT(DISTINCT c.id) FROM conversation_members mine JOIN conversations c ON c.id=mine.conversation_id JOIN conversation_members theirs ON theirs.conversation_id=c.id AND theirs.user_id<>mine.user_id JOIN `user` other ON other.id_user=theirs.user_id WHERE mine.user_id=? AND ' . not_blocked_sql('other.id_user', $uid) . ' AND ' . unblocked_conversation_sql('c.id', $uid);
$count = $pdo->prepare($countSql);
$count->execute([$uid]);
$threadTotal = (int) $count->fetchColumn();
$threadPages = pagination_state($threadTotal, $perPage, $requestedPage);
$conv = max(0, (int) ($_GET['conversation'] ?? 0));
$selected = null;
$messages = [];
$threadSql = 'SELECT c.id,other.id_user AS other_id,other.nama,other.username,other.profile_photo,latest.body,latest.created_at,(SELECT COUNT(*) FROM messages um WHERE um.conversation_id=c.id AND um.sender_id<>? AND um.read_at IS NULL) AS unread FROM conversation_members mine JOIN conversations c ON c.id=mine.conversation_id JOIN conversation_members theirs ON theirs.conversation_id=c.id AND theirs.user_id<>mine.user_id JOIN `user` other ON other.id_user=theirs.user_id LEFT JOIN messages latest ON latest.id=(SELECT MAX(mx.id) FROM messages mx WHERE mx.conversation_id=c.id) WHERE mine.user_id=? AND ' . not_blocked_sql('other.id_user', $uid) . ' AND ' . unblocked_conversation_sql('c.id', $uid) . ' ORDER BY COALESCE(latest.created_at,c.created_at) DESC LIMIT ' . $threadPages['per_page'] . ' OFFSET ' . $threadPages['offset'];
$q = $pdo->prepare($threadSql);
$q->execute([$uid, $uid]);
$threads = $q->fetchAll();
mark_user_conversations_delivered($pdo, $uid);
foreach ($threads as $thread) {
    if ((int) $thread['id'] === $conv) {
        $selected = $thread;
        break;
    }
}
if ($conv > 0) {
    $historyRows = conversation_messages_for_viewer($pdo, $conv, $uid);
    if ($historyRows === null) {
        $conv = 0;
        $selected = null;
        $messages = [];
    } else {
        $selectedQuery = $pdo->prepare('SELECT c.id,other.id_user AS other_id,other.nama,other.username,other.profile_photo,(SELECT COUNT(*) FROM messages um WHERE um.conversation_id=c.id AND um.sender_id<>? AND um.read_at IS NULL) AS unread FROM conversation_members mine JOIN conversations c ON c.id=mine.conversation_id JOIN conversation_members theirs ON theirs.conversation_id=c.id AND theirs.user_id<>mine.user_id JOIN `user` other ON other.id_user=theirs.user_id WHERE mine.user_id=? AND c.id=? LIMIT 1');
        $selectedQuery->execute([$uid, $uid, $conv]);
        $selected = $selectedQuery->fetch() ?: null;
        if ($selected) {
            $messages = $historyRows;
        } else {
            $conv = 0;
            $messages = [];
        }
    }
}
if (!$selected && !empty($_GET['to'])) {
    $target = (int) $_GET['to'];
    $targetQuery = $pdo->prepare('SELECT u.id_user,u.nama,u.username,u.profile_photo FROM `user` u LEFT JOIN user_settings s ON s.user_id=u.id_user WHERE u.id_user=? AND ' . visibility_sql('s', 'profile_visibility', 'u.id_user', $uid) . ' AND ' . not_blocked_sql('u.id_user', $uid));
    $targetQuery->execute([$target]);
    $selectedTo = $targetQuery->fetch();
    if ($selectedTo && $target !== $uid) {
        $selected = ['id' => 0, 'other_id' => $target, 'nama' => $selectedTo['nama'], 'username' => $selectedTo['username'], 'profile_photo' => $selectedTo['profile_photo'], 'body' => 'Mulai percakapan...', 'unread' => 0];
        $existing = $pdo->prepare('SELECT c.id FROM conversations c JOIN conversation_members mine ON mine.conversation_id=c.id AND mine.user_id=? JOIN conversation_members theirs ON theirs.conversation_id=c.id AND theirs.user_id=? WHERE (SELECT COUNT(*) FROM conversation_members cm WHERE cm.conversation_id=c.id)=2 AND ' . unblocked_conversation_sql('c.id', $uid) . ' LIMIT 1');
        $existing->execute([$uid, $target]);
        $existingConversation = (int) $existing->fetchColumn();
        if ($existingConversation > 0) {
            $historyRows = conversation_messages_for_viewer($pdo, $existingConversation, $uid);
            if ($historyRows === null) {
                $selected = null;
                $conv = 0;
            } else {
                $conv = $existingConversation;
                $selected['id'] = $existingConversation;
                $messages = $historyRows;
            }
        }
    }
}
if ($conv > 0 && !$selected) {
    $historyRows = conversation_messages_for_viewer($pdo, $conv, $uid);
    if ($historyRows === null) {
        $conv = 0;
        $selected = null;
        $messages = [];
    } else {
        $selectedQuery = $pdo->prepare('SELECT c.id,other.id_user AS other_id,other.nama,other.username,other.profile_photo,(SELECT COUNT(*) FROM messages um WHERE um.conversation_id=c.id AND um.sender_id<>? AND um.read_at IS NULL) AS unread FROM conversation_members mine JOIN conversations c ON c.id=mine.conversation_id JOIN conversation_members theirs ON theirs.conversation_id=c.id AND theirs.user_id<>mine.user_id JOIN `user` other ON other.id_user=theirs.user_id WHERE mine.user_id=? AND c.id=? LIMIT 1');
        $selectedQuery->execute([$uid, $uid, $conv]);
        $selected = $selectedQuery->fetch() ?: null;
        if ($selected) {
            $messages = $historyRows;
        } else {
            $conv = 0;
            $messages = [];
        }
    }
} elseif (empty($messages)) {
    $messages = [];
}
?>
<section class="page-heading">
    <div>
        <div class="eyebrow">KONEKSI RIDER</div>
        <h1>Pesan</h1>
        <p>Pesan hanya terlihat oleh peserta percakapan.</p>
    </div>
</section>
<section class="chat-layout <?= $selected ? 'has-conversation' : 'list-only' ?>">
    <aside class="chat-threads">
        <div class="chat-list-head"><b>Pesan</b><span><?= $threadTotal ?></span></div>
        <?php foreach ($threads as $t): ?>
            <a class="chat-thread <?= (int) $t['id'] === $conv ? 'active' : '' ?>" data-conversation-id="<?= (int) $t['id'] ?>" href="<?= e(url('messages', ['conversation' => $t['id']])) ?>">
                <?= avatar($t['profile_photo'], $t['nama']) ?>
                <span><b><?= e($t['nama']) ?></b><small><?= e(mb_strimwidth((string) $t['body'], 0, 36, '…')) ?></small></span>
                <?php if ((int) $t['unread']): ?><i data-thread-unread><?= (int) $t['unread'] ?></i><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if (!$threads): ?><div class="muted pad">Belum ada obrolan.<br>Buka profil rider untuk memulai.</div><?php endif; ?>
        <?php if ($threadPages['total_pages'] > 1): ?>
            <nav class="pagination" aria-label="Halaman obrolan">
                <?php foreach (pagination_pages($threadPages['page'], $threadPages['total_pages']) as $number): ?>
                    <a class="<?= $number === $threadPages['page'] ? 'selected' : '' ?>" href="<?= e(url('messages', array_merge($conv ? ['conversation' => $conv] : [], ['p' => $number]))) ?>" <?= $number === $threadPages['page'] ? 'aria-current="page"' : '' ?>><?= $number ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </aside>
    <div class="chat-window">
        <?php if ($selected): ?>
            <div class="chat-contact"><a class="chat-back" href="<?= e(url('messages')) ?>" aria-label="Kembali ke daftar obrolan">‹</a><?= avatar($selected['profile_photo'], $selected['nama']) ?><span><b><?= e($selected['nama']) ?></b><small>@<?= e($selected['username']) ?></small></span></div>
            <div class="chat-messages" data-chat-messages data-last-id="<?= $messages ? (int) end($messages)['id'] : '' ?>">
                <?php foreach ($messages as $m): ?>
                    <article class="chat-message <?= (int) $m['sender_id'] === $uid ? 'mine' : 'theirs' ?>">
                        <p><?= e($m['body']) ?></p>
                        <div class="message-meta"><small><?= e($m['time_label']) ?></small><?php $status = !empty($m['read_at']) ? 'read' : (!empty($m['delivered_at']) ? 'delivered' : 'sent'); ?><span class="message-checks <?= e($status) ?>" aria-label="<?= $status === 'read' ? 'Dibaca' : ($status === 'delivered' ? 'Terkirim ke perangkat' : 'Terkirim') ?>"><?= $status === 'sent' ? '✓' : '✓✓' ?></span></div>
                    </article>
                <?php endforeach; ?>
            </div>
            <form method="post" class="chat-compose"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="message_send"><input type="hidden" name="conversation_id" value="<?= $conv ?>"><?php if($conv===0&&!empty($selected['other_id'])):?><input type="hidden" name="to_user" value="<?= (int)$selected['other_id'] ?>"><?php endif;?><input name="body" maxlength="4000" required aria-label="Tulis pesan" placeholder="Tulis pesan…"><button class="button primary" aria-label="Kirim pesan">↑</button></form>
            <div data-chat data-poll-url="<?= e(url('messages', ['ajax' => 'messages', 'conversation' => $conv])) ?>"></div>
        <?php else: ?>
            <div class="chat-empty"><span>▤</span><h3>Pilih obrolan</h3><p>Pilih percakapan untuk membaca pesan atau temukan rider untuk memulai.</p><a class="button subtle" href="<?= e(url('search')) ?>">Cari rider</a></div>
        <?php endif; ?>
    </div>
</section>
