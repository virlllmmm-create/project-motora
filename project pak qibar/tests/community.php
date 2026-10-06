<?php
declare(strict_types=1);
// All mutations use connection-local tables shadowing the live application.
$root=getenv('MOTORA_COMMUNITY_TEST_ROOT') ?: dirname(__DIR__);
require $root.'/config/database.php';
require $root.'/includes/functions.php';
require $root.'/includes/actions.php';
error_reporting(E_ALL);
set_error_handler(static function(int $severity,string $message,string $file,int $line): never { throw new ErrorException($message,0,$severity,$file,$line); });
function group_test_tables(PDO $pdo): void
{
    foreach(['user','user_settings','friend_requests','communities','community_members','community_messages','community_message_reactions','community_message_hidden','community_calls','community_call_participants','community_call_signals','notifications'] as $table) {
        $columns=[]; $primary=[];
        foreach($pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll() as $column) {
            $definition='`'.$column['Field'].'` '.$column['Type'].($column['Null']==='YES'?' NULL':' NOT NULL');
            if(str_contains($column['Extra'],'auto_increment')) $definition.=' AUTO_INCREMENT';
            elseif($column['Default']!==null) $definition.=' DEFAULT '.(str_starts_with(strtolower($column['Default']),'current_timestamp')?'CURRENT_TIMESTAMP':$pdo->quote($column['Default']));
            if($column['Key']==='PRI') $primary[]='`'.$column['Field'].'`';
            $columns[]=$definition;
        }
        if($primary) $columns[]='PRIMARY KEY('.implode(',',$primary).')';
        $pdo->exec('CREATE TEMPORARY TABLE `'.$table.'` ('.implode(',',$columns).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }
}
function group_check(bool $value,string $message): void { if(!$value) throw new RuntimeException('FAIL: '.$message); echo 'PASS: '.$message."\n"; }
function group_denied(callable $callback,string $message): void { try { $callback(); } catch(RuntimeException $e) { group_check(!($e instanceof PDOException),$message); return; } throw new RuntimeException('FAIL: action was allowed: '.$message); }
group_check(action_referer_same_origin(parse_url('http://127.0.0.1:18575/index.php?page=community_settings&id=1'),['HTTP_HOST'=>'127.0.0.1:18575']),'same development host and port permit returning to settings');
group_check(!action_referer_same_origin(parse_url('http://127.0.0.1:18576/index.php'),['HTTP_HOST'=>'127.0.0.1:18575']),'a different referer port is rejected');
group_check(!action_referer_same_origin(parse_url('http://elsewhere.test:18575/index.php'),['HTTP_HOST'=>'127.0.0.1:18575']),'a different referer host is rejected');
group_check(action_referer_same_origin(parse_url('http://LOCALHOST:80/index.php'),['HTTP_HOST'=>'localhost']),'default HTTP port and hostname casing are normalized');
group_check(action_referer_same_origin(parse_url('https://localhost:443/index.php'),['HTTP_HOST'=>'localhost','HTTPS'=>'on']),'default HTTPS port is normalized');
group_check(!action_referer_same_origin(parse_url('http://localhost:443/index.php'),['HTTP_HOST'=>'localhost','HTTPS'=>'on']),'a different referer scheme is rejected');
group_check(action_referer_same_origin(parse_url('index.php?page=community_settings&id=1'),['HTTP_HOST'=>'localhost']),'relative application referer remains accepted');
group_check(!action_referer_same_origin(parse_url('mailto:someone@example.test'),['HTTP_HOST'=>'localhost']),'scheme-only referer is rejected');
$pdo=db(); group_test_tables($pdo);
$pdo->exec("INSERT INTO `user`(id_user,nama,username,email,password_hash) VALUES
 (100,'Leader Rider','leader','leader@example.test','test'),(101,'Admin Rider','admin','admin@example.test','test'),
 (102,'Member Rider','member','member@example.test','test'),(103,'Outside Rider','outside','outside@example.test','test'),
 (104,'Second Member','second','second@example.test','test')");
$pdo->exec('INSERT INTO user_settings(user_id) VALUES(100),(101),(102),(103),(104)');
$_SESSION=['user_id'=>100,'csrf'=>'community-test'];
$_SERVER['REQUEST_METHOD']='POST'; $_SERVER['HTTP_HOST']='localhost'; $_SERVER['SCRIPT_NAME']='/index.php'; $_FILES=[];
$uploads=[]; $leader=current_user();
$result=community_handle_action($pdo,100,$leader,'community_create',['name'=>'Sunmori Circle','city'=>'Bandung','description'=>'Teman satu perjalanan'],$uploads);
$id=(int)$result[2]['id'];
group_check($result[1]==='community' && $id>0,'creation redirects to community chat with its real ID');
group_check(community_require($pdo,$id,100)['my_role']==='leader','creator is automatically the leader');
$pdo->prepare("INSERT INTO community_members(community_id,user_id,status,role) VALUES(?,101,'approved','admin'),(?,102,'approved','member'),(?,104,'approved','member')")->execute([$id,$id,$id]);
$member=['id_user'=>102,'nama'=>'Member Rider']; $admin=['id_user'=>101,'nama'=>'Admin Rider'];
$sent=community_chat_action($pdo,$id,102,'send',['body'=>'Pesan anggota pertama']); $messageId=$sent['message_id'];
group_check($messageId>0,'approved member can send a group message');
group_denied(fn()=>community_chat_state($pdo,$id,103),'outsider cannot read group chat');
group_denied(fn()=>community_chat_action($pdo,$id,103,'send',['body'=>'Unauthorized']),'outsider cannot send group messages');
group_denied(fn()=>community_chat_action($pdo,$id,101,'edit',['message_id'=>$messageId,'body'=>'Hijack']),'admin cannot edit another participant message');
group_denied(fn()=>community_handle_action($pdo,102,$member,'community_role',['community_id'=>$id,'member_id'=>104,'role'=>'admin'],$uploads),'member cannot promote an admin');
group_denied(fn()=>community_handle_action($pdo,101,$admin,'community_role',['community_id'=>$id,'member_id'=>104,'role'=>'admin'],$uploads),'only leader can assign roles');
group_denied(fn()=>community_handle_action($pdo,101,$admin,'community_member_remove',['community_id'=>$id,'member_id'=>100],$uploads),'admin cannot remove leader');
$result=community_handle_action($pdo,100,$leader,'community_role',['community_id'=>$id,'member_id'=>104,'role'=>'admin'],$uploads);
group_check($result[1]==='community_settings' && $result[2]===['id'=>$id],'role changes return to settings for the same community');
group_check(community_require($pdo,$id,104)['my_role']==='admin','leader can recruit a member as admin');
group_denied(fn()=>community_handle_action($pdo,101,$admin,'community_member_remove',['community_id'=>$id,'member_id'=>104],$uploads),'admin cannot remove another admin');
community_chat_action($pdo,$id,102,'edit',['message_id'=>$messageId,'body'=>'Pesan diperbarui']);
community_chat_action($pdo,$id,101,'react',['message_id'=>$messageId,'emoji'=>'👍']);
community_chat_action($pdo,$id,101,'pin',['message_id'=>$messageId,'hours'=>168]);
$state=community_chat_state($pdo,$id,100);
group_check(count($state['pins'])===1 && $state['messages'][1]['body']==='Pesan diperbarui','edited messages and pinned banner are returned');
group_check((int)$pdo->query('SELECT COUNT(*) FROM community_message_reactions')->fetchColumn()===1,'emoji reaction is stored per participant');
community_chat_action($pdo,$id,101,'react',['message_id'=>$messageId,'emoji'=>'👍']);
group_check((int)$pdo->query('SELECT COUNT(*) FROM community_message_reactions')->fetchColumn()===0,'same reaction toggles off');
group_denied(fn()=>community_chat_action($pdo,$id,102,'pin',['message_id'=>$messageId]),'ordinary member cannot pin under admin-only permission');
$pdo->prepare('UPDATE community_messages SET created_at=DATE_SUB(NOW(),INTERVAL 16 MINUTE) WHERE id=?')->execute([$messageId]);
group_denied(fn()=>community_chat_action($pdo,$id,102,'edit',['message_id'=>$messageId,'body'=>'Too late']),'editing is limited to 15 minutes');
community_chat_action($pdo,$id,101,'delete',['message_id'=>$messageId]);
group_check($pdo->query('SELECT deleted_at FROM community_messages WHERE id='.$messageId)->fetchColumn()!==null,'admin can delete a group message for everyone');
group_check(!$pdo->query('SELECT pinned_until FROM community_messages WHERE id='.$messageId)->fetchColumn(),'deleted message no longer stays pinned');
$permissions=['community_id'=>$id,'send_messages'=>'admins','edit_info'=>'admins','add_members'=>'admins','pin_messages'=>'admins','start_calls'=>'admins'];
$result=community_handle_action($pdo,101,$admin,'community_permissions',$permissions,$uploads);
group_check($result[1]==='community_settings' && $result[2]===['id'=>$id],'permission changes keep admin on community settings');
group_denied(fn()=>community_chat_action($pdo,$id,102,'send',['body'=>'Forbidden']),'admin-only send permission is enforced on server');
group_denied(fn()=>community_call_action($pdo,$id,102,'start',['kind'=>'audio']),'member cannot start a call under admin-only permission');
group_denied(fn()=>community_call_action($pdo,$id,103,'start',['kind'=>'audio']),'outsider cannot start a group call');
$call=community_call_action($pdo,$id,100,'start',['kind'=>'video']);
$join=community_call_action($pdo,$id,102,'join',['call_id'=>$call['call']['id']]);
group_check(count($join['call']['participants'])===2,'approved member can join an ongoing video call');
group_denied(fn()=>community_call_action($pdo,$id,102,'poll',['call_id'=>$call['call']['id'],'session'=>'forged']),'forged call session is rejected');
$signal=['call_id'=>$call['call']['id'],'session'=>$call['session'],'recipient'=>102,'recipient_session'=>$join['session'],'payload'=>json_encode(['type'=>'offer','sdp'=>'fixture'])];
community_call_action($pdo,$id,100,'signal',$signal);
$poll=community_call_action($pdo,$id,102,'poll',['call_id'=>$call['call']['id'],'session'=>$join['session'],'after'=>0]);
group_check(count($poll['signals'])===1 && $poll['signals'][0]['sender']===100,'call signaling is delivered only to its intended participant');
group_denied(fn()=>community_call_action($pdo,$id,102,'end',['call_id'=>$call['call']['id'],'session'=>$join['session']]),'member cannot end call for everyone');
community_handle_action($pdo,101,$admin,'community_member_remove',['community_id'=>$id,'member_id'=>102],$uploads);
group_denied(fn()=>community_call_action($pdo,$id,102,'poll',['call_id'=>$call['call']['id'],'session'=>$join['session']]),'removed member loses access to ongoing call');
group_denied(fn()=>community_chat_state($pdo,$id,102),'removed member loses access to group history');
community_call_action($pdo,$id,101,'end',['call_id'=>$call['call']['id']]);
group_check(community_active_call($pdo,$id,100)===null,'admin can end ongoing call');
$result=community_handle_action($pdo,100,$leader,'community_transfer',['community_id'=>$id,'member_id'=>104],$uploads);
group_check($result[1]==='community_settings' && $result[2]===['id'=>$id],'leadership transfer returns to settings');
group_check(community_require($pdo,$id,104)['my_role']==='leader' && community_require($pdo,$id,100)['my_role']==='admin','leadership transfer creates one leader and demotes former leader to admin');
$pdo->prepare("UPDATE communities SET is_private=1,requires_approval=1 WHERE id=?")->execute([$id]);
$token=$pdo->query('SELECT invite_token FROM communities WHERE id='.$id)->fetchColumn();
group_denied(fn()=>community_handle_action($pdo,103,['nama'=>'Outside Rider'],'community_join',['community_id'=>$id,'invite'=>'wrong'],$uploads),'private-group join requires valid invite');
$result=community_handle_action($pdo,103,['nama'=>'Outside Rider'],'community_join',['community_id'=>$id,'invite'=>$token],$uploads);
group_check($result[1]==='communities' && $result[2]===[],'pending private-group join still returns to community listing');
group_check(community_context($pdo,$id,103)['member_status']==='pending','approval setting keeps invited rider pending');
community_handle_action($pdo,104,['nama'=>'Second Member'],'community_approve',['community_id'=>$id,'member_id'=>103,'approve'=>'yes'],$uploads);
group_check(community_require($pdo,$id,103)['my_role']==='member','leader can approve invited rider');
community_handle_action($pdo,104,['nama'=>'Second Member'],'community_invite_reset',['community_id'=>$id],$uploads);
group_check($pdo->query('SELECT invite_token FROM communities WHERE id='.$id)->fetchColumn()!==$token,'reset invalidates previous invite token');
$uid=104; $user=['nama'=>'Second Member']; $page='community'; $_GET=['id'=>$id];
ob_start(); require $root.'/pages/community.php'; $html=ob_get_clean();
group_check(str_contains($html,'Chat komunitas') && str_contains($html,'data-community'),'community chat renders without the former SQL parameter error');
group_check(str_contains($html,e(url('community_settings',['id'=>$id]))),'chat header links to settings for the same community');
group_check(!str_contains($html,'value="community_mute"') && !str_contains($html,'value="community_permissions"') && !str_contains($html,'group-heading'),'chat excludes management forms and the duplicate community heading');
$page='community_settings'; ob_start(); require $root.'/pages/community-settings.php'; $settings=ob_get_clean();
group_check(str_contains($settings,'data-community-settings') && str_contains($settings,e(url('community',['id'=>$id]))),'separate settings page links back to its chat');
group_check(str_contains($settings,'value="community_role"') && str_contains($settings,'value="community_transfer"') && str_contains($settings,'value="community_permissions"'),'leader settings contain role, leadership, and permission controls');
group_check(str_contains($settings,'value="community_delete"') && str_contains($settings,'name="confirm_delete"') && strpos($settings,'group-delete"')>strpos($settings,'<summary>Anggota'),'leader settings place confirmed permanent deletion below members');

group_check(!str_contains($settings,'data-group-messages') && !str_contains($settings,'data-call-start'),'settings page excludes chat and call controls');
$uid=101; $user=$admin; ob_start(); require $root.'/pages/community-settings.php'; $adminSettings=ob_get_clean();
group_check(str_contains($adminSettings,'value="community_permissions"') && !str_contains($adminSettings,'value="community_role"') && !str_contains($adminSettings,'value="community_transfer"') && !str_contains($adminSettings,'value="community_close"') && !str_contains($adminSettings,'value="community_delete"'),'admin settings retain member management without leader-only controls');
$uid=103; $user=['nama'=>'Outside Rider']; ob_start(); require $root.'/pages/community-settings.php'; $memberSettings=ob_get_clean();
group_check(str_contains($memberSettings,'value="community_mute"') && str_contains($memberSettings,'value="community_leave"') && !str_contains($memberSettings,'value="community_permissions"') && !str_contains($memberSettings,'value="community_role"') && !str_contains($memberSettings,'value="community_delete"'),'member settings retain personal actions without administrative controls');
$uid=102; $user=$member; ob_start(); require $root.'/pages/community-settings.php'; $removedSettings=ob_get_clean();
group_check(http_response_code()===404 && !str_contains($removedSettings,'data-community-settings') && !str_contains($removedSettings,'<form'),'removed member cannot read community settings or management forms');
http_response_code(200);
$uid=104; $user=['nama'=>'Second Member'];
$page='communities'; ob_start(); require $root.'/pages/views/communities.php'; $listing=ob_get_clean();
group_check(str_contains($listing,'Leader') && str_contains($listing,'Sunmori Circle'),'community listing shows creator role correctly');
$result=community_handle_action($pdo,104,['nama'=>'Second Member'],'community_close',['community_id'=>$id],$uploads);
group_check($result[1]==='community_settings' && $result[2]===['id'=>$id],'closing a community returns to its read-only settings');
$result=community_handle_action($pdo,104,['nama'=>'Second Member'],'community_mute',['community_id'=>$id,'muted'=>1],$uploads);
group_check($result[1]==='community_settings' && $result[2]===['id'=>$id],'personal notification setting remains usable after closure');
group_denied(fn()=>community_chat_action($pdo,$id,104,'send',['body'=>'Closed']),'closing group disables new messages');
group_check(community_chat_state($pdo,$id,104)['closed'],'closed group keeps history available to members');

// Delete only connection-local community data and files created by this test.
$deletePost=['community_id'=>$id,'confirm_delete'=>'1'];
group_denied(fn()=>community_handle_action($pdo,101,$admin,'community_delete',$deletePost,$uploads),'admin cannot permanently delete a closed community');
group_denied(fn()=>community_handle_action($pdo,100,$leader,'community_delete',$deletePost,$uploads),'former leader cannot delete after transferring leadership');
group_denied(fn()=>community_handle_action($pdo,103,['nama'=>'Outside Rider'],'community_delete',$deletePost,$uploads),'ordinary member cannot permanently delete a community');
group_denied(fn()=>community_handle_action($pdo,102,$member,'community_delete',$deletePost,$uploads),'removed member cannot permanently delete a community');
group_denied(fn()=>community_handle_action($pdo,104,['nama'=>'Second Member'],'community_delete',['community_id'=>$id],$uploads),'permanent deletion requires an explicit confirmation');
group_denied(fn()=>community_handle_action($pdo,104,['nama'=>'Second Member'],'community_delete',['community_id'=>$id,'confirm_delete'=>['1']],$uploads),'malformed deletion confirmation is rejected');
group_check(community_allowed(community_require($pdo,$id,104),'delete') && !community_allowed(community_require($pdo,$id,101),'delete'),'closed-community deletion is restricted to its leader');
$uid=104; $user=['nama'=>'Second Member']; $_GET=['id'=>$id];
ob_start(); require $root.'/pages/community-settings.php'; $closedSettings=ob_get_clean();
group_check(str_contains($closedSettings,'value="community_delete"') && !str_contains($closedSettings,'value="community_close"'),'closed community still exposes permanent deletion to its leader');

class CommunityDeleteFailureConnection extends PDO
{
    public function __construct(private PDO $connection) {}
    public function beginTransaction(): bool { return $this->connection->beginTransaction(); }
    public function commit(): bool { return $this->connection->commit(); }
    public function rollBack(): bool { return $this->connection->rollBack(); }
    public function inTransaction(): bool { return $this->connection->inTransaction(); }
    public function prepare(string $query,array $options=[]): PDOStatement|false
    {
        if ($query==='DELETE FROM communities WHERE id=?') throw new RuntimeException('Simulated final delete failure.');
        return $this->connection->prepare($query,$options);
    }
}
$deleteTestFiles=[];
try {
    foreach (['community','group','community'] as $folder) {
        $file='uploads/'.$folder.'/'.bin2hex(random_bytes(18)).'.png';
        $deleteTestFiles[]=$file;
        if (file_put_contents($root.'/'.$file,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j9XkAAAAASUVORK5CYII='))===false) throw new RuntimeException('Could not create deletion test image.');
    }
    [$coverFile,$attachmentFile,$sharedFile]=$deleteTestFiles;
    $pdo->prepare('UPDATE communities SET image=? WHERE id=?')->execute([$coverFile,$id]);
    $otherResult=community_handle_action($pdo,100,$leader,'community_create',['name'=>'Retained Circle','city'=>'Jakarta','description'=>'Unrelated community stays available'],$uploads);
    $otherId=(int)$otherResult[2]['id'];
    $pdo->prepare('UPDATE communities SET image=? WHERE id=?')->execute([$sharedFile,$otherId]);
    $pdo->prepare('INSERT INTO community_messages(community_id,sender_id,body,attachment,deleted_at) VALUES(?,104,?,?,NOW())')->execute([$id,'Photo from a previously deleted message',$attachmentFile]);
    $photoMessageId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO community_messages(community_id,sender_id,body,attachment) VALUES(?,104,?,?)')->execute([$id,'Shared image',$sharedFile]);
    $pdo->prepare("INSERT INTO community_message_reactions(message_id,user_id,emoji) VALUES(?,103,'👍')")->execute([$photoMessageId]);
    $pdo->prepare('INSERT INTO community_message_hidden(message_id,user_id) VALUES(?,103)')->execute([$photoMessageId]);
    $pdo->prepare("INSERT INTO notifications(recipient_id,actor_id,type,entity_id,body) VALUES(103,104,'community_message',?,'Group activity'),(103,100,'friend_request',?,'Unrelated friendship'),(104,100,'community_added',?,'Other community activity')")->execute([$id,$id,$otherId]);
    $messageIds=$pdo->query('SELECT id FROM community_messages WHERE community_id='.(int)$id)->fetchAll(PDO::FETCH_COLUMN);
    $callIds=$pdo->query('SELECT id FROM community_calls WHERE community_id='.(int)$id)->fetchAll(PDO::FETCH_COLUMN);
    $tables=['communities','community_members','community_messages','community_message_reactions','community_message_hidden','community_calls','community_call_participants','community_call_signals','notifications'];
    $snapshot=static function() use($pdo,$tables): array {
        $counts=[];
        foreach($tables as $table) $counts[$table]=(int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
        return $counts;
    };
    $beforeFailure=$snapshot();
    group_denied(fn()=>community_handle_action(new CommunityDeleteFailureConnection($pdo),104,['nama'=>'Second Member'],'community_delete',$deletePost,$uploads),'database failure aborts permanent deletion');
    group_check($snapshot()===$beforeFailure && !$pdo->inTransaction(),'failed deletion rolls back all community data');
    group_check(is_file($root.'/'.$coverFile) && is_file($root.'/'.$attachmentFile) && is_file($root.'/'.$sharedFile),'failed deletion keeps all uploaded files');

    $otherBefore=$pdo->query('SELECT * FROM communities WHERE id='.(int)$otherId)->fetch();
    $result=community_handle_action($pdo,104,['nama'=>'Second Member'],'community_delete',$deletePost,$uploads);
    group_check($result[1]==='communities' && $result[2]===[],'permanent deletion returns to community listing');
    group_check(community_context($pdo,$id,104)===null,'deleted community is physically removed');
    foreach(['community_members','community_messages','community_calls'] as $table) {
        group_check((int)$pdo->query('SELECT COUNT(*) FROM '.$table.' WHERE community_id='.(int)$id)->fetchColumn()===0,'permanent deletion removes all '.$table);
    }
    foreach(['community_message_reactions','community_message_hidden'] as $table) {
        group_check((int)$pdo->query('SELECT COUNT(*) FROM '.$table.' WHERE message_id IN ('.implode(',',array_map('intval',$messageIds)).')')->fetchColumn()===0,'permanent deletion removes all '.$table);
    }
    foreach(['community_call_participants','community_call_signals'] as $table) {
        group_check((int)$pdo->query('SELECT COUNT(*) FROM '.$table.' WHERE call_id IN ('.implode(',',array_map('intval',$callIds)).')')->fetchColumn()===0,'permanent deletion removes all '.$table);
    }
    group_check((int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE entity_id=".(int)$id." AND LEFT(type,10)='community_'")->fetchColumn()===0,'deleted community leaves no stale notifications');
    group_check((int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE entity_id=".(int)$id." AND type='friend_request'")->fetchColumn()===1,'unrelated notifications with the same numeric ID are preserved');
    group_check(!is_file($root.'/'.$coverFile) && !is_file($root.'/'.$attachmentFile),'permanent deletion removes the cover and attachments including previously deleted photos');
    group_check(is_file($root.'/'.$sharedFile),'an image still referenced by another community is preserved');
    group_check($pdo->query('SELECT * FROM communities WHERE id='.(int)$otherId)->fetch()===$otherBefore,'unrelated community remains unchanged');
    group_check((int)$pdo->query('SELECT COUNT(*) FROM community_members WHERE community_id='.(int)$otherId)->fetchColumn()===1 && (int)$pdo->query('SELECT COUNT(*) FROM community_messages WHERE community_id='.(int)$otherId)->fetchColumn()===1,'unrelated membership and chat history are preserved');
    group_check((int)$pdo->query('SELECT COUNT(*) FROM user')->fetchColumn()===5,'community deletion preserves rider accounts');
    group_denied(fn()=>community_chat_state($pdo,$id,104),'deleted community chat is no longer available');
    group_denied(fn()=>community_handle_action($pdo,104,['nama'=>'Second Member'],'community_delete',$deletePost,$uploads),'deleted community cannot be deleted again');
    $uid=104; $user=['nama'=>'Second Member']; $_GET=['id'=>$id];
    ob_start(); require $root.'/pages/community-settings.php'; $deletedSettings=ob_get_clean();
    group_check(http_response_code()===404 && !str_contains($deletedSettings,'<form'),'deleted community settings return not found');
    http_response_code(200);

    $result=community_handle_action($pdo,100,$leader,'community_delete',['community_id'=>$otherId,'confirm_delete'=>'1'],$uploads);
    group_check($result[1]==='communities' && community_context($pdo,$otherId,100)===null,'leader can permanently delete an active community');
    group_check(!is_file($root.'/'.$sharedFile),'shared image is removed once its final community reference is deleted');
} finally {
    foreach($deleteTestFiles as $file) delete_upload($file);
}

echo "All community regression checks passed.\n";
