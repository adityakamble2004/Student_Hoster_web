-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Generation Time: Apr 18, 2026 at 10:05 PM
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
-- Database: `studentport`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(150) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `ip`, `user_agent`, `meta`, `created_at`) VALUES
(1, 1, 'verified_by_admin', '::1', NULL, NULL, '2026-04-17 18:43:35'),
(2, 3, 'upload_created:1', '::1', NULL, NULL, '2026-04-18 00:24:02'),
(3, 3, 'upload_created:2', '::1', NULL, NULL, '2026-04-18 00:24:18'),
(4, 3, 'upload_deleted:1', '::1', NULL, NULL, '2026-04-18 00:25:04'),
(5, 3, 'upload_deleted:1', '::1', NULL, NULL, '2026-04-18 00:25:05'),
(6, 3, 'upload_deleted:1', '::1', NULL, NULL, '2026-04-18 00:25:08'),
(7, 3, 'upload_deleted:1', '::1', NULL, NULL, '2026-04-18 00:25:09'),
(8, 3, 'upload_deleted:1', '::1', NULL, NULL, '2026-04-18 00:25:09'),
(9, 3, 'upload_deleted:1', '::1', NULL, NULL, '2026-04-18 00:25:10'),
(10, 3, 'rescan_requested:1', '::1', NULL, NULL, '2026-04-18 00:25:12'),
(11, 3, 'upload_deleted:1', '::1', NULL, NULL, '2026-04-18 00:25:28'),
(12, 3, 'upload_deleted:1', '::1', NULL, NULL, '2026-04-18 00:38:02'),
(13, 3, 'upload_deleted:1', '::1', NULL, NULL, '2026-04-18 00:38:14'),
(14, 3, 'verified_by_admin', '::1', NULL, NULL, '2026-04-18 00:54:23'),
(15, 2, 'verified_by_admin', '::1', NULL, NULL, '2026-04-18 00:54:24'),
(16, 3, 'upload_created:3', '::1', NULL, NULL, '2026-04-18 23:14:46'),
(17, 3, 'upload_created:4', '::1', NULL, NULL, '2026-04-19 00:40:46'),
(18, 3, 'upload_created:5', '::1', NULL, NULL, '2026-04-19 00:40:55'),
(19, 3, 'upload_created:6', '::1', NULL, NULL, '2026-04-19 01:20:20'),
(20, 3, 'upload_created:7', '::1', NULL, NULL, '2026-04-19 01:20:47'),
(21, 3, 'upload_created:8', '::1', NULL, NULL, '2026-04-19 01:21:30'),
(22, 3, 'upload_created:9', '::1', NULL, NULL, '2026-04-19 01:23:29');

-- --------------------------------------------------------

--
-- Table structure for table `job_queue`
--

CREATE TABLE `job_queue` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed` tinyint(1) NOT NULL DEFAULT 0,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `moderation_logs`
--

CREATE TABLE `moderation_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `portfolio_id` bigint(20) UNSIGNED NOT NULL,
  `moderator_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` enum('flagged','approved','removed','requested_edit') NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `portfolios`
--

CREATE TABLE `portfolios` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `storage_path` varchar(1024) NOT NULL,
  `visibility` enum('public','private_link','recruiter_only') NOT NULL DEFAULT 'public',
  `size_bytes` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `views_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `status` enum('pending','approved','live','removed') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deploy_path` varchar(1024) DEFAULT NULL,
  `subdomain` varchar(255) DEFAULT NULL,
  `deploy_status` enum('pending','processing','deployed','failed') DEFAULT 'pending',
  `entry_file` varchar(255) DEFAULT 'index.html'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `portfolios`
--

INSERT INTO `portfolios` (`id`, `user_id`, `title`, `slug`, `storage_path`, `visibility`, `size_bytes`, `views_count`, `published_at`, `status`, `created_at`, `updated_at`, `deploy_path`, `subdomain`, `deploy_status`, `entry_file`) VALUES
(1, 3, NULL, 'site_ab2e5e30', '', 'public', 0, 0, NULL, 'removed', '2026-04-19 00:57:23', '2026-04-19 00:57:23', NULL, NULL, 'pending', 'index.html'),
(2, 3, NULL, 'site_75519477', '', 'public', 0, 0, NULL, 'removed', '2026-04-19 00:57:23', '2026-04-19 00:57:23', NULL, NULL, 'pending', 'index.html'),
(3, 3, NULL, 'site_fb8d06c4', '', 'public', 0, 0, NULL, 'removed', '2026-04-19 00:57:23', '2026-04-19 00:57:23', NULL, NULL, 'pending', 'index.html'),
(4, 3, NULL, 'site_b1caed05', '', 'public', 0, 0, NULL, 'removed', '2026-04-19 00:57:23', '2026-04-19 00:57:23', NULL, NULL, 'pending', 'index.html'),
(5, 3, NULL, 'site_d5cfc9ed', '', 'public', 0, 0, NULL, 'removed', '2026-04-19 00:57:23', '2026-04-19 00:57:23', NULL, NULL, 'pending', 'index.html'),
(6, 3, NULL, 'site_5f22cfb3', '', 'public', 0, 0, NULL, 'removed', '2026-04-19 00:57:23', '2026-04-19 00:57:23', NULL, NULL, 'pending', 'index.html'),
(7, 3, NULL, 'site_53c49dc5', '', 'public', 0, 0, NULL, 'removed', '2026-04-19 01:20:20', '2026-04-19 01:20:20', NULL, NULL, 'pending', 'index.html'),
(8, 3, NULL, 'site_8d2ff122', '', 'public', 0, 0, NULL, 'removed', '2026-04-19 01:20:48', '2026-04-19 01:20:48', NULL, NULL, 'pending', 'index.html'),
(9, 3, NULL, 'site_c659e1c6', '', 'public', 0, 0, NULL, 'removed', '2026-04-19 01:21:30', '2026-04-19 01:21:30', NULL, NULL, 'pending', 'index.html'),
(10, 3, NULL, 'site_2f8286e7', '/student/public/portfolios/site_2f8286e7', 'public', 0, 0, '2026-04-19 01:23:31', 'live', '2026-04-19 01:23:31', '2026-04-19 01:23:31', NULL, NULL, 'pending', 'index.html');

