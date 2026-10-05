<?php
declare(strict_types=1);

function community_context(PDO $pdo, int $id, int $viewer, bool $lock = false): ?array
{
    $q = $pdo->prepare("SELECT c.*,cm.status AS member_status,cm.role AS member_role,cm.muted,cm.last_read_message_id
        FROM communities c LEFT JOIN community_members cm ON cm.community_id=c.id AND cm.user_id=?
        WHERE c.id=?" . ($lock ? ' FOR UPDATE' : ''));
    $q->execute([$viewer, $id]);
    $c = $q->fetch();
    if (!$c || is_blocked($viewer, (int) $c['owner_id'])) return null;
    $c['my_role'] = (int) $c['owner_id'] === $viewer ? 'leader' : ($c['member_status'] === 'approved' ? $c['member_role'] : null);
    return $c;
}
function community_require(PDO $pdo, int $id, int $viewer, bool $lock = false): array
{
    $c = community_context($pdo, $id, $viewer, $lock);
    if (!$c || !$c['my_role']) throw new RuntimeException('Anda harus menjadi anggota aktif komunitas ini.');
    return $c;
}
function community_allowed(array $c, string $permission): bool
{
    if (!$c['my_role'] || (!empty($c['closed_at']) && $permission !== 'delete')) return false;
    if ($c['my_role'] === 'leader') return true;
    if (in_array($permission, ['roles', 'transfer', 'close', 'delete'], true)) return false;
    if ($c['my_role'] === 'admin') return true;
    return in_array($permission, ['send_messages','edit_info','add_members','pin_messages','start_calls'], true) && $c[$permission] === 'all';
}
function community_role_label(?string $role): string
{
    return ['leader'=>'Leader','admin'=>'Admin','member'=>'Anggota'][$role ?? ''] ?? 'Pengunjung';
}
function community_form_fields(int $id, string $action): string
{
    return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'"><input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="community_id" value="'.$id.'">';
}
function community_system(PDO $pdo, int $id, int $actor, string $body): void
{
    $pdo->prepare("INSERT INTO community_messages(community_id,sender_id,body,kind) VALUES(?,?,?,'system')")->execute([$id,$actor,$body]);
}
function community_notify(PDO $pdo, int $id, int $actor, string $type, string $body): void
{
    $q = $pdo->prepare("SELECT user_id FROM community_members WHERE community_id=? AND status='approved' AND muted=0 AND user_id<>?");
    $q->execute([$id,$actor]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $recipient) {
        if (!is_blocked((int)$recipient,$actor)) notify((int)$recipient,$actor,$type,$id,mb_substr($body,0,250),'notify_community',$pdo);
    }
}
function community_validate_info(array $post): array
{
    $values = [];
    foreach (['name'=>120,'city'=>100,'region'=>100,'motorcycle_type'=>80,'description'=>4000] as $key=>$max) {
        $value = trim(is_string($post[$key] ?? null) ? $post[$key] : '');
        if (mb_strlen($value)>$max || (in_array($key,['name','city','description'],true) && $value==='')) throw new RuntimeException('Periksa nama, kota, dan deskripsi komunitas serta panjang isiannya.');
        $values[] = $value;
    }
    return $values;
}
function community_handle_action(PDO $pdo, int $uid, array $user, string $action, array $post, array &$uploads): array
{
    $id = (int)($post['community_id'] ?? 0);
    $message = 'Komunitas diperbarui.';
    $returnPage = 'community';
    $deletedFiles = [];
    $pdo->beginTransaction();
    try {
        if ($action === 'community_create') {
            [$name,$city,$region,$type,$description] = community_validate_info($post);
            $pdo->prepare('SELECT id_user FROM `user` WHERE id_user=? FOR UPDATE')->execute([$uid]);
            $image = tracked_upload_image($uploads,'image','community');
            $q = $pdo->prepare('INSERT INTO communities(owner_id,name,city,region,motorcycle_type,description,image,is_private,requires_approval,invite_token) VALUES(?,?,?,?,?,?,?,?,?,?)');
            $q->execute([$uid,$name,$city,$region?:null,$type?:null,$description,$image,(int)!empty($post['is_private']),(int)!empty($post['requires_approval']),bin2hex(random_bytes(32))]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare("INSERT INTO community_members(community_id,user_id,status,role) VALUES(?,?,'approved','leader')")->execute([$id,$uid]);
            community_system($pdo,$id,$uid,$user['nama'].' membuat komunitas.');
            $message = 'Komunitas dibuat. Anda menjadi leader.';
        } elseif ($action === 'community_join') {
            $c = community_context($pdo,$id,$uid,true);
            if (!$c || $c['closed_at']) throw new RuntimeException('Komunitas tidak tersedia.');
            $token = is_string($post['invite'] ?? null) ? $post['invite'] : '';
            $invited = $token !== '' && $c['invite_token'] && hash_equals($c['invite_token'],$token);
            if ($c['is_private'] && !$invited && !$c['my_role']) throw new RuntimeException('Gunakan tautan undangan untuk bergabung ke komunitas private.');
            if ($c['my_role']) throw new RuntimeException('Anda sudah tergabung di komunitas ini.');
            if (in_array($c['member_status'],['pending','rejected'],true)) throw new RuntimeException($c['member_status']==='pending'?'Permintaan Anda masih menunggu persetujuan.':'Permintaan sebelumnya ditolak. Hubungi admin untuk diundang kembali.');
            $status = $c['requires_approval'] || ($c['is_private'] && !$invited) ? 'pending' : 'approved';
            $pdo->prepare("INSERT INTO community_members(community_id,user_id,status,role) VALUES(?,?,?,'member')")->execute([$id,$uid,$status]);
            if ($status==='approved') community_system($pdo,$id,$uid,$user['nama'].' bergabung.');
            else community_notify($pdo,$id,$uid,'community_request',$user['nama'].' meminta bergabung ke '.$c['name'].'.');
            $message = $status==='pending'?'Permintaan bergabung dikirim.':'Anda bergabung dengan komunitas.';
            if ($status==='pending' && $c['is_private']) $returnPage='communities';
        } else {
            $c = community_require($pdo,$id,$uid,true);
            if ($c['closed_at'] && !in_array($action,['community_leave','community_mute','community_delete'],true)) throw new RuntimeException('Komunitas ini sudah ditutup.');
            $returnPage = 'community_settings';
            switch ($action) {
                case 'community_leave':
                    if ($c['my_role']==='leader') throw new RuntimeException('Serahkan posisi leader sebelum keluar.');
                    community_system($pdo,$id,$uid,$user['nama'].' keluar dari komunitas.');
                    $pdo->prepare('DELETE FROM community_members WHERE community_id=? AND user_id=?')->execute([$id,$uid]);
                    $pdo->prepare('DELETE p FROM community_call_participants p JOIN community_calls c ON c.id=p.call_id WHERE c.community_id=? AND p.user_id=?')->execute([$id,$uid]);
                    $returnPage='communities'; $message='Anda keluar dari komunitas.';
                    break;
                case 'community_approve':
                case 'community_member_remove':
                case 'community_role':
                case 'community_transfer':
                    $permission = $action==='community_role'?'roles':($action==='community_transfer'?'transfer':'manage');
                    if (!community_allowed($c,$permission)) throw new RuntimeException('Peran Anda tidak memiliki izin untuk tindakan ini.');
                    $target = (int)($post['member_id']??0);
                    $q=$pdo->prepare('SELECT cm.*,u.nama FROM community_members cm JOIN `user` u ON u.id_user=cm.user_id WHERE cm.community_id=? AND cm.user_id=? FOR UPDATE');
                    $q->execute([$id,$target]); $member=$q->fetch();
                    if (!$member || $target===$uid || $target===(int)$c['owner_id']) throw new RuntimeException('Leader dan akun sendiri tidak dapat diubah melalui tindakan ini.');
                    if ($action==='community_approve') {
                        if ($member['status']!=='pending' || !in_array($post['approve']??'',['yes','no'],true)) throw new RuntimeException('Permintaan tidak valid atau sudah diproses.');
                        if (is_blocked($target,(int)$c['owner_id'])) throw new RuntimeException('Rider ini tidak dapat bergabung ke komunitas.');
                        $status=$post['approve']==='yes'?'approved':'rejected';
                        $pdo->prepare("UPDATE community_members SET status=?,role='member',joined_at=CURRENT_TIMESTAMP WHERE community_id=? AND user_id=?")->execute([$status,$id,$target]);
                        if ($status==='approved') { community_system($pdo,$id,$uid,$member['nama'].' diterima oleh '.$user['nama'].'.'); notify($target,$uid,'community_approved',$id,'Permintaan ke '.$c['name'].' disetujui.','notify_community',$pdo); }
                    } elseif ($action==='community_member_remove') {
                        if ($member['status']!=='approved' || ($c['my_role']==='admin' && $member['role']!=='member')) throw new RuntimeException('Admin hanya dapat mengeluarkan anggota biasa.');
                        $pdo->prepare("UPDATE community_members SET status='rejected',role='member' WHERE community_id=? AND user_id=?")->execute([$id,$target]);
                        $pdo->prepare('DELETE p FROM community_call_participants p JOIN community_calls c ON c.id=p.call_id WHERE c.community_id=? AND p.user_id=?')->execute([$id,$target]);
                        community_system($pdo,$id,$uid,$member['nama'].' dikeluarkan oleh '.$user['nama'].'.');
                    } elseif ($action==='community_role') {
                        $role=$post['role']??'';
                        if ($member['status']!=='approved' || !in_array($role,['admin','member'],true)) throw new RuntimeException('Peran anggota tidak valid.');
                        $pdo->prepare('UPDATE community_members SET role=? WHERE community_id=? AND user_id=?')->execute([$role,$id,$target]);
                        community_system($pdo,$id,$uid,$member['nama'].' menjadi '.strtolower(community_role_label($role)).'.');
                    } else {
                        if ($member['status']!=='approved') throw new RuntimeException('Leader baru harus anggota aktif.');
                        $pdo->prepare('UPDATE communities SET owner_id=? WHERE id=?')->execute([$target,$id]);
                        $pdo->prepare("UPDATE community_members SET role=CASE WHEN user_id=? THEN 'leader' ELSE 'admin' END WHERE community_id=? AND user_id IN (?,?)")->execute([$target,$id,$target,$uid]);
                        community_system($pdo,$id,$uid,$member['nama'].' menjadi leader baru.');
                    }
                    $message='Keanggotaan diperbarui.';
                    break;
                case 'community_member_add':
                    if (!community_allowed($c,'add_members')) throw new RuntimeException('Anda tidak dapat menambahkan anggota.');
                    $username=trim((string)($post['username']??''));
                    $q=$pdo->prepare('SELECT id_user,nama FROM `user` WHERE username=?'); $q->execute([$username]); $target=$q->fetch();
                    if (!$target || !can_view((int)$target['id_user'],'profile_visibility') || is_blocked((int)$c['owner_id'],(int)$target['id_user'])) throw new RuntimeException('Rider tidak ditemukan atau aksesnya terbatas.');
                    $q=$pdo->prepare('SELECT status FROM community_members WHERE community_id=? AND user_id=?'); $q->execute([$id,$target['id_user']]);
                    if ($q->fetchColumn()==='approved') throw new RuntimeException('Rider sudah menjadi anggota.');
                    $status=$c['my_role']==='member' && $c['requires_approval']?'pending':'approved';
                    $pdo->prepare("INSERT INTO community_members(community_id,user_id,status,role) VALUES(?,?,?,'member') ON DUPLICATE KEY UPDATE status=VALUES(status),role='member',joined_at=CURRENT_TIMESTAMP")->execute([$id,$target['id_user'],$status]);
                    if ($status==='approved') community_system($pdo,$id,$uid,$user['nama'].' menambahkan '.$target['nama'].'.');
                    notify((int)$target['id_user'],$uid,'community_added',$id,'Anda diundang ke '.$c['name'].'.','notify_community',$pdo);
                    $message=$status==='approved'?'Anggota ditambahkan.':'Anggota menunggu persetujuan admin.';
                    break;
                case 'community_info':
                    if (!community_allowed($c,'edit_info')) throw new RuntimeException('Hanya leader dan admin yang dapat mengedit info grup.');
                    [$name,$city,$region,$type,$description]=community_validate_info($post);
                    $image=tracked_upload_image($uploads,'image','community');
                    $pdo->prepare('UPDATE communities SET name=?,city=?,region=?,motorcycle_type=?,description=?,image=COALESCE(?,image) WHERE id=?')->execute([$name,$city,$region?:null,$type?:null,$description,$image,$id]);
                    community_system($pdo,$id,$uid,$user['nama'].' memperbarui info komunitas.');
                    break;
                case 'community_permissions':
                    if (!community_allowed($c,'manage')) throw new RuntimeException('Hanya leader dan admin yang dapat mengatur izin grup.');
                    $args=[];
                    foreach (['send_messages','edit_info','add_members','pin_messages','start_calls'] as $key) {
                        if (!in_array($post[$key]??'',['all','admins'],true)) throw new RuntimeException('Pengaturan izin tidak valid.');
                        $args[]=$post[$key];
                    }
                    $pdo->prepare('UPDATE communities SET send_messages=?,edit_info=?,add_members=?,pin_messages=?,start_calls=?,is_private=?,requires_approval=? WHERE id=?')->execute(array_merge($args,[(int)!empty($post['is_private']),(int)!empty($post['requires_approval']),$id]));
                    community_system($pdo,$id,$uid,$user['nama'].' memperbarui izin grup.');
                    break;
                case 'community_invite_reset':
                    if (!community_allowed($c,'manage')) throw new RuntimeException('Hanya leader dan admin yang dapat mereset tautan.');
                    $pdo->prepare('UPDATE communities SET invite_token=? WHERE id=?')->execute([bin2hex(random_bytes(32)),$id]);
                    $message='Tautan undangan baru dibuat. Tautan lama tidak berlaku.';
                    break;
                case 'community_delete':
                    if (!community_allowed($c,'delete') || (int)$c['owner_id']!==$uid) throw new RuntimeException('Hanya leader yang dapat menghapus komunitas.');
                    if (($post['confirm_delete'] ?? null)!=='1') throw new RuntimeException('Konfirmasi penghapusan komunitas diperlukan.');
                    $q=$pdo->prepare('SELECT attachment FROM community_messages WHERE community_id=? AND attachment IS NOT NULL');
                    $q->execute([$id]);
                    $deletedFiles=array_merge([$c['image']],$q->fetchAll(PDO::FETCH_COLUMN));
                    foreach ([
                        'DELETE r FROM community_message_reactions r JOIN community_messages m ON m.id=r.message_id WHERE m.community_id=?',
                        'DELETE h FROM community_message_hidden h JOIN community_messages m ON m.id=h.message_id WHERE m.community_id=?',
                        'DELETE s FROM community_call_signals s JOIN community_calls calls ON calls.id=s.call_id WHERE calls.community_id=?',
                        'DELETE p FROM community_call_participants p JOIN community_calls calls ON calls.id=p.call_id WHERE calls.community_id=?',
                        'DELETE FROM community_calls WHERE community_id=?',
                        'DELETE FROM community_messages WHERE community_id=?',
                        'DELETE FROM community_members WHERE community_id=?',
                        "DELETE FROM notifications WHERE entity_id=? AND LEFT(type,10)='community_'",
                        'DELETE FROM communities WHERE id=?',
                    ] as $sql) {
                        $pdo->prepare($sql)->execute([$id]);
                    }
                    $returnPage='communities';
                    $message='Komunitas dan seluruh data terkait telah dihapus permanen.';
                    break;
                case 'community_close':
                    if (!community_allowed($c,'close')) throw new RuntimeException('Hanya leader yang dapat menutup komunitas.');
                    community_system($pdo,$id,$uid,$user['nama'].' menutup komunitas.');
                    $pdo->prepare('UPDATE communities SET closed_at=NOW() WHERE id=?')->execute([$id]);
                    $pdo->prepare('UPDATE community_calls SET ended_at=NOW() WHERE community_id=? AND ended_at IS NULL')->execute([$id]);
                    $message='Komunitas ditutup. Riwayat masih dapat dibaca anggota.';
                    break;
                case 'community_mute':
                    $pdo->prepare('UPDATE community_members SET muted=? WHERE community_id=? AND user_id=?')->execute([(int)!empty($post['muted']),$id,$uid]);
                    $message=!empty($post['muted'])?'Notifikasi grup dibisukan.':'Notifikasi grup diaktifkan.';
                    break;
                default: throw new RuntimeException('Aksi komunitas tidak dikenal.');
            }
        }
        $pdo->commit();
        $uploads=[];
        foreach (array_unique(array_filter($deletedFiles)) as $path) {
            delete_upload_if_unreferenced($pdo,$path);
        }
        return [$message,$returnPage,in_array($returnPage,['community','community_settings'],true)?['id'=>$id]:[]];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function community_chat_state(PDO $pdo, int $id, int $uid, int $before=0, string $search=''): array
{
    $c=community_require($pdo,$id,$uid);
    $where='m.community_id=? AND NOT EXISTS(SELECT 1 FROM community_message_hidden h WHERE h.message_id=m.id AND h.user_id=?)';
    $args=[$id,$uid];
    if ($before>0) { $where.=' AND m.id<?'; $args[]=$before; }
    if ($search!=='') { $where.=' AND m.deleted_at IS NULL AND m.body LIKE ?'; $args[]='%'.mb_substr($search,0,100).'%'; }
    $q=$pdo->prepare("SELECT m.*,TIMESTAMPDIFF(SECOND,m.created_at,NOW()) AS age_seconds,(m.pinned_until>NOW()) AS is_pinned,u.nama,u.username,u.profile_photo,cm.role,
        r.body AS reply_body,r.deleted_at AS reply_deleted,ru.nama AS reply_name
        FROM community_messages m LEFT JOIN `user` u ON u.id_user=m.sender_id
        LEFT JOIN community_members cm ON cm.community_id=m.community_id AND cm.user_id=m.sender_id
        LEFT JOIN community_messages r ON r.id=m.reply_to AND r.community_id=m.community_id
        LEFT JOIN `user` ru ON ru.id_user=r.sender_id WHERE $where ORDER BY m.id DESC LIMIT 51");
    $q->execute($args); $rows=$q->fetchAll(); $hasMore=count($rows)>50; $rows=array_reverse(array_slice($rows,0,50));
    $last=(int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM community_messages WHERE community_id='.(int)$id)->fetchColumn();
    if (!$before && $search==='') {
        $pdo->prepare('UPDATE community_members SET last_read_message_id=GREATEST(last_read_message_id,?) WHERE community_id=? AND user_id=?')->execute([$last,$id,$uid]);
        $pdo->prepare("UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE recipient_id=? AND entity_id=? AND type IN ('community_message','community_call')")->execute([$uid,$id]);
    }
    $q=$pdo->prepare("SELECT user_id,last_read_message_id FROM community_members WHERE community_id=? AND status='approved' AND user_id<>?"); $q->execute([$id,$uid]); $readers=$q->fetchAll();
    $messages=[];
    foreach ($rows as $row) {
        $deleted=!empty($row['deleted_at']); $mine=(int)$row['sender_id']===$uid; $age=(int)$row['age_seconds'];
        $q=$pdo->prepare('SELECT emoji,COUNT(*) AS total,MAX(user_id=?) AS mine FROM community_message_reactions WHERE message_id=? GROUP BY emoji'); $q->execute([$uid,$row['id']]);
        $readCount=0; foreach($readers as $reader) if ((int)$reader['last_read_message_id']>=(int)$row['id']) $readCount++;
        $messages[]=[
            'id'=>(int)$row['id'],'sender_id'=>(int)$row['sender_id'],'name'=>$row['nama']?:'Rider','username'=>$row['username'],
            'role'=>community_role_label((int)$row['sender_id']===(int)$c['owner_id']?'leader':($row['role']?:'member')),
            'body'=>$deleted?((int)$row['deleted_by']===(int)$row['sender_id']?'Pesan ini dihapus.':'Pesan dihapus oleh admin.'):$row['body'],
            'kind'=>$row['kind'],'mine'=>$mine,'deleted'=>$deleted,'edited'=>(bool)$row['edited_at'],
            'time'=>date('H:i',strtotime($row['created_at'])),'date'=>date('d M Y',strtotime($row['created_at'])),
            'attachment'=>!$deleted && $row['attachment']?url('community',['id'=>$id,'ajax'=>'community_attachment','message_id'=>$row['id']]):null,
            'reply'=>$row['reply_to']?['id'=>(int)$row['reply_to'],'name'=>$row['reply_name']?:'Rider','body'=>$row['reply_deleted']?'Pesan dihapus.':mb_substr($row['reply_body']?:'Foto',0,120)]:null,
            'reactions'=>$deleted?[]:$q->fetchAll(),'pinned'=>!$deleted && (bool)$row['is_pinned'],
            'can_edit'=>!$deleted && $mine && $age<=900 && $row['kind']==='message' && community_allowed($c,'send_messages'),
            'can_delete'=>!$deleted && $age<=172800 && ($mine || community_allowed($c,'manage')) && $row['kind']==='message',
            'read_count'=>$readCount,'recipient_count'=>count($readers),
        ];
    }
    $q=$pdo->prepare('SELECT id,body FROM community_messages WHERE community_id=? AND pinned_until>NOW() AND deleted_at IS NULL ORDER BY pinned_until DESC LIMIT 3'); $q->execute([$id]);
    return ['messages'=>$messages,'pins'=>$q->fetchAll(),'has_more'=>$hasMore,'role'=>$c['my_role'],'can_send'=>community_allowed($c,'send_messages'),'can_pin'=>community_allowed($c,'pin_messages'),'can_start_call'=>community_allowed($c,'start_calls'),'closed'=>(bool)$c['closed_at'],'call'=>community_active_call($pdo,$id,$uid)];
}
function community_chat_action(PDO $pdo, int $id, int $uid, string $action, array $post): array
{
    $uploads=[];
    $pdo->beginTransaction();
    try {
        $c=community_require($pdo,$id,$uid,true);
        if ($c['closed_at']) throw new RuntimeException('Komunitas sudah ditutup.');
        if ($action==='send') {
            if (!community_allowed($c,'send_messages')) throw new RuntimeException('Hanya leader dan admin yang dapat mengirim pesan.');
            $body=trim(is_string($post['body']??null)?$post['body']:'');
            if (mb_strlen($body)>4000) throw new RuntimeException('Pesan maksimal 4.000 karakter.');
            $photo=tracked_upload_image($uploads,'attachment','group');
            if ($body==='' && !$photo) throw new RuntimeException('Tulis pesan atau pilih foto.');
            $reply=(int)($post['reply_to']??0);
            if ($reply) { $q=$pdo->prepare('SELECT 1 FROM community_messages WHERE id=? AND community_id=? AND deleted_at IS NULL'); $q->execute([$reply,$id]); if (!$q->fetchColumn()) throw new RuntimeException('Pesan balasan tidak tersedia.'); }
            $q=$pdo->prepare('INSERT INTO community_messages(community_id,sender_id,body,attachment,reply_to) VALUES(?,?,?,?,?)'); $q->execute([$id,$uid,$body,$photo,$reply?:null]);
            $messageId=(int)$pdo->lastInsertId();
            community_notify($pdo,$id,$uid,'community_message',$c['name'].': '.($body?:'Foto baru'));
        } else {
            $messageId=(int)($post['message_id']??0);
            $q=$pdo->prepare('SELECT *,TIMESTAMPDIFF(SECOND,created_at,NOW()) AS age_seconds FROM community_messages WHERE id=? AND community_id=? FOR UPDATE'); $q->execute([$messageId,$id]); $m=$q->fetch();
            if (!$m || $m['kind']!=='message') throw new RuntimeException('Pesan tidak tersedia.');
            $mine=(int)$m['sender_id']===$uid; $age=(int)$m['age_seconds'];
            if ($action==='hide') {
                $pdo->prepare('INSERT IGNORE INTO community_message_hidden(message_id,user_id) VALUES(?,?)')->execute([$messageId,$uid]);
            } elseif ($m['deleted_at']) throw new RuntimeException('Pesan sudah dihapus.');
            elseif ($action==='edit') {
                if (!$mine || $age>900 || !community_allowed($c,'send_messages')) throw new RuntimeException('Anda hanya dapat mengedit pesan sendiri dalam 15 menit.');
                $body=trim((string)($post['body']??''));
                if (($body==='' && !$m['attachment']) || mb_strlen($body)>4000) throw new RuntimeException('Isi pesan tidak valid.');
                $pdo->prepare('UPDATE community_messages SET body=?,edited_at=NOW() WHERE id=?')->execute([$body,$messageId]);
            } elseif ($action==='delete') {
                if ($age>172800 || (!$mine && !community_allowed($c,'manage'))) throw new RuntimeException('Hapus untuk semua hanya tersedia untuk pengirim atau admin dalam 2 hari.');
                $pdo->prepare("UPDATE community_messages SET body='',deleted_at=NOW(),deleted_by=?,pinned_until=NULL WHERE id=?")->execute([$uid,$messageId]);
                $pdo->prepare('DELETE FROM community_message_reactions WHERE message_id=?')->execute([$messageId]);
            } elseif ($action==='react') {
                $emoji=$post['emoji']??'';
                if (!in_array($emoji,['👍','❤️','😂','😮','😢','🙏'],true)) throw new RuntimeException('Reaksi tidak valid.');
                $q=$pdo->prepare('SELECT emoji FROM community_message_reactions WHERE message_id=? AND user_id=?'); $q->execute([$messageId,$uid]);
                if ($q->fetchColumn()===$emoji) $pdo->prepare('DELETE FROM community_message_reactions WHERE message_id=? AND user_id=?')->execute([$messageId,$uid]);
                else $pdo->prepare('INSERT INTO community_message_reactions(message_id,user_id,emoji) VALUES(?,?,?) ON DUPLICATE KEY UPDATE emoji=VALUES(emoji)')->execute([$messageId,$uid,$emoji]);
            } elseif ($action==='pin' || $action==='unpin') {
                if (!community_allowed($c,'pin_messages')) throw new RuntimeException('Anda tidak memiliki izin menyematkan pesan.');
                if ($action==='unpin') $pdo->prepare('UPDATE community_messages SET pinned_until=NULL,pinned_by=NULL WHERE id=?')->execute([$messageId]);
                else {
                    $hours=(int)($post['hours']??168);
                    if (!in_array($hours,[24,168,720],true)) throw new RuntimeException('Durasi sematan tidak valid.');
                    $q=$pdo->prepare('SELECT COUNT(*) FROM community_messages WHERE community_id=? AND pinned_until>NOW() AND id<>?'); $q->execute([$id,$messageId]);
                    if ((int)$q->fetchColumn()>=3) throw new RuntimeException('Maksimal tiga pesan disematkan. Lepas sematan lain dahulu.');
                    $pdo->prepare('UPDATE community_messages SET pinned_until=DATE_ADD(NOW(),INTERVAL '.$hours.' HOUR),pinned_by=? WHERE id=?')->execute([$uid,$messageId]);
                }
            } else throw new RuntimeException('Aksi pesan tidak valid.');
        }
        $pdo->commit(); $uploads=[];
        return ['ok'=>true,'message_id'=>$messageId];
    } catch(Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach($uploads as $path) delete_upload($path);
        throw $e;
    }
}
function community_ajax(string $type): never
{
    $user=require_login(); $uid=(int)$user['id_user']; $pdo=db(); $id=(int)($_GET['id']??0);
    try {
        if ($type==='community_attachment') {
            community_require($pdo,$id,$uid);
            $q=$pdo->prepare('SELECT attachment FROM community_messages WHERE community_id=? AND id=? AND deleted_at IS NULL'); $q->execute([$id,(int)($_GET['message_id']??0)]); $path=$q->fetchColumn();
            if (!$path || !preg_match('~^uploads/group/[a-f0-9]{36}\.(jpg|png|webp)$~D',$path)) throw new RuntimeException('Foto tidak tersedia.');
            $file=dirname(__DIR__).'/'.$path;
            if (!is_file($file)) throw new RuntimeException('Foto tidak tersedia.');
            header('Content-Type: '.(new finfo(FILEINFO_MIME_TYPE))->file($file)); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store'); readfile($file); exit;
        }
        if ($type==='community_chat' && $_SERVER['REQUEST_METHOD']==='GET') $result=community_chat_state($pdo,$id,$uid,(int)($_GET['before']??0),trim((string)($_GET['q']??'')));
        else {
            if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); throw new RuntimeException('Gunakan metode POST.'); }
            csrf_check();
            $result=$type==='community_chat'?community_chat_action($pdo,$id,$uid,(string)($_POST['chat_action']??''),$_POST):community_call_action($pdo,$id,$uid,(string)($_POST['call_action']??''),$_POST);
        }
    } catch (Throwable $e) {
        if (http_response_code()<400) http_response_code($e instanceof PDOException?500:403);
        if ($e instanceof PDOException) error_log($e->getMessage());
        $result=['error'=>$e instanceof PDOException?'Permintaan gagal diproses. Coba kembali.':$e->getMessage()];
    }
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit;
}
require_once __DIR__.'/community-calls.php';
