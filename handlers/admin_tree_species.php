<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid method']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$action = $_POST['action'] ?? '';

try {
    $db = (new Database())->getConnection();

    if ($action === 'add') {
        $name = trim($_POST['species_name'] ?? '');
        if ($name === '') {
            throw new RuntimeException('Species name is required');
        }
        $sci = trim($_POST['scientific_name'] ?? '') ?: null;
        $common = trim($_POST['common_name'] ?? '') ?: null;
        $stmt = $db->prepare('INSERT INTO tree_species (species_name, scientific_name, common_name) VALUES (?, ?, ?)');
        $stmt->execute([$name, $sci, $common]);
        echo json_encode(['ok' => true, 'message' => 'Species added', 'id' => (int) $db->lastInsertId()]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['species_id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Invalid species');
        }
        $db->prepare('DELETE FROM tree_species WHERE species_id = ?')->execute([$id]);
        echo json_encode(['ok' => true, 'message' => 'Species removed']);
        exit;
    }

    echo json_encode(['ok' => false, 'message' => 'Unknown action']);
} catch (Throwable $e) {
    error_log('admin_tree_species: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
