<?php
session_start();

// Koneksi database
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

// Buat tabel notifikasi jika belum ada
$pdo->exec("CREATE TABLE IF NOT EXISTS notifikasi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pesan TEXT NOT NULL,
    dibaca TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Fungsi helper
function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit();
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// Fungsi untuk mengirim notifikasi
function kirimNotifikasi($user_id, $pesan, $pdo) {
    $stmt = $pdo->prepare("INSERT INTO notifikasi (user_id, pesan, dibaca) VALUES (?, ?, 0)");
    $stmt->execute([$user_id, $pesan]);
    return $stmt->rowCount() > 0;
}

// Proses form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'register':
                // Proses pendaftaran
                $username = trim($_POST['username']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $nama_lengkap = trim($_POST['nama_lengkap']);
                $email = trim($_POST['email']);
                $nik = trim($_POST['nik']);
                $kk = trim($_POST['kk']);
                $telepon = trim($_POST['telepon']);
                $alamat = trim($_POST['alamat']);
                
                // Validasi NIK dan KK harus 16 digit angka
                if (!preg_match('/^[0-9]{16}$/', $nik)) {
                    $_SESSION['error'] = "NIK harus terdiri dari 16 digit angka.";
                    break;
                }
                
                if (!preg_match('/^[0-9]{16}$/', $kk)) {
                    $_SESSION['error'] = "Nomor KK harus terdiri dari 16 digit angka.";
                    break;
                }
                
                // Cek apakah username, email atau NIK sudah digunakan
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ? OR nik = ?");
                $stmt->execute([$username, $email, $nik]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "Username, email atau NIK sudah digunakan.";
                    break;
                }
                
                $stmt = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, email, nik, kk, telepon, alamat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$username, $password, $nama_lengkap, $email, $nik, $kk, $telepon, $alamat])) {
                    $_SESSION['message'] = "Pendaftaran berhasil! Silakan login.";
                    redirect('index.php?view=login');
                } else {
                    $_SESSION['error'] = "Pendaftaran gagal. Silakan coba lagi.";
                }
                break;
                
            case 'login':
                // Proses login
                $username = trim($_POST['username']);
                $password = $_POST['password'];
                
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['is_admin'] = ($user['username'] === 'admin');
                    
                    redirect('index.php?view=dashboard');
                } else {
                    $_SESSION['error'] = "Username atau password salah!";
                }
                break;
                
            case 'apply_job':
                // Proses lamaran kerja
                if (!isLoggedIn()) {
                    $_SESSION['error'] = "Silakan login terlebih dahulu!";
                    redirect('index.php?view=login');
                }
                
                $user_id = $_SESSION['user_id'];
                $lowongan_id = $_POST['lowongan_id'];
                $surat_lamaran = trim($_POST['surat_lamaran']);
                
                // Cek apakah user sudah melamar di lowongan ini
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM lamaran WHERE user_id = ? AND lowongan_id = ?");
                $stmt->execute([$user_id, $lowongan_id]);
                if ($stmt->fetchColumn() > 0) {
                    $_SESSION['error'] = "Anda sudah melamar untuk lowongan ini.";
                    redirect('index.php?view=apply&id=' . $lowongan_id);
                }
                
                // Handle file upload
                $cv_path = null;
                if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    $max_size = 5 * 1024 * 1024; // 5MB
                    
                    if (!in_array($_FILES['cv']['type'], $allowed_types)) {
                        $_SESSION['error'] = "Format file tidak didukung. Hanya PDF, DOC, dan DOCX yang diizinkan.";
                        redirect('index.php?view=apply&id=' . $lowongan_id);
                    }
                    
                    if ($_FILES['cv']['size'] > $max_size) {
                        $_SESSION['error'] = "Ukuran file terlalu besar. Maksimal 5MB.";
                        redirect('index.php?view=apply&id=' . $lowongan_id);
                    }
                    
                    $upload_dir = 'uploads/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $file_ext = pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION);
                    $cv_path = $upload_dir . uniqid() . '.' . $file_ext;
                    
                    if (!move_uploaded_file($_FILES['cv']['tmp_name'], $cv_path)) {
                        $_SESSION['error'] = "Gagal mengupload CV!";
                        redirect('index.php?view=apply&id=' . $lowongan_id);
                    }
                } else {
                    $_SESSION['error'] = "Harap upload CV Anda.";
                    redirect('index.php?view=apply&id=' . $lowongan_id);
                }
                
                $stmt = $pdo->prepare("INSERT INTO lamaran (user_id, lowongan_id, cv_path, surat_lamaran) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$user_id, $lowongan_id, $cv_path, $surat_lamaran])) {
                    $_SESSION['message'] = "Lamaran berhasil dikirim!";
                    redirect('index.php?view=dashboard');
                } else {
                    $_SESSION['error'] = "Gagal mengirim lamaran!";
                    redirect('index.php?view=apply&id=' . $lowongan_id);
                }
                break;
                
            case 'update_status':
                // Admin update status lamaran
                if (!isAdmin()) {
                    $_SESSION['error'] = "Akses ditolak!";
                    redirect('index.php');
                }
                
                $lamaran_id = $_POST['lamaran_id'];
                $status = $_POST['status'];
                $catatan = !empty($_POST['catatan']) ? trim($_POST['catatan']) : '';
                
                // Ambil data lamaran untuk notifikasi
                $stmt = $pdo->prepare("SELECT l.*, u.nama_lengkap, u.id as user_id, j.judul 
                                      FROM lamaran l 
                                      JOIN users u ON l.user_id = u.id 
                                      JOIN lowongan j ON l.lowongan_id = j.id 
                                      WHERE l.id = ?");
                $stmt->execute([$lamaran_id]);
                $lamaran = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("UPDATE lamaran SET status = ? WHERE id = ?");
                if ($stmt->execute([$status, $lamaran_id])) {
                    // Kirim notifikasi ke user
                    $pesan = "Status lamaran Anda untuk posisi " . $lamaran['judul'] . " telah diubah menjadi: " . ucfirst($status);
                    if (!empty($catatan)) {
                        $pesan .= ". Catatan: " . $catatan;
                    }
                    
                    if (kirimNotifikasi($lamaran['user_id'], $pesan, $pdo)) {
                        $_SESSION['message'] = "Status lamaran berhasil diupdate dan notifikasi telah dikirim!";
                    } else {
                        $_SESSION['message'] = "Status lamaran berhasil diupdate!";
                    }
                } else {
                    $_SESSION['error'] = "Gagal mengupdate status!";
                }
                redirect('index.php?view=admin');
                break;
                
            case 'add_job':
                // Admin tambah lowongan baru
                if (!isAdmin()) {
                    $_SESSION['error'] = "Akses ditolak!";
                    redirect('index.php');
                }
                
                $judul = trim($_POST['judul']);
                $deskripsi = trim($_POST['deskripsi']);
                $departemen = trim($_POST['departemen']);
                
                $stmt = $pdo->prepare("INSERT INTO lowongan (judul, deskripsi, departemen) VALUES (?, ?, ?)");
                if ($stmt->execute([$judul, $deskripsi, $departemen])) {
                    $_SESSION['message'] = "Lowongan berhasil ditambahkan!";
                } else {
                    $_SESSION['error'] = "Gagal menambahkan lowongan!";
                }
                redirect('index.php?view=admin');
                break;
                
            case 'delete_job':
                // Admin hapus lowongan
                if (!isAdmin()) {
                    $_SESSION['error'] = "Akses ditolak!";
                    redirect('index.php');
                }
                
                $job_id = $_POST['job_id'];
                
                // Periksa apakah ada lamaran untuk lowongan ini
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM lamaran WHERE lowongan_id = ?");
                $stmt->execute([$job_id]);
                $application_count = $stmt->fetchColumn();
                
                if ($application_count > 0) {
                    $_SESSION['error'] = "Tidak dapat menghapus lowongan karena sudah ada $application_count lamaran!";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM lowongan WHERE id = ?");
                    if ($stmt->execute([$job_id])) {
                        $_SESSION['message'] = "Lowongan berhasil dihapus!";
                    } else {
                        $_SESSION['error'] = "Gagal menghapus lowongan!";
                    }
                }
                redirect('index.php?view=admin');
                break;
                
            case 'mark_notification_read':
                // Tandai notifikasi sebagai sudah dibaca
                if (isLoggedIn()) {
                    $notif_id = $_POST['notif_id'];
                    $stmt = $pdo->prepare("UPDATE notifikasi SET dibaca = 1 WHERE id = ? AND user_id = ?");
                    $stmt->execute([$notif_id, $_SESSION['user_id']]);
                    echo json_encode(['success' => true]);
                    exit();
                }
                break;

            case 'get_unread_count':
                // Ambil jumlah notifikasi yang belum dibaca
                if (isLoggedIn()) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND dibaca = 0");
                    $stmt->execute([$_SESSION['user_id']]);
                    $unread_count = $stmt->fetchColumn();
                    echo json_encode(['unread_count' => $unread_count]);
                    exit();
                }
                break;
        }
    }
}

// Tentukan view yang akan ditampilkan
$view = isset($_GET['view']) ? $_GET['view'] : 'home';

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('index.php');
}

// Ambil data lowongan untuk home
$lowongan = $pdo->query("SELECT * FROM lowongan ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);

// Ambil data untuk dashboard
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $lamaran_saya = $pdo->prepare("SELECT l.*, lo.judul as lowongan_judul, lo.departemen 
                                  FROM lamaran l 
                                  JOIN lowongan lo ON l.lowongan_id = lo.id 
                                  WHERE l.user_id = ? 
                                  ORDER BY l.created_at DESC");
    $lamaran_saya->execute([$user_id]);
    $lamaran_saya = $lamaran_saya->fetchAll(PDO::FETCH_ASSOC);
    
    // Hitung notifikasi belum dibaca
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifikasi WHERE user_id = ? AND dibaca = 0");
    $stmt->execute([$user_id]);
    $unread_notifications = $stmt->fetchColumn();
}

// Ambil data untuk admin
if (isAdmin() && $view == 'admin') {
    $semua_lamaran = $pdo->query("SELECT lam.*, us.nama_lengkap, us.email, lo.judul as lowongan_judul 
                                 FROM lamaran lam 
                                 JOIN users us ON lam.user_id = us.id 
                                 JOIN lowongan lo ON lam.lowongan_id = lo.id 
                                 ORDER BY lam.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    $semua_lowongan = $pdo->query("SELECT * FROM lowongan ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistik untuk dashboard admin
    $total_lamaran = $pdo->query("SELECT COUNT(*) FROM lamaran")->fetchColumn();
    $lamaran_diterima = $pdo->query("SELECT COUNT(*) FROM lamaran WHERE status = 'diterima'")->fetchColumn();
    $lamaran_ditolak = $pdo->query("SELECT COUNT(*) FROM lamaran WHERE status = 'ditolak'")->fetchColumn();
    $lamaran_menunggu = $pdo->query("SELECT COUNT(*) FROM lamaran WHERE status = 'menunggu'")->fetchColumn();
}

// AJAX request untuk notifikasi
if (isset($_GET['get_notifications']) && isLoggedIn()) {
    // Hanya ambil notifikasi yang belum dibaca
    $stmt = $pdo->prepare("SELECT * FROM notifikasi WHERE user_id = ? AND dibaca = 0 ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($notifications);
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Rekrutmen Karyawan - PT SEKAR PUTRA DAERAH</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1e40af',
                        secondary: '#3b82f6',
                        accent: '#f59e0b',
                        dark: '#1f2937',
                        light: '#f3f4f6',
                        success: '#10b981',
                        danger: '#ef4444',
                        warning: '#f59e0b',
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .nav-link {
            position: relative;
        }
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: #3b82f6;
            transition: width 0.3s;
        }
        .nav-link:hover::after {
            width: 100%;
        }
        .card-hover {
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .department-badge {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .tab-button.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }
        .file-upload-container {
            transition: all 0.3s ease;
        }
        .file-upload-container.dragover {
            border-color: #3b82f6;
            background-color: #f0f7ff;
        }
        .smooth-transition {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4 py-4">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <div class="bg-primary p-3 rounded-lg mr-3">
                        <i class="fas fa-briefcase text-white text-2xl"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-800">PT SEKAR PUTRA DAERAH</h1>
                        <p class="text-sm text-gray-600">Sistem Rekrutmen Karyawan</p>
                    </div>
                </div>
                
                <!-- Mobile Menu Button -->
                <button class="md:hidden text-gray-600 focus:outline-none" id="mobileMenuBtn">
                    <i class="fas fa-bars text-2xl"></i>
                </button>
                
                <!-- Desktop Navigation -->
                <nav class="hidden md:flex space-x-8 items-center">
                    <?php if (isLoggedIn()): ?>
                        <a href="index.php" class="nav-link text-gray-700 hover:text-primary font-medium"><i class="fas fa-home mr-2"></i>Home</a>
                        <a href="index.php?view=dashboard" class="nav-link text-gray-700 hover:text-primary font-medium"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
                        <?php if (isAdmin()): ?>
                            <a href="index.php?view=admin" class="nav-link text-gray-700 hover:text-primary font-medium"><i class="fas fa-cog mr-2"></i>Admin Panel</a>
                        <?php endif; ?>
                        
                        <!-- Notifikasi Bell -->
                        <div class="relative">
                            <button id="notificationBell" class="text-gray-700 hover:text-primary">
                                <i class="fas fa-bell text-xl"></i>
                                <?php if (isset($unread_notifications) && $unread_notifications > 0): ?>
                                <span class="notification-badge"><?= $unread_notifications ?></span>
                                <?php endif; ?>
                            </button>
                            <div id="notificationDropdown" class="absolute hidden right-0 mt-2 w-80 bg-white rounded-md shadow-lg py-2 z-50 border border-gray-200">
                                <div class="px-4 py-2 border-b">
                                    <h3 class="text-lg font-semibold">Notifikasi</h3>
                                </div>
                                <div class="max-h-60 overflow-y-auto" id="notificationList">
                                    <?php
                                    if (isLoggedIn()) {
                                        // Hanya ambil notifikasi yang belum dibaca
                                        $stmt = $pdo->prepare("SELECT * FROM notifikasi WHERE user_id = ? AND dibaca = 0 ORDER BY created_at DESC");
                                        $stmt->execute([$_SESSION['user_id']]);
                                        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        if (count($notifications) > 0) {
                                            foreach ($notifications as $notif) {
                                                echo '<div class="px-4 py-3 border-b hover:bg-gray-50 bg-blue-50 cursor-pointer notification-item" data-id="' . $notif['id'] . '">
                                                    <p class="text-sm text-gray-800">'.$notif['pesan'].'</p>
                                                    <p class="text-xs text-gray-500 mt-1">'.date('d M Y H:i', strtotime($notif['created_at'])).'</p>
                                                </div>';
                                            }
                                        } else {
                                            echo '<div class="px-4 py-3 text-center text-gray-500">Tidak ada notifikasi</div>';
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="px-4 py-2 border-t">
                                    <a href="#" class="text-primary text-sm font-medium" id="markAllRead">Tandai Semua Sudah Dibaca</a>
                                </div>
                            </div>
                        </div>
                        
                        <a href="index.php?logout=true" class="nav-link text-gray-700 hover:text-primary font-medium"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
                    <?php else: ?>
                        <a href="index.php" class="nav-link text-gray-700 hover:text-primary font-medium"><i class="fas fa-home mr-2"></i>Home</a>
                        <a href="index.php?view=login" class="nav-link text-gray-700 hover:text-primary font-medium"><i class="fas fa-sign-in-alt mr-2"></i>Login</a>
                        <a href="index.php?view=register" class="nav-link text-gray-700 hover:text-primary font-medium"><i class="fas fa-user-plus mr-2"></i>Daftar</a>
                    <?php endif; ?>
                </nav>
            </div>
            
            <!-- Mobile Navigation -->
            <div class="hidden py-4 bg-white mt-2 rounded-lg shadow-lg" id="mobileNav">
                <?php if (isLoggedIn()): ?>
                    <a href="index.php" class="block py-2 px-4 text-gray-700 hover:text-primary font-medium"><i class="fas fa-home mr-2"></i>Home</a>
                    <a href="index.php?view=dashboard" class="block py-2 px-4 text-gray-700 hover:text-primary hover:bg-gray-100 font-medium rounded-md"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a>
                    <?php if (isAdmin()): ?>
                        <a href="index.php?view=admin" class="block py-2 px-4 text-gray-700 hover:text-primary hover:bg-gray-100 font-medium rounded-md"><i class="fas fa-cog mr-2"></i>Admin Panel</a>
                    <?php endif; ?>
                    <a href="index.php?logout=true" class="block py-2 px-4 text-gray-700 hover:text-primary hover:bg-gray-100 font-medium rounded-md"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
                <?php else: ?>
                    <a href="index.php" class="block py-2 px-4 text-gray-700 hover:text-primary hover:bg-gray-100 font-medium rounded-md"><i class="fas fa-home mr-2"></i>Home</a>
                    <a href="index.php?view=login" class="block py-2 px-4 text-gray-700 hover:text-primary hover:bg-gray-100 font-medium rounded-md"><i class="fas fa-sign-in-alt mr-2"></i>Login</a>
                    <a href="index.php?view=register" class="block py-2 px-4 text-gray-700 hover:text-primary hover:bg-gray-100 font-medium rounded-md"><i class="fas fa-user-plus mr-2"></i>Daftar</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow container mx-auto px-4 py-8">
        <!-- Notifications -->
        <?php if (isset($_SESSION['message'])): ?>
            <div id="successMessage" class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?= $_SESSION['message']; unset($_SESSION['message']); ?></span>
                </div>
                <button onclick="hideNotification('successMessage')" class="text-green-700 hover:text-green-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div id="errorMessage" class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?= $_SESSION['error']; unset($_SESSION['error']); ?></span>
                </div>
                <button onclick="hideNotification('errorMessage')" class="text-red-700 hover:text-red-900">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>
        
        <!-- Dynamic Content -->
        <?php
        switch ($view) {
            case 'register':
                // View Register
                echo '
                <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-md overflow-hidden p-6 md:p-8">
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-gray-800">Daftar Akun Baru</h2>
                        <p class="text-gray-600 mt-2">Isi formulir berikut untuk membuat akun</p>
                    </div>
                    
                    <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-6" id="registerForm">
                        <input type="hidden" name="action" value="register">
                        
                        <div class="md:col-span-2">
                            <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Informasi Pribadi</h3>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2" for="nama_lengkap">Nama Lengkap <span class="text-red-500">*</span></label>
                            <input type="text" id="nama_lengkap" name="nama_lengkap" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2" for="username">Username <span class="text-red-500">*</span></label>
                            <input type="text" id="username" name="username" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2" for="email">Email <span class="text-red-500">*</span></label>
                            <input type="email" id="email" name="email" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2" for="telepon">No. Telepon <span class="text-red-500">*</span></label>
                            <input type="tel" id="telepon" name="telepon" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2" for="nik">NIK <span class="text-red-500">*</span></label>
                            <input type="text" id="nik" name="nik" maxlength="16" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            <p class="text-xs text-gray-500 mt-1">16 digit angka</p>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2" for="kk">No. KK <span class="text-red-500">*</span></label>
                            <input type="text" id="kk" name="kk" maxlength="16" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                            <p class="text-xs text-gray-500 mt-1">16 digit angka</p>
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-gray-700 mb-2" for="alamat">Alamat Lengkap <span class="text-red-500">*</span></label>
                            <textarea id="alamat" name="alamat" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary" rows="3"></textarea>
                        </div>
                        
                        <div class="md:col-span-2 mt-4">
                            <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Informasi Akun</h3>
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2" for="password">Password <span class="text-red-500">*</span></label>
                            <input type="password" id="password" name="password" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-gray-700 mb-2" for="confirm_password">Konfirmasi Password <span class="text-red-500">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div class="md:col-span-2 mt-6">
                            <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3 px-4 rounded-lg transition duration-300">
                                Daftar Sekarang
                            </button>
                        </div>
                        
                        <div class="md:col-span-2 text-center mt-4">
                            <p class="text-gray-600">Sudah punya akun? <a href="index.php?view=login" class="text-primary hover:underline">Login di sini</a></p>
                        </div>
                    </form>
                </div>';
                break;
                
            case 'login':
                // View Login
                echo '
                <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden p-6 md:p-8">
                    <div class="text-center mb-8">
                        <div class="bg-primary p-3 rounded-full inline-block mb-4">
                            <i class="fas fa-user-lock text-white text-3xl"></i>
                        </div>
                        <h2 class="text-3xl font-bold text-gray-800">Login ke Akun</h2>
                        <p class="text-gray-600 mt-2">Masuk untuk mengelola lamaran Anda</p>
                    </div>
                    
                    <form method="POST" action="" id="loginForm">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 mb-2" for="username">Username</label>
                            <input type="text" id="username" name="username" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 mb-2" for="password">Password</label>
                            <input type="password" id="password" name="password" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div class="mb-6">
                            <button type="submit" class="w-full bg-primary hover:bg-blue-800 text-white font-bold py-3 px-4 rounded-lg transition duration-300">
                                Login
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <p class="text-gray-600">Belum punya akun? <a href="index.php?view=register" class="text-primary hover:underline">Daftar di sini</a></p>
                        </div>
                    </form>
                </div>';
                break;
                
            case 'dashboard':
                if (!isLoggedIn()) {
                    redirect('index.php?view=login');
                }
                // View Dashboard
                echo '
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-800">Dashboard Pelamar</h2>
                    <p class="text-gray-600">Selamat datang, ' . $_SESSION['nama_lengkap'] . '! Kelola lamaran kerja Anda di sini.</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-md p-6 flex items-center card-hover">
                        <div class="bg-blue-100 p-4 rounded-full mr-4">
                            <i class="fas fa-briefcase text-blue-600 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">' . count($lamaran_saya) . '</h3>
                            <p class="text-gray-600">Total Lamaran</p>
                        </div>
                    </div>
                    
               
                    
                    <div class="bg-white rounded-xl shadow-md p-6 flex items-center card-hover">
                        <div class="bg-green-100 p-4 rounded-full mr-4">
                            <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-800">' . count(array_filter($lamaran_saya, function($lamaran) { return $lamaran['status'] == 'diterima'; })) . '</h3>
                            <p class="text-gray-600">Diterima</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b">
                        <h3 class="text-xl font-semibold text-gray-800">Lamaran Terbaru</h3>
                    </div>
                    <div class="p-6">
                        ' . (count($lamaran_saya) > 0 ? '
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="py-3 px-4 text-left">Posisi</th>
                                        <th class="py-3 px-4 text-left">Departemen</th>
                                        <th class="py-3 px-4 text-left">Tanggal Apply</th>
                                        <th class="py-3 px-4 text-left">Status</th>
                                        <th class="py-3 px-4 text-left">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ' . implode('', array_map(function($lamaran) {
                                        $status_class = '';
                                        if ($lamaran['status'] == 'diterima') $status_class = 'bg-green-100 text-green-800';
                                        else if ($lamaran['status'] == 'ditolak') $status_class = 'bg-red-100 text-red-800';
                                        else $status_class = 'bg-yellow-100 text-yellow-800';
                                        
                                        return '
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="py-3 px-4">' . $lamaran['lowongan_judul'] . '</td>
                                            <td class="py-3 px-4">' . $lamaran['departemen'] . '</td>
                                            <td class="py-3 px-4">' . date('d M Y', strtotime($lamaran['created_at'])) . '</td>
                                            <td class="py-3 px-4"><span class="px-3 py-1 rounded-full text-xs ' . $status_class . '">' . ucfirst($lamaran['status']) . '</span></td>
                                            <td class="py-3 px-4">
                                                <a href="' . $lamaran['cv_path'] . '" target="_blank" class="text-blue-600 hover:text-blue-800 mr-3"><i class="fas fa-eye"></i> Lihat CV</a>
                                            </td>
                                        </tr>';
                                    }, $lamaran_saya)) . '
                                </tbody>
                            </table>
                        </div>' : '
                        <div class="text-center py-8">
                            <div class="bg-gray-100 p-4 rounded-full inline-block mb-4">
                                <i class="fas fa-file-alt text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum ada lamaran</h3>
                            <p class="text-gray-600 mb-4">Anda belum mengajukan lamaran untuk lowongan manapun</p>
                            <a href="index.php" class="bg-primary hover:bg-blue-800 text-white font-bold py-2 px-4 rounded-lg transition duration-300 inline-block">
                                Lihat Lowongan
                            </a>
                        </div>') . '
                    </div>
                </div>';
                break;
                
            case 'apply':
                if (!isLoggedIn()) {
                    redirect('index.php?view=login');
                }
                // View Apply
                $lowongan_id = $_GET['id'];
                $lowongan_detail = $pdo->prepare("SELECT * FROM lowongan WHERE id = ?");
                $lowongan_detail->execute([$lowongan_id]);
                $lowongan_detail = $lowongan_detail->fetch(PDO::FETCH_ASSOC);
                
                if (!$lowongan_detail) {
                    $_SESSION['error'] = "Lowongan tidak ditemukan!";
                    redirect('index.php');
                }
                
                echo '
                <div class="max-w-3xl mx-auto">
                    <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
                        <div class="p-6 border-b">
                            <h2 class="text-2xl font-bold text-gray-800">Lamar Posisi: ' . $lowongan_detail['judul'] . '</h2>
                            <p class="text-gray-600">Departemen: ' . $lowongan_detail['departemen'] . '</p>
                        </div>
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-800 mb-4">Deskripsi Pekerjaan:</h3>
                            <p class="text-gray-700">' . $lowongan_detail['deskripsi'] . '</p>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md overflow-hidden p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-6">Formulir Lamaran</h3>
                        
                        <form method="POST" action="" enctype="multipart/form-data" id="applyForm">
                            <input type="hidden" name="action" value="apply_job">
                            <input type="hidden" name="lowongan_id" value="' . $lowongan_id . '">
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2" for="surat_lamaran">Surat Lamaran <span class="text-red-500">*</span></label>
                                <textarea id="surat_lamaran" name="surat_lamaran" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary" rows="5" placeholder="Tuliskan surat lamaran Anda yang menjelaskan mengapa Anda cocok untuk posisi ini"></textarea>
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2" for="cv">Upload CV (PDF/DOC/DOCX) <span class="text-red-500">*</span></label>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center file-upload-container" id="fileUploadContainer">
                                    <input type="file" id="cv" name="cv" required class="hidden" accept=".pdf,.doc,.docx">
                                    <div class="mb-4">
                                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-400"></i>
                                    </div>
                                    <p class="text-gray-600 mb-2">Drag & drop file here or</p>
                                    <label for="cv" class="bg-primary hover:bg-blue-800 text-white font-bold py-2 px-4 rounded-lg transition duration-300 inline-block cursor-pointer">
                                        Pilih File
                                    </label>
                                    <p class="text-xs text-gray-500 mt-2">Max. file size: 5MB</p>
                                    <div id="fileName" class="mt-3 text-sm text-primary font-medium hidden"></div>
                                </div>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <a href="index.php" class="text-gray-600 hover:text-gray-800 font-medium">
                                    <i class="fas fa-arrow-left mr-2"></i>Kembali
                                </a>
                                <button type="submit" class="bg-primary hover:bg-blue-800 text-white font-bold py-2 px-6 rounded-lg transition duration-300">
                                    Kirim Lamaran
                                </button>
                            </div>
                        </form>
                    </div>
                </div>';
                break;
                
            case 'admin':
                if (!isAdmin()) {
                    redirect('index.php');
                }
                // View Admin
                echo '
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-800">Admin Panel</h2>
                    <p class="text-gray-600">Kelola lowongan dan lamaran karyawan</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                        <div class="flex items-center">
                            <div class="bg-blue-100 p-3 rounded-full mr-4">
                                <i class="fas fa-file-alt text-blue-600 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-800">' . $total_lamaran . '</h3>
                                <p class="text-gray-600">Total Lamaran</p>
                            </div>
                        </div>
                    </div>
                    
                    
                    <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                        <div class="flex items-center">
                            <div class="bg-green-100 p-3 rounded-full mr-4">
                                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-800">' . $lamaran_diterima . '</h3>
                                <p class="text-gray-600">Diterima</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                        <div class="flex items-center">
                            <div class="bg-red-100 p-3 rounded-full mr-4">
                                <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold text-gray-800">' . $lamaran_ditolak . '</h3>
                                <p class="text-gray-600">Ditolak</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl shadow-md overflow-hidden mb-8">
                    <div class="border-b">
                        <ul class="flex flex-wrap -mb-px" id="adminTabs">
                            <li class="mr-2">
                                <button class="inline-block py-4 px-6 text-lg font-medium border-b-2 border-primary text-primary tab-button active" data-tab="lamaran">
                                    <i class="fas fa-list mr-2"></i>Lamaran Masuk
                                </button>
                            </li>
                            <li class="mr-2">
                                <button class="inline-block py-4 px-6 text-lg font-medium border-b-2 border-transparent hover:text-primary hover:border-gray-300 tab-button" data-tab="lowongan">
                                    <i class="fas fa-briefcase mr-2"></i>Kelola Lowongan
                                </button>
                            </li>
                            <li class="mr-2">
                                <button class="inline-block py-4 px-6 text-lg font-medium border-b-2 border-transparent hover:text-primary hover:border-gray-300 tab-button" data-tab="tambah">
                                    <i class="fas fa-plus-circle mr-2"></i>Tambah Lowongan
                                </button>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="p-6" id="lamaranTab">
                        <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-list mr-2"></i> Daftar Lamaran Masuk
                        </h3>
                        
                        ' . (count($semua_lamaran) > 0 ? '
                        <div class="overflow-x-auto rounded-lg shadow">
                            <table class="w-full whitespace-nowrap">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Pelamar</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Lowongan</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Tanggal</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">CV</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Status</th>
                                        <th class="py-3 px-4 text-left text-sm font-semibold text-gray-700">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    ' . implode('', array_map(function($l) {
                                        $status_class = '';
                                        if ($l['status'] == 'diterima') $status_class = 'bg-green-100 text-green-800';
                                        else if ($l['status'] == 'ditolak') $status_class = 'bg-red-100 text-red-800';
                                        else $status_class = 'bg-yellow-100 text-yellow-800';
                                        
                                        return '
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4">
                                                <div>
                                                    <p class="font-medium text-gray-900">' . htmlspecialchars($l['nama_lengkap']) . '</p>
                                                    <p class="text-sm text-gray-500">' . htmlspecialchars($l['email']) . '</p>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4">' . htmlspecialchars($l['lowongan_judul']) . '</td>
                                            <td class="py-3 px-4">' . date('d M Y', strtotime($l['created_at'])) . '</td>
                                            <td class="py-3 px-4">
                                                ' . ($l['cv_path'] ? '
                                                <a href="' . $l['cv_path'] . '" target="_blank" class="inline-flex items-center px-3 py-1 bg-primary text-white text-sm rounded-md hover:bg-blue-700 transition">
                                                    <i class="fas fa-download mr-1"></i> Unduh CV</a>' : '
                                                <span class="text-gray-500 text-sm">Tidak ada CV</span>') . '
                                            </td>
                                            <td class="py-3 px-4">
                                                <span class="px-3 py-1 rounded-full text-xs font-medium ' . $status_class . '">
                                                    ' . ($l['status'] == 'diterima' ? '<i class="fas fa-check-circle mr-1"></i>' : '') . '
                                                    ' . ($l['status'] == 'ditolak' ? '<i class="fas fa-times-circle mr-1"></i>' : '') . '
                                                    ' . ($l['status'] == 'menunggu' ? '<i class="fas fa-clock mr-1"></i>' : '') . '
                                                    ' . ucfirst($l['status']) . '
                                                </span>
                                            </td>
                                            <td class="py-3 px-4">
                                                <form method="POST" action="" class="inline">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="lamaran_id" value="' . $l['id'] . '">
                                                    <select name="status" class="text-xs border rounded px-2 py-1 mr-2 focus:outline-none focus:ring-2 focus:ring-primary" onchange="showNoteField(this)">
                                                        <option value="menunggu" ' . ($l['status'] == 'menunggu' ? 'selected' : '') . '>Menunggu</option>
                                                        <option value="diterima" ' . ($l['status'] == 'diterima' ? 'selected' : '') . '>Diterima</option>
                                                        <option value="ditolak" ' . ($l['status'] == 'ditolak' ? 'selected' : '') . '>Ditolak</option>
                                                    </select>
                                                    <div class="hidden mt-2" id="noteField-' . $l['id'] . '">
                                                        <textarea name="catatan" placeholder="Catatan untuk pelamar (opsional)" class="w-full text-xs border rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-primary" rows="2"></textarea>
                                                        <button type="submit" class="mt-1 px-3 py-1 bg-primary text-white text-xs rounded-md hover:bg-blue-700 transition">Update</button>
                                                    </div>
                                                </form>
                                            </td>
                                        </tr>';
                                    }, $semua_lamaran)) . '
                                </tbody>
                            </table>
                        </div>' : '
                        <div class="text-center py-8 bg-gray-50 rounded-lg">
                            <div class="bg-gray-200 p-4 rounded-full inline-block mb-4">
                                <i class="fas fa-file-alt text-gray-500 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum ada lamaran</h3>
                            <p class="text-gray-600">Tidak ada lamaran yang masuk saat ini</p>
                        </div>') . '
                    </div>
                    
                    <div class="p-6 hidden" id="lowonganTab">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-xl font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-briefcase mr-2"></i> Daftar Lowongan
                            </h3>
                        </div>
                        
                        ' . (count($semua_lowongan) > 0 ? '
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            ' . implode('', array_map(function($l) {
                                $department_colors = [
                                    'IT' => 'bg-blue-100 text-blue-800',
                                    'Marketing' => 'bg-purple-100 text-purple-800',
                                    'Finance' => 'bg-green-100 text-green-800',
                                    'HR' => 'bg-pink-100 text-pink-800',
                                    'Sales' => 'bg-orange-100 text-orange-800',
                                    'Customer Service' => 'bg-teal-100 text-teal-800',
                                    'Produksi' => 'bg-indigo-100 text-indigo-800',
                                    'Logistik' => 'bg-amber-100 text-amber-800'
                                ];
                                
                                $dept = $l['departemen'];
                                $badge_class = isset($department_colors[$dept]) ? $department_colors[$dept] : 'bg-gray-100 text-gray-800';
                                
                                return '
                                <div class="bg-white border rounded-lg shadow-sm overflow-hidden card-hover">
                                    <div class="p-6">
                                        <div class="flex justify-between items-start mb-4">
                                            <h4 class="text-lg font-semibold text-gray-800">' . htmlspecialchars($l['judul']) . '</h4>
                                            <span class="px-2.5 py-0.5 rounded text-xs font-medium ' . $badge_class . '">' . $dept . '</span>
                                        </div>
                                        <p class="text-gray-600 text-sm mb-4">' . htmlspecialchars(substr($l['deskripsi'], 0, 100)) . '...</p>
                                        <form method="POST" action="" class="inline">
                                            <input type="hidden" name="action" value="delete_job">
                                            <input type="hidden" name="job_id" value="' . $l['id'] . '">
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm" onclick="return confirm(\'Apakah Anda yakin ingin menghapus lowongan ini?\')">
                                                <i class="fas fa-trash-alt mr-1"></i> Hapus
                                            </button>
                                        </form>
                                    </div>
                                </div>';
                            }, $semua_lowongan)) . '
                        </div>' : '
                        <div class="text-center py-8 bg-gray-50 rounded-lg">
                            <div class="bg-gray-200 p-4 rounded-full inline-block mb-4">
                                <i class="fas fa-briefcase text-gray-500 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum ada lowongan</h3>
                            <p class="text-gray-600">Tambahkan lowongan pertama Anda</p>
                        </div>') . '
                    </div>
                    
                    <div class="p-6 hidden" id="tambahTab">
                        <h3 class="text-xl font-semibold text-gray-800 mb-6 flex items-center">
                            <i class="fas fa-plus-circle mr-2"></i> Tambah Lowongan Baru
                        </h3>
                        
                        <form method="POST" action="" id="addJobForm">
                            <input type="hidden" name="action" value="add_job">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium" for="judul">
                                        <i class="fas fa-heading mr-2 text-gray-500"></i>Judul Lowongan <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" id="judul" name="judul" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 mb-2 font-medium" for="departemen">
                                        <i class="fas fa-building mr-2 text-gray-500"></i>Departemen <span class="text-red-500">*</span>
                                    </label>
                                    <select id="departemen" name="departemen" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="">Pilih Departemen</option>
                                        <option value="IT">IT</option>
                                        <option value="Marketing">Marketing</option>
                                        <option value="Finance">Finance</option>
                                        <option value="HR">HR</option>
                                        <option value="Sales">Sales</option>
                                        <option value="Customer Service">Customer Service</option>
                                        <option value="Produksi">Produksi</option>
                                        <option value="Logistik">Logistik</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 mb-2 font-medium" for="deskripsi">
                                    <i class="fas fa-align-left mr-2 text-gray-500"></i>Deskripsi Pekerjaan <span class="text-red-500">*</span>
                                    </label>
                                <textarea id="deskripsi" name="deskripsi" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary" rows="5" placeholder="Jelaskan detail lengkap tentang lowongan ini"></textarea>
                            </div>
                            
                            <div class="text-right">
                                <button type="submit" class="bg-primary hover:bg-blue-800 text-white font-bold py-2 px-6 rounded-lg transition duration-300 flex items-center">
                                    <i class="fas fa-save mr-2"></i> Simpan Lowongan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        const tabs = document.querySelectorAll(".tab-button");
                        const tabContents = {
                            lamaran: document.getElementById("lamaranTab"),
                            lowongan: document.getElementById("lowonganTab"),
                            tambah: document.getElementById("tambahTab")
                        };
                        
                        tabs.forEach(tab => {
                            tab.addEventListener("click", function() {
                                const tabName = this.getAttribute("data-tab");
                                
                                // Hide all tabs
                                Object.values(tabContents).forEach(content => {
                                    content.classList.add("hidden");
                                });
                                
                                // Show selected tab
                                tabContents[tabName].classList.remove("hidden");
                                
                                // Update active tab style
                                tabs.forEach(t => {
                                    t.classList.remove("text-primary", "border-primary");
                                    t.classList.add("border-transparent");
                                });
                                this.classList.add("text-primary", "border-primary");
                                this.classList.remove("border-transparent");
                            });
                        });
                    });
                    
                    // Show note field when status is changed
                    function showNoteField(selectElement) {
                        const form = selectElement.closest(\'form\');
                        const applicationId = form.querySelector(\'input[name="lamaran_id"]\').value;
                        const noteField = document.getElementById(\'noteField-\' + applicationId);
                        
                        if (selectElement.value !== \'menunggu\') {
                            noteField.classList.remove(\'hidden\');
                        } else {
                            noteField.classList.add(\'hidden\');
                        }
                    }
                </script>';
                break;
                
            default:
                // View Home
                // Hitung statistik untuk ditampilkan
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
                $total_users = $stmt->fetch()['total'];

                $stmt = $pdo->query("SELECT COUNT(*) as total FROM lowongan");
                $total_jobs = $stmt->fetch()['total'];

                $stmt = $pdo->query("SELECT COUNT(*) as total FROM lamaran");
                $total_applications = $stmt->fetch()['total'];
                
                echo '
                <!-- Hero Section -->
                <div class="bg-gradient-to-r from-primary to-blue-800 rounded-2xl shadow-xl overflow-hidden mb-12 text-white">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center p-8 lg:p-12">
                        <div>
                            <h1 class="text-4xl lg:text-5xl font-bold mb-4">Temukan Karir Impian Anda di PT SEKAR PUTRA DAERAH</h1>
                            <p class="text-lg mb-8 opacity-90">Bergabunglah dengan tim profesional kami dan kembangkan potensi Anda dalam lingkungan kerja yang dinamis dan mendukung.</p>
                            <div class="flex flex-wrap gap-4">
                                ' . (!isLoggedIn() ? '
                                <a href="index.php?view=register" class="bg-white text-primary hover:bg-blue-100 font-bold py-3 px-6 rounded-lg transition duration-300">
                                    Daftar Sekarang
                                </a>
                                <a href="#lowongan" class="border-2 border-white text-white hover:bg-white hover:text-primary font-bold py-3 px-6 rounded-lg transition duration-300">
                                    Lihat Lowongan
                                </a>' : '
                                <a href="index.php?view=dashboard" class="bg-white text-primary hover:bg-blue-100 font-bold py-3 px-6 rounded-lg transition duration-300">
                                    <i class="fas fa-tachometer-alt mr-2"></i> Dashboard Saya
                                </a>') . '
                            </div>
                        </div>
                        <div class="hidden lg:block">
                            <img src="https://images.unsplash.com/photo-1551434678-e076c223a692?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80" alt="Career" class="rounded-xl shadow-lg">
                        </div>
                    </div>
                </div>
                
                <!-- Stats Section -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12">
                    <div class="bg-white rounded-xl shadow-md p-6 text-center card-hover">
                        <div class="bg-blue-100 p-3 rounded-full inline-block mb-4">
                            <i class="fas fa-users text-blue-600 text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800">' . $total_users . '</h3>
                        <p class="text-gray-600">Pengguna Terdaftar</p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md p-6 text-center card-hover">
                        <div class="bg-green-100 p-3 rounded-full inline-block mb-4">
                            <i class="fas fa-briefcase text-green-600 text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800">' . $total_jobs . '</h3>
                        <p class="text-gray-600">Lowongan Tersedia</p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-md p-6 text-center card-hover">
                        <div class="bg-purple-100 p-3 rounded-full inline-block mb-4">
                            <i class="fas fa-file-alt text-purple-600 text-2xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800">' . $total_applications . '</h3>
                        <p class="text-gray-600">Lamaran Dikirim</p>
                    </div>
                </div>
                
                <!-- Lowongan Section -->
                <div id="lowongan" class="mb-12">
                    <div class="flex justify-between items-center mb-8">
                        <h2 class="text-3xl font-bold text-gray-800">Lowongan Tersedia</h2>
                        <div id="refreshJobs" class="bg-gray-100 hover:bg-gray-200 p-2 rounded-lg cursor-pointer transition-colors">
                            <i class="fas fa-sync-alt text-gray-600"></i>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="jobsContainer">
                        ' . (count($lowongan) > 0 ? implode('', array_map(function($job) {
                            $department_colors = [
                                'IT' => 'bg-blue-100 text-blue-800',
                                'Marketing' => 'bg-purple-100 text-purple-800',
                                'Finance' => 'bg-green-100 text-green-800',
                                'HR' => 'bg-pink-100 text-pink-800',
                                'Sales' => 'bg-orange-100 text-orange-800',
                                'Customer Service' => 'bg-teal-100 text-teal-800',
                                'Produksi' => 'bg-indigo-100 text-indigo-800',
                                'Logistik' => 'bg-amber-100 text-amber-800'
                            ];
                            
                            $dept = $job['departemen'];
                            $badge_class = isset($department_colors[$dept]) ? $department_colors[$dept] : 'bg-gray-100 text-gray-800';
                            
                            return '
                            <div class="bg-white rounded-xl shadow-md overflow-hidden card-hover relative">
                                <span class="department-badge px-3 py-1 rounded-full text-xs font-medium ' . $badge_class . '">' . $dept . '</span>
                                <div class="p-6">
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">' . htmlspecialchars($job['judul']) . '</h3>
                                    <p class="text-gray-600 mb-4">' . htmlspecialchars(substr($job['deskripsi'], 0, 100)) . '...</p>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-500"><i class="far fa-clock mr-1"></i> ' . rand(1, 5) . ' hari lalu</span>
                                        <a href="' . (isLoggedIn() ? 'index.php?view=apply&id=' . $job['id'] : 'index.php?view=login') . '" class="bg-primary hover:bg-blue-800 text-white font-medium py-2 px-4 rounded-lg transition duration-300 text-sm">
                                            ' . (isLoggedIn() ? 'Lamar Sekarang' : 'Login untuk Lamar') . '
                                        </a>
                                    </div>
                                </div>
                            </div>';
                        }, $lowongan)) : '
                        <div class="col-span-3 text-center py-8 bg-gray-50 rounded-lg">
                            <div class="bg-gray-200 p-4 rounded-full inline-block mb-4">
                                <i class="fas fa-briefcase text-gray-500 text-2xl"></i>
                            </div>
                            <h3 class="text-xl font-semibold text-gray-700 mb-2">Belum ada lowongan tersedia</h3>
                            <p class="text-gray-600">Silakan cek kembali di lain waktu</p>
                        </div>') . '
                    </div>
                </div>
                
                <!-- Testimoni Section -->
                <div class="bg-gray-100 rounded-2xl p-8 mb-12">
                    <h2 class="text-3xl font-bold text-gray-800 text-center mb-8">Apa Kata Karyawan Kami?</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-user text-blue-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Budi Santoso</h4>
                                    <p class="text-sm text-gray-600">Web Developer</p>
                                </div>
                            </div>
                            <p class="text-gray-600">"Sejak bergabung dengan PT SEKAR PUTRA DAERAH, saya mendapatkan banyak kesempatan untuk mengembangkan skill dan karir saya. Lingkungan kerja yang sangat supportive."</p>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-user text-purple-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Siti Rahayu</h4>
                                    <p class="text-sm text-gray-600">Marketing Manager</p>
                                </div>
                            </div>
                            <p class="text-gray-600">"Perusahaan memberikan kesempatan yang sama bagi semua karyawan untuk berkembang. Sistem rekrutmennya juga sangat transparan dan profesional."</p>
                        </div>
                        
                        <div class="bg-white rounded-xl shadow-md p-6 card-hover">
                            <div class="flex items-center mb-4">
                                                            <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                    <i class="fas fa-user text-green-600"></i>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800">Ahmad Fauzi</h4>
                                    <p class="text-sm text-gray-600">Finance Analyst</p>
                                </div>
                            </div>
                            <p class="text-gray-600">"Saya sangat merekomendasikan PT SEKAR PUTRA DAERAH sebagai tempat untuk membangun karir. Benefit yang diberikan sangat kompetitif dan manajemennya sangat memperhatikan karyawan."</p>
                        </div>
                    </div>
                </div>
                
                <!-- CTA Section -->
                <div class="bg-gradient-to-r from-primary to-blue-800 rounded-2xl shadow-xl p-8 text-center text-white mb-12">
                    <h2 class="text-3xl font-bold mb-4">Siap Bergabung Dengan Kami?</h2>
                    <p class="text-lg mb-8 max-w-2xl mx-auto opacity-90">Daftarkan diri Anda sekarang dan jadilah bagian dari tim profesional PT SEKAR PUTRA DAERAH untuk mengembangkan karir dan potensi Anda.</p>
                    ' . (!isLoggedIn() ? '
                    <a href="index.php?view=register" class="bg-white text-primary hover:bg-blue-100 font-bold py-3 px-8 rounded-lg transition duration-300 inline-block">
                        Daftar Sekarang
                    </a>' : '
                    <a href="index.php?view=dashboard" class="bg-white text-primary hover:bg-blue-100 font-bold py-3 px-8 rounded-lg transition duration-300 inline-block">
                        Lihat Dashboard
                    </a>') . '
                </div>';
                break;
        }
        ?>
    </main>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4 flex items-center">
                        <div class="bg-primary p-2 rounded-lg mr-3">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        PT SEKAR PUTRA DAERAH
                    </h3>
                    <p class="text-gray-400">Kami adalah perusahaan yang bergerak di bidang teknologi yang selalu mencari talenta terbaik untuk bergabung dengan tim kami.</p>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4">Tautan Cepat</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li><a href="index.php" class="hover:text-white transition-colors"><i class="fas fa-arrow-right mr-2"></i>Home</a></li>
                        <li><a href="index.php#lowongan" class="hover:text-white transition-colors"><i class="fas fa-arrow-right mr-2"></i>Lowongan</a></li>
                        <li><a href="index.php?view=login" class="hover:text-white transition-colors"><i class="fas fa-arrow-right mr-2"></i>Login</a></li>
                        <li><a href="index.php?view=register" class="hover:text-white transition-colors"><i class="fas fa-arrow-right mr-2"></i>Daftar</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4">Kontak Kami</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li class="flex items-center">
                            <i class="fas fa-map-marker-alt mr-3 text-primary"></i>
                            <span>Jl. Contoh No. 123, Jakarta</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone mr-3 text-primary"></i>
                            <span>(021) 123-4567</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-3 text-primary"></i>
                            <span>info@ptsekard.com</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-4">Ikuti Kami</h3>
                    <div class="flex space-x-4">
                        <a href="#" class="bg-gray-800 hover:bg-gray-700 h-10 w-10 rounded-full flex items-center justify-center transition-colors">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="bg-gray-800 hover:bg-gray-700 h-10 w-10 rounded-full flex items-center justify-center transition-colors">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="bg-gray-800 hover:bg-gray-700 h-10 w-10 rounded-full flex items-center justify-center transition-colors">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="bg-gray-800 hover:bg-gray-700 h-10 w-10 rounded-full flex items-center justify-center transition-colors">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2025 PT SEKAR PUTRA DAERAH. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', function() {
            const mobileNav = document.getElementById('mobileNav');
            mobileNav.classList.toggle('hidden');
        });

        // Notification bell functionality
        document.getElementById('notificationBell').addEventListener('click', function() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('hidden');
            
            // Jika dropdown ditampilkan, refresh notifikasi
            if (!dropdown.classList.contains('hidden')) {
                refreshNotifications();
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const bell = document.getElementById('notificationBell');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (!bell.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Function to refresh notifications via AJAX
        function refreshNotifications() {
            $.ajax({
                url: 'index.php?get_notifications=true',
                type: 'GET',
                dataType: 'json',
                success: function(notifications) {
                    let notificationHtml = '';
                    
                    if (notifications.length > 0) {
                        notifications.forEach(function(notif) {
                            const timeAgo = timeSince(new Date(notif.created_at));
                            
                            notificationHtml += `
                                <div class="px-4 py-3 border-b hover:bg-gray-50 bg-blue-50 cursor-pointer notification-item" data-id="${notif.id}">
                                    <p class="text-sm text-gray-800">${notif.pesan}</p>
                                    <p class="text-xs text-gray-500 mt-1">${timeAgo}</p>
                                </div>
                            `;
                        });
                    } else {
                        notificationHtml = '<div class="px-4 py-3 text-center text-gray-500">Tidak ada notifikasi</div>';
                    }
                    
                    $('#notificationList').html(notificationHtml);
                    
                    // Update badge count
                    updateNotificationBadge();
                },
                error: function() {
                    console.error('Gagal memuat notifikasi');
                }
            });
        }

        // Function to update notification badge count
        function updateNotificationBadge() {
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'get_unread_count'
                },
                success: function(response) {
                    const unreadCount = response.unread_count;
                    if (unreadCount > 0) {
                        $('.notification-badge').text(unreadCount).show();
                    } else {
                        $('.notification-badge').hide();
                    }
                }
            });
        }

        // Function to calculate time since
        function timeSince(date) {
            const seconds = Math.floor((new Date() - date) / 1000);
            
            let interval = seconds / 31536000;
            if (interval > 1) {
                return Math.floor(interval) + " tahun lalu";
            }
            interval = seconds / 2592000;
            if (interval > 1) {
                return Math.floor(interval) + " bulan lalu";
            }
            interval = seconds / 86400;
            if (interval > 1) {
                return Math.floor(interval) + " hari lalu";
            }
            interval = seconds / 3600;
            if (interval > 1) {
                return Math.floor(interval) + " jam lalu";
            }
            interval = seconds / 60;
            if (interval > 1) {
                return Math.floor(interval) + " menit lalu";
            }
            return Math.floor(seconds) + " detik lalu";
        }

        // Mark notification as read when clicked
        $(document).on('click', '.notification-item', function() {
            const notificationId = $(this).data('id');
            
            $.ajax({
                url: '',
                type: 'POST',
                data: {
                    action: 'mark_notification_read',
                    notif_id: notificationId
                },
                success: function() {
                    // Hapus notifikasi yang sudah dibaca dari daftar
                    $(this).remove();
                    
                    // Refresh daftar notifikasi
                    refreshNotifications();
                    
                    // Update badge count
                    updateNotificationBadge();
                }.bind(this)
            });
        });

        // Mark all notifications as read
        $('#markAllRead').on('click', function(e) {
            e.preventDefault();
            
            // Ambil semua ID notifikasi yang ditampilkan
            const notificationIds = [];
            $('.notification-item').each(function() {
                notificationIds.push($(this).data('id'));
            });
            
            // Kirim permintaan untuk menandai semua sebagai sudah dibaca
            notificationIds.forEach(function(id) {
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: {
                        action: 'mark_notification_read',
                        notif_id: id
                    }
                });
            });
            
            // Kosongkan daftar notifikasi
            $('#notificationList').html('<div class="px-4 py-3 text-center text-gray-500">Tidak ada notifikasi</div>');
            
            // Sembunyikan badge notifikasi
            $('.notification-badge').hide();
        });

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Hide notifications after 5 seconds
            setTimeout(function() {
                const successMsg = document.getElementById('successMessage');
                const errorMsg = document.getElementById('errorMessage');
                
                if (successMsg) successMsg.style.display = 'none';
                if (errorMsg) errorMsg.style.display = 'none';
            }, 5000);
            
            // Custom function to hide notifications
            window.hideNotification = function(id) {
                document.getElementById(id).style.display = 'none';
            };
            
            // File upload drag and drop
            const fileUploadContainer = document.getElementById('fileUploadContainer');
            const fileInput = document.getElementById('cv');
            const fileName = document.getElementById('fileName');
            
            if (fileUploadContainer && fileInput) {
                // Drag and drop events
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    fileUploadContainer.addEventListener(eventName, preventDefaults, false);
                });
                
                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                ['dragenter', 'dragover'].forEach(eventName => {
                    fileUploadContainer.addEventListener(eventName, highlight, false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    fileUploadContainer.addEventListener(eventName, unhighlight, false);
                });
                
                function highlight() {
                    fileUploadContainer.classList.add('dragover');
                }
                
                function unhighlight() {
                    fileUploadContainer.classList.remove('dragover');
                }
                
                fileUploadContainer.addEventListener('drop', handleDrop, false);
                
                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    fileInput.files = files;
                    updateFileName();
                }
                
                fileInput.addEventListener('change', updateFileName);
                
                function updateFileName() {
                    if (fileInput.files.length > 0) {
                        fileName.textContent = 'File terpilih: ' + fileInput.files[0].name;
                        fileName.classList.remove('hidden');
                    }
                }
            }
            
            // Admin tabs functionality
            const adminTabs = document.querySelectorAll('.tab-button');
            if (adminTabs.length > 0) {
                adminTabs.forEach(tab => {
                    tab.addEventListener('click', function() {
                        const tabName = this.getAttribute('data-tab');
                        
                        // Hide all tabs
                        document.querySelectorAll('[id$="Tab"]').forEach(tabContent => {
                            tabContent.classList.add('hidden');
                        });
                        
                        // Show selected tab
                        document.getElementById(tabName + 'Tab').classList.remove('hidden');
                        
                        // Update active tab style
                        adminTabs.forEach(t => {
                            t.classList.remove('active', 'border-primary', 'text-primary');
                            t.classList.add('border-transparent');
                        });
                        this.classList.add('active', 'border-primary', 'text-primary');
                    });
                });
            }
            
            // Refresh jobs functionality
            const refreshJobs = document.getElementById('refreshJobs');
            if (refreshJobs) {
                refreshJobs.addEventListener('click', function() {
                    this.classList.add('animate-spin');
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                });
            }
            
            // Real-time form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    let valid = true;
                    const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
                    
                    inputs.forEach(input => {
                        if (!input.value.trim()) {
                            valid = false;
                            input.classList.add('border', 'border-red-500');
                        } else {
                            input.classList.remove('border', 'border-red-500');
                        }
                    });
                    
                    // Validasi NIK dan KK
                    const nikInput = form.querySelector('input[name="nik"]');
                    if (nikInput && !/^[0-9]{16}$/.test(nikInput.value)) {
                        valid = false;
                        nikInput.classList.add('border', 'border-red-500');
                        alert('NIK harus terdiri dari 16 digit angka.');
                    }
                    
                    const kkInput = form.querySelector('input[name="kk"]');
                    if (kkInput && !/^[0-9]{16}$/.test(kkInput.value)) {
                        valid = false;
                        kkInput.classList.add('border', 'border-red-500');
                        alert('Nomor KK harus terdiri dari 16 digit angka.');
                    }
                    
                    // Validasi konfirmasi password
                    const passwordInput = form.querySelector('input[name="password"]');
                    const confirmInput = form.querySelector('input[name="confirm_password"]');
                    if (passwordInput && confirmInput && passwordInput.value !== confirmInput.value) {
                        valid = false;
                        confirmInput.classList.add('border', 'border-red-500');
                        alert('Konfirmasi password tidak sesuai.');
                    }
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Harap isi semua field yang wajib diisi dengan format yang benar!');
                    }
                });
            });
            
            // AJAX untuk notifikasi real-time
            setInterval(function() {
                if ($('#notificationDropdown').is(':visible')) {
                    refreshNotifications();
                }
                
                // Update badge count setiap 30 detik
                updateNotificationBadge();
            }, 30000); // Refresh setiap 30 detik
        });
    </script>
</body>
</html>