-- Add Verifier role and notification time schedule
-- Run this in your denrdb database (e.g. via phpMyAdmin)

-- Add scheduled_time to notifications (for Verifier time schedule in notifications)
-- If column already exists, skip this statement or run once only.
ALTER TABLE `notifications` 
ADD COLUMN `scheduled_time` DATETIME NULL DEFAULT NULL AFTER `created_at`;

-- Add 'verifier' to users.role enum (MariaDB/MySQL)
ALTER TABLE `users` 
MODIFY COLUMN `role` ENUM('landowner','officer','admin','verifier') NOT NULL DEFAULT 'landowner';
