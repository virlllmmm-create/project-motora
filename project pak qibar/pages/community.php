<?php
$communityId=max(0,(int)($_GET['id']??0));
$community=community_context($pdo,$communityId,$uid);
$invite=is_string($_GET['invite']??null)?$_GET['invite']:'';
$validInvite=$community && $invite!=='' && $community['invite_token'] && hash_equals($community['invite_token'],$invite);
if (!$community || ($community['is_private'] && !$community['my_role'] && !$validInvite)) {
    http_response_code(404); echo '<div class="empty-state roomy">Komunitas tidak ditemukan atau aksesnya terbatas.</div>'; return;
}
$isMember=(bool)$community['my_role'];
$canManage=community_allowed($community,'manage');
$q=$pdo->prepare("SELECT COUNT(*) FROM community_members WHERE community_id=? AND status='approved'");
$q->execute([$communityId]); $memberCount=(int)$q->fetchColumn();
$communityChatPage=$isMember;
?>
<?php if(!$isMember): ?>
<section class="page-heading group-heading">
  <div><a class="text-link" href="<?= e(url('communities')) ?>">← Semua komunitas</a><h1><?= e($community['name']) ?></h1><p><?= $memberCount ?> anggota · <?= e($community['city']) ?> · <?= $community['is_private']?'Private':'Publik' ?><?= $community['closed_at']?' · Ditutup':'' ?></p></div>
</section>
<section class="panel group-preview"><h2>Tentang komunitas</h2><p><?= nl2br(e($community['description'])) ?></p>
  <?php if($community['closed_at']): ?><p>Komunitas ini sudah ditutup.</p>
  <?php elseif($community['member_status']==='pending'): ?><span class="tag">Menunggu persetujuan admin</span>
  <?php elseif($community['member_status']==='rejected'): ?><p>Permintaan ditolak atau keanggotaan berakhir. Hubungi admin untuk diundang kembali.</p>
  <?php else: ?><form method="post"><?= community_form_fields($communityId,'community_join') ?><input type="hidden" name="invite" value="<?= e($validInvite?$invite:'') ?>"><button class="button primary"><?= $community['requires_approval']?'Minta bergabung':'Gabung komunitas' ?></button></form><?php endif; ?>
  <p class="muted">Chat dan panggilan tersedia setelah menjadi anggota aktif.</p>
</section>
<?php else: ?>
<div class="community-workspace" data-community data-id="<?= $communityId ?>" data-user-id="<?= $uid ?>" data-csrf="<?= e(csrf_token()) ?>" data-chat-url="<?= e(url('community',['id'=>$communityId,'ajax'=>'community_chat'])) ?>" data-call-url="<?= e(url('community',['id'=>$communityId,'ajax'=>'community_call'])) ?>" data-can-manage="<?= $canManage?'1':'0' ?>">
  <section class="group-chat panel" aria-label="Chat komunitas">
    <header class="group-chat-head">
      <a class="group-back" href="<?= e(url('communities')) ?>" aria-label="Kembali ke semua komunitas" title="Semua komunitas"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></a>
      <?= avatar($community['image'],$community['name']) ?><div><b><?= e($community['name']) ?></b><small><?= $memberCount ?> anggota · Chat komunitas</small></div>
      <div class="group-call-buttons"><button type="button" class="button subtle" data-call-start="audio" aria-label="Mulai panggilan suara" <?= !community_allowed($community,'start_calls')?'hidden':'' ?>>☎ <span>Suara</span></button><button type="button" class="button subtle" data-call-start="video" aria-label="Mulai panggilan video" <?= !community_allowed($community,'start_calls')?'hidden':'' ?>>▣ <span>Video</span></button><a class="button subtle group-settings-button" href="<?= e(url('community_settings',['id'=>$communityId])) ?>" aria-label="Pengaturan komunitas" title="Pengaturan komunitas"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 3-.6 2.3-2 .9-2.1-.6-2 3.4 1.7 1.7v2.6l-1.7 1.7 2 3.4 2.1-.6 2 .9L9 21h4l.6-2.3 2-.9 2.1.6 2-3.4-1.7-1.7v-2.6L19.7 9l-2-3.4-2.1.6-2-.9L13 3z"/><circle cx="11" cy="12" r="3"/></svg></a></div>
    </header>
    <div class="group-call-banner" data-call-banner hidden><span data-call-banner-text></span><button type="button" class="button primary" data-call-join>Gabung panggilan</button></div>
    <div class="group-call-room" data-call-room hidden>
      <div class="group-call-status" data-call-status role="status">Menghubungkan…</div><div class="group-video-grid" data-call-videos></div>
      <div class="group-call-controls"><button type="button" class="button subtle" data-call-mic>Bisukan mic</button><button type="button" class="button subtle" data-call-camera>Matikan kamera</button><button type="button" class="button primary" data-call-leave>Keluar panggilan</button><?php if($canManage): ?><button type="button" class="button subtle" data-call-end>Akhiri untuk semua</button><?php endif; ?></div>
    </div>
    <div class="group-search"><label><span class="sr-only">Cari pesan dalam grup</span><input type="search" data-group-search placeholder="Cari pesan di komunitas…" maxlength="100"></label></div>
    <div class="group-pins" data-group-pins hidden></div>
    <div class="group-message-list" data-group-messages tabindex="0" aria-label="Riwayat pesan"><button type="button" class="group-load-more" data-load-more hidden>Muat pesan sebelumnya</button><div data-group-items><p class="group-empty">Memuat percakapan…</p></div></div>
    <p class="group-feedback" data-group-feedback role="status" hidden></p>
    <form class="group-compose" data-group-compose enctype="multipart/form-data">
      <div class="group-reply-draft" data-reply-draft hidden><span></span><button type="button" data-reply-cancel aria-label="Batal membalas">×</button></div>
      <div class="group-attachment-preview" data-attachment-preview hidden><img alt="Pratinjau foto"><span></span><button type="button" data-attachment-cancel aria-label="Hapus foto">×</button></div>
      <div class="group-compose-row"><label class="group-attach" title="Kirim foto"><span aria-hidden="true">＋</span><span class="sr-only">Pilih foto</span><input type="file" name="attachment" accept="image/jpeg,image/png,image/webp"></label><textarea name="body" rows="1" maxlength="4000" placeholder="Tulis pesan…" aria-label="Tulis pesan grup"></textarea><button class="button primary" type="submit" aria-label="Kirim pesan">↑</button></div>
      <small data-group-restriction hidden>Hanya leader dan admin yang dapat mengirim pesan.</small>
    </form>
  </section>

</div>
<dialog class="group-message-dialog" data-message-dialog><form method="dialog"><h3 data-dialog-title></h3><div data-dialog-content></div><div class="group-dialog-actions"><button class="button subtle" value="cancel">Batal</button><button class="button primary" value="confirm">Simpan</button></div></form></dialog>
<?php endif; ?>
