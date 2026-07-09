<?php
/**
 * Printable registration certificate (template). Official layout reference: DENR issuances portal.
 */
session_start();
require_once __DIR__ . '/../../../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    header('Location: ../../../index.php');
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid plantation');
}

$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare('SELECT p.*, u.full_name AS owner_name, u.email AS owner_email FROM plantations p JOIN users u ON p.user_id = u.user_id WHERE p.plantation_id = ? AND p.user_id = ?');
$stmt->execute([$id, $_SESSION['user_id']]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$p || ($p['status'] ?? '') !== 'registered') {
    http_response_code(403);
    exit('Certificate is available only for registered plantations.');
}

$refUrl = 'https://www.denr.gov.ph/issuances';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Certificate — <?php echo htmlspecialchars($p['plantation_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #e8ecf1; font-family: Georgia, 'Times New Roman', serif; }
        .cert-wrap { max-width: 720px; margin: 2rem auto; padding: 1rem; }
        .cert-sheet {
            background: #fffef8;
            border: 3px double #1a4d2e;
            box-shadow: 0 8px 28px rgba(0,0,0,.12);
            padding: 2.5rem 2.75rem;
            min-height: 900px;
        }
        .cert-seal { text-align: center; margin-bottom: 1rem; }
        .cert-seal img { height: 64px; }
        .cert-title { text-align: center; font-size: 1.35rem; letter-spacing: .12em; text-transform: uppercase; color: #1a4d2e; font-weight: 700; }
        .cert-sub { text-align: center; font-size: .95rem; color: #444; margin-top: .35rem; }
        .cert-body { margin-top: 2rem; font-size: 1.05rem; line-height: 1.65; color: #222; }
        .cert-field { border-bottom: 1px dotted #666; min-height: 1.5rem; display: inline-block; min-width: 60%; }
        .cert-meta { margin-top: 2.5rem; font-size: .9rem; color: #555; }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .cert-wrap { margin: 0; padding: 0; max-width: none; }
            .cert-sheet { box-shadow: none; border: 2px solid #000; }
        }
    </style>
</head>
<body>
    <div class="cert-wrap">
        <p class="no-print small text-muted mb-2">
            This page follows a formal certificate layout. For official samples and issuances, see the
            <a href="<?php echo htmlspecialchars($refUrl); ?>" target="_blank" rel="noopener">DENR issuances portal</a>.
        </p>
        <div class="cert-sheet">
            <div class="cert-seal">
                <img src="../../../assets/img/denrlogo.png" alt="">
            </div>
            <div class="cert-title">Certificate of Tree Plantation Registration</div>
            <div class="cert-sub">Department of Environment and Natural Resources — Region IX (Digital System)</div>
            <div class="cert-body">
                <p>This is to certify that the private tree plantation described below is recorded in the DENR Region IX Digital System as <strong>registered</strong>.</p>
                <p><strong>Registered owner:</strong> <span class="cert-field"><?php echo htmlspecialchars($p['owner_name']); ?></span></p>
                <p><strong>Plantation name:</strong> <span class="cert-field"><?php echo htmlspecialchars($p['plantation_name']); ?></span></p>
                <p><strong>Location:</strong> <span class="cert-field"><?php echo htmlspecialchars($p['location_address']); ?></span></p>
                <p><strong>Land area (ha):</strong> <span class="cert-field"><?php echo htmlspecialchars(number_format((float) $p['land_area'], 2)); ?></span></p>
                <p><strong>Tree species (summary):</strong> <span class="cert-field"><?php echo htmlspecialchars($p['tree_species']); ?></span></p>
                <p class="cert-meta">
                    Registration reference: <strong>PLT-<?php echo (int) $p['plantation_id']; ?></strong><br>
                    <?php if (!empty($p['approved_at'])): ?>
                    Date of registration: <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($p['approved_at']))); ?></strong><br>
                    <?php elseif (!empty($p['registered_at'])): ?>
                    Date of registration: <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($p['registered_at']))); ?></strong><br>
                    <?php endif; ?>
                    This document was generated from the landowner portal and does not replace any official stamped certificate where required by law.
                </p>
            </div>
        </div>
        <div class="text-center no-print mt-3">
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print / Save as PDF</button>
            <a href="plantations.php" class="btn btn-outline-secondary">Back to plantations</a>
        </div>
    </div>
</body>
</html>
