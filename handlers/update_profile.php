<?php
session_start();
require_once '../config/database.php';
require_once '../config/contact_utils.php';
require_once __DIR__ . '/../config/verifier_notify_admins.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Validate input
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);

    if (empty($full_name) || empty($email)) {
        throw new Exception('Name and email are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    $contact_norm = denr_normalize_contact_number($contact_number);
    if ($contact_norm === null) {
        throw new Exception('Contact number must be digits only (7–15 digits). No letters.');
    }
    $contact_number = $contact_norm;

    // Check if email is already taken by another user
    $query = "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        throw new Exception('Email is already taken');
    }

    // Update user profile
    $query = "UPDATE users SET 
              full_name = :full_name,
              email = :email,
              contact_number = :contact_number
              WHERE user_id = :user_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':full_name', $full_name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':contact_number', $contact_number);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);

    if ($stmt->execute()) {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'verifier') {
            denr_notify_admins_verifier_activity($db, 'Updated their profile (name, email, contact number).');
        }
        echo json_encode([
            'status' => 'success',
            'message' => 'Profile updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update profile');
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
