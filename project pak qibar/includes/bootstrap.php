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
    echo '<!doctype html><html lang="id"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pengaturan database MOTORA</title><main style="font:16px/1.7 system-ui;max-width:680px;margin:8vh auto;padding:24px"><h1>MOTORA belum terhubung ke database</h1><p>Pesan ini berarti koneksi database belum berhasil, bukan bahwa file migration hilang.</p><ol><li>Aktifkan Apache dan MySQL pada XAMPP di komputer yang menjalankan project.</li><li>Untuk instalasi baru, buka phpMyAdmin dan buat database <b>motora</b>, lalu pilih database tersebut.</li><li>Di tab <b>Import</b>, pilih file <code>database/migration.sql</code> dari folder project. Setelah selesai, import <code>database/community-chat.sql</code>.</li><li>Jika memakai database atau akun MySQL lain, sesuaikan konfigurasi koneksi di <code>config/database.php</code>.</li></ol><p>File SQL diimpor melalui phpMyAdmin, bukan dibuka sebagai halaman website. Pastikan seluruh folder project, termasuk <code>database</code>, ikut disalin.</p><p>Jika hanya mengakses dari HP atau komputer lain di jaringan yang sama, gunakan alamat IP komputer server. Database cukup berada di server tersebut.</p><p>Petunjuk lengkap: <code>database/INSTALL.md</code>. Setelah pengaturan selesai, muat ulang halaman.</p></main></html>';
    exit;
}
