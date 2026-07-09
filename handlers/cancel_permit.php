<?php
session_start();
require_once '../config/database.php';
require_once '../config/notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['permit_id'])) {
        throw new Exception('Permit ID is required');
    }

    $database = new Database();
    $db = $database->getConnection();

    // Verify permit ownership and status
    $query = "SELECT p.*, pl.plantation_name FROM permits p 
              JOIN plantations pl ON p.plantation_id = pl.plantation_id 
              WHERE p.permit_id = :permit_id 
              AND pl.user_id = :user_id 
              AND p.status = 'pending'";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':permit_id', $data['permit_id']);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $permit = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$permit) {
        throw new Exception('Permit not found or cannot be canceled');
    }

    // Delete the permit
    $query = "DELETE FROM permits WHERE permit_id = :permit_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':permit_id', $data['permit_id']);

    if ($stmt->execute()) {
        $ptype_label = denr_permit_type_label((string) ($permit['permit_type'] ?? 'cutting'));
        $pname = (string) ($permit['plantation_name'] ?? 'plantation');
        denr_notify_staff(
            $db,
            'Permit request cancelled: ' . $ptype_label . ' for plantation "' . $pname . '".'
        );
        echo json_encode(['status' => 'success', 'message' => 'Permit request canceled successfully']);
    } else {
        throw new Exception('Failed to cancel permit request');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
