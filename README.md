# Panduan Instalasi Sistem Rekrutmen Karyawan PT SEKAR PUTRA DAERAH

## Persyaratan Sistem

1. Web Server: Apache (disarankan)
2. PHP: Versi 7.4 atau lebih tinggi
3. Database: MySQL 5.7 atau MariaDB 10.3+
4. PHP Extensions: PDO, MySQLi, GD (jika perlu manipulasi gambar)

## Langkah-langkah Instalasi

### 1. Persiapan Server

Pastikan server Anda memenuhi persyaratan sistem di atas. Untuk pemeriksaan cepat, buat file `info.php` dengan konten:


Upload ke server dan akses melalui browser. Pastikan versi PHP sesuai.

### 2. Download dan Extract File

Download semua file sistem dan extract ke folder root website Anda (contoh: `htdocs`, `www`, atau `public_html`).

### 3. Konfigurasi Database

#### a. Buat Database Baru

1. Login ke phpMyAdmin atau panel database lainnya
2. Buat database baru dengan nama: `rekrutmen_db`

#### b. Import Struktur Database

### 4. Konfigurasi Koneksi Database

Edit file `index.php` dan sesuaikan konfigurasi database pada bagian awal:

// Koneksi database
$host = 'localhost'; // Ganti dengan host database Anda
$dbname = 'rekrutmen_db'; // Nama database
$username = 'root'; // Username database
$password = ''; // Password database


Sesuaikan dengan kredensial database server Anda.

### 5. Konfigurasi Folder Upload

Pastikan folder `uploads/` ada dan memiliki izin yang tepat:

# Buat folder uploads
mkdir uploads

# Berikan izin yang diperlukan (untuk Linux/Unix)
chmod 755 uploads

### 6. Konfigurasi Web Server

#### Untuk Apache:

Pastikan mod_rewrite diaktifkan dan buat file `.htaccess` dengan konten:

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]

#### Untuk Nginx:

Tambahkan konfigurasi berikut ke server block:

```

### 7. Testing Instalasi

1. Akses website melalui browser
2. Anda seharusnya dapat melihat halaman home
3. Coba register user baru
4. Login dengan user admin:
   - Username: `admin`
   - Password: `admin123`

### 8. Troubleshooting

#### Jika terjadi error koneksi database:
- Pastikan informasi koneksi database benar
- Pastikan database sudah dibuat
- Pastikan ekstensi PMySQL diaktifkan di PHP

#### Jika upload file tidak bekerja:
- Pastikan folder `uploads` ada dan dapat ditulisi
- Periksa izin folder (chmod 755 atau 777 untuk testing)

#### Jika halaman tidak loading dengan benar:
- Pastikan mod_rewrite aktif (Apache)
- Periksa konfigurasi server

### 9. Keamanan

Setelah instalasi selesai, lakukan langkah-langkah keamanan:

1. Ganti password default admin
2. Pastikan folder `uploads` tidak dapat mengeksekusi script PHP
3. Batasi akses ke file sensitif

### 10. Backup Rutin

Selalu lakukan backup rutin untuk:
- Database (export SQL)
- Folder `uploads` yang berisi CV pelamar

## Dukungan

Jika mengalami kesulitan dalam instalasi, silakan hubungi tim developer atau konsultasikan dengan administrator server.


**Catatan**: Panduan ini mengasumsikan Anda memiliki akses dan pengetahuan dasar tentang administrasi server web. Jika tidak, disarankan untuk meminta bantuan dari administrator sistem.
