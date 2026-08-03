<?php
/**
 * DENR recommendations update:
 * - plantations.status includes rejected
 * - rejection_reason, tax_declaration_path, site_photo_path columns
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
        $db->exec("ALTER TABLE plantations MODIFY COLUMN status ENUM('pending','validated','registered','rejected') DEFAULT 'pending'");
        $results[] = 'OK: plantations.status includes rejected';
    } catch (PDOException $e) {
        $results[] = 'SKIP/ERR status enum: ' . $e->getMessage();
    }

    $cols = $db->query('SHOW COLUMNS FROM plantations')->fetchAll(PDO::FETCH_COLUMN);
    $add = [
        'rejection_reason' => "ADD COLUMN `rejection_reason` VARCHAR(255) DEFAULT NULL AFTER `status`",
        'tax_declaration_path' => "ADD COLUMN `tax_declaration_path` VARCHAR(255) DEFAULT NULL AFTER `verification_document`",
        'site_photo_path' => "ADD COLUMN `site_photo_path` VARCHAR(255) DEFAULT NULL AFTER `tax_declaration_path`",
    ];
    foreach ($add as $col => $ddl) {
        if (in_array($col, $cols, true)) {
            $results[] = "SKIP: column {$col} already exists";
            continue;
        }
        try {
            $db->exec("ALTER TABLE plantations {$ddl}");
            $results[] = "OK: added plantations.{$col}";
        } catch (PDOException $e) {
            $results[] = "ERR {$col}: " . $e->getMessage();
        }
    }

    echo "DENR recommendations DB update\n";
    echo implode("\n", $results) . "\n";
    echo "Done.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . $e->getMessage() . "\n";
}
