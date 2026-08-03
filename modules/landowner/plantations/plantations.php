<?php
session_start();
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    header('Location: ../../../index.php');
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Profile defaults for auto-fill in plantation registration
$profile_defaults = ['full_name' => '', 'contact_number' => ''];
try {
    $pu = $db->prepare('SELECT full_name, contact_number FROM users WHERE user_id = ?');
    $pu->execute([$user_id]);
    $prow = $pu->fetch(PDO::FETCH_ASSOC);
    if ($prow) {
        $profile_defaults['full_name'] = (string) ($prow['full_name'] ?? '');
        $profile_defaults['contact_number'] = (string) ($prow['contact_number'] ?? '');
    }
} catch (PDOException $e) {
    // ignore
}

// Fetch plantations
try {
    $query = "SELECT * FROM plantations WHERE user_id = :user_id ORDER BY registered_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $plantations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error: " . $e->getMessage());
    $error = true;
}

// Fetch tree species for dropdown
try {
    $speciesQuery = "SELECT * FROM tree_species ORDER BY species_name ASC";
    $speciesStmt = $db->prepare($speciesQuery);
    $speciesStmt->execute();
    $treeSpecies = $speciesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching species: " . $e->getMessage());
    $treeSpecies = [];
}

/**
 * Format tree_species for display. Supports:
 * - New format: "Mahogany:5,Narra:7" -> "Mahogany (5), Narra (7)"
 * - Legacy: "Mahogany, Narra" -> "Mahogany (1), Narra (1)"
 */
