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
    $user_id = intval($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $district = trim($_POST['district'] ?? '');
    if ($district === '') {
        $district = null;
    }
    $role = $_POST['role'] ?? '';
    $status = $_POST['status'] ?? 'active';
    $password = trim($_POST['password'] ?? '');
    
    // Validation
    if ($user_id <= 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid user ID'
        ]);
        exit();
    }
    
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
    
    $contact_norm = denr_normalize_contact_number($contact_number);
    if ($contact_norm === null) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Contact number must be digits only (7–15 digits). No letters.'
        ]);
        exit();
    }
    $contact_number = $contact_norm;
    
    // Validate role (must match Add User and UI: landowner, admin, verifier)
    $allowed_roles = ['landowner', 'admin', 'verifier'];
    if (!in_array($role, $allowed_roles)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid role selected'
        ]);
        exit();
    }
    
    // Validate status
    $allowed_statuses = ['active', 'inactive'];
    if (!in_array($status, $allowed_statuses)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid status selected'
        ]);
        exit();
    }
    
    try {
        // Check if user exists
        $check_user = $db->prepare("SELECT user_id, email, role, status FROM users WHERE user_id = ?");
        $check_user->execute([$user_id]);
        
        if ($check_user->rowCount() === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'User not found'
            ]);
            exit();
        }
        
        $existing_user = $check_user->fetch(PDO::FETCH_ASSOC);
        $old_role = (string) ($existing_user['role'] ?? '');
        $old_status = (string) ($existing_user['status'] ?? 'active');
        
        // Check if email already exists for another user
        $check_email = $db->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $check_email->execute([$email, $user_id]);
        
        if ($check_email->rowCount() > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email address already exists for another user'
            ]);
            exit();
        }
        
        // Build update query
        if (!empty($password)) {
            // Validate password if provided
            if (strlen($password) < 6) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Password must be at least 6 characters long'
                ]);
                exit();
            }
            
            // Update with password (users table may not have updated_at column)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET full_name = ?, email = ?, contact_number = ?, district = ?, role = ?, status = ?, password = ? WHERE user_id = ?";
            $params = [$full_name, $email, $contact_number, $district, $role, $status, $hashed_password, $user_id];
        } else {
            // Update without password
            $update_query = "UPDATE users SET full_name = ?, email = ?, contact_number = ?, district = ?, role = ?, status = ? WHERE user_id = ?";
            $params = [$full_name, $email, $contact_number, $district, $role, $status, $user_id];
        }
        
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute($params)) {
            if ($old_role !== $role) {
                denr_notify_user(
                    $db,
                    $user_id,
                    'Your DENR account role has been updated to ' . ucfirst($role) . '.'
                );
            }
            if ($old_status !== $status) {
                denr_notify_user(
                    $db,
                    $user_id,
                    'Your DENR account status is now ' . $status . '.'
                );
            }
            if (!empty($password)) {
                denr_notify_user($db, $user_id, 'Your DENR account password was reset by an administrator.');
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'User updated successfully',
                'user_data' => [
                    'user_id' => $user_id,
                    'full_name' => $full_name,
                    'email' => $email,
                    'contact_number' => $contact_number,
                    'role' => $role,
                    'status' => $status
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to update user. Please try again.'
            ]);
        }
        
    } catch (PDOException $e) {
        // Log error for debugging
        error_log("Update User Error: " . $e->getMessage());
        
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
