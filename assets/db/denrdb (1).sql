-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 21, 2025 at 06:05 AM
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
-- Database: `denrdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action`, `module`, `created_at`) VALUES
(32, 8, 'Updated plantation #13 status to validated', 'plantations', '2025-10-31 04:55:17'),
(33, 8, 'Updated plantation #13 status to validated', 'plantations', '2025-10-31 04:55:21'),
(34, 8, 'Updated plantation #13 status to pending', 'plantations', '2025-10-31 04:55:24'),
(35, 8, 'Updated plantation #13 status to registered', 'plantations', '2025-10-31 04:56:12'),
(36, 8, 'Updated plantation #13 status to pending', 'plantations', '2025-10-31 04:56:22'),
(37, 8, 'Updated plantation #13 status to validated', 'plantations', '2025-10-31 04:56:25'),
(38, 8, 'Updated plantation #13 status to pending', 'plantations', '2025-10-31 04:58:33'),
(39, 8, 'Updated plantation #13 status to pending', 'plantations', '2025-10-31 04:58:42'),
(40, 8, 'Updated plantation #13 status to registered', 'plantations', '2025-10-31 04:59:07'),
(41, 10, 'Submitted cutting permit request for plantation ID: 13', 'permits', '2025-10-31 04:59:33'),
(42, 8, 'Updated permit #9 status to approved', 'permits', '2025-10-31 05:09:53'),
(43, 8, 'Updated permit #9 status to approved', 'permits', '2025-10-31 05:11:51'),
(44, 8, 'Updated permit #9 status to approved', 'permits', '2025-10-31 05:15:09'),
(45, 8, 'Updated permit #9 status to approved', 'permits', '2025-10-31 05:18:12'),
(46, 8, 'Updated plantation #14 status to registered', 'plantations', '2025-11-16 12:25:01'),
(47, 8, 'Updated plantation #14 status to validated', 'plantations', '2025-11-21 03:25:42'),
(48, 8, 'Updated plantation #14 status from validated to registered', 'plantations', '2025-11-21 03:36:28');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `conversation_id` int(11) NOT NULL,
  `user_1_id` int(11) NOT NULL,
  `user_2_id` int(11) NOT NULL,
  `last_message_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`conversation_id`, `user_1_id`, `user_2_id`, `last_message_id`, `created_at`) VALUES
(1, 9, 8, NULL, '2025-11-21 03:50:14'),
(2, 8, 10, NULL, '2025-11-21 04:02:48');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `doc_id` int(11) NOT NULL,
  `plantation_id` int(11) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`doc_id`, `plantation_id`, `document_name`, `file_name`, `file_path`, `uploaded_at`) VALUES
(7, 13, 'Permit Approval Document', 'TEST.docx', 'assets/uploads/documents/69044694aa822_permit_9.docx', '2025-10-31 05:18:12');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message_text` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(4) DEFAULT 0,
  `conversation_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `subject`, `message_text`, `created_at`, `is_read`, `conversation_id`) VALUES
(1, 9, 8, NULL, 'hello', '2025-11-21 03:50:39', 1, 1),
(2, 9, 8, NULL, 'HELLO\r\n', '2025-11-21 03:53:38', 1, 1),
(3, 9, 8, NULL, 'hello', '2025-11-21 04:01:04', 1, 1),
(4, 8, 9, NULL, 'HELLO', '2025-11-21 04:02:56', 1, 1),
(5, 8, 9, NULL, 'FDFSFDS', '2025-11-21 04:07:36', 1, 1),
(6, 9, 8, NULL, 'SDSADSA', '2025-11-21 04:07:49', 1, 1),
(7, 8, 9, NULL, 'dsdadd', '2025-11-21 04:57:23', 1, 1),
(8, 8, 9, NULL, 'csacssc', '2025-11-21 04:59:02', 1, 1),
(9, 8, 9, NULL, 'sdsad', '2025-11-21 04:59:54', 1, 1),
(10, 9, 8, NULL, 'SDSS', '2025-11-21 05:02:14', 1, 1),
(11, 8, 9, NULL, 'SDSADSA', '2025-11-21 05:02:23', 1, 1),
(12, 8, 9, NULL, 'SDDSDSSA', '2025-11-21 05:02:49', 1, 1),
(13, 9, 8, NULL, 'DSADADSD', '2025-11-21 05:02:57', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notif_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notif_id`, `user_id`, `message`, `is_read`, `created_at`) VALUES
(1, 10, 'Your permit application has been approved.', 1, '2025-10-31 05:09:53'),
(2, 10, 'Your permit application has been approved.', 1, '2025-10-31 05:11:51'),
(3, 10, 'Your permit application has been approved.', 1, '2025-10-31 05:15:09'),
(4, 10, 'Your permit application has been approved.', 1, '2025-10-31 05:18:12'),
(5, 9, 'Your plantation has been officially registered!', 1, '2025-11-21 03:36:28'),
(6, 8, 'You have a new message', 1, '2025-11-21 04:01:04'),
(7, 9, 'You have a new message', 1, '2025-11-21 04:02:56'),
(8, 9, 'You have a new message', 0, '2025-11-21 04:07:36'),
(9, 8, 'You have a new message', 1, '2025-11-21 04:07:49'),
(10, 9, 'You have a new message', 1, '2025-11-21 04:57:23'),
(11, 9, 'You have a new message', 0, '2025-11-21 04:59:02'),
(12, 9, 'You have a new message', 0, '2025-11-21 04:59:54'),
(13, 8, 'You have a new message', 0, '2025-11-21 05:02:14'),
(14, 9, 'You have a new message', 0, '2025-11-21 05:02:23'),
(15, 9, 'You have a new message', 0, '2025-11-21 05:02:49'),
(16, 8, 'You have a new message', 0, '2025-11-21 05:02:57');

