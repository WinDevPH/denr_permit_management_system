<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    header('Location: ../../../index.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$moa_doc_sql = "(LOWER(COALESCE(d.document_name, '')) LIKE '%moa%'
               OR LOWER(COALESCE(d.document_name, '')) LIKE '%memorandum of agreement%')";
try {
    $dc = $db->query("SHOW COLUMNS FROM documents LIKE 'document_category'");
    if ($dc && $dc->rowCount() > 0) {
        $moa_doc_sql = "($moa_doc_sql OR LOWER(COALESCE(d.document_category, '')) = 'moa')";
    } else {
        $moa_doc_sql = "($moa_doc_sql)";
    }
} catch (PDOException $e) {
    $moa_doc_sql = "($moa_doc_sql)";
}

// Fetch user's plantations for dropdown
try {
    $query = "SELECT plantation_id, plantation_name FROM plantations WHERE user_id = :user_id AND status = 'registered'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $plantations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $plantations = [];
}

// Plantations that don't have a permit yet (for Request modal).
// If an MOA is on file, separate rules may apply — we still list plantations but MOA-covered lots may not need a permit (see UI note).
try {
    $query = "SELECT p.* FROM plantations p 
              WHERE p.user_id = :user_id 
              AND p.status = 'registered'
              AND NOT EXISTS (SELECT 1 FROM permits pm WHERE pm.plantation_id = p.plantation_id)
              AND NOT EXISTS (
                SELECT 1 FROM documents d 
                WHERE d.plantation_id = p.plantation_id 
                AND " . $moa_doc_sql . "
              )";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $available_plantations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $available_plantations = [];
}

// Plantations with MOA (informational — permit may not be required under MOA)
$moa_plantations = [];
try {
    $mq = "SELECT DISTINCT p.plantation_id, p.plantation_name FROM plantations p
           WHERE p.user_id = :user_id AND p.status = 'registered'
           AND EXISTS (
             SELECT 1 FROM documents d WHERE d.plantation_id = p.plantation_id
             AND " . $moa_doc_sql . "
           )";
    $ms = $db->prepare($mq);
    $ms->execute([':user_id' => $user_id]);
    $moa_plantations = $ms->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $moa_plantations = [];
}

// Fetch permits with plantation details
try {
    $query = "SELECT p.*, pl.plantation_name, 
              (SELECT COUNT(*) FROM documents d WHERE d.plantation_id = p.plantation_id) as has_documents 
              FROM permits p 
              JOIN plantations pl ON p.plantation_id = pl.plantation_id 
              WHERE pl.user_id = :user_id 
              ORDER BY p.requested_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $permits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $permits = [];
}

