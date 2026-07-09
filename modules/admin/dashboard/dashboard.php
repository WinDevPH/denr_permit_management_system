<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit();
}

require_once '../../../config/database.php';

// Initialize database connection for admin stats
$database = new Database();
$db = $database->getConnection();

try {
    // Get total users count
    $users_query = "SELECT COUNT(*) as total_users FROM users";
    $stmt = $db->prepare($users_query);
    $stmt->execute();
    $users_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get total plantations count
    $plantations_query = "SELECT COUNT(*) as total_plantations FROM plantations";
    $stmt = $db->prepare($plantations_query);
    $stmt->execute();
    $plantations_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get pending permits count
    $permits_query = "SELECT COUNT(*) as pending_permits FROM permits WHERE status = 'pending'";
    $stmt = $db->prepare($permits_query);
    $stmt->execute();
    $permits_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get total permits count
    $total_permits_query = "SELECT COUNT(*) as total_permits FROM permits";
    $stmt = $db->prepare($total_permits_query);
    $stmt->execute();
    $total_permits_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // New registrations this month
    $new_users_query = "SELECT COUNT(*) as new_users FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $stmt = $db->prepare($new_users_query);
    $stmt->execute();
    $new_users_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Approved permits this month
    $approved_permits_query = "SELECT COUNT(*) as approved_permits FROM permits WHERE status = 'approved' AND MONTH(approved_at) = MONTH(CURRENT_DATE()) AND YEAR(approved_at) = YEAR(CURRENT_DATE())";
    $stmt = $db->prepare($approved_permits_query);
    $stmt->execute();
    $approved_permits_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pending reviews: pending plantations + pending permits
    $pending_plantations_query = "SELECT COUNT(*) as pending_plantations FROM plantations WHERE status = 'pending'";
    $stmt = $db->prepare($pending_plantations_query);
    $stmt->execute();
    $pending_plantations_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $pending_reviews = ($pending_plantations_stats['pending_plantations'] ?? 0) + ($permits_stats['pending_permits'] ?? 0);

    // Permits by status (for doughnut chart)
    $permits_by_status = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    foreach (['pending', 'approved', 'rejected'] as $s) {
        $q = $db->prepare("SELECT COUNT(*) FROM permits WHERE status = ?");
        $q->execute([$s]);
        $permits_by_status[$s] = (int) $q->fetchColumn();
    }

    // Last 6 months: users & permits (for bar chart)
    $chart_months = [];
    $chart_users = [];
    $chart_permits = [];
    for ($i = 5; $i >= 0; $i--) {
        $d = new DateTime("first day of -$i months");
        $chart_months[] = $d->format('M Y');
        $m = $d->format('m');
        $y = $d->format('Y');
        $next = (clone $d)->modify('+1 month');
        $m2 = $next->format('m');
        $y2 = $next->format('Y');
        $uq = $db->prepare("SELECT COUNT(*) FROM users WHERE created_at >= ? AND created_at < ?");
        $uq->execute([$d->format('Y-m-d'), $next->format('Y-m-d')]);
        $chart_users[] = (int) $uq->fetchColumn();
        $pq = $db->prepare("SELECT COUNT(*) FROM permits WHERE requested_at >= ? AND requested_at < ?");
        $pq->execute([$d->format('Y-m-d'), $next->format('Y-m-d')]);
        $chart_permits[] = (int) $pq->fetchColumn();
    }

    // Recent activities from audit_logs (join users for name)
    $recent_activities = [];
    try {
        $act_query = "SELECT a.log_id, a.user_id, a.action, a.module, a.created_at, u.full_name
                      FROM audit_logs a
                      LEFT JOIN users u ON a.user_id = u.user_id
                      ORDER BY a.created_at DESC
                      LIMIT 3";
        $act_stmt = $db->query($act_query);
        if ($act_stmt) {
            $recent_activities = $act_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $recent_activities = [];
    }

} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $users_stats = ['total_users' => 0];
    $plantations_stats = ['total_plantations' => 0];
    $permits_stats = ['pending_permits' => 0];
    $total_permits_stats = ['total_permits' => 0];
    $new_users_stats = ['new_users' => 0];
    $approved_permits_stats = ['approved_permits' => 0];
    $pending_plantations_stats = ['pending_plantations' => 0];
    $pending_reviews = 0;
    $permits_by_status = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    $chart_months = array_fill(0, 6, '');
    $chart_users = array_fill(0, 6, 0);
    $chart_permits = array_fill(0, 6, 0);
    $recent_activities = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - DENR Digital System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png" />
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../../../admin_includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation -->
            <?php include '../../../admin_includes/header.php'; ?>

            <!-- Dashboard Content -->
            <div class="dashboard-content admin-dashboard">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Dashboard</h1>
                        <p class="admin-dashboard-subtitle">Overview of DENR Digital System</p>
                    </div>
                </header>

                <!-- KPI stats cards (Attendify-style: icon top-right, rounded card) -->
                <section class="admin-kpis">
                    <a href="../users/users.php" class="admin-kpi admin-kpi--primary">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Total Users</span>
                            <span class="admin-kpi-value"><?php echo (int)($users_stats['total_users'] ?? 0); ?></span>
                            <span class="admin-kpi-meta">Registered in system</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                    </a>
                    <a href="../plantations/plantations.php" class="admin-kpi admin-kpi--success">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Plantations</span>
                            <span class="admin-kpi-value"><?php echo (int)($plantations_stats['total_plantations'] ?? 0); ?></span>
                            <span class="admin-kpi-meta">Total recorded</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-seedling"></i></span>
                    </a>
                    <a href="../permits/permits.php?status=pending" class="admin-kpi admin-kpi--warning">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Pending Permits</span>
                            <span class="admin-kpi-value"><?php echo (int)($permits_stats['pending_permits'] ?? 0); ?></span>
                            <span class="admin-kpi-meta">Awaiting review</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                    </a>
                    <a href="../permits/permits.php" class="admin-kpi admin-kpi--info">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Total Permits</span>
                            <span class="admin-kpi-value"><?php echo (int)($total_permits_stats['total_permits'] ?? 0); ?></span>
                            <span class="admin-kpi-meta">All time</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-file-signature"></i></span>
                    </a>
                </section>

                <!-- Charts Row -->
                <section class="admin-charts-row">
                    <div class="admin-card admin-chart-card">
                        <div class="admin-card-head">
                            <h3>Permits by Status</h3>
                        </div>
                        <div class="admin-card-body admin-chart-wrap">
                            <canvas id="chartPermitsStatus" height="220"></canvas>
                        </div>
                    </div>
                    <div class="admin-card admin-chart-card">
                        <div class="admin-card-head">
                            <h3>Activity (Last 6 Months)</h3>
                        </div>
                        <div class="admin-card-body admin-chart-wrap">
                            <canvas id="chartActivity" height="220"></canvas>
                        </div>
                    </div>
                </section>

                <!-- Needs Attention + Recent Activity -->
                <section class="admin-dashboard-grid">
                    <div class="admin-card admin-card-attention">
                        <div class="admin-card-head">
                            <h3>Needs Attention</h3>
                        </div>
                        <div class="admin-card-body">
                            <div class="admin-attention-list">
                                <a href="../plantations/plantations.php?status=pending" class="admin-attention-item">
                                    <span class="admin-attention-icon success"><i class="fas fa-seedling"></i></span>
                                    <div class="admin-attention-content">
                                        <span class="admin-attention-label">Pending Plantations</span>
                                        <span class="admin-attention-value"><?php echo (int)($pending_plantations_stats['pending_plantations'] ?? 0); ?></span>
                                    </div>
                                    <span class="admin-attention-arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                <a href="../permits/permits.php?status=pending" class="admin-attention-item">
                                    <span class="admin-attention-icon warning"><i class="fas fa-file-alt"></i></span>
                                    <div class="admin-attention-content">
                                        <span class="admin-attention-label">Pending Permits</span>
                                        <span class="admin-attention-value"><?php echo (int)($permits_stats['pending_permits'] ?? 0); ?></span>
                                    </div>
                                    <span class="admin-attention-arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                <a href="../permits/permits.php" class="admin-attention-item">
                                    <span class="admin-attention-icon info"><i class="fas fa-file-signature"></i></span>
                                    <div class="admin-attention-content">
                                        <span class="admin-attention-label">Permits by status</span>
                                        <span class="admin-attention-desc"><?php echo (int)($permits_by_status['approved'] ?? 0); ?> approved · <?php echo (int)($permits_by_status['rejected'] ?? 0); ?> rejected</span>
                                    </div>
                                    <span class="admin-attention-arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="admin-card">
                        <div class="admin-card-head">
                            <h3>Recent Activity</h3>
                        </div>
                        <div class="admin-card-body">
                            <?php if (empty($recent_activities)): ?>
                                <div class="admin-empty-state">
                                    <i class="fas fa-inbox admin-empty-icon"></i>
                                    <p>No recent activities yet.</p>
                                </div>
                            <?php else: ?>
                                <ul class="admin-activity-list">
                                    <?php foreach ($recent_activities as $act):
                                        $module = $act['module'] ?? '';
                                        $icon = 'fa-circle';
                                        $iconClass = 'muted';
                                        if ($module === 'permits') { $icon = 'fa-file-alt'; $iconClass = 'info'; }
                                        elseif ($module === 'plantations') { $icon = 'fa-seedling'; $iconClass = 'success'; }
                                        elseif ($module === 'users') { $icon = 'fa-user'; $iconClass = 'primary'; }
                                        $who = !empty($act['full_name']) ? htmlspecialchars($act['full_name']) : 'User #' . (int)$act['user_id'];
                                        $when = date('M j, Y g:i A', strtotime($act['created_at']));
                                    ?>
                                    <li class="admin-activity-item">
                                        <span class="admin-activity-icon <?php echo $iconClass; ?>"><i class="fas <?php echo $icon; ?>"></i></span>
                                        <div class="admin-activity-content">
                                            <p class="admin-activity-text"><?php echo htmlspecialchars($act['action']); ?></p>
                                            <span class="admin-activity-meta"><?php echo $who; ?> · <?php echo $when; ?></span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Quick Actions -->
                <section class="admin-card">
                    <div class="admin-card-head">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="admin-quick-actions">
                            <a href="../users/users.php" class="admin-quick-action-btn"><i class="fas fa-users"></i><span>Manage Users</span></a>
                            <a href="../plantations/plantations.php" class="admin-quick-action-btn"><i class="fas fa-seedling"></i><span>Plantations</span></a>
                            <a href="../permits/permits.php" class="admin-quick-action-btn"><i class="fas fa-file-alt"></i><span>Review Permits</span></a>
                            <a href="../reports/reports.php" class="admin-quick-action-btn"><i class="fas fa-chart-line"></i><span>Reports</span></a>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content">
                <div class="modal-body logout-modal">
                    <div class="logout-icon">
                        <i class="fas fa-sign-out-alt"></i>
                    </div>
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
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(el) { return new bootstrap.Tooltip(el); });

        document.querySelectorAll('.admin-quick-action-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { this.classList.add('admin-loading'); });
        });

        var chartData = {
            permitsStatus: <?php echo json_encode($permits_by_status); ?>,
            months: <?php echo json_encode($chart_months); ?>,
            users: <?php echo json_encode($chart_users); ?>,
            permits: <?php echo json_encode($chart_permits); ?>
        };

        var doughnutCtx = document.getElementById('chartPermitsStatus');
        if (doughnutCtx && typeof Chart !== 'undefined') {
            new Chart(doughnutCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Approved', 'Rejected'],
                    datasets: [{
                        data: [chartData.permitsStatus.pending, chartData.permitsStatus.approved, chartData.permitsStatus.rejected],
                        backgroundColor: ['#ffc107', '#198754', '#dc3545'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        var barCtx = document.getElementById('chartActivity');
        if (barCtx && typeof Chart !== 'undefined') {
            new Chart(barCtx, {
                type: 'bar',
                data: {
                    labels: chartData.months,
                    datasets: [
                        { label: 'New Users', data: chartData.users, backgroundColor: 'rgba(0, 124, 54, 0.7)', borderRadius: 4 },
                        { label: 'Permit Requests', data: chartData.permits, backgroundColor: 'rgba(23, 162, 184, 0.7)', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    },
                    plugins: {
                        legend: { position: 'top' }
                    }
                }
            });
        }
    });
    </script>
</body>

</html>