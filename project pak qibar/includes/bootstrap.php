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
    echo '<!doctype html><meta charset="utf-8"><title>MOTORA setup</title><main style="font:16px system-ui;max-width:680px;margin:10vh auto;padding:24px"><h1>MOTORA needs database setup</h1><p>Pastikan MySQL XAMPP aktif dan database <b>motora</b> tersedia. Schema audit sudah disimpan pada <code>database/migration.sql</code>; backup tabel existing ada di <code>database/backups/</code>.</p><p>Jalankan migration tersebut sekali pada database <code>motora</code>, lalu muat ulang halaman.</p></main>';
    exit;
}
