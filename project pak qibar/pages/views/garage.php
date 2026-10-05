<?php $editId=(int)($_GET['edit']??0);$editing=null;if($editId){$q=$pdo->prepare('SELECT * FROM motorcycles WHERE id=? AND user_id=?');$q->execute([$editId,$uid]);$editing=$q->fetch();} $q=$pdo->prepare('SELECT m.*,COUNT(p.id) AS photo_count FROM motorcycles m LEFT JOIN motorcycle_photos p ON p.motorcycle_id=m.id WHERE m.user_id=? GROUP BY m.id ORDER BY m.is_primary DESC,m.created_at DESC');$q->execute([$uid]);$motors=$q->fetchAll(); ?>
<section class="page-heading">
    <div>
        <div class="eyebrow">MESIN DAN CERITAMU</div>
        <h1>Garasi saya</h1>
        <p>Kelola motor dan foto perjalananmu.</p>
    </div><a class="button primary" href="#form-motor">+ Tambah motor</a>
</section>
<?php if($motors):?><section class="garage-grid"><?php foreach($motors as $m):?><article
        class="motor-card"><a class="motor-photo"
            href="<?= e(url('motorcycle',['id'=>$m['id']])) ?>"><?php if($m['main_photo']):?><img
                src="<?= e($m['main_photo']) ?>"
                alt="<?= e($m['brand'].' '.$m['model']) ?>"><?php else:?><span
                class="bike-placeholder">◉</span><?php endif;?><?php if($m['is_primary']):?><span
                class="primary-badge">MOTOR UTAMA</span><?php endif;?></a>
        <div class="motor-info">
            <div class="eyebrow">
                <?= e($m['type'] . ($m['year'] ? ' · ' . (int) $m['year'] : '')) ?></div>
            <h3><?= e($m['brand'].' '.$m['model']) ?></h3>
            <p><?= e(implode(' · ',array_filter([$m['color'],$m['plate_number']]))) ?></p>
            <div class="motor-actions"><a class="text-link"
                    href="<?= e(url('motorcycle',['id'=>$m['id']])) ?>">Detail ·
                    <?= (int)$m['photo_count'] ?> foto</a><a class="text-link"
                    href="<?= e(url('garage',['edit'=>$m['id']])) ?>">Edit</a>
                <form method="post" onsubmit="return confirm('Hapus motor dan semua fotonya?')">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input
                        type="hidden" name="action" value="motor_delete"><input type="hidden"
                        name="id" value="<?= (int)$m['id'] ?>"><button
                        class="link-danger">Hapus</button></form>
            </div>
        </div>
    </article><?php endforeach;?></section><?php else:?><div class="empty-state roomy">
    <span>◉</span>
    <h3>Garasimu masih kosong</h3>
    <p>Tambahkan motor pertamamu untuk mulai terhubung dengan rider lain.</p>
</div><?php endif;?>
<section class="panel create-panel" id="form-motor">
    <div class="section-head">
        <div>
            <div class="eyebrow"><?= $editing?'PERBARUI DATA':'TAMBAHKAN KE GARASI' ?></div>
            <h2><?= $editing?'Edit motor':'Motor baru' ?></h2>
        </div><?php if($editing):?><a class="text-link"
            href="<?= e(url('garage')) ?>">Batal</a><?php endif;?>
    </div>
    <form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden"
            name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action"
            value="<?= $editing?'motor_edit':'motor_add' ?>"><?php if($editing):?><input
            type="hidden" name="id"
            value="<?= (int)$editing['id'] ?>"><?php endif;?><label>Merk<input required name="brand"
                maxlength="80" value="<?= e($editing['brand']??'') ?>"
                placeholder="Yamaha"></label><label>Model<input required name="model"
                maxlength="100" value="<?= e($editing['model']??'') ?>"
                placeholder="R15 V4"></label><label>Tipe<input name="type"
                value="<?= e($editing['type']??'') ?>"
                placeholder="Sport"></label><label>Tahun<input type="number" name="year" min="1900"
                max="<?= (int)date('Y')+1 ?>"
                value="<?= e($editing['year']??'') ?>"></label><label>Foto
            utama<input type="file" name="main_photo"
                accept="image/jpeg,image/png,image/webp"><small>JPG, PNG, WEBP · maks. 5
                MB</small></label><label>Foto tambahan<input type="file" name="photos[]" multiple
                accept="image/jpeg,image/png,image/webp"><small>Bisa pilih beberapa foto · maks. 5
                MB per foto</small></label><label class="check-label"><input type="checkbox"
                name="is_primary" <?= !empty($editing['is_primary'])?'checked':'' ?>> Jadikan motor
            utama</label>
        <div><button class="button primary"><?= $editing?'Simpan perubahan':'Simpan ke garasi' ?>
                →</button></div>
    </form>
</section>
