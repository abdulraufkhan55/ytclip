-- Multi-Platform Short Video Publishing and Automation System Schema
-- Supports MySQL 8.0+ and MariaDB 10.4+

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `publishing_attempts`;
DROP TABLE IF EXISTS `publishing_jobs`;
DROP TABLE IF EXISTS `videos`;
DROP TABLE IF EXISTS `social_accounts`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- Users table
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'operator') NOT NULL DEFAULT 'operator',
  `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_role` (`role`),
  INDEX `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Social Accounts table
CREATE TABLE `social_accounts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `platform` ENUM('youtube', 'tiktok', 'instagram') NOT NULL,
  `account_id` VARCHAR(191) NOT NULL,
  `account_name` VARCHAR(191) NOT NULL,
  `encrypted_access_token` MEDIUMTEXT NOT NULL,
  `encrypted_refresh_token` MEDIUMTEXT NULL,
  `token_expires_at` DATETIME NULL,
  `granted_scopes` TEXT NULL,
  `connection_status` ENUM('connected', 'expired', 'revoked', 'error') NOT NULL DEFAULT 'connected',
  `last_verified_at` DATETIME NULL,
  `metadata` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_social_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  UNIQUE KEY `uk_platform_account` (`platform`, `account_id`),
  INDEX `idx_platform_status` (`platform`, `connection_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Videos table
CREATE TABLE `videos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `owner_id` INT UNSIGNED NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `storage_path` VARCHAR(500) NOT NULL,
  `thumbnail_path` VARCHAR(500) NULL,
  `mime_type` VARCHAR(100) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL,
  `duration_seconds` DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  `width` INT UNSIGNED NULL,
  `height` INT UNSIGNED NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `default_caption` TEXT NULL,
  `hashtags` VARCHAR(500) NULL,
  `checksum` VARCHAR(64) NULL,
  `video_status` ENUM('draft', 'ready', 'processing', 'published', 'partially_published', 'failed', 'archived') NOT NULL DEFAULT 'draft',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_video_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  INDEX `idx_video_status` (`video_status`),
  INDEX `idx_video_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Publishing Jobs table
CREATE TABLE `publishing_jobs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `video_id` INT UNSIGNED NOT NULL,
  `social_account_id` INT UNSIGNED NOT NULL,
  `platform` ENUM('youtube', 'tiktok', 'instagram') NOT NULL,
  `platform_title` VARCHAR(255) NULL,
  `platform_caption` TEXT NULL,
  `platform_description` TEXT NULL,
  `scheduled_at` DATETIME NULL,
  `status` ENUM('queued', 'validating', 'uploading', 'processing', 'published', 'failed', 'retry_scheduled', 'awaiting_user_action', 'cancelled', 'draft_uploaded') NOT NULL DEFAULT 'queued',
  `platform_media_id` VARCHAR(255) NULL,
  `platform_publish_id` VARCHAR(255) NULL,
  `published_url` VARCHAR(500) NULL,
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
  `next_retry_at` DATETIME NULL,
  `locked_at` DATETIME NULL,
  `locked_by` VARCHAR(100) NULL,
  `last_error_code` VARCHAR(100) NULL,
  `last_error_message` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `published_at` DATETIME NULL,
  CONSTRAINT `fk_job_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_job_account` FOREIGN KEY (`social_account_id`) REFERENCES `social_accounts` (`id`) ON DELETE CASCADE,
  INDEX `idx_job_status_sched` (`status`, `scheduled_at`, `next_retry_at`),
  INDEX `idx_job_platform_status` (`platform`, `status`),
  INDEX `idx_job_video_account` (`video_id`, `social_account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Publishing Attempts table (append-only history)
CREATE TABLE `publishing_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `publishing_job_id` INT UNSIGNED NOT NULL,
  `attempt_number` INT UNSIGNED NOT NULL,
  `started_at` DATETIME NOT NULL,
  `finished_at` DATETIME NULL,
  `outcome` ENUM('success', 'failed', 'partial', 'cancelled', 'processing') NOT NULL,
  `http_status` INT NULL,
  `error_code` VARCHAR(100) NULL,
  `sanitized_response` LONGTEXT NULL,
  `error_message` TEXT NULL,
  CONSTRAINT `fk_attempt_job` FOREIGN KEY (`publishing_job_id`) REFERENCES `publishing_jobs` (`id`) ON DELETE CASCADE,
  INDEX `idx_attempt_job` (`publishing_job_id`),
  INDEX `idx_attempt_outcome` (`outcome`),
  INDEX `idx_attempt_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Logs table
CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(100) NOT NULL,
  `entity_id` VARCHAR(100) NULL,
  `ip_address` VARCHAR(45) NULL,
  `details` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_entity` (`entity_type`, `entity_id`),
  INDEX `idx_audit_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
CREATE TABLE `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` LONGTEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login Attempts table for rate limiting
CREATE TABLE `login_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) NOT NULL DEFAULT 0,
  INDEX `idx_login_rate` (`ip_address`, `email`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('app_name', 'YTClip Multi-Publisher'),
('app_url', 'http://localhost/ytclip'),
('app_timezone', 'UTC'),
('min_video_duration', '30'),
('max_video_duration', '60'),
('max_upload_size_mb', '200'),
('ffprobe_path', 'C:\\xampp\\mysql\\bin\\ffprobe.exe'),
('daily_publishing_limit', '50'),
('mock_mode', '1'),
('youtube_client_id', ''),
('youtube_client_secret', ''),
('tiktok_client_key', ''),
('tiktok_client_secret', ''),
('instagram_app_id', ''),
('instagram_app_secret', ''),
('encryption_key', '');
