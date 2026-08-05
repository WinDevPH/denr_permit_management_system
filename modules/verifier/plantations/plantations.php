<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'verifier') {
    header('Location: ../../../index.php');
    exit();
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Pagination and filters (same as Users / Attendify: 5 per page default)
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page_options = [5, 10, 25, 50];
$per_page_request = (int)($_GET['per_page'] ?? 5);
$limit = in_array($per_page_request, $per_page_options) ? $per_page_request : 5;
$offset = ($page - 1) * $limit;

$where_conditions = [];
$params = [];
if ($search !== '') {
    $where_conditions[] = "(p.plantation_name LIKE ? OR u.full_name LIKE ? OR p.tree_species LIKE ?)";
    $q = "%{$search}%";
    $params[] = $q;
    $params[] = $q;
    $params[] = $q;
}
if ($status_filter !== '') {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}
$where_clause = count($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$order_by = 'p.plantation_id DESC';
if ($sort === 'oldest') $order_by = 'p.plantation_id ASC';
elseif ($sort === 'name') $order_by = 'p.plantation_name ASC';
elseif ($sort === 'area') $order_by = 'CAST(p.land_area AS DECIMAL(10,2)) DESC';

// Count for pagination
$count_sql = "SELECT COUNT(*) FROM plantations p JOIN users u ON p.user_id = u.user_id $where_clause";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_plantations = (int) $count_stmt->fetchColumn();
$total_pages = $total_plantations > 0 ? (int) ceil($total_plantations / $limit) : 1;
$page = min($page, max(1, $total_pages));

// Fetch current page
$query = "SELECT p.*, u.full_name as owner_name, u.profile_img as owner_profile_img 
          FROM plantations p 
          JOIN users u ON p.user_id = u.user_id 
          $where_clause 
          ORDER BY $order_by 
          LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
$stmt = $db->prepare($query);
$stmt->execute($params);
$plantations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add statistics queries
$stats = [
    'total' => $db->query("SELECT COUNT(*) FROM plantations")->fetchColumn(),
    'pending' => $db->query("SELECT COUNT(*) FROM plantations WHERE status='pending'")->fetchColumn(),
    'validated' => $db->query("SELECT COUNT(*) FROM plantations WHERE status='validated'")->fetchColumn(),
    'verified' => $db->query("SELECT COUNT(*) FROM plantations WHERE status='verified'")->fetchColumn(),
    'registered' => $db->query("SELECT COUNT(*) FROM plantations WHERE status='registered'")->fetchColumn()
];

function denr_verifier_status_label($status) {
    $map = [
        'pending' => 'Pending',
        'validated' => 'Checked',
        'verified' => 'Verified',
        'registered' => 'Registered',
        'rejected' => 'Rejected',
    ];
    return $map[$status] ?? ucfirst((string) $status);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Locations - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/admin_users.css">
    <link rel="stylesheet" href="../../../assets/css/admin_plantations.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>

<body class="admin-plantations-page">
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../verifier_includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../../../verifier_includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard admin-plantations">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Verify Locations</h1>
                        <p class="admin-dashboard-subtitle">Verify and update location status</p>
                    </div>
                </header>

                <!-- Statistics -->
                <div class="admin-stats-row">
                    <div class="admin-stat-item">
                        <div class="stat-icon primary">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $stats['total']; ?></span>
                            <span class="stat-label">Total Locations</span>
                        </div>
                    </div>
                    <div class="admin-stat-item">
                        <div class="stat-icon warning">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <polyline points="12 6 12 12 16 14" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $stats['pending']; ?></span>
                            <span class="stat-label">Pending Review</span>
                        </div>
                    </div>
                    <div class="admin-stat-item">
                        <div class="stat-icon success">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                <polyline points="22 4 12 14.01 9 11.01" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $stats['validated']; ?></span>
                            <span class="stat-label">Checked</span>
                        </div>
                    </div>
                    <div class="admin-stat-item">
                        <div class="stat-icon info">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                                <polyline points="10 9 9 9 8 9" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo (int)$stats['verified'] + (int)$stats['registered']; ?></span>
                            <span class="stat-label">Verified</span>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="admin-filters-card">
                    <form method="get" action="plantations.php" class="filters-form">
                        <div class="filter-group">
                            <div class="search-box">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <input type="text" name="search" id="searchInput" placeholder="Search plantations..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="filter-group">
                            <select name="status" id="statusFilter" class="filter-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>
                                    Pending</option>
                                <option value="validated"
                                    <?php echo $status_filter === 'validated' ? 'selected' : ''; ?>>Checked</option>
                                <option value="verified"
                                    <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="registered"
                                    <?php echo $status_filter === 'registered' ? 'selected' : ''; ?>>Registered</option>
                                <option value="rejected"
                                    <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <select name="sort" id="sortBy" class="filter-select">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First
                                </option>
                                <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First
                                </option>
                                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name A–Z</option>
                                <option value="area" <?php echo $sort === 'area' ? 'selected' : ''; ?>>Area (Largest)
                                </option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn-filter">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                                </svg> Filter
                            </button>
                            <a href="plantations.php" class="btn-reset">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="23 4 23 10 17 10" />
                                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
                                </svg> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Plantations Table -->
                <div class="admin-table-card">
                    <div class="table-header">
                        <h4><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                            </svg> Plantations List</h4>
                        <span class="table-count" id="tableCount"><?php echo $total_plantations; ?> plantations
                            found</span>
                    </div>
                    <div class="table-responsive">
                        <?php if (empty($plantations)): ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Owner</th>
                                    <th>Name</th>
                                    <th>Species</th>
                                    <th>Area</th>
                                    <th>Document</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="no-data">
                                        <div class="empty-state">
                                            <svg class="icon-svg empty-state-icon" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round" style="display: none;">
                                                <path
                                                    d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" />
                                                <polyline points="3.27 6.96 12 12.01 20.73 6.96" />
                                                <line x1="12" y1="22.08" x2="12" y2="12" />
                                            </svg>
                                            <h5>No Plantations Found</h5>
                                            <p>There are no plantations registered in the system yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <table class="admin-table plantations-table">
                            <thead>
                                <tr>
                                    <th>Owner</th>
                                    <th>Plantation Name</th>
                                    <th>Species</th>
                                    <th>Area (ha)</th>
                                    <th>Document</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plantations as $plantation): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php if (!empty($plantation['owner_profile_img']) && file_exists("../../../assets/uploads/profiles/" . $plantation['owner_profile_img'])): ?>
                                                <img src="../../../assets/uploads/profiles/<?php echo htmlspecialchars($plantation['owner_profile_img']); ?>"
                                                    alt="Owner">
                                                <?php else: ?>
                                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                                    <circle cx="12" cy="7" r="4" />
                                                </svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="user-details">
                                                <span
                                                    class="user-name"><?php echo htmlspecialchars($plantation['owner_name']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="plantation-name">
                                        <?php echo htmlspecialchars($plantation['plantation_name']); ?></td>
                                    <td>
                                        <?php
                                        $species = array_filter(array_map('trim', explode(',', $plantation['tree_species'])));
                                        foreach ($species as $spec):
                                        ?><span class="species-badge"><?php echo htmlspecialchars($spec); ?></span><?php
                                        endforeach;
                                        if (empty($species)): ?><span class="text-muted">—</span><?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($plantation['land_area']); ?></td>
                                    <td>
                                        <?php if (!empty($plantation['verification_document'])): ?>
                                        <span class="doc-badge doc-uploaded">Uploaded</span>
                                        <?php else: ?>
                                        <span class="doc-badge doc-none">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span
                                            class="status-badge status-<?php echo $plantation['status']; ?>"><?php
                                            echo htmlspecialchars(denr_verifier_status_label($plantation['status']));
                                            ?></span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-action btn-review"
                                                onclick="reviewPlantation(<?php echo htmlspecialchars(json_encode($plantation)); ?>)">
                                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path
                                                        d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                        <div id="searchEmptyState" class="empty-state empty-state-search" style="display: none;">
                            <svg class="icon-svg empty-state-icon"
                                style="width: 2.5rem; height: 2.5rem; margin: 0 auto 0.75rem;" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <h5>No Results Found</h5>
                            <p>No plantations match your search or filter.</p>
                        </div>
                    </div>

                    <!-- Pagination (same as Attendify: Show per page next to "Showing...") -->
                    <?php if ($total_plantations > 0): ?>
                    <div class="admin-pagination">
                        <div class="admin-pagination-left">
                            <label class="admin-per-page-wrap">
                                <span class="admin-per-page-label">Show</span>
                                <select class="admin-per-page-select" aria-label="Rows per page"
                                    onchange="var p=new URLSearchParams(window.location.search);p.set('per_page',this.value);p.set('page','1');window.location=window.location.pathname+'?'+p.toString();">
                                    <?php foreach ($per_page_options as $n): ?>
                                    <option value="<?php echo $n; ?>" <?php echo $limit == $n ? 'selected' : ''; ?>>
                                        <?php echo $n; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="admin-per-page-label">per page</span>
                            </label>
                            <span class="pagination-info">
                                Showing <?php echo $offset + 1; ?> to
                                <?php echo min($offset + $limit, $total_plantations); ?> of
                                <?php echo $total_plantations; ?> entries
                            </span>
                        </div>
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo (int)$limit; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo urlencode($sort); ?>"
                                class="page-btn">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="15 18 9 12 15 6" />
                                </svg> Previous
                            </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&per_page=<?php echo (int)$limit; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo urlencode($sort); ?>"
                                class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo (int)$limit; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&sort=<?php echo urlencode($sort); ?>"
                                class="page-btn">
                                Next <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6" />
                                </svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="toast-container"></div>
            </div>
        </main>
    </div>

    <!-- Review Modal – clean UI/UX, same pattern as Users -->
    <div class="modal fade review-modal" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content modern-modal">
                <header class="modal-header modern-header">
                    <button type="button" class="modal-back-btn" data-bs-dismiss="modal" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12" />
                            <polyline points="12 19 5 12 12 5" />
                        </svg>
                    </button>
                    <div class="modal-title-wrapper">
                        <div class="modal-icon review-modal-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                        </div>
                        <div class="modal-title-text">
                            <h2 id="reviewModalLabel" class="modal-title-heading">Review Plantation</h2>
                            <p class="modal-title-sub">View details, map, and update status</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close modern-close" data-bs-dismiss="modal" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                            stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18" />
                            <line x1="6" y1="6" x2="18" y2="18" />
                        </svg>
                    </button>
                </header>

                <form id="reviewForm" action="../../../handlers/admin_review_plantation.php" method="POST">
                    <input type="hidden" name="plantation_id" id="plantation_id">
                    <div class="modal-body modern-body modal-body-sections">
                        <section class="modal-section" aria-labelledby="reviewDetailsHeading">
                            <h3 id="reviewDetailsHeading" class="modal-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                    <polyline points="14 2 14 8 20 8" />
                                    <line x1="16" y1="13" x2="8" y2="13" />
                                    <line x1="16" y1="17" x2="8" y2="17" />
                                    <polyline points="10 9 9 9 8 9" />
                                </svg>
                                Details
                            </h3>
                            <div id="plantationDetails" class="location-details review-details-inner" role="region"
                                aria-label="Plantation details"></div>
                        </section>

                        <section class="modal-section" aria-labelledby="reviewMapHeading">
                            <h3 id="reviewMapHeading" class="modal-section-title">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                    <circle cx="12" cy="10" r="3" />
                                </svg>
                                Location
                            </h3>
                            <div id="reviewMap" class="review-map-wrap" role="img" aria-label="Plantation location map">
                            </div>
                        </section>

                        <section class="modal-section modal-section-actions" aria-labelledby="reviewFormHeading" id="verifierReviewActions">
                            <h3 id="reviewFormHeading" class="modal-section-title">Review</h3>
                            <p class="small text-muted mb-2" id="verifierReviewHint">Choose <strong>Verified</strong> or <strong>Reject</strong>. Notes are required only when rejecting. Status is never “Approved”.</p>
                            <div class="alert alert-info py-2 px-3 small mb-0" id="verifierAlreadyDone" style="display:none;">
                                This plantation is already <strong>Verified</strong> or <strong>Registered</strong>. No further notes or status changes are needed.
                            </div>
                            <div class="form-grid form-grid-review" id="verifierStatusForm">
                                <div class="form-group">
                                    <label class="form-label" for="statusSelect">Status</label>
                                    <select name="status" id="statusSelect" class="form-control modern-select" required
                                        aria-required="true" onchange="toggleRejectionReason()">
                                        <option value="">Select status...</option>
                                        <option value="verified">Verified</option>
                                        <option value="rejected">Reject</option>
                                    </select>
                                </div>
                                <div class="form-group form-group-full" id="rejectionReasonGroup" style="display: none;">
                                    <label class="form-label" for="rejectionReason">Reason for rejection <span class="text-danger">*</span></label>
                                    <select name="rejection_reason" id="rejectionReason" class="form-control modern-select">
                                        <option value="">Select reason...</option>
                                        <option value="Incomplete required documents">Incomplete required documents</option>
                                        <option value="Invalid land ownership document">Invalid land ownership document</option>
                                        <option value="Invalid or unreadable uploaded files">Invalid or unreadable uploaded files</option>
                                        <option value="Incorrect plantation location">Incorrect plantation location</option>
                                        <option value="Applicant information does not match submitted documents">Applicant information does not match submitted documents</option>
                                    </select>
                                </div>
                                <div class="form-group form-group-full" id="remarksGroup" style="display: none;">
                                    <label class="form-label" for="remarksArea">Notes <span class="text-danger">*</span></label>
                                    <textarea name="remarks" id="remarksArea"
                                        class="form-control modern-input modern-textarea" rows="3"
                                        placeholder="Add rejection notes / additional details"></textarea>
                                </div>
                            </div>
                        </section>
                    </div>
                    <footer class="modal-footer modern-footer" id="verifierReviewFooter">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-update" id="verifierUpdateBtn">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                <polyline points="22 4 12 14.01 9 11.01" />
                            </svg>
                            Update
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="../../../assets/js/polygon_area.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filtering/sorting/pagination are server-side via form submit (same as Users / Attendify)

        // Show success message if exists
        <?php if (isset($_SESSION['success'])): ?>
        showToast('<?php echo $_SESSION['success']; ?>', 'success');
        <?php unset($_SESSION['success']); endif; ?>

        // Show error message if exists
        <?php if (isset($_SESSION['error'])): ?>
        showToast('<?php echo $_SESSION['error']; ?>', 'error');
        <?php unset($_SESSION['error']); endif; ?>
    });

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = `toast show bg-${type === 'success' ? 'success' : 'danger'} text-white`;
        toast.innerHTML = `
            <div class="toast-body">
                ${message}
                <button type="button" class="btn-close btn-close-white float-end" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        document.querySelector('.toast-container').appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    function clearSearchFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('statusFilter').value = '';
        document.getElementById('sortBy').value = 'newest';
        filterPlantations();
    }

    let reviewMap, reviewMarker;
    let reviewMohonMarkers = [];
    let reviewBoundaryLayer = null;

    function clearReviewMapOverlays() {
        if (reviewMarker && reviewMap) {
            reviewMap.removeLayer(reviewMarker);
            reviewMarker = null;
        }
        reviewMohonMarkers.forEach(function(m) {
            if (reviewMap) reviewMap.removeLayer(m);
        });
        reviewMohonMarkers = [];
        if (reviewBoundaryLayer && reviewMap) {
            reviewMap.removeLayer(reviewBoundaryLayer);
            reviewBoundaryLayer = null;
        }
    }

    function toggleRejectionReason() {
        const statusSelect = document.getElementById('statusSelect');
        const group = document.getElementById('rejectionReasonGroup');
        const reason = document.getElementById('rejectionReason');
        const remarksGroup = document.getElementById('remarksGroup');
        const remarks = document.getElementById('remarksArea');
        if (!statusSelect || !group || !reason) return;
        const isRejected = statusSelect.value === 'rejected';
        group.style.display = isRejected ? '' : 'none';
        reason.required = isRejected;
        if (!isRejected) reason.value = '';
        if (remarksGroup) remarksGroup.style.display = isRejected ? '' : 'none';
        if (remarks) {
            remarks.required = isRejected;
            if (!isRejected) remarks.value = '';
        }
    }

    function reviewPlantation(plantation) {
        document.getElementById('plantation_id').value = plantation.plantation_id;

        const alreadyDone = plantation.status === 'verified' || plantation.status === 'registered';
        const statusForm = document.getElementById('verifierStatusForm');
        const alreadyDoneMsg = document.getElementById('verifierAlreadyDone');
        const reviewHint = document.getElementById('verifierReviewHint');
        const updateBtn = document.getElementById('verifierUpdateBtn');
        const statusSelect = document.getElementById('statusSelect');

        if (alreadyDone) {
            if (statusForm) statusForm.style.display = 'none';
            if (alreadyDoneMsg) alreadyDoneMsg.style.display = '';
            if (reviewHint) reviewHint.style.display = 'none';
            if (updateBtn) updateBtn.style.display = 'none';
            if (statusSelect) {
                statusSelect.required = false;
                statusSelect.disabled = true;
                statusSelect.value = '';
            }
        } else {
            if (statusForm) statusForm.style.display = '';
            if (alreadyDoneMsg) alreadyDoneMsg.style.display = 'none';
            if (reviewHint) reviewHint.style.display = '';
            if (updateBtn) updateBtn.style.display = '';
            if (statusSelect) {
                statusSelect.required = true;
                statusSelect.disabled = false;
                // Verifier: Verified or Reject only (never Approved)
                statusSelect.value = plantation.status === 'rejected' ? 'rejected' : '';
            }
        }

        const reasonSel = document.getElementById('rejectionReason');
        if (reasonSel) {
            reasonSel.value = plantation.rejection_reason || '';
        }
        toggleRejectionReason();

        let mohonPts = [];
        try {
            if (plantation.mohon_points_json) {
                mohonPts = typeof plantation.mohon_points_json === 'string'
                    ? JSON.parse(plantation.mohon_points_json)
                    : plantation.mohon_points_json;
                if (!Array.isArray(mohonPts)) mohonPts = [];
            }
        } catch (e) {
            mohonPts = [];
        }
        if (!mohonPts.length && plantation.landmark_latitude && plantation.landmark_longitude) {
            mohonPts = [{
                lat: parseFloat(plantation.landmark_latitude),
                lng: parseFloat(plantation.landmark_longitude)
            }];
        }

        const ageDisplay = (plantation.age_of_plantation !== null && plantation.age_of_plantation !== undefined && plantation.age_of_plantation !== '')
            ? (parseFloat(plantation.age_of_plantation) + ' year(s)')
            : '';

        const details = `
            <div class="location-detail-item">
                <i class="fas fa-tree"></i>
                <div>
                    <strong>Name</strong>
                    <span>${plantation.plantation_name}</span>
                </div>
            </div>
            <div class="location-detail-item">
                <i class="fas fa-leaf"></i>
                <div>
                    <strong>Species</strong>
                    <span class="species-badges">${plantation.tree_species.split(',').map(s => `<span class="badge bg-success me-1"><i class="fas fa-leaf"></i> ${s.trim()}</span>`).join('')}</span>
                </div>
            </div>
            <div class="location-detail-item">
                <i class="fas fa-ruler-combined"></i>
                <div>
                    <strong>Area</strong>
                    <span>${plantation.land_area} ha</span>
                </div>
            </div>
            ${ageDisplay ? `
            <div class="location-detail-item">
                <i class="fas fa-hourglass-half"></i>
                <div>
                    <strong>Age of plantation</strong>
                    <span>${ageDisplay}</span>
                </div>
            </div>` : ''}
            <div class="location-detail-item">
                <i class="fas fa-map-marker-alt"></i>
                <div>
                    <strong>Location</strong>
                    <span>${plantation.location_address}</span>
                </div>
            </div>
            ${plantation.contact_person_name ? `
            <div class="location-detail-item">
                <i class="fas fa-user"></i>
                <div>
                    <strong>Name</strong>
                    <span>${plantation.contact_person_name}</span>
                </div>
            </div>` : ''}
            ${plantation.contact_address ? `
            <div class="location-detail-item">
                <i class="fas fa-home"></i>
                <div>
                    <strong>Address</strong>
                    <span>${plantation.contact_address}</span>
                </div>
            </div>` : ''}
            ${plantation.contact_phone ? `
            <div class="location-detail-item">
                <i class="fas fa-phone"></i>
                <div>
                    <strong>Contact</strong>
                    <span>${plantation.contact_phone}</span>
                </div>
            </div>` : ''}
            <div class="location-detail-item">
                <i class="fas fa-location-dot"></i>
                <div>
                    <strong>Lot coordinates</strong>
                    <span>${plantation.latitude}, ${plantation.longitude}</span>
                </div>
            </div>
            ${mohonPts.length ? `
            <div class="location-detail-item">
                <i class="fas fa-draw-polygon text-danger"></i>
                <div>
                    <strong>Mohon (boundary)</strong>
                    <span>${mohonPts.length} corner(s): ${mohonPts.map((p, i) => '#' + (i + 1) + ' ' + parseFloat(p.lat).toFixed(5) + ', ' + parseFloat(p.lng).toFixed(5)).join(' · ')}</span>
                </div>
            </div>` : ''}
            ${mohonPts.length >= 3 && typeof denrFormatBoundaryArea === 'function' ? `
            <div class="location-detail-item">
                <i class="fas fa-ruler-combined text-primary"></i>
                <div>
                    <strong>Total boundary area</strong>
                    <span>${denrFormatBoundaryArea(mohonPts)}</span>
                </div>
            </div>` : ''}
            <div class="location-detail-item">
                <i class="fas fa-user"></i>
                <div>
                    <strong>Owner</strong>
                    <span>${plantation.owner_name}</span>
                </div>
            </div>
            ${plantation.verification_document ? `
            <div class="location-detail-item">
                <i class="fas fa-file-pdf"></i>
                <div>
                    <strong>Verification Document</strong>
                    <span>
                        <a href="../../../${plantation.verification_document}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-download"></i> View Document
                        </a>
                    </span>
                </div>
            </div>` : ''}
            ${plantation.tax_declaration_path ? `
            <div class="location-detail-item">
                <i class="fas fa-file-invoice"></i>
                <div>
                    <strong>Tax Declaration</strong>
                    <span>
                        <a href="../../../${plantation.tax_declaration_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-download"></i> View Tax Declaration
                        </a>
                    </span>
                </div>
            </div>` : ''}
            ${plantation.site_photo_path ? `
            <div class="location-detail-item">
                <i class="fas fa-camera"></i>
                <div>
                    <strong>Picture of the site</strong>
                    <span>
                        <a href="../../../${plantation.site_photo_path}" target="_blank" class="btn btn-sm btn-outline-primary mb-1">
                            <i class="fas fa-image"></i> Open Full Size
                        </a>
                        <img src="../../../${plantation.site_photo_path}" alt="Site photo" class="review-site-photo-preview" loading="lazy"
                            onerror="this.style.display='none'">
                    </span>
                </div>
            </div>` : ''}
            ${plantation.status === 'rejected' && plantation.rejection_reason ? `
            <div class="location-detail-item">
                <i class="fas fa-ban text-danger"></i>
                <div>
                    <strong>Rejection reason</strong>
                    <span>${plantation.rejection_reason}</span>
                </div>
            </div>` : ''}
        `;

        document.getElementById('plantationDetails').innerHTML = details;

        const reviewModalEl = document.getElementById('reviewModal');
        const reviewModal = window.verifierReviewModal || (window.verifierReviewModal = new bootstrap.Modal(reviewModalEl, { backdrop: true, keyboard: true }));
        function onReviewModalShown() {
            const lat = parseFloat(plantation.latitude) || 7.1907;
            const lng = parseFloat(plantation.longitude) || 122.0794;

            if (!reviewMap) {
                reviewMap = L.map('reviewMap').setView([lat, lng], 15);

                const baseMaps = {
                    "Default": L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors'
                    }),
                    "Satellite": L.tileLayer(
                        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                            attribution: '© Esri'
                        }),
                    "Terrain": L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenTopoMap contributors'
                    })
                };

                baseMaps["Default"].addTo(reviewMap);

                L.control.layers(baseMaps, null, {
                    position: 'topright'
                }).addTo(reviewMap);
            }

            clearReviewMapOverlays();

            if (plantation.latitude && plantation.longitude) {
                reviewMarker = L.marker([lat, lng]).addTo(reviewMap)
                    .bindPopup(
                        `<b>Lot</b><br>${plantation.plantation_name || 'Plantation'}<br>${plantation.location_address || ''}`
                    )
                    .openPopup();
            }

            var latlngs = [];
            mohonPts.forEach(function(p, i) {
                var ll = [parseFloat(p.lat), parseFloat(p.lng)];
                latlngs.push(ll);
                var mohonIcon = L.divIcon({
                    className: 'mohon-marker-icon',
                    html: '<div style="background:#c0392b;width:22px;height:22px;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 5px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:700;">' + (i + 1) + '</div>',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11]
                });
                var mm = L.marker(ll, { icon: mohonIcon }).addTo(reviewMap)
                    .bindPopup('<b>Mohon #' + (i + 1) + '</b><br>Boundary corner');
                reviewMohonMarkers.push(mm);
            });
            if (latlngs.length >= 3) {
                reviewBoundaryLayer = L.polygon(latlngs, { color: '#c0392b', weight: 2, fillColor: '#e74c3c', fillOpacity: 0.12 }).addTo(reviewMap);
            } else if (latlngs.length === 2) {
                reviewBoundaryLayer = L.polyline(latlngs, { color: '#c0392b', weight: 3, dashArray: '10,8' }).addTo(reviewMap);
            }

            var b = L.latLngBounds([]);
            if (plantation.latitude && plantation.longitude) b.extend([lat, lng]);
            mohonPts.forEach(function(p) {
                b.extend([parseFloat(p.lat), parseFloat(p.lng)]);
            });
            if (b.isValid()) {
                reviewMap.fitBounds(b, { padding: [36, 36], maxZoom: 17 });
            } else {
                reviewMap.setView([lat, lng], 15);
            }

            reviewMap.invalidateSize();
        }

        reviewModalEl.removeEventListener('shown.bs.modal', reviewPlantation._onReviewShown);
        reviewPlantation._onReviewShown = onReviewModalShown;
        reviewModalEl.addEventListener('shown.bs.modal', onReviewModalShown);

        reviewModal.show();
    }

    // Prevent stuck dark overlay when closing review modal (click outside or Cancel)
    function clearModalBackdrop() {
        document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    document.addEventListener('hidden.bs.modal', clearModalBackdrop);
    </script>
</body>

</html>