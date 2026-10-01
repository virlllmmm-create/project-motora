<?php
function handle_ajax_request(string $type): void
{
    if (in_array($type, ['community_chat', 'community_call', 'community_attachment'], true)) {
        community_ajax($type);
    }
    if ($type === 'messages') {
        $viewer = require_login();
        $conversationId = (int) ($_GET['conversation'] ?? 0);
        $userId = (int) $viewer['id_user'];

        $messages = conversation_messages_for_viewer(db(), $conversationId, $userId);
        if ($messages === null) {
            http_response_code(403);
            exit;
        }

        $messages = array_map(
            static function (array $message): array {
                $message['created_at'] = date('H:i', strtotime((string) $message['created_at']));
                return $message;
            },
            $messages,
        );

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            array_map(
                static function (array $message) use ($userId): array {
                    $status = !empty($message['read_at'])
                        ? 'read'
                        : (!empty($message['delivered_at']) ? 'delivered' : 'sent');

                    return [
                        'id' => (int) $message['id'],
                        'body' => $message['body'],
                        'created_at' => $message['created_at'],
                        'mine' => (int) $message['sender_id'] === $userId,
                        'status' => $status,
                    ];
                },
                $messages,
            ),
            JSON_UNESCAPED_UNICODE,
        );
        exit;
    }

    if ($type !== 'deliveries') {
        return;
    }

    $viewer = require_login();
    $userId = (int) $viewer['id_user'];
    mark_user_conversations_delivered(db(), $userId);

    $query = db()->prepare(
        'SELECT messages.conversation_id, COUNT(*) AS unread
        FROM messages
        WHERE messages.sender_id <> ? AND messages.read_at IS NULL
            AND conversation_id IN (
                SELECT conversation_id FROM conversation_members WHERE user_id = ?
            ) AND ' . unblocked_conversation_sql('messages.conversation_id', $userId) . '
        GROUP BY messages.conversation_id',
    );
    $query->execute([$userId, $userId]);
    $unread = [];
    foreach ($query->fetchAll() as $row) {
        $unread[(string) $row['conversation_id']] = (int) $row['unread'];
    }

    $query = db()->prepare(
        'SELECT COUNT(*) FROM notifications n WHERE n.recipient_id = ? AND n.read_at IS NULL AND ' . notification_visibility_sql($userId, 'n'),
    );
    $query->execute([$userId]);
    $notificationCount = (int) $query->fetchColumn();

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(
        ['unread' => $unread, 'notification_count' => $notificationCount],
        JSON_UNESCAPED_UNICODE,
    );
    exit;
}
