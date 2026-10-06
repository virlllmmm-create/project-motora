<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']), 'samesite' => 'Lax']);
    session_start();
}
require_once __DIR__ . '/functions.php';

try {
    db();
} catch (Throwable $e) {
    http_response_code(503);
    require_once __DIR__ . '/database-error.php';
    $issue = database_connection_issue($e);
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Koneksi database MOTORA</title><main style="font:16px/1.7 system-ui;max-width:680px;margin:8vh auto;padding:24px">';
    echo '<p>MOTORA · Kode pemeriksaan: <b>' . e($issue['code']) . '</b></p><h1>' . e($issue['title']) . '</h1><p>' . e($issue['message']) . '</p><p>' . e($issue['action']) . '</p>';
    echo '<p>Setelah pengaturan selesai, muat ulang halaman. Petunjuk pemindahan project tersedia pada <code>database/INSTALL.md</code>.</p></main></html>';
    exit;
}
