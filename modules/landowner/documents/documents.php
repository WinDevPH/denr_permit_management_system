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

// Fetch user's documents
try {
    $query = "SELECT d.*, p.plantation_name 
              FROM documents d
              JOIN plantations p ON d.plantation_id = p.plantation_id
              WHERE p.user_id = :user_id
              ORDER BY d.uploaded_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $documents = [];
}

// Fetch registered plantations with approved permits for upload modal
try {
    $query = "SELECT DISTINCT p.plantation_id, p.plantation_name 
              FROM plantations p 
              JOIN permits pm ON p.plantation_id = pm.plantation_id
              WHERE p.user_id = :user_id 
              AND p.status = 'registered'
              AND pm.status = 'approved'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $plantations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $plantations = [];
}

// Unique plantation names for filter dropdown
$plantation_names = array_values(array_unique(array_column($documents, 'plantation_name')));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/documents.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>
<body class="documents-page">
    <div class="loading-overlay" id="loadingOverlay" aria-hidden="true">
        <div class="doc-loading-content">
            <div class="doc-loading-spinner"></div>
            <p class="doc-loading-text">Processing...</p>
            <span class="doc-loading-percent loading-percentage" id="loadingPercent">0%</span>
        </div>
    </div>

    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../../../includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard admin-documents">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Documents</h1>
                        <p class="admin-dashboard-subtitle">Upload and manage documents for your plantations.</p>
                    </div>
                    <button type="button" class="doc-btn-upload" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal" aria-label="Upload document">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Upload Document</span>
                    </button>
                </header>

                <?php if (!empty($documents)): ?>
                <div class="doc-toolbar">
                    <div class="doc-stats">
                        <span class="doc-stat-badge">
                            <i class="fas fa-file-alt"></i>
                            <strong><?php echo count($documents); ?></strong> document<?php echo count($documents) !== 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    <div class="doc-filters">
                        <div class="doc-search-wrap">
                            <i class="fas fa-search doc-search-icon"></i>
                            <input type="text" id="docSearch" class="doc-search-input" placeholder="Search by name or plantation..." aria-label="Search documents">
                        </div>
                        <?php if (count($plantation_names) > 1): ?>
                        <select id="docFilterPlantation" class="doc-filter-select" aria-label="Filter by plantation">
                            <option value="">All plantations</option>
                            <?php foreach ($plantation_names as $pname): ?>
                            <option value="<?php echo htmlspecialchars($pname); ?>"><?php echo htmlspecialchars($pname); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="documents-grid" id="documentsGrid">
                    <?php if (empty($documents)): ?>
                    <div class="doc-empty-state">
                        <div class="doc-empty-icon">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h2 class="doc-empty-title">No documents yet</h2>
                        <p class="doc-empty-desc">Upload permits, certificates, or other files linked to your approved plantations.</p>
                        <?php if (!empty($plantations)): ?>
                        <button type="button" class="doc-btn-upload doc-btn-upload--center" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Upload your first document</span>
                        </button>
                        <?php else: ?>
                        <p class="doc-empty-hint">You need at least one plantation with an approved permit to upload documents.</p>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <?php foreach ($documents as $document): 
                        $ext = strtolower(pathinfo($document['file_name'], PATHINFO_EXTENSION));
                        $icon_class = 'fa-file-alt';
                        $type_class = 'doc-type-default';
                        if ($ext === 'pdf') { $icon_class = 'fa-file-pdf'; $type_class = 'doc-type-pdf'; }
                        elseif (in_array($ext, ['doc', 'docx'])) { $icon_class = 'fa-file-word'; $type_class = 'doc-type-word'; }
                    ?>
                    <article class="document-card" data-name="<?php echo htmlspecialchars($document['document_name']); ?>" data-plantation="<?php echo htmlspecialchars($document['plantation_name']); ?>">
                        <div class="document-card-inner">
                            <div class="document-card-header">
                                <div class="document-icon-wrap <?php echo $type_class; ?>">
                                    <i class="fas <?php echo $icon_class; ?>"></i>
                                </div>
                                <span class="document-type-badge"><?php echo strtoupper($ext); ?></span>
                            </div>
                            <h3 class="document-title"><?php echo htmlspecialchars($document['document_name']); ?></h3>
                            <ul class="document-meta-list" aria-label="Document details">
                                <li>
                                    <i class="fas fa-tree"></i>
                                    <span><?php echo htmlspecialchars($document['plantation_name']); ?></span>
                                </li>
                                <li>
                                    <i class="fas fa-calendar-alt"></i>
                                    <span><?php echo date('M j, Y', strtotime($document['uploaded_at'])); ?></span>
                                </li>
                            </ul>
                            <div class="document-actions">
                                <a href="../../../<?php echo htmlspecialchars($document['file_path']); ?>" class="doc-action doc-action-download" download="<?php echo htmlspecialchars($document['file_name']); ?>" title="Download">
                                    <i class="fas fa-download"></i>
                                    <span>Download</span>
                                </a>
                                <button type="button" class="doc-action doc-action-delete" onclick="deleteDocument(<?php echo (int)$document['doc_id']; ?>)" title="Delete document">
                                    <i class="fas fa-trash-alt"></i>
                                    <span>Delete</span>
                                </button>
                            </div>
                        </div>
                    </article>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal fade" id="uploadDocumentModal" tabindex="-1" aria-labelledby="uploadDocumentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content doc-modal-content">
                <div class="modal-header doc-modal-header">
                    <h2 class="modal-title doc-modal-title" id="uploadDocumentModalLabel">
                        <i class="fas fa-cloud-upload-alt"></i>
                        Upload Document
                    </h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body doc-modal-body">
                    <?php if (empty($plantations)): ?>
                    <div class="doc-no-plantations">
                        <div class="doc-no-plantations-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h3>No approved plantations</h3>
                        <p>You need at least one plantation with an approved permit before you can upload documents. Register a plantation and get it approved first.</p>
                    </div>
                    <?php else: ?>
                    <form id="uploadDocumentForm" class="doc-upload-form">
                        <div class="doc-form-group">
                            <label for="documentName" class="doc-form-label">Document name</label>
                            <div class="doc-input-wrap">
                                <i class="fas fa-file-signature doc-input-icon"></i>
                                <input type="text" id="documentName" name="document_name" class="doc-form-input" required maxlength="200" placeholder="e.g. Land title, MOA, Permit copy">
                            </div>
                        </div>
                        <div class="doc-form-group">
                            <label for="documentCategory" class="doc-form-label">Document type</label>
                            <div class="doc-input-wrap">
                                <i class="fas fa-tag doc-input-icon"></i>
                                <select id="documentCategory" name="document_category" class="doc-form-select" required>
                                    <option value="land_title">Land title / titulo</option>
                                    <option value="tax_declaration">Tax Declaration</option>
                                    <option value="moa">Memorandum of Agreement (MOA)</option>
                                    <option value="permit">Permit / clearance</option>
                                    <option value="certificate">Certificate</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <p class="small text-muted mb-0 mt-2">Official land records in the Philippines are maintained by the <a href="https://www.lra.gov.ph/" target="_blank" rel="noopener">Land Registration Authority (LRA)</a>. You may obtain or verify title information through LRA services, then upload a PDF copy here.</p>
                        </div>
                        <div class="doc-form-group">
                            <label for="plantationId" class="doc-form-label">Plantation</label>
                            <div class="doc-input-wrap">
                                <i class="fas fa-tree doc-input-icon"></i>
                                <select id="plantationId" name="plantation_id" class="doc-form-select" required>
                                    <option value="">Choose a plantation...</option>
                                    <?php foreach ($plantations as $plantation): ?>
                                    <option value="<?php echo (int)$plantation['plantation_id']; ?>"><?php echo htmlspecialchars($plantation['plantation_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="doc-form-group">
                            <label class="doc-form-label">File</label>
                            <div class="doc-file-zone" id="fileDropZone">
                                <input type="file" id="documentFile" name="document" class="doc-file-input" required accept=".pdf,.doc,.docx" aria-label="Choose file">
                                <div class="doc-file-placeholder" id="filePlaceholder">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p><strong>Choose a file</strong> or drag it here</p>
                                    <span class="doc-file-hint">PDF, DOC or DOCX · Max 5MB</span>
                                </div>
                                <div class="doc-file-chosen" id="fileChosen" hidden>
                                    <i class="fas fa-file-alt"></i>
                                    <span class="doc-file-name" id="chosenFileName"></span>
                                    <button type="button" class="doc-file-clear" id="fileClear" aria-label="Clear file"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                <?php if (!empty($plantations)): ?>
                <div class="modal-footer doc-modal-footer">
                    <button type="button" class="doc-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="uploadDocumentForm" class="doc-btn-upload doc-btn-upload--submit">
                        <i class="fas fa-upload"></i>
                        <span>Upload</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../assets/js/documents.js"></script>
    <script>
    (function() {
        var loadingOverlay = document.getElementById('loadingOverlay');
        var loadingPercent = document.getElementById('loadingPercent');
        if (loadingOverlay && loadingPercent) {
            window.showLoading = function() { loadingOverlay.style.display = 'flex'; };
            window.hideLoading = function() { loadingOverlay.style.display = 'none'; };
            window.setLoadingPercent = function(v) { loadingPercent.textContent = v + '%'; };
        }

        var searchInput = document.getElementById('docSearch');
        var filterSelect = document.getElementById('docFilterPlantation');
        var cards = document.querySelectorAll('.document-card');
        function applyFilters() {
            var q = (searchInput && searchInput.value) ? searchInput.value.trim().toLowerCase() : '';
            var plant = (filterSelect && filterSelect.value) ? filterSelect.value.toLowerCase() : '';
            cards.forEach(function(card) {
                var name = (card.getAttribute('data-name') || '').toLowerCase();
                var plantation = (card.getAttribute('data-plantation') || '').toLowerCase();
                var matchSearch = !q || name.indexOf(q) !== -1 || plantation.indexOf(q) !== -1;
                var matchPlant = !plant || plantation === plant;
                card.style.display = (matchSearch && matchPlant) ? '' : 'none';
            });
        }
        if (searchInput) searchInput.addEventListener('input', applyFilters);
        if (filterSelect) filterSelect.addEventListener('change', applyFilters);

        var fileInput = document.getElementById('documentFile');
        var filePlaceholder = document.getElementById('filePlaceholder');
        var fileChosen = document.getElementById('fileChosen');
        var chosenFileName = document.getElementById('chosenFileName');
        var fileClear = document.getElementById('fileClear');
        var dropZone = document.getElementById('fileDropZone');
        if (fileInput && filePlaceholder && fileChosen && chosenFileName && fileClear && dropZone) {
            fileInput.addEventListener('change', function() {
                var file = fileInput.files[0];
                if (file) {
                    chosenFileName.textContent = file.name;
                    filePlaceholder.hidden = true;
                    fileChosen.hidden = false;
                } else {
                    filePlaceholder.hidden = false;
                    fileChosen.hidden = true;
                }
            });
            fileClear.addEventListener('click', function() {
                fileInput.value = '';
                filePlaceholder.hidden = false;
                fileChosen.hidden = true;
            });
            dropZone.addEventListener('dragover', function(e) { e.preventDefault(); dropZone.classList.add('doc-file-zone--dragover'); });
            dropZone.addEventListener('dragleave', function() { dropZone.classList.remove('doc-file-zone--dragover'); });
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                dropZone.classList.remove('doc-file-zone--dragover');
                if (e.dataTransfer.files.length) fileInput.files = e.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            });
        }
    })();
    </script>
</body>
</html>
