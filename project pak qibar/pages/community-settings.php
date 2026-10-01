<?php
$communityId=max(0,(int)($_GET['id']??0));
$community=community_context($pdo,$communityId,$uid);
if (!$community || !$community['my_role']) {
    http_response_code(404); echo '<div class="empty-state roomy">Pengaturan hanya tersedia untuk anggota aktif komunitas.</div>'; return;
}
$canManage=community_allowed($community,'manage');
$q=$pdo->prepare("SELECT cm.user_id,cm.role,u.nama,u.username,u.profile_photo FROM community_members cm JOIN `user` u ON u.id_user=cm.user_id WHERE cm.community_id=? AND cm.status='approved' ORDER BY FIELD(cm.role,'leader','admin','member'),u.nama");
$q->execute([$communityId]); $members=$q->fetchAll(); $memberCount=count($members);
$pending=[];
if($canManage) { $q=$pdo->prepare("SELECT cm.user_id,u.nama,u.username FROM community_members cm JOIN `user` u ON u.id_user=cm.user_id WHERE cm.community_id=? AND cm.status='pending' ORDER BY cm.joined_at"); $q->execute([$communityId]); $pending=$q->fetchAll(); }
?>
<section class="page-heading group-settings-heading">
  <div><a class="text-link" href="<?= e(url('community',['id'=>$communityId])) ?>">← Kembali ke chat</a><h1>Pengaturan komunitas</h1><p><?= e($community['name']) ?> · <?= $memberCount ?> anggota · <?= e($community['city']) ?><?= $community['closed_at']?' · Ditutup':'' ?></p></div>
  <span class="group-role <?= e($community['my_role']) ?>">Anda · <?= e(community_role_label($community['my_role'])) ?></span>
