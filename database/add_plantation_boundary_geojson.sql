-- Legacy polygon/shapefile boundary storage (cleared on save; Mohon-only workflow still uses mohon_points_json).
-- Run once. Duplicate column errors can be ignored if already applied.
-- Preferred: run database/run_system_addition_update.php (includes this column).

ALTER TABLE `plantations` ADD COLUMN `boundary_geojson` text DEFAULT NULL AFTER `mohon_points_json`;
