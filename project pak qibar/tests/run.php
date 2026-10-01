<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/functions.php';
require __DIR__ . '/../includes/actions.php';

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo "PASS: {$message}\n";
}

$pdo = db();
$pdo->exec('CREATE TEMPORARY TABLE `user` (
    id_user INT PRIMARY KEY, nama VARCHAR(100) NOT NULL, username VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL, role VARCHAR(30) NOT NULL DEFAULT \'rider\',
    city VARCHAR(100) NULL, region VARCHAR(100) NULL, bio TEXT NULL,
    profile_photo VARCHAR(255) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    password_hash VARCHAR(255) NOT NULL DEFAULT \'\'
)');
$pdo->exec('CREATE TEMPORARY TABLE friend_requests (
    id INT AUTO_INCREMENT PRIMARY KEY, sender_id INT NOT NULL, receiver_id INT NOT NULL,
    status VARCHAR(20) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_friend_direction(sender_id,receiver_id)
)');
$pdo->exec('CREATE TEMPORARY TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL, used_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)');
$pdo->exec('CREATE TEMPORARY TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY, conversation_id INT NOT NULL, sender_id INT NOT NULL,
    body TEXT NOT NULL, read_at DATETIME NULL, delivered_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)');
$pdo->exec('CREATE TEMPORARY TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY, recipient_id INT NOT NULL, actor_id INT NULL,
    type VARCHAR(40) NOT NULL, entity_id INT NULL, body VARCHAR(255) NOT NULL,
    read_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)');
$pdo->exec('CREATE TEMPORARY TABLE communities (
    id INT PRIMARY KEY, owner_id INT NOT NULL, is_private TINYINT NOT NULL DEFAULT 0
)');
$pdo->exec('CREATE TEMPORARY TABLE conversations (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)');
$pdo->exec('CREATE TEMPORARY TABLE motorcycles (
    id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, brand VARCHAR(80) NOT NULL,
    model VARCHAR(100) NOT NULL, type VARCHAR(80) NOT NULL DEFAULT \'\', year SMALLINT NULL,
    color VARCHAR(80) NULL, plate_number VARCHAR(20) NULL, description TEXT NULL,
    main_photo VARCHAR(255) NULL, is_primary TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)');
$pdo->exec('CREATE TEMPORARY TABLE conversation_members (
    conversation_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(conversation_id,user_id)
)');
$pdo->exec('CREATE TEMPORARY TABLE community_members (
    community_id INT NOT NULL, user_id INT NOT NULL, status VARCHAR(20) NOT NULL,
    PRIMARY KEY (community_id,user_id)
)');

