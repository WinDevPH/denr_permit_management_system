<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'verifier') {
    header('Location: ../../../index.php');
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM users WHERE user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$verifier = $stmt->fetch(PDO::FETCH_ASSOC);

$stats = [
    'permits_reviewed' => $db->query("SELECT COUNT(*) FROM permits")->fetchColumn() ?: 0,
    'plantations_reviewed' => $db->query("SELECT COUNT(*) FROM plantations")->fetchColumn() ?: 0,
];

$verifier_img = $verifier['profile_img'] ?? 'default.png';
$verifier_img_path = __DIR__ . '/../../../assets/uploads/profiles/' . $verifier_img;
$has_image = ($verifier_img !== 'default.png' && !empty($verifier_img) && file_exists($verifier_img_path));
$initials = 'U';
if (!empty($verifier['full_name'])) {
    $words = explode(' ', trim($verifier['full_name']));
    $initials = strtoupper(mb_substr($words[0], 0, 1));
    if (count($words) > 1) $initials .= strtoupper(mb_substr($words[1], 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - DENR Verifier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/admin_profile.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>
<body class="admin-profile-page">
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../verifier_includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include __DIR__ . '/../../../verifier_includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard admin-profile">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Profile</h1>
                        <p class="admin-dashboard-subtitle">Manage your account and security.</p>
                    </div>
                </header>

                <nav class="profile-breadcrumb" aria-label="Breadcrumb">
                    <ol class="profile-breadcrumb-list">
                        <li class="profile-breadcrumb-item">
                            <a href="../dashboard/dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
                        </li>
                        <li class="profile-breadcrumb-sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                        <li class="profile-breadcrumb-item profile-breadcrumb-item--current" aria-current="page">
                            <i class="fas fa-user"></i> <span>Profile</span>
                        </li>
                    </ol>
                </nav>

                <div class="profile-container">
                    <div class="profile-col profile-col-hero">
                        <div class="profile-hero">
                            <div class="profile-hero-avatar-wrap">
                                <input type="file" id="profileUpload" accept="image/jpeg,image/png,image/jpg,image/gif" class="profile-photo-input" aria-label="Change profile photo">
                                <label for="profileUpload" class="profile-hero-avatar" id="profileHeroAvatar" tabindex="0" aria-label="Change profile photo">
                                    <?php if ($has_image): ?>
                                    <img id="profileImage" src="../../../assets/uploads/profiles/<?php echo htmlspecialchars($verifier_img); ?>" alt="" onerror="this.style.display='none'; var p=document.getElementById('profileImagePlaceholder'); if(p) p.style.display='flex';">
                                    <span id="profileImagePlaceholder" class="profile-hero-initials" style="display:none;"><?php echo htmlspecialchars($initials); ?></span>
                                    <?php else: ?>
                                    <img id="profileImage" src="" alt="" style="display:none;">
                                    <span id="profileImagePlaceholder" class="profile-hero-initials"><?php echo htmlspecialchars($initials); ?></span>
                                    <?php endif; ?>
                                    <span class="profile-hero-avatar-overlay" aria-hidden="true"><i class="fas fa-camera"></i></span>
                                </label>
                            </div>
                            <div class="profile-hero-info">
                                <h3 class="profile-hero-name" id="profileDisplayName"><?php echo htmlspecialchars($verifier['full_name'] ?? 'User'); ?></h3>
                                <div class="profile-hero-role-wrap">
                                    <i class="fas fa-user-check profile-hero-role-icon"></i>
                                    <span class="profile-hero-role">Verifier</span>
                                </div>
                                <ul class="profile-hero-details" aria-label="Profile details">
                                    <li class="profile-hero-detail-item">
                                        <div class="profile-hero-detail-head">
                                            <i class="fas fa-envelope profile-hero-detail-icon"></i>
                                            <span class="profile-hero-detail-label">Email</span>
                                        </div>
                                        <span class="profile-hero-detail-value profile-hero-detail-value--email"><?php echo htmlspecialchars($verifier['email'] ?? '—'); ?></span>
                                    </li>
                                    <li class="profile-hero-detail-item">
                                        <div class="profile-hero-detail-head">
                                            <i class="fas fa-calendar profile-hero-detail-icon"></i>
                                            <span class="profile-hero-detail-label">Member since</span>
                                        </div>
                                        <span class="profile-hero-detail-value"><?php echo !empty($verifier['created_at']) ? date('F j, Y', strtotime($verifier['created_at'])) : '—'; ?></span>
                                    </li>
                                    <li class="profile-hero-detail-item">
                                        <div class="profile-hero-detail-head">
                                            <i class="fas fa-file-check profile-hero-detail-icon"></i>
                                            <span class="profile-hero-detail-label">Permits in system</span>
                                        </div>
                                        <span class="profile-hero-detail-value"><?php echo (int)$stats['permits_reviewed']; ?></span>
                                    </li>
                                    <li class="profile-hero-detail-item">
                                        <div class="profile-hero-detail-head">
                                            <i class="fas fa-tree profile-hero-detail-icon"></i>
                                            <span class="profile-hero-detail-label">Plantations in system</span>
                                        </div>
                                        <span class="profile-hero-detail-value"><?php echo (int)$stats['plantations_reviewed']; ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="profile-col profile-col-form">
                        <form id="updateProfileForm" class="profile-form">
                            <div class="profile-form-block">
                                <h3 class="profile-form-block-title">
                                    <i class="fas fa-user-edit profile-form-block-icon"></i>
                                    <span>Edit Profile</span>
                                </h3>
                                <div class="profile-form-grid">
                                    <div class="profile-form-row">
                                        <label for="fullName"><i class="fas fa-user profile-form-label-icon"></i> Full name</label>
                                        <input type="text" id="fullName" name="full_name" value="<?php echo htmlspecialchars($verifier['full_name'] ?? ''); ?>" required maxlength="120" autocomplete="name">
                                    </div>
                                    <div class="profile-form-row">
                                        <label for="email"><i class="fas fa-envelope profile-form-label-icon"></i> Email</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($verifier['email'] ?? ''); ?>" required maxlength="120" autocomplete="email">
                                    </div>
                                    <div class="profile-form-row">
                                        <label for="phone"><i class="fas fa-phone profile-form-label-icon"></i> Phone</label>
                                        <input type="tel" id="phone" name="contact_number" value="<?php echo htmlspecialchars($verifier['contact_number'] ?? ''); ?>" maxlength="20" autocomplete="tel">
                                    </div>
                                    <div class="profile-form-row">
                                        <label><i class="fas fa-user-check profile-form-label-icon"></i> Role</label>
                                        <input type="text" value="Verifier" disabled class="profile-form-input-disabled">
                                        <span class="profile-form-note">Role cannot be changed.</span>
                                    </div>
                                </div>
                            </div>
                            <div class="profile-form-block">
                                <h3 class="profile-form-block-title">
                                    <i class="fas fa-lock profile-form-block-icon"></i>
                                    <span>Change Password</span>
                                </h3>
                                <div class="profile-form-grid">
                                    <div class="profile-form-row profile-form-row--full">
                                        <label for="currentPassword"><i class="fas fa-lock profile-form-label-icon"></i> Current password</label>
                                        <div class="profile-form-input-wrap">
                                            <input type="password" id="currentPassword" name="current_password" autocomplete="current-password" placeholder="Leave blank to keep current">
                                            <button type="button" class="toggle-password" aria-label="Toggle password"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="profile-form-row">
                                        <label for="newPassword"><i class="fas fa-key profile-form-label-icon"></i> New password</label>
                                        <div class="profile-form-input-wrap">
                                            <input type="password" id="newPassword" name="new_password" minlength="8" autocomplete="new-password" placeholder="Min 8 characters">
                                            <button type="button" class="toggle-password" aria-label="Toggle password"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>
                                    <div class="profile-form-row">
                                        <label for="confirmPassword"><i class="fas fa-key profile-form-label-icon"></i> Confirm new password</label>
                                        <div class="profile-form-input-wrap">
                                            <input type="password" id="confirmPassword" name="confirm_password" minlength="8" autocomplete="new-password" placeholder="Repeat new password">
                                            <button type="button" class="toggle-password" aria-label="Toggle password"><i class="fas fa-eye"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="profile-btn profile-btn-primary" id="profileSaveBtn"><i class="fas fa-save"></i> Save changes</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>
    <div id="errorPopup" class="error-popup">
        <div class="error-content">
            <i class="fas fa-exclamation-circle"></i>
            <h4>Error</h4>
            <p id="errorMessage">An error occurred. Please try again.</p>
            <button type="button" class="profile-btn profile-btn-danger" onclick="hideErrorPopup()"><i class="fas fa-times"></i> Close</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/admin_profile.js"></script>
</body>
</html>
