<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit;
}

require_once __DIR__ . '/../../../config/database.php';
$db = (new Database())->getConnection();
$rows = $db->query('SELECT * FROM tree_species ORDER BY species_name ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tree species — DENR Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>
<body>
<div class="dashboard-container">
    <?php include __DIR__ . '/../../../admin_includes/sidebar.php'; ?>
    <main class="main-content">
        <?php include __DIR__ . '/../../../admin_includes/header.php'; ?>
        <div class="dashboard-content admin-dashboard">
            <header class="admin-dashboard-header">
                <div>
                    <h1 class="admin-dashboard-title">Tree species</h1>
                    <p class="admin-dashboard-subtitle">Options shown in landowner plantation registration.</p>
                </div>
            </header>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header">Add species</div>
                        <div class="card-body">
                            <form id="addSpeciesForm" class="vstack gap-2">
                                <input type="hidden" name="action" value="add">
                                <label class="form-label">Name <span class="text-danger">*</span></label>
                                <input type="text" name="species_name" class="form-control" required maxlength="100">
                                <label class="form-label">Scientific name</label>
                                <input type="text" name="scientific_name" class="form-control" maxlength="150">
                                <label class="form-label">Common name</label>
                                <input type="text" name="common_name" class="form-control" maxlength="100">
                                <button type="submit" class="btn btn-primary mt-2"><i class="fas fa-plus"></i> Add</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header">Current list (<?php echo count($rows); ?>)</div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>Name</th><th>Scientific</th><th>Common</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($r['species_name']); ?></td>
                                        <td><?php echo htmlspecialchars($r['scientific_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($r['common_name'] ?? ''); ?></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-del-species" data-id="<?php echo (int) $r['species_id']; ?>">Delete</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addSpeciesForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    fetch('../../../handlers/admin_tree_species.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.ok) location.reload();
            else showNotification('error', d.message || 'Failed');
        }).catch(function() { showNotification('error', 'Request failed'); });
});
document.querySelectorAll('.btn-del-species').forEach(function(btn) {
    btn.addEventListener('click', function() {
        if (!confirm('Delete this species?')) return;
        var fd = new FormData();
        fd.append('action', 'delete');
        fd.append('species_id', btn.getAttribute('data-id'));
        fetch('../../../handlers/admin_tree_species.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.ok) location.reload();
                else showNotification('error', d.message || 'Failed');
            });
    });
});
</script>
</body>
</html>
