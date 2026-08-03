<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit();
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch permits with plantation and owner details
$query = "SELECT p.*, pl.plantation_name, pl.tree_species, u.full_name as owner_name 
          FROM permits p 
          JOIN plantations pl ON p.plantation_id = pl.plantation_id
          JOIN users u ON pl.user_id = u.user_id 
          ORDER BY p.requested_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$permits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Attach number-of-trees (permit_trees) for each permit
$permitsHaveTrees = $db->query("SHOW TABLES LIKE 'permit_trees'")->rowCount() > 0;
foreach ($permits as &$permit) {
    $permit['trees'] = [];
    if ($permitsHaveTrees) {
        $tq = $db->prepare("SELECT tree_species, quantity, registry_number FROM permit_trees WHERE permit_id = ?");
        $tq->execute([$permit['permit_id']]);
        $permit['trees'] = $tq->fetchAll(PDO::FETCH_ASSOC);
    }
}
unset($permit);

// Get permit statistics
$stats = [
    'total' => $db->query("SELECT COUNT(*) FROM permits")->fetchColumn(),
    'pending' => $db->query("SELECT COUNT(*) FROM permits WHERE status='pending'")->fetchColumn(),
    'approved' => $db->query("SELECT COUNT(*) FROM permits WHERE status='approved'")->fetchColumn(),
    'rejected' => $db->query("SELECT COUNT(*) FROM permits WHERE status='rejected'")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permit Management - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/admin_users.css">
    <link rel="stylesheet" href="../../../assets/css/admin_permits.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>

<body class="admin-permits-page">
    <div class="dashboard-container">
        <?php include '../../../admin_includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include '../../../admin_includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard admin-permits">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Permits</h1>
                        <p class="admin-dashboard-subtitle">Review and manage permit applications</p>
                    </div>
                </header>

                <!-- Statistics (same style as Users) -->
                <div class="admin-stats-row">
                    <div class="admin-stat-item">
                        <div class="stat-icon primary">
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
                            <span class="stat-number"><?php echo $stats['total']; ?></span>
                            <span class="stat-label">Total Permits</span>
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
                            <span class="stat-number"><?php echo $stats['approved']; ?></span>
                            <span class="stat-label">Approved</span>
                        </div>
                    </div>
                    <div class="admin-stat-item">
                        <div class="stat-icon info">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10" />
                                <line x1="15" y1="9" x2="9" y2="15" />
                                <line x1="9" y1="9" x2="15" y2="15" />
                            </svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $stats['rejected']; ?></span>
                            <span class="stat-label">Rejected</span>
                        </div>
                    </div>
                </div>

                <!-- Filters (same style as Users) -->
                <div class="admin-filters-card">
                    <div class="filters-form">
                        <div class="filter-group">
                            <div class="search-box">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8" />
                                    <line x1="21" y1="21" x2="16.65" y2="16.65" />
                                </svg>
                                <input type="text" id="searchInput" placeholder="Search permits..." value="">
                            </div>
                        </div>
                        <div class="filter-group">
                            <select id="typeFilter" class="filter-select">
                                <option value="">All Types</option>
                                <option value="certificate">Registration Certificate</option>
                                <option value="cutting">Cutting Permit</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <select id="statusFilter" class="filter-select">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="button" class="btn-filter" id="btnFilterPermits" aria-label="Apply filters">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                                </svg> Filter
                            </button>
                            <a href="permits.php" class="btn-reset">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="23 4 23 10 17 10" />
                                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
                                </svg> Reset
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Permits list card (same card style as Users table card) -->
                <div class="admin-table-card">
                    <div class="table-header">
                        <h4><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                                <polyline points="10 9 9 9 8 9" />
                            </svg> Permits List</h4>
                        <span class="table-count" id="permitsCount"><?php echo count($permits); ?> permits
                            found</span>
                    </div>
                    <div class="table-responsive">
                        <?php if (empty($permits)): ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Permit #</th>
                                    <th>Owner</th>
                                    <th>Plantation</th>
                                    <th>Type</th>
                                    <th>Requested</th>
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
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                <polyline points="14 2 14 8 20 8" />
                                                <line x1="16" y1="13" x2="8" y2="13" />
                                                <line x1="16" y1="17" x2="8" y2="17" />
                                                <polyline points="10 9 9 9 8 9" />
                                            </svg>
                                            <h5>No Permits Found</h5>
                                            <p>There are no permit applications in the system yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="permit-list">
                            <table class="admin-table permits-table">
                                <thead>
                                    <tr>
                                        <th>Permit #</th>
                                        <th>Owner</th>
                                        <th>Plantation</th>
                                        <th>Type</th>
                                        <th>Requested</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($permits as $permit): ?>
                                    <?php
                                    $document = null;
                                    if ($permit['status'] === 'approved') {
                                        $doc_query = "SELECT * FROM documents WHERE plantation_id = :plantation_id ORDER BY uploaded_at DESC LIMIT 1";
                                        $doc_stmt = $db->prepare($doc_query);
                                        $doc_stmt->execute([':plantation_id' => $permit['plantation_id']]);
                                        $document = $doc_stmt->fetch(PDO::FETCH_ASSOC);
                                    }
                                    $typeLabel = $permit['permit_type'] === 'certificate' ? 'Registration Certificate' : 'Cutting Permit';
                                    ?>
                                    <tr data-type="<?php echo htmlspecialchars($permit['permit_type']); ?>">
                                        <td class="permit-id">#<?php echo (int)$permit['permit_id']; ?></td>
                                        <td class="permit-owner"><?php echo htmlspecialchars($permit['owner_name']); ?>
                                        </td>
                                        <td class="permit-plantation">
                                            <?php echo htmlspecialchars($permit['plantation_name']); ?></td>
                                        <td>
                                            <span
                                                class="permit-type-badge permit-type-<?php echo $permit['permit_type']; ?>"><?php echo htmlspecialchars($typeLabel); ?></span>
                                        </td>
                                        <td class="permit-date">
                                            <?php echo date('M d, Y', strtotime($permit['requested_at'])); ?></td>
                                        <td>
                                            <span
                                                class="status-badge status-<?php echo $permit['status']; ?>"><?php echo ucfirst($permit['status']); ?></span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action btn-review"
                                                    title="<?php echo $permit['status'] === 'approved' ? 'View issued permit (not editable)' : 'Review permit'; ?>"
                                                    onclick="reviewPermit(<?php echo htmlspecialchars(json_encode($permit)); ?>)">
                                                    <svg class="icon-svg" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <?php if ($permit['status'] === 'approved'): ?>
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                        <?php else: ?>
                                                        <path
                                                            d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" />
                                                        <path
                                                            d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                                                        <?php endif; ?>
                                                    </svg>
                                                </button>
                                                <?php if ($document): ?>
                                                <a href="../../../<?php echo htmlspecialchars($document['file_path']); ?>"
                                                    class="btn-action btn-download"
                                                    download="<?php echo htmlspecialchars($document['file_name']); ?>"
                                                    title="Download document">
                                                    <svg class="icon-svg" viewBox="0 0 24 24" fill="none"
                                                        stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                        stroke-linejoin="round">
                                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                                        <polyline points="7 10 12 15 17 10" />
                                                        <line x1="12" y1="15" x2="12" y2="3" />
                                                    </svg>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
                            <p>No permits match your search or filters.</p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Review Modal – same design as Users (modern-modal) -->
    <div class="modal fade permit-review-modal" id="permitModal" tabindex="-1" aria-labelledby="permitModalLabel"
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
                        <div class="modal-icon permit-modal-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                                <polyline points="10 9 9 9 8 9" />
                            </svg>
                        </div>
                        <div class="modal-title-text">
                            <h2 id="permitModalLabel" class="modal-title-heading">Review Permit</h2>
                            <p class="modal-title-sub" id="permitModalSub">View details and approve or reject</p>
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

                <form id="permitForm" action="../../../handlers/admin_review_permit.php" method="POST"
                    enctype="multipart/form-data">
                    <input type="hidden" name="permit_id" id="permit_id">
                    <input type="hidden" name="plantation_id" id="plantation_id">
                    <div class="modal-body modern-body modal-body-sections">
                        <section class="modal-section" aria-labelledby="permitDetailsHeading">
                            <h3 id="permitDetailsHeading" class="modal-section-title">
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
                            <div id="permitDetails" class="permit-details-inner" role="region"
                                aria-label="Permit details"></div>
                        </section>

                        <section class="modal-section modal-section-actions" id="permitReviewActions" aria-labelledby="permitFormHeading">
                            <h3 id="permitFormHeading" class="modal-section-title">Review</h3>
                            <div class="form-grid form-grid-permit">
                                <div class="form-group">
                                    <label class="form-label" for="permitStatus">Action</label>
                                    <select name="status" id="permitStatus" class="form-control modern-select" required
                                        aria-required="true" onchange="toggleDocumentUpload()">
                                        <option value="">Select action...</option>
                                        <option value="approved">Approve</option>
                                        <option value="rejected">Reject</option>
                                    </select>
                                </div>
                                <div class="form-group form-group-full" id="documentUpload" style="display: none;">
                                    <label class="form-label" for="permitDocument">Document</label>
                                    <input type="file" id="permitDocument" name="permit_document"
                                        class="form-control modern-input" accept=".pdf,.doc,.docx">
                                    <span class="form-feedback text-muted">PDF or DOC, max 5MB</span>
                                </div>
                                <div class="form-group form-group-full">
                                    <label class="form-label" for="permitRemarks">Notes</label>
                                    <textarea name="remarks" id="permitRemarks"
                                        class="form-control modern-input modern-textarea" rows="3"
                                        placeholder="Add feedback (optional)"></textarea>
                                </div>
                            </div>
                        </section>
                        <div id="permitIssuedNotice" class="alert alert-info mx-3" style="display: none;">
                            This permit has been issued and can no longer be edited.
                        </div>
                    </div>
                    <footer class="modal-footer modern-footer">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18" />
                                <line x1="6" y1="6" x2="18" y2="18" />
                            </svg>
                            <span id="permitCancelLabel">Cancel</span>
                        </button>
                        <button type="submit" class="btn btn-update" id="permitSubmitBtn">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" />
                                <polyline points="22 4 12 14.01 9 11.01" />
                            </svg>
                            Send
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>

    <script>
    // Real-time search and filter functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const typeFilter = document.getElementById('typeFilter');
        const statusFilter = document.getElementById('statusFilter');
        const permitList = document.querySelector('.permit-list');
        const searchEmptyState = document.getElementById('searchEmptyState');

        function filterPermits() {
            const searchTerm = searchInput.value.toLowerCase();
            const typeValue = typeFilter.value.toLowerCase();
            const statusValue = statusFilter.value.toLowerCase();

            if (!permitList) return;

            const rows = permitList.querySelectorAll('tbody tr');
            let visibleCount = 0;

            rows.forEach(row => {
                const rowText = row.textContent.toLowerCase();
                const dataType = row.getAttribute('data-type') || '';
                const statusEl = row.querySelector('.status-badge');
                const permitStatus = statusEl ? statusEl.textContent.toLowerCase() : '';

                const matchesSearch = searchTerm === '' || rowText.includes(searchTerm);
                const matchesType = typeValue === '' || dataType === typeValue;
                const matchesStatus = statusValue === '' || permitStatus.includes(statusValue);

                const isVisible = matchesSearch && matchesType && matchesStatus;
                row.style.display = isVisible ? '' : 'none';

                if (isVisible) visibleCount++;
            });

            const countEl = document.getElementById('permitsCount');
            if (countEl) countEl.textContent = visibleCount + ' permits';

            permitList.style.display = visibleCount > 0 ? '' : 'none';
            if (searchEmptyState) {
                searchEmptyState.style.display = visibleCount === 0 && (searchTerm || typeValue ||
                    statusValue) ? 'block' : 'none';
            }
        }

        if (searchInput) searchInput.addEventListener('input', filterPermits);
        if (typeFilter) typeFilter.addEventListener('change', filterPermits);
        if (statusFilter) statusFilter.addEventListener('change', filterPermits);
        var btnFilter = document.getElementById('btnFilterPermits');
        if (btnFilter) btnFilter.addEventListener('click', filterPermits);
    });

    function toggleDocumentUpload() {
        const statusSelect = document.getElementById('permitStatus');
        const uploadDiv = document.getElementById('documentUpload');
        const uploadInput = document.getElementById('permitDocument');
        if (!statusSelect || !uploadDiv || !uploadInput) return;
        const status = statusSelect.value;
        if (status === 'approved') {
            uploadDiv.style.display = '';
            uploadInput.required = true;
        } else {
            uploadDiv.style.display = 'none';
            uploadInput.required = false;
        }
    }

    function clearSearchFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('typeFilter').value = '';
        document.getElementById('statusFilter').value = '';
        if (typeof filterPermits === 'function') filterPermits();
    }

    function reviewPermit(permit) {
        document.getElementById('permit_id').value = permit.permit_id;
        document.getElementById('plantation_id').value = permit.plantation_id;

        const permitType = permit.permit_type === 'certificate' ? 'Certificate' : 'Cutting';
        const requestDate = new Date(permit.requested_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        const isCutting = permit.permit_type === 'cutting';
        let treesTable = '';
        if (permit.trees && permit.trees.length > 0) {
            treesTable = `
            <div class="info-group mt-2">
                <strong><i class="fas fa-tree"></i> Number of trees</strong>
                <table class="table table-sm table-bordered mt-1">
                    <thead><tr><th>Species</th><th>Quantity</th><th>Registry no.</th></tr></thead>
                    <tbody>
                        ${permit.trees.map(t => '<tr><td>' + (t.tree_species || '-') + '</td><td>' + (t.quantity || '0') + '</td><td>' + (t.registry_number || '-') + '</td></tr>').join('')}
                    </tbody>
                </table>
            </div>`;
        } else if (isCutting) {
            treesTable =
                '<div class="info-group mt-2"><small class="text-muted"><i class="fas fa-info-circle"></i> No tree entries yet. Limitation: for cutting only.</small></div>';
        }

        const details = `
            <div class="info-group">
                <div class="info-item">
                    <i class="fas fa-user"></i>
                    <div>
                        <strong>Owner</strong>
                        <span>${permit.owner_name}</span>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-leaf"></i>
                    <div>
                        <strong>Plantation</strong>
                        <span>${permit.plantation_name}</span>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-file"></i>
                    <div>
                        <strong>Type</strong>
                        <span>${permitType}</span>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar-check"></i>
                    <div>
                        <strong>Requested</strong>
                        <span>${requestDate}</span>
                    </div>
                </div>
                ${isCutting ? '<div class="info-item"><i class="fas fa-exclamation-circle"></i><div><strong>Limitation</strong><span>For cutting only.</span></div></div>' : ''}
            </div>
            ${treesTable}
        `;

        document.getElementById('permitDetails').innerHTML = details;
        const issued = permit.status === 'approved';
        const actions = document.getElementById('permitReviewActions');
        const notice = document.getElementById('permitIssuedNotice');
        const submitBtn = document.getElementById('permitSubmitBtn');
        const statusSel = document.getElementById('permitStatus');
        const remarks = document.getElementById('permitRemarks');
        const sub = document.getElementById('permitModalSub');
        const cancelLabel = document.getElementById('permitCancelLabel');
        if (actions) actions.style.display = issued ? 'none' : '';
        if (notice) notice.style.display = issued ? '' : 'none';
        if (submitBtn) submitBtn.style.display = issued ? 'none' : '';
        if (statusSel) {
            statusSel.required = !issued;
            statusSel.disabled = issued;
            statusSel.value = issued ? '' : (permit.defaultStatus || 'approved');
        }
        if (remarks) {
            remarks.disabled = issued;
            remarks.value = permit.remarks || '';
        }
        if (sub) sub.textContent = issued ? 'Issued permit — view only' : 'View details and approve or reject';
        if (cancelLabel) cancelLabel.textContent = issued ? 'Close' : 'Cancel';
        if (!issued) toggleDocumentUpload();
        new bootstrap.Modal(document.getElementById('permitModal')).show();
    }

    function approvePermit(permitId) {
        const permit = permits.find(p => p.permit_id === permitId);
        if (permit) {
            permit.defaultStatus = 'approved';
            reviewPermit(permit);
        }
    }

    function rejectPermit(permitId) {
        const permit = permits.find(p => p.permit_id === permitId);
        if (permit) {
            permit.defaultStatus = 'rejected';
            reviewPermit(permit);
            document.getElementById('permitStatus').value = 'rejected';
            toggleDocumentUpload();
        }
    }

    var permitDocInput = document.querySelector('input[name="permit_document"]');
    if (permitDocInput) {
        permitDocInput.addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (!file) return;
            var allowedTypes = ['application/pdf', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            var maxSize = 5 * 1024 * 1024;
            if (!allowedTypes.includes(file.type)) {
                showNotification('error', 'Only PDF and DOC files are allowed');
                this.value = '';
                return;
            }
            if (file.size > maxSize) {
                showNotification('error', 'File size must be less than 5MB');
                this.value = '';
                return;
            }
        });
    }

    // Initialize permits data from PHP
    const permits = <?php echo json_encode($permits); ?>;
    </script>
</body>

</html>