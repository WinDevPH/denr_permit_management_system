<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once __DIR__ . '/../config/verifier_notify_admins.php';
require_once __DIR__ . '/../config/notifications.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$other_user_id = $_POST['other_user_id'] ?? null;

if (!$other_user_id) {
    echo json_encode(['success' => false, 'message' => 'Missing user ID']);
    exit();
}

/**
 * Who may start a DM: admin with anyone; verifier with admin/landowner; landowner with admin/verifier; others unchanged.
 */
function messaging_pair_allowed(string $myRole, string $otherRole): bool
{
    if ($myRole === 'admin') {
        return true;
    }
    if ($myRole === 'verifier') {
        return $otherRole === 'admin' || $otherRole === 'landowner';
    }
    if ($myRole === 'landowner') {
        return $otherRole === 'admin' || $otherRole === 'verifier';
    }
    if ($myRole === 'officer') {
        return true;
    }
    return false;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if ((int) $other_user_id === (int) $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Invalid recipient']);
        exit();
    }

    $check_query = "SELECT conversation_id FROM conversations 
                   WHERE (user_1_id = :user_1 AND user_2_id = :user_2) 
                      OR (user_1_id = :user_2 AND user_2_id = :user_1)";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([
        ':user_1' => $_SESSION['user_id'],
        ':user_2' => $other_user_id
    ]);
    $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode(['success' => true, 'conversation_id' => $existing['conversation_id']]);
        exit();
    }

    $roleStmt = $db->prepare('SELECT user_id, role, full_name FROM users WHERE user_id = ?');
    $roleStmt->execute([(int) $other_user_id]);
    $other = $roleStmt->fetch(PDO::FETCH_ASSOC);
    if (!$other) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    $myRole = $_SESSION['role'] ?? '';
    if (!messaging_pair_allowed((string) $myRole, (string) $other['role'])) {
        echo json_encode(['success' => false, 'message' => 'You cannot start a conversation with this user.']);
        exit();
    }

    // Create new conversation
    $insert_query = "INSERT INTO conversations (user_1_id, user_2_id, created_at) 
                    VALUES (:user_1, :user_2, CURRENT_TIMESTAMP)";
    $insert_stmt = $db->prepare($insert_query);
    $insert_stmt->execute([
        ':user_1' => $_SESSION['user_id'],
        ':user_2' => $other_user_id
    ]);

    $conversation_id = $db->lastInsertId();

    $my_label = isset($_SESSION['full_name']) ? trim((string) $_SESSION['full_name']) : 'A user';
    if ($my_label === '') {
        $my_label = 'User #' . (int) $_SESSION['user_id'];
    }
    denr_notify_user(
        $db,
        (int) $other_user_id,
        $my_label . ' started a new conversation with you. Open Messages to reply.'
    );

    if ($myRole === 'verifier' && (($other['role'] ?? '') === 'landowner')) {
        $on = trim((string) ($other['full_name'] ?? ''));
        if ($on === '') {
            $on = 'landowner #' . (int) $other_user_id;
        }
        denr_notify_admins_verifier_activity($db, 'Started a new conversation with ' . $on . '.');
    }

    echo json_encode(['success' => true, 'conversation_id' => $conversation_id]);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
