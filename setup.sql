-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 08, 2026 at 09:26 AM
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
-- Database: `scholarspace`
--

-- --------------------------------------------------------

--
-- Table structure for table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `content` text NOT NULL,
  `upvotes` int(11) DEFAULT 0,
  `is_removed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `comments`
--

INSERT INTO `comments` (`id`, `post_id`, `user_id`, `parent_id`, `content`, `upvotes`, `is_removed`, `created_at`) VALUES
(3, 4, 1, NULL, 'AWSDSq', 0, 0, '2026-06-05 12:39:57');

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `title` varchar(300) NOT NULL DEFAULT 'Untitled Document',
  `content` longtext DEFAULT NULL,
  `share_token` varchar(64) NOT NULL,
  `share_mode` enum('private','view','edit') DEFAULT 'private',
  `sub_id` int(11) DEFAULT NULL,
  `post_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`id`, `owner_id`, `title`, `content`, `share_token`, `share_mode`, `sub_id`, `post_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'dada', '<p>adada</p>', '09a9ce1d7f292dcb34d056228e843ed2b8cc204bfa0320c592c318e01972432b', 'private', NULL, NULL, '2026-06-05 11:20:38', '2026-06-05 11:20:38'),
(2, 1, 'sddd', '<p>dsds</p>', 'cad313ad65d4e61da9a65cabc54a7043698d6f21462d6e4ee66580526f3068eb', 'private', NULL, NULL, '2026-06-06 16:45:55', '2026-06-06 16:45:55');

-- --------------------------------------------------------

--
-- Table structure for table `document_shares`
--

CREATE TABLE `document_shares` (
  `id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `permission` enum('view','edit') DEFAULT 'view',
  `shared_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `graduate_profiles`
--

CREATE TABLE `graduate_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_status` enum('employed','self-employed','freelance','unemployed','further_study') NOT NULL,
  `company` varchar(200) DEFAULT NULL,
  `job_title` varchar(150) DEFAULT NULL,
  `salary_range` varchar(50) DEFAULT NULL,
  `education_level` enum('diploma','bachelor','master','phd','other') NOT NULL,
  `field_of_study` varchar(150) DEFAULT NULL,
  `graduation_year` year(4) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `matric_numbers` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

CREATE TABLE `otp_codes` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_codes`
--

INSERT INTO `otp_codes` (`id`, `email`, `otp`, `expires_at`, `used`, `created_at`) VALUES
(3, 'zinku05@gmail.com', '943641', '2026-05-05 11:42:02', 1, '2026-05-05 09:32:02'),
(4, 'tomiokagayuu@gmail.com', '477911', '2026-05-19 05:58:04', 1, '2026-05-19 03:48:04'),
(6, 'afifnaufalzaidi@gmail.com', '218576', '2026-06-03 02:22:03', 1, '2026-06-03 00:12:03'),
(7, 'idiazfifa@gmail.com', '642483', '2026-06-03 07:46:57', 1, '2026-06-03 05:36:57'),
(10, 'afiflegend2006@gmail.com', '343906', '2026-06-07 20:58:49', 0, '2026-06-07 12:48:49');

-- --------------------------------------------------------

--
-- Table structure for table `pending_registrations`
--

CREATE TABLE `pending_registrations` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_type` enum('student','graduate') NOT NULL,
  `profile_photo` varchar(255) DEFAULT 'default.png',
  `extra_data` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pending_registrations`
--

INSERT INTO `pending_registrations` (`id`, `username`, `email`, `password_hash`, `user_type`, `profile_photo`, `extra_data`, `created_at`) VALUES
(6, 'Ali', 'afifnaufalzaidi@gmail.com', '$2y$12$EQu.RwuxYJ1YbxVvL86kfeHUTY/99blIaiuAvqMJ8mjs.GRNYq122', 'graduate', 'default.png', '{\"matric_number\":\"D03210003\",\"job_status\":\"unemployed\",\"company\":\"\",\"job_title\":\"\",\"salary_range\":\"\",\"education_level\":\"bachelor\",\"field_of_study\":\"Computer Science\",\"graduation_year\":\"2023\",\"linkedin_url\":\"\",\"bio\":\"\",\"skills\":\"\"}', '2026-06-03 00:12:03'),
(10, 'KuihMuih', 'afiflegend2006@gmail.com', '$2y$12$7H4jW3spBQT17AnZV9FJUO96B3deGMrQ97jYL0yghljZDrwb8EoBa', 'student', 'default.png', '{\"matric_number\":\"D032410364\",\"kyc_image\":\"uploads\\/kyc\\/kyc_KuihMuih_1780836529.png\"}', '2026-06-07 12:48:49');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sub_id` int(11) NOT NULL,
  `title` varchar(300) NOT NULL,
  `content` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `file_url` varchar(255) DEFAULT NULL,
  `upvotes` int(11) DEFAULT 0,
  `downvotes` int(11) DEFAULT 0,
  `is_removed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `posts`
