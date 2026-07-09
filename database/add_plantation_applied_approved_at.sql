-- Timeline fields used by handlers/add_plantation.php (NOW(), NOW()) and status workflow.
-- Run once. Duplicate column errors can be ignored for lines already applied.
-- Preferred: database/run_system_addition_update.php (includes these + backfill).

ALTER TABLE `plantations` ADD COLUMN `applied_at` datetime DEFAULT NULL AFTER `registered_at`;
UPDATE `plantations` SET `applied_at` = `registered_at` WHERE `applied_at` IS NULL AND `registered_at` IS NOT NULL;
ALTER TABLE `plantations` ADD COLUMN `approved_at` datetime DEFAULT NULL AFTER `applied_at`;