$pdo->exec('CREATE TEMPORARY TABLE motorcycle_photos (
    id INT AUTO_INCREMENT PRIMARY KEY, motorcycle_id INT NOT NULL, path VARCHAR(255) NOT NULL
)');
$pdo->exec('CREATE TEMPORARY TABLE user_settings (
    user_id INT PRIMARY KEY, profile_visibility VARCHAR(20) NOT NULL DEFAULT \'public\',
    motorcycles_visibility VARCHAR(20) NOT NULL DEFAULT \'public\',
    gallery_visibility VARCHAR(20) NOT NULL DEFAULT \'public\',
    show_city TINYINT NOT NULL DEFAULT 1, is_searchable TINYINT NOT NULL DEFAULT 1,
    notify_friend_requests TINYINT NOT NULL DEFAULT 1, notify_community TINYINT NOT NULL DEFAULT 1,
    notify_messages TINYINT NOT NULL DEFAULT 1, notify_activity TINYINT NOT NULL DEFAULT 1
)');
$pdo->exec("INSERT INTO `user` (id_user,nama,username,email,password_hash) VALUES
    (100,'Viewer','viewer','viewer@example.test','old-hash'),
    (101,'Blocks viewer','blocked-viewer','blocked@example.test','old-hash'),
    (102,'Viewer blocked','viewer-blocked','blocked-by@example.test','old-hash'),
    (103,'Public owner','public-owner','public-owner@example.test','old-hash'),
    (104,'Another rider','another-rider','another@example.test','old-hash')");
$pdo->exec('INSERT INTO user_settings(user_id) VALUES (100),(101),(102),(103)');
$pdo->exec("UPDATE user_settings SET notify_messages=0 WHERE user_id=103");
$pdo->exec('INSERT INTO user_settings(user_id) VALUES (104)');
$pdo->exec("INSERT INTO communities(id,owner_id,is_private) VALUES (1,103,1),(2,103,0)");
$pdo->exec("INSERT INTO community_members(community_id,user_id,status) VALUES (1,100,'pending'),(1,102,'approved')");
$pdo->exec("INSERT INTO notifications(recipient_id,actor_id,type,body) VALUES (100,101,'friend_request','hidden blocked actor'),(100,102,'friend_request','hidden actor blocked viewer'),(100,103,'friend_request','visible actor'),(100,NULL,'activity','system notice')");
$pdo->exec("INSERT INTO motorcycles(id,user_id,brand,model,type,main_photo) VALUES (1,101,'Honda','CB','Sport','uploads/motor/private.jpg')");
$pdo->exec("UPDATE user_settings SET motorcycles_visibility='private', gallery_visibility='private',profile_visibility='public' WHERE user_id=101");

$_SESSION = ['user_id' => 100];
$pdo->exec("INSERT INTO friend_requests(sender_id,receiver_id,status) VALUES
    (100,101,'blocked'),(102,100,'blocked'),(100,100,'accepted'),(100,103,'accepted'),(100,104,'accepted'),(103,100,'pending')");
$visibleIds = array_map(
    'intval',
    $pdo->query('SELECT u.id_user FROM `user` u WHERE u.id_user<>100 AND ' . not_blocked_sql('u.id_user', 100) . ' ORDER BY u.id_user')->fetchAll(PDO::FETCH_COLUMN),
);
check($visibleIds === [103, 104], 'blocked users are excluded in either block direction');
check(!can_view(101, 'profile_visibility'), 'a blocked profile is not viewable');
check(!can_view(102, 'profile_visibility'), 'a profile that blocked the viewer is not viewable');
check(str_contains(community_access_sql('c', 'mine', 100), "mine.status='approved'"), 'private community access requires approved membership');
$publicMotorCount = $pdo->query("SELECT COUNT(*) FROM motorcycles m JOIN user_settings s ON s.user_id=m.user_id WHERE m.user_id=101 AND " . visibility_sql('s', 'motorcycles_visibility', 'm.user_id', 100))->fetchColumn();
check((int) $publicMotorCount === 0, 'private motorcycle details are not returned to non-friends');
$publicGalleryCount = $pdo->query("SELECT COUNT(m.main_photo) FROM motorcycles m JOIN user_settings s ON s.user_id=m.user_id WHERE m.user_id=101 AND " . visibility_sql('s', 'gallery_visibility', 'm.user_id', 100))->fetchColumn();
check((int) $publicGalleryCount === 0, 'private gallery images are not returned to non-friends');
$pdo->exec("UPDATE user_settings SET motorcycles_visibility='friends', gallery_visibility='friends' WHERE user_id=101");
check((int) $pdo->query("SELECT COUNT(*) FROM motorcycles m JOIN user_settings s ON s.user_id=m.user_id WHERE m.user_id=101 AND " . visibility_sql('s', 'motorcycles_visibility', 'm.user_id', 100))->fetchColumn() === 0, 'friends-only motorcycle details are not visible to a non-friend');
$pdo->exec("UPDATE friend_requests SET status='accepted' WHERE sender_id=100 AND receiver_id=101");
check((int) $pdo->query("SELECT COUNT(*) FROM motorcycles m JOIN user_settings s ON s.user_id=m.user_id WHERE m.user_id=101 AND " . visibility_sql('s', 'motorcycles_visibility', 'm.user_id', 100))->fetchColumn() === 1, 'friends-only motorcycle details are visible to an accepted friend');
$pdo->exec("UPDATE friend_requests SET status='blocked' WHERE sender_id=100 AND receiver_id=101");
check((int) $pdo->query("SELECT COUNT(*) FROM motorcycles m JOIN user_settings s ON s.user_id=m.user_id WHERE m.user_id=101 AND " . visibility_sql('s', 'motorcycles_visibility', 'm.user_id', 100))->fetchColumn() === 0, 'a block overrides accepted friendship and private field visibility');
$pdo->exec("UPDATE user_settings SET motorcycles_visibility='private', gallery_visibility='private',profile_visibility='public' WHERE user_id=101");
$communityVisibility = $pdo->query('SELECT c.id FROM communities c LEFT JOIN community_members mine ON mine.community_id=c.id AND mine.user_id=100 AND mine.status=\'approved\' WHERE ' . community_access_sql('c', 'mine', 100) . ' ORDER BY c.id')->fetchAll(PDO::FETCH_COLUMN);
check(array_map('intval', $communityVisibility) === [2], 'private community details are hidden from pending and non-members');
$pdo->exec("UPDATE friend_requests SET status='accepted' WHERE sender_id=100 AND receiver_id=103");
$pdo->exec("UPDATE communities SET owner_id=103,is_private=1 WHERE id=2");
$friendCommunityVisibility = $pdo->query('SELECT c.id FROM communities c LEFT JOIN community_members mine ON mine.community_id=c.id AND mine.user_id=100 AND mine.status=\'approved\' WHERE ' . community_access_sql('c', 'mine', 100) . ' ORDER BY c.id')->fetchAll(PDO::FETCH_COLUMN);
check(array_map('intval', $friendCommunityVisibility) === [], 'approved friendship with a private community owner is not community membership');
$pdo->exec("INSERT INTO community_members(community_id,user_id,status) VALUES(2,100,'approved')");
$memberCommunityVisibility = $pdo->query('SELECT c.id FROM communities c LEFT JOIN community_members mine ON mine.community_id=c.id AND mine.user_id=100 AND mine.status=\'approved\' WHERE ' . community_access_sql('c', 'mine', 100) . ' ORDER BY c.id')->fetchAll(PDO::FETCH_COLUMN);
check(array_map('intval', $memberCommunityVisibility) === [2], 'approved community members can view private community details');
check(pagination_state(0, 9, 999)['page'] === 1, 'empty result pagination clamps to its sole page');
check(pagination_state(1000000000, 9, PHP_INT_MAX)['offset'] < 1000000000, 'pagination clamps out-of-range pages and avoids huge offsets');
check(pagination_pages(2, 4) === [1, 2, 3, 4], 'pagination renders every page number for multi-page results');
$visibleFriendCount = $pdo->query("SELECT COUNT(*) FROM friend_requests f JOIN `user` u ON u.id_user=IF(f.sender_id=100,f.receiver_id,f.sender_id) LEFT JOIN user_settings s ON s.user_id=u.id_user WHERE (f.sender_id=100 OR f.receiver_id=100) AND f.status='accepted' AND u.id_user<>100 AND " . visibility_sql('s', 'profile_visibility', 'u.id_user', 100))->fetchColumn();
check((int) $visibleFriendCount === 2, 'friends result counts exclude self-relations and include only visible accepted peers');
$insertRider = $pdo->prepare('INSERT INTO `user` (id_user,nama,username,email,password_hash) VALUES (?,?,?,?,?)');
$insertSetting = $pdo->prepare('INSERT INTO user_settings(user_id) VALUES(?)');
$insertFriend = $pdo->prepare("INSERT INTO friend_requests(sender_id,receiver_id,status) VALUES(?,?,'accepted')");
for ($riderId = 200; $riderId < 224; $riderId++) {
    $insertRider->execute([$riderId, 'Pagination Rider ' . $riderId, 'pagination_rider_' . $riderId, 'pagination' . $riderId . '@example.test', 'test-hash']);
    $insertSetting->execute([$riderId]);
    $insertFriend->execute([100, $riderId]);
}
$_GET['p_friends'] = 1;
$uid = 100;
$pdo = db();
ob_start();
require __DIR__ . '/../pages/views/friends.php';
$firstFriendsPage = ob_get_clean();
check(str_contains($firstFriendsPage, '@pagination_rider_200') && !str_contains($firstFriendsPage, '@pagination_rider_223'), 'friends view returns only the first bounded page of a real multi-page list');
$_GET['p_friends'] = 2;
ob_start();
require __DIR__ . '/../pages/views/friends.php';
$secondFriendsPage = ob_get_clean();
check(str_contains($secondFriendsPage, '@pagination_rider_223') && !str_contains($secondFriendsPage, '@pagination_rider_200'), 'friends view renders a distinct later page for a real multi-page list');
unset($_GET['p_friends']);
$visibleNotificationCount = $pdo->query('SELECT COUNT(*) FROM notifications n WHERE n.recipient_id=100 AND ' . notification_visibility_sql(100, 'n'))->fetchColumn();
check((int) $visibleNotificationCount === 2, 'blocked actors are excluded from notification lists while system notifications remain visible');
$visibleUnreadNotificationCount = $pdo->query('SELECT COUNT(*) FROM notifications n WHERE n.recipient_id=100 AND n.read_at IS NULL AND ' . notification_visibility_sql(100, 'n'))->fetchColumn();
check((int) $visibleUnreadNotificationCount === 2, 'notification badge count excludes unread notices from blocked actors');
check(is_local_reset_request('127.0.0.1', 'localhost:8080', true), 'local reset links are allowed for explicitly enabled loopback requests');
check(is_local_reset_request('::1', '[::1]:8080', true), 'local reset links accept IPv6 loopback requests');
check(!is_local_reset_request('192.168.1.20', 'localhost', true), 'local reset links are denied to remote clients');
check(!is_local_reset_request('127.0.0.1', 'motora.example', true), 'local reset links require a loopback host');
check(!is_local_reset_request('127.0.0.1', 'localhost.evil.test', true), 'local reset links reject lookalike localhost hosts');
check(!is_local_reset_request('127.0.0.1', 'localhost@evil.test', true), 'local reset links reject userinfo-style host values');
check(!is_local_reset_request('127.0.0.1', 'localhost:8080', false), 'local reset links are disabled unless explicitly enabled');
$oldResetMode = getenv('MOTORA_LOCAL_RESET_LINKS');
$oldRemoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$oldHttpHost = $_SERVER['HTTP_HOST'] ?? null;
putenv('MOTORA_LOCAL_RESET_LINKS=1');
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost:8080';
check(local_reset_links_enabled(), 'local reset feature requires and honors explicit config from a loopback request');
$_SERVER['REMOTE_ADDR'] = '192.168.1.20';
check(!local_reset_links_enabled(), 'local reset feature denies remote requests even when config is enabled');
if ($oldResetMode === false) {
    putenv('MOTORA_LOCAL_RESET_LINKS');
} else {
    putenv('MOTORA_LOCAL_RESET_LINKS=' . $oldResetMode);
}
if ($oldRemoteAddress === null) {
    unset($_SERVER['REMOTE_ADDR']);
} else {
    $_SERVER['REMOTE_ADDR'] = $oldRemoteAddress;
}
if ($oldHttpHost === null) {
    unset($_SERVER['HTTP_HOST']);
} else {
    $_SERVER['HTTP_HOST'] = $oldHttpHost;
}

$resetTokens = [];
for ($i = 0; $i < 5; $i++) {
    $resetTokens[] = create_password_reset_token($pdo, 104);
}
check(count(array_unique($resetTokens)) === 5, 'reset requests receive distinct tokens while under the active-token limit');
check(create_password_reset_token($pdo, 104) === null, 'repeated reset requests are capped without issuing more active tokens');
$activeResetCount = $pdo->prepare('SELECT COUNT(*) FROM password_resets WHERE user_id=104 AND used_at IS NULL AND expires_at>NOW()');
$activeResetCount->execute();
check((int) $activeResetCount->fetchColumn() === 5, 'requesting another reset does not invalidate an already-issued token');
$pdo->prepare('UPDATE password_resets SET created_at=DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE user_id=104')->execute();
check(create_password_reset_token($pdo, 104) !== null, 'reset requests can resume after the account rate-limit window expires');

$token = str_repeat('a', 64);
$pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(100,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))')->execute([hash('sha256', $token)]);
$secondToken = str_repeat('b', 64);
$pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(100,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))')->execute([hash('sha256', $secondToken)]);
$newHash = password_hash('correct horse battery staple', PASSWORD_DEFAULT);
check(complete_password_reset($pdo, $token, $newHash), 'a valid reset token updates the password');
check(!complete_password_reset($pdo, $token, password_hash('another safe password', PASSWORD_DEFAULT)), 'a reset token cannot be consumed twice');
$firstConversation = find_or_create_direct_conversation($pdo, 100, 103);
$secondConversation = find_or_create_direct_conversation($pdo, 103, 100);
check($firstConversation === $secondConversation, 'a direct conversation is reused regardless of participant ordering');
$memberCount = $pdo->prepare('SELECT COUNT(*) FROM conversation_members WHERE conversation_id=?');
$memberCount->execute([$firstConversation]);
check((int) $memberCount->fetchColumn() === 2, 'new direct conversation contains exactly the two participants');
$pdo->prepare('INSERT INTO messages(conversation_id,sender_id,body) VALUES (?,?,?)')->execute([$firstConversation, 103, 'visible before block']);
$pdo->prepare("INSERT INTO notifications(recipient_id,actor_id,type,entity_id,body) VALUES(100,103,'message',?,'message waiting')")->execute([$firstConversation]);
$visibleMessages = conversation_messages_for_viewer($pdo, $firstConversation, 100);
check(is_array($visibleMessages) && count($visibleMessages) === 1 && $visibleMessages[0]['body'] === 'visible before block', 'conversation history is returned to an authorized participant');
$readCheck = $pdo->query('SELECT read_at FROM messages WHERE conversation_id=' . $firstConversation)->fetchColumn();
check($readCheck !== null, 'opening an authorized conversation marks incoming messages read');
check($pdo->query("SELECT read_at FROM notifications WHERE recipient_id=100 AND type='message' AND entity_id=" . $firstConversation)->fetchColumn() !== null, 'opening a conversation clears its unread notification');
$pdo->prepare("INSERT INTO notifications(recipient_id,actor_id,type,entity_id,body) VALUES(103,100,'message',?,'message notification to peer')")->execute([$firstConversation]);
check($pdo->query("SELECT read_at FROM notifications WHERE recipient_id=103 AND type='message' AND entity_id=" . $firstConversation)->fetchColumn() === null, 'opening a conversation does not clear the peer notification');
$secondConversation = find_or_create_direct_conversation($pdo, 100, 104);
$pdo->prepare("INSERT INTO messages(conversation_id,sender_id,body,delivered_at) VALUES(?,?,?,NOW())")->execute([$secondConversation, 104, 'already delivered']);
mark_user_conversations_delivered($pdo, 100);
check((int) $pdo->query('SELECT COUNT(*) FROM messages WHERE conversation_id=' . $secondConversation)->fetchColumn() === 1, 'delivery polling leaves already-delivered conversations untouched');
$pdo->exec("UPDATE friend_requests SET status='accepted' WHERE sender_id=100 AND receiver_id=103");
$pdo->exec("UPDATE friend_requests SET status='accepted' WHERE sender_id=100 AND receiver_id=101");
$pdo->exec("INSERT INTO community_members(community_id,user_id,status) VALUES (2,101,'approved')");
check((int) $pdo->query('SELECT COUNT(*) FROM community_members cm JOIN user_settings s ON s.user_id=cm.user_id WHERE cm.community_id=2 AND cm.user_id<>100 AND ' . member_access_sql('cm', 's', 100))->fetchColumn() === 1, 'accepted friends can see each other in an approved community');
$pdo->exec("UPDATE friend_requests SET status='blocked' WHERE sender_id=100 AND receiver_id=101");
check((int) $pdo->query('SELECT COUNT(*) FROM community_members cm JOIN user_settings s ON s.user_id=cm.user_id WHERE cm.community_id=2 AND cm.user_id<>100 AND ' . member_access_sql('cm', 's', 100))->fetchColumn() === 0, 'a block overrides community member profile visibility');
block_user($pdo, 100, 103);
block_user($pdo, 100, 103);
check((string) $pdo->query('SELECT status FROM friend_requests WHERE sender_id=100 AND receiver_id=103')->fetchColumn() === 'blocked', 'blocking atomically replaces friendship and repeated blocking is safe');
check((int) $pdo->query('SELECT COUNT(*) FROM conversation_members WHERE conversation_id=' . $firstConversation . ' AND ' . unblocked_conversation_sql('conversation_members.conversation_id', 100))->fetchColumn() === 0, 'blocking a rider hides the existing conversation from polling and delivery contexts');
check(conversation_messages_for_viewer($pdo, $firstConversation, 100) === null, 'blocking a rider prevents reading the old conversation history');
$pdo->prepare('UPDATE messages SET delivered_at=NULL,read_at=NULL WHERE conversation_id=?')->execute([$firstConversation]);
$blockedDelivery = conversation_messages_for_viewer($pdo, $firstConversation, 100, false);
check($blockedDelivery === null, 'blocking prevents delivery/read receipts for pre-existing messages');
$readState=$pdo->query('SELECT delivered_at,read_at FROM messages WHERE conversation_id=' . $firstConversation)->fetch(PDO::FETCH_ASSOC);
check($readState['delivered_at']===null&&$readState['read_at']===null, 'blocked conversation delivery does not change message receipt state');
$pdo->exec('DELETE FROM friend_requests WHERE sender_id=100 AND receiver_id=103');
$pdo->exec("INSERT INTO friend_requests(sender_id,receiver_id,status) VALUES (100,103,'accepted')");
mark_user_conversations_delivered($pdo, 100);
$delivered = $pdo->query('SELECT delivered_at,read_at FROM messages WHERE conversation_id=' . $firstConversation)->fetch(PDO::FETCH_ASSOC);
check($delivered['delivered_at'] !== null && $delivered['read_at'] === null, 'delivery polling marks messages delivered without marking them read');
$secondConversation = find_or_create_direct_conversation($pdo, 100, 104);
$pdo->prepare("UPDATE friend_requests SET status='blocked' WHERE sender_id=100 AND receiver_id=104")->execute();
$pdo->prepare("INSERT INTO messages(conversation_id,sender_id,body,delivered_at) VALUES(?,?,?,NOW())")->execute([$secondConversation, 104, 'already delivered']);
mark_user_conversations_delivered($pdo, 100);
check($pdo->query('SELECT delivered_at FROM messages WHERE conversation_id=' . $secondConversation)->fetchColumn() !== null, 'delivery polling does not clear an existing delivered timestamp');
$q = $pdo->prepare('SELECT password_hash FROM `user` WHERE id_user=100');
$q->execute();
check(password_verify('correct horse battery staple', (string) $q->fetchColumn()), 'the password update is committed with token consumption');
check((int) $pdo->query('SELECT COUNT(*) FROM password_resets WHERE user_id=100 AND used_at IS NULL')->fetchColumn() === 0, 'successful password reset invalidates any other outstanding reset links');
check((int) $pdo->query('SELECT COUNT(*) FROM password_resets WHERE user_id=100 AND used_at IS NOT NULL')->fetchColumn() >= 2, 'consumed and invalidated reset links retain timestamps so request throttling remains effective');
$pdo->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(100,?,DATE_SUB(NOW(), INTERVAL 1 MINUTE))')->execute([hash('sha256', str_repeat('c', 64))]);
check(!complete_password_reset($pdo, str_repeat('c', 64), password_hash('another safe password', PASSWORD_DEFAULT)), 'an expired reset token cannot change the account password');
check((int) $pdo->query('SELECT COUNT(*) FROM password_resets WHERE user_id=100 AND used_at IS NULL')->fetchColumn() === 1, 'expired reset links do not become active or consume another token');

echo "All integration checks passed.\n";
