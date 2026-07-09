<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/../config/verifier_notify_admins.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

try {
    if (!isset($_FILES['profile_image'])) {
        throw new Exception('No image uploaded');
    }

    $file = $_FILES['profile_image'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];

    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid file type. Only JPG, JPEG & PNG allowed');
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = '../assets/uploads/profiles/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Error uploading file');
    }

    // Update database
    $database = new Database();
    $db = $database->getConnection();

    // Delete old profile image if exists
    $query = "SELECT profile_img FROM users WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $old_image = $stmt->fetchColumn();

    if ($old_image && $old_image !== 'default.png') {
        $old_filepath = $upload_dir . $old_image;
        if (file_exists($old_filepath)) {
            unlink($old_filepath);
        }
    }

    // Update user profile image in database
    $query = "UPDATE users SET profile_img = :profile_img WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':profile_img', $filename);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);

    if (!$stmt->execute()) {
        throw new Exception('Error updating database');
    }

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'verifier') {
        denr_notify_admins_verifier_activity($db, 'Updated their profile photo.');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Profile image updated successfully',
        'filename' => $filename
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
