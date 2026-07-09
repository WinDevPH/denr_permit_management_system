<?php
if (!isset($_SESSION)) {
    session_start();
}

// Get user profile image and name
try {
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/notification_redirect.php';
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT profile_img, full_name FROM users WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $profile_img = $result['profile_img'] ?? 'default.png';
    $user_name = $result['full_name'] ?? 'User';
} catch (PDOException $e) {
    $profile_img = 'default.png';
    $user_name = 'User';
}

// Fetch pending plantations and permits for notifications (verifier sees same pending items)
try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    $plantQuery = "SELECT p.*, u.full_name FROM plantations p LEFT JOIN users u ON p.user_id = u.user_id WHERE p.status = 'pending' ORDER BY p.registered_at DESC";
    $plantStmt = $db->prepare($plantQuery);
    $plantStmt->execute();
    $pendingPlantations = $plantStmt->fetchAll(PDO::FETCH_ASSOC);

    $permitQuery = "SELECT pm.*, pl.plantation_name, u.full_name FROM permits pm 
                    LEFT JOIN plantations pl ON pm.plantation_id = pl.plantation_id
                    LEFT JOIN users u ON pl.user_id = u.user_id
                    WHERE pm.status = 'pending' ORDER BY pm.requested_at DESC";
    $permitStmt = $db->prepare($permitQuery);
    $permitStmt->execute();
    $pendingPermits = $permitStmt->fetchAll(PDO::FETCH_ASSOC);

    $inAppNotifications = [];
    $unreadNotifCount = 0;
    try {
        $nstmt = $db->prepare('SELECT notif_id, message, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15');
        $nstmt->execute([$_SESSION['user_id']]);
        $inAppNotifications = $nstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $uc = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND (is_read = 0 OR is_read IS NULL)');
        $uc->execute([$_SESSION['user_id']]);
        $unreadNotifCount = (int) $uc->fetchColumn();
    } catch (PDOException $e) {
        $inAppNotifications = [];
        $unreadNotifCount = 0;
    }

    $notifCount = count($pendingPlantations) + count($pendingPermits) + $unreadNotifCount;
} catch (PDOException $e) {
    $pendingPlantations = [];
    $pendingPermits = [];
    $inAppNotifications = [];
    $unreadNotifCount = 0;
    $notifCount = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifier Header</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png" />

    <style>
    .notif-dropdown {
        display: none;
        position: absolute;
        top: 48px;
        right: 0;
        width: 370px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 8px 32px rgba(44, 62, 80, 0.18), 0 1.5px 4px rgba(0, 0, 0, 0.08);
        z-index: 9999;
        border: 1px solid #e9ecef;
        padding: 0;
        overflow: hidden;
        animation: fadeInNotif 0.2s;
    }
    @keyframes fadeInNotif {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .notif-dropdown-header {
        background: #198754;
        color: #fff;
        padding: 0.6rem 1rem;
        font-weight: 600;
        font-size: 0.8125rem;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .notif-dropdown-list {
        max-height: 320px;
        overflow-y: auto;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .notif-dropdown-list li {
        padding: 0.65rem 1rem;
        border-bottom: 1px solid #f1f1f1;
        font-size: 0.8125rem;
        cursor: pointer;
        transition: background 0.15s;
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
    }
    .notif-dropdown-list li:last-child { border-bottom: none; }
    .notif-dropdown-list li:hover { background: #f8f9fa; }
    .notif-dropdown-empty {
        text-align: center;
        color: #aaa;
        padding: 1.5rem 1rem;
        font-size: 0.8125rem;
    }
    .notif-dropdown-icon { font-size: 1.1rem; margin-top: 2px; }
    .notif-dropdown-content { flex: 1; min-width: 0; }
    .notif-dropdown-title {
        font-weight: 600;
        font-size: 0.875rem;
        color: #222;
        margin-bottom: 2px;
        white-space: normal;
        overflow: visible;
        text-overflow: unset;
    }
    .notif-dropdown-meta { font-size: 0.75rem; color: #888; margin-top: 2px; }
    .notif-dropdown-remarks { font-size: 0.75rem; color: #666; margin-top: 2px; }
    .notif-bell-dropdown-wrap { position: relative; display: inline-block; }
    </style>
</head>

<body>
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
            <div class="notif-bell-dropdown-wrap">
                <div class="notif-bell" id="notifBell" title="Notifications and pending verifications">
                    <svg class="header-icon header-icon-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($notifCount > 0): ?>
                    <span class="notif-badge"><?php echo $notifCount; ?></span>
                    <?php endif; ?>
                </div>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-header">
                        <svg class="notif-dropdown-header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Alerts &amp; schedule
                    </div>
                    <?php if (empty($inAppNotifications)): ?>
                    <div class="notif-dropdown-empty" style="border-bottom:1px solid #eee;">
                        <div>No system messages.</div>
                    </div>
                    <?php else: ?>
                    <ul class="notif-dropdown-list" style="border-bottom:1px solid #eee;">
                        <?php foreach ($inAppNotifications as $msg): ?>
                        <?php
                            $vfTarget = htmlspecialchars(
                                denr_notification_redirect_for_role('verifier', (string) $msg['message']),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            $vfNid = (int) ($msg['notif_id'] ?? 0);
                        ?>
                        <li data-notif-id="<?php echo $vfNid; ?>" data-notif-target="<?php echo $vfTarget; ?>"
                            onclick="denrVerifierNotifNavigate(this)"
                            style="<?php echo (empty($msg['is_read']) || (int)$msg['is_read'] === 0) ? 'background:#f0fff4;' : ''; ?>">
                            <span class="notif-dropdown-icon notif-dropdown-icon--primary"><i class="fas fa-bell" style="color:#198754;"></i></span>
                            <span class="notif-dropdown-content">
                                <span class="notif-dropdown-title"><?php echo htmlspecialchars($msg['message']); ?></span>
                                <span class="notif-dropdown-meta"><?php echo htmlspecialchars(date('M d, Y g:i A', strtotime($msg['created_at']))); ?></span>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>

                    <div class="notif-dropdown-header" style="background:#157347;">
                        <svg class="notif-dropdown-header-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M7 20c0-4 2-6 5-6s5 2 5 6"/></svg>
                        Pending to verify
                    </div>
                    <?php if (empty($pendingPlantations) && empty($pendingPermits)): ?>
                    <div class="notif-dropdown-empty">
                        <div>No pending items — check alerts above for scheduled visits.</div>
                    </div>
                    <?php else: ?>
                    <ul class="notif-dropdown-list">
                        <?php foreach ($pendingPlantations as $plant): ?>
                        <li onclick="window.location.href='../plantations/plantations.php?plantation_id=<?php echo urlencode($plant['plantation_id']); ?>'">
                            <span class="notif-dropdown-icon notif-dropdown-icon--success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M7 20c0-4 2-6 5-6s5 2 5 6"/></svg></span>
                            <span class="notif-dropdown-content">
                                <span class="notif-dropdown-title">Plantation: <?php echo htmlspecialchars($plant['plantation_name']); ?></span>
                                <span class="notif-dropdown-meta"><?php echo htmlspecialchars($plant['full_name']); ?> · <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($plant['registered_at']))); ?></span>
                            </span>
                        </li>
                        <?php endforeach; ?>
                        <?php foreach ($pendingPermits as $permit): ?>
                        <li onclick="window.location.href='../permits/permits.php?permit_id=<?php echo urlencode($permit['permit_id']); ?>'">
                            <span class="notif-dropdown-icon notif-dropdown-icon--primary"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></span>
                            <span class="notif-dropdown-content">
                                <span class="notif-dropdown-title">Permit: <?php echo htmlspecialchars($permit['plantation_name']); ?> (<?php echo htmlspecialchars(ucfirst($permit['permit_type'])); ?>)</span>
                                <span class="notif-dropdown-meta"><?php echo htmlspecialchars($permit['full_name']); ?> · <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($permit['requested_at']))); ?></span>
                                <?php if (!empty($permit['remarks'])): ?>
                                <div class="notif-dropdown-remarks"><em>Remarks:</em> <?php echo htmlspecialchars($permit['remarks']); ?></div>
                                <?php endif; ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
            <a href="../profile/profile.php" class="header-profile-avatar" aria-label="Profile" title="Profile">
                <?php
                $profile_img_path = __DIR__ . '/../assets/uploads/profiles/' . $profile_img;
                if ($profile_img === 'default.png' || !file_exists($profile_img_path)) {
                    echo '<span class="header-profile-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6"/></svg></span>';
                } else {
                    echo '<img src="../../../assets/uploads/profiles/' . htmlspecialchars($profile_img) . '" alt="Profile" class="header-profile-img">';
                }
                ?>
            </a>
        </div>
    </header>

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
    (function() {
        const SIDEBAR_STORAGE_KEY = 'denr-sidebar-collapsed';
        const dashboardContainer = document.querySelector('.dashboard-container');
        const sidebar = document.querySelector('.sidebar');
        const menuToggle = document.querySelector('.menu-toggle');
        if (menuToggle && dashboardContainer) {
            try {
                if (localStorage.getItem(SIDEBAR_STORAGE_KEY) === 'true') dashboardContainer.classList.add('sidebar-collapsed');
            } catch (e) {}
            menuToggle.addEventListener('click', function() {
                dashboardContainer.classList.toggle('sidebar-collapsed');
                try { localStorage.setItem(SIDEBAR_STORAGE_KEY, dashboardContainer.classList.contains('sidebar-collapsed')); } catch (e) {}
            });
        }
        const notifBell = document.getElementById('notifBell');
        const notifDropdown = document.getElementById('notifDropdown');
        if (notifBell && notifDropdown) {
            let notifOpen = false;
            notifBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notifDropdown.style.display = notifDropdown.style.display === 'block' ? 'none' : 'block';
                notifOpen = notifDropdown.style.display === 'block';
            });
            document.addEventListener('click', function(e) {
                if (notifOpen && !notifDropdown.contains(e.target) && !notifBell.contains(e.target)) {
                    notifDropdown.style.display = 'none';
                    notifOpen = false;
                }
            });
        }
        window.denrVerifierNotifNavigate = function(li) {
            var id = li.getAttribute('data-notif-id');
            var target = li.getAttribute('data-notif-target') || '../dashboard/dashboard.php';
            if (!id) { window.location.href = target; return; }
            fetch('../../../handlers/mark_notification_read.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notif_id: parseInt(id, 10) })
            })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data && data.success) {
                        try {
                            var bell = document.getElementById('notifBell');
                            var bd = bell && bell.querySelector('.notif-badge');
                            var n = bd ? parseInt(bd.textContent, 10) : 0;
                            if (!isNaN(n) && n > 0 && bd) { bd.textContent = String(n - 1); if (n - 1 <= 0) bd.remove(); }
                        } catch (e) {}
                        window.location.href = target;
                        return;
                    }
                    window.location.href = target;
                })
                .catch(function() { window.location.href = target; });
        };
    })();
    </script>
</body>
</html>
