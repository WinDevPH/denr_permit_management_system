-- Fix: Unknown column 'tax_declaration_path' when adding plantations
-- Run in phpMyAdmin on InfinityFree (ignore "Duplicate column" errors if a column already exists).

ALTER TABLE `plantations` ADD COLUMN `tax_declaration_path` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `plantations` ADD COLUMN `site_photo_path` VARCHAR(255) DEFAULT NULL;
ALTER TABLE `plantations` ADD COLUMN `rejection_reason` VARCHAR(255) DEFAULT NULL;
