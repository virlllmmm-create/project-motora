# Menjalankan MOTORA di perangkat lain

## Project disalin ke komputer baru

1. Gunakan XAMPP dengan PHP 8.2 dan MariaDB 10.4 atau versi kompatibel. File migration memakai sintaks MariaDB.
2. Salin seluruh folder project ke folder `htdocs` XAMPP. Pastikan `database/migration.sql` dan `database/community-chat.sql` ikut disalin.
3. Aktifkan Apache dan MySQL dari XAMPP Control Panel.
4. Buka phpMyAdmin di komputer baru, buat database `motora`, lalu klik database tersebut.
5. Pilih tab **Import**, pilih file `migration.sql` dari folder `database` project, dan jalankan. File ini sekarang juga menyiapkan tabel `user` bila database masih kosong.
6. Setelah berhasil, import `community-chat.sql` ke database yang sama.
7. Buka project melalui alamat `http://localhost/` sesuai lokasi folder di `htdocs`. Jangan membuka `index.php` langsung dari File Explorer.

Untuk struktur folder `htdocs/project/project pak qibar`, alamatnya adalah:

`http://localhost/project/project%20pak%20qibar/`

Koneksi bawaan: host `127.0.0.1`, database `motora`, pengguna `root`, password kosong. Jika berbeda, sesuaikan variabel lingkungan `MOTORA_DB_HOST`, `MOTORA_DB_NAME`, `MOTORA_DB_USER`, dan `MOTORA_DB_PASS` yang dibaca oleh `config/database.php`, atau nilai bawaan pada file tersebut.

Import migration membuat struktur, bukan memindahkan akun dan isi database lama. Untuk membawa data lama, export database lengkap melalui phpMyAdmin pada komputer asal, import hasilnya pada komputer baru, dan salin folder `uploads`. Gunakan kedua migration di atas bila skema hasil import belum diperbarui. Jangan menggunakan backup audit awal sebagai database aplikasi saat ini.

## Hanya membuka website dari HP atau komputer lain

Tidak perlu menyalin project atau mengimpor database di perangkat pengunjung. Pastikan Apache dan MySQL aktif pada komputer server dan kedua perangkat berada di jaringan yang sama. Gunakan IP LAN komputer server, bukan `localhost` atau `127.0.0.1` pada perangkat pengunjung.

Contoh format alamat (ganti IP dengan IP komputer server):

`http://IP-KOMPUTER-SERVER/project/project%20pak%20qibar/`

Jika memakai server pengembangan PHP, alamat yang terikat pada `127.0.0.1:8080` hanya dapat diakses dari komputer itu sendiri. Untuk akses jaringan, gunakan Apache XAMPP yang dikonfigurasi menerima koneksi LAN. Bila koneksi terhalang, periksa konfigurasi Apache dan izin firewall untuk jaringan privat.

## Jika tertulis file tidak ditemukan

- **File migration tidak ditemukan saat import:** pilih file dari folder `database` project yang benar. Migration diimpor di phpMyAdmin, bukan dijadikan URL halaman aplikasi.
- **Tabel `user` tidak ditemukan saat migration:** gunakan `migration.sql` terbaru yang menyertakan pembuatan tabel awal.
- **Halaman MOTORA belum terhubung ke database:** periksa MySQL aktif, nama database, dan akun koneksi. Pesan ini tidak menyatakan bahwa file SQL hilang.
- **404 Not Found:** cocokkan URL dengan lokasi project di dalam `htdocs`.

Jika masih gagal, catat teks error lengkap dan langkah yang memunculkannya agar penyebabnya dapat dibedakan.
