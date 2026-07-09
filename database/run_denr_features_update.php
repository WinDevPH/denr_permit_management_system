<?php
/**
 * DENR feature pack: shapefile boundary storage, activity logs, districts, document categories, timeline dates.
 * Open once in browser: /denr/database/run_denr_features_update.php
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';

$response = ['success' => false, 'message' => '', 'steps' => []];

function step(array &$response, string $message, bool $ok = true): void {
    $response['steps'][] = ['message' => $message, 'ok' => $ok];
}

function columnExists(PDO $db, string $table, string $column): bool {
    $stmt = $db->query("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE " . $db->quote($column));
    return $stmt && $stmt->rowCount() > 0;
}

function tableExists(PDO $db, string $table): bool {
    $t = str_replace('`', '', $table);
    $stmt = $db->query("SHOW TABLES LIKE " . $db->quote($t));
    return $stmt && $stmt->rowCount() > 0;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db instanceof PDO) {
        throw new RuntimeException('Database connection failed. Start MySQL and check config/database.php.');
    }

    if (!columnExists($db, 'plantations', 'boundary_geojson')) {
        $db->exec("ALTER TABLE `plantations` ADD COLUMN `boundary_geojson` LONGTEXT NULL DEFAULT NULL AFTER `mohon_points_json`");
        step($response, 'Added plantations.boundary_geojson');
    } else {
        step($response, 'plantations.boundary_geojson already present');
    }

    if (!columnExists($db, 'plantations', 'district')) {
        $db->exec("ALTER TABLE `plantations` ADD COLUMN `district` varchar(120) DEFAULT NULL AFTER `location_address`");
        step($response, 'Added plantations.district');
    } else {
        step($response, 'plantations.district already present');
    }

    if (!columnExists($db, 'plantations', 'applied_at')) {
        $db->exec("ALTER TABLE `plantations` ADD COLUMN `applied_at` datetime DEFAULT NULL AFTER `registered_at`");
        step($response, 'Added plantations.applied_at');
        $db->exec("UPDATE `plantations` SET `applied_at` = `registered_at` WHERE `applied_at` IS NULL AND `registered_at` IS NOT NULL");
        step($response, 'Backfilled applied_at from registered_at');
    } else {
        step($response, 'plantations.applied_at already present');
    }

    if (!columnExists($db, 'plantations', 'approved_at')) {
        $db->exec("ALTER TABLE `plantations` ADD COLUMN `approved_at` datetime DEFAULT NULL AFTER `applied_at`");
        step($response, 'Added plantations.approved_at');
    } else {
        step($response, 'plantations.approved_at already present');
    }

    if (!columnExists($db, 'users', 'district')) {
        $db->exec("ALTER TABLE `users` ADD COLUMN `district` varchar(120) DEFAULT NULL AFTER `contact_number`");
        step($response, 'Added users.district (verifier / assignment filter)');
    } else {
        step($response, 'users.district already present');
    }

    if (!tableExists($db, 'plantation_activity_log')) {
        $db->exec("CREATE TABLE `plantation_activity_log` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `plantation_id` int(11) NOT NULL,
          `actor_user_id` int(11) DEFAULT NULL,
          `action` varchar(80) NOT NULL DEFAULT 'status_change',
          `old_status` varchar(40) DEFAULT NULL,
          `new_status` varchar(40) DEFAULT NULL,
          `detail` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `plantation_id` (`plantation_id`),
          KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
        step($response, 'Created plantation_activity_log');
    } else {
        step($response, 'plantation_activity_log already exists');
    }

    if (tableExists($db, 'documents') && !columnExists($db, 'documents', 'document_category')) {
        $after = columnExists($db, 'documents', 'document_name') ? 'document_name' : 'plantation_id';
        $db->exec("ALTER TABLE `documents` ADD COLUMN `document_category` varchar(50) DEFAULT NULL AFTER `" . str_replace('`', '', $after) . "`");
        step($response, 'Added documents.document_category');
    } else {
        step($response, 'documents.document_category skipped or already present');
    }

    $response['success'] = true;
    $response['message'] = 'DENR features database update finished.';
} catch (Throwable $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    step($response, 'Error: ' . $e->getMessage(), false);
}

echo json_encode($response, JSON_PRETTY_PRINT);
