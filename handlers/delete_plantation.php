<?php
session_start();
require_once '../config/database.php';
require_once '../config/notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $plantation_id = $data['plantation_id'];
    $user_id = $_SESSION['user_id'];

    $database = new Database();
    $db = $database->getConnection();

    // Check if plantation exists and belongs to user
    $check_query = "SELECT status, plantation_name FROM plantations WHERE plantation_id = :plantation_id AND user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':plantation_id', $plantation_id);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    $plantation = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plantation) {
        throw new Exception('Plantation not found');
    }

    if ($plantation['status'] !== 'pending') {
        throw new Exception('Only pending plantations can be deleted');
    }

    // Delete the plantation
    $delete_query = "DELETE FROM plantations WHERE plantation_id = :plantation_id AND user_id = :user_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':plantation_id', $plantation_id);
    $delete_stmt->bindParam(':user_id', $user_id);

    if ($delete_stmt->execute()) {
        denr_notify_admins(
            $db,
            'Plantation application withdrawn: "' . ($plantation['plantation_name'] ?? 'plantation') . '" was removed by the landowner.'
        );
        echo json_encode(['status' => 'success', 'message' => 'Plantation deleted successfully']);
    } else {
        throw new Exception('Failed to delete plantation');
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
