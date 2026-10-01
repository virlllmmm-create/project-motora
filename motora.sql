-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 01, 2026 at 03:15 AM
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
-- Database: `motora`
--

-- --------------------------------------------------------

--
-- Table structure for table `communities`
--

CREATE TABLE `communities` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `region` varchar(100) DEFAULT NULL,
  `motorcycle_type` varchar(80) DEFAULT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 0,
  `requires_approval` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `send_messages` enum('all','admins') NOT NULL DEFAULT 'all',
  `edit_info` enum('all','admins') NOT NULL DEFAULT 'admins',
  `add_members` enum('all','admins') NOT NULL DEFAULT 'admins',
  `pin_messages` enum('all','admins') NOT NULL DEFAULT 'admins',
  `start_calls` enum('all','admins') NOT NULL DEFAULT 'all',
  `invite_token` char(64) DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `communities`
--

INSERT INTO `communities` (`id`, `owner_id`, `name`, `image`, `description`, `city`, `region`, `motorcycle_type`, `is_private`, `requires_approval`, `created_at`, `send_messages`, `edit_info`, `add_members`, `pin_messages`, `start_calls`, `invite_token`, `closed_at`) VALUES
(2, 11, 'openai1', 'uploads/community/64422c4ad4bc1c811e2ccdd902a261fbc69b.png', 'masuk yuk masuk', 'jakarta', 'tanggerang', 'sport', 0, 1, '2026-09-30 10:08:02', 'all', 'admins', 'admins', 'admins', 'all', NULL, '2026-09-30 18:28:17'),
(3, 11, 'sdadasdad', NULL, 'wdwadsawdsaw', 'wadsawdsa', 'awdsawdsa', 'wadsawdsa', 0, 0, '2026-09-30 10:25:35', 'all', 'admins', 'admins', 'admins', 'all', NULL, '2026-09-30 18:28:04'),
(6, 11, 'sdadasdad', NULL, 'yuks', 'jakarta', 'tanggerang', 'sport', 0, 0, '2026-09-30 11:30:06', 'all', 'admins', 'admins', 'admins', 'all', '7feaa6a7106a66c0993fc5712aa6bff363a1e89b9b752f3b1adc60f9f777d6db', NULL),
(8, 11, 'openai1', NULL, 'awdsadawsadas', 'wadsawdsa', 'tanggerang', 'sport', 0, 0, '2026-09-30 23:41:17', 'all', 'admins', 'admins', 'admins', 'all', 'a20408cb51a7440c79b2017592b91789ed85c900d8ea53a054bcb68499a8793d', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `community_calls`
--

CREATE TABLE `community_calls` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `started_by` int(11) NOT NULL,
  `kind` enum('audio','video') NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `ended_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_call_participants`
--

CREATE TABLE `community_call_participants` (
  `call_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` char(32) NOT NULL,
  `last_seen` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_call_signals`
--

CREATE TABLE `community_call_signals` (
  `id` int(11) NOT NULL,
  `call_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `sender_session` char(32) NOT NULL,
  `recipient_session` char(32) NOT NULL,
  `payload` mediumtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_members`
--

CREATE TABLE `community_members` (
  `community_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` enum('leader','admin','member') NOT NULL DEFAULT 'member',
  `last_read_message_id` int(11) NOT NULL DEFAULT 0,
  `muted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `community_members`
--

INSERT INTO `community_members` (`community_id`, `user_id`, `status`, `joined_at`, `role`, `last_read_message_id`, `muted`) VALUES
(2, 11, 'approved', '2026-09-30 10:08:02', 'leader', 20, 0),
(3, 11, 'approved', '2026-09-30 10:25:35', 'leader', 19, 0),
(6, 11, 'approved', '2026-09-30 11:30:06', 'leader', 21, 0),
(8, 11, 'approved', '2026-09-30 23:41:17', 'leader', 85, 0);

-- --------------------------------------------------------

--
-- Table structure for table `community_messages`
--

CREATE TABLE `community_messages` (
  `id` int(11) NOT NULL,
  `community_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL,
  `body` text NOT NULL,
  `kind` enum('message','system') NOT NULL DEFAULT 'message',
  `attachment` varchar(255) DEFAULT NULL,
  `reply_to` int(11) DEFAULT NULL,
  `edited_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `pinned_until` datetime DEFAULT NULL,
  `pinned_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `community_messages`
--

INSERT INTO `community_messages` (`id`, `community_id`, `sender_id`, `body`, `kind`, `attachment`, `reply_to`, `edited_at`, `deleted_at`, `deleted_by`, `pinned_until`, `pinned_by`, `created_at`) VALUES
(19, 3, 11, 'devin menutup komunitas.', 'system', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-30 11:28:04'),
(20, 2, 11, 'devin menutup komunitas.', 'system', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-30 11:28:17'),
(21, 6, 11, 'devin membuat komunitas.', 'system', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-30 11:30:06'),
(85, 8, 11, 'devin membuat komunitas.', 'system', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-09-30 23:41:17');

-- --------------------------------------------------------

--
-- Table structure for table `community_message_hidden`
--

CREATE TABLE `community_message_hidden` (
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `community_message_reactions`
--

CREATE TABLE `community_message_reactions` (
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `emoji` varchar(16) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `created_at`) VALUES
(2, '2026-09-25 03:24:53'),
(3, '2026-09-25 03:26:42'),
(4, '2026-09-25 03:39:50');

-- --------------------------------------------------------

--
-- Table structure for table `conversation_members`
--

CREATE TABLE `conversation_members` (
  `conversation_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conversation_members`
--

INSERT INTO `conversation_members` (`conversation_id`, `user_id`, `joined_at`) VALUES
(4, 10, '2026-09-25 03:39:50'),
(4, 11, '2026-09-25 03:39:50');

-- --------------------------------------------------------

--
-- Table structure for table `friend_requests`
--

CREATE TABLE `friend_requests` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','blocked') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `friend_requests`
--

INSERT INTO `friend_requests` (`id`, `sender_id`, `receiver_id`, `status`, `created_at`, `updated_at`) VALUES
(3, 11, 10, 'accepted', '2026-09-25 09:49:24', '2026-09-29 10:33:37');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` bigint(20) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivered_at` timestamp NULL DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `body`, `created_at`, `delivered_at`, `read_at`) VALUES
(4, 4, 11, 'halo', '2026-09-25 03:39:50', '2026-09-25 04:07:52', '2026-09-25 04:07:56'),
(5, 4, 11, 'woi', '2026-09-25 03:45:04', '2026-09-25 04:07:52', '2026-09-25 04:07:56'),
(6, 4, 11, 'halo', '2026-09-25 04:09:17', '2026-09-25 04:09:35', '2026-09-25 04:09:46'),
(7, 4, 10, 'halo devin', '2026-09-25 09:48:38', '2026-09-25 09:49:07', '2026-09-25 09:49:15'),
(8, 4, 11, 'we', '2026-09-28 11:24:59', '2026-09-28 13:41:52', '2026-09-29 04:26:42'),
(9, 4, 11, 'wdsa', '2026-09-28 11:27:55', '2026-09-28 13:41:52', '2026-09-29 04:26:42'),
(10, 4, 11, 'asd', '2026-09-28 11:32:50', '2026-09-28 13:41:52', '2026-09-29 04:26:42'),
(11, 4, 10, 'wdsa', '2026-09-30 09:56:20', '2026-09-30 09:57:02', '2026-09-30 10:08:42'),
(12, 4, 11, 'ppp', '2026-10-01 00:47:47', '2026-10-01 00:58:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `motorcycles`
--

CREATE TABLE `motorcycles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `brand` varchar(80) NOT NULL,
  `model` varchar(100) NOT NULL,
  `type` varchar(80) NOT NULL DEFAULT '',
  `year` smallint(5) UNSIGNED DEFAULT NULL,
  `color` varchar(60) DEFAULT NULL,
  `plate_number` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `main_photo` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `motorcycles`
--

INSERT INTO `motorcycles` (`id`, `user_id`, `brand`, `model`, `type`, `year`, `color`, `plate_number`, `description`, `main_photo`, `is_primary`, `created_at`) VALUES
(4, 11, 'kawasaki', 'zx25rr', 'sport', 2021, 'hijau', NULL, 'gaaa', 'uploads/motor/1beeab9c85c4a4c8f1c292b908a3bdff5e43.jpg', 1, '2026-09-30 06:04:02'),
(5, 11, 'kawasaki', 'zx25rr', 'sport', 1933, 'hijau', NULL, 'awdsa', 'uploads/motor/4eafd46c2c71f12bd0d132e10bcd375558ee.png', 0, '2026-09-30 09:58:58'),
(6, 11, 'kawasaki', 'wdasawdsawd', 'awdsawdsa', 2000, NULL, NULL, NULL, NULL, 0, '2026-09-30 10:26:14');

-- --------------------------------------------------------

--
-- Table structure for table `motorcycle_photos`
--

CREATE TABLE `motorcycle_photos` (
  `id` int(11) NOT NULL,
  `motorcycle_id` int(11) NOT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `motorcycle_photos`
--

INSERT INTO `motorcycle_photos` (`id`, `motorcycle_id`, `path`, `created_at`) VALUES
(2, 4, 'uploads/motor/1beeab9c85c4a4c8f1c292b908a3bdff5e43.jpg', '2026-09-30 06:04:02'),
(3, 5, 'uploads/motor/4eafd46c2c71f12bd0d132e10bcd375558ee.png', '2026-09-30 09:58:58');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint(20) NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `type` varchar(40) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `body` varchar(255) NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `recipient_id`, `actor_id`, `type`, `entity_id`, `body`, `read_at`, `created_at`) VALUES
(10, 10, 11, 'message', 4, 'Pesan baru dari devin.', '2026-09-25 04:09:43', '2026-09-25 03:39:50'),
(11, 10, 11, 'message', 4, 'Pesan baru dari devin.', '2026-09-25 04:09:42', '2026-09-25 03:45:04'),
(12, 10, 11, 'message', 4, 'Pesan baru dari devin.', '2026-09-25 04:09:41', '2026-09-25 04:09:17'),
(17, 11, 10, 'message', 4, 'Pesan baru dari orville.', '2026-09-25 09:49:12', '2026-09-25 09:48:38'),
(18, 10, 11, 'friend_request', 3, 'devin ingin berteman dengan Anda.', '2026-09-28 13:41:52', '2026-09-25 09:49:24'),
(19, 10, 11, 'message', 4, 'Pesan baru dari devin.', '2026-09-28 13:41:52', '2026-09-28 11:25:00'),
(20, 10, 11, 'message', 4, 'Pesan baru dari devin.', '2026-09-28 13:41:52', '2026-09-28 11:27:55'),
(21, 10, 11, 'message', 4, 'Pesan baru dari devin.', '2026-09-28 13:41:52', '2026-09-28 11:32:50'),
(22, 11, 10, 'friend_accepted', 3, 'orville menerima permintaan teman.', '2026-09-29 10:33:49', '2026-09-29 10:33:37'),
(23, 11, 10, 'message', 4, 'Pesan baru dari orville.', '2026-09-30 09:57:12', '2026-09-30 09:56:20'),
(180, 10, 11, 'message', 4, 'Pesan baru dari devin.', '2026-10-01 00:58:44', '2026-10-01 00:47:47');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id_user` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id_user`, `nama`, `username`, `email`, `password_hash`, `role`, `city`, `region`, `bio`, `profile_photo`, `created_at`) VALUES
(10, 'orville', 'ville', '999.docb2@gmail.com', '$2y$10$9n4T3JJz2qdQSu3oGlV28eXLAZ8qjULqIH9fO94CYIqh9.FrnBs1m', 'rider', 'tanggerang', NULL, NULL, NULL, '2026-09-25 03:38:13'),
(11, 'devin', 'depin', 'orvilleeeeeee@gmail.com', '$2y$10$R.zPaS7JI1rX7.FyMcxgteUldAZ3ZRQyGsQUN.NBu9bBZd.e4PcEa', 'rider', 'tanggerang', 'banten', NULL, NULL, '2026-09-25 03:38:59');

-- --------------------------------------------------------

--
-- Table structure for table `user_settings`
--

CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `profile_visibility` enum('public','friends','private') NOT NULL DEFAULT 'public',
  `motorcycles_visibility` enum('public','friends','private') NOT NULL DEFAULT 'public',
  `gallery_visibility` enum('public','friends','private') NOT NULL DEFAULT 'public',
  `show_city` tinyint(1) NOT NULL DEFAULT 1,
  `is_searchable` tinyint(1) NOT NULL DEFAULT 1,
  `notify_friend_requests` tinyint(1) NOT NULL DEFAULT 1,
  `notify_community` tinyint(1) NOT NULL DEFAULT 1,
  `notify_messages` tinyint(1) NOT NULL DEFAULT 1,
  `notify_activity` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_settings`
--

INSERT INTO `user_settings` (`user_id`, `profile_visibility`, `motorcycles_visibility`, `gallery_visibility`, `show_city`, `is_searchable`, `notify_friend_requests`, `notify_community`, `notify_messages`, `notify_activity`, `updated_at`) VALUES
(10, 'public', 'public', 'public', 1, 1, 1, 1, 1, 1, '2026-09-25 03:38:13'),
(11, 'public', 'public', 'public', 1, 1, 1, 1, 1, 1, '2026-09-25 03:38:59');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `communities`
--
ALTER TABLE `communities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_community_invite` (`invite_token`),
  ADD KEY `idx_community_location` (`city`,`region`),
  ADD KEY `idx_community_type` (`motorcycle_type`),
  ADD KEY `fk_community_owner` (`owner_id`);

--
-- Indexes for table `community_calls`
--
ALTER TABLE `community_calls`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_group_calls` (`community_id`,`ended_at`);

--
-- Indexes for table `community_call_participants`
--
ALTER TABLE `community_call_participants`
  ADD PRIMARY KEY (`call_id`,`user_id`);

--
-- Indexes for table `community_call_signals`
--
ALTER TABLE `community_call_signals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_group_signal` (`call_id`,`recipient_id`,`id`);

--
-- Indexes for table `community_members`
--
ALTER TABLE `community_members`
  ADD PRIMARY KEY (`community_id`,`user_id`),
  ADD KEY `idx_member_user_status` (`user_id`,`status`);

--
-- Indexes for table `community_messages`
--
ALTER TABLE `community_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_group_messages` (`community_id`,`id`),
  ADD KEY `fk_group_message_sender` (`sender_id`);

--
-- Indexes for table `community_message_hidden`
--
ALTER TABLE `community_message_hidden`
  ADD PRIMARY KEY (`message_id`,`user_id`),
  ADD KEY `fk_group_hidden_user` (`user_id`);

--
-- Indexes for table `community_message_reactions`
--
ALTER TABLE `community_message_reactions`
  ADD PRIMARY KEY (`message_id`,`user_id`),
  ADD KEY `fk_group_reaction_user` (`user_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `conversation_members`
--
ALTER TABLE `conversation_members`
  ADD PRIMARY KEY (`conversation_id`,`user_id`),
  ADD KEY `idx_conversation_member_user` (`user_id`);

--
-- Indexes for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_friend_direction` (`sender_id`,`receiver_id`),
  ADD KEY `idx_friend_receiver` (`receiver_id`,`status`),
  ADD KEY `idx_friend_sender` (`sender_id`,`status`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_message_conversation` (`conversation_id`,`id`),
  ADD KEY `idx_message_sender` (`sender_id`);

--
-- Indexes for table `motorcycles`
--
ALTER TABLE `motorcycles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_motor_user` (`user_id`),
  ADD KEY `idx_motor_search` (`type`,`brand`,`model`);

--
-- Indexes for table `motorcycle_photos`
--
ALTER TABLE `motorcycle_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_photo_motor` (`motorcycle_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notification_user` (`recipient_id`,`read_at`,`id`),
  ADD KEY `fk_notification_actor` (`actor_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_password_reset_token` (`token_hash`),
  ADD KEY `idx_password_reset_user` (`user_id`,`expires_at`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_user_username` (`username`),
  ADD KEY `idx_user_city_region` (`city`,`region`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `communities`
--
ALTER TABLE `communities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `community_calls`
--
ALTER TABLE `community_calls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `community_call_signals`
--
ALTER TABLE `community_call_signals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `community_messages`
--
ALTER TABLE `community_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `friend_requests`
--
ALTER TABLE `friend_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `motorcycles`
--
ALTER TABLE `motorcycles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `motorcycle_photos`
--
ALTER TABLE `motorcycle_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=181;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `communities`
--
ALTER TABLE `communities`
  ADD CONSTRAINT `fk_community_owner` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `community_calls`
--
ALTER TABLE `community_calls`
  ADD CONSTRAINT `fk_group_call_community` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_call_participants`
--
ALTER TABLE `community_call_participants`
  ADD CONSTRAINT `fk_group_call_participant` FOREIGN KEY (`call_id`) REFERENCES `community_calls` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_call_signals`
--
ALTER TABLE `community_call_signals`
  ADD CONSTRAINT `fk_group_signal_call` FOREIGN KEY (`call_id`) REFERENCES `community_calls` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `community_members`
--
ALTER TABLE `community_members`
  ADD CONSTRAINT `fk_cm_community` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cm_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `community_messages`
--
ALTER TABLE `community_messages`
  ADD CONSTRAINT `fk_group_message_community` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `user` (`id_user`) ON DELETE SET NULL;

--
-- Constraints for table `community_message_hidden`
--
ALTER TABLE `community_message_hidden`
  ADD CONSTRAINT `fk_group_hidden_message` FOREIGN KEY (`message_id`) REFERENCES `community_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_hidden_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `community_message_reactions`
--
ALTER TABLE `community_message_reactions`
  ADD CONSTRAINT `fk_group_reaction_message` FOREIGN KEY (`message_id`) REFERENCES `community_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_group_reaction_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `conversation_members`
--
ALTER TABLE `conversation_members`
  ADD CONSTRAINT `fk_conversation_member_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_conversation_member_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `friend_requests`
--
ALTER TABLE `friend_requests`
  ADD CONSTRAINT `fk_friend_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_friend_sender` FOREIGN KEY (`sender_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `motorcycles`
--
ALTER TABLE `motorcycles`
  ADD CONSTRAINT `fk_motor_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `motorcycle_photos`
--
ALTER TABLE `motorcycle_photos`
  ADD CONSTRAINT `fk_photo_motor` FOREIGN KEY (`motorcycle_id`) REFERENCES `motorcycles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_actor` FOREIGN KEY (`actor_id`) REFERENCES `user` (`id_user`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notification_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;

--
-- Constraints for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