function formatTreeSpeciesDisplay($tree_species) {
    if ($tree_species === null || trim($tree_species) === '') return '';
    $parts = array_map('trim', explode(',', $tree_species));
    $out = [];
    foreach ($parts as $p) {
        if (strpos($p, ':') !== false) {
            list($name, $qty) = explode(':', $p, 2);
            $out[] = trim($name) . ' (' . (int) $qty . ')';
        } else {
            if ($p !== '') $out[] = $p . ' (1)';
        }
    }
    return implode(', ', $out);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plantations - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/landowner.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/plantations.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png" />

</head>

<body>
    <!-- Loading Animation Overlay -->
    <div class="loading-overlay">
        <div class="loading-circle">
            <div class="loading-circle-outer"></div>
            <div class="loading-circle-inner"></div>
            <div class="loading-percentage">0%</div>
            <div class="loading-text">Processing...</div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>

    <div class="dashboard-container">
        <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php include __DIR__ . '/../../../includes/header.php'; ?>

            <div class="dashboard-content admin-dashboard">
                <header class="admin-dashboard-header">
                    <div>
                        <h1 class="admin-dashboard-title">Plantations</h1>
                        <p class="admin-dashboard-subtitle">Register and manage your tree plantations.</p>
                    </div>
                    <button type="button" class="add-plantation-btn" id="openAddPlantationModal" aria-label="Add New Plantation">
                        <i class="fas fa-plus"></i> Add New Plantation
                    </button>
                </header>

                <div class="plantation-container">
                    <div class="plantation-grid">
                        <?php if (empty($plantations)): ?>
                        <div class="empty-state">
                            <i class="fas fa-seedling"></i>
                            <h3>Start Your Green Journey Today</h3>
                            <p>Begin your contribution to environmental conservation by registering your first
                                plantation
                                through the "Add New Plantation" button above.</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($plantations as $plantation): ?>
                        <div class="plantation-card" data-plantation-id="<?php echo $plantation['plantation_id']; ?>"
                            data-name="<?php echo htmlspecialchars($plantation['plantation_name']); ?>"
                            data-tree_species="<?php echo htmlspecialchars($plantation['tree_species']); ?>"
                            data-land_area="<?php echo htmlspecialchars($plantation['land_area']); ?>"
                            data-age_of_plantation="<?php echo htmlspecialchars($plantation['age_of_plantation'] ?? ''); ?>"
                            data-location_address="<?php echo htmlspecialchars($plantation['location_address']); ?>"
                            data-latitude="<?php echo htmlspecialchars($plantation['latitude'] ?? ''); ?>"
                            data-longitude="<?php echo htmlspecialchars($plantation['longitude'] ?? ''); ?>"
                            data-landmark_latitude="<?php echo htmlspecialchars($plantation['landmark_latitude'] ?? ''); ?>"
                            data-landmark_longitude="<?php echo htmlspecialchars($plantation['landmark_longitude'] ?? ''); ?>"
                            data-mohon-points="<?php echo htmlspecialchars($plantation['mohon_points_json'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-district="<?php echo htmlspecialchars($plantation['district'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-contact_person_name="<?php echo htmlspecialchars($plantation['contact_person_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-contact_address="<?php echo htmlspecialchars($plantation['contact_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-contact_phone="<?php echo htmlspecialchars($plantation['contact_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            data-status="<?php echo $plantation['status']; ?>">

                            <div class="plantation-status <?php echo $plantation['status']; ?>">
                                <?php
                                $st = $plantation['status'];
                                echo htmlspecialchars($st === 'validated' ? 'Checked' : ucfirst($st));
                                ?>
                            </div>
                            <?php if ($st === 'rejected' && !empty($plantation['rejection_reason'])): ?>
                            <div class="small text-danger px-3 pt-1">
                                <i class="fas fa-ban"></i>
                                <?php echo htmlspecialchars($plantation['rejection_reason']); ?>
                            </div>
                            <?php endif; ?>

                            <div class="plantation-content">
                                <div class="plantation-header">
                                    <h3><?php echo htmlspecialchars($plantation['plantation_name']); ?></h3>
                                </div>

                                <div class="plantation-info">
                                    <div class="info-item">
                                        <i class="fas fa-leaf"></i>
                                        <span><?php echo htmlspecialchars(formatTreeSpeciesDisplay($plantation['tree_species'])); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-ruler-combined"></i>
                                        <span><?php echo number_format($plantation['land_area'], 2); ?> hectares</span>
                                    </div>
                                    <?php if (isset($plantation['age_of_plantation']) && $plantation['age_of_plantation'] !== null && $plantation['age_of_plantation'] !== ''): ?>
                                    <div class="info-item">
                                        <i class="fas fa-hourglass-half"></i>
                                        <span><?php
                                            $ageVal = (float) $plantation['age_of_plantation'];
                                            echo htmlspecialchars(rtrim(rtrim(number_format($ageVal, 1, '.', ''), '0'), '.'));
                                        ?> year<?php echo abs($ageVal - 1.0) < 0.001 ? '' : 's'; ?> old</span>
                                    </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($plantation['location_address']); ?></span>
                                    </div>
                                    <?php if (!empty($plantation['contact_person_name']) || !empty($plantation['contact_phone'])): ?>
                                    <div class="info-item">
                                        <i class="fas fa-user-circle"></i>
                                        <span><?php echo htmlspecialchars(trim(($plantation['contact_person_name'] ?? '') . (isset($plantation['contact_phone']) && $plantation['contact_phone'] !== '' ? ' · ' . $plantation['contact_phone'] : ''))); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="plantation-actions">
                                <button class="p-action-btn p-btn-view" title="View Details"
                                    data-id="<?php echo $plantation['plantation_id']; ?>">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($plantation['status'] !== 'registered'): ?>
                                <button class="p-action-btn p-btn-edit" title="Edit Plantation"
                                    data-id="<?php echo $plantation['plantation_id']; ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="p-action-btn p-btn-delete" title="Delete Plantation"
                                    data-id="<?php echo $plantation['plantation_id']; ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Plantation Modal -->
    <div class="modal fade" id="addPlantationModal" tabindex="-1" role="dialog"
        aria-labelledby="addPlantationModalLabel">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPlantationModalLabel">Register New Plantation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addPlantationForm">
                        <div class="form-group">
                            <label class="form-label">Plantation Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-tree"></i></span>
                                <input type="text" class="form-control" name="plantation_name"
                                    placeholder="Enter plantation name" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tree Species <small class="text-muted">(Species and quantity per species)</small></label>
                            <input type="hidden" name="tree_species" id="tree_species_hidden" value="">
                            <div id="tree_species_rows" class="tree-species-rows">
                                <div class="tree-species-row row g-2 align-items-center mb-2">
                                    <div class="col-md-7">
                                        <select class="form-control tree-species-select" data-row="0">
                                            <option value="">-- Select species --</option>
                                            <?php foreach ($treeSpecies as $species): ?>
                                            <option value="<?php echo htmlspecialchars($species['species_name']); ?>">
                                                <?php echo htmlspecialchars($species['species_name']); ?>
                                                <?php if (!empty($species['scientific_name'])): ?>
                                                (<?php echo htmlspecialchars($species['scientific_name']); ?>)
                                                <?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" class="form-control tree-species-qty" min="1" placeholder="Qty" value="1" data-row="0">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-species-row" title="Remove" disabled><i class="fas fa-minus"></i></button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="add_species_row"><i class="fas fa-plus"></i> Add species</button>
                            <small class="form-text text-muted d-block mt-1">Add each species and enter the number of trees (e.g. Mahogany: 5, Narra: 7)</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Verification Document (Image/PDF)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-file-upload"></i></span>
                                <input type="file" class="form-control" name="verification_document"
                                    accept="image/*,.pdf,.doc,.docx" id="verification_document">
                            </div>
                            <small class="form-text text-muted">
                                Upload land title or other ownership documents.
                                Max file size: 5MB. Accepted formats: JPG, PNG, PDF, DOC, DOCX
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tax Declaration <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-file-invoice"></i></span>
                                <input type="file" class="form-control" name="tax_declaration"
                                    accept="image/*,.pdf,.doc,.docx" id="tax_declaration">
                            </div>
                            <small class="form-text text-muted">
                                Upload Tax Declaration. Max 5MB (JPG, PNG, PDF, DOC, DOCX).
                            </small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Picture of the site <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-camera"></i></span>
                                <input type="file" class="form-control" name="site_photo"
                                    accept="image/*" id="site_photo">
                            </div>
                            <small class="form-text text-muted">
                                Clear photo of the plantation site. Max 5MB (JPG, PNG).
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Land Area (hectares)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-ruler"></i></span>
                                        <input type="number" class="form-control" name="land_area" step="0.01"
                                            placeholder="0.00" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Age of Plantation (years) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-hourglass-half"></i></span>
                                        <input type="number" class="form-control" name="age_of_plantation"
                                            id="age_of_plantation" step="0.1" min="0" max="9999"
                                            placeholder="e.g. 5" required>
                                    </div>
                                    <small class="form-text text-muted">How many years old is the plantation?</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label class="form-label">Location Address <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                        <input type="text" class="form-control" name="location_address"
                                            id="location_address" required
                                            placeholder="Site location for Tax Declaration">
                                    </div>
                                    <small class="form-text text-muted">Location of the site (required with Tax Declaration).</small>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">District / locality <small class="text-muted">(optional, helps assign verifiers)</small></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-map"></i></span>
                                <input type="text" class="form-control" name="district" id="district"
                                    placeholder="e.g. Zamboanga City — District II" maxlength="120">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Name <small class="text-muted">(contact person for this plantation)</small></label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="contact_person_name" id="contact_person_name"
                                    placeholder="Full name" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-home"></i></span>
                                        <input type="text" class="form-control" name="contact_address" id="contact_address"
                                            placeholder="Mailing or site address" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Contact</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="text" class="form-control" name="contact_phone" id="contact_phone"
                                            placeholder="Digits only (e.g. 09171234567)" required autocomplete="tel"
                                            inputmode="numeric" pattern="[0-9]{7,15}" maxlength="15" title="7–15 digits, no letters">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Select Location on Map</label>

                            <div class="map-instructions">
                                <i class="fas fa-info-circle"></i>
                                <span><strong>1)</strong> Search or click the map to set the <strong>lot</strong> (blue pin).
                                    <strong>2)</strong> Use <em>Add Mohon</em> to place boundary corners (red numbered pins).
                                    The map draws a <strong>boundary track</strong> (line if 2 pins, closed area if 3+).</span>
                            </div>

                            <div class="map-search-container">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="searchLocation"
                                        placeholder="Search location (e.g. city, address, landmark)">
                                    <button class="btn" type="button" id="searchBtn">
                                        <i class="fas fa-arrow-right"></i>
                                        <div class="spinner-border text-light" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </button>
                                </div>
                            </div>

                            <div class="map-container">
                                <div class="map-status">
                                    <i class="fas fa-spinner fa-spin"></i>
                                    <span>Searching location...</span>
                                </div>
                                <div id="map"></div>
                            </div>
                            <div class="lot-coordinates-display mt-2 p-2 rounded" id="lotCoordinatesDisplay" style="background: #f0f2f5; font-size: 0.875rem;">
                                <strong>Lot location (Coordinates):</strong>
                                <span id="coordinatesText" class="coordinates-live text-muted">Click on map to pin — coordinates update in real time</span>
                                <p class="small text-muted mb-0 mt-1">Click on the map to set the <strong>lot location</strong> (blue marker).</p>
                            </div>
                        </div>

                        <input type="hidden" name="latitude" id="latitude" required>
                        <input type="hidden" name="longitude" id="longitude" required>
                        <input type="hidden" name="mohon_points_json" id="mohon_points_json" value="">
                        <input type="hidden" name="landmark_latitude" id="landmark_latitude" value="">
                        <input type="hidden" name="landmark_longitude" id="landmark_longitude" value="">
                        <div class="mt-2 p-2 rounded" style="background: #f0f2f5; font-size: 0.875rem;">
                            <strong><i class="fas fa-draw-polygon me-1"></i> Mohon (boundary corners)</strong>
                            <p class="small text-muted mb-2 mt-1">Place multiple red pins along the plantation boundary. The map shows a connecting <strong>track</strong> and a closed <strong>boundary</strong> when you have three or more points.</p>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-danger" id="toggleMohonPlaceBtn" title="Each map click adds a numbered Mohon">
                                    <i class="fas fa-plus"></i> Add Mohon (click map)
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="doneMohonBtn" style="display:none;">
                                    <i class="fas fa-check"></i> Done placing Mohon
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearMohonBtn">
                                    <i class="fas fa-eraser"></i> Clear all Mohon
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="fitMapBoundsBtn">
                                    <i class="fas fa-compress-arrows-alt"></i> Fit map to lot &amp; boundary
                                </button>
                            </div>
                            <div id="mohonSummary" class="small text-muted mb-0" aria-live="polite">No Mohon placed yet.</div>
                            <div id="boundaryAreaDisplay" class="small mt-1 mb-0" aria-live="polite"></div>
                        </div>
                        <!-- hidden id to switch between add / edit -->
                        <input type="hidden" name="plantation_id" id="plantation_id" value="">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addPlantationForm" class="btn btn-primary"
                        id="submitPlantation">Register Plantation</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Plantation Modal (display pinned location) -->
    <div class="modal fade" id="viewPlantationModal" tabindex="-1" role="dialog" aria-labelledby="viewPlantationTitle">
        <div class="modal-dialog modal-md modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPlantationTitle">Plantation Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="viewMap" style="height:320px; border-radius:8px;"></div>
                    <p class="mt-2 mb-1" id="viewPlantationAddress"></p>
                    <p class="small text-muted mb-0" id="viewPlantationCoordinates"></p>
                    <p class="small text-muted mb-0" id="viewPlantationMohon" style="display:none;"></p>
                    <p class="small text-muted mb-0" id="viewPlantationArea" style="display:none;"></p>
                    <div id="viewPlantationTimeline" class="mt-3 small" style="display:none;">
                        <h6 class="fw-bold mb-2"><i class="fas fa-history me-1"></i> Application log</h6>
                        <ul class="list-unstyled mb-2" id="viewPlantationTimelineList"></ul>
                        <p class="mb-0" id="viewPlantationDates"></p>
                    </div>
                    <div class="mt-2" id="viewCertLinkWrap" style="display:none;">
                        <a href="#" class="btn btn-sm btn-outline-primary" id="viewCertLink" target="_blank" rel="noopener"><i class="fas fa-certificate me-1"></i> Registration certificate (print)</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="denr-profile-json"><?php echo json_encode($profile_defaults, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>

    <!-- Include jQuery (required for existing $('#addPlantationModal').on(...) usage) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- <script src="../../../assets/js/plantations.js"></script> -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="../../../assets/js/polygon_area.js"></script>

    <style>
    /* Loading Animation Styles */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.95);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .loading-circle {
        position: relative;
        width: 120px;
        height: 120px;
    }

    .loading-circle-outer {
        position: absolute;
        border: 3px solid var(--primary-color, #28a745);
        border-right-color: transparent;
        border-bottom-color: transparent;
        width: 100%;
        height: 100%;
        border-radius: 50%;
        animation: spinCircle 1s linear infinite;
    }

    .loading-circle-inner {
        position: absolute;
        border: 3px solid var(--secondary-color, #20c997);
        border-left-color: transparent;
        border-top-color: transparent;
        width: 80%;
        height: 80%;
        top: 10%;
        left: 10%;
        border-radius: 50%;
        animation: spinCircle 1s linear infinite reverse;
    }

    .loading-percentage {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--primary-color, #28a745);
    }

    .loading-text {
        position: absolute;
        width: 100%;
        text-align: center;
        bottom: -40px;
        font-size: 0.9rem;
        color: var(--primary-color, #28a745);
    }

    @keyframes spinCircle {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    /* Select2 Custom Styling */
    .select2-container--bootstrap-5 .select2-selection {
        border-color: #dee2e6;
        min-height: 38px;
    }

    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background-color: #28a745;
        border-color: #28a745;
        color: white;
        padding: 2px 8px;
        margin: 3px;
    }

    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
        color: white;
        margin-right: 5px;
    }

    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ff6b6b;
    }

    .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }

    .map-status {
        display: none;
        position: absolute;
        top: 10px;
        left: 10px;
        background: rgba(255, 255, 255, 0.8);
        padding: 8px 12px;
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        align-items: center;
        font-size: 0.9rem;
    }

    .map-status.show {
        display: flex;
    }

    .map-status i {
        margin-right: 8px;
        color: var(--primary-color, #28a745);
    }

    .map-instructions {
        font-size: 0.9rem;
        color: #555;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
    }

    .map-instructions i {
        margin-right: 5px;
        color: var(--primary-color, #28a745);
    }

    .loading-pulse {
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.02);
        }

        100% {
            transform: scale(1);
        }
    }
    </style>

    <script>
    let map, marker;
    let viewMap, viewMarker;
    let locatingSpinner;
    /** Set by editPlantation; applied on shown.bs.modal after map exists (fixes race with setTimeout). */
    var pendingPlantationMapEdit = null;

    var DENR_PROFILE_DEFAULTS = {};
    try {
        var _pj = document.getElementById('denr-profile-json');
        if (_pj && _pj.textContent) DENR_PROFILE_DEFAULTS = JSON.parse(_pj.textContent);
    } catch (e) { DENR_PROFILE_DEFAULTS = {}; }

    function denrDigitsOnly(val) {
        return String(val || '').replace(/\D/g, '');
    }

    // Tree species rows: build hidden value, add/remove rows
    function buildTreeSpeciesFromRows() {
        const rows = document.querySelectorAll('#tree_species_rows .tree-species-row');
        const parts = [];
        rows.forEach(function(row) {
            const sel = row.querySelector('.tree-species-select');
            const qty = row.querySelector('.tree-species-qty');
            const name = sel ? sel.value.trim() : '';
            const num = (qty && qty.value !== '') ? parseInt(qty.value, 10) : 0;
            if (name && num > 0) parts.push(name + ':' + num);
        });
        document.getElementById('tree_species_hidden').value = parts.join(',');
        return parts.length > 0;
    }

    function updateRemoveSpeciesButtons() {
        const rows = document.querySelectorAll('#tree_species_rows .tree-species-row');
        rows.forEach(function(row, i) {
            const btn = row.querySelector('.remove-species-row');
            if (btn) btn.disabled = rows.length <= 1;
        });
    }

    function addSpeciesRow() {
        const container = document.getElementById('tree_species_rows');
        const firstRow = container.querySelector('.tree-species-row');
        if (!firstRow) return;
        const clone = firstRow.cloneNode(true);
        clone.querySelector('.tree-species-select').value = '';
        clone.querySelector('.tree-species-qty').value = '1';
        clone.querySelectorAll('.tree-species-select, .tree-species-qty').forEach(function(el) { el.dataset.row = container.querySelectorAll('.tree-species-row').length; });
        container.appendChild(clone);
        updateRemoveSpeciesButtons();
    }

    function resetSpeciesRowsToOne() {
        const container = document.getElementById('tree_species_rows');
        const firstRow = container.querySelector('.tree-species-row');
        if (!firstRow) return;
        container.innerHTML = '';
        const row = firstRow.cloneNode(true);
        row.querySelector('.tree-species-select').value = '';
        row.querySelector('.tree-species-qty').value = '1';
        row.querySelector('.remove-species-row').disabled = true;
        container.appendChild(row);
    }

    $(document).ready(function() {
        $('#add_species_row').on('click', addSpeciesRow);
        $(document).on('click', '.remove-species-row', function() {
            const row = this.closest('.tree-species-row');
            if (row && document.querySelectorAll('#tree_species_rows .tree-species-row').length > 1) {
                row.remove();
                updateRemoveSpeciesButtons();
            }
        });
    });

    // Create custom location control
    L.Control.Location = L.Control.extend({
        onAdd: function(map) {
            const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
            const button = L.DomUtil.create('a', 'location-button', div);
            button.innerHTML = '<i class="fas fa-location-crosshairs"></i>';
            button.title = 'My Location';
            button.href = '#';
            button.style.display = 'flex';
            button.style.alignItems = 'center';
            button.style.justifyContent = 'center';
            button.style.width = '34px';
            button.style.height = '34px';

            // Create spinner element
            locatingSpinner = L.DomUtil.create('span', 'spinner', button);
            locatingSpinner.style.display = 'none';
            locatingSpinner.style.width = '14px';
            locatingSpinner.style.height = '14px';
            locatingSpinner.style.border = '2px solid #f3f3f3';
            locatingSpinner.style.borderTop = '2px solid #3388ff';
            locatingSpinner.style.borderRadius = '50%';
            locatingSpinner.style.animation = 'spin 1s linear infinite';

            L.DomEvent.on(button, 'click', function(e) {
                L.DomEvent.preventDefault(e);
                getCurrentLocation();
            });

            return div;
        }
    });

    // Add spinning animation style
    const style = document.createElement('style');
    style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
    document.head.appendChild(style);

    // Fullscreen control
    const fullScreenControl = L.Control.extend({
        onAdd: function(map) {
            const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control');
            const button = L.DomUtil.create('a', 'fullscreen-button', container);
            button.innerHTML = '<i class="fas fa-expand"></i>';
            button.title = 'Full Screen';
            button.href = '#';
            button.style.display = 'flex';
            button.style.alignItems = 'center';
            button.style.justifyContent = 'center';
            button.style.width = '34px';
            button.style.height = '34px';

            L.DomEvent.on(button, 'click', function(e) {
                L.DomEvent.preventDefault(e);
                toggleFullScreen();
            });

            return container;
        }
    });

    function toggleFullScreen() {
        const mapContainer = document.getElementById('map');
        if (!document.fullscreenElement) {
            if (mapContainer.requestFullscreen) {
                mapContainer.requestFullscreen();
            } else if (mapContainer.mozRequestFullScreen) {
                mapContainer.mozRequestFullScreen();
            } else if (mapContainer.webkitRequestFullscreen) {
                mapContainer.webkitRequestFullscreen();
            } else if (mapContainer.msRequestFullscreen) {
                mapContainer.msRequestFullscreen();
            }
            // Change icon to compress when in fullscreen
            document.querySelector('.fullscreen-button i').classList.replace('fa-expand', 'fa-compress');
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
            // Change icon back to expand when exiting fullscreen
            document.querySelector('.fullscreen-button i').classList.replace('fa-compress', 'fa-expand');
        }
    }

    // Initialize add/edit modal map when modal opens
    $('#addPlantationModal').on('shown.bs.modal', function() {
        if (!map) {
            map = L.map('map').setView([7.1907, 122.0794], 13);

            // Define base maps
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

            // Add default layer
            baseMaps["Default"].addTo(map);

            // Add layer control
            L.control.layers(baseMaps, null, {
                position: 'topright'
            }).addTo(map);

            // Add location control
            new L.Control.Location({
                position: 'topleft'
            }).addTo(map);

            // Add fullscreen control
            map.addControl(new fullScreenControl({
                position: 'topright'
            }));

            // Initialize search functionality
            const searchInput = document.getElementById('searchLocation');
            const searchBtn = document.getElementById('searchBtn');

            const performSearch = () => {
                const searchText = searchInput.value;
                if (searchText) {
                    // Show loading state
                    searchBtn.classList.add('loading');
                    document.querySelector('.map-status').classList.add('show');

                    fetch(
                            `../../../handlers/geocode.php?action=search&q=${encodeURIComponent(searchText)}`
                        )
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                const location = data[0];
                                const latlng = {
                                    lat: parseFloat(location.lat),
                                    lng: parseFloat(location.lon)
                                };
                                setMarker(latlng);
                                map.setView(latlng, 16);
                                map.getContainer().classList.add('loading-pulse');
                                setTimeout(() => {
                                    map.getContainer().classList.remove('loading-pulse');
                                }, 1000);
                            } else {
                                showNotification('error', 'Location not found');
                            }
                        })
                        .catch(error => {
                            showNotification('error', 'Error searching location');
                        })
                        .finally(() => {
                            // Hide loading state
                            searchBtn.classList.remove('loading');
                            document.querySelector('.map-status').classList.remove('show');
                        });
                }
            };

            searchBtn.addEventListener('click', performSearch);
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch();
                }
            });

            // Click: lot pin by default; in "Add Mohon" mode each click adds a boundary pin
            map.on('click', function(e) {
                if (setMohonMode) {
                    addMohonAt(e.latlng);
                } else {
                    setMarker(e.latlng);
                }
            });

            document.getElementById('toggleMohonPlaceBtn').addEventListener('click', function() {
                setMohonMode = true;
                this.classList.add('active');
                var done = document.getElementById('doneMohonBtn');
                if (done) done.style.display = '';
                var ms = document.getElementById('mohonSummary');
                if (ms) ms.innerHTML = '<span class="text-success">Click the map for each Mohon. Press <strong>Done</strong> when finished.</span>';
            });
            var doneMohonBtn = document.getElementById('doneMohonBtn');
            if (doneMohonBtn) {
                doneMohonBtn.addEventListener('click', function() {
                    setMohonMode = false;
                    var t = document.getElementById('toggleMohonPlaceBtn');
                    if (t) t.classList.remove('active');
                    doneMohonBtn.style.display = 'none';
                    updateMohonSummary();
                });
            }
            document.getElementById('clearMohonBtn').addEventListener('click', function() {
                clearAllMohon();
            });
            document.getElementById('fitMapBoundsBtn').addEventListener('click', function() {
                fitMapToPlantationArea();
            });
        }
        map.invalidateSize();
        // Defer so modal layout/tiles settle; then show lot + Mohon for Edit Plantation
        setTimeout(function() {
            if (map) {
                map.invalidateSize();
                flushPendingPlantationMapEdit();
            }
        }, 100);
    });

    function getCurrentLocation() {
        if (!navigator.geolocation) {
            showNotification('error', 'Geolocation is not supported by your browser');
            return;
        }

        // Show loading states
        if (locatingSpinner) locatingSpinner.style.display = 'block';
        document.querySelector('.map-status').classList.add('show');
        document.querySelector('.map-status span').textContent = 'Getting your location...';

        navigator.geolocation.getCurrentPosition(
            function(position) {
                const latlng = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };

                setMarker(latlng);
                map.setView(latlng, 16);
                map.getContainer().classList.add('loading-pulse');
                setTimeout(() => {
                    map.getContainer().classList.remove('loading-pulse');
                }, 1000);

                // Hide loading states
                if (locatingSpinner) locatingSpinner.style.display = 'none';
                document.querySelector('.map-status').classList.remove('show');
            },
            function(error) {
                // Hide loading states
                if (locatingSpinner) locatingSpinner.style.display = 'none';
                document.querySelector('.map-status').classList.remove('show');

                let errorMessage = "An unknown error occurred getting your location.";
                switch (error.code) {
                    case error.PERMISSION_DENIED:
                        errorMessage = "Location permission denied. Please enable location access.";
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMessage = "Location information unavailable.";
                        break;
                    case error.TIMEOUT:
                        errorMessage = "Location request timed out.";
                        break;
                }
                showNotification('error', errorMessage);
            }, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    }

    function updateLotCoordinatesDisplay(lat, lng) {
        var el = document.getElementById('lotCoordinatesDisplay');
        var text = document.getElementById('coordinatesText');
        if (!el || !text) return;
        if (lat != null && lng != null && lat !== '' && lng !== '') {
            text.textContent = ' Lat: ' + parseFloat(lat).toFixed(6) + ', Lng: ' + parseFloat(lng).toFixed(6);
            text.classList.remove('text-muted');
        } else {
            text.textContent = ' Click on map to pin — coordinates update in real time';
            text.classList.add('text-muted');
        }
    }

    // Lot location marker only (blue). Mohon is separate (red marker).
    // opts.skipGeocode: when loading Edit, keep saved address instead of overwriting via reverse geocode.
    function setMarker(latlng, opts) {
        opts = opts || {};
        if (!map) return;
        if (marker) {
            map.removeLayer(marker);
        }
        marker = L.marker(latlng, {
            draggable: true
        }).addTo(map);

        document.getElementById('latitude').value = latlng.lat;
        document.getElementById('longitude').value = latlng.lng;
        updateLotCoordinatesDisplay(latlng.lat, latlng.lng);

        if (!opts.skipGeocode) {
            fetch(`../../../handlers/geocode.php?action=reverse&lat=${latlng.lat}&lon=${latlng.lng}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('location_address').value = data.display_name || '';
                }).catch(() => {});
        }

        marker.on('drag', function(e) {
            const pos = e.target.getLatLng();
            document.getElementById('latitude').value = pos.lat;
            document.getElementById('longitude').value = pos.lng;
            updateLotCoordinatesDisplay(pos.lat, pos.lng);
        });
        marker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            document.getElementById('latitude').value = pos.lat;
            document.getElementById('longitude').value = pos.lng;
            updateLotCoordinatesDisplay(pos.lat, pos.lng);
            fetch(`../../../handlers/geocode.php?action=reverse&lat=${pos.lat}&lon=${pos.lng}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('location_address').value = data.display_name || '';
                }).catch(() => {});
        });
        fitMapToPlantationArea();
    }

    function flushPendingPlantationMapEdit() {
        if (!map || !pendingPlantationMapEdit) return;
        var p = pendingPlantationMapEdit;
        pendingPlantationMapEdit = null;
        var hasLot = p.lat != null && p.lng != null && String(p.lat) !== '' && String(p.lng) !== '';
        if (hasLot) {
            setMarker(L.latLng(parseFloat(p.lat), parseFloat(p.lng)), { skipGeocode: true });
        }
        if (p.mohonPts && p.mohonPts.length) {
            loadMohonPoints(p.mohonPts);
        } else {
            clearAllMohon();
        }
        fitMapToPlantationArea();
    }

    var mohonMarkers = [];
    var boundaryLayer = null;
    var setMohonMode = false;

    function mohonNumberIcon(index) {
        return L.divIcon({
            className: 'mohon-marker-icon',
            html: '<div style="background:#c0392b;width:26px;height:26px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 5px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:#fff;font-size:11px;font-weight:700;">' + index + '</div>',
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });
    }

    function getMohonPoints() {
        return mohonMarkers.map(function(m) {
            var ll = m.getLatLng();
            return { lat: ll.lat, lng: ll.lng };
        });
    }

    function syncMohonHiddenFields() {
        var pts = getMohonPoints();
        document.getElementById('mohon_points_json').value = JSON.stringify(pts);
        if (pts.length > 0) {
            document.getElementById('landmark_latitude').value = pts[0].lat;
            document.getElementById('landmark_longitude').value = pts[0].lng;
        } else {
            document.getElementById('landmark_latitude').value = '';
            document.getElementById('landmark_longitude').value = '';
        }
        updateMohonSummary();
    }

    function updateBoundaryAreaDisplay(pts) {
        var areaEl = document.getElementById('boundaryAreaDisplay');
        if (!areaEl) return;
        if (!pts || pts.length < 3) {
            areaEl.innerHTML = pts && pts.length === 2
                ? '<span class="text-muted">Add one more Mohon to calculate total boundary area.</span>'
                : '';
            return;
        }
        var areaText = typeof denrFormatBoundaryArea === 'function'
            ? denrFormatBoundaryArea(pts)
            : '';
        areaEl.innerHTML = areaText
            ? '<strong><i class="fas fa-ruler-combined me-1"></i>Total area:</strong> ' + areaText
            : '';
    }

    function updateMohonSummary() {
        var el = document.getElementById('mohonSummary');
        if (!el) return;
        var pts = getMohonPoints();
        if (pts.length === 0) {
            el.textContent = 'No Mohon placed yet.';
            updateBoundaryAreaDisplay(pts);
            return;
        }
        el.innerHTML = '<strong>' + pts.length + ' Mohon:</strong> ' + pts.map(function(p, i) {
            return '#' + (i + 1) + ' (' + p.lat.toFixed(5) + ', ' + p.lng.toFixed(5) + ')';
        }).join(' · ');
        updateBoundaryAreaDisplay(pts);
    }

    function redrawBoundaryTrack() {
        if (!map) return;
        if (boundaryLayer) {
            map.removeLayer(boundaryLayer);
            boundaryLayer = null;
        }
        var pts = getMohonPoints().map(function(p) { return [p.lat, p.lng]; });
        if (pts.length >= 3) {
            boundaryLayer = L.polygon(pts, {
                color: '#c0392b',
                weight: 2,
                fillColor: '#e74c3c',
                fillOpacity: 0.12
            }).addTo(map);
        } else if (pts.length === 2) {
            boundaryLayer = L.polyline(pts, { color: '#c0392b', weight: 3, dashArray: '10,8' }).addTo(map);
        } else if (pts.length === 1) {
            boundaryLayer = null;
        }
    }

    function fitMapToPlantationArea() {
        if (!map) return;
        var bounds = L.latLngBounds([]);
        var latEl = document.getElementById('latitude');
        var lngEl = document.getElementById('longitude');
        if (latEl && lngEl && latEl.value && lngEl.value) {
            bounds.extend([parseFloat(latEl.value), parseFloat(lngEl.value)]);
        }
        getMohonPoints().forEach(function(p) {
            bounds.extend([p.lat, p.lng]);
        });
        if (bounds.isValid()) {
            map.fitBounds(bounds, { padding: [40, 40], maxZoom: 17 });
        }
    }

    function addMohonAt(latlng) {
        if (!map) return;
        var idx = mohonMarkers.length + 1;
        var m = L.marker(latlng, { icon: mohonNumberIcon(idx), draggable: true }).addTo(map);
        m.on('drag', function() {
            syncMohonHiddenFields();
            redrawBoundaryTrack();
        });
        m.on('dragend', function() {
            fitMapToPlantationArea();
        });
        mohonMarkers.push(m);
        renumberMohonIcons();
        syncMohonHiddenFields();
        redrawBoundaryTrack();
        fitMapToPlantationArea();
    }

    function renumberMohonIcons() {
        mohonMarkers.forEach(function(m, i) {
            m.setIcon(mohonNumberIcon(i + 1));
        });
    }

    function clearAllMohon() {
        mohonMarkers.forEach(function(m) {
            if (map) map.removeLayer(m);
        });
        mohonMarkers = [];
        if (boundaryLayer && map) {
            map.removeLayer(boundaryLayer);
            boundaryLayer = null;
        }
        syncMohonHiddenFields();
    }

    function loadMohonPoints(pts) {
        clearAllMohon();
        if (!pts || !pts.length || !map) return;
        pts.forEach(function(p) {
            if (p && p.lat != null && p.lng != null) {
                addMohonAt(L.latLng(parseFloat(p.lat), parseFloat(p.lng)));
            }
        });
    }

    var viewBoundaryLayer = null;
    var viewMohonMarkers = [];

    function clearViewMapOverlays() {
        if (viewMarker && viewMap) {
            viewMap.removeLayer(viewMarker);
            viewMarker = null;
        }
        viewMohonMarkers.forEach(function(m) {
            if (viewMap) viewMap.removeLayer(m);
        });
        viewMohonMarkers = [];
        if (viewBoundaryLayer && viewMap) {
            viewMap.removeLayer(viewBoundaryLayer);
            viewBoundaryLayer = null;
        }
    }

    function viewPlantation(id) {
        const card = document.querySelector(`.plantation-card[data-plantation-id="${id}"]`);
        if (!card) return;

        var pid = parseInt(id, 10);
        var st = card.getAttribute('data-status') || '';
        var certWrap = document.getElementById('viewCertLinkWrap');
        var certA = document.getElementById('viewCertLink');
        if (certWrap && certA) {
            if (st === 'registered') {
                certWrap.style.display = 'block';
                certA.href = 'certificate.php?id=' + encodeURIComponent(String(pid));
            } else {
                certWrap.style.display = 'none';
            }
        }
        var tlw = document.getElementById('viewPlantationTimeline');
        var tll = document.getElementById('viewPlantationTimelineList');
        var tld = document.getElementById('viewPlantationDates');
        if (tll) tll.innerHTML = '';
        if (tld) tld.textContent = '';
        if (tlw) tlw.style.display = 'none';
        fetch('../../../handlers/get_plantation_timeline.php?plantation_id=' + encodeURIComponent(String(pid)))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || !data.ok || !tlw || !tll) return;
                tlw.style.display = 'block';
                var pl = data.plantation || {};
                var parts = [];
                if (pl.applied_at) parts.push('<strong>Date applied:</strong> ' + pl.applied_at);
                if (pl.approved_at) parts.push('<strong>Date approved:</strong> ' + pl.approved_at);
                else if (st === 'registered' && pl.registered_at) parts.push('<strong>Registered:</strong> ' + pl.registered_at);
                if (tld) tld.innerHTML = parts.join('<br>');
                (data.logs || []).forEach(function(row) {
                    var li = document.createElement('li');
                    li.className = 'mb-1 border-bottom pb-1';
                    var when = row.created_at || '';
                    var actor = row.actor_name ? (' — ' + row.actor_name) : '';
                    var det = row.detail ? (': ' + String(row.detail)) : '';
                    li.innerHTML = '<span class="text-muted">' + when + '</span> · <strong>' + (row.action || '') + '</strong>' + actor +
                        (row.old_status || row.new_status ? ' <em>(' + (row.old_status || '') + (row.old_status && row.new_status ? ' → ' : '') + (row.new_status || '') + ')</em>' : '') + det;
                    tll.appendChild(li);
                });
            }).catch(function() {});

        const name = card.getAttribute('data-name');
        const address = card.getAttribute('data-location_address');
        const lat = card.getAttribute('data-latitude');
        const lng = card.getAttribute('data-longitude');
        let mohonPts = [];
        try {
            const raw = card.getAttribute('data-mohon-points');
            if (raw) mohonPts = JSON.parse(raw);
        } catch (e) { mohonPts = []; }
        if (!mohonPts || !mohonPts.length) {
            const mLat = card.getAttribute('data-landmark_latitude');
            const mLng = card.getAttribute('data-landmark_longitude');
            if (mLat && mLng) mohonPts = [{ lat: parseFloat(mLat), lng: parseFloat(mLng) }];
        }

        document.getElementById('viewPlantationTitle').textContent = name || 'Plantation Location';
        document.getElementById('viewPlantationAddress').textContent = address || '';
        document.getElementById('viewPlantationCoordinates').textContent = (lat && lng) ? ('Lot location: Lat ' + parseFloat(lat).toFixed(6) + ', Lng ' + parseFloat(lng).toFixed(6)) : '';
        var viewMohonEl = document.getElementById('viewPlantationMohon');
        if (viewMohonEl) {
            if (mohonPts && mohonPts.length) {
                viewMohonEl.innerHTML = '<strong>Mohon (' + mohonPts.length + '):</strong> ' + mohonPts.map(function(p, i) {
                    return '#' + (i + 1) + ' Lat ' + parseFloat(p.lat).toFixed(6) + ', Lng ' + parseFloat(p.lng).toFixed(6);
                }).join(' · ');
                viewMohonEl.style.display = 'block';
            } else {
                viewMohonEl.textContent = '';
                viewMohonEl.style.display = 'none';
            }
        }
        var viewAreaEl = document.getElementById('viewPlantationArea');
        if (viewAreaEl) {
            if (mohonPts && mohonPts.length >= 3 && typeof denrFormatBoundaryArea === 'function') {
                viewAreaEl.innerHTML = '<strong>Total boundary area:</strong> ' + denrFormatBoundaryArea(mohonPts);
                viewAreaEl.style.display = 'block';
            } else {
                viewAreaEl.textContent = '';
                viewAreaEl.style.display = 'none';
            }
        }

        const viewModalEl = document.getElementById('viewPlantationModal');
        const viewModal = new bootstrap.Modal(viewModalEl);

        function onViewShown() {
            const vlat = lat || 7.1907;
            const vlng = lng || 122.0794;
            if (!viewMap) {
                viewMap = L.map('viewMap').setView([vlat, vlng], 15);
                const viewBaseMaps = {
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
                viewBaseMaps["Default"].addTo(viewMap);
                L.control.layers(viewBaseMaps, null, { position: 'topright' }).addTo(viewMap);
            }

            clearViewMapOverlays();

            if (lat && lng) {
                viewMarker = L.marker([parseFloat(lat), parseFloat(lng)]).addTo(viewMap)
                    .bindPopup(`<b>Lot</b><br>${name || 'Plantation'}<br>${address || ''}`).openPopup();
            }

            var latlngs = [];
            if (mohonPts && mohonPts.length) {
                mohonPts.forEach(function(p, i) {
                    var ll = [parseFloat(p.lat), parseFloat(p.lng)];
                    latlngs.push(ll);
                    var ic = L.divIcon({
                        className: 'mohon-marker-icon',
                        html: '<div style="background:#c0392b;width:22px;height:22px;border-radius:50%;border:2px solid #fff;display:flex;align-items:center;justify-content:center;color:#fff;font-size:10px;font-weight:700;">' + (i + 1) + '</div>',
                        iconSize: [22, 22],
                        iconAnchor: [11, 11]
                    });
                    var mm = L.marker(ll, { icon: ic }).addTo(viewMap).bindPopup('<b>Mohon #' + (i + 1) + '</b>');
                    viewMohonMarkers.push(mm);
                });
                if (latlngs.length >= 3) {
                    viewBoundaryLayer = L.polygon(latlngs, { color: '#c0392b', weight: 2, fillColor: '#e74c3c', fillOpacity: 0.12 }).addTo(viewMap);
                } else if (latlngs.length === 2) {
                    viewBoundaryLayer = L.polyline(latlngs, { color: '#c0392b', weight: 3, dashArray: '10,8' }).addTo(viewMap);
                }
            }

            var b = L.latLngBounds([]);
            if (lat && lng) b.extend([parseFloat(lat), parseFloat(lng)]);
            mohonPts.forEach(function(p) { b.extend([parseFloat(p.lat), parseFloat(p.lng)]); });
            if (b.isValid()) {
                viewMap.fitBounds(b, { padding: [36, 36], maxZoom: 17 });
            } else {
                viewMap.setView([vlat, vlng], 15);
            }
            viewMap.invalidateSize();
        }

        viewModalEl.removeEventListener('shown.bs.modal', viewPlantation._onViewShownRef);
        viewPlantation._onViewShownRef = onViewShown;
        viewModalEl.addEventListener('shown.bs.modal', onViewShown);

        viewModal.show();
    }

    // Edit plantation: populate addPlantationForm and open modal for editing
    function editPlantation(id) {
        const card = document.querySelector(`.plantation-card[data-plantation-id="${id}"]`);
        if (!card) return;
        document.getElementById('addPlantationModalLabel').textContent = 'Edit Plantation';

        document.getElementById('plantation_id').value = id;
        document.querySelector('input[name="plantation_name"]').value = card.getAttribute('data-name') || '';
        
        // Parse tree species (format "Name:Qty,Name:Qty" or legacy "Name, Name")
        const treeSpeciesData = card.getAttribute('data-tree_species') || '';
        const pairs = [];
        treeSpeciesData.split(',').forEach(function(s) {
            s = s.trim();
            if (!s) return;
            if (s.indexOf(':') >= 0) {
                const idx = s.indexOf(':');
                pairs.push({ name: s.substring(0, idx).trim(), qty: s.substring(idx + 1).trim() || '1' });
            } else {
                pairs.push({ name: s, qty: '1' });
            }
        });
        const container = document.getElementById('tree_species_rows');
        const templateRow = container.querySelector('.tree-species-row');
        if (!templateRow) return;
        container.innerHTML = '';
        if (pairs.length === 0) {
            const emptyRow = templateRow.cloneNode(true);
            emptyRow.querySelector('.tree-species-select').value = '';
            emptyRow.querySelector('.tree-species-qty').value = '1';
            container.appendChild(emptyRow);
        } else {
            pairs.forEach(function(p) {
                const row = templateRow.cloneNode(true);
                row.querySelector('.tree-species-select').value = p.name;
                row.querySelector('.tree-species-qty').value = p.qty;
                container.appendChild(row);
            });
        }
        updateRemoveSpeciesButtons();
        
        document.querySelector('input[name="land_area"]').value = card.getAttribute('data-land_area') || '';
        var ageEl = document.getElementById('age_of_plantation');
        if (ageEl) ageEl.value = card.getAttribute('data-age_of_plantation') || '';
        document.getElementById('location_address').value = card.getAttribute('data-location_address') || '';
        var cname = document.getElementById('contact_person_name');
        var caddr = document.getElementById('contact_address');
        var cphone = document.getElementById('contact_phone');
        if (cname) cname.value = card.getAttribute('data-contact_person_name') || '';
        if (caddr) caddr.value = card.getAttribute('data-contact_address') || '';
        if (cphone) cphone.value = card.getAttribute('data-contact_phone') || '';
        document.getElementById('latitude').value = card.getAttribute('data-latitude') || '';
        document.getElementById('longitude').value = card.getAttribute('data-longitude') || '';
        document.getElementById('landmark_latitude').value = card.getAttribute('data-landmark_latitude') || '';
        document.getElementById('landmark_longitude').value = card.getAttribute('data-landmark_longitude') || '';
        var lat = card.getAttribute('data-latitude');
        var lng = card.getAttribute('data-longitude');
        if (lat && lng) updateLotCoordinatesDisplay(lat, lng);
        else updateLotCoordinatesDisplay(null, null);

        var rawMohon = card.getAttribute('data-mohon-points');
        var mohonPts = [];
        try {
            if (rawMohon) mohonPts = JSON.parse(rawMohon);
        } catch (err) { mohonPts = []; }
        if (!mohonPts.length) {
            var mLat = card.getAttribute('data-landmark_latitude');
            var mLng = card.getAttribute('data-landmark_longitude');
            if (mLat && mLng) mohonPts = [{ lat: parseFloat(mLat), lng: parseFloat(mLng) }];
        }
        pendingPlantationMapEdit = { lat: lat, lng: lng, mohonPts: mohonPts };

        var distEl = document.getElementById('district');
        if (distEl) distEl.value = card.getAttribute('data-district') || '';

        buildTreeSpeciesFromRows();

        var submitBtn = document.getElementById('submitPlantation');
        if (submitBtn) submitBtn.innerHTML = 'Update Plantation';

        // show modal using the single shared instance (avoids duplicate backdrop)
        addPlantationModal.show();
    }

    // Single modal instance so backdrop closes correctly when clicking outside
    const addPlantationModalEl = document.getElementById('addPlantationModal');
    const addPlantationModal = new bootstrap.Modal(addPlantationModalEl, {
        backdrop: true,  // Allow click outside to close; ensures backdrop is removed with modal
        keyboard: true
    });

    // Open add modal from "Add New Plantation" button via same instance (avoids duplicate backdrop)
    var openAddBtn = document.getElementById('openAddPlantationModal');
    if (openAddBtn) {
        openAddBtn.addEventListener('click', function() {
            document.getElementById('addPlantationModalLabel').textContent = 'Register New Plantation';
            pendingPlantationMapEdit = null;
            var sb = document.getElementById('submitPlantation');
            if (sb) sb.innerHTML = 'Register Plantation';
            setTimeout(function() {
                var n = document.getElementById('contact_person_name');
                var p = document.getElementById('contact_phone');
                if (n && DENR_PROFILE_DEFAULTS.full_name && !String(n.value).trim()) {
                    n.value = DENR_PROFILE_DEFAULTS.full_name;
                }
                if (p && DENR_PROFILE_DEFAULTS.contact_number) {
                    var d = denrDigitsOnly(DENR_PROFILE_DEFAULTS.contact_number);
                    if (d && !String(p.value).trim()) p.value = d;
                }
            }, 0);
            addPlantationModal.show();
        });
    }

    // Handle modal focus management
    addPlantationModalEl.addEventListener('shown.bs.modal', function() {
        document.querySelector('#addPlantationModal input[name="plantation_name"]') && document.querySelector('#addPlantationModal input[name="plantation_name"]').focus();
    });

    addPlantationModalEl.addEventListener('hidden.bs.modal', function() {
        // Reset form when modal closes
        pendingPlantationMapEdit = null;
        var sb = document.getElementById('submitPlantation');
        if (sb) sb.innerHTML = 'Register Plantation';
        document.getElementById('addPlantationForm').reset();
        resetSpeciesRowsToOne();
        document.getElementById('tree_species_hidden').value = '';
        const inputs = this.getElementsByTagName('input');
        for (let input of inputs) {
            input.classList.remove('is-invalid');
        }
        setMohonMode = false;
        var toggleMohon = document.getElementById('toggleMohonPlaceBtn');
        if (toggleMohon) toggleMohon.classList.remove('active');
        var doneMohon = document.getElementById('doneMohonBtn');
        if (doneMohon) doneMohon.style.display = 'none';
        clearAllMohon();
        if (marker && map) { map.removeLayer(marker); marker = null; }
        updateLotCoordinatesDisplay(null, null);
        // Force-remove any stuck modal backdrop and body state (fixes dark overlay left behind)
        clearModalBackdrop();
    });

    // Run when any modal closes so dark overlay never sticks (add or view plantation modal)
    function clearModalBackdrop() {
        document.querySelectorAll('.modal-backdrop').forEach(function(el) { el.remove(); });
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }
    document.addEventListener('hidden.bs.modal', clearModalBackdrop);

    function updatePlantation(formData) {
        const submitBtn = document.getElementById('submitPlantation');
        const loadingOverlay = document.querySelector('.loading-overlay');
        let progress = 0;

        // Show loading overlay
        loadingOverlay.style.display = 'flex';

        // Simulate progress
        const progressInterval = setInterval(() => {
            progress += Math.random() * 30;
            if (progress > 90) clearInterval(progressInterval);
            document.querySelector('.loading-percentage').textContent =
                Math.min(Math.round(progress), 90) + '%';
        }, 500);

        if (submitBtn) {
            submitBtn.disabled = true;
        }

        fetch('../../../handlers/update_plantation.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                clearInterval(progressInterval);
                document.querySelector('.loading-percentage').textContent = '100%';

                setTimeout(() => {
                    loadingOverlay.style.display = 'none';

                    if (data.status === 'success') {
                        showNotification('success', 'Plantation updated successfully');
                        addPlantationModal.hide();
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        throw new Error(data.message || 'Failed to update plantation');
                    }
                }, 500);
            })
            .catch(error => {
                clearInterval(progressInterval);
                loadingOverlay.style.display = 'none';
                showNotification('error', error.message || 'Error updating plantation');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = 'Update Plantation';
                }
            });
    }

    // Update form submission handler
    document.getElementById('addPlantationForm').addEventListener('submit', function(e) {
        e.preventDefault();

        if (!document.getElementById('latitude').value || !document.getElementById('longitude').value) {
            showNotification('error', 'Please select a location on the map');
            return;
        }

        if (!buildTreeSpeciesFromRows()) {
            showNotification('error', 'Please add at least one tree species with quantity.');
            return;
        }

        var cn = document.getElementById('contact_person_name');
        var ca = document.getElementById('contact_address');
        var cp = document.getElementById('contact_phone');
        if (!cn || !ca || !cp || !String(cn.value).trim() || !String(ca.value).trim() || !String(cp.value).trim()) {
            showNotification('error', 'Please enter name, address, and contact.');
            return;
        }
        var cDigits = denrDigitsOnly(cp.value);
        if (cDigits.length < 7 || cDigits.length > 15) {
            showNotification('error', 'Contact number must be 7–15 digits only (no letters).');
            return;
        }
        cp.value = cDigits;

        var mohonRaw = document.getElementById('mohon_points_json').value;
        var mohonLen = 0;
        try {
            var mj = JSON.parse(mohonRaw || '[]');
            if (Array.isArray(mj)) mohonLen = mj.length;
        } catch (e2) { mohonLen = 0; }
        var isNew = !document.getElementById('plantation_id').value;
        if (isNew && mohonLen < 2) {
            showNotification('error', 'Add at least two Mohon points on the map for the plantation boundary.');
            return;
        }

        const formData = new FormData(this);
        const plantationId = document.getElementById('plantation_id').value;

        if (plantationId) {
            // Update existing plantation
            updatePlantation(formData);
        } else {
            // Add new plantation
            const submitBtn = document.getElementById('submitPlantation');
            const loadingOverlay = document.querySelector('.loading-overlay');
            let progress = 0;

            // Show loading overlay
            loadingOverlay.style.display = 'flex';

            // Simulate progress
            const progressInterval = setInterval(() => {
                progress += Math.random() * 30;
                if (progress > 90) clearInterval(progressInterval);
                document.querySelector('.loading-percentage').textContent =
                    Math.min(Math.round(progress), 90) + '%';
            }, 500);

            if (submitBtn) {
                submitBtn.disabled = true;
            }

            fetch('../../../handlers/add_plantation.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    clearInterval(progressInterval);
                    document.querySelector('.loading-percentage').textContent = '100%';

                    setTimeout(() => {
                        loadingOverlay.style.display = 'none';

                        if (data.status === 'success') {
                            showNotification('success', 'Plantation added successfully');
                            addPlantationModal.hide();
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            throw new Error(data.message || 'Failed to add plantation');
                        }
                    }, 500);
                })
                .catch(error => {
                    clearInterval(progressInterval);
                    loadingOverlay.style.display = 'none';
                    showNotification('error', error.message || 'Error adding plantation');
                })
                .finally(() => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Register Plantation';
                    }
                });
        }
    });

    // Add delete plantation function
    function deletePlantation(id) {
        if (confirm('Are you sure you want to delete this plantation? This action cannot be undone.')) {
            const loadingOverlay = document.querySelector('.loading-overlay');
            loadingOverlay.style.display = 'flex';

            fetch('../../../handlers/delete_plantation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        plantation_id: id
                    })
                })
                .then(response => response.json())
                .then((data) => {
                    loadingOverlay.style.display = 'none';
                    if (data.status === 'success') {
                        showNotification('success', 'Plantation deleted successfully');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        throw new Error(data.message || 'Failed to delete plantation');
                    }
                })
                .catch(error => {
                    loadingOverlay.style.display = 'none';
                    showNotification('error', error.message || 'Error deleting plantation');
                });
        }
    }

    // Replace the existing click handlers with this updated code
    document.addEventListener('DOMContentLoaded', function() {
        // View button click handler
        document.querySelectorAll('.p-btn-view').forEach(btn => {
            btn.addEventListener('click', function() {
                const card = this.closest('.plantation-card');
                const id = this.getAttribute('data-id');
                viewPlantation(id);
            });
        });

        // Edit button click handler
        document.querySelectorAll('.p-btn-edit').forEach(btn => {
            btn.addEventListener('click', function() {
                const card = this.closest('.plantation-card');
                const id = this.getAttribute('data-id');
                editPlantation(id);
            });
        });

        // Delete button click handler
        document.querySelectorAll('.p-btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                deletePlantation(id);
            });
        });
    });
    </script>
</body>

</html>