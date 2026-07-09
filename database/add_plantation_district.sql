-- Optional district/locality for plantation (landowner form + verifier assignment).
-- Run once. Duplicate column errors can be ignored if already applied.
-- Or run: database/run_system_addition_update.php (includes this column).

ALTER TABLE `plantations` ADD COLUMN `district` varchar(120) DEFAULT NULL AFTER `location_address`;