// Stats (same as admin but for current user's permits only)
$stats = [
    'total' => count($permits),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
];
foreach ($permits as $p) {
    if (isset($p['status']) && isset($stats[$p['status']])) {
        $stats[$p['status']]++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permits - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/admin_users.css">
    <link rel="stylesheet" href="../../../assets/css/admin_permits.css">
    <link rel="stylesheet" href="../../../assets/css/permits.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>
<body class="landowner-permits-page">
    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../../../includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard admin-permits">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Permits</h1>
                        <p class="admin-dashboard-subtitle">Request and track your permit applications.</p>
                    </div>
                    <button type="button" class="admin-users-add-btn" data-bs-toggle="modal" data-bs-target="#requestPermitModal">
                        <i class="fas fa-plus-circle"></i> Request New Permit
                    </button>
                </header>

                <?php if (!empty($moa_plantations)): ?>
                <div class="alert alert-info d-flex align-items-start gap-2 mb-4" role="status">
                    <i class="fas fa-file-contract mt-1"></i>
                    <div>
                        <strong>MOA on file.</strong> For plantation(s) with a recorded Memorandum of Agreement, a separate permit may not be required under your agreement.
                        Upload the MOA under <a href="../documents/documents.php">Documents</a> with document type <strong>MOA</strong> so the system can exclude those lots from new permit requests.
                        <span class="d-block small text-muted mt-1">Affected: <?php echo htmlspecialchars(implode(', ', array_column($moa_plantations, 'plantation_name'))); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Statistics (same style as admin) -->
                <div class="admin-stats-row">
                    <div class="admin-stat-item">
                        <div class="stat-icon primary">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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

                <!-- Filters (same style as admin) -->
                <div class="admin-filters-card">
                    <div class="filters-form">
                        <div class="filter-group">
                            <div class="search-box">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3" />
                                </svg> Filter
                            </button>
                            <a href="permits.php" class="btn-reset">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="23 4 23 10 17 10" />
                                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
                                </svg> Reset
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Permits list card (same as admin) -->
                <div class="admin-table-card">
                    <div class="table-header">
                        <h4>
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                <polyline points="14 2 14 8 20 8" />
                                <line x1="16" y1="13" x2="8" y2="13" />
                                <line x1="16" y1="17" x2="8" y2="17" />
                                <polyline points="10 9 9 9 8 9" />
                            </svg> Permits List
                        </h4>
                        <span class="table-count" id="permitsCount"><?php echo count($permits); ?> permits found</span>
                    </div>
                    <div class="table-responsive">
                        <?php if (empty($permits)): ?>
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Permit #</th>
                                    <th>Plantation</th>
                                    <th>Type</th>
                                    <th>Requested</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="6" class="no-data">
                                        <div class="empty-state">
                                            <svg class="icon-svg empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:2.5rem;height:2.5rem;">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                <polyline points="14 2 14 8 20 8" />
                                                <line x1="16" y1="13" x2="8" y2="13" />
                                                <line x1="16" y1="17" x2="8" y2="17" />
                                                <polyline points="10 9 9 9 8 9" />
                                            </svg>
                                            <h5>No Permits Yet</h5>
                                            <p>Request a permit for a registered plantation using the button above.</p>
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
                                        <th>Plantation</th>
                                        <th>Type</th>
                                        <th>Requested</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($permits as $permit):
                                        $typeLabel = $permit['permit_type'] === 'certificate' ? 'Registration Certificate' : 'Cutting Permit';
                                    ?>
                                    <tr data-type="<?php echo htmlspecialchars($permit['permit_type']); ?>" data-status="<?php echo htmlspecialchars($permit['status']); ?>" data-permit-id="<?php echo (int)$permit['permit_id']; ?>" data-plantation-id="<?php echo (int)$permit['plantation_id']; ?>" data-plantation-name="<?php echo htmlspecialchars($permit['plantation_name']); ?>" data-requested="<?php echo date('M d, Y', strtotime($permit['requested_at'])); ?>" data-approved="<?php echo $permit['approved_at'] ? date('M d, Y', strtotime($permit['approved_at'])) : ''; ?>" data-remarks="<?php echo htmlspecialchars($permit['remarks'] ?? ''); ?>">
                                        <td class="permit-id">#<?php echo (int)$permit['permit_id']; ?></td>
                                        <td class="permit-plantation"><?php echo htmlspecialchars($permit['plantation_name']); ?></td>
                                        <td>
                                            <span class="permit-type-badge permit-type-<?php echo $permit['permit_type']; ?>"><?php echo htmlspecialchars($typeLabel); ?></span>
                                        </td>
                                        <td class="permit-date"><?php echo date('M d, Y', strtotime($permit['requested_at'])); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $permit['status']; ?>"><?php echo ucfirst($permit['status']); ?></span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action btn-review" onclick="viewPermitDetails(<?php echo (int)$permit['permit_id']; ?>)" title="View details">
                                                    <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                                                        <circle cx="12" cy="12" r="3" />
                                                    </svg>
                                                </button>
                                                <?php if ($permit['status'] === 'approved'): ?>
                                                    <?php if (!empty($permit['has_documents'])): ?>
                                                    <a href="../documents/documents.php?plantation=<?php echo (int)$permit['plantation_id']; ?>" class="btn-action btn-download" title="View documents">
                                                        <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                            <polyline points="14 2 14 8 20 8" />
                                                            <line x1="16" y1="13" x2="8" y2="13" />
                                                            <line x1="16" y1="17" x2="8" y2="17" />
                                                            <polyline points="10 9 9 9 8 9" />
                                                        </svg>
                                                    </a>
                                                    <?php else: ?>
                                                    <button type="button" class="btn-action btn-document-disabled" onclick="showNoDocumentsWarning()" title="No documents yet" disabled>
                                                        <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                                            <polyline points="14 2 14 8 20 8" />
                                                            <line x1="16" y1="13" x2="8" y2="13" />
                                                            <line x1="16" y1="17" x2="8" y2="17" />
                                                            <polyline points="10 9 9 9 8 9" />
                                                        </svg>
                                                    </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($permit['status'] === 'pending'): ?>
                                                <button type="button" class="btn-action btn-delete" onclick="cancelPermit(<?php echo (int)$permit['permit_id']; ?>)" title="Cancel request">
                                                    <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                        <line x1="18" y1="6" x2="6" y2="18" />
                                                        <line x1="6" y1="6" x2="18" y2="18" />
                                                    </svg>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div id="searchEmptyState" class="empty-state empty-state-search" style="display: none;">
                            <svg class="icon-svg empty-state-icon" style="width:2.5rem;height:2.5rem;margin:0 auto 0.75rem;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8" />
                                <line x1="21" y1="21" x2="16.65" y2="16.65" />
                            </svg>
                            <h5>No Results Found</h5>
                            <p>No permits match your search or filters.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Request Permit Modal -->
    <div class="modal fade" id="requestPermitModal" tabindex="-1" aria-labelledby="requestPermitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requestPermitModalLabel">Request New Permit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($available_plantations)): ?>
                    <div class="no-plantations-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <h4>No Available Plantations</h4>
                        <?php if (empty($plantations)): ?>
                        <p>You need at least one registered plantation before requesting a permit.</p>
                        <?php else: ?>
                        <p>All your registered plantations already have permit requests.</p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <form id="permitRequestForm">
                        <div class="mb-3">
                            <label class="form-label">Select Plantation</label>
                            <select class="form-select" name="plantation_id" required>
                                <option value="">Choose a plantation...</option>
                                <?php foreach ($available_plantations as $plantation): ?>
                                <option value="<?php echo (int)$plantation['plantation_id']; ?>"><?php echo htmlspecialchars($plantation['plantation_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Permit type</label>
                            <select class="form-select" name="permit_type" required>
                                <option value="certificate">Registration Certificate</option>
                                <option value="cutting">Cutting Permit</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3" placeholder="Optional notes..."></textarea>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if (!empty($available_plantations)): ?>
                    <button type="submit" form="permitRequestForm" class="btn btn-primary">Submit Request</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- View Permit Modal -->
    <div class="modal fade" id="viewPermitModal" tabindex="-1" aria-labelledby="viewPermitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPermitModalLabel">Permit Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="detail-group">
                        <div class="detail-label">Plantation</div>
                        <div class="detail-value" id="view-plantation-name"></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Status</div>
                        <div class="detail-value"><span id="view-permit-status" class="status-badge"></span></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Request Date</div>
                        <div class="detail-value" id="view-request-date"></div>
                    </div>
                    <div class="detail-group" id="approval-date-group">
                        <div class="detail-label">Approval Date</div>
                        <div class="detail-value" id="view-approval-date"></div>
                    </div>
                    <div class="detail-group">
                        <div class="detail-label">Remarks</div>
                        <div class="detail-value" id="view-remarks"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- No Documents Warning Modal -->
    <div class="modal fade" id="noDocumentsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">No Documents Available</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-exclamation-circle text-warning" style="font-size:3rem;margin-bottom:1rem;"></i>
                    <p>No documents have been uploaded for this plantation yet.</p>
                    <p class="text-muted">Upload documents in the Documents section.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="../documents/documents.php" class="btn btn-primary">Go to Documents</a>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var searchInput = document.getElementById('searchInput');
        var typeFilter = document.getElementById('typeFilter');
        var statusFilter = document.getElementById('statusFilter');
        var permitList = document.querySelector('.permit-list');
        var searchEmptyState = document.getElementById('searchEmptyState');
        var permitsCount = document.getElementById('permitsCount');

        function filterPermits() {
            if (!permitList) return;
            var searchTerm = (searchInput && searchInput.value) ? searchInput.value.toLowerCase() : '';
            var typeValue = (typeFilter && typeFilter.value) ? typeFilter.value.toLowerCase() : '';
            var statusValue = (statusFilter && statusFilter.value) ? statusFilter.value.toLowerCase() : '';
            var rows = permitList.querySelectorAll('tbody tr');
            var visibleCount = 0;
            rows.forEach(function(row) {
                var rowText = row.textContent.toLowerCase();
                var dataType = row.getAttribute('data-type') || '';
                var dataStatus = row.getAttribute('data-status') || '';
                var matchSearch = !searchTerm || rowText.indexOf(searchTerm) !== -1;
                var matchType = !typeValue || dataType === typeValue;
                var matchStatus = !statusValue || dataStatus === statusValue;
                var visible = matchSearch && matchType && matchStatus;
                row.style.display = visible ? '' : 'none';
                if (visible) visibleCount++;
            });
            if (permitsCount) permitsCount.textContent = visibleCount + ' permit' + (visibleCount !== 1 ? 's' : '') + ' found';
            if (searchEmptyState && permitList) {
                var noResults = visibleCount === 0 && rows.length > 0;
                searchEmptyState.style.display = noResults ? 'flex' : 'none';
                permitList.style.display = noResults ? 'none' : '';
            }
        }
        if (searchInput) searchInput.addEventListener('input', filterPermits);
        if (typeFilter) typeFilter.addEventListener('change', filterPermits);
        if (statusFilter) statusFilter.addEventListener('change', filterPermits);
        var btnFilter = document.getElementById('btnFilterPermits');
        if (btnFilter) btnFilter.addEventListener('click', filterPermits);

        var form = document.getElementById('permitRequestForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                fetch('../../../handlers/submit_permit.php', { method: 'POST', body: formData })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.status === 'success') location.reload();
                        else showNotification('error', 'Error: ' + (data.message || 'Request failed'));
                    })
                    .catch(function(err) {
                        console.error(err);
                        showNotification('error', 'An error occurred while submitting the request');
                    });
            });
        }
    });

    function viewPermitDetails(permitId) {
        var row = document.querySelector('.permit-list tr[data-permit-id="' + permitId + '"]');
        if (!row) return;
        document.getElementById('view-plantation-name').textContent = row.getAttribute('data-plantation-name') || '';
        var statusEl = document.getElementById('view-permit-status');
        var status = row.getAttribute('data-status') || '';
        statusEl.textContent = status.charAt(0).toUpperCase() + status.slice(1);
        statusEl.className = 'status-badge status-' + status;
        document.getElementById('view-request-date').textContent = row.getAttribute('data-requested') || '';
        document.getElementById('view-approval-date').textContent = row.getAttribute('data-approved') || '—';
        document.getElementById('view-remarks').textContent = row.getAttribute('data-remarks') || 'None';
        document.getElementById('approval-date-group').style.display = 'block';
        var m = new bootstrap.Modal(document.getElementById('viewPermitModal'));
        m.show();
    }

    function showNoDocumentsWarning() {
        var m = new bootstrap.Modal(document.getElementById('noDocumentsModal'));
        m.show();
    }

    function viewPlantationDocuments(plantationId) {
        window.location.href = '../documents/documents.php?plantation=' + plantationId;
    }

    function cancelPermit(permitId) {
        if (!confirm('Are you sure you want to cancel this permit request?')) return;
        fetch('../../../handlers/cancel_permit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ permit_id: permitId })
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.status === 'success') location.reload();
                else showNotification('error', 'Error: ' + (data.message || 'Cancel failed'));
            })
            .catch(function(err) {
                console.error(err);
                showNotification('error', 'An error occurred');
            });
    }
    </script>
</body>
</html>
