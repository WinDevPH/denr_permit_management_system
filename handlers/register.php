<?php
header('Content-Type: application/json; charset=utf-8');

require_once '../config/database.php';
require_once '../config/contact_utils.php';
require_once '../config/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact_number = trim($_POST['contact_number'] ?? '');
$plain_password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if ($full_name === '' || $email === '' || $contact_number === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields']);
    exit;
}

$contact_norm = denr_normalize_contact_number($contact_number);
if ($contact_norm === null) {
    echo json_encode(['status' => 'error', 'message' => 'Contact number must contain digits only (7–15 digits). No letters.']);
    exit;
}
$contact_number = $contact_norm;

if ($plain_password !== $confirm_password) {
    echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
    exit;
}

if (strlen($plain_password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters long']);
    exit;
}

if (strlen($plain_password) > 128) {
    echo json_encode(['status' => 'error', 'message' => 'Password is too long']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$password = password_hash($plain_password, PASSWORD_DEFAULT);
$role = 'landowner';

try {
    $check_stmt = $db->prepare('SELECT email FROM users WHERE email = ?');
    $check_stmt->execute([$email]);

    if ($check_stmt->rowCount() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
        exit;
    }

    $stmt = $db->prepare('INSERT INTO users (full_name, email, password, role, contact_number) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$full_name, $email, $password, $role, $contact_number]);

    denr_notify_admins(
        $db,
        'New landowner registration: ' . $full_name . ' (' . $email . ').'
    );

    echo json_encode(['status' => 'success', 'message' => 'Registration successful']);
} catch (PDOException $e) {
    error_log('register.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again.']);
}