-- --------------------------------------------------------

--
-- Table structure for table `portfolio_tags`
--

CREATE TABLE `portfolio_tags` (
  `portfolio_id` bigint(20) UNSIGNED NOT NULL,
  `tag` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shortlists`
--

CREATE TABLE `shortlists` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `recruiter_id` bigint(20) UNSIGNED NOT NULL,
  `portfolio_id` bigint(20) UNSIGNED NOT NULL,
  `reason` varchar(512) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uploads`
--

CREATE TABLE `uploads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `portfolio_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `original_filename` varchar(512) NOT NULL,
  `stored_filename` varchar(1024) NOT NULL,
  `size_bytes` bigint(20) UNSIGNED NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `scan_status` enum('pending','clean','infected','error') NOT NULL DEFAULT 'pending',
  `scan_report` text DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `stage` enum('queued','unpacking','scanning','validating','publishing','published','needs_moderation','infected','error') NOT NULL DEFAULT 'queued',
  `stage_detail` text DEFAULT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('student','recruiter','moderator','admin') NOT NULL DEFAULT 'student',
  `college` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','suspended','deleted') NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `college`, `is_verified`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Kamble Aditya balaji', 'ak8806657127@gmail.com', '$2y$10$8ZSU4tD2acPcrqXhPSt6QOgK10t/P/qnsTU9j0/V5F5XY2.J9pDeu', 'admin', 'APCOR&E', 1, 'active', '2026-04-17 17:02:33', '2026-04-17 18:43:35'),
(2, 'Kamble Aditya balaji', 'droptechnologyes@gmail.com', '$2y$10$8WyKOeR6fleCoUOIDsYyhOqJUacnupzqsO3X.o7rfg00qQPZYjQDO', 'moderator', 'APCOR&E', 1, 'active', '2026-04-17 19:22:38', '2026-04-18 00:54:24'),
(3, 'Kamble Aditya balaji', 'student@gmail.com', '$2y$10$Y2nMktikdnuiJHqtoTWEnebJoOl6VkAf0FHdgEfLrrDVViIPcsf4q', 'student', 'APCOR&E', 1, 'active', '2026-04-18 00:20:53', '2026-04-18 00:54:23');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`);

--
-- Indexes for table `job_queue`
--
ALTER TABLE `job_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `moderation_logs`
--
ALTER TABLE `moderation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_moderation_portfolio` (`portfolio_id`),
  ADD KEY `idx_moderation_moderator` (`moderator_id`);

--
-- Indexes for table `portfolios`
--
ALTER TABLE `portfolios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_portfolios_slug` (`slug`),
  ADD KEY `idx_portfolios_user` (`user_id`),
  ADD KEY `idx_portfolios_status` (`status`),
  ADD KEY `idx_portfolios_visibility` (`visibility`);

--
-- Indexes for table `portfolio_tags`
--
ALTER TABLE `portfolio_tags`
  ADD PRIMARY KEY (`portfolio_id`,`tag`),
  ADD KEY `idx_portfolio_tags_tag` (`tag`);

--
-- Indexes for table `shortlists`
--
ALTER TABLE `shortlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_shortlist_recruiter_portfolio` (`recruiter_id`,`portfolio_id`),
  ADD KEY `idx_shortlists_recruiter` (`recruiter_id`),
  ADD KEY `idx_shortlists_portfolio` (`portfolio_id`);

--
-- Indexes for table `uploads`
--
ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uploads_user` (`user_id`),
  ADD KEY `idx_uploads_portfolio` (`portfolio_id`),
  ADD KEY `idx_scan_status` (`scan_status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_users_email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `job_queue`
--
ALTER TABLE `job_queue`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `moderation_logs`
--
ALTER TABLE `moderation_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `portfolios`
--
ALTER TABLE `portfolios`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `shortlists`
--
ALTER TABLE `shortlists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `moderation_logs`
--
ALTER TABLE `moderation_logs`
  ADD CONSTRAINT `fk_moderation_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_moderation_portfolio` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `portfolios`
--
ALTER TABLE `portfolios`
  ADD CONSTRAINT `fk_portfolios_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `portfolio_tags`
--
ALTER TABLE `portfolio_tags`
  ADD CONSTRAINT `fk_pt_portfolio` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shortlists`
--
ALTER TABLE `shortlists`
  ADD CONSTRAINT `fk_shortlists_portfolio` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_shortlists_recruiter` FOREIGN KEY (`recruiter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `uploads`
--
ALTER TABLE `uploads`
  ADD CONSTRAINT `fk_uploads_portfolio` FOREIGN KEY (`portfolio_id`) REFERENCES `portfolios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_uploads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
