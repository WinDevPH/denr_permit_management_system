<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/contact_utils.php';
require_once __DIR__ . '/../config/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$full_name = trim((string) ($_POST['full_name'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$contact_number = trim((string) ($_POST['contact_number'] ?? ''));
$plain_password = (string) ($_POST['password'] ?? '');
$confirm_password = (string) ($_POST['confirm_password'] ?? '');

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

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

if (!$db instanceof PDO) {
    error_log('register.php: database connection failed');
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Please ensure MySQL is running and try again.']);
    exit;
}

$password = password_hash($plain_password, PASSWORD_DEFAULT);
if ($password === false) {
    echo json_encode(['status' => 'error', 'message' => 'Could not secure password. Please try again.']);
    exit;
}

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
} catch (Throwable $e) {
    error_log('register.php: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Registration failed. Please try again.']);
}
