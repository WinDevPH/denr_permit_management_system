<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permit Process - DENR</title>
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
                    <h2><i class="fas fa-info-circle"></i> Permit to Cut – Process Clarification</h2>
                    <p>How the request of permit to cut works: registration, request, and verification</p>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Why we have Registration, Request, etc.</h5>
                        <ol class="process-steps">
                            <li><strong>Registration</strong> – Landowners register their plantation (land area, location, map pins, Mohon boundary). This creates the record that can later be used for permits.</li>
                            <li><strong>Request</strong> – After a plantation is validated/registered, the landowner can request a permit (e.g. Registration Certificate or Cutting Permit).</li>
                            <li><strong>Verification</strong> – Admin/verifier can schedule and perform land and permit verification. Time schedule is used for verification.</li>
                            <li><strong>Approval / Rejection</strong> – Admin reviews the permit application and approves or rejects with remarks.</li>
                        </ol>
                        <p class="mt-3 text-muted small">Limitation: Cutting permits are for cutting only. Chainsaw and tree registry numbers are recorded where applicable.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
