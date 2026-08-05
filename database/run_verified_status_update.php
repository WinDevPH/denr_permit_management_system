<?php
/**
 * Add plantations.status value "verified" (site visit done, not yet officially registered).
 * Flow: pending → validated (Checked) → verified (by verifier) → registered (registration success) | rejected
 */
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        throw new Exception('Database connection failed');
    }

    $results = [];

    try {
        $db->exec("ALTER TABLE plantations MODIFY COLUMN status ENUM('pending','validated','verified','registered','rejected') DEFAULT 'pending'");
        $results[] = 'OK: plantations.status includes verified';
    } catch (PDOException $e) {
        $results[] = 'SKIP/ERR status enum: ' . $e->getMessage();
    }

    // Cutting permit detail columns (optional extras on permits)
    $permitCols = $db->query('SHOW COLUMNS FROM permits')->fetchAll(PDO::FETCH_COLUMN);
    $addPermit = [
        'applicant_name' => "ADD COLUMN `applicant_name` VARCHAR(150) DEFAULT NULL",
        'contact_number' => "ADD COLUMN `contact_number` VARCHAR(50) DEFAULT NULL",
        'property_location' => "ADD COLUMN `property_location` TEXT DEFAULT NULL",
        'proof_of_ownership' => "ADD COLUMN `proof_of_ownership` VARCHAR(255) DEFAULT NULL",
        'cutting_land_area' => "ADD COLUMN `cutting_land_area` DECIMAL(10,2) DEFAULT NULL",
        'cutting_tree_species' => "ADD COLUMN `cutting_tree_species` VARCHAR(255) DEFAULT NULL",
        'trees_to_cut' => "ADD COLUMN `trees_to_cut` INT DEFAULT NULL",
        'reason_for_cutting' => "ADD COLUMN `reason_for_cutting` TEXT DEFAULT NULL",
        'intended_use' => "ADD COLUMN `intended_use` TEXT DEFAULT NULL",
        'supporting_docs_json' => "ADD COLUMN `supporting_docs_json` TEXT DEFAULT NULL",
    ];
    foreach ($addPermit as $col => $ddl) {
        if (in_array($col, $permitCols, true)) {
            $results[] = "SKIP: permits.{$col} already exists";
            continue;
        }
        try {
            $db->exec("ALTER TABLE permits {$ddl}");
            $results[] = "OK: added permits.{$col}";
        } catch (PDOException $e) {
            $results[] = "ERR permits.{$col}: " . $e->getMessage();
        }
    }

    echo "DENR verified status + cutting permit fields update\n";
    echo implode("\n", $results) . "\n";
    echo "Done.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage() . "\n";
}