-- --------------------------------------------------------

--
-- Table structure for table `permits`
--

CREATE TABLE `permits` (
  `permit_id` int(11) NOT NULL,
  `plantation_id` int(11) NOT NULL,
  `permit_type` enum('certificate','cutting') NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permits`
--

INSERT INTO `permits` (`permit_id`, `plantation_id`, `permit_type`, `status`, `remarks`, `requested_at`, `approved_at`) VALUES
(9, 13, 'cutting', 'approved', '', '2025-10-31 04:59:33', '2025-10-31 05:18:12');

-- --------------------------------------------------------

--
-- Table structure for table `plantations`
--

CREATE TABLE `plantations` (
  `plantation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plantation_name` varchar(150) DEFAULT NULL,
  `tree_species` varchar(100) DEFAULT NULL,
  `land_area` decimal(10,2) DEFAULT NULL,
  `location_address` text DEFAULT NULL,
  `latitude` decimal(10,6) DEFAULT NULL,
  `longitude` decimal(10,6) DEFAULT NULL,
  `status` enum('pending','validated','registered') DEFAULT 'pending',
  `registered_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plantations`
--

INSERT INTO `plantations` (`plantation_id`, `user_id`, `plantation_name`, `tree_species`, `land_area`, `location_address`, `latitude`, `longitude`, `status`, `registered_at`) VALUES
(13, 10, 'Nara', 'test', 2.00, 'Upper Bunguiao, Dulian, Zamboanga City, Zamboanga Peninsula, 7000, Philippines', 7.138128, 122.134223, 'registered', '2025-10-31 04:59:07'),
(14, 9, 'SSD', 'SDSADASD', 2.00, 'Sibuco, Zamboanga del Norte, Zamboanga Peninsula, 7122, Philippines', 7.197195, 122.052622, 'registered', '2025-11-21 03:36:28');

-- --------------------------------------------------------

--
-- Table structure for table `plantation_reviews`
--

CREATE TABLE `plantation_reviews` (
  `review_id` int(11) NOT NULL,
  `plantation_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(20) NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('landowner','officer','admin') NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_img` varchar(255) DEFAULT 'default.png',
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role`, `contact_number`, `created_at`, `profile_img`, `status`) VALUES
(8, 'Admin', 'admin@gmail.com', '$2y$10$y4DoJk0mfvZLJmNGRxkioOFBMyAVJ6F0NHoaHyOidhAIuA7uOZKcO', 'admin', '975248950', '2025-10-11 12:00:26', 'profile_8_1763294466.jpg', 'active'),
(9, 'Jerwin Tenajura', 'admin1@gmail.com', '$2y$10$zThrSKRf0HSu2RSE8cU5FunWsLpAj.ei1OUce9Vr1HUGv7spN7TJm', 'landowner', '09519456481', '2025-10-11 12:06:14', 'default.png', 'active'),
(10, 'Jerwin Teñajura', 'admin2@gmail.com', '$2y$10$UjKGxGXNLf65qSBEnqXKJejFJwZPmaKPhB0NO23siUII.e4BEdk/y', 'landowner', '933443', '2025-10-31 03:36:15', 'default.png', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`conversation_id`),
  ADD UNIQUE KEY `unique_conversation` (`user_1_id`,`user_2_id`),
  ADD KEY `user_2_id` (`user_2_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`doc_id`),
  ADD KEY `plantation_id` (`plantation_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`),
  ADD KEY `conversation_id` (`conversation_id`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `permits`
--
ALTER TABLE `permits`
  ADD PRIMARY KEY (`permit_id`),
  ADD KEY `plantation_id` (`plantation_id`);

--
-- Indexes for table `plantations`
--
ALTER TABLE `plantations`
  ADD PRIMARY KEY (`plantation_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `plantation_reviews`
--
ALTER TABLE `plantation_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD KEY `plantation_id` (`plantation_id`),
  ADD KEY `admin_id` (`admin_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `conversation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `permits`
--
ALTER TABLE `permits`
  MODIFY `permit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `plantations`
--
ALTER TABLE `plantations`
  MODIFY `plantation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `plantation_reviews`
--
ALTER TABLE `plantation_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`user_1_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user_2_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`plantation_id`) REFERENCES `plantations` (`plantation_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `permits`
--
ALTER TABLE `permits`
  ADD CONSTRAINT `permits_ibfk_1` FOREIGN KEY (`plantation_id`) REFERENCES `plantations` (`plantation_id`) ON DELETE CASCADE;

--
-- Constraints for table `plantations`
--
ALTER TABLE `plantations`
  ADD CONSTRAINT `plantations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `plantation_reviews`
--
ALTER TABLE `plantation_reviews`
  ADD CONSTRAINT `plantation_reviews_ibfk_1` FOREIGN KEY (`plantation_id`) REFERENCES `plantations` (`plantation_id`),
  ADD CONSTRAINT `plantation_reviews_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
