<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../config/database.php';
require_once __DIR__ . '/../config/verifier_notify_admins.php';
require_once __DIR__ . '/../config/notifications.php';

$conversation_id = $_POST['conversation_id'] ?? null;
$message_text = $_POST['message_text'] ?? null;

// Debug logging
error_log('Send message - Conversation ID: ' . $conversation_id . ', Message: ' . substr($message_text ?? '', 0, 50));

if (!$conversation_id || !$message_text) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Sanitize message
$message_text = trim($message_text);
if (empty($message_text)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Verify conversation exists and user is part of it
    $conv_query = "SELECT * FROM conversations 
                   WHERE conversation_id = :conversation_id 
                   AND (user_1_id = :user_id OR user_2_id = :user_id)";
    $conv_stmt = $db->prepare($conv_query);
    $conv_stmt->execute([
        ':conversation_id' => $conversation_id,
        ':user_id' => $_SESSION['user_id']
    ]);
    $conversation = $conv_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conversation) {
        error_log('Conversation not found or access denied');
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Conversation not found or access denied']);
        exit();
    }

    // Determine receiver
    $receiver_id = ($conversation['user_1_id'] == $_SESSION['user_id']) 
                   ? $conversation['user_2_id'] 
                   : $conversation['user_1_id'];

    $sender_label = isset($_SESSION['full_name']) ? trim((string) $_SESSION['full_name']) : 'Someone';
    if ($sender_label === '') {
        $sender_label = 'User #' . (int) $_SESSION['user_id'];
    }

    // Insert message
    $msg_query = "INSERT INTO messages 
                  (conversation_id, sender_id, receiver_id, message_text, created_at, is_read) 
                  VALUES (:conversation_id, :sender_id, :receiver_id, :message_text, CURRENT_TIMESTAMP, 0)";
    $msg_stmt = $db->prepare($msg_query);
    $result = $msg_stmt->execute([
        ':conversation_id' => $conversation_id,
        ':sender_id' => $_SESSION['user_id'],
        ':receiver_id' => $receiver_id,
        ':message_text' => $message_text
    ]);

    if ($result) {
        error_log('Message inserted successfully');
        
        // Create notification for receiver
        denr_notify_user($db, (int) $receiver_id, 'You have a new message from ' . $sender_label . '.');

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'verifier') {
            denr_notify_admins_verifier_activity($db, 'Sent a new message.');
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Message sent successfully',
            'sender_id' => $_SESSION['user_id']
        ]);
        exit();
    } else {
        throw new Exception('Failed to insert message');
    }
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('Error: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>