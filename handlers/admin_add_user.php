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
require_once '../config/contact_utils.php';
require_once '../config/notifications.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get form data
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $role = $_POST['role'] ?? 'landowner';
    $password = trim($_POST['password'] ?? '');
    
    // Validation (password set by admin on User Accounts page removed — optional; empty = auto-generate)
    if (empty($full_name) || empty($email) || empty($contact_number)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Name, email, and contact number are required'
        ]);
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid email format'
        ]);
        exit();
    }
    
    $generated_password = null;
    if ($password !== '') {
        if (strlen($password) < 6) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Password must be at least 6 characters long'
            ]);
            exit();
        }
    } else {
        // Readable one-time password for handoff (avoid ambiguous chars)
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $plain = '';
        for ($i = 0; $i < 12; $i++) {
            $plain .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $generated_password = $plain;
        $password = $plain;
    }
    
    $contact_norm = denr_normalize_contact_number($contact_number);
    if ($contact_norm === null) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Contact number must be digits only (7–15 digits). No letters.'
        ]);
        exit();
    }
    $contact_number = $contact_norm;
    
    // Validate role
    $allowed_roles = ['landowner', 'admin', 'verifier'];
    if (!in_array($role, $allowed_roles)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid role selected'
        ]);
        exit();
    }
    
    try {
        // Check if email already exists
        $check_email = $db->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_email->execute([$email]);
        
        if ($check_email->rowCount() > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email address already exists'
            ]);
            exit();
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $insert_user = $db->prepare("
            INSERT INTO users (full_name, email, contact_number, role, password, status, created_at) 
            VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        if ($insert_user->execute([$full_name, $email, $contact_number, $role, $hashed_password])) {
            $new_user_id = (int) $db->lastInsertId();

            denr_notify_user(
                $db,
                $new_user_id,
                'Your DENR account has been created. Sign in with your email to get started.'
            );
            denr_notify_admins(
                $db,
                'New user account created: ' . $full_name . ' (' . ucfirst($role) . ').'
            );

            $payload = [
                'status' => 'success',
                'message' => $generated_password !== null
                    ? 'User added successfully. Copy the one-time password below and share it securely with the user.'
                    : 'User added successfully',
                'user_id' => $new_user_id,
                'user_data' => [
                    'full_name' => $full_name,
                    'email' => $email,
                    'role' => $role,
                    'status' => 'active'
                ]
            ];
            if ($generated_password !== null) {
                $payload['temp_password'] = $generated_password;
            }

            echo json_encode($payload);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to add user. Please try again.'
            ]);
        }
        
    } catch (PDOException $e) {
        // Log error for debugging
        error_log("Add User Error: " . $e->getMessage());
        
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error occurred. Please try again.'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>
