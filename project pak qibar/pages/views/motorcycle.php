<?php $id=(int)($_GET['id']??0);$q=$pdo->prepare('SELECT m.*,u.id_user,u.nama,u.username FROM motorcycles m JOIN `user` u ON u.id_user=m.user_id JOIN user_settings s ON s.user_id=u.id_user WHERE m.id=? AND '.visibility_sql('s','motorcycles_visibility','m.user_id',$uid).' AND '.not_blocked_sql('m.user_id',$uid));$q->execute([$id]);$m=$q->fetch();if(!$m||!can_view((int)$m['user_id'],'motorcycles_visibility')){http_response_code(404);?>
<div class="empty-state">Motor tidak ditemukan atau tidak dapat ditampilkan.</div>
<?php }else{$showPhotoGallery=can_view((int)$m['user_id'],'gallery_visibility')&&can_view((int)$m['user_id'],'motorcycles_visibility');$q=$pdo->prepare('SELECT p.id,p.path FROM motorcycle_photos p JOIN motorcycles m ON m.id=p.motorcycle_id JOIN user_settings s ON s.user_id=m.user_id WHERE p.motorcycle_id=? AND '.visibility_sql('s','gallery_visibility','m.user_id',$uid).' AND '.visibility_sql('s','motorcycles_visibility','m.user_id',$uid).' AND '.not_blocked_sql('m.user_id',$uid).' ORDER BY p.id DESC');$q->execute([$id]);$photos=$showPhotoGallery?$q->fetchAll():[];?>
<section class="page-heading">
    <div>
        <div class="eyebrow">GARASI RIDER</div>
        <h1><?= e($m['brand'].' '.$m['model']) ?></h1>
        <p>Milik <a class="text-link"
                href="<?= e(profile_link((int)$m['user_id'])) ?>"><?= e($m['nama']) ?></a></p>
    </div><?php if((int)$m['user_id']===$uid):?><a class="button subtle"
        href="<?= e(url('garage',['edit'=>$id])) ?>">Edit motor</a><?php endif;?>
</section>
<section class="motor-detail-hero"><?php if($m['main_photo']&&$showPhotoGallery):?><img
        src="<?= e($m['main_photo']) ?>" alt="<?= e($m['brand'].' '.$m['model']) ?>"><?php else:?>
    <div class="bike-placeholder large">◉</div><?php endif;?><div><span
            class="eyebrow"><?= e($m['type']?:'MOTOR') ?></span>
        <h2><?= e($m['brand'].' '.$m['model']) ?></h2>
        <p><?= e(implode(' · ',array_filter([$m['year'],$m['color']]))) ?></p>
        <p class="body-copy">
            <?= nl2br(e($m['description']?:'Belum ada deskripsi untuk motor ini.')) ?></p>
    </div>
</section>
<div class="section-head">
    <div>
        <div class="eyebrow">ALBUM</div>
        <h2>Galeri motor</h2>
    </div><span class="tag"><?= count($photos) ?> foto</span>
</div>
<section class="photo-grid"><?php foreach($photos as $p):?><figure><img src="<?= e($p['path']) ?>"
            alt="Foto motor"><?php if((int)$m['user_id']===$uid):?><form method="post"><input
                type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden"
                name="action" value="photo_delete"><input type="hidden" name="photo_id"
                value="<?= (int)$p['id'] ?>"><button class="photo-delete"
                aria-label="Hapus foto">×</button></form><?php endif;?></figure>
    <?php endforeach;?><?php if(!$showPhotoGallery):?><div class="empty-state">Pemilik membatasi
        visibilitas galeri.</div><?php elseif(!$photos):?><div class="empty-state">Belum ada foto
        motor.</div><?php endif;?></section>
<?php } ?>