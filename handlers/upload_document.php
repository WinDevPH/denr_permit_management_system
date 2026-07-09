<?php
session_start();
require_once '../config/database.php';
require_once '../config/notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

try {
    if (!isset($_FILES['document']) || !isset($_POST['plantation_id'])) {
        throw new Exception('Missing required fields');
    }

    $file = $_FILES['document'];
    $plantation_id = (int) $_POST['plantation_id'];
    $document_name = isset($_POST['document_name']) ? trim($_POST['document_name']) : '';
    $document_category = isset($_POST['document_category']) ? trim($_POST['document_category']) : '';

    if ($document_name === '') {
        throw new Exception('Document name is required');
    }

    $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    if (!in_array($file['type'], $allowed_types, true)) {
        throw new Exception('Invalid file type. Only PDF and DOC files are allowed');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size must be less than 5MB');
    }

    $database = new Database();
    $db = $database->getConnection();

    $own = $db->prepare('SELECT p.plantation_id, p.plantation_name FROM plantations p WHERE p.plantation_id = ? AND p.user_id = ? AND p.status = ?');
    $own->execute([$plantation_id, $_SESSION['user_id'], 'registered']);
    $plantation = $own->fetch(PDO::FETCH_ASSOC);
    if (!$plantation) {
        throw new Exception('Plantation not found or not eligible for document upload');
    }

    $upload_dir = '../assets/uploads/documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'doc_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Error uploading file');
    }

    $relative = 'assets/uploads/documents/' . $filename;

    $allowed_cat = ['', 'land_title', 'moa', 'permit', 'certificate', 'other'];
    if (!in_array($document_category, $allowed_cat, true)) {
        $document_category = 'other';
    }
    if ($document_category === '') {
        $document_category = 'other';
    }

    $cols = $db->query('SHOW COLUMNS FROM documents')->fetchAll(PDO::FETCH_COLUMN);
    $has_cat = in_array('document_category', $cols, true);

    if ($has_cat) {
        $query = 'INSERT INTO documents (plantation_id, document_name, document_category, file_name, file_path) VALUES (?, ?, ?, ?, ?)';
        $stmt = $db->prepare($query);
        $ok = $stmt->execute([$plantation_id, $document_name, $document_category, $file['name'], $relative]);
    } else {
        $query = 'INSERT INTO documents (plantation_id, document_name, file_name, file_path) VALUES (?, ?, ?, ?)';
        $stmt = $db->prepare($query);
        $ok = $stmt->execute([$plantation_id, $document_name, $file['name'], $relative]);
    }

    if (!$ok) {
        @unlink($filepath);
        throw new Exception('Error saving document information');
    }

    denr_notify_admins(
        $db,
        'New document uploaded for plantation "' . ($plantation['plantation_name'] ?? 'plantation') . '": ' . $document_name . '.'
    );

    echo json_encode([
        'status' => 'success',
        'message' => 'Document uploaded successfully',
        'filename' => $filename,
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
