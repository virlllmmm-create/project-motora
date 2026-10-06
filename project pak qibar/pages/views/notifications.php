<?php
    $q=$pdo->prepare('SELECT n.*,u.nama,u.username,u.profile_photo FROM notifications n LEFT JOIN `user` u ON u.id_user=n.actor_id WHERE n.recipient_id=? AND '.notification_visibility_sql($uid, 'n').' ORDER BY n.id DESC LIMIT 100');
    $q->execute([$uid]);
    $notes=$q->fetchAll();
    $visibleIds=array_map(static fn(array $note): int => (int)$note['id'],$notes);
    if ($visibleIds) {
      $placeholders=implode(',',array_fill(0,count($visibleIds),'?'));
      $read=$pdo->prepare("UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE recipient_id=? AND read_at IS NULL AND id IN ($placeholders)");
      $read->execute(array_merge([$uid],$visibleIds));
      $readAt=date('Y-m-d H:i:s');
      foreach($notes as &$note) if(empty($note['read_at'])) $note['read_at']=$readAt;
      unset($note);
    }
  ?>
<section class="page-heading">
    <div>
        <div class="eyebrow">KABAR TERBARU</div>
        <h1>Aktivitas komunitas</h1>
        <p>Permintaan teman, komunitas, dan pesan.</p>
    </div>
</section>
<section class="panel notification-list"><?php foreach($notes as $n):?><article
        class="notification-row <?= $n['read_at']?'':'unread' ?>">
        <?= avatar($n['profile_photo'],$n['nama']?:'M') ?><span><b><?= e($n['nama']?:'MOTORA') ?></b>
            <p><?= e($n['body']) ?></p>
            <small><?= e(date('d M Y · H:i',strtotime($n['created_at']))) ?></small>
            <?php if(str_starts_with($n['type'],'community_') && $n['entity_id']): ?><a class="text-link" href="<?= e(url('community',['id'=>(int)$n['entity_id']])) ?>">Lihat komunitas →</a><?php endif; ?>
        </span></article><?php endforeach;?><?php if(!$notes):?><div class="empty-state">Belum ada
        notifikasi.</div><?php endif;?></section>
