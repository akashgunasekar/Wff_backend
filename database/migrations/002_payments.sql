-- Migration: Create payments table
-- Safe to run on existing database

CREATE TABLE IF NOT EXISTS `payments` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `registration_id` INT NOT NULL,
  
  `razorpay_order_id` VARCHAR(100) UNIQUE,
  `razorpay_payment_id` VARCHAR(100) UNIQUE,
  `razorpay_signature` VARCHAR(255),
  
  `amount` BIGINT UNSIGNED NOT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
  `status` ENUM('created', 'authorized', 'captured', 'failed', 'refunded') NOT NULL DEFAULT 'created',
  `method` VARCHAR(50),
  
  `error_code` VARCHAR(100),
  `error_description` TEXT,
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`registration_id`) REFERENCES `registrations`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
