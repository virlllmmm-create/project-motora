<?php
declare(strict_types=1);

// Run migrations against a disposable, randomly named database, never live tables.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../config/database.php';
$pdo = db();
$schema = 'motora_install_test_' . bin2hex(random_bytes(8));
$created = false;
try {
    $pdo->exec("CREATE DATABASE `{$schema}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $pdo->exec("USE `{$schema}`");
    $base = file_get_contents(__DIR__ . '/../database/migration.sql');
    $chat = file_get_contents(__DIR__ . '/../database/community-chat.sql');
    if ($base === false || $chat === false) {
        throw new RuntimeException('Migration files are missing.');
    }
    $pdo->exec($base);
    $pdo->exec($chat);
    $pdo->exec("INSERT INTO `user` (nama, username, email, password_hash, role) VALUES ('Install fixture', 'install_fixture', 'install@example.test', 'not-a-login-password', 'rider')");
    $pdo->exec($base);
    $pdo->exec($chat);
    if ((int) $pdo->query("SELECT COUNT(*) FROM `user` WHERE username='install_fixture'")->fetchColumn() !== 1) {
        throw new RuntimeException('Repeated migrations did not preserve the existing account.');
    }
    $pdo->query('SELECT delivered_at FROM messages LIMIT 0');
    $pdo->query('SELECT invite_token, closed_at FROM communities LIMIT 0');
    $pdo->query('SELECT role, last_read_message_id FROM community_members LIMIT 0');
    $pdo->query('SELECT id FROM community_messages LIMIT 0');
    echo "PASS: fresh database installs both migrations; repeated imports preserve existing accounts.\n";
} finally {
    if ($created && preg_match('/\Amotora_install_test_[a-f0-9]{16}\z/', $schema)) {
        $pdo->exec("DROP DATABASE `{$schema}`");
    }
}
