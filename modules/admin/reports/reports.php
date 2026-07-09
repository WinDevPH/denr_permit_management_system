<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit();
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-01-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'permits';

// Sanitize for queries
$start_safe = $db->quote($start_date);
$end_safe = $db->quote($end_date);

// Permits in date range
$permits = $db->query("SELECT p.*, pl.plantation_name FROM permits p 
    JOIN plantations pl ON p.plantation_id = pl.plantation_id 
    WHERE DATE(p.requested_at) BETWEEN $start_safe AND $end_safe 
    ORDER BY p.requested_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Plantations
$plantations = $db->query("SELECT * FROM plantations ORDER BY plantation_id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Stats for date range
$stats_permits = [
    'total' => (int) $db->query("SELECT COUNT(*) FROM permits WHERE DATE(requested_at) BETWEEN $start_safe AND $end_safe")->fetchColumn(),
    'approved' => (int) $db->query("SELECT COUNT(*) FROM permits WHERE status='approved' AND DATE(requested_at) BETWEEN $start_safe AND $end_safe")->fetchColumn(),
    'pending' => (int) $db->query("SELECT COUNT(*) FROM permits WHERE status='pending' AND DATE(requested_at) BETWEEN $start_safe AND $end_safe")->fetchColumn(),
    'rejected' => (int) $db->query("SELECT COUNT(*) FROM permits WHERE status='rejected' AND DATE(requested_at) BETWEEN $start_safe AND $end_safe")->fetchColumn(),
];
$stats_plantations = [
    'total' => (int) $db->query("SELECT COUNT(*) FROM plantations")->fetchColumn(),
    'registered' => (int) $db->query("SELECT COUNT(*) FROM plantations WHERE status='registered'")->fetchColumn(),
    'validated' => (int) $db->query("SELECT COUNT(*) FROM plantations WHERE status='validated'")->fetchColumn(),
    'pending' => (int) $db->query("SELECT COUNT(*) FROM plantations WHERE status='pending'")->fetchColumn(),
];
$stats_users = [
    'total' => (int) $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'admins' => (int) $db->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn(),
    'landowners' => (int) $db->query("SELECT COUNT(*) FROM users WHERE role='landowner'")->fetchColumn(),
];

// Export CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="DENR_Report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['DENR Report', 'Generated: ' . date('Y-m-d H:i:s'), 'Period: ' . $start_date . ' to ' . $end_date]);
    fputcsv($out, []);
    fputcsv($out, ['Permit ID', 'Plantation', 'Type', 'Status', 'Requested']);
    foreach ($permits as $p) {
        fputcsv($out, [$p['permit_id'], $p['plantation_name'] ?? '', $p['permit_type'] ?? '', $p['status'] ?? '', $p['requested_at'] ?? '']);
    }
    fputcsv($out, []);
    fputcsv($out, ['Plantation ID', 'Name', 'Species', 'Area', 'Status']);
    foreach ($plantations as $pl) {
        fputcsv($out, [$pl['plantation_id'], $pl['plantation_name'] ?? '', $pl['tree_species'] ?? '', $pl['land_area'] ?? '', $pl['status'] ?? '']);
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="admin-reports-page">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/reports.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>
<body class="admin-reports-page">
    <div class="dashboard-container">
        <?php include '../../../admin_includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include '../../../admin_includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard admin-reports">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Reports</h1>
                        <p class="admin-dashboard-subtitle">Generate permits, plantations and system reports. Choose a report type, set filters, and export.</p>
                    </div>
                </header>

                <nav class="reports-breadcrumb" aria-label="Breadcrumb">
                    <ol class="reports-breadcrumb-list">
                        <li class="reports-breadcrumb-item">
                            <a href="../dashboard/dashboard.php"><i class="fas fa-home"></i> <span>Dashboard</span></a>
                        </li>
                        <li class="reports-breadcrumb-sep" aria-hidden="true"><i class="fas fa-chevron-right"></i></li>
                        <li class="reports-breadcrumb-item reports-breadcrumb-item--current" aria-current="page">
                            <i class="fas fa-chart-bar"></i> <span>Reports</span>
                        </li>
                    </ol>
                </nav>

                <!-- Report Type -->
                <section class="reports-section reports-section-types">
                    <h2 class="reports-section-title">Report Type</h2>
                    <div class="reports-type-list" role="group" aria-label="Report type">
                        <button type="button" class="reports-type-card <?php echo $report_type === 'permits' ? 'is-active' : ''; ?>" data-report="permits" aria-pressed="<?php echo $report_type === 'permits' ? 'true' : 'false'; ?>">
                            <span class="reports-type-icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                            <span class="reports-type-body">
                                <span class="reports-type-label">Permits</span>
                                <span class="reports-type-desc">By date range</span>
                            </span>
                        </button>
                        <button type="button" class="reports-type-card <?php echo $report_type === 'plantations' ? 'is-active' : ''; ?>" data-report="plantations" aria-pressed="<?php echo $report_type === 'plantations' ? 'true' : 'false'; ?>">
                            <span class="reports-type-icon" aria-hidden="true"><i class="fas fa-tree"></i></span>
                            <span class="reports-type-body">
                                <span class="reports-type-label">Plantations</span>
                                <span class="reports-type-desc">All plantations</span>
                            </span>
                        </button>
                        <button type="button" class="reports-type-card <?php echo $report_type === 'system' ? 'is-active' : ''; ?>" data-report="system" aria-pressed="<?php echo $report_type === 'system' ? 'true' : 'false'; ?>">
                            <span class="reports-type-icon" aria-hidden="true"><i class="fas fa-users-cog"></i></span>
                            <span class="reports-type-body">
                                <span class="reports-type-label">System</span>
                                <span class="reports-type-desc">Users &amp; stats</span>
                            </span>
                        </button>
                    </div>
                </section>

                <!-- Filters -->
                <section class="reports-section reports-section-filters">
                    <h2 class="reports-section-title">Filters</h2>
                    <form method="get" class="reports-filters" id="reportsFilterForm">
                        <input type="hidden" name="report_type" id="reportsTypeInput" value="<?php echo htmlspecialchars($report_type); ?>">
                        <div class="reports-filter-row">
                            <div class="reports-filter-item">
                                <label for="reportsDateFrom" class="reports-filter-label">Date From</label>
                                <input type="date" id="reportsDateFrom" name="start_date" class="reports-filter-input" value="<?php echo htmlspecialchars($start_date); ?>" aria-label="Start date">
                            </div>
                            <div class="reports-filter-item">
                                <label for="reportsDateTo" class="reports-filter-label">Date To</label>
                                <input type="date" id="reportsDateTo" name="end_date" class="reports-filter-input" value="<?php echo htmlspecialchars($end_date); ?>" aria-label="End date">
                            </div>
                            <div class="reports-filter-actions">
                                <button type="submit" class="reports-btn reports-btn-primary" id="reportsGenerateBtn">
                                    <i class="fas fa-play"></i> <span>Generate Report</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Output -->
                <section class="reports-section reports-section-output">
                    <div class="reports-output-card">
                        <header class="reports-output-header">
                            <h2 class="reports-output-title">Report Results</h2>
                            <div class="reports-output-actions">
                                <button type="button" class="reports-action-btn" id="reportsPrintBtn" title="Print" aria-label="Print report">
                                    <i class="fas fa-print"></i> <span>Print</span>
                                </button>
                                <a href="?report_type=<?php echo urlencode($report_type); ?>&amp;start_date=<?php echo urlencode($start_date); ?>&amp;end_date=<?php echo urlencode($end_date); ?>&amp;export=csv" class="reports-action-btn" id="reportsCsvBtn" title="Download CSV" aria-label="Download as CSV">
                                    <i class="fas fa-download"></i> <span>Download</span>
                                </a>
                            </div>
                        </header>
                        <div class="reports-output-body" id="reportsOutputBody">
                            <?php
                            $has_data = ($report_type === 'permits' && count($permits) > 0) || ($report_type === 'plantations' && count($plantations) > 0) || $report_type === 'system';
                            ?>
                            <div class="reports-empty" id="reportsEmptyState" <?php echo $has_data ? 'hidden' : ''; ?>>
                                <span class="reports-empty-icon" aria-hidden="true"><i class="fas fa-file-alt"></i></span>
                                <p class="reports-empty-title">No report yet</p>
                                <p class="reports-empty-text">Select a report type, set your date range, then click <strong>Generate Report</strong>.</p>
                            </div>
                            <div class="reports-table-wrap" id="reportsTableWrap" <?php echo !$has_data ? 'hidden' : ''; ?>>
                                <div class="reports-table-scroll">
                                    <?php if ($report_type === 'permits'): ?>
                                    <table class="reports-table" id="reportsTable">
                                        <thead><tr><th>ID</th><th>Plantation</th><th>Type</th><th>Status</th><th>Requested</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($permits as $p): ?>
                                            <tr>
                                                <td><?php echo (int)$p['permit_id']; ?></td>
                                                <td><?php echo htmlspecialchars($p['plantation_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($p['permit_type'] ?? '')); ?></td>
                                                <td><span class="reports-status-badge status-<?php echo htmlspecialchars($p['status'] ?? ''); ?>"><?php echo htmlspecialchars(ucfirst($p['status'] ?? '')); ?></span></td>
                                                <td><?php echo !empty($p['requested_at']) ? date('M j, Y', strtotime($p['requested_at'])) : '—'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php elseif ($report_type === 'plantations'): ?>
                                    <table class="reports-table" id="reportsTable">
                                        <thead><tr><th>ID</th><th>Name</th><th>Species</th><th>Area (ha)</th><th>Status</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($plantations as $pl): ?>
                                            <tr>
                                                <td><?php echo (int)$pl['plantation_id']; ?></td>
                                                <td><?php echo htmlspecialchars($pl['plantation_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($pl['tree_species'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($pl['land_area'] ?? '—'); ?></td>
                                                <td><span class="reports-status-badge status-<?php echo htmlspecialchars($pl['status'] ?? ''); ?>"><?php echo htmlspecialchars(ucfirst($pl['status'] ?? '')); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <?php else: ?>
                                    <table class="reports-table" id="reportsTable">
                                        <thead><tr><th>Category</th><th>Total</th><th>Detail</th></tr></thead>
                                        <tbody>
                                            <tr><td>Permits (in range)</td><td><?php echo $stats_permits['total']; ?></td><td>Approved: <?php echo $stats_permits['approved']; ?>, Pending: <?php echo $stats_permits['pending']; ?>, Rejected: <?php echo $stats_permits['rejected']; ?></td></tr>
                                            <tr><td>Plantations</td><td><?php echo $stats_plantations['total']; ?></td><td>Registered: <?php echo $stats_plantations['registered']; ?>, Validated: <?php echo $stats_plantations['validated']; ?>, Pending: <?php echo $stats_plantations['pending']; ?></td></tr>
                                            <tr><td>Users</td><td><?php echo $stats_users['total']; ?></td><td>Admins: <?php echo $stats_users['admins']; ?>, Landowners: <?php echo $stats_users['landowners']; ?></td></tr>
                                        </tbody>
                                    </table>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/reports.js"></script>
</body>
</html>
