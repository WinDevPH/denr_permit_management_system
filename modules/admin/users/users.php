<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit();
}

require_once '../../../config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Handle user actions (activate, deactivate, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = $_POST['user_id'] ?? 0;
    
    try {
        switch ($action) {
            case 'activate':
                $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $message = "User activated successfully";
                $message_type = "success";
                break;
                
            case 'deactivate':
                $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $message = "User deactivated successfully";
                $message_type = "success";
                break;
                
            case 'delete':
                $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $message = "User deleted successfully";
                $message_type = "success";
                break;
                
            case 'update_role':
                $new_role = $_POST['new_role'] ?? '';
                $stmt = $db->prepare("UPDATE users SET role = ? WHERE user_id = ?");
                $stmt->execute([$new_role, $user_id]);
                $message = "User role updated successfully";
                $message_type = "success";
                break;
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Pagination and search
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$district_filter = isset($_GET['district']) ? trim((string) $_GET['district']) : '';
/** When filtering by verifier district, only verifiers match. */
if ($district_filter !== '') {
    $role_filter = 'verifier';
}
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page_options = [5, 10, 25, 50];
$per_page_request = (int)($_GET['per_page'] ?? 5);
$limit = in_array($per_page_request, $per_page_options) ? $per_page_request : 5;
$offset = ($page - 1) * $limit;

// Build query with filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($district_filter !== '') {
    $where_conditions[] = "(role = 'verifier' AND LOWER(TRIM(COALESCE(district, ''))) = LOWER(?))";
    $params[] = $district_filter;
} elseif (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$district_filter_options = [];
try {
    $dds = $db->query(
        "SELECT DISTINCT TRIM(district) AS d FROM users
         WHERE role = 'verifier' AND district IS NOT NULL AND TRIM(district) != ''
         ORDER BY d ASC"
    );
    if ($dds) {
        $district_filter_options = array_values(array_unique(array_filter(array_map('trim', $dds->fetchAll(PDO::FETCH_COLUMN, 0) ?: []))));
    }
} catch (PDOException $e) {
    $district_filter_options = [];
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM users $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_users = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $limit);

// Get users with pagination
$users_query = "SELECT user_id, full_name, email, contact_number, role, status, created_at, profile_img, TRIM(COALESCE(district, '')) AS district 
                FROM users $where_clause 
                ORDER BY created_at DESC 
                LIMIT $limit OFFSET $offset";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute($params);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
try {
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN role = 'landowner' THEN 1 ELSE 0 END) as landowners,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                    SUM(CASE WHEN role = 'verifier' THEN 1 ELSE 0 END) as verifiers,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
                    FROM users";
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total' => 0, 'landowners' => 0, 'admins' => 0, 'verifiers' => 0, 'active' => 0, 'inactive' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/main.css">
    <link rel="stylesheet" href="../../../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../../../assets/css/admin_users.css">
    <link rel="icon" type="image/png" href="../../../assets/img/denrlogo.png" />
</head>

<body class="admin-users-page">
    <div class="dashboard-container">
        <!-- Sidebar -->
        <?php include '../../../admin_includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Navigation -->
            <?php include '../../../admin_includes/header.php'; ?>

            <!-- Users Management Content (same layout as Dashboard) -->
            <div class="dashboard-content admin-dashboard admin-users">
                <header class="admin-dashboard-header admin-users-header">
                    <div>
                        <h1 class="admin-dashboard-title">Manage Users</h1>
                        <p class="admin-dashboard-subtitle">Manage system users, roles, and permissions</p>
                    </div>
                    <button type="button" class="admin-users-add-btn" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg> Add New User
                    </button>
                </header>

                <?php if (isset($message)): ?>
                <div
                    class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- User Statistics -->
                <div class="admin-stats-row">
                    <div class="admin-stat-item">
                        <div class="stat-icon primary">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $stats['total']; ?></span>
                            <span class="stat-label">Total Users</span>
                        </div>
                    </div>
                    <div class="admin-stat-item">
                        <div class="stat-icon success">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $stats['landowners']; ?></span>
                            <span class="stat-label">Landowners</span>
                        </div>
                    </div>
                    <div class="admin-stat-item">
                        <div class="stat-icon warning">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 11v4"/><path d="M12 3h.01"/></svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $stats['admins']; ?></span>
                            <span class="stat-label">Admins</span>
                        </div>
                    </div>
                    <div class="admin-stat-item">
                        <div class="stat-icon info">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <div class="stat-content">
                            <span class="stat-number"><?php echo $stats['verifiers'] ?? 0; ?></span>
                            <span class="stat-label">Verifiers</span>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="admin-filters-card">
                    <form method="GET" class="filters-form">
                        <div class="filter-group">
                            <div class="search-box">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                    placeholder="Search users...">
                            </div>
                        </div>
                        <div class="filter-group">
                            <select name="role" class="filter-select" id="usersRoleFilter" <?php echo $district_filter !== '' ? 'disabled' : ''; ?>>
                                <option value="">All Roles</option>
                                <option value="landowner" <?php echo $role_filter === 'landowner' ? 'selected' : ''; ?>>Landowner</option>
                                <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="verifier" <?php echo $role_filter === 'verifier' ? 'selected' : ''; ?>>Verifier</option>
                            </select>
                            <?php if ($district_filter !== ''): ?>
                            <input type="hidden" name="role" value="verifier">
                            <?php endif; ?>
                        </div>
                        <div class="filter-group">
                            <select name="district" class="filter-select" aria-label="Filter verifiers by district">
                                <option value="">All districts</option>
                                <?php foreach ($district_filter_options as $dopt): ?>
                                <option value="<?php echo htmlspecialchars($dopt); ?>" <?php echo $district_filter === $dopt ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dopt); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>
                                    Active</option>
                                <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>
                                    Inactive</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn-filter">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg> Filter
                            </button>
                            <a href="users.php" class="btn-reset">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Users Table -->
                <div class="admin-table-card">
                    <div class="table-header">
                        <h4><svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>Users List</h4>
                        <span class="table-count"><?php echo $total_users; ?> users found</span>
                    </div>
                    <div class="table-responsive">
                        <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Contact</th>
                                        <th>Role</th>
                                        <th>District</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="8" class="no-data">
                                        <div class="empty-state">
                                            <svg class="icon-svg empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                            <h5>No Users Found</h5>
                                            <p>No users match your search criteria</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar">
                                                <?php if ($user['profile_img'] && file_exists("../../../assets/uploads/profiles/" . $user['profile_img'])): ?>
                                                <img src="../../../assets/uploads/profiles/<?php echo htmlspecialchars($user['profile_img']); ?>"
                                                    alt="Profile">
                                                <?php else: ?>
                                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="user-details">
                                                <span
                                                    class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="user-email"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td class="user-contact"><?php echo htmlspecialchars($user['contact_number']); ?>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td class="user-district text-muted"><?php echo $user['role'] === 'verifier' ? htmlspecialchars((string)($user['district'] ?? '')) : '—'; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['status']; ?>">
                                            <?php echo ucfirst($user['status']); ?>
                                        </span>
                                    </td>
                                    <td class="join-date"><?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action btn-edit" type="button"
                                                onclick="editUser(<?php echo $user['user_id']; ?>)">
                                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            </button>
                                            <?php if ($user['status'] === 'active'): ?>
                                            <button class="btn-action btn-deactivate" type="button"
                                                onclick="toggleUserStatus(<?php echo $user['user_id']; ?>, 'deactivate')">
                                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="18" y1="8" x2="23" y2="13"/><line x1="23" y1="8" x2="18" y2="13"/></svg>
                                            </button>
                                            <?php else: ?>
                                            <button class="btn-action btn-activate" type="button"
                                                onclick="toggleUserStatus(<?php echo $user['user_id']; ?>, 'activate')">
                                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn-action btn-delete" type="button"
                                                onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination (same as Attendify: Show per page next to "Showing...") -->
                    <?php if ($total_users > 0): ?>
                    <?php
                    $district_q = ($district_filter !== '') ? '&district=' . urlencode($district_filter) : '';
                    $pager_role = urlencode($role_filter);
                    ?>
                    <div class="admin-pagination">
                        <div class="admin-pagination-left">
                            <label class="admin-per-page-wrap">
                                <span class="admin-per-page-label">Show</span>
                                <select class="admin-per-page-select" aria-label="Rows per page" onchange="var p=new URLSearchParams(window.location.search);p.set('per_page',this.value);p.set('page','1');window.location=window.location.pathname+'?'+p.toString();">
                                    <?php foreach ($per_page_options as $n): ?>
                                    <option value="<?php echo $n; ?>" <?php echo $limit == $n ? 'selected' : ''; ?>><?php echo $n; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="admin-per-page-label">per page</span>
                            </label>
                            <span class="pagination-info">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_users); ?> of
                                <?php echo $total_users; ?> entries
                            </span>
                        </div>
                        <div class="pagination-controls">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&per_page=<?php echo (int)$limit; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $pager_role; ?>&status=<?php echo urlencode($status_filter); ?><?php echo $district_q; ?>"
                                class="page-btn">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg> Previous
                            </a>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&per_page=<?php echo (int)$limit; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $pager_role; ?>&status=<?php echo urlencode($status_filter); ?><?php echo $district_q; ?>"
                                class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&per_page=<?php echo (int)$limit; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $pager_role; ?>&status=<?php echo urlencode($status_filter); ?><?php echo $district_q; ?>"
                                class="page-btn">
                                Next <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Add User Modal (small) -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm-custom">
            <div class="modal-content modern-modal">
                <div class="modal-header modern-header">
                    <button type="button" class="modal-back-btn" data-bs-dismiss="modal" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="modal-title-wrapper">
                        <div class="modal-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        </div>
                        <div class="modal-title-text">
                            <h5>Add New User</h5>
                            <p>Create a new account in the system</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close modern-close" data-bs-dismiss="modal" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form id="addUserForm" method="POST">
                    <div class="modal-body modern-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    Full Name
                                </label>
                                <input type="text" name="full_name" class="form-control modern-input"
                                    placeholder="Enter full name" required>
                                <div class="form-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    Email Address
                                </label>
                                <input type="email" name="email" class="form-control modern-input"
                                    placeholder="Enter email address" required>
                                <div class="form-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    Contact Number
                                </label>
                                <input type="tel" name="contact_number" class="form-control modern-input"
                                    placeholder="Digits only (7–15)" required maxlength="15" inputmode="numeric" pattern="[0-9]{7,15}">
                                <div class="form-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 11v4"/><path d="M12 3h.01"/></svg>
                                    User Role
                                </label>
                                <select name="role" class="form-control modern-select" required>
                                    <option value="">Select role</option>
                                    <option value="landowner">Landowner</option>
                                    <option value="admin">Administrator</option>
                                    <option value="verifier">Verifier</option>
                                </select>
                                <div class="form-feedback"></div>
                            </div>

                        </div>
                    </div>
                    <div class="modal-footer modern-footer">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-create">
                            <span class="btn-text">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                                Create User
                            </span>
                            <span class="btn-loading">
                                <svg class="icon-svg icon-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>
                                Creating...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal (small) -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm-custom">
            <div class="modal-content modern-modal">
                <div class="modal-header modern-header">
                    <button type="button" class="modal-back-btn" data-bs-dismiss="modal" aria-label="Back">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                    </button>
                    <div class="modal-title-wrapper">
                        <div class="modal-icon edit-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </div>
                        <div class="modal-title-text">
                            <h5>Edit User</h5>
                            <p>Update user account details</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close modern-close" data-bs-dismiss="modal" aria-label="Close">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>
                <form id="editUserForm" method="POST">
                    <input type="hidden" name="user_id" id="editUserId">
                    <div class="modal-body modern-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    Full Name
                                </label>
                                <input type="text" name="full_name" id="editFullName" class="form-control modern-input"
                                    placeholder="Enter full name" required>
                                <div class="form-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    Email Address
                                </label>
                                <input type="email" name="email" id="editEmail" class="form-control modern-input"
                                    placeholder="Enter email address" required>
                                <div class="form-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    Contact Number
                                </label>
                                <input type="tel" name="contact_number" id="editContactNumber"
                                    class="form-control modern-input" placeholder="Digits only (7–15)" required maxlength="15" inputmode="numeric" pattern="[0-9]{7,15}">
                                <div class="form-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">District / assignment area</label>
                                <input type="text" name="district" id="editDistrict" class="form-control modern-input" placeholder="For verifiers: e.g. District I, Zamboanga City" maxlength="120">
                                <small class="text-muted">Used to filter verifiers when scheduling field visits.</small>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 11v4"/><path d="M12 3h.01"/></svg>
                                    User Role
                                </label>
                                <select name="role" id="editRole" class="form-control modern-select" required>
                                    <option value="">Select role</option>
                                    <option value="landowner">Landowner</option>
                                    <option value="admin">Administrator</option>
                                    <option value="verifier">Verifier</option>
                                </select>
                                <div class="form-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v4"/><path d="M12 18v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="3"/></svg>
                                    Account Status
                                </label>
                                <select name="status" id="editStatus" class="form-control modern-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                                <div class="form-feedback"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer modern-footer">
                        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">
                            <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-update">
                            <span class="btn-text">
                                <svg class="icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                Update User
                            </span>
                            <span class="btn-loading">
                                <svg class="icon-svg icon-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/></svg>
                                Updating...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../../includes/role_notifications.php'; ?>

    <!-- Hidden forms for actions -->
    <form id="userActionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="actionType">
        <input type="hidden" name="user_id" id="actionUserId">
        <input type="hidden" name="new_role" id="actionNewRole">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editUser(userId) {
        // Clear previous form data
        clearFormValidation(document.getElementById('editUserForm'));

        // Show loading state (you can add a loader here)
        fetch(`../../../handlers/admin_get_user.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const user = data.user;

                    // Populate form fields
                    document.getElementById('editUserId').value = user.user_id;
                    document.getElementById('editFullName').value = user.full_name;
                    document.getElementById('editEmail').value = user.email;
                    document.getElementById('editContactNumber').value = user.contact_number;
                    var ed = document.getElementById('editDistrict');
                    if (ed) ed.value = user.district || '';
                    document.getElementById('editRole').value = user.role;
                    document.getElementById('editStatus').value = user.status;

                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
                    modal.show();
                } else {
                    showNotification('error', data.message || 'Failed to load user data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'An error occurred while loading user data');
            });
    }

    function toggleUserStatus(userId, action) {
        if (confirm(`Are you sure you want to ${action} this user?`)) {
            document.getElementById('actionType').value = action;
            document.getElementById('actionUserId').value = userId;
            document.getElementById('userActionForm').submit();
        }
    }

    function deleteUser(userId) {
        if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            document.getElementById('actionType').value = 'delete';
            document.getElementById('actionUserId').value = userId;
            document.getElementById('userActionForm').submit();
        }
    }

    // Add User Form Handler
    document.getElementById('addUserForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');

        // Show loading state
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'flex';

        // Clear previous validations
        clearFormValidation(form);

        // Create FormData
        const formData = new FormData(form);

        fetch('../../../handlers/admin_add_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    let msg = data.message || 'User added successfully';
                    if (data.temp_password) {
                        msg += ' One-time password: ' + data.temp_password;
                    }
                    showNotification('success', msg);
                    form.reset();

                    // Close modal after delay
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById('addUserModal')).hide();
                        // Reload page to show new user
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('error', data.message);

                    // Show field-specific errors if available
                    if (data.field_errors) {
                        Object.keys(data.field_errors).forEach(field => {
                            showFieldError(form, field, data.field_errors[field]);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'An unexpected error occurred. Please try again.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.disabled = false;
                btnText.style.display = 'flex';
                btnLoading.style.display = 'none';
            });
    });

    // Edit User Form Handler
    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoading = submitBtn.querySelector('.btn-loading');

        // Show loading state
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnLoading.style.display = 'flex';

        // Clear previous validations
        clearFormValidation(form);

        // Create FormData
        const formData = new FormData(form);

        fetch('../../../handlers/admin_update_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showNotification('success', data.message);

                    // Close modal after delay
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById('editUserModal'))
                            .hide();
                        // Reload page to show updated user
                        window.location.reload();
                    }, 1500);
                } else {
                    showNotification('error', data.message);

                    // Show field-specific errors if available
                    if (data.field_errors) {
                        Object.keys(data.field_errors).forEach(field => {
                            showFieldError(form, field, data.field_errors[field]);
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'An unexpected error occurred. Please try again.');
            })
            .finally(() => {
                // Reset button state
                submitBtn.disabled = false;
                btnText.style.display = 'flex';
                btnLoading.style.display = 'none';
            });
    });

    function clearFormValidation(form) {
        form.querySelectorAll('.form-feedback').forEach(feedback => {
            feedback.textContent = '';
            feedback.classList.remove('error', 'success');
        });

        form.querySelectorAll('.modern-input, .modern-select').forEach(input => {
            input.classList.remove('error', 'success');
        });
    }

    function showFieldError(form, fieldName, message) {
        const field = form.querySelector(`[name="${fieldName}"]`);
        const feedback = field.parentNode.querySelector('.form-feedback');

        field.classList.add('error');
        feedback.textContent = message;
        feedback.classList.add('error');
    }

    // Reset form when modal is hidden
    document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function() {
        const form = document.getElementById('addUserForm');
        form.reset();
        clearFormValidation(form);
    });

    // Reset edit form when modal is hidden
    document.getElementById('editUserModal').addEventListener('hidden.bs.modal', function() {
        const form = document.getElementById('editUserForm');
        form.reset();
        clearFormValidation(form);
    });

    // Auto-submit form on filter change
    document.querySelectorAll('.filter-select').forEach(select => {
        select.addEventListener('change', function() {
            this.form.submit();
        });
    });

    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    </script>
</body>

</html>