<?php
/**
 * Application timeline: dates and activity log (landowner owner, or admin/verifier).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$pid = isset($_GET['plantation_id']) ? (int) $_GET['plantation_id'] : 0;
if ($pid <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid plantation']);
    exit;
}

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare('SELECT p.*, u.full_name AS owner_name FROM plantations p JOIN users u ON p.user_id = u.user_id WHERE p.plantation_id = ?');
    $stmt->execute([$pid]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['ok' => false, 'message' => 'Not found']);
        exit;
    }

    $role = $_SESSION['role'] ?? '';
    $uid = (int) $_SESSION['user_id'];
    if ($role === 'landowner' && (int) $row['user_id'] !== $uid) {
        echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
        exit;
    }
    if (!in_array($role, ['landowner', 'admin', 'verifier'], true)) {
        echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
        exit;
    }

    $logs = [];
    try {
        $lq = $db->prepare('SELECT l.*, u.full_name AS actor_name FROM plantation_activity_log l LEFT JOIN users u ON l.actor_user_id = u.user_id WHERE l.plantation_id = ? ORDER BY l.created_at ASC');
        $lq->execute([$pid]);
        $logs = $lq->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $logs = [];
    }

    echo json_encode([
        'ok' => true,
        'plantation' => [
            'plantation_id' => (int) $row['plantation_id'],
            'plantation_name' => $row['plantation_name'],
            'status' => $row['status'],
            'registered_at' => $row['registered_at'] ?? null,
            'applied_at' => $row['applied_at'] ?? null,
            'approved_at' => $row['approved_at'] ?? null,
        ],
        'logs' => $logs,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('get_plantation_timeline: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Error loading timeline']);
}
