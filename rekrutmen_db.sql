-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 21 Agu 2025 pada 09.27
-- Versi server: 10.4.28-MariaDB
-- Versi PHP: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rekrutmen_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `lamaran`
--

CREATE TABLE `lamaran` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `lowongan_id` int(11) NOT NULL,
  `cv_path` varchar(255) DEFAULT NULL,
  `surat_lamaran` text DEFAULT NULL,
  `status` enum('menunggu','diterima','ditolak') DEFAULT 'menunggu',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `lamaran`
--

INSERT INTO `lamaran` (`id`, `user_id`, `lowongan_id`, `cv_path`, `surat_lamaran`, `status`, `created_at`) VALUES
(1, 2, 1, 'uploads/68a4625a56e63.pdf', 'Lorem ipsum, placeholder or dummy text used in typesetting and graphic design for previewing layouts. It features scrambled Latin text, which emphasizes the design over content of the layout. It is the standard placeholder text of the printing and publishing industries.', 'diterima', '2025-08-19 11:39:06'),
(2, 3, 3, 'uploads/68a4a0d1c1206.pdf', 'tes', 'diterima', '2025-08-19 16:05:37'),
(3, 1, 4, 'uploads/68a4a189e7087.docx', 'Testimoni', 'diterima', '2025-08-19 16:08:41'),
(4, 1, 4, 'uploads/68a4bc2b57e2c.pdf', 'kugj', 'diterima', '2025-08-19 18:02:19'),
(5, 2, 5, 'uploads/68a5248c131c9.pdf', 'fbcxfbx', 'diterima', '2025-08-20 01:27:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `lowongan`
--

CREATE TABLE `lowongan` (
  `id` int(11) NOT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `departemen` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `lowongan`
--

INSERT INTO `lowongan` (`id`, `judul`, `deskripsi`, `departemen`, `created_at`) VALUES
(1, 'Web Developer', 'Kami mencari web developer berpengalaman dengan pengetahuan PHP, JavaScript, dan framework modern.', 'IT', '2025-08-19 11:36:04'),
(2, 'Marketing Specialist', 'Dicari marketing specialist dengan pengalaman di digital marketing dan campaign management.', 'Marketing', '2025-08-19 11:36:04'),
(3, 'Customer Service', 'Kami membutuhkan customer service yang ramah dan komunikatif untuk melayani pelanggan.', 'Customer Service', '2025-08-19 11:36:04'),
(4, 'IT Support', 'Bisa semua 5 elemen', 'IT', '2025-08-19 16:07:36'),
(5, 'test', 'yfhjmhcnbv', 'HR', '2025-08-19 18:00:33');

-- --------------------------------------------------------

--
-- Struktur dari tabel `notifikasi`
--

CREATE TABLE `notifikasi` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pesan` text NOT NULL,
  `dibaca` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `notifikasi`
--

INSERT INTO `notifikasi` (`id`, `user_id`, `pesan`, `dibaca`, `created_at`) VALUES
(1, 2, 'Status lamaran Anda untuk posisi test telah diubah menjadi: Diterima. Catatan: Saya Terima', 1, '2025-08-20 01:28:18'),
(2, 2, 'Status lamaran Anda untuk posisi test telah diubah menjadi: Ditolak. Catatan: Saya tidak jadi tidak bisa lanjut ke interview', 0, '2025-08-20 01:29:41'),
(3, 2, 'Status lamaran Anda untuk posisi test telah diubah menjadi: Diterima. Catatan: oke', 0, '2025-08-20 04:20:41');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `nik` varchar(20) NOT NULL,
  `kk` varchar(20) NOT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `email`, `nik`, `kk`, `telepon`, `alamat`, `created_at`) VALUES
(1, 'admin', '$2y$10$fMoG0GHaeoakr3sJNsUAXOMB1aVUvcs66fxmzRLChwkTAk6zujnlK', 'Administrator', 'admin@example.com', '1234567890123456', '1234567890123456', NULL, NULL, '2025-08-19 11:36:04'),
(2, 'user', '$2y$10$Al4CW08k06Zksj3/WOfguu1P4DPP.ijlKUZxHJ5HCZJaf9oyxkPyi', 'user', 'user@gmail.com', '3525211230003222', '3525211230003222', '081217623624', 'Gresik', '2025-08-19 11:38:19'),
(3, 'pengguna', '$2y$10$IKOFblraIyUP9DHYNwsa6ODw4jPs/1sjZjSz8PrD62.WIpRBV4IZm', 'pengguna', 'pengguna@gmail.com', '8768374582458999', '8768374582458999', '081217623624', 'Gresik', '2025-08-19 16:04:28');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `lamaran`
--
ALTER TABLE `lamaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `lowongan_id` (`lowongan_id`);

--
-- Indeks untuk tabel `lowongan`
--
ALTER TABLE `lowongan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `nik` (`nik`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `lamaran`
--
ALTER TABLE `lamaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `lowongan`
--
ALTER TABLE `lowongan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `lamaran`
--
ALTER TABLE `lamaran`
  ADD CONSTRAINT `lamaran_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `lamaran_ibfk_2` FOREIGN KEY (`lowongan_id`) REFERENCES `lowongan` (`id`);

--
-- Ketidakleluasaan untuk tabel `notifikasi`
--
ALTER TABLE `notifikasi`
  ADD CONSTRAINT `notifikasi_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
