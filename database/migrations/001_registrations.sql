-- Migration: Create registrations table
-- Safe to run on existing database

CREATE TABLE IF NOT EXISTS `registrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `registration_number` VARCHAR(50) NOT NULL UNIQUE,
  `event_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  
  `athlete_name` VARCHAR(255) NOT NULL,
  `date_of_birth` DATE NOT NULL,
  `gender` VARCHAR(50) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `email` VARCHAR(255),
  
  `address` TEXT,
  `city` VARCHAR(100),
  `state` VARCHAR(100),
  
  `emergency_contact_name` VARCHAR(255),
  `emergency_contact_phone` VARCHAR(20),
  
  `status` ENUM('pending', 'payment_pending', 'paid', 'confirmed', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending',
  
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`category_id`) REFERENCES `event_categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
