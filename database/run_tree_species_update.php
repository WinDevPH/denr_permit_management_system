<?php
header('Content-Type: application/json');

require_once '../config/database.php';

$response = [
    'success' => false,
    'message' => '',
    'steps' => []
];

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Step 1: Create tree_species table
    try {
        $sql = "CREATE TABLE IF NOT EXISTS `tree_species` (
          `species_id` int(11) NOT NULL AUTO_INCREMENT,
          `species_name` varchar(100) NOT NULL,
          `scientific_name` varchar(150) DEFAULT NULL,
          `common_name` varchar(100) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          PRIMARY KEY (`species_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        $db->exec($sql);
        $response['steps'][] = [
            'message' => 'Created tree_species table',
            'status' => true
        ];
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') !== false) {
            $response['steps'][] = [
                'message' => 'tree_species table already exists (skipped)',
                'status' => true
            ];
        } else {
            throw $e;
        }
    }
    
    // Step 2: Check if data already exists
    $checkQuery = "SELECT COUNT(*) as count FROM tree_species";
    $stmt = $db->query($checkQuery);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count == 0) {
        // Step 3: Insert tree species data
        $speciesData = [
            ['Narra', 'Pterocarpus indicus', 'Philippine Mahogany'],
            ['Molave', 'Vitex parviflora', 'Molave'],
            ['Acacia', 'Acacia mangium', 'Acacia'],
            ['Mahogany', 'Swietenia macrophylla', 'Mahogany'],
            ['Ipil-ipil', 'Leucaena leucocephala', 'Ipil-ipil'],
            ['Gmelina', 'Gmelina arborea', 'Gmelina'],
            ['Bamboo', 'Bambusa vulgaris', 'Common Bamboo'],
            ['Yakal', 'Shorea astylosa', 'Yakal'],
            ['Kamagong', 'Diospyros blancoi', 'Velvet Apple'],
            ['Apitong', 'Dipterocarpus grandiflorus', 'Apitong'],
            ['Lauan', 'Shorea contorta', 'White Lauan'],
            ['Teak', 'Tectona grandis', 'Teak'],
            ['Mangium', 'Acacia mangium', 'Black Wattle'],
            ['Rubber Tree', 'Hevea brasiliensis', 'Rubber Tree'],
            ['Falcata', 'Paraserianthes falcataria', 'Falcata'],
            ['Agoho', 'Casuarina equisetifolia', 'Beach She-oak'],
            ['Mango', 'Mangifera indica', 'Mango Tree'],
            ['Coconut', 'Cocos nucifera', 'Coconut Palm'],
            ['Durian', 'Durio zibethinus', 'Durian'],
            ['Rambutan', 'Nephelium lappaceum', 'Rambutan']
        ];
        
        $insertQuery = "INSERT INTO tree_species (species_name, scientific_name, common_name) VALUES (?, ?, ?)";
        $stmt = $db->prepare($insertQuery);
        
        foreach ($speciesData as $species) {
            $stmt->execute($species);
        }
        
        $response['steps'][] = [
            'message' => 'Inserted 20 tree species records',
            'status' => true
        ];
    } else {
        $response['steps'][] = [
            'message' => "Tree species data already exists ($count records found)",
            'status' => true
        ];
    }
    
    // Step 4: Add verification_document column to plantations table
    try {
        // Check if column exists
        $checkColumn = "SHOW COLUMNS FROM plantations LIKE 'verification_document'";
        $stmt = $db->query($checkColumn);
        
        if ($stmt->rowCount() == 0) {
            $sql = "ALTER TABLE `plantations` 
                    ADD COLUMN `verification_document` varchar(255) DEFAULT NULL AFTER `longitude`";
            $db->exec($sql);
            $response['steps'][] = [
                'message' => 'Added verification_document column to plantations table',
                'status' => true
            ];
        } else {
            $response['steps'][] = [
                'message' => 'verification_document column already exists (skipped)',
                'status' => true
            ];
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $response['steps'][] = [
                'message' => 'verification_document column already exists (skipped)',
                'status' => true
            ];
        } else {
            throw $e;
        }
    }
    
    $response['success'] = true;
    $response['message'] = 'Database updated successfully!';
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Database update failed: ' . $e->getMessage();
    $response['steps'][] = [
        'message' => 'Error: ' . $e->getMessage(),
        'status' => false
    ];
}

echo json_encode($response);
