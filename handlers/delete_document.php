<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $doc_id = $data['doc_id'] ?? null;

    if (!$doc_id) {
        throw new Exception('Document ID is required');
    }

    $database = new Database();
    $db = $database->getConnection();

    // First get the file path
    $query = "SELECT file_path FROM documents WHERE doc_id = :doc_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':doc_id', $doc_id);
    $stmt->execute();
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$document) {
        throw new Exception('Document not found');
    }

    // Delete file from storage
    $filepath = '../assets/uploads/documents/' . $document['file_path'];
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    // Delete from database
    $query = "DELETE FROM documents WHERE doc_id = :doc_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':doc_id', $doc_id);

    if (!$stmt->execute()) {
        throw new Exception('Failed to delete document');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Document deleted successfully'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
