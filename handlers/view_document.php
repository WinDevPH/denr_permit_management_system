<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$file = $_GET['file'] ?? '';
$name = $_GET['name'] ?? 'document';

if (empty($file)) {
    die('No file specified');
}

$filepath = '../assets/uploads/documents/' . $file;

if (!file_exists($filepath)) {
    die('File not found');
}

// Get file extension
$extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

// Set appropriate headers based on file type
switch ($extension) {
    case 'pdf':
        header('Content-Type: application/pdf');
        break;
    case 'doc':
        header('Content-Type: application/msword');
        break;
    case 'docx':
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        break;
    default:
        die('Unsupported file type');
}

header('Content-Disposition: inline; filename="' . $name . '.' . $extension . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filepath);
