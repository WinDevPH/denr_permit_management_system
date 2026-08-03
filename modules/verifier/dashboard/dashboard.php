<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'verifier') {
    header('Location: ../../../index.php');
    exit();
}

require_once __DIR__ . '/../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$stats = [
    'pending_plantations' => 0,
    'pending_permits'     => 0,
    'validated'           => 0,
    'registered'          => 0,
    'approved_permits'    => 0,
    'rejected_permits'    => 0,
];

try {
    $stats['pending_plantations'] = (int) $db->query("SELECT COUNT(*) FROM plantations WHERE status = 'pending'")->fetchColumn();
    $stats['pending_permits']     = (int) $db->query("SELECT COUNT(*) FROM permits WHERE status = 'pending'")->fetchColumn();
    $stats['validated']           = (int) $db->query("SELECT COUNT(*) FROM plantations WHERE status = 'validated'")->fetchColumn();
    $stats['registered']          = (int) $db->query("SELECT COUNT(*) FROM plantations WHERE status = 'registered'")->fetchColumn();
    $stats['approved_permits']    = (int) $db->query("SELECT COUNT(*) FROM permits WHERE status = 'approved'")->fetchColumn();
    $stats['rejected_permits']    = (int) $db->query("SELECT COUNT(*) FROM permits WHERE status = 'rejected'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Verifier dashboard: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifier Dashboard - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png" />
</head>

<body>
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../verifier_includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../../../verifier_includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Verifier Dashboard</h1>
                        <p class="admin-dashboard-subtitle">Review and verify plantations and permits.</p>
                    </div>
                    <div class="dashboard-brand-logos" aria-label="Official logos">
                        <img src="../../../assets/img/denrlogo.png" alt="DENR" class="dashboard-brand-logo">
                        <img src="../../../assets/img/fmb_logo.png" alt="Forest Management Bureau (FMB)" class="dashboard-brand-logo dashboard-brand-logo-fmb">
                        <span class="dashboard-brand-caption">FMB · Forest Management Bureau</span>
                    </div>
                </header>

                <section class="admin-kpis">
                    <a href="../plantations/plantations.php?status=pending" class="admin-kpi admin-kpi--warning">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Pending Plantations</span>
                            <span class="admin-kpi-value"><?php echo $stats['pending_plantations']; ?></span>
                            <span class="admin-kpi-meta">Awaiting verification</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-seedling"></i></span>
                    </a>
                    <a href="../permits/permits.php?status=pending" class="admin-kpi admin-kpi--warning">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Pending Permits</span>
                            <span class="admin-kpi-value"><?php echo $stats['pending_permits']; ?></span>
                            <span class="admin-kpi-meta">Awaiting verification</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                    </a>
                    <a href="../plantations/plantations.php?status=validated" class="admin-kpi admin-kpi--info">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Checked</span>
                            <span class="admin-kpi-value"><?php echo $stats['validated']; ?></span>
                            <span class="admin-kpi-meta">Plantations</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-check"></i></span>
                    </a>
                    <a href="../plantations/plantations.php?status=registered" class="admin-kpi admin-kpi--success">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Registered</span>
                            <span class="admin-kpi-value"><?php echo $stats['registered']; ?></span>
                            <span class="admin-kpi-meta">Plantations</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-clipboard-check"></i></span>
                    </a>
                    <a href="../permits/permits.php?status=approved" class="admin-kpi admin-kpi--success">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Approved Permits</span>
                            <span class="admin-kpi-value"><?php echo $stats['approved_permits']; ?></span>
                            <span class="admin-kpi-meta">All time</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-file-signature"></i></span>
                    </a>
                </section>

                <section class="admin-card admin-card-attention">
                    <div class="admin-card-head">
                        <h3>Needs Attention</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="admin-attention-list">
                            <a href="../plantations/plantations.php?status=pending" class="admin-attention-item">
                                <span class="admin-attention-icon warning"><i class="fas fa-seedling"></i></span>
                                <div class="admin-attention-content">
                                    <span class="admin-attention-label">Pending Plantations</span>
                                    <span class="admin-attention-value"><?php echo $stats['pending_plantations']; ?></span>
                                </div>
                                <span class="admin-attention-arrow"><i class="fas fa-chevron-right"></i></span>
                            </a>
                            <a href="../permits/permits.php?status=pending" class="admin-attention-item">
                                <span class="admin-attention-icon warning"><i class="fas fa-file-alt"></i></span>
                                <div class="admin-attention-content">
                                    <span class="admin-attention-label">Pending Permits</span>
                                    <span class="admin-attention-value"><?php echo $stats['pending_permits']; ?></span>
                                </div>
                                <span class="admin-attention-arrow"><i class="fas fa-chevron-right"></i></span>
                            </a>
                        </div>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="admin-quick-actions">
                            <a href="../plantations/plantations.php" class="admin-quick-action-btn"><i class="fas fa-seedling"></i><span>Verify Plantations</span></a>
                            <a href="../permits/permits.php" class="admin-quick-action-btn"><i class="fas fa-file-alt"></i><span>Verify Permits</span></a>
                            <a href="../calendar/calendar.php" class="admin-quick-action-btn"><i class="fas fa-calendar-alt"></i><span>Calendar</span></a>
                            <a href="../profile/profile.php" class="admin-quick-action-btn"><i class="fas fa-user"></i><span>Profile</span></a>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body logout-modal">
                    <div class="logout-icon"><i class="fas fa-sign-out-alt"></i></div>
                    <h5>Sign Out</h5>
                    <p>Are you sure you want to sign out?</p>
                    <div class="logout-actions">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <a href="../../../handlers/logout.php" class="btn btn-danger">Sign Out</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.querySelectorAll('.admin-quick-action-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { this.classList.add('admin-loading'); });
    });
    </script>
</body>

</html>
