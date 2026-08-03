<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit();
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$selected_conversation_id = $_GET['conversation'] ?? null;

try {
    // Get all users except current user, with optional conversation data
    $user_id = $_SESSION['user_id'];
    $users_list_query = "SELECT u.user_id, u.full_name, u.profile_img, u.email,
                    c.conversation_id,
                    (SELECT message_text FROM messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message,
                    (SELECT created_at FROM messages WHERE conversation_id = c.conversation_id ORDER BY created_at DESC LIMIT 1) as last_message_time,
                    (SELECT COUNT(*) FROM messages m2 WHERE m2.conversation_id = c.conversation_id AND m2.is_read = 0 AND m2.receiver_id = :user_id) as unread_count
                   FROM users u
                   LEFT JOIN conversations c ON (
                        (c.user_1_id = u.user_id AND c.user_2_id = :user_id) OR
                        (c.user_2_id = u.user_id AND c.user_1_id = :user_id)
                   )
                   WHERE u.user_id != :user_id
                   ORDER BY last_message_time IS NULL ASC, last_message_time DESC, u.full_name ASC";
    
    $users_list_stmt = $db->prepare($users_list_query);
    $users_list_stmt->execute([':user_id' => $user_id]);
    $users_list = $users_list_stmt->fetchAll(PDO::FETCH_ASSOC);

    $selected_conversation = null;
    $messages = [];
    $messages_by_date = [];
    $other_user = null;

    if ($selected_conversation_id) {
        $detail_query = "SELECT * FROM conversations WHERE conversation_id = :conversation_id AND (user_1_id = :user_id OR user_2_id = :user_id)";
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->execute([':conversation_id' => $selected_conversation_id, ':user_id' => $_SESSION['user_id']]);
        $selected_conversation = $detail_stmt->fetch(PDO::FETCH_ASSOC);

        if ($selected_conversation) {
            $other_id = ($selected_conversation['user_1_id'] == $_SESSION['user_id']) 
                        ? $selected_conversation['user_2_id'] 
                        : $selected_conversation['user_1_id'];
            
            $user_query = "SELECT user_id, full_name, profile_img FROM users WHERE user_id = :user_id";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->execute([':user_id' => $other_id]);
            $other_user = $user_stmt->fetch(PDO::FETCH_ASSOC);

            $msg_query = "SELECT m.*, u.full_name, u.profile_img FROM messages m
                         JOIN users u ON m.sender_id = u.user_id
                         WHERE m.conversation_id = :conversation_id
                         ORDER BY m.created_at ASC";
            $msg_stmt = $db->prepare($msg_query);
            $msg_stmt->execute([':conversation_id' => $selected_conversation_id]);
            $messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group messages by date for date separators
            $messages_by_date = [];
            foreach ($messages as $msg) {
                $date_key = date('Y-m-d', strtotime($msg['created_at']));
                if (!isset($messages_by_date[$date_key])) {
                    $messages_by_date[$date_key] = [];
                }
                $messages_by_date[$date_key][] = $msg;
            }

            $read_query = "UPDATE messages SET is_read = 1 
                          WHERE conversation_id = :conversation_id AND receiver_id = :user_id";
            $read_stmt = $db->prepare($read_query);
            $read_stmt->execute([
                ':conversation_id' => $selected_conversation_id,
                ':user_id' => $_SESSION['user_id']
            ]);
        }
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
}

function getProfileImage($profileImg) {
    if ($profileImg && file_exists('../../../assets/uploads/profiles/' . $profileImg)) {
        return '../../../assets/uploads/profiles/' . $profileImg;
    }
    return null;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/messages.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>

<body class="admin-messages-page">
    <div class="dashboard-container">
        <?php include '../../../admin_includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../../admin_includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard admin-messages">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Messages</h1>
                        <p class="admin-dashboard-subtitle">Communicate with landowners and manage conversations</p>
                    </div>
                </header>

                <div class="msg-app <?php echo ($selected_conversation && $other_user) ? 'msg-app--thread-open' : ''; ?>" role="application" aria-label="Messaging">
                    <aside class="msg-sidebar">
                        <div class="msg-sidebar-search">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchConversations" class="form-control"
                                placeholder="Search users..." aria-label="Search users">
                        </div>

                        <div class="msg-tab-content">
                            <div class="msg-conv-pane" id="conversations-pane">
                                <ul class="msg-conv-list" aria-label="Users list">
                                    <?php if (empty($users_list)): ?>
                                    <li class="msg-empty-state">
                                        <div class="msg-empty-icon"><i class="fas fa-users"></i></div>
                                        <p class="msg-empty-title">No other users</p>
                                        <p class="msg-empty-desc">There are no other users to message.</p>
                                    </li>
                                    <?php else: ?>
                                    <?php foreach ($users_list as $u):
                                            $profileImg = getProfileImage($u['profile_img']);
                                            $hasConvo = !empty($u['conversation_id']);
                                            $isActive = $hasConvo && (string)($selected_conversation_id ?? '') === (string)$u['conversation_id'];
                                            $preview = trim($u['last_message'] ?? '') !== '' ? substr($u['last_message'], 0, 45) . (strlen($u['last_message']) > 45 ? '…' : '') : ($u['email'] ?? 'No messages yet');
                                        ?>
                                    <li class="msg-conv-item <?php echo $isActive ? 'is-active' : ''; ?>"
                                        data-user-id="<?php echo (int)$u['user_id']; ?>"
                                        data-conversation-id="<?php echo $hasConvo ? (int)$u['conversation_id'] : ''; ?>"
                                        onclick="<?php echo $hasConvo ? 'openConversation(' . (int)$u['conversation_id'] . ')' : 'startConversation(' . (int)$u['user_id'] . ')'; ?>"
                                        role="button" tabindex="0"
                                        onkeydown="if(event.key==='Enter') this.click();">
                                        <div class="msg-conv-avatar">
                                            <?php if ($profileImg): ?>
                                            <img src="<?php echo htmlspecialchars($profileImg); ?>"
                                                alt="" loading="lazy"
                                                onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('is-hidden');">
                                            <?php endif; ?>
                                            <div class="msg-avatar-placeholder <?php echo $profileImg ? 'is-hidden' : ''; ?>">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <?php if (!empty($u['unread_count']) && (int)$u['unread_count'] > 0): ?>
                                            <span class="msg-unread-badge" aria-label="<?php echo (int)$u['unread_count']; ?> unread"><?php echo (int)$u['unread_count']; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="msg-conv-body">
                                            <span class="msg-conv-name"><?php echo htmlspecialchars($u['full_name']); ?></span>
                                            <span class="msg-conv-preview"><?php echo htmlspecialchars($preview); ?></span>
                                            <?php if (!empty($u['last_message_time'])): ?>
                                            <time class="msg-conv-time" datetime="<?php echo date('c', strtotime($u['last_message_time'])); ?>"><?php echo date('M j, g:i A', strtotime($u['last_message_time'])); ?></time>
                                            <?php endif; ?>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </aside>

                    <section class="msg-thread" aria-label="Conversation">
                        <?php if ($selected_conversation && $other_user):
                            $otherProfileImg = getProfileImage($other_user['profile_img']);
                        ?>
                        <header class="msg-thread-header">
                            <a href="?" class="msg-thread-back" aria-label="Back to conversations">
                                <i class="fas fa-arrow-left"></i>
                            </a>
                            <div class="msg-thread-user">
                                <div class="msg-thread-avatar-wrap">
                                    <?php if ($otherProfileImg): ?>
                                    <img src="<?php echo htmlspecialchars($otherProfileImg); ?>"
                                        alt="" class="msg-thread-avatar" loading="lazy"
                                        onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('is-hidden');">
                                    <?php endif; ?>
                                    <div class="msg-thread-avatar msg-avatar-placeholder <?php echo $otherProfileImg ? 'is-hidden' : ''; ?>">
                                        <i class="fas fa-user"></i>
                                    </div>
                                </div>
                                <div class="msg-thread-info">
                                    <h2 class="msg-thread-name"><?php echo htmlspecialchars($other_user['full_name']); ?></h2>
                                    <span class="msg-thread-status"><i class="msg-status-dot"></i> Active</span>
                                </div>
                            </div>
                        </header>

                        <div class="msg-thread-messages" id="messagesList" role="log" aria-live="polite">
                            <?php if (empty($messages_by_date)): ?>
                            <div class="msg-thread-empty" aria-label="No messages yet">
                                <div class="msg-thread-empty-icon"><i class="fas fa-comment-dots"></i></div>
                                <p class="msg-thread-empty-title">No messages yet</p>
                                <p class="msg-thread-empty-desc">Send a message to start the conversation.</p>
                            </div>
                            <?php else:
                            foreach ($messages_by_date as $date_key => $day_messages):
                                $label = date('Y-m-d', strtotime($date_key)) === date('Y-m-d') ? 'Today' : (date('Y-m-d', strtotime($date_key)) === date('Y-m-d', strtotime('-1 day')) ? 'Yesterday' : date('F j, Y', strtotime($date_key)));
                            ?>
                            <div class="msg-date-divider" role="separator">
                                <span><?php echo $label; ?></span>
                            </div>
                            <?php foreach ($day_messages as $msg):
                                $msgProfileImg = getProfileImage($msg['profile_img']);
                                $isSent = (int)$msg['sender_id'] === (int)$_SESSION['user_id'];
                            ?>
                            <article class="msg-bubble-wrap msg-bubble--<?php echo $isSent ? 'sent' : 'received'; ?>"
                                data-msg-id="<?php echo (int)($msg['message_id'] ?? 0); ?>">
                                <div class="msg-bubble-avatar">
                                    <?php if ($msgProfileImg): ?>
                                    <img src="<?php echo htmlspecialchars($msgProfileImg); ?>" alt="" loading="lazy"
                                        onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('is-hidden');">
                                    <?php endif; ?>
                                    <span class="msg-avatar-placeholder msg-avatar--sm <?php echo $msgProfileImg ? 'is-hidden' : ''; ?>"><i class="fas fa-user"></i></span>
                                </div>
                                <div class="msg-bubble-content">
                                    <div class="msg-bubble-text"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></div>
                                    <time class="msg-bubble-time" datetime="<?php echo date('c', strtotime($msg['created_at'])); ?>"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></time>
                                </div>
                            </article>
                            <?php endforeach; ?>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <form id="messageForm" class="msg-composer" aria-label="Send message">
                            <input type="hidden" name="conversation_id" value="<?php echo htmlspecialchars($selected_conversation_id); ?>">
                            <div class="msg-composer-inner">
                                <textarea name="message_text" class="msg-composer-input form-control"
                                    placeholder="Type a message..." required minlength="1" rows="1"
                                    aria-label="Message text"></textarea>
                                <button type="submit" class="msg-composer-send btn btn-success" title="Send message" aria-label="Send">
                                    <i class="fas fa-paper-plane"></i>
                                    <span class="msg-composer-send-label">SEND</span>
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                        <div class="msg-welcome" aria-label="No conversation selected">
                            <div class="msg-welcome-icon"><i class="fas fa-comments"></i></div>
                            <h2 class="msg-welcome-title">Select a conversation</h2>
                            <p class="msg-welcome-desc">Choose a user from the list to view or start a conversation.</p>
                        </div>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/messages.js?v=<?php echo time(); ?>"></script>
    <script>
    function openConversation(conversationId) { window.location.href = '?conversation=' + conversationId; }
    </script>
</body>

</html>