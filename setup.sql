-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 21, 2026 at 08:36 PM
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
(4, 9, 1, NULL, 'testeas', 0, 0, '2026-06-21 17:06:31');

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
(4, 1, 'dd', '<p>dddddd</p>', '162ac22a24fe8bb11e8794e534d6b3592d5b1408c08767fc989bfd8e0a2bb24b', 'view', 5, 8, '2026-06-21 16:55:10', '2026-06-21 17:03:33'),
(5, 10, 'da', '<p>das</p>', '919a96fcf27eaeae41abff049c5558ca9e7b918ff3774c9a0f39243ac2426198', 'view', 18, 10, '2026-06-21 17:10:47', '2026-06-21 17:10:47');

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

--
-- Dumping data for table `graduate_profiles`
--

INSERT INTO `graduate_profiles` (`id`, `user_id`, `job_status`, `company`, `job_title`, `salary_range`, `education_level`, `field_of_study`, `graduation_year`, `linkedin_url`, `bio`, `skills`, `matric_numbers`) VALUES
(1, 15, 'unemployed', '', '', '', 'bachelor', 'Computer Science', '2024', '', '', 'dadad', NULL);

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
(12, 'zinku05@gmail.com', '639592', '2026-06-22 01:14:35', 1, '2026-06-21 17:04:35'),
(13, 'tomiokagayuu@gmail.com', '746714', '2026-06-22 01:24:49', 1, '2026-06-21 17:14:49'),
(17, 'idiazfifa@gmail.com', '016313', '2026-06-22 01:42:34', 1, '2026-06-21 17:32:34');

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
(7, 1, 5, 'ghjnb', '', NULL, NULL, 'uploads/posts/post_doc_6a37ff3b586a0_W9_Lab_7_Basic_Router__Static_Route_Configuration_-_Answer_Sheet__1_.docx', 0, 0, 1, '2026-06-21 15:11:55'),
(8, 1, 5, 'dd', 'dd', NULL, 'http://localhost/Workshop ScholarSpace/view_doc.php?token=162ac22a24fe8bb11e8794e534d6b3592d5b1408c08767fc989bfd8e0a2bb24b', NULL, 0, 0, 0, '2026-06-21 16:55:10'),
(9, 10, 18, 'dada', '', NULL, NULL, NULL, 2, 0, 0, '2026-06-21 17:05:58'),
(10, 10, 18, 'da', 'das', NULL, 'http://localhost/Workshop ScholarSpace/view_doc.php?token=919a96fcf27eaeae41abff049c5558ca9e7b918ff3774c9a0f39243ac2426198', NULL, 0, 0, 0, '2026-06-21 17:10:47');

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
(81, 1, 5, '2026-06-21 15:13:55'),
(89, 10, 18, '2026-06-21 17:10:54'),
(91, 1, 18, '2026-06-21 17:06:34'),
(95, 1, 3, '2026-06-21 18:34:57');

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

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `reporter_id`, `target_type`, `target_id`, `reason`, `status`, `created_at`) VALUES
(2, 10, 'post', 8, 'dada', 'dismissed', '2026-06-21 17:05:27'),
(3, 1, 'user', 10, 'rfdsf', 'dismissed', '2026-06-21 17:57:06');

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
(7, 10, 'D032410343'),
(8, 11, 'D032410222');

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
(3, '/Sub - Cats', 'cats', 'Kitty where?', 'Hobby', 'default_sub.png', NULL, 1201, '2026-05-05 02:50:17', NULL, NULL),
(4, '/Sub - WhatIsThisThing', 'whatisthis', 'Sub to understand whatever you found', 'Hobby', 'default_sub.png', NULL, 457, '2026-05-05 02:50:17', NULL, NULL),
(5, '/Sub - ComputerScience', 'computerscience', 'Discuss CS concepts, papers and theories', 'Study', 'default_sub.png', NULL, 2402, '2026-05-05 02:50:17', NULL, NULL),
(6, '/Sub - MachineLearning', 'machinelearning', 'ML research, projects and breakthroughs', 'Study', 'default_sub.png', NULL, 1800, '2026-05-05 02:50:17', NULL, NULL),
(7, '/Sub - WhatIsThisThing?', '', 'Sub to understand whatever you found and can\'t understand where it\'s from', NULL, 'default_sub.png', NULL, 456, '2026-05-12 07:02:38', NULL, NULL),
(9, 'Testiing', 'testiing', 'mmmm', 'Hobby', 'default_sub.png', NULL, 1, '2026-05-30 06:14:26', NULL, 'mmm'),
(17, 'hgvjh', 'hgvjh', 'hjgkjhgk', 'Travel', 'uploads/subs/sub_icon_hgvjh_1782054105.png', 1, 1, '2026-06-21 15:01:45', NULL, ''),
(18, 'dadad', 'dadad', 'da', 'Technology', 'uploads/subs/sub_icon_dadad_1782061546.jpg', 10, 1, '2026-06-21 17:05:46', 'uploads/subs/sub_banner_dadad_1782061546.png', '');

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
(20, 1, 17, 'moderator', '2026-06-21 15:01:45'),
(25, 1, 5, 'member', '2026-06-21 15:13:55'),
(26, 10, 1, 'member', '2026-06-21 17:05:00'),
(27, 10, 2, 'member', '2026-06-21 17:05:00'),
(28, 10, 3, 'member', '2026-06-21 17:05:00'),
(29, 10, 18, 'moderator', '2026-06-21 17:05:46'),
(30, 11, 1, 'member', '2026-06-21 17:15:12'),
(31, 11, 2, 'member', '2026-06-21 17:15:12'),
(32, 11, 3, 'member', '2026-06-21 17:15:12'),
(33, 15, 1, 'member', '2026-06-21 17:32:49'),
(34, 15, 2, 'member', '2026-06-21 17:32:49'),
(35, 15, 3, 'member', '2026-06-21 17:32:49'),
(36, 1, 3, 'member', '2026-06-21 18:34:57');

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
(1, 'admin', 'admin@scholarspace.local', '$2y$12$dmrrK0bYmsStR5lEMRHmAO/b.KSM11LDW09QhBmKTOVDacJVciLQG', 'admin', 'uploads/profiles/profile_1_1782054673.png', 1, 0, 0, '2026-05-05 02:50:17', NULL, NULL, 'none', NULL, NULL),
(10, 'KuyaMuih', 'zinku05@gmail.com', '$2y$12$UvXUJdSRLXzG8TBmtWAUieUTVskR1RTGJZkV.Z/GcJ1dc5CRx5IZa', 'student', 'default.png', 1, 0, 0, '2026-06-21 17:05:00', '790833', '2026-06-22 02:45:52', 'none', NULL, NULL),
(11, 'Ali', 'tomiokagayuu@gmail.com', '$2y$12$6/h0c82gh1X4xv4tVzv7Suem5RbaojN92AZHW36GmTBEHkve3pKEu', 'student', 'default.png', 1, 0, 0, '2026-06-21 17:15:12', NULL, NULL, 'rejected', 'uploads/kyc/kyc_Ali_1782062089.jpg', ''),
(15, 'Boboiboy', 'idiazfifa@gmail.com', '$2y$12$Ul/rW3VVKYXHAGopdMSZeet6RCxhDSZjlVgVFYY/QRAjDktEMaFUa', 'graduate', 'default.png', 1, 0, 0, '2026-06-21 17:32:49', NULL, NULL, 'approved', 'uploads/kyc/kyc_Boboiboy_1782063154.png', NULL);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `document_shares`
--
ALTER TABLE `document_shares`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `graduate_profiles`
--
ALTER TABLE `graduate_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `otp_codes`
--
ALTER TABLE `otp_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `pending_registrations`
--
ALTER TABLE `pending_registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `recent_searches`
--
ALTER TABLE `recent_searches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `recent_visits`
--
ALTER TABLE `recent_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `student_profiles`
--
ALTER TABLE `student_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `subcommunities`
--
ALTER TABLE `subcommunities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `sub_bans`
--
ALTER TABLE `sub_bans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sub_memberships`
--
ALTER TABLE `sub_memberships`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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

CREATE TABLE IF NOT EXISTS post_votes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    post_id    INT NOT NULL,
    vote_type  ENUM('up','down') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);