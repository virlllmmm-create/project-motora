# Audit MOTORA awal

Audit dilakukan secara read-only sebelum perubahan apa pun.

## Project dan runtime

- Folder `C:\xampp\htdocs\project pak qibar` awalnya kosong dan bukan repository Git.
- XAMPP menyediakan PHP 8.2.12 dan MariaDB 10.4.32.
- Ekstensi PDO, `pdo_mysql`, `mysqli`, dan `fileinfo` tersedia.
- Database `motora` sudah ada; database baru tidak dibuat.

## Skema database sebelum migration

| Tabel | Kolom | Primary key | Index lain | Foreign key | Data |
|---|---|---|---|---|---:|
| `user` | `id_user` int auto increment, `nama` varchar(100), `email` varchar(150), `password_hash` varchar(255), `role` varchar(20), `created_at` timestamp | `id_user` | unique `email` | tidak ada | 0 baris |

Tabel menggunakan InnoDB, `utf8mb4_general_ci`. Tidak ditemukan tabel profil, garasi, komunitas, pertemanan, percakapan, atau pengaturan. Karena data akun awal kosong, tidak ada nilai existing yang perlu dipetakan. Nama tabel dan kolom akun yang sudah ada dipertahankan.

## Perubahan yang diterapkan

Migration menambahkan kolom username dan profil ke `user`, serta tabel pengaturan/privasi, motor dan foto, komunitas dan anggota, permintaan teman, percakapan dan pesan, notifikasi, dan reset password. Foreign key dibuat untuk relasi akun serta relasi anak. Tidak ada tabel yang dihapus atau diganti nama, dan tidak ada akun atau data uji tersisa.

Dump tabel `user` sebelum perubahan: `database/backups/motora-user-before-motora.sql`.
