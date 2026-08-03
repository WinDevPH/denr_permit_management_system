<?php
if (!isset($_SESSION)) {
    session_start();
}
$sidebar_profile_img = 'default.png';
$sidebar_user_name = 'User';
try {
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("SELECT profile_img, full_name FROM users WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $sidebar_profile_img = $row['profile_img'] ?? 'default.png';
        $sidebar_user_name = $row['full_name'] ?? 'User';
    }
} catch (Exception $e) {
    // keep defaults
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifier Sidebar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png" />

</head>

<body>
    <!-- Verifier Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logos" aria-label="DENR and FMB">
                <img src="../../../assets/img/denrlogo.png" alt="DENR Logo" class="sidebar-logo">
                <img src="../../../assets/img/fmb_logo.png" alt="Forest Management Bureau Logo" class="sidebar-logo sidebar-logo-fmb">
            </div>
            <span class="sidebar-title">DENR Verifier</span>
        </div>

        <div class="sidebar-divider"></div>
        <nav class="sidebar-nav">
            <p class="sidebar-nav-label">Verifier</p>
            <ul>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <a href="../dashboard/dashboard.php"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" />
                            <rect x="14" y="3" width="7" height="7" />
                            <rect x="14" y="14" width="7" height="7" />
                            <rect x="3" y="14" width="7" height="7" />
                        </svg><span class="sidebar-nav-text">Dashboard</span></a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'plantations.php' ? 'active' : ''; ?>">
                    <a href="../plantations/plantations.php"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22v-7" />
                            <path d="M9 8c0 1.5 1.5 3 3 3s3-1.5 3-3c0-2-1.5-4-3-4S9 6 9 8Z" />
                            <path d="M12 15c-4 0-7-2-7-7 0-2 1-4 2-5h10c1 1 2 3 2 5 0 5-3 7-7 7Z" />
                        </svg><span class="sidebar-nav-text">Verify Locations</span></a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'calendar.php' ? 'active' : ''; ?>">
                    <a href="../calendar/calendar.php"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
                            <line x1="16" y1="2" x2="16" y2="6" />
                            <line x1="8" y1="2" x2="8" y2="6" />
                            <line x1="3" y1="10" x2="21" y2="10" />
                        </svg><span class="sidebar-nav-text">Calendar</span></a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : ''; ?>">
                    <a href="../messages/messages.php"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z" />
                            <polyline points="22,6 12,13 2,6" />
                        </svg><span class="sidebar-nav-text">Messages</span></a>
                </li>
                <li class="<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>">
                    <a href="../profile/profile.php"><svg class="sidebar-icon" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg><span class="sidebar-nav-text">Profile</span></a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-divider"></div>
        <div class="sidebar-footer">
            <div class="sidebar-footer-row">
                <a href="../profile/profile.php" class="sidebar-profile">
                    <?php
                    $img_path = __DIR__ . '/../assets/uploads/profiles/' . $sidebar_profile_img;
                    if ($sidebar_profile_img !== 'default.png' && file_exists($img_path)): ?>
                        <img src="../../../assets/uploads/profiles/<?php echo htmlspecialchars($sidebar_profile_img); ?>"
                            alt="" class="sidebar-profile-img">
                    <?php else: ?>
                        <span class="sidebar-profile-avatar"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.75">
                                <circle cx="12" cy="8" r="4" />
                                <path d="M4 20c0-4 4-6 8-6s8 2 8 6" />
                            </svg></span>
                    <?php endif; ?>
                    <span class="sidebar-profile-name"><?php echo htmlspecialchars($sidebar_user_name); ?></span>
                </a>
                <button type="button" class="sidebar-logout-btn" data-bs-toggle="modal" data-bs-target="#logoutModal"
                    aria-label="Sign out">
                    <svg class="sidebar-logout-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                </button>
            </div>
        </div>
    </aside>

</body>

</html>
