-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 08, 2026 at 09:16 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_salon`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id_booking` varchar(20) NOT NULL,
  `nama_customer` varchar(100) NOT NULL,
  `whatsapp_customer` varchar(20) NOT NULL,
  `tgl_booking` date NOT NULL,
  `jam_booking` time NOT NULL,
  `total_biaya` decimal(10,2) DEFAULT 0.00,
  `bayar_cash` decimal(15,2) DEFAULT 0.00,
  `bayar_transfer` decimal(15,2) DEFAULT 0.00,
  `jumlah_terbayar` decimal(15,2) DEFAULT 0.00,
  `status_pembayaran` enum('pending','dp','lunas','batal') DEFAULT 'pending',
  `status_kerja` enum('menunggu','diproses','selesai') DEFAULT 'menunggu',
  `id_employee` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id_booking`, `nama_customer`, `whatsapp_customer`, `tgl_booking`, `jam_booking`, `total_biaya`, `bayar_cash`, `bayar_transfer`, `jumlah_terbayar`, `status_pembayaran`, `status_kerja`, `id_employee`, `created_at`) VALUES
('SLN-20260505-7098', 'Puspa', '0812341851891', '2026-05-05', '02:28:00', 385000.00, 0.00, 0.00, 0.00, 'lunas', 'selesai', 3, '2026-05-04 19:28:09'),
('SLN-20260505-752B', 'GG', '0852141401401', '2026-05-05', '07:35:00', 655000.00, 0.00, 0.00, 0.00, 'lunas', 'selesai', NULL, '2026-05-04 20:36:00'),
('SLN-20260506-3B6E', 'cek', '123', '2026-05-06', '21:20:00', 35000.00, 12000.00, 0.00, 12000.00, 'lunas', 'selesai', NULL, '2026-05-06 14:18:17'),
('SLN-20260506-4B9B', 'GG 2', '0185818', '2026-05-06', '01:07:00', 550000.00, 0.00, 0.00, 0.00, 'lunas', 'selesai', NULL, '2026-05-05 18:05:12'),
('SLN-20260506-4DD3', 'Nurjaman', '0875123149912', '2026-05-06', '08:48:00', 535000.00, 0.00, 0.00, 0.00, 'lunas', 'selesai', NULL, '2026-05-04 20:43:48'),
('SLN-20260506-5410', 'Sabian2', '085718237817', '2026-05-06', '00:20:00', 185000.00, 0.00, 0.00, 0.00, 'lunas', 'selesai', NULL, '2026-05-05 17:21:32'),
('SLN-20260506-5E90', 'Sabian', '085718237817', '2026-05-06', '00:20:00', 35000.00, 0.00, 14000.00, 14000.00, 'dp', 'menunggu', NULL, '2026-05-05 17:20:23'),
('SLN-20260506-7B6F', 'Sabian3', '08756172361', '2026-05-06', '00:23:00', 535000.00, 13000.00, 522000.00, 535000.00, 'lunas', 'selesai', NULL, '2026-05-05 17:22:36');

-- --------------------------------------------------------

--
-- Table structure for table `booking_details`
--

CREATE TABLE `booking_details` (
  `id_detail` int(11) NOT NULL,
  `id_booking` varchar(20) DEFAULT NULL,
  `id_service` int(11) DEFAULT NULL,
  `id_employee` int(11) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_details`
--

INSERT INTO `booking_details` (`id_detail`, `id_booking`, `id_service`, `id_employee`, `subtotal`) VALUES
(1, 'SLN-20260505-7098', 7, 3, 35000.00),
(2, 'SLN-20260505-7098', 8, 4, 350000.00),
(3, 'SLN-20260505-752B', 6, 4, 120000.00),
(4, 'SLN-20260505-752B', 7, 4, 35000.00),
(5, 'SLN-20260505-752B', 8, 6, 350000.00),
(6, 'SLN-20260505-752B', 9, 5, 150000.00),
(7, 'SLN-20260506-4DD3', 7, 4, 35000.00),
(8, 'SLN-20260506-4DD3', 8, 4, 350000.00),
(9, 'SLN-20260506-4DD3', 9, 3, 150000.00),
(10, 'SLN-20260506-5E90', 7, 5, 35000.00),
(11, 'SLN-20260506-5410', 7, 3, 35000.00),
(12, 'SLN-20260506-5410', 9, 3, 150000.00),
(13, 'SLN-20260506-7B6F', 7, 3, 35000.00),
(14, 'SLN-20260506-7B6F', 8, 3, 350000.00),
(15, 'SLN-20260506-7B6F', 9, 5, 150000.00),
(16, 'SLN-20260506-4B9B', 9, 4, 550000.00),
(17, 'SLN-20260506-3B6E', 7, 4, 35000.00);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id_employee` int(11) NOT NULL,
  `nama_karyawan` varchar(100) NOT NULL,
  `spesialisasi` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id_employee`, `nama_karyawan`, `spesialisasi`, `status`) VALUES
(3, 'Siti', 'Manicure & Pedicure', 'active'),
(4, 'Amoy', 'segala', 'active'),
(5, 'Herni', 'Smoothing', 'active'),
(6, 'Elly', 'Hair Color', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id_service` int(11) NOT NULL,
  `nama_layanan` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `harga` decimal(10,2) NOT NULL,
  `komisi_persen` int(3) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id_service`, `nama_layanan`, `deskripsi`, `harga`, `komisi_persen`) VALUES
(6, 'Eyelash', NULL, 120000.00, 20),
(7, 'Cuci Kering', NULL, 35000.00, 15),
(8, 'Smoothing Filler', NULL, 350000.00, 15),
(9, 'Hair Color', NULL, 550000.00, 15);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `level` int(11) NOT NULL COMMENT '0:SuperUser, 1:Admin, 2:Pegawai, 3:User'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `username`, `password`, `nama_lengkap`, `level`) VALUES
(3, 'admin', '$2y$10$X8lCAR7tDkEsDKz1VeCjL.nK09oifauLAqrqTEN4cARgJ43lREfvK', 'Owner Amoy Salon', 0),
(4, 'test', '$2y$10$G9hf5YYKMSwwF13..PnnouArtp6EtILBjd0bb7KrP1qowoy15QR/6', 'test', 3),
(5, 'ely', '$2y$10$rd0II31kM8iCk/nrwCc4ye18mbzMf1GTVhDpASmWEJZohLLrC1U3m', 'Ely', 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id_booking`),
  ADD KEY `id_employee` (`id_employee`);

--
-- Indexes for table `booking_details`
--
ALTER TABLE `booking_details`
  ADD PRIMARY KEY (`id_detail`),
  ADD KEY `id_booking` (`id_booking`),
  ADD KEY `id_service` (`id_service`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id_employee`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id_service`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `booking_details`
--
ALTER TABLE `booking_details`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id_employee` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id_service` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`id_employee`) REFERENCES `employees` (`id_employee`) ON DELETE SET NULL;

--
-- Constraints for table `booking_details`
--
ALTER TABLE `booking_details`
  ADD CONSTRAINT `booking_details_ibfk_1` FOREIGN KEY (`id_booking`) REFERENCES `bookings` (`id_booking`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_details_ibfk_2` FOREIGN KEY (`id_service`) REFERENCES `services` (`id_service`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
