<?php
require_once __DIR__ . '/../config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SHOW COLUMNS FROM plantations LIKE 'age_of_plantation'");
if ($stmt->rowCount() === 0) {
    $db->exec('ALTER TABLE plantations ADD COLUMN age_of_plantation decimal(5,1) DEFAULT NULL AFTER land_area');
    echo "added age_of_plantation\n";
} else {
    echo "age_of_plantation already exists\n";
}