--

INSERT INTO `posts` (`id`, `user_id`, `sub_id`, `title`, `content`, `image_url`, `link_url`, `file_url`, `upvotes`, `downvotes`, `is_removed`, `created_at`) VALUES
(4, 1, 4, 'saa', '', NULL, NULL, NULL, 0, 0, 0, '2026-06-05 12:39:49');

-- --------------------------------------------------------

--
-- Table structure for table `recent_searches`
--

CREATE TABLE `recent_searches` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `query` varchar(200) NOT NULL,
  `searched_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recent_visits`
--

CREATE TABLE `recent_visits` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sub_id` int(11) NOT NULL,
  `visited_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recent_visits`
--

INSERT INTO `recent_visits` (`id`, `user_id`, `sub_id`, `visited_at`) VALUES
(59, 1, 4, '2026-06-05 12:40:02');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `target_type` enum('post','user') NOT NULL,
  `target_id` int(11) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `status` enum('pending','resolved','dismissed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_profiles`
--

CREATE TABLE `student_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `matric_number` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_profiles`
--

INSERT INTO `student_profiles` (`id`, `user_id`, `matric_number`) VALUES
(1, 2, 'D032410360');

-- --------------------------------------------------------

--
-- Table structure for table `subcommunities`
--

CREATE TABLE `subcommunities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(110) NOT NULL,
  `description` text DEFAULT NULL,
  `topic` varchar(80) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT 'default_sub.png',
  `creator_id` int(11) DEFAULT NULL,
  `member_count` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `banner_image` varchar(255) DEFAULT NULL,
  `rules` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subcommunities`
--

INSERT INTO `subcommunities` (`id`, `name`, `slug`, `description`, `topic`, `profile_photo`, `creator_id`, `member_count`, `created_at`, `banner_image`, `rules`) VALUES
(1, '/Sub - Programming Y1', 'programming-y1', 'Sub community on sharing notes and ideas around Year 1', 'Study', 'default_sub.png', NULL, 234, '2026-05-05 02:50:17', NULL, NULL),
(2, '/Sub - Ranting', 'ranting', 'Let it all out, no one cares', 'Hobby', 'default_sub.png', NULL, 891, '2026-05-05 02:50:17', NULL, NULL),
(3, '/Sub - Cats', 'cats', 'Kitty where?', 'Hobby', 'default_sub.png', NULL, 1200, '2026-05-05 02:50:17', NULL, NULL),
(4, '/Sub - WhatIsThisThing', 'whatisthis', 'Sub to understand whatever you found', 'Hobby', 'default_sub.png', NULL, 456, '2026-05-05 02:50:17', NULL, NULL),
(5, '/Sub - ComputerScience', 'computerscience', 'Discuss CS concepts, papers and theories', 'Study', 'default_sub.png', NULL, 2401, '2026-05-05 02:50:17', NULL, NULL),
(6, '/Sub - MachineLearning', 'machinelearning', 'ML research, projects and breakthroughs', 'Study', 'default_sub.png', NULL, 1800, '2026-05-05 02:50:17', NULL, NULL),
(7, '/Sub - WhatIsThisThing?', '', 'Sub to understand whatever you found and can\'t understand where it\'s from', NULL, 'default_sub.png', NULL, 456, '2026-05-12 07:02:38', NULL, NULL),
(9, 'Testiing', 'testiing', 'mmmm', 'Hobby', 'default_sub.png', NULL, 1, '2026-05-30 06:14:26', NULL, 'mmm');

-- --------------------------------------------------------

--
-- Table structure for table `sub_bans`
--

