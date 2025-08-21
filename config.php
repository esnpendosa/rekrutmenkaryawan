<?php
// Konfigurasi database
$host = 'localhost';
$dbname = 'rekrutmen_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Buat admin user jika belum ada (password: admin123)
$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
if ($stmt->fetchColumn() == 0) {
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, password, nama_lengkap, email) VALUES 
                ('admin', '$hashedPassword', 'Administrator', 'admin@example.com')");
}

// Buat contoh lowongan jika belum ada
$stmt = $pdo->query("SELECT COUNT(*) FROM lowongan");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("INSERT INTO lowongan (judul, deskripsi, departemen) VALUES 
                ('Web Developer', 'Kami mencari web developer berpengalaman dengan pengetahuan PHP, JavaScript, dan framework modern.', 'IT'),
                ('Marketing Specialist', 'Dicari marketing specialist dengan pengalaman di digital marketing dan campaign management.', 'Marketing'),
                ('Customer Service', 'Kami membutuhkan customer service yang ramah dan komunikatif untuk melayani pelanggan.', 'Customer Service')");
}
?>