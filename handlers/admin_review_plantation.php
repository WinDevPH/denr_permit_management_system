<?php
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'verifier'], true)) {
    header('Location: ../index.php');
    exit();
}

require_once '../config/database.php';
require_once __DIR__ . '/../config/verifier_notify_admins.php';
require_once __DIR__ . '/../config/notifications.php';
$database = new Database();
$db = $database->getConnection();

$rejection_presets = [
    'Incomplete required documents',
    'Invalid land ownership document',
    'Invalid or unreadable uploaded files',
    'Incorrect plantation location',
    'Applicant information does not match submitted documents',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plantation_id = $_POST['plantation_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $rejection_reason = trim((string) ($_POST['rejection_reason'] ?? ''));
    $is_verifier = (isset($_SESSION['role']) && $_SESSION['role'] === 'verifier');

    try {
        // Ensure verified is in ENUM
        try {
            $db->exec("ALTER TABLE plantations MODIFY COLUMN status ENUM('pending','validated','verified','registered','rejected') DEFAULT 'pending'");
        } catch (Throwable $e) {
            // ignore if already applied
        }

        // Verifier: Verified or Reject only (never Approved / never official Registered)
        $allowed = $is_verifier
            ? ['verified', 'rejected']
            : ['pending', 'validated', 'verified', 'registered', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            throw new Exception('Invalid status selected');
        }

        // First, get the plantation's user_id before updating
        $user_query = "SELECT user_id, status as old_status, plantation_name FROM plantations WHERE plantation_id = :plantation_id";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->execute([':plantation_id' => $plantation_id]);
        $plantation_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plantation_data) {
            throw new Exception('Plantation not found');
        }

        $owner_user_id = $plantation_data['user_id'];
        $old_status = $plantation_data['old_status'];
        $plantation_name = (string) ($plantation_data['plantation_name'] ?? 'plantation');

        // Verifier cannot re-review once already verified or officially registered
        if ($is_verifier && in_array($old_status, ['verified', 'registered'], true) && $status !== 'rejected') {
            throw new Exception('This plantation is already verified. No further notes or status changes are needed.');
        }

        if ($status === 'rejected') {
            if ($rejection_reason === '' || !in_array($rejection_reason, $rejection_presets, true)) {
                throw new Exception('Please select a reason for rejection.');
            }
            if ($is_verifier && trim((string) $remarks) === '') {
                throw new Exception('Please add notes for the rejection.');
            }
        } else {
            $rejection_reason = '';
            // Verified / Registered: do not keep notes
            if ($is_verifier) {
                $remarks = '';
            }
        }

        // Update plantation status
        // registered_at / approved_at only when officially Registered (not when merely Verified)
        $query = "UPDATE plantations SET 
                  status = :status,
                  rejection_reason = :rejection_reason,
                  registered_at = CASE 
                    WHEN :status2 = 'registered' THEN CURRENT_TIMESTAMP
                    ELSE registered_at 
                  END,
                  approved_at = CASE 
                    WHEN :status3 = 'registered' THEN CURRENT_TIMESTAMP
                    ELSE approved_at 
                  END
                  WHERE plantation_id = :plantation_id";
                  
        $stmt = $db->prepare($query);
        $execParams = [
            ':status' => $status,
            ':status2' => $status,
            ':status3' => $status,
            ':rejection_reason' => $status === 'rejected' ? $rejection_reason : null,
            ':plantation_id' => $plantation_id
        ];
        try {
            $stmt->execute($execParams);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'rejection_reason') !== false) {
                $query = "UPDATE plantations SET 
                  status = :status,
                  registered_at = CASE 
                    WHEN :status2 = 'registered' THEN CURRENT_TIMESTAMP
                    ELSE registered_at 
                  END,
                  approved_at = CASE 
                    WHEN :status3 = 'registered' THEN CURRENT_TIMESTAMP
                    ELSE approved_at 
                  END
                  WHERE plantation_id = :plantation_id";
                $stmt = $db->prepare($query);
                try {
                    $stmt->execute([
                        ':status' => $status,
                        ':status2' => $status,
                        ':status3' => $status,
                        ':plantation_id' => $plantation_id
                    ]);
                } catch (PDOException $e2) {
                    if (strpos($e2->getMessage(), 'approved_at') !== false) {
                        $query = "UPDATE plantations SET 
                          status = :status,
                          registered_at = CASE 
                            WHEN :status2 = 'registered' THEN CURRENT_TIMESTAMP
                            ELSE registered_at 
                          END
                          WHERE plantation_id = :plantation_id";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            ':status' => $status,
                            ':status2' => $status,
                            ':plantation_id' => $plantation_id
                        ]);
                    } else {
                        throw $e2;
                    }
                }
            } elseif (strpos($msg, 'approved_at') !== false) {
                $query = "UPDATE plantations SET 
                  status = :status,
                  rejection_reason = :rejection_reason,
                  registered_at = CASE 
                    WHEN :status2 = 'registered' THEN CURRENT_TIMESTAMP
                    ELSE registered_at 
                  END
                  WHERE plantation_id = :plantation_id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':status' => $status,
                    ':status2' => $status,
                    ':rejection_reason' => $status === 'rejected' ? $rejection_reason : null,
                    ':plantation_id' => $plantation_id
                ]);
            } else {
                throw $e;
            }
        }

        try {
            $detail = trim((string) $remarks);
            if ($status === 'rejected' && $rejection_reason !== '') {
                $detail = $rejection_reason . ($detail !== '' ? ' — ' . $detail : '');
            }
            if ($detail === '') {
                $detail = null;
            }
            $pal = $db->prepare('INSERT INTO plantation_activity_log (plantation_id, actor_user_id, action, old_status, new_status, detail) VALUES (?, ?, ?, ?, ?, ?)');
            $pal->execute([
                (int) $plantation_id,
                (int) $_SESSION['user_id'],
                'status_change',
                $old_status,
                $status,
                $detail,
            ]);
        } catch (Throwable $e) {
            error_log('plantation_activity_log: ' . $e->getMessage());
        }

        // Notify landowner when status changes
        if ($status !== $old_status && (int) $owner_user_id > 0) {
            $notif_message = '';
            if ($status === 'validated') {
                $notif_message = 'Your plantation "' . $plantation_name . '" details have been checked by admin. A verification visit may be scheduled next.';
            } elseif ($status === 'verified') {
                $notif_message = 'Your plantation "' . $plantation_name . '" has been verified by the field verifier. Official registration will follow.';
            } elseif ($status === 'registered') {
                $notif_message = 'Your plantation "' . $plantation_name . '" has been officially registered!';
            } elseif ($status === 'rejected') {
                $notif_message = 'Your plantation "' . $plantation_name . '" application was rejected. Reason: ' . $rejection_reason . '.';
                if (trim((string) $remarks) !== '') {
                    $notif_message .= ' Notes: ' . (strlen($remarks) > 180 ? substr($remarks, 0, 180) . '…' : $remarks);
                }
            } else {
                $notif_message = 'Your plantation "' . $plantation_name . '" status is now ' . $status . '.';
            }

            denr_notify_user($db, (int) $owner_user_id, $notif_message);

            if ($status === 'verified') {
                denr_notify_admins_verifier_activity(
                    $db,
                    'Plantation verified: "' . $plantation_name . '" — awaiting official registration.'
                );
            }
            if ($status === 'registered') {
                denr_notify_verifiers(
                    $db,
                    'Plantation registered: "' . $plantation_name . '" is now officially registered.'
                );
            }
        }

        // Log the action
        $action = "Updated plantation #$plantation_id status from $old_status to $status";
        $log_query = "INSERT INTO audit_logs (user_id, action, module) VALUES (:user_id, :action, 'plantations')";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':action' => $action
        ]);

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'verifier') {
            denr_notify_admins_verifier_activity(
                $db,
                sprintf(
                    'Updated plantation #%d from %s to %s.',
                    (int) $plantation_id,
                    $old_status,
                    $status
                )
            );
        }

        $_SESSION['success'] = "Plantation status updated successfully.";
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating plantation status.";
        error_log($e->getMessage());
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        error_log($e->getMessage());
    }
}

$is_verifier = (isset($_SESSION['role']) && $_SESSION['role'] === 'verifier');
header('Location: ' . ($is_verifier ? '../modules/verifier/plantations/plantations.php' : '../modules/admin/plantations/plantations.php'));
exit();
