<?php
declare(strict_types=1);

/** Show actionable connection errors without exposing credentials or raw exception text. */
function database_connection_issue(Throwable $error): array
{
    if ($error->getMessage() === 'could not find driver') {
        return [
            'code' => 'PDO_MYSQL',
            'title' => 'Ekstensi MySQL untuk PHP belum aktif',
            'message' => 'PHP yang menjalankan website belum memiliki driver pdo_mysql.',
            'action' => 'Aktifkan extension=pdo_mysql pada php.ini yang digunakan Apache, lalu restart Apache melalui XAMPP.',
        ];
    }
    $code = $error instanceof PDOException ? (int) ($error->errorInfo[1] ?? $error->getCode()) : 0;
    return match ($code) {
        1049 => [
            'code' => '1049',
            'title' => 'Nama database belum cocok',
            'message' => 'MySQL dapat dihubungi, tetapi database yang diminta project tidak ditemukan.',
            'action' => 'Cocokkan nama database di config/database.php (bawaan: motora) dengan nama yang terlihat di phpMyAdmin. Periksa juga MOTORA_DB_NAME bila memakai variabel lingkungan.',
        ],
        1045 => [
            'code' => '1045',
            'title' => 'Akun koneksi MySQL ditolak',
            'message' => 'Username atau password koneksi project tidak diterima MySQL.',
            'action' => 'Sesuaikan username dan password di config/database.php dengan MySQL perangkat ini. Periksa MOTORA_DB_USER dan MOTORA_DB_PASS bila digunakan. Keberhasilan membuka phpMyAdmin tidak memastikan akun koneksi project sama.',
        ],
        1044 => [
            'code' => '1044',
            'title' => 'Akun MySQL belum memiliki akses',
            'message' => 'Akun koneksi project tidak diizinkan membuka database yang dipilih.',
            'action' => 'Gunakan akun MySQL yang memiliki akses ke database project dan cocokkan konfigurasinya di config/database.php.',
        ],
        2002, 2003 => [
            'code' => (string) $code,
            'title' => 'Layanan MySQL belum dapat dihubungi',
            'message' => 'Koneksi project ke server MySQL belum berhasil.',
            'action' => 'Pastikan MySQL aktif di XAMPP perangkat ini. Cocokkan host dan port MySQL dengan config/database.php; port bawaan project adalah 3306.',
        ],
        default => [
            'code' => 'DB_CONNECTION',
            'title' => 'Koneksi database perlu diperiksa',
            'message' => 'Project belum berhasil terhubung ke MySQL.',
            'action' => 'Periksa konfigurasi koneksi dan log error Apache pada perangkat yang menjalankan project. Kirim kode pada halaman ini serta teks error terkait dari log; sembunyikan password bila tertulis.',
        ],
    };
}
