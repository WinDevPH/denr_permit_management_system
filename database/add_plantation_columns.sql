-- Add missing columns to plantations (lot_number, specifications, landmark coordinates).
-- Run this once. If a column already exists, you will get "Duplicate column name" for that line; you can ignore it or run the migration via PHP instead: database/run_system_addition_update.php

ALTER TABLE `plantations` ADD COLUMN `lot_number` varchar(50) DEFAULT NULL AFTER `location_address`;
ALTER TABLE `plantations` ADD COLUMN `specifications` text DEFAULT NULL AFTER `lot_number`;
ALTER TABLE `plantations` ADD COLUMN `landmark_latitude` decimal(10,6) DEFAULT NULL AFTER `longitude`;
ALTER TABLE `plantations` ADD COLUMN `landmark_longitude` decimal(10,6) DEFAULT NULL AFTER `landmark_latitude`;
