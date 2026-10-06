<?php
declare(strict_types=1);

function community_active_call(PDO $pdo, int $id, int $uid): ?array
{
    $q=$pdo->prepare("SELECT c.id,c.kind,u.nama AS started_name FROM community_calls c JOIN `user` u ON u.id_user=c.started_by
        WHERE c.community_id=? AND c.ended_at IS NULL AND EXISTS(SELECT 1 FROM community_call_participants p
        JOIN community_members cm ON cm.community_id=c.community_id AND cm.user_id=p.user_id AND cm.status='approved'
        WHERE p.call_id=c.id AND p.last_seen>DATE_SUB(NOW(),INTERVAL 45 SECOND)) ORDER BY c.id DESC LIMIT 1");
    $q->execute([$id]); $call=$q->fetch();
    if (!$call) return null;
    $q=$pdo->prepare("SELECT p.user_id,p.session_token,u.nama FROM community_call_participants p JOIN `user` u ON u.id_user=p.user_id
        JOIN community_members cm ON cm.community_id=? AND cm.user_id=p.user_id AND cm.status='approved'
        WHERE p.call_id=? AND p.last_seen>DATE_SUB(NOW(),INTERVAL 45 SECOND) ORDER BY p.user_id");
    $q->execute([$id,$call['id']]); $participants=$q->fetchAll();
    return ['id'=>(int)$call['id'],'kind'=>$call['kind'],'started_name'=>$call['started_name'],'participants'=>array_map(static fn($p)=>['id'=>(int)$p['user_id'],'name'=>$p['nama'],'session'=>$p['session_token']],$participants)];
}
function community_ice_servers(): array
{
    $configured=getenv('MOTORA_WEBRTC_ICE_SERVERS');
    if ($configured!==false && $configured!=='') {
        $servers=json_decode($configured,true);
        if (is_array($servers) && array_is_list($servers)) return $servers;
    }
    return [['urls'=>'stun:stun.l.google.com:19302']];
}
function community_call_action(PDO $pdo, int $id, int $uid, string $action, array $post): array
{
    $pdo->beginTransaction();
    try {
        $c=community_require($pdo,$id,$uid,true);
        $callId=(int)($post['call_id']??0);
        $session=(string)($post['session']??'');
        if ($action==='start' || $action==='join') {
            if ($c['closed_at']) throw new RuntimeException('Komunitas sudah ditutup.');
            $active=community_active_call($pdo,$id,$uid);
            if ($action==='start') {
                if (!community_allowed($c,'start_calls')) throw new RuntimeException('Hanya leader dan admin yang dapat memulai panggilan.');
                $kind=(string)($post['kind']??'audio');
                if (!in_array($kind,['audio','video'],true)) throw new RuntimeException('Jenis panggilan tidak valid.');
                if (!$active) {
                    $pdo->prepare('UPDATE community_calls SET ended_at=NOW() WHERE community_id=? AND ended_at IS NULL')->execute([$id]);
                    $pdo->prepare('INSERT INTO community_calls(community_id,started_by,kind) VALUES(?,?,?)')->execute([$id,$uid,$kind]);
                    $callId=(int)$pdo->lastInsertId();
                    community_system($pdo,$id,$uid,current_user()['nama'].' memulai panggilan '.($kind==='video'?'video':'suara').'.');
                    community_notify($pdo,$id,$uid,'community_call','Panggilan '.($kind==='video'?'video':'suara').' di '.$c['name'].'.');
                } else $callId=$active['id'];
            } elseif (!$active || $active['id']!==$callId) throw new RuntimeException('Panggilan sudah berakhir.');
            if ($active && count($active['participants'])>=8 && !in_array($uid,array_column($active['participants'],'id'),true)) throw new RuntimeException('Panggilan sudah penuh (maksimal 8 peserta).');
            $q=$pdo->prepare('SELECT 1 FROM community_call_participants WHERE call_id=? AND user_id=? AND last_seen>DATE_SUB(NOW(),INTERVAL 45 SECOND)'); $q->execute([$callId,$uid]);
            if ($q->fetchColumn()) throw new RuntimeException('Anda sudah mengikuti panggilan ini di tab lain.');
            $session=bin2hex(random_bytes(16));
            $pdo->prepare('INSERT INTO community_call_participants(call_id,user_id,session_token,last_seen) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE session_token=VALUES(session_token),last_seen=NOW()')->execute([$callId,$uid,$session]);
            $result=['ok'=>true,'session'=>$session,'call'=>community_active_call($pdo,$id,$uid),'ice_servers'=>community_ice_servers()];
        } else {
            $q=$pdo->prepare('SELECT c.*,p.session_token FROM community_calls c LEFT JOIN community_call_participants p ON p.call_id=c.id AND p.user_id=? WHERE c.id=? AND c.community_id=? FOR UPDATE'); $q->execute([$uid,$callId,$id]); $call=$q->fetch();
            if (!$call || $call['ended_at']) throw new RuntimeException('Panggilan sudah berakhir.');
            if ($action==='end') {
                if (!community_allowed($c,'manage')) throw new RuntimeException('Hanya leader dan admin yang dapat mengakhiri panggilan untuk semua.');
                $pdo->prepare('UPDATE community_calls SET ended_at=NOW() WHERE id=?')->execute([$callId]);
                $result=['ok'=>true];
            } else {
                if (!$call['session_token'] || !$session || !hash_equals($call['session_token'],$session)) throw new RuntimeException('Sesi panggilan tidak valid.');
                if ($c['closed_at']) throw new RuntimeException('Komunitas sudah ditutup.');
                if ($action==='leave') {
                    $pdo->prepare('DELETE FROM community_call_participants WHERE call_id=? AND user_id=? AND session_token=?')->execute([$callId,$uid,$session]);
                    if (!community_active_call($pdo,$id,$uid)) $pdo->prepare('UPDATE community_calls SET ended_at=NOW() WHERE id=?')->execute([$callId]);
                    $result=['ok'=>true];
                } elseif ($action==='signal') {
                    $target=(int)($post['recipient']??0); $payload=(string)($post['payload']??''); $data=json_decode($payload,true);
                    if ($target===$uid || strlen($payload)>65536 || !is_array($data) || !in_array($data['type']??'',['offer','answer','candidate'],true)) throw new RuntimeException('Sinyal panggilan tidak valid.');
                    $q=$pdo->prepare("SELECT p.session_token FROM community_call_participants p JOIN community_members cm ON cm.user_id=p.user_id AND cm.community_id=? AND cm.status='approved'
                        WHERE p.call_id=? AND p.user_id=? AND p.last_seen>DATE_SUB(NOW(),INTERVAL 45 SECOND)"); $q->execute([$id,$callId,$target]); $recipientSession=$q->fetchColumn();
                    if (!$recipientSession || !hash_equals($recipientSession,(string)($post['recipient_session']??''))) throw new RuntimeException('Peserta sudah meninggalkan panggilan.');
                    $pdo->prepare('INSERT INTO community_call_signals(call_id,sender_id,recipient_id,sender_session,recipient_session,payload) VALUES(?,?,?,?,?,?)')->execute([$callId,$uid,$target,$session,$recipientSession,$payload]);
                    $result=['ok'=>true];
                } elseif ($action==='poll') {
                    $pdo->prepare('UPDATE community_call_participants SET last_seen=NOW() WHERE call_id=? AND user_id=?')->execute([$callId,$uid]);
                    $q=$pdo->prepare('SELECT id,sender_id,sender_session,payload FROM community_call_signals WHERE call_id=? AND recipient_id=? AND recipient_session=? AND id>? AND created_at>DATE_SUB(NOW(),INTERVAL 2 MINUTE) ORDER BY id LIMIT 100'); $q->execute([$callId,$uid,$session,max(0,(int)($post['after']??0))]);
                    $signals=$q->fetchAll();
                    $result=['ok'=>true,'call'=>community_active_call($pdo,$id,$uid),'signals'=>array_map(static fn($s)=>['id'=>(int)$s['id'],'sender'=>(int)$s['sender_id'],'session'=>$s['sender_session'],'data'=>json_decode($s['payload'],true)],$signals)];
                } else throw new RuntimeException('Aksi panggilan tidak valid.');
            }
        }
        $pdo->commit(); return $result;
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
