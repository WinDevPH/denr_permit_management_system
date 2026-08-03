<?php
/**
 * Admin: schedule one or more verifiers to visit a plantation on a date/time.
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/notifications.php';

$verifier_ids = [];
if (!empty($_POST['verifier_ids']) && is_array($_POST['verifier_ids'])) {
    foreach ($_POST['verifier_ids'] as $vid) {
        $vid = (int) $vid;
        if ($vid > 0) {
            $verifier_ids[] = $vid;
        }
    }
    $verifier_ids = array_values(array_unique($verifier_ids));
} elseif (!empty($_POST['verifier_id'])) {
    $verifier_ids = [(int) $_POST['verifier_id']];
}

$plantation_id = isset($_POST['plantation_id']) ? (int) $_POST['plantation_id'] : 0;
$scheduled_raw = isset($_POST['scheduled_at']) ? trim($_POST['scheduled_at']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;

if (count($verifier_ids) === 0 || $plantation_id <= 0 || $scheduled_raw === '') {
    echo json_encode(['success' => false, 'message' => 'Select at least one verifier, a plantation, and date/time.']);
    exit;
}

$dt = DateTime::createFromFormat('Y-m-d\TH:i', $scheduled_raw);
if (!$dt) {
    $dt = date_create($scheduled_raw);
}
if (!$dt) {
    echo json_encode(['success' => false, 'message' => 'Invalid date/time format.']);
    exit;
}
$scheduled_at = $dt->format('Y-m-d H:i:s');

try {
    $database = new Database();
    $db = $database->getConnection();

    $pCheck = $db->prepare('SELECT plantation_id, plantation_name, location_address, user_id, status FROM plantations WHERE plantation_id = ?');
    $pCheck->execute([$plantation_id]);
    $plant = $pCheck->fetch(PDO::FETCH_ASSOC);
    if (!$plant) {
        echo json_encode(['success' => false, 'message' => 'Plantation not found.']);
        exit;
    }

    // Admin must CHECK (validate) client details before scheduling a verifier
    $pStatus = (string) ($plant['status'] ?? '');
    if (!in_array($pStatus, ['validated', 'registered'], true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Check and approve the plantation details first (set status to Checked) before scheduling a verifier.',
        ]);
        exit;
    }

    $vCheck = $db->prepare('SELECT user_id FROM users WHERE user_id = ? AND role = ?');
    $created = 0;
    $when = $dt->format('M d, Y g:i A');
    $pname = $plant['plantation_name'];

    $ins = $db->prepare('INSERT INTO verification_assignments
        (verifier_id, plantation_id, permit_id, scheduled_at, status, notes)
        VALUES (?, ?, NULL, ?, ?, ?)');

    foreach ($verifier_ids as $verifier_id) {
        $vCheck->execute([$verifier_id, 'verifier']);
        if ($vCheck->rowCount() === 0) {
            continue;
        }
        $ins->execute([
            $verifier_id,
            $plantation_id,
            $scheduled_at,
            'pending',
            $notes !== '' ? $notes : null,
        ]);
        $created++;

        $notifMsg = 'Pending verification schedule: visit plantation "' . $pname . '" on ' . $when . '.';
        if ($notes) {
            $notifMsg .= ' Notes: ' . (strlen($notes) > 200 ? substr($notes, 0, 200) . '…' : $notes);
        }
        denr_notify_user($db, $verifier_id, $notifMsg, $scheduled_at);
    }

    $owner_id = (int) ($plant['user_id'] ?? 0);
    if ($owner_id > 0) {
        denr_notify_user(
            $db,
            $owner_id,
            'A verification visit has been scheduled for your plantation "' . $pname . '" on ' . $when . '.',
            $scheduled_at
        );
    }

    denr_notify_admins(
        $db,
        'Verification visit scheduled for plantation "' . $pname . '" on ' . $when . ' (' . $created . ' verifier' . ($created === 1 ? '' : 's') . ').'
    );

    if ($created === 0) {
        echo json_encode(['success' => false, 'message' => 'No valid verifiers selected.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => $created === 1
            ? 'Schedule saved. The verifier has been notified.'
            : "Schedule saved for {$created} verifiers. They have been notified.",
        'created' => $created,
    ]);
} catch (Throwable $e) {
    error_log('save_verification_assignment: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not save schedule. Please try again.']);
}
