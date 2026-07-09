<?php
session_start();
require_once '../config/database.php';
require_once '../config/notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Validate required fields
    if (empty($_POST['plantation_id'])) {
        throw new Exception('Please select a plantation');
    }

    // Verify plantation ownership - Fixed query
    $query = "SELECT p.*, u.user_id 
              FROM plantations p 
              JOIN users u ON p.user_id = u.user_id 
              WHERE p.plantation_id = :plantation_id 
              AND p.user_id = :user_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':plantation_id', $_POST['plantation_id']);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $plantation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plantation) {
        throw new Exception('Plantation not found or you do not have permission');
    }

    if ($plantation['status'] !== 'registered') {
        throw new Exception('Plantation must be registered before requesting permits');
    }

    // Check for existing pending permits
    $query = "SELECT permit_id FROM permits 
              WHERE plantation_id = :plantation_id 
              AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':plantation_id', $_POST['plantation_id']);
    $stmt->execute();

    if ($stmt->fetch()) {
        throw new Exception('There is already a pending permit request for this plantation');
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        // Insert permit request
        $query = "INSERT INTO permits (plantation_id, permit_type, remarks) 
                  VALUES (:plantation_id, :permit_type, :remarks)";
        $stmt = $db->prepare($query);

        $permit_type = in_array($_POST['permit_type'] ?? '', ['certificate', 'cutting'], true) ? $_POST['permit_type'] : 'cutting';
        $stmt->bindParam(':plantation_id', $_POST['plantation_id']);
        $stmt->bindValue(':permit_type', $permit_type);
        $stmt->bindParam(':remarks', $_POST['remarks']);

        if (!$stmt->execute()) {
            throw new Exception('Failed to submit permit request');
        }

        $permit_id = (int) $db->lastInsertId();
        $ptype_label = denr_permit_type_label($permit_type);
        $owner_label = isset($_SESSION['full_name']) ? trim((string) $_SESSION['full_name']) : 'Landowner';

        denr_notify_staff(
            $db,
            'New permit request pending: ' . $ptype_label . ' for plantation "' . $plantation['plantation_name'] . '" by ' . $owner_label . '.'
        );
        denr_notify_user(
            $db,
            (int) $_SESSION['user_id'],
            'Your ' . strtolower($ptype_label) . ' request for plantation "' . $plantation['plantation_name'] . '" has been submitted and is pending review.'
        );

        // Log the action
        $action = "Submitted " . $permit_type . " permit request for plantation ID: " . $_POST['plantation_id'];
        $query = "INSERT INTO audit_logs (user_id, action, module) VALUES (:user_id, :action, 'permits')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':action', $action);
        $stmt->execute();

        // Commit transaction
        $db->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Permit request submitted successfully'
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
