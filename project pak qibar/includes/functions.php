<?php
declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function url(string $page = 'dashboard', array $params = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}
function go(string $page = 'dashboard', array $params = []): never
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header('Location: ' . url($page, $params));
    exit();
}
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}
function csrf_check(): void
{
    $expected = (string) ($_SESSION['csrf'] ?? '');
    $provided = is_string($_POST['csrf'] ?? null) ? (string) $_POST['csrf'] : '';
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST' &&
        ($expected === '' || $provided === '' || !hash_equals($expected, $provided))
    ) {
        http_response_code(400);
        exit('Form kedaluwarsa. Muat ulang halaman dan coba lagi.');
    }
}
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}
function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}
function current_user(): ?array
{
    static $loaded = false,
        $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id) {
        $q = db()->prepare(
            'SELECT id_user,nama,username,email,role,city,region,bio,profile_photo,created_at FROM `user` WHERE id_user=?',
        );
        $q->execute([$id]);
        $user = $q->fetch() ?: null;
    }
    if (!$user) {
        unset($_SESSION['user_id']);
    }
    return $user;
}
function require_login(): array
{
    $u = current_user();
    if (!$u) {
        go('login');
    }
    return $u;
}
function ensure_settings(int $userId): void
{
    if ($userId < 1) {
        return;
    }
    $exists = db()->prepare('SELECT 1 FROM `user` WHERE id_user=?');
    $exists->execute([$userId]);
    if ($exists->fetchColumn()) {
        db()
            ->prepare('INSERT IGNORE INTO user_settings(user_id) VALUES(?)')
            ->execute([$userId]);
    }
}
function setting(int $userId, string $field, mixed $default = null): mixed
{
    $allowed = [
        'profile_visibility',
        'motorcycles_visibility',
        'gallery_visibility',
        'show_city',
        'is_searchable',
        'notify_friend_requests',
        'notify_community',
        'notify_messages',
        'notify_activity',
    ];
    if (!in_array($field, $allowed, true)) {
        return $default;
    }
    ensure_settings($userId);
    $q = db()->prepare("SELECT `$field` FROM user_settings WHERE user_id=?");
    $q->execute([$userId]);
    return $q->fetchColumn();
}
function are_friends(int $a, int $b): bool
{
    if ($a === $b) {
        return true;
    }
    $q = db()->prepare(
        "SELECT 1 FROM friend_requests WHERE status='accepted' AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) LIMIT 1",
    );
    $q->execute([$a, $b, $b, $a]);
    return (bool) $q->fetchColumn();
}
function is_blocked(int $a, int $b): bool
{
    if ($a < 1 || $b < 1 || $a === $b) {
        return false;
    }
    $q = db()->prepare(
        "SELECT 1 FROM friend_requests WHERE status='blocked' AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) LIMIT 1",
    );
    $q->execute([$a, $b, $b, $a]);
    return (bool) $q->fetchColumn();
}
function not_blocked_sql(string $userIdExpression, int $viewerId): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*\\.[A-Za-z_][A-Za-z0-9_]*$/D', $userIdExpression)) {
        throw new InvalidArgumentException('Invalid SQL user ID expression.');
    }
    if ($viewerId < 1) {
        return '1=1';
    }
    return "NOT EXISTS (SELECT 1 FROM friend_requests fb WHERE fb.status='blocked' AND ((fb.sender_id={$userIdExpression} AND fb.receiver_id={$viewerId}) OR (fb.sender_id={$viewerId} AND fb.receiver_id={$userIdExpression})))";
}
function community_access_sql(string $communityAlias, string $membershipAlias, int $viewerId): string
{
    foreach ([$communityAlias, $membershipAlias] as $identifier) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier)) {
            throw new InvalidArgumentException('Invalid SQL community identifier.');
        }
    }
    $access = "({$communityAlias}.is_private=0 OR {$communityAlias}.owner_id={$viewerId} OR {$membershipAlias}.status='approved')";
    return $viewerId > 0
        ? "{$access} AND NOT EXISTS (SELECT 1 FROM friend_requests cb WHERE cb.status='blocked' AND ((cb.sender_id={$communityAlias}.owner_id AND cb.receiver_id={$viewerId}) OR (cb.sender_id={$viewerId} AND cb.receiver_id={$communityAlias}.owner_id)))"
        : $access;
}
function can_view_community(int $communityId, int $viewerId): bool
{
    if ($communityId < 1 || $viewerId < 1) {
        return false;
    }
    $q = db()->prepare(
        'SELECT 1 FROM communities c LEFT JOIN community_members cm ON cm.community_id=c.id AND cm.user_id=? AND cm.status=\'approved\' WHERE c.id=? AND ' .
            community_access_sql('c', 'cm', $viewerId) .
            ' LIMIT 1',
    );
    $q->execute([$viewerId, $communityId]);
    return (bool) $q->fetchColumn();
}
function member_access_sql(string $membershipAlias, string $settingsAlias, int $viewerId): string
{
    foreach ([$membershipAlias, $settingsAlias] as $identifier) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier)) {
            throw new InvalidArgumentException('Invalid SQL membership identifier.');
        }
    }
    if ($viewerId < 1) {
        return '1=0';
    }
    return "({$membershipAlias}.user_id={$viewerId} OR ((" .
        visibility_sql($settingsAlias, 'profile_visibility', $membershipAlias . '.user_id', $viewerId) .
        ') AND ' . not_blocked_sql($membershipAlias . '.user_id', $viewerId) . '))';
}
function unblocked_conversation_sql(string $conversationIdExpression, int $viewerId): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*\\.(id|conversation_id)$/D', $conversationIdExpression)) {
        throw new InvalidArgumentException('Invalid SQL conversation identifier.');
    }
    if ($viewerId < 1) {
        return '1=1';
    }
    return "NOT EXISTS (SELECT 1 FROM conversation_members blocked_peer JOIN friend_requests blocked_link ON blocked_link.status='blocked' AND ((blocked_link.sender_id=blocked_peer.user_id AND blocked_link.receiver_id={$viewerId}) OR (blocked_link.sender_id={$viewerId} AND blocked_link.receiver_id=blocked_peer.user_id)) WHERE blocked_peer.conversation_id={$conversationIdExpression} AND blocked_peer.user_id<>{$viewerId})";
}
function visibility_sql(
    string $settingsAlias,
    string $visibilityField,
    string $ownerIdExpression,
    int $viewerId,
): string {
    foreach ([$settingsAlias, $visibilityField] as $identifier) {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $identifier)) {
            throw new InvalidArgumentException('Invalid SQL visibility identifier.');
        }
    }
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*\\.[A-Za-z_][A-Za-z0-9_]*$/D', $ownerIdExpression)) {
        throw new InvalidArgumentException('Invalid SQL owner ID expression.');
    }
    $visibility = "COALESCE({$settingsAlias}.{$visibilityField}, 'public')";
    $public = "{$visibility}='public'";
    if ($viewerId < 1) {
        return $public;
    }
    $allowed = "({$ownerIdExpression}={$viewerId} OR {$public} OR ({$visibility}='friends' AND EXISTS (SELECT 1 FROM friend_requests vf WHERE vf.status='accepted' AND ((vf.sender_id={$ownerIdExpression} AND vf.receiver_id={$viewerId}) OR (vf.sender_id={$viewerId} AND vf.receiver_id={$ownerIdExpression})))))";
    return "{$allowed} AND " . not_blocked_sql($ownerIdExpression, $viewerId);
}
function pagination_state(int $total, int $perPage, int $requestedPage): array
{
    $perPage = max(1, min(100, $perPage));
    $totalPages = max(1, (int) ceil(max(0, $total) / $perPage));
    $page = max(1, min($totalPages, $requestedPage));
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'offset' => ($page - 1) * $perPage,
    ];
}
function pagination_pages(int $currentPage, int $totalPages, int $maxVisible = 7): array
{
    $totalPages = max(1, $totalPages);
    $currentPage = max(1, min($totalPages, $currentPage));
    $maxVisible = max(1, min(21, $maxVisible));
    $start = max(1, $currentPage - intdiv($maxVisible - 1, 2));
    $end = min($totalPages, $start + $maxVisible - 1);
    $start = max(1, $end - $maxVisible + 1);

    return range($start, $end);
}
function lock_user_pair(PDO $pdo, int $userA, int $userB): void
{
    if ($userA < 1 || $userB < 1 || $userA === $userB) {
        throw new InvalidArgumentException('Participants pengguna tidak valid.');
    }
    $first = min($userA, $userB);
    $second = max($userA, $userB);
    $lock = $pdo->prepare('SELECT id_user FROM `user` WHERE id_user IN (?,?) ORDER BY id_user FOR UPDATE');
    $lock->execute([$first, $second]);
    if (count($lock->fetchAll(PDO::FETCH_COLUMN)) !== 2) {
        throw new RuntimeException('Salah satu pengguna tidak ditemukan.');
    }
}
function block_user(PDO $pdo, int $blockerId, int $blockedId): void
{
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        lock_user_pair($pdo, $blockerId, $blockedId);
        $existingBlock = $pdo->prepare(
            "SELECT sender_id FROM friend_requests WHERE status='blocked' AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) LIMIT 1 FOR UPDATE",
        );
        $existingBlock->execute([$blockerId, $blockedId, $blockedId, $blockerId]);
        if ($existingBlock->fetchColumn() !== false) {
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return;
        }
        $delete = $pdo->prepare(
            'DELETE FROM friend_requests WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)',
        );
        $delete->execute([$blockerId, $blockedId, $blockedId, $blockerId]);
        $insert = $pdo->prepare(
            "INSERT INTO friend_requests(sender_id,receiver_id,status) VALUES(?,?,'blocked') ON DUPLICATE KEY UPDATE status='blocked',updated_at=NOW()",
        );
        $insert->execute([$blockerId, $blockedId]);
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
function find_or_create_direct_conversation(PDO $pdo, int $userA, int $userB): int
{
    if ($userA < 1 || $userB < 1 || $userA === $userB) {
        throw new InvalidArgumentException('Participants percakapan tidak valid.');
    }
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }
    try {
        lock_user_pair($pdo, $userA, $userB);
        if (is_blocked($userA, $userB)) {
            throw new RuntimeException('Percakapan tidak dapat dimulai karena interaksi dibatasi.');
        }
        $first = min($userA, $userB);
        $second = max($userA, $userB);
        $lookup = $pdo->prepare(
            'SELECT c.id FROM conversations c JOIN conversation_members a ON a.conversation_id=c.id AND a.user_id=? JOIN conversation_members b ON b.conversation_id=c.id AND b.user_id=? WHERE (SELECT COUNT(*) FROM conversation_members cm WHERE cm.conversation_id=c.id)=2 LIMIT 1',
        );
        $lookup->execute([$first, $second]);
        $conversationId = (int) $lookup->fetchColumn();
        if (!$conversationId) {
            $pdo->exec('INSERT INTO conversations() VALUES()');
            $conversationId = (int) $pdo->lastInsertId();
            $members = $pdo->prepare('INSERT INTO conversation_members(conversation_id,user_id) VALUES(?,?),(?,?)');
            $members->execute([$conversationId, $first, $conversationId, $second]);
        }
        if ($ownsTransaction) {
            $pdo->commit();
        }
        return $conversationId;
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
function conversation_messages_for_viewer(PDO $pdo, int $conversationId, int $viewerId, bool $markRead = true): ?array
{
    if ($conversationId < 1 || $viewerId < 1) {
        return null;
    }

    $pdo->beginTransaction();
    try {
        $membersQuery = $pdo->prepare(
            'SELECT user_id FROM conversation_members WHERE conversation_id=? ORDER BY user_id FOR UPDATE',
        );
        $membersQuery->execute([$conversationId]);
        $members = array_map('intval', $membersQuery->fetchAll(PDO::FETCH_COLUMN));
        if (count($members) !== 2 || !in_array($viewerId, $members, true)) {
            $pdo->rollBack();
            return null;
        }

        $peerId = $members[0] === $viewerId ? $members[1] : $members[0];
        lock_user_pair($pdo, $viewerId, $peerId);
        if (is_blocked($viewerId, $peerId) || !can_view($peerId, 'profile_visibility')) {
            $pdo->rollBack();
            return null;
        }

        $pdo->prepare(
            'UPDATE messages SET delivered_at=COALESCE(delivered_at,NOW()) WHERE conversation_id=? AND sender_id<>?',
        )->execute([$conversationId, $viewerId]);

        if (!$markRead) {
            $pdo->commit();
            return [];
        }

        $pdo->prepare(
            'UPDATE messages SET read_at=COALESCE(read_at,NOW()) WHERE conversation_id=? AND sender_id<>?',
        )->execute([$conversationId, $viewerId]);
        $pdo->prepare(
            "UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE recipient_id=? AND type='message' AND entity_id=? AND read_at IS NULL",
        )->execute([$viewerId, $conversationId]);
        $messages = $pdo->prepare(
            'SELECT id,sender_id,body,delivered_at,read_at,created_at FROM messages WHERE conversation_id=? ORDER BY id DESC LIMIT 100',
        );
        $messages->execute([$conversationId]);
        $result = array_reverse($messages->fetchAll());
        foreach ($result as &$message) {
            $message['time_label'] = date('H:i · d M', strtotime((string) $message['created_at']));
        }
        unset($message);

        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
function mark_user_conversations_delivered(PDO $pdo, int $viewerId): void
{
    $conversations = $pdo->prepare(
        'SELECT DISTINCT member.conversation_id
         FROM conversation_members member
         JOIN messages incoming ON incoming.conversation_id=member.conversation_id
             AND incoming.sender_id<>? AND incoming.delivered_at IS NULL
         WHERE member.user_id=?',
    );
    $conversations->execute([$viewerId, $viewerId]);
    foreach ($conversations->fetchAll(PDO::FETCH_COLUMN) as $conversationId) {
        try {
            conversation_messages_for_viewer($pdo, (int) $conversationId, $viewerId, false);
        } catch (PDOException $e) {
            if ((string) $e->getCode() !== '40001') {
                throw $e;
            }
        }
    }
}
function is_local_reset_request(string $remoteAddress, string $httpHost, bool $enabled = false): bool
{
    if (!$enabled || !is_loopback_ip($remoteAddress)) {
        return false;
    }

    $host = parse_url('http://' . $httpHost, PHP_URL_HOST);
    if (!is_string($host) || str_contains($httpHost, '/') || str_contains($httpHost, '@')) {
        return false;
    }

    $host = strtolower(trim($host, '[]'));
    if ($host === 'localhost') {
        return true;
    }

    return filter_var($host, FILTER_VALIDATE_IP) !== false && is_loopback_ip($host);
}
function is_loopback_ip(string $address): bool
{
    if (!filter_var($address, FILTER_VALIDATE_IP)) {
        return false;
    }

    $packedAddress = inet_pton($address);
    return $packedAddress !== false &&
        ((strlen($packedAddress) === 4 && ord($packedAddress[0]) === 127) ||
            $packedAddress === str_repeat("\0", 15) . "\1");
}
function local_reset_links_enabled(): bool
{
    return getenv('MOTORA_LOCAL_RESET_LINKS') === '1' &&
        is_local_reset_request(
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            (string) ($_SERVER['HTTP_HOST'] ?? ''),
            true,
        );
}
function create_password_reset_token(PDO $pdo, int $userId): ?string
{
    if ($pdo->inTransaction()) {
        throw new LogicException('Password reset requests must own their transaction.');
    }
    if ($userId < 1) {
        return null;
    }

    $pdo->beginTransaction();
    try {
        $user = $pdo->prepare('SELECT id_user FROM `user` WHERE id_user=? FOR UPDATE');
        $user->execute([$userId]);
        if (!$user->fetchColumn()) {
            $pdo->rollBack();
            return null;
        }

        $pdo->prepare(
            'DELETE FROM password_resets WHERE user_id=? AND created_at<DATE_SUB(NOW(), INTERVAL 1 HOUR) AND expires_at<=NOW()',
        )->execute([$userId]);
        $recentRequests = $pdo->prepare(
            'SELECT COUNT(*) FROM password_resets WHERE user_id=? AND created_at>=DATE_SUB(NOW(), INTERVAL 1 HOUR)',
        );
        $recentRequests->execute([$userId]);
        if ((int) $recentRequests->fetchColumn() >= 5) {
            $pdo->commit();
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $insert = $pdo->prepare(
            'INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))',
        );
        $insert->execute([$userId, hash('sha256', $token)]);
        $pdo->commit();
        return $token;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
function complete_password_reset(PDO $pdo, string $token, string $passwordHash): bool
{
    if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
        return false;
    }

    $tokenHash = hash('sha256', $token);
    $lookup = $pdo->prepare(
        'SELECT user_id FROM password_resets WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1',
    );
    $lookup->execute([$tokenHash]);
    $userId = (int) $lookup->fetchColumn();
    if ($userId < 1) {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $user = $pdo->prepare('SELECT id_user FROM `user` WHERE id_user=? FOR UPDATE');
        $user->execute([$userId]);
        if (!$user->fetchColumn()) {
            $pdo->rollBack();
            return false;
        }

        $reset = $pdo->prepare(
            'SELECT id FROM password_resets WHERE user_id=? AND token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1 FOR UPDATE',
        );
        $reset->execute([$userId, $tokenHash]);
        $resetId = (int) $reset->fetchColumn();
        if ($resetId < 1) {
            $pdo->rollBack();
            return false;
        }

        $updatePassword = $pdo->prepare('UPDATE `user` SET password_hash=? WHERE id_user=?');
        $updatePassword->execute([$passwordHash, $userId]);
        if ($updatePassword->rowCount() !== 1) {
            throw new RuntimeException('Password reset did not update exactly one account.');
        }

        $consume = $pdo->prepare(
            'UPDATE password_resets SET used_at=NOW() WHERE id=? AND user_id=? AND token_hash=? AND used_at IS NULL AND expires_at>NOW()',
        );
        $consume->execute([$resetId, $userId, $tokenHash]);
        if ($consume->rowCount() !== 1) {
            throw new RuntimeException('Password reset token was not consumed exactly once.');
        }

        $deleteOtherResets = $pdo->prepare('UPDATE password_resets SET used_at=NOW() WHERE user_id=? AND id<>? AND used_at IS NULL');
        $deleteOtherResets->execute([$userId, $resetId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
function can_view(int $ownerId, string $settingName): bool
{
    $allowedFields = ['profile_visibility', 'motorcycles_visibility', 'gallery_visibility'];
    if ($ownerId < 1 || !in_array($settingName, $allowedFields, true)) {
        return false;
    }
    $viewer = (int) (current_user()['id_user'] ?? 0);
    if ($viewer < 1) {
        return false;
    }
    $exists = db()->prepare('SELECT 1 FROM `user` WHERE id_user=?');
    $exists->execute([$ownerId]);
    if (!$exists->fetchColumn()) {
        return false;
    }
    if ($ownerId === $viewer) {
        return true;
    }
    if (is_blocked($viewer, $ownerId)) {
        return false;
    }
    $visibility = (string) setting($ownerId, $settingName, 'public');
    return in_array($visibility, ['public', 'friends', 'private'], true) &&
        ($visibility === 'public' || ($visibility === 'friends' && are_friends($viewer, $ownerId)));
}
function notification_visibility_sql(int $viewerId, string $notificationAlias = 'n'): string
{
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $notificationAlias)) {
        throw new InvalidArgumentException('Invalid SQL notification identifier.');
    }
    if ($viewerId < 1) {
        return '1=0';
    }

    $actorIdExpression = $notificationAlias . '.actor_id';
    return "({$actorIdExpression} IS NULL OR {$actorIdExpression}={$viewerId} OR " .
        not_blocked_sql($actorIdExpression, $viewerId) . ')';
}
function notify(
    int $recipient,
    ?int $actor,
    string $type,
    ?int $entity,
    string $body,
    string $preference = 'notify_activity',
    ?PDO $pdo = null,
): void {
    if ($recipient === $actor || (int) setting($recipient, $preference, 1) !== 1) {
        return;
    }
    ($pdo ?? db())
        ->prepare(
            'INSERT INTO notifications(recipient_id,actor_id,type,entity_id,body) VALUES(?,?,?,?,?)',
        )
        ->execute([$recipient, $actor, $type, $entity, $body]);
}
function upload_image(string $field, string $folder): ?string
{
    $allowedFolders = ['profile', 'motor', 'community', 'group'];
    if (!in_array($folder, $allowedFolders, true)) {
        throw new InvalidArgumentException('Folder unggahan tidak valid.');
    }
    if (!isset($_FILES[$field])) {
        return null;
    }
    $file = $_FILES[$field];
    if (!is_array($file) || !isset($file['error'], $file['size'], $file['tmp_name']) || is_array($file['error']) || is_array($file['size']) || is_array($file['tmp_name'])) {
        throw new RuntimeException('Data unggahan tidak valid.');
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Foto gagal diunggah atau ukurannya lebih dari 5 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Sumber unggahan tidak valid.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $imageInfo = @getimagesize($file['tmp_name']);
    $expectedImageTypes = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG];
    if (defined('IMAGETYPE_WEBP')) {
        $expectedImageTypes['image/webp'] = constant('IMAGETYPE_WEBP');
    }
    if (!isset($extensions[$mime]) || $imageInfo === false || ($imageInfo[2] ?? null) !== ($expectedImageTypes[$mime] ?? null)) {
        throw new RuntimeException('Gunakan gambar JPG, PNG, atau WEBP yang valid.');
    }
    $relativeDir = 'uploads/' . $folder;
    $absoluteDir = dirname(__DIR__) . '/' . $relativeDir;
    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
        throw new RuntimeException('Folder upload tidak dapat dibuat.');
    }
    $name = bin2hex(random_bytes(18)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $absoluteDir . '/' . $name)) {
        throw new RuntimeException('Foto tidak dapat disimpan.');
    }
    return $relativeDir . '/' . $name;
}
function tracked_upload_image(array &$uploadedFiles, string $field, string $folder): ?string
{
    $path = upload_image($field, $folder);
    if ($path !== null) {
        $uploadedFiles[] = $path;
    }
    return $path;
}
function mark_upload_persisted(array &$uploadedFiles, ?string $path): void
{
    if ($path === null) {
        return;
    }
    $index = array_search($path, $uploadedFiles, true);
    if ($index !== false) {
        unset($uploadedFiles[$index]);
    }
}
function delete_upload_if_unreferenced(PDO $pdo, ?string $path): void
{
    if (!$path) {
        return;
    }
    $references = $pdo->prepare(
        'SELECT
            (SELECT COUNT(*) FROM `user` WHERE profile_photo=?) +
            (SELECT COUNT(*) FROM motorcycles WHERE main_photo=?) +
            (SELECT COUNT(*) FROM motorcycle_photos WHERE path=?) +
            (SELECT COUNT(*) FROM communities WHERE image=?) +
            (SELECT COUNT(*) FROM community_messages WHERE attachment=?)',
    );
    $references->execute([$path, $path, $path, $path, $path]);
    if ((int) $references->fetchColumn() === 0) {
        delete_upload($path);
    }
}
function delete_upload(?string $path): void
{
    if (!$path || !str_starts_with($path, 'uploads/')) {
        return;
    }
    $root = realpath(dirname(__DIR__) . '/uploads');
    $file = realpath(dirname(__DIR__) . '/' . $path);
    $relative = substr($path, strlen('uploads/'));
    $parts = explode('/', str_replace('\\', '/', $relative));
    if (
        !$root ||
        !$file ||
        count($parts) !== 2 ||
        !in_array($parts[0], ['profile', 'motor', 'community', 'group'], true) ||
        !preg_match('/^[a-f0-9]{36}\\.(jpg|png|webp)$/D', $parts[1]) ||
        !str_starts_with($file, $root . DIRECTORY_SEPARATOR) ||
        !is_file($file)
    ) {
        return;
    }
    @unlink($file);
}
function profile_link(int $id): string
{
    return url('profile', ['id' => $id]);
}
function avatar(?string $path, string $name = 'Rider'): string
{
    if ($path) {
        return '<img class="avatar" src="' . e($path) . '" alt="Foto profil ' . e($name) . '">';
    }
    return '<span class="avatar avatar-fallback">' .
        e(mb_strtoupper(mb_substr($name, 0, 1))) .
        '</span>';
}
function page_title(string $page): string
{
    return [
        'dashboard' => 'Ringkasan',
        'search' => 'Pencarian Rider',
        'communities' => 'Komunitas Motor',
        'community' => 'Chat Komunitas',
        'community_settings' => 'Pengaturan Komunitas',
        'garage' => 'Garasi Saya',
        'motorcycle' => 'Detail Motor',
        'profile' => 'Profil Rider',
        'friends' => 'Teman Rider',
        'messages' => 'Obrolan',
        'notifications' => 'Notifikasi',
        'settings' => 'Pengaturan',
        'login' => 'Masuk',
        'register' => 'Daftar',
        'forgot' => 'Lupa Sandi',
        'reset' => 'Atur Ulang Sandi',
    ][$page] ?? 'MOTORA';
}

require_once __DIR__ . '/community.php';
