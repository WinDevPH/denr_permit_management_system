<?php
/**
 * System Addition migration runner.
 * Adds: verifier role, plantation lot/specs/landmark, permit verification schedule,
 * permit_trees, chainsaw_registry, verification_assignments.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$response = ['success' => false, 'message' => '', 'steps' => []];

function addStep(&$response, $message, $status = true) {
    $response['steps'][] = ['message' => $message, 'status' => $status];
}

function columnExists($db, $table, $column) {
    $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($column));
    return $stmt->rowCount() > 0;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // 1. Add verifier role to users
    try {
        $db->exec("ALTER TABLE `users` MODIFY COLUMN `role` enum('landowner','officer','admin','verifier') NOT NULL DEFAULT 'landowner'");
        addStep($response, 'Added verifier role to users', true);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'already') !== false) {
            addStep($response, 'Users role already includes verifier', true);
        } else {
            addStep($response, 'Users role update: ' . $e->getMessage(), false);
        }
    }

    // 2. Plantation: district (locality for verifier assignment), lot/specs/landmark, contact fields
    foreach (
        [
            ['district', "ADD COLUMN `district` varchar(120) DEFAULT NULL AFTER `location_address`"],
            ['lot_number', "ADD COLUMN `lot_number` varchar(50) DEFAULT NULL AFTER `district`"],
            ['specifications', "ADD COLUMN `specifications` text DEFAULT NULL AFTER `lot_number`"],
            ['landmark_latitude', "ADD COLUMN `landmark_latitude` decimal(10,6) DEFAULT NULL AFTER `longitude`"],
            ['landmark_longitude', "ADD COLUMN `landmark_longitude` decimal(10,6) DEFAULT NULL AFTER `landmark_latitude`"],
            ['mohon_points_json', "ADD COLUMN `mohon_points_json` text DEFAULT NULL AFTER `landmark_longitude`"],
            ['boundary_geojson', "ADD COLUMN `boundary_geojson` text DEFAULT NULL AFTER `mohon_points_json`"],
            ['contact_person_name', "ADD COLUMN `contact_person_name` varchar(150) DEFAULT NULL AFTER `boundary_geojson`"],
            ['contact_address', "ADD COLUMN `contact_address` text DEFAULT NULL AFTER `contact_person_name`"],
            ['contact_phone', "ADD COLUMN `contact_phone` varchar(80) DEFAULT NULL AFTER `contact_address`"],
            ['age_of_plantation', "ADD COLUMN `age_of_plantation` decimal(5,1) DEFAULT NULL AFTER `land_area`"],
        ] as $col
    ) {
        if (!columnExists($db, 'plantations', $col[0])) {
            $db->exec("ALTER TABLE `plantations` " . $col[1]);
            addStep($response, "Added plantations.{$col[0]}", true);
        }
    }

    // 2b. Plantation timeline dates (add_plantation INSERT uses registered_at, applied_at)
    foreach (
        [
            ['applied_at', "ADD COLUMN `applied_at` datetime DEFAULT NULL AFTER `registered_at`"],
            ['approved_at', "ADD COLUMN `approved_at` datetime DEFAULT NULL AFTER `applied_at`"],
        ] as $col
    ) {
        if (!columnExists($db, 'plantations', $col[0])) {
            $db->exec("ALTER TABLE `plantations` " . $col[1]);
            addStep($response, "Added plantations.{$col[0]}", true);
        }
    }
    try {
        $db->exec("UPDATE `plantations` SET `applied_at` = `registered_at` WHERE `applied_at` IS NULL AND `registered_at` IS NOT NULL");
        addStep($response, 'Backfilled applied_at from registered_at where needed', true);
    } catch (PDOException $e) {
        addStep($response, 'Backfill applied_at: ' . $e->getMessage(), false);
    }

    // 3. Permits: only certificate & cutting (no transport). Migrate legacy transport rows to cutting.
    try {
        $db->exec("UPDATE `permits` SET `permit_type` = 'cutting' WHERE `permit_type` = 'transport'");
        $db->exec("ALTER TABLE `permits` MODIFY COLUMN `permit_type` enum('certificate','cutting') NOT NULL");
        addStep($response, 'Permit type is certificate or cutting only (transport removed)', true);
    } catch (PDOException $e) {
        addStep($response, 'Permit type enum: ' . $e->getMessage(), false);
    }
    foreach (
        [
            ['verification_scheduled_at', "ADD COLUMN `verification_scheduled_at` datetime DEFAULT NULL AFTER `requested_at`"],
            ['verified_by', "ADD COLUMN `verified_by` int(11) DEFAULT NULL AFTER `approved_at`"],
            ['verification_notes', "ADD COLUMN `verification_notes` text DEFAULT NULL AFTER `remarks`"],
        ] as $col
    ) {
        if (!columnExists($db, 'permits', $col[0])) {
            $db->exec("ALTER TABLE `permits` " . $col[1]);
            addStep($response, "Added permits.{$col[0]}", true);
        }
    }

    // 4. permit_trees table
    $db->exec("CREATE TABLE IF NOT EXISTS `permit_trees` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `permit_id` int(11) NOT NULL,
      `tree_species` varchar(100) NOT NULL,
      `quantity` int(11) NOT NULL DEFAULT 0,
      `registry_number` varchar(50) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `permit_id` (`permit_id`),
      CONSTRAINT `permit_trees_ibfk_1` FOREIGN KEY (`permit_id`) REFERENCES `permits` (`permit_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    addStep($response, 'Created permit_trees table (number of trees in details)', true);

    // 5. chainsaw_registry
    $db->exec("CREATE TABLE IF NOT EXISTS `chainsaw_registry` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `registry_number` varchar(50) NOT NULL,
      `brand_model` varchar(100) DEFAULT NULL,
      `serial_number` varchar(100) DEFAULT NULL,
      `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `registry_number` (`registry_number`),
      KEY `user_id` (`user_id`),
      CONSTRAINT `chainsaw_registry_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    addStep($response, 'Created chainsaw_registry table', true);

    // 6. verification_assignments (verifier calendar & assignments)
    $db->exec("CREATE TABLE IF NOT EXISTS `verification_assignments` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `verifier_id` int(11) NOT NULL,
      `plantation_id` int(11) DEFAULT NULL,
      `permit_id` int(11) DEFAULT NULL,
      `scheduled_at` datetime NOT NULL,
      `status` enum('pending','completed','cancelled') DEFAULT 'pending',
      `notes` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `verifier_id` (`verifier_id`),
      KEY `plantation_id` (`plantation_id`),
      KEY `permit_id` (`permit_id`),
      CONSTRAINT `verification_assignments_verifier` FOREIGN KEY (`verifier_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
      CONSTRAINT `verification_assignments_plantation` FOREIGN KEY (`plantation_id`) REFERENCES `plantations` (`plantation_id`) ON DELETE CASCADE,
      CONSTRAINT `verification_assignments_permit` FOREIGN KEY (`permit_id`) REFERENCES `permits` (`permit_id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    addStep($response, 'Created verification_assignments table (verifier calendar)', true);

    $response['success'] = true;
    $response['message'] = 'System Addition migration completed.';
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    addStep($response, 'Error: ' . $e->getMessage(), false);
}

echo json_encode($response);
