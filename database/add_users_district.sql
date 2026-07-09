-- Verifier assignment / filter (admin users page, edit modal)
-- Run once on your denrdb database (phpMyAdmin or: mysql -u root denrdb < add_users_district.sql)
-- If `district` already exists, skip or comment out this file.

ALTER TABLE `users`
  ADD COLUMN `district` VARCHAR(120) NULL DEFAULT NULL AFTER `contact_number`;
