-- MOTORA schema migration for the existing `motora` database.
-- Audited first: existing table `user` had 0 rows, was InnoDB/utf8mb4,
-- and contained id_user, nama, email, password_hash, role, created_at.
-- This migration only adds columns/indexes and new tables. It never drops or
-- renames existing objects or deletes existing rows. Run once on `motora`.

ALTER TABLE `user`
  ADD COLUMN IF NOT EXISTS `username` VARCHAR(50) NULL AFTER `nama`,
  ADD COLUMN IF NOT EXISTS `city` VARCHAR(100) NULL AFTER `role`,
  ADD COLUMN IF NOT EXISTS `region` VARCHAR(100) NULL AFTER `city`,
  ADD COLUMN IF NOT EXISTS `bio` TEXT NULL AFTER `region`,
  ADD COLUMN IF NOT EXISTS `profile_photo` VARCHAR(255) NULL AFTER `bio`;

UPDATE `user` SET `username` = CONCAT('rider', `id_user`) WHERE `username` IS NULL OR `username` = '';
ALTER TABLE `user` MODIFY COLUMN `username` VARCHAR(50) NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS `uq_user_username` ON `user` (`username`);
CREATE INDEX IF NOT EXISTS `idx_user_city_region` ON `user` (`city`, `region`);

CREATE TABLE IF NOT EXISTS `user_settings` (
  `user_id` INT NOT NULL,
  `profile_visibility` ENUM('public','friends','private') NOT NULL DEFAULT 'public',
  `motorcycles_visibility` ENUM('public','friends','private') NOT NULL DEFAULT 'public',
  `gallery_visibility` ENUM('public','friends','private') NOT NULL DEFAULT 'public',
  `show_city` TINYINT(1) NOT NULL DEFAULT 1,
  `is_searchable` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_friend_requests` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_community` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_messages` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_activity` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `motorcycles` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `brand` VARCHAR(80) NOT NULL,
  `model` VARCHAR(100) NOT NULL,
  `type` VARCHAR(80) NOT NULL DEFAULT '',
  `year` SMALLINT UNSIGNED NULL,
  `color` VARCHAR(60) NULL,
  `plate_number` VARCHAR(20) NULL,
  `description` TEXT NULL,
  `main_photo` VARCHAR(255) NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_motor_user` (`user_id`),
  KEY `idx_motor_search` (`type`, `brand`, `model`),
  CONSTRAINT `fk_motor_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `motorcycle_photos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `motorcycle_id` INT NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_photo_motor` (`motorcycle_id`),
  CONSTRAINT `fk_photo_motor` FOREIGN KEY (`motorcycle_id`) REFERENCES `motorcycles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `communities` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `owner_id` INT NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `image` VARCHAR(255) NULL,
  `description` TEXT NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  `region` VARCHAR(100) NULL,
  `motorcycle_type` VARCHAR(80) NULL,
  `is_private` TINYINT(1) NOT NULL DEFAULT 0,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_community_location` (`city`, `region`),
  KEY `idx_community_type` (`motorcycle_type`),
  CONSTRAINT `fk_community_owner` FOREIGN KEY (`owner_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `community_members` (
  `community_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`community_id`, `user_id`),
  KEY `idx_member_user_status` (`user_id`, `status`),
  CONSTRAINT `fk_cm_community` FOREIGN KEY (`community_id`) REFERENCES `communities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cm_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `friend_requests` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `sender_id` INT NOT NULL,
  `receiver_id` INT NOT NULL,
  `status` ENUM('pending','accepted','rejected','blocked') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_friend_direction` (`sender_id`, `receiver_id`),
  KEY `idx_friend_receiver` (`receiver_id`, `status`),
  KEY `idx_friend_sender` (`sender_id`, `status`),
  CONSTRAINT `fk_friend_sender` FOREIGN KEY (`sender_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE,
  CONSTRAINT `fk_friend_receiver` FOREIGN KEY (`receiver_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conversation_members` (
  `conversation_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `joined_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`conversation_id`, `user_id`),
  KEY `idx_conversation_member_user` (`user_id`),
  CONSTRAINT `fk_conversation_member_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_conversation_member_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `conversation_id` INT NOT NULL,
  `sender_id` INT NOT NULL,
  `body` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `delivered_at` TIMESTAMP NULL,
  `read_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `idx_message_conversation` (`conversation_id`, `id`),
  KEY `idx_message_sender` (`sender_id`),
  CONSTRAINT `fk_message_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_message_sender` FOREIGN KEY (`sender_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `messages` ADD COLUMN IF NOT EXISTS `delivered_at` TIMESTAMP NULL AFTER `created_at`;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `recipient_id` INT NOT NULL,
  `actor_id` INT NULL,
  `type` VARCHAR(40) NOT NULL,
  `entity_id` INT NULL,
  `body` VARCHAR(255) NOT NULL,
  `read_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notification_user` (`recipient_id`, `read_at`, `id`),
  CONSTRAINT `fk_notification_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE,
  CONSTRAINT `fk_notification_actor` FOREIGN KEY (`actor_id`) REFERENCES `user` (`id_user`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_password_reset_token` (`token_hash`),
  KEY `idx_password_reset_user` (`user_id`, `expires_at`),
  CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
