-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 09, 2025 at 08:26 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gallon_delivery`
--

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` int(11) NOT NULL,
  `delivery_fee` int(11) NOT NULL,
  `distance_km` decimal(5,2) NOT NULL,
  `payment_method` enum('transfer','cod') NOT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `order_status` enum('pending','processing','delivering','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `total_amount`, `delivery_fee`, `distance_km`, `payment_method`, `payment_status`, `order_status`, `notes`, `created_at`) VALUES
(2, 6, 12000, 0, 0.98, 'cod', 'pending', 'pending', '', '2025-07-09 04:34:11'),
(3, 6, 47000, 0, 0.98, 'cod', 'pending', 'pending', '', '2025-07-09 04:48:50'),
(4, 6, 224000, 0, 0.98, 'cod', 'pending', 'pending', '', '2025-07-09 05:01:42'),
(5, 6, 75000, 0, 0.98, 'cod', 'pending', 'pending', '', '2025-07-09 05:02:21'),
(6, 6, 32000, 0, 0.98, 'cod', 'pending', 'pending', '', '2025-07-09 05:41:58'),
(7, 6, 8000, 0, 0.98, 'cod', 'pending', 'pending', '', '2025-07-09 05:44:05'),
(8, 6, 4000, 0, 0.98, 'cod', 'pending', 'pending', '', '2025-07-09 05:45:45'),
(9, 6, 28000, 0, 0.98, 'cod', 'pending', 'pending', '', '2025-07-09 05:50:03');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `quantity`, `price`) VALUES
(2, 2, 1, 3, 4000),
(3, 3, 4, 1, 15000),
(4, 3, 6, 1, 32000),
(5, 4, 6, 7, 32000),
(6, 5, 4, 5, 15000),
(7, 6, 1, 8, 4000),
(8, 7, 1, 1, 4000),
(9, 7, 3, 1, 4000),
(10, 8, 3, 1, 4000),
(11, 9, 1, 1, 4000),
(12, 9, 3, 1, 4000),
(13, 9, 5, 1, 20000);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('isi_ulang','original','gas') NOT NULL,
  `brand` varchar(50) NOT NULL,
  `price` int(11) NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `type`, `brand`, `price`, `is_available`, `created_at`) VALUES
(1, 'Galon Isi Ulang Grand', 'isi_ulang', 'Grand', 4000, 1, '2025-07-08 23:33:59'),
(2, 'Galon Isi Ulang Aqua', 'isi_ulang', 'Aqua', 4000, 1, '2025-07-08 23:33:59'),
(3, 'Galon Isi Ulang Tripanca', 'isi_ulang', 'Tripanca', 4000, 1, '2025-07-08 23:33:59'),
(4, 'Galon Original Grand', 'original', 'Grand', 15000, 1, '2025-07-08 23:33:59'),
(5, 'Galon Original Aqua', 'original', 'Aqua', 20000, 1, '2025-07-08 23:33:59'),
(6, 'Gas LPG', 'gas', 'LPG', 32000, 1, '2025-07-08 23:33:59');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `password` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `house_photo` varchar(255) DEFAULT NULL,
  `role` enum('pembeli','karyawan','admin','boss') DEFAULT 'pembeli',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `phone`, `password`, `address`, `latitude`, `longitude`, `house_photo`, `role`, `created_at`) VALUES
(1, 'Admin', '098', '$2y$10$jBJdG8cN.2JUJYXUU8zPru6gWSDLjzL09CQcjQNjSOkT9Vk3MeWjW', 'Jl. Admin No. 1', -5.35413320, 105.24616870, NULL, 'admin', '2025-07-08 23:33:59'),
(2, 'Karyawan', '123', '$2y$10$BiWMyMFFQa9H6jnUYxr23uNx13ZHUOoPL.AoTuZZp0wQ5X7Zc3A0u', 'Jl. Karyawan No. 1', -5.35413320, 105.24616870, NULL, 'karyawan', '2025-07-08 23:33:59'),
(5, 'Boss', '0895605840762', '$2y$10$pfr0GhkvRFCveIBuuCi.W.rsxKEVcBcQ1ZW15JkASgOsp4.ftoDdu', 'Jl. Boss No. 1', -5.35413320, 105.24616870, NULL, 'boss', '2025-07-09 02:39:51'),
(6, 'muhammad ridho rizky alfatih', '0895605840761', '$2y$10$QgFetyeSdJaUlI1K6ix0kOrt8Kp670wtuSUjBa7klW/p90QrZSgAm', 'jl. PADAT KARYA LINGSUH LK II RT 12 KELURAHAN RAJABASA JAYA KECAMATAN RAJABASA', -5.35680540, 105.25460030, 'uploads/houses/house_1752035626.jpeg', 'pembeli', '2025-07-09 04:33:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
