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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plantation_id = $_POST['plantation_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';

    try {
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

        // Update plantation status (approved_at when officially registered)
        $query = "UPDATE plantations SET 
                  status = :status,
                  registered_at = CASE 
                    WHEN :status = 'registered' THEN CURRENT_TIMESTAMP
                    ELSE registered_at 
                  END,
                  approved_at = CASE 
                    WHEN :status = 'registered' THEN CURRENT_TIMESTAMP
                    ELSE approved_at 
                  END
                  WHERE plantation_id = :plantation_id";
                  
        $stmt = $db->prepare($query);
        try {
            $stmt->execute([
                ':status' => $status,
                ':plantation_id' => $plantation_id
            ]);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'approved_at') !== false) {
                $query = "UPDATE plantations SET 
                  status = :status,
                  registered_at = CASE 
                    WHEN :status = 'registered' THEN CURRENT_TIMESTAMP
                    ELSE registered_at 
                  END
                  WHERE plantation_id = :plantation_id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':status' => $status,
                    ':plantation_id' => $plantation_id
                ]);
            } else {
                throw $e;
            }
        }

        try {
            $detail = trim((string) $remarks);
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
                $notif_message = 'Your plantation "' . $plantation_name . '" has been validated.';
            } elseif ($status === 'registered') {
                $notif_message = 'Your plantation "' . $plantation_name . '" has been officially registered!';
            } elseif ($status === 'rejected') {
                $notif_message = 'Your plantation "' . $plantation_name . '" application was rejected.';
                if (trim((string) $remarks) !== '') {
                    $notif_message .= ' Remarks: ' . (strlen($remarks) > 180 ? substr($remarks, 0, 180) . '…' : $remarks);
                }
            } else {
                $notif_message = 'Your plantation "' . $plantation_name . '" status is now ' . $status . '.';
            }

            denr_notify_user($db, (int) $owner_user_id, $notif_message);

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
