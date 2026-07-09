<?php
session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access'
    ]);
    exit();
}

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['user_id'])) {
    $database = new Database();
    $db = $database->getConnection();
    
    $user_id = intval($_GET['user_id']);
    
    if ($user_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid user ID'
        ]);
        exit();
    }
    
    try {
        $stmt = $db->prepare("SELECT user_id, full_name, email, contact_number, district, role, status FROM users WHERE user_id = ?");
        try {
            $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'district') !== false) {
                $stmt = $db->prepare("SELECT user_id, full_name, email, contact_number, role, status FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
            } else {
                throw $e;
            }
        }
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!isset($user['district'])) {
                $user['district'] = '';
            }
            echo json_encode([
                'status' => 'success',
                'user' => $user
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'User not found'
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Get User Error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error occurred'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request'
    ]);
}
?>