</section>
  <div class="group-details" data-community-settings>
    <p class="group-settings-feedback" data-settings-feedback role="status" hidden></p>
    <div class="group-settings-column">
    <details class="panel group-detail" open><summary>Info komunitas</summary><p class="group-description"><?= nl2br(e($community['description'])) ?></p><p class="muted"><?= e($community['motorcycle_type']?:'Semua tipe motor') ?><?= $community['region']?' · '.e($community['region']):'' ?></p>
      <form method="post"><?= community_form_fields($communityId,'community_mute') ?><input type="hidden" name="muted" value="<?= empty($community['muted'])?'1':'0' ?>"><button class="button subtle"><?= empty($community['muted'])?'Bisukan notifikasi':'Aktifkan notifikasi' ?></button></form>
      <p class="group-permission-note">Leader mengelola seluruh komunitas. Admin mengelola anggota dan chat. Perubahan peran dan pemindahan leader hanya dapat dilakukan leader.</p>
    </details>
    <?php if(community_allowed($community,'add_members')): ?><details class="panel group-detail"><summary>Tambah dan undang rider</summary><form method="post" class="group-settings-form"><?= community_form_fields($communityId,'community_member_add') ?><label>Username rider<input required name="username" maxlength="50" placeholder="username tanpa @"></label><button class="button primary">Tambahkan rider</button></form>
      <?php if($community['invite_token']): ?><label class="group-invite-label">Tautan undangan<input readonly data-invite-link value="<?= e(url('community',['id'=>$communityId,'invite'=>$community['invite_token']])) ?>"></label><button type="button" class="button subtle" data-copy-invite>Salin tautan undangan</button><?php endif; ?>
      <?php if($canManage): ?><form method="post" class="group-invite-reset" onsubmit="return confirm('Tautan undangan lama akan tidak berlaku. Lanjutkan?')"><?= community_form_fields($communityId,'community_invite_reset') ?><button class="text-link"><?= $community['invite_token']?'Reset tautan':'Buat tautan undangan' ?></button></form><?php endif; ?>
    </details><?php endif; ?>
    <?php if($canManage && $pending): ?><details class="panel group-detail" open><summary>Permintaan bergabung <span><?= count($pending) ?></span></summary><?php foreach($pending as $member): ?><div class="group-pending"><b><?= e($member['nama']) ?></b><form method="post"><?= community_form_fields($communityId,'community_approve') ?><input type="hidden" name="member_id" value="<?= (int)$member['user_id'] ?>"><button class="button primary" name="approve" value="yes">Terima</button><button class="button subtle" name="approve" value="no">Tolak</button></form></div><?php endforeach; ?></details><?php endif; ?>
    <?php if(community_allowed($community,'edit_info')): ?><details class="panel group-detail"><summary>Edit info komunitas</summary><form method="post" enctype="multipart/form-data" class="group-settings-form"><?= community_form_fields($communityId,'community_info') ?><?php foreach(['name'=>'Nama komunitas','city'=>'Kota','region'=>'Daerah','motorcycle_type'=>'Tipe motor'] as $key=>$label): ?><label><?= $label ?><input name="<?= $key ?>" value="<?= e($community[$key]) ?>" maxlength="<?= $key==='name'?120:($key==='motorcycle_type'?80:100) ?>" <?= in_array($key,['name','city'],true)?'required':'' ?>></label><?php endforeach; ?><label>Deskripsi<textarea required name="description" maxlength="4000" rows="3"><?= e($community['description']) ?></textarea></label><label>Foto komunitas<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label><button class="button primary">Simpan info</button></form></details><?php endif; ?>
    <?php if($canManage): ?><details class="panel group-detail"><summary>Izin grup</summary><form method="post" class="group-settings-form"><?= community_form_fields($communityId,'community_permissions') ?><?php foreach(['send_messages'=>'Mengirim pesan','edit_info'=>'Mengedit info grup','add_members'=>'Menambah dan mengundang anggota','pin_messages'=>'Menyematkan pesan','start_calls'=>'Memulai panggilan'] as $key=>$label): ?><label><?= $label ?><select name="<?= $key ?>"><option value="all" <?= $community[$key]==='all'?'selected':'' ?>>Semua anggota</option><option value="admins" <?= $community[$key]==='admins'?'selected':'' ?>>Leader dan admin</option></select></label><?php endforeach; ?><label class="check-label"><input type="checkbox" name="is_private" <?= $community['is_private']?'checked':'' ?>>Komunitas private</label><label class="check-label"><input type="checkbox" name="requires_approval" <?= $community['requires_approval']?'checked':'' ?>>Setujui anggota baru dahulu</label><button class="button primary">Simpan izin</button></form></details><?php endif; ?>
    </div>
    <div class="group-settings-column">
    <div class="group-members-section">
    <details class="panel group-detail" open><summary>Anggota <span><?= $memberCount ?></span></summary><div class="group-member-list">
    <?php foreach($members as $member): $memberRole=(int)$member['user_id']===(int)$community['owner_id']?'leader':$member['role']; ?>
      <div class="group-member"><a class="group-member-profile" href="<?= e(profile_link((int)$member['user_id'])) ?>"><?= avatar($member['profile_photo'],$member['nama']) ?><span><b><?= e($member['nama']) ?><?= (int)$member['user_id']===$uid?' (Anda)':'' ?></b><small>@<?= e($member['username']) ?></small></span></a><span class="group-role <?= e($memberRole) ?>"><?= e(community_role_label($memberRole)) ?></span>
      <?php if((int)$member['user_id']!==$uid && $memberRole!=='leader' && $canManage && ($community['my_role']==='leader' || $memberRole==='member')): ?>
        <details class="group-member-menu"><summary>Kelola</summary>
          <?php if($community['my_role']==='leader'): ?><form method="post"><?= community_form_fields($communityId,'community_role') ?><input type="hidden" name="member_id" value="<?= (int)$member['user_id'] ?>"><input type="hidden" name="role" value="<?= $memberRole==='admin'?'member':'admin' ?>"><button class="text-link"><?= $memberRole==='admin'?'Jadikan anggota':'Jadikan admin' ?></button></form><form method="post" onsubmit="return confirm('Serahkan posisi leader kepada rider ini? Anda akan menjadi admin.')"><?= community_form_fields($communityId,'community_transfer') ?><input type="hidden" name="member_id" value="<?= (int)$member['user_id'] ?>"><button class="text-link">Jadikan leader</button></form><?php endif; ?>
          <form method="post" onsubmit="return confirm('Keluarkan rider ini dari komunitas?')"><?= community_form_fields($communityId,'community_member_remove') ?><input type="hidden" name="member_id" value="<?= (int)$member['user_id'] ?>"><button class="link-danger">Keluarkan</button></form>
        </details>
      <?php endif; ?></div>
    <?php endforeach; ?></div></details>
    <?php if(community_allowed($community,'delete')): ?>
      <section class="panel group-detail group-delete" aria-labelledby="communityDeleteTitle">
        <div class="group-delete-heading"><span class="group-delete-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6"/></svg></span><h2 id="communityDeleteTitle">Hapus komunitas</h2></div>
        <p id="communityDeleteDescription">Komunitas, anggota, riwayat chat, foto, dan panggilan akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.</p>
        <form method="post" onsubmit="return confirm('Hapus komunitas secara permanen? Seluruh anggota, chat, foto, dan panggilan akan dihapus. Tindakan ini tidak dapat dibatalkan.')">
          <?= community_form_fields($communityId,'community_delete') ?>
          <button type="submit" class="button group-delete-button" name="confirm_delete" value="1" aria-describedby="communityDeleteDescription">Hapus komunitas permanen</button>
        </form>
      </section>
    <?php endif; ?>
    </div>
    <section class="panel group-detail group-danger"><?php if($community['my_role']==='leader'): ?><?php if(!$community['closed_at']): ?><form method="post" onsubmit="return confirm('Tutup komunitas? Semua chat baru dan panggilan akan dinonaktifkan.')"><?= community_form_fields($communityId,'community_close') ?><button class="button group-close-button">Tutup komunitas</button></form><?php endif; ?><small>Untuk keluar, serahkan posisi leader ke anggota lain dahulu.</small><?php else: ?><form method="post" onsubmit="return confirm('Keluar dari komunitas ini?')"><?= community_form_fields($communityId,'community_leave') ?><button class="button group-close-button">Keluar dari komunitas</button></form><?php endif; ?></section>
    </div>
  </div>
