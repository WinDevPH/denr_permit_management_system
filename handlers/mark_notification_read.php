<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$notif_id = $data['notif_id'] ?? null;

if (!$notif_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid notification']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "UPDATE notifications 
              SET is_read = 1 
              WHERE notif_id = :notif_id 
              AND user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':notif_id' => $notif_id,
        ':user_id' => $_SESSION['user_id']
    ]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
