<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit();
}

require_once '../../../config/database.php';
$database = new Database();
$db = $database->getConnection();

$chainsawList = [];
$hasTable = $db->query("SHOW TABLES LIKE 'chainsaw_registry'")->rowCount() > 0;
if ($hasTable) {
    $q = "SELECT c.*, u.full_name FROM chainsaw_registry c LEFT JOIN users u ON c.user_id = u.user_id ORDER BY c.registered_at DESC";
    $chainsawList = $db->query($q)->fetchAll(PDO::FETCH_ASSOC);
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_chainsaw']) && $hasTable) {
    $user_id = (int) ($_POST['user_id'] ?? 0);
    $registry_number = trim($_POST['registry_number'] ?? '');
    $brand_model = trim($_POST['brand_model'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    if ($user_id && $registry_number) {
        $ins = $db->prepare("INSERT INTO chainsaw_registry (user_id, registry_number, brand_model, serial_number) VALUES (?, ?, ?, ?)");
        $ins->execute([$user_id, $registry_number, $brand_model ?: null, $serial_number ?: null]);
        header('Location: chainsaw.php?added=1');
        exit;
    }
}

$users = $db->query("SELECT user_id, full_name, email FROM users WHERE role = 'landowner' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chainsaw Registry - DENR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/admin_plantations.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png">
</head>
<body>
    <div class="dashboard-container">
        <?php include '../../../admin_includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include '../../../admin_includes/header.php'; ?>
            <div class="container mt-4">
                <div class="admin-dashboard-header">
                    <h2><i class="fas fa-tools"></i> Chainsaw Registry</h2>
                    <p>Registration for chainsaw and registry number of tree</p>
                </div>
                <?php if (isset($_GET['added'])): ?>
                <div class="alert alert-success">Chainsaw registered successfully.</div>
                <?php endif; ?>
                <?php if (!$hasTable): ?>
                <div class="alert alert-info">Run the System Addition migration first to create the chainsaw registry table.</div>
                <?php else: ?>
                <div class="mb-3">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addChainsawModal"><i class="fas fa-plus"></i> Register Chainsaw</button>
                </div>
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($chainsawList)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fas fa-tools"></i></div>
                            <h4>No chainsaws registered</h4>
                            <p>Register a chainsaw using the button above.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table plantations-table">
                                <thead>
                                    <tr>
                                        <th>Registry number</th>
                                        <th>Owner</th>
                                        <th>Brand / model</th>
                                        <th>Serial number</th>
                                        <th>Registered at</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($chainsawList as $c): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($c['registry_number']); ?></td>
                                        <td><?php echo htmlspecialchars($c['full_name'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($c['brand_model'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($c['serial_number'] ?? '-'); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($c['registered_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <div class="modal fade" id="addChainsawModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="add_chainsaw" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title">Register Chainsaw</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label">Owner</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Select landowner...</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['full_name'] . ' (' . $u['email'] . ')'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Registry number</label>
                            <input type="text" name="registry_number" class="form-control" required placeholder="Registry number">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Brand / model</label>
                            <input type="text" name="brand_model" class="form-control" placeholder="Optional">
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Serial number</label>
                            <input type="text" name="serial_number" class="form-control" placeholder="Optional">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Register</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
