<?php
if (!isset($_SESSION)) {
    session_start();
}

// Get user profile image and name
try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    require_once __DIR__ . '/notification_redirect.php';

    $query = "SELECT profile_img, full_name FROM users WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $profile_img = $result['profile_img'] ?? 'default.png';
    $user_name = $result['full_name'] ?? 'User';

    // Get notifications for user (include scheduled_time for Verifier time schedule when column exists)
    $notifications = [];
    try {
        $col = $db->query("SHOW COLUMNS FROM notifications LIKE 'scheduled_time'")->fetch();
        $has_scheduled = !empty($col);
        $notif_query = $has_scheduled
            ? "SELECT notif_id, message, created_at, is_read, scheduled_time FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 15"
            : "SELECT notif_id, message, created_at, is_read FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 15";
        $notif_stmt = $db->prepare($notif_query);
        $notif_stmt->bindParam(':user_id', $_SESSION['user_id']);
        $notif_stmt->execute();
        $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$has_scheduled) {
            foreach ($notifications as &$n) { $n['scheduled_time'] = null; }
            unset($n);
        }
    } catch (Exception $e) {
        $notifications = [];
    }

    // Get unread notifications count
    $unread_query = "SELECT COUNT(*) FROM notifications 
                     WHERE user_id = :user_id AND is_read = 0";
    $unread_stmt = $db->prepare($unread_query);
    $unread_stmt->bindParam(':user_id', $_SESSION['user_id']);
    $unread_stmt->execute();
    $unread_count = $unread_stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $profile_img = 'default.png';
    $user_name = 'User';
    $notifications = [];
    $unread_count = 0;
    error_log('Header notification error: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Header</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png" />

    <style>
    /* Header profile avatar (img + notif only) styled in landowner.css / main.css */

    .notifications-dropdown {
        position: relative;
        margin-right: 1rem;
    }

    /* .notifications-btn styled in main.css */

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: #dc3545;
        color: white;
        border-radius: 50%;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        min-width: 18px;
        text-align: center;
    }

    .notifications-menu {
        position: absolute;
        top: 100%;
        right: 0;
        width: 320px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        display: none;
        z-index: 1000;
        max-height: 400px;
        overflow-y: auto;
    }

    .notifications-menu.show {
        display: block;
    }

    .notification-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e9ecef;
        transition: background-color 0.3s;
    }

    .notification-item:hover {
        background-color: #f8f9fa;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-content {
        font-size: 0.8125rem;
        color: #495057;
    }

    .notification-time {
        font-size: 0.6875rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    .notification-schedule {
        font-size: 0.6875rem;
        color: #007c36;
        margin-top: 0.25rem;
    }
    .notification-schedule i {
        margin-right: 0.25rem;
    }

    .notification-item.unread {
        background-color: #e8f4ff;
    }

    .empty-notifications {
        padding: 1.5rem;
        text-align: center;
        color: #6c757d;
        font-size: 0.8125rem;
    }

    </style>
</head>

<body>
    <!-- Top Navigation -->
    <header class="top-nav">
        <div class="nav-left">
            <button class="menu-toggle" type="button" aria-label="Toggle menu">
                <svg class="header-icon header-icon-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12h18M3 6h18M3 18h18"/></svg>
            </button>
            <form class="header-search" action="#" method="get" role="search">
                <span class="header-search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </span>
                <input type="search" class="header-search-input" name="q" placeholder="Search..." aria-label="Search" autocomplete="off">
            </form>
        </div>
        <div class="nav-right">
            <div class="notifications-dropdown">
                <button class="notifications-btn" type="button" onclick="toggleNotifications()" aria-label="Notifications">
                    <svg class="header-icon header-icon-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </button>
                <div class="notifications-menu" id="notificationsMenu">
                    <?php if (empty($notifications)): ?>
                    <div class="empty-notifications">
                        <svg class="empty-notifications-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                        <p>No notifications</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($notifications as $notif): ?>
                    <?php
                        $nrRole = isset($_SESSION['role']) ? (string) $_SESSION['role'] : 'landowner';
                        $nrUrl = htmlspecialchars(
                            denr_notification_redirect_for_role($nrRole, (string) $notif['message']),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>"
                        data-notif-target="<?php echo $nrUrl; ?>"
                        onclick="handleNotificationNavigate(<?php echo (int) $notif['notif_id']; ?>, this)">
                        <div class="notification-content">
                            <?php echo htmlspecialchars($notif['message']); ?>
                        </div>
                        <?php if (!empty($notif['scheduled_time'])): ?>
                        <div class="notification-schedule">
                            <i class="fas fa-clock"></i> Schedule: <?php echo date('M d, Y h:i A', strtotime($notif['scheduled_time'])); ?>
                        </div>
                        <?php endif; ?>
                        <div class="notification-time">
                            <?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <a href="../profile/profile.php" class="header-profile-avatar" aria-label="Profile" title="Profile">
                <?php
                $profile_img_path = '../../../assets/uploads/profiles/' . $profile_img;
                if ($profile_img === 'default.png' || !file_exists($profile_img_path)) {
                    echo '<span class="header-profile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg></span>';
                } else {
                    echo '<img src="' . htmlspecialchars($profile_img_path) . '" alt="Profile" class="header-profile-img">';
                }
                ?>
            </a>
        </div>
    </header>

    <!-- Logout Modal (simple confirmation) -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered logout-modal-dialog">
            <div class="modal-content logout-modal-content">
                <div class="modal-body logout-modal-body">
                    <div class="logout-icon-wrap">
                        <svg class="logout-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    </div>
                    <p class="logout-modal-title" id="logoutModalTitle">Sign out?</p>
                    <p class="logout-modal-desc">You will need to sign in again to continue.</p>
                    <div class="logout-actions">
                        <button type="button" class="btn btn-logout-cancel" data-bs-dismiss="modal">Cancel</button>
                        <a href="../../../handlers/logout.php" class="btn btn-logout-confirm">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Sidebar state: persist collapsed so refresh keeps it until burger is clicked
    const SIDEBAR_STORAGE_KEY = 'denr-sidebar-collapsed';
    const dashboardContainer = document.querySelector('.dashboard-container');
    const sidebar = document.querySelector('.sidebar');

    (function applySavedSidebarState() {
        try {
            if (localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'true') {
                dashboardContainer.classList.add('sidebar-collapsed');
            } else {
                dashboardContainer.classList.remove('sidebar-collapsed');
            }
        } catch (e) {}
    })();

    document.querySelector('.menu-toggle').addEventListener('click', function() {
        dashboardContainer.classList.toggle('sidebar-collapsed');
        try {
            localStorage.setItem(SIDEBAR_STORAGE_KEY, dashboardContainer.classList.contains('sidebar-collapsed'));
        } catch (e) {}
    });

    // Close sidebar when clicking outside
    document.addEventListener('click', function(event) {
        const isMobile = window.innerWidth <= 768;
        const clickedOutsideSidebar = !sidebar.contains(event.target);
        const clickedMenuToggle = event.target.closest('.menu-toggle');
        const isSidebarOpen = dashboardContainer.classList.contains('sidebar-collapsed');

        if (isMobile && clickedOutsideSidebar && !clickedMenuToggle && isSidebarOpen) {
            dashboardContainer.classList.remove('sidebar-collapsed');
        }
    });

    // Close sidebar when clicking on sidebar navigation links on mobile
    document.querySelectorAll('.sidebar-nav a').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                const isSidebarOpen = dashboardContainer.classList.contains('sidebar-collapsed');
                if (isSidebarOpen) {
                    dashboardContainer.classList.remove('sidebar-collapsed');
                }
            }
        });
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    function toggleNotifications() {
        const menu = document.getElementById('notificationsMenu');
        menu.classList.toggle('show');
    }

    // Close notifications when clicking outside
    document.addEventListener('click', function(event) {
        const menu = document.getElementById('notificationsMenu');
        const btn = event.target.closest('.notifications-btn');

        if (!btn && menu.classList.contains('show')) {
            menu.classList.remove('show');
        }
    });

    function handleNotificationNavigate(notifId, element) {
        const target = element.getAttribute('data-notif-target') || '../dashboard/dashboard.php';
        fetch('../../../handlers/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    notif_id: notifId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.classList.remove('unread');
                    updateNotificationBadge();
                    window.location.href = target;
                } else {
                    window.location.href = target;
                }
            })
            .catch(function() {
                window.location.href = target;
            });
    }

    function updateNotificationBadge() {
        const badge = document.querySelector('.notification-badge');
        const unreadItems = document.querySelectorAll('.notification-item.unread').length;

        if (badge && unreadItems === 0) {
            badge.remove();
        }
    }
    </script>

</body>

</html>