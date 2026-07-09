-- Multiple Mohon (boundary) points as JSON array: [{"lat":7.1,"lng":122.0},...]
-- Run via database/run_system_addition_update.php or manually.
ALTER TABLE `plantations` ADD COLUMN `mohon_points_json` text DEFAULT NULL AFTER `landmark_longitude`;