CREATE TABLE `sub_bans` (
  `id` int(11) NOT NULL,
  `sub_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `banned_by` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sub_memberships`
--

CREATE TABLE `sub_memberships` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sub_id` int(11) NOT NULL,
  `role` enum('member','moderator') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sub_memberships`
--

INSERT INTO `sub_memberships` (`id`, `user_id`, `sub_id`, `role`, `joined_at`) VALUES
(1, 2, 1, 'member', '2026-05-05 09:32:20'),
(2, 2, 2, 'member', '2026-05-05 09:32:20'),
(3, 2, 3, 'member', '2026-05-05 09:32:20');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('student','graduate','admin') NOT NULL DEFAULT 'student',
  `profile_photo` varchar(255) DEFAULT 'default.png',
  `is_verified` tinyint(1) DEFAULT 0,
  `is_suspended` tinyint(1) DEFAULT 0,
  `is_banned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_otp` varchar(10) DEFAULT NULL,
  `reset_otp_expiry` datetime DEFAULT NULL,
  `kyc_status` enum('none','pending','approved','rejected') DEFAULT 'none',
  `kyc_image` varchar(255) DEFAULT NULL,
  `kyc_reason` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `user_type`, `profile_photo`, `is_verified`, `is_suspended`, `is_banned`, `created_at`, `reset_otp`, `reset_otp_expiry`, `kyc_status`, `kyc_image`, `kyc_reason`) VALUES
(1, 'admin', 'admin@scholarspace.local', '$2y$12$dmrrK0bYmsStR5lEMRHmAO/b.KSM11LDW09QhBmKTOVDacJVciLQG', 'admin', 'default.png', 1, 0, 0, '2026-05-05 02:50:17', NULL, NULL, 'none', NULL, NULL),
(2, 'KuyaKuih', 'zinku05@gmail.com', '$2y$12$FvU5O1/W.BJWJcdMizsoSeApuxvNnO51xQeKluHMWEd9EAY20r/2m', 'student', 'default.png', 1, 0, 0, '2026-05-05 09:32:20', '332819', '2026-06-07 01:06:14', 'none', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `share_token` (`share_token`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `sub_id` (`sub_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `document_shares`
--
ALTER TABLE `document_shares`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_share` (`doc_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `graduate_profiles`
--
ALTER TABLE `graduate_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `otp_codes`
--
ALTER TABLE `otp_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pending_registrations`
--
ALTER TABLE `pending_registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `sub_id` (`sub_id`);

--
-- Indexes for table `recent_searches`
--
ALTER TABLE `recent_searches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_search` (`user_id`,`query`);

--
-- Indexes for table `recent_visits`
--
ALTER TABLE `recent_visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_visit` (`user_id`,`sub_id`),
  ADD KEY `sub_id` (`sub_id`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`);

--
-- Indexes for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `matric_number` (`matric_number`);

--
-- Indexes for table `subcommunities`
--
ALTER TABLE `subcommunities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `creator_id` (`creator_id`);

--
-- Indexes for table `sub_bans`
--
ALTER TABLE `sub_bans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sub_ban` (`sub_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `banned_by` (`banned_by`);

--
-- Indexes for table `sub_memberships`
--
ALTER TABLE `sub_memberships`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`user_id`,`sub_id`),
  ADD KEY `sub_id` (`sub_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `document_shares`
--
ALTER TABLE `document_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `graduate_profiles`
--
ALTER TABLE `graduate_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `pending_registrations`
--
ALTER TABLE `pending_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `recent_searches`
--
ALTER TABLE `recent_searches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `recent_visits`
--
ALTER TABLE `recent_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `subcommunities`
--
ALTER TABLE `subcommunities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `sub_bans`
--
ALTER TABLE `sub_bans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sub_memberships`
--
ALTER TABLE `sub_memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comments_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`sub_id`) REFERENCES `subcommunities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `documents_ibfk_3` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_shares`
--
ALTER TABLE `document_shares`
  ADD CONSTRAINT `document_shares_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `document_shares_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `graduate_profiles`
--
ALTER TABLE `graduate_profiles`
  ADD CONSTRAINT `graduate_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`sub_id`) REFERENCES `subcommunities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recent_searches`
--
ALTER TABLE `recent_searches`
  ADD CONSTRAINT `recent_searches_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `recent_visits`
--
ALTER TABLE `recent_visits`
  ADD CONSTRAINT `recent_visits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recent_visits_ibfk_2` FOREIGN KEY (`sub_id`) REFERENCES `subcommunities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `student_profiles`
--
ALTER TABLE `student_profiles`
  ADD CONSTRAINT `student_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subcommunities`
--
ALTER TABLE `subcommunities`
  ADD CONSTRAINT `subcommunities_ibfk_1` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sub_bans`
--
ALTER TABLE `sub_bans`
  ADD CONSTRAINT `sub_bans_ibfk_1` FOREIGN KEY (`sub_id`) REFERENCES `subcommunities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sub_bans_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sub_bans_ibfk_3` FOREIGN KEY (`banned_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sub_memberships`
--
ALTER TABLE `sub_memberships`
  ADD CONSTRAINT `sub_memberships_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sub_memberships_ibfk_2` FOREIGN KEY (`sub_id`) REFERENCES `subcommunities` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
