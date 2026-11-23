-- ==============================================================================
-- WFF TAMIL NADU — CONSOLIDATED PRODUCTION DATABASE SCHEMA
-- Compatible with MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- Character set: utf8mb4, Collation: utf8mb4_unicode_ci
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Events Table
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `event_name` VARCHAR(255) NOT NULL,
  `event_date` DATE NOT NULL,
  `venue` VARCHAR(255) NOT NULL,
  `tan_spray_price` INT DEFAULT 0,
  `banner_image` VARCHAR(255) NOT NULL,
  `status` ENUM('upcoming', 'ongoing', 'completed', 'cancelled', 'draft') NOT NULL DEFAULT 'draft',
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Event Categories
CREATE TABLE IF NOT EXISTS `event_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `event_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `age_group` VARCHAR(100),
  `gender` VARCHAR(50),
  `entry_fee` VARCHAR(100),
  `availability` ENUM('open', 'closed', 'waitlist') NOT NULL DEFAULT 'open',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Registrations Table
CREATE TABLE IF NOT EXISTS `registrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_number` VARCHAR(50) NOT NULL UNIQUE,
  `event_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  
  `athlete_name` VARCHAR(255) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `age` INT NULL,
  `gender` VARCHAR(50) NOT NULL,
  `height` DECIMAL(5,2) NULL,
  `weight` DECIMAL(5,2) NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(255),
  `instagram_id` VARCHAR(255) NULL,
  
  `address` TEXT,
  `city` VARCHAR(100),
  `state` VARCHAR(100),
  
  `emergency_contact_name` VARCHAR(255),
  `emergency_contact_phone` VARCHAR(20),
  
  `tan_spray_requested` TINYINT(1) DEFAULT 0,
  `tan_spray_fee` INT DEFAULT 0,
  `base_total` INT DEFAULT 0,
  `additional_category_discount` INT DEFAULT 0,
  `total_amount` INT NULL,
  
  `status` ENUM('pending', 'payment_pending', 'paid', 'confirmed', 'cancelled', 'rejected') NOT NULL DEFAULT 'payment_pending',
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX (`phone`),
  INDEX (`email`),
  INDEX (`status`),
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`category_id`) REFERENCES `event_categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Multi-Category Enrollment
CREATE TABLE IF NOT EXISTS `registration_categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `base_fee` INT NOT NULL,
  `discount` INT DEFAULT 0,
  `payable` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`registration_id`) REFERENCES `registrations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `event_categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Payments Table
CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT NOT NULL,
  `razorpay_order_id` VARCHAR(100) NULL,
  `razorpay_payment_id` VARCHAR(100) NULL,
  `razorpay_signature` VARCHAR(255) NULL,
  `amount` BIGINT UNSIGNED NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
  `status` ENUM('created', 'authorized', 'captured', 'failed', 'refunded') NOT NULL DEFAULT 'created',
  `method` VARCHAR(50) DEFAULT 'upi_qr',
  `error_code` VARCHAR(100) NULL,
  `error_description` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`razorpay_order_id`),
  INDEX (`razorpay_payment_id`),
  FOREIGN KEY (`registration_id`) REFERENCES `registrations`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Administrators Table
CREATE TABLE IF NOT EXISTS `admins` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(50) DEFAULT 'admin',
  `is_active` BOOLEAN DEFAULT TRUE,
  `last_login_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Login Attempts Rate-Limiting Table
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ip_address` VARCHAR(45) NOT NULL,
  `email_attempt` VARCHAR(190) NOT NULL,
  `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`ip_address`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Admin Audit Logs
CREATE TABLE IF NOT EXISTS `admin_audit_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `entity_id` VARCHAR(100) NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` TEXT NULL,
  `details` JSON NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Razorpay Webhook Logs
CREATE TABLE IF NOT EXISTS `webhook_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `webhook_id` VARCHAR(100) NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `payload` JSON NULL,
  `status` VARCHAR(50) DEFAULT 'processed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Officials Table
CREATE TABLE IF NOT EXISTS `officials` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `role` VARCHAR(255) NOT NULL,
  `designation` VARCHAR(255) NOT NULL,
  `photo` VARCHAR(255) NOT NULL,
  `bio` TEXT,
  `sort_order` INT DEFAULT 0,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Champions Table
CREATE TABLE IF NOT EXISTS `champions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `athlete_name` VARCHAR(255) NOT NULL,
  `title_won` VARCHAR(255) NOT NULL,
  `event_name` VARCHAR(255) NOT NULL,
  `event_id` INT NULL,
  `category` VARCHAR(255),
  `photo` VARCHAR(255) NOT NULL,
  `year` VARCHAR(4),
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Gallery Albums & Images
CREATE TABLE IF NOT EXISTS `gallery_albums` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `event_id` INT NULL,
  `date` DATE,
  `cover_image` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gallery_images` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `album_id` INT NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `caption` VARCHAR(255),
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`album_id`) REFERENCES `gallery_albums`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Hero Slides & Announcements
CREATE TABLE IF NOT EXISTS `hero_slides` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `subtitle` VARCHAR(255) NOT NULL,
  `image` VARCHAR(255) NOT NULL,
  `date` VARCHAR(100),
  `location` VARCHAR(255),
  `event_id` INT NULL,
  `sort_order` INT DEFAULT 0,
  `status` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `message` VARCHAR(255) NOT NULL,
  `link_text` VARCHAR(100),
  `link_url` VARCHAR(255),
  `status` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `start_at` DATETIME NULL,
  `end_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Homepage Dynamic Sections & Site Settings
CREATE TABLE IF NOT EXISTS `homepage_sections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `section_key` VARCHAR(100) NOT NULL UNIQUE,
  `title` VARCHAR(255),
  `subtitle` VARCHAR(255),
  `content` TEXT,
  `status` TINYINT(1) DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
