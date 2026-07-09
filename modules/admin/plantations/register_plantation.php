<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
$db = (new Database())->getConnection();
$landowners = $db->query("SELECT user_id, full_name, email FROM users WHERE role = 'landowner' ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$species = $db->query('SELECT species_id, species_name, scientific_name FROM tree_species ORDER BY species_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register plantation (Admin) — DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/plantations.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
    <style>
        #map { height: 420px; border-radius: 8px; z-index: 1; }
        .map-container { position: relative; border-radius: 8px; overflow: hidden; }
        .map-status { display: none; position: absolute; z-index: 1000; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.95); padding: 1rem 1.5rem; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
        .map-status.show { display: flex; align-items: center; gap: 0.5rem; }
        #searchBtn.loading .spinner-border { display: inline-block !important; }
        #searchBtn .spinner-border { display: none; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>
<div class="dashboard-container">
    <?php include __DIR__ . '/../../../admin_includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../../admin_includes/header.php'; ?>
        <div class="dashboard-content admin-dashboard">
            <header class="admin-dashboard-header">
                <div>
                    <h1 class="admin-dashboard-title">Register plantation (admin)</h1>
                    <p class="admin-dashboard-subtitle">Same map and species workflow as landowners. Pick a user from the list <strong>or</strong> type their full name or email. Contact phone accepts digits only.</p>
                </div>
                <a href="plantations.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
            </header>

            <script type="application/json" id="landowners-json"><?php echo json_encode($landowners, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>

            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($landowners)): ?>
                    <p class="text-warning mb-3"><i class="fas fa-exclamation-triangle"></i> No landowner accounts yet. Create landowner users first, or type an email that will match once accounts exist.</p>
                    <?php endif; ?>

                    <form id="adminPlantForm" class="row g-3" enctype="multipart/form-data">
                        <div class="col-12">
                            <label class="form-label">Landowner <span class="text-danger">*</span></label>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-6">
                                    <select name="landowner_user_id" id="landowner_user_id" class="form-select" aria-label="Select landowner">
                                        <option value="">— Select from list —</option>
                                        <?php foreach ($landowners as $lo): ?>
                                        <option value="<?php echo (int) $lo['user_id']; ?>"><?php echo htmlspecialchars($lo['full_name'] . ' · ' . $lo['email']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="landowner_lookup" id="landowner_lookup" list="landowners-datalist"
                                        placeholder="Or type full name or email (matches on blur)" autocomplete="off" maxlength="200">
                                    <datalist id="landowners-datalist">
                                        <?php foreach ($landowners as $lo): ?>
                                        <option value="<?php echo htmlspecialchars($lo['email']); ?>"></option>
                                        <option value="<?php echo htmlspecialchars($lo['full_name']); ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                            </div>
                            <small class="text-muted">If you use the text field, use the exact registered email or full name when possible. The dropdown is filled automatically when a unique match is found.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Plantation name <span class="text-danger">*</span></label>
                            <input type="text" name="plantation_name" class="form-control" required maxlength="200">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Land area (ha) <span class="text-danger">*</span></label>
                            <input type="number" name="land_area" class="form-control" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">District (optional)</label>
                            <input type="text" name="district" class="form-control" maxlength="120">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Tree species <span class="text-danger">*</span> <small class="text-muted">(add rows like landowner registration)</small></label>
                            <input type="hidden" name="tree_species" id="tree_species_hidden" value="">
                            <div id="tree_species_rows" class="tree-species-rows">
                                <div class="tree-species-row row g-2 align-items-center mb-2">
                                    <div class="col-md-7">
                                        <select class="form-select tree-species-select">
                                            <option value="">— Select species —</option>
                                            <?php foreach ($species as $sp): ?>
                                            <option value="<?php echo htmlspecialchars($sp['species_name']); ?>">
                                                <?php echo htmlspecialchars($sp['species_name']); ?>
                                                <?php if (!empty($sp['scientific_name'])): ?>(<?php echo htmlspecialchars($sp['scientific_name']); ?>)<?php endif; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="number" class="form-control tree-species-qty" min="1" placeholder="Qty" value="1">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-species-row" title="Remove" disabled><i class="fas fa-minus"></i></button>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1" id="add_species_row"><i class="fas fa-plus"></i> Add species</button>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Location address <span class="text-danger">*</span></label>
                            <input type="text" name="location_address" id="location_address" class="form-control" required maxlength="500">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Contact person <span class="text-danger">*</span></label>
                            <input type="text" name="contact_person_name" class="form-control" required maxlength="150">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact address <span class="text-danger">*</span></label>
                            <input type="text" name="contact_address" class="form-control" required maxlength="300">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Contact phone <span class="text-danger">*</span></label>
                            <input type="text" name="contact_phone" class="form-control" required maxlength="15" inputmode="numeric" pattern="[0-9]*" placeholder="Digits only" title="Numbers only">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Select location on map <span class="text-danger">*</span></label>
                            <div class="map-instructions mb-2 small">
                                <i class="fas fa-info-circle text-success"></i>
                                <span><strong>1)</strong> Search or click for the <strong>lot</strong> (blue pin).
                                <strong>2)</strong> Use <em>Add Mohon</em> for boundary corners — same as landowner flow.</span>
                            </div>
                            <div class="map-search-container mb-2">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="searchLocation" placeholder="Search location">
                                    <button class="btn btn-primary" type="button" id="searchBtn">
                                        <i class="fas fa-arrow-right"></i>
                                        <span class="spinner-border spinner-border-sm text-light" role="status" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="map-container border">
                                <div class="map-status"><i class="fas fa-spinner fa-spin"></i><span>Loading…</span></div>
                                <div id="map"></div>
                            </div>
                            <div class="lot-coordinates-display mt-2 p-2 rounded small" style="background:#f0f2f5;">
                                <strong>Lot (coordinates):</strong> <span id="coordinatesText" class="text-muted">Click map to set blue pin</span>
                            </div>
                        </div>

                        <input type="hidden" name="latitude" id="latitude" value="">
                        <input type="hidden" name="longitude" id="longitude" value="">
                        <input type="hidden" name="mohon_points_json" id="mohon_points_json" value="">
                        <input type="hidden" name="landmark_latitude" id="landmark_latitude" value="">
                        <input type="hidden" name="landmark_longitude" id="landmark_longitude" value="">

                        <div class="col-12 p-3 rounded small" style="background:#f0f2f5;">
                            <strong><i class="fas fa-draw-polygon me-1"></i> Mohon</strong>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-danger" id="toggleMohonPlaceBtn"><i class="fas fa-plus"></i> Add Mohon</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="doneMohonBtn" style="display:none;"><i class="fas fa-check"></i> Done</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clearMohonBtn"><i class="fas fa-eraser"></i> Clear Mohon</button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="fitMapBoundsBtn"><i class="fas fa-compress-arrows-alt"></i> Fit bounds</button>
                            </div>
                            <div id="mohonSummary" class="text-muted mt-2">No Mohon placed yet.</div>
                            <div id="boundaryAreaDisplay" class="small mt-1 mb-0" aria-live="polite"></div>
                        </div>

                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save plantation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
window.DENR_REG_PLANT = {
    geocodeSearch: "../../../handlers/geocode.php?action=search&q=",
    geocodeReverse: "../../../handlers/geocode.php?action=reverse&lat="
};
</script>
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<script src="../../../assets/js/polygon_area.js"></script>
<script src="../../../assets/js/admin_register_plantation.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
