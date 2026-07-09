<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    header('Location: ../../../index.php');
    exit();
}

// Initialize so template never hits undefined variables (avoids 500 on Hostinger when a query fails)
$counts = [
    'plantations' => 0, 'pending' => 0, 'validated' => 0, 'registered' => 0,
    'approved_permits' => 0, 'pending_permits' => 0, 'documents' => 0
];
$tree_species_counts = [];
$activities = [];
$monthly_activities = [];
$error = false;

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

if (!$db) {
    $error = true;
} else try {
    // Total Plantations with status counts
    $plantation_query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated,
                SUM(CASE WHEN status = 'registered' THEN 1 ELSE 0 END) as registered
              FROM plantations 
              WHERE user_id = :user_id";
    $stmt = $db->prepare($plantation_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $plantation_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch permit counts
    $permits_query = "SELECT 
                     COUNT(CASE WHEN p.status = 'approved' THEN 1 END) as approved_permits,
                     COUNT(CASE WHEN p.status = 'pending' THEN 1 END) as pending_permits
                     FROM permits p
                     JOIN plantations pl ON p.plantation_id = pl.plantation_id
                     WHERE pl.user_id = :user_id";
    $stmt = $db->prepare($permits_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $permit_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Add document count query
    $documents_query = "SELECT COUNT(*) as total_documents 
                       FROM documents d 
                       JOIN plantations p ON d.plantation_id = p.plantation_id 
                       WHERE p.user_id = :user_id";
    $stmt = $db->prepare($documents_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $document_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    $counts['plantations'] = $plantation_stats['total'] ?? 0;
    $counts['pending'] = $plantation_stats['pending'] ?? 0;
    $counts['validated'] = $plantation_stats['validated'] ?? 0;
    $counts['registered'] = $plantation_stats['registered'] ?? 0;
    $counts['approved_permits'] = $permit_stats['approved_permits'] ?? 0;
    $counts['pending_permits'] = $permit_stats['pending_permits'] ?? 0;
    $counts['documents'] = $document_stats['total_documents'] ?? 0;

    // Tree species: sum of quantities per species (format "Name:Qty,Name:Qty" or legacy "Name, Name")
    $tree_species_query = "SELECT tree_species FROM plantations 
                           WHERE user_id = :user_id 
                           AND tree_species IS NOT NULL 
                           AND TRIM(tree_species) != ''";
    $stmt = $db->prepare($tree_species_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $by_species = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $parts = array_map('trim', explode(',', $row['tree_species']));
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (strpos($p, ':') !== false) {
                list($name, $qty) = explode(':', $p, 2);
                $name = trim($name);
                $qty = (int) $qty;
                if ($name !== '') $by_species[$name] = ($by_species[$name] ?? 0) + $qty;
            } else {
                $by_species[$p] = ($by_species[$p] ?? 0) + 1;
            }
        }
    }
    $tree_species_counts = [];
    foreach ($by_species as $species_name => $num) {
        $tree_species_counts[] = ['species_name' => $species_name, 'num' => $num];
    }
    usort($tree_species_counts, function ($a, $b) { return $b['num'] - $a['num']; });

    // Recent Activities Query
    $activities_query = "
        SELECT * FROM (
            SELECT 
                'plantation' as type,
                p.plantation_name,
                p.status,
                p.registered_at as activity_date,
                NULL as permit_type,
                NULL as doc_type
            FROM plantations p 
            WHERE p.user_id = :user_id
            
            UNION ALL
            
            SELECT 
                'permit' as type,
                pl.plantation_name,
                pm.status,
                pm.requested_at as activity_date,
                pm.permit_type,
                NULL as doc_type
            FROM permits pm
            JOIN plantations pl ON pm.plantation_id = pl.plantation_id
            WHERE pl.user_id = :user_id
            
            UNION ALL
            
            SELECT 
                'document' as type,
                pl.plantation_name,
                NULL as status,
                d.uploaded_at as activity_date,
                NULL as permit_type,
                d.file_name as doc_type
            FROM documents d
            JOIN plantations pl ON d.plantation_id = pl.plantation_id
            WHERE pl.user_id = :user_id
        ) activities
        WHERE activity_date IS NOT NULL
        ORDER BY activity_date DESC
        LIMIT 3";

    $stmt = $db->prepare($activities_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly Activities Query
    $monthly_activities_query = "
        SELECT 
            DATE_FORMAT(activity_date, '%Y-%m') as month,
            COUNT(*) as count
        FROM (
            SELECT registered_at as activity_date FROM plantations WHERE user_id = :user_id
            UNION ALL
            SELECT requested_at FROM permits p 
            JOIN plantations pl ON p.plantation_id = pl.plantation_id 
            WHERE pl.user_id = :user_id
        ) activities
        GROUP BY DATE_FORMAT(activity_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6";

    $stmt = $db->prepare($monthly_activities_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $monthly_activities = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    error_log("Landowner dashboard Error: " . $e->getMessage());
    $error = true;
    $activities = [];
    $tree_species_counts = [];
}

// timeAgo helper to use database timestamp directly
function timeAgo($timestamp)
{
    if (!$timestamp) return '';

    date_default_timezone_set('Asia/Manila');
    $timestamp = strtotime($timestamp);
    $currentTime = time();
    $timeDiff = $currentTime - $timestamp;

    $intervals = [
        31536000 => 'yr',
        2592000 => 'mon',
        86400 => 'day',
        3600 => 'hr',
        60 => 'min',
        1 => 'sec'
    ];

    foreach ($intervals as $secs => $str) {
        $d = $timeDiff / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . $str . ($r > 1 ? 's' : '');
        }
    }

    return 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landowner Dashboard - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/landowner.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png" />

</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation -->
            <?php include __DIR__ . '/../../../includes/header.php'; ?>

            <!-- Dashboard Content (same style as Admin Dashboard) -->
            <div class="dashboard-content admin-dashboard">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Dashboard</h1>
                        <p class="admin-dashboard-subtitle">Overview of your plantations and permits.</p>
                    </div>
                </header>

                <section class="admin-kpis">
                    <a href="../plantations/plantations.php" class="admin-kpi admin-kpi--primary">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Total Plantations</span>
                            <span class="admin-kpi-value"><?php echo (int)($counts['plantations'] ?? 0); ?></span>
                            <span class="admin-kpi-meta">Recorded</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-seedling"></i></span>
                    </a>
                    <a href="../permits/permits.php?status=approved" class="admin-kpi admin-kpi--success">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Approved Permits</span>
                            <span class="admin-kpi-value"><?php echo (int)($counts['approved_permits'] ?? 0); ?></span>
                            <span class="admin-kpi-meta">All time</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-check-circle"></i></span>
                    </a>
                    <a href="../permits/permits.php?status=pending" class="admin-kpi admin-kpi--warning">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Pending Requests</span>
                            <span class="admin-kpi-value"><?php echo (int)($counts['pending_permits'] ?? 0); ?></span>
                            <span class="admin-kpi-meta">Awaiting review</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
                    </a>
                    <a href="../documents/documents.php" class="admin-kpi admin-kpi--info">
                        <div class="admin-kpi-body">
                            <span class="admin-kpi-label">Total Documents</span>
                            <span class="admin-kpi-value"><?php echo (int)($counts['documents'] ?? 0); ?></span>
                            <span class="admin-kpi-meta">Uploaded</span>
                        </div>
                        <span class="admin-kpi-icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                    </a>
                </section>

                <section class="admin-charts-row">
                    <div class="admin-card admin-chart-card">
                        <div class="admin-card-head">
                            <h3>Plantation Distribution</h3>
                        </div>
                        <div class="admin-card-body admin-chart-wrap">
                            <canvas id="statusChart" height="220"></canvas>
                        </div>
                    </div>
                </section>

                <section class="admin-dashboard-grid">
                    <div class="admin-card admin-card-attention">
                        <div class="admin-card-head">
                            <h3>Needs Attention</h3>
                        </div>
                        <div class="admin-card-body">
                            <div class="admin-attention-list">
                                <a href="../plantations/plantations.php?status=pending" class="admin-attention-item">
                                    <span class="admin-attention-icon warning"><i class="fas fa-seedling"></i></span>
                                    <div class="admin-attention-content">
                                        <span class="admin-attention-label">Pending Plantations</span>
                                        <span class="admin-attention-value"><?php echo (int)($counts['pending'] ?? 0); ?></span>
                                    </div>
                                    <span class="admin-attention-arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                <a href="../permits/permits.php?status=pending" class="admin-attention-item">
                                    <span class="admin-attention-icon warning"><i class="fas fa-file-alt"></i></span>
                                    <div class="admin-attention-content">
                                        <span class="admin-attention-label">Pending Permits</span>
                                        <span class="admin-attention-value"><?php echo (int)($counts['pending_permits'] ?? 0); ?></span>
                                    </div>
                                    <span class="admin-attention-arrow"><i class="fas fa-chevron-right"></i></span>
                                </a>
                                <a href="../plantations/plantations.php" class="admin-attention-item">
                                    <span class="admin-attention-icon success"><i class="fas fa-seedling"></i></span>
                                    <div class="admin-attention-content">
                                        <span class="admin-attention-label">Plantations by status</span>
                                        <span class="admin-attention-desc"><?php echo (int)($counts['registered'] ?? 0); ?> registered · <?php echo (int)($counts['validated'] ?? 0); ?> validated</span>
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
                            <?php if (empty($activities)): ?>
                                <div class="admin-empty-state">
                                    <i class="fas fa-inbox admin-empty-icon"></i>
                                    <p>No recent activities yet.</p>
                                </div>
                            <?php else: ?>
                                <ul class="admin-activity-list">
                                    <?php foreach ($activities as $activity):
                                        $icon = 'fa-circle';
                                        $iconClass = 'muted';
                                        $text = '';
                                        switch ($activity['type']) {
                                            case 'plantation':
                                                $icon = 'fa-seedling';
                                                $iconClass = $activity['status'] === 'registered' ? 'success' : ($activity['status'] === 'validated' ? 'info' : 'warning');
                                                $text = 'Plantation ' . ($activity['status'] ? ucfirst($activity['status']) : '') . ' — ' . htmlspecialchars($activity['plantation_name']);
                                                break;
                                            case 'permit':
                                                $icon = 'fa-file-signature';
                                                $iconClass = $activity['status'] === 'approved' ? 'success' : 'warning';
                                                $text = (isset($activity['permit_type']) ? ucfirst($activity['permit_type']) : '') . ' permit ' . ($activity['status'] ? ucfirst($activity['status']) : '') . ' — ' . htmlspecialchars($activity['plantation_name']);
                                                break;
                                            case 'document':
                                                $icon = 'fa-file-upload';
                                                $iconClass = 'primary';
                                                $text = 'Document uploaded — ' . htmlspecialchars($activity['plantation_name']) . (isset($activity['doc_type']) && $activity['doc_type'] ? ' · ' . htmlspecialchars($activity['doc_type']) : '');
                                                break;
                                        }
                                        $when = timeAgo($activity['activity_date']);
                                    ?>
                                    <li class="admin-activity-item">
                                        <span class="admin-activity-icon <?php echo $iconClass; ?>"><i class="fas <?php echo $icon; ?>"></i></span>
                                        <div class="admin-activity-content">
                                            <p class="admin-activity-text"><?php echo $text; ?></p>
                                            <span class="admin-activity-meta"><?php echo $when; ?></span>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head">
                        <h3><i class="fas fa-leaf"></i> Number of Tree Species</h3>
                    </div>
                    <div class="admin-card-body">
                        <?php if (empty($tree_species_counts)): ?>
                        <p class="text-muted mb-0 small">No tree species recorded yet. Add plantations to see species count.</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 admin-tree-species-table">
                                <thead>
                                    <tr>
                                        <th>Tree Species</th>
                                        <th class="text-end">Total trees</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tree_species_counts as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['species_name']); ?></td>
                                        <td class="text-end"><?php echo (int)$row['num']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="admin-card">
                    <div class="admin-card-head">
                        <h3>Quick Actions</h3>
                    </div>
                    <div class="admin-card-body">
                        <div class="admin-quick-actions">
                            <a href="../plantations/plantations.php" class="admin-quick-action-btn"><i class="fas fa-seedling"></i><span>My Plantations</span></a>
                            <a href="../permits/permits.php" class="admin-quick-action-btn"><i class="fas fa-file-alt"></i><span>Permits</span></a>
                            <a href="../documents/documents.php" class="admin-quick-action-btn"><i class="fas fa-file-upload"></i><span>Documents</span></a>
                            <a href="../profile/profile.php" class="admin-quick-action-btn"><i class="fas fa-user"></i><span>Profile</span></a>
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

            var statusCtx = document.getElementById('statusChart');
            if (statusCtx && typeof Chart !== 'undefined') {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending', 'Validated', 'Registered'],
                        datasets: [{
                            data: [
                                <?php echo (int)($counts['pending'] ?? 0); ?>,
                                <?php echo (int)($counts['validated'] ?? 0); ?>,
                                <?php echo (int)($counts['registered'] ?? 0); ?>
                            ],
                            backgroundColor: ['#ffc107', '#17a2b8', '#198754'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }
        });
    </script>
</body>

</html>