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
    $permit_id = $_POST['permit_id'] ?? '';
    $status = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    $plantation_id = $_POST['plantation_id'] ?? '';

    try {
        $db->beginTransaction();

        // Get current permit details before updating
        $status_query = "SELECT pt.status as old_status, pt.permit_type, p.plantation_name, p.user_id
                         FROM permits pt
                         JOIN plantations p ON pt.plantation_id = p.plantation_id
                         WHERE pt.permit_id = :permit_id";
        $status_stmt = $db->prepare($status_query);
        $status_stmt->execute([':permit_id' => $permit_id]);
        $permit_data = $status_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$permit_data) {
            throw new Exception('Permit not found');
        }
        
        $old_status = $permit_data['old_status'];
        $owner_user_id = (int) $permit_data['user_id'];
        $plantation_name = (string) ($permit_data['plantation_name'] ?? 'plantation');
        $ptype_label = denr_permit_type_label((string) ($permit_data['permit_type'] ?? 'cutting'));

        // Issued permits must not be editable
        if ($old_status === 'approved') {
            throw new Exception('This permit has already been issued and can no longer be edited.');
        }

        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new Exception('Invalid permit action');
        }

        // Update permit status
        $query = "UPDATE permits SET 
                  status = :status,
                  remarks = :remarks,
                  approved_at = CASE 
                    WHEN :status = 'approved' THEN CURRENT_TIMESTAMP
                    ELSE NULL 
                  END
                  WHERE permit_id = :permit_id AND status = 'pending'";
                  
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':status' => $status,
            ':remarks' => $remarks,
            ':permit_id' => $permit_id
        ]);
        if ($stmt->rowCount() === 0) {
            throw new Exception('This permit has already been issued and can no longer be edited.');
        }

        // Optional document upload on approve (e.g. admin flow); skip if no file submitted
        if ($status === 'approved'
            && !empty($_FILES['permit_document']['name'])
            && (int) ($_FILES['permit_document']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
        ) {
            $file = $_FILES['permit_document'];
            $fileName = $file['name'];
            $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedTypes = ['pdf', 'doc', 'docx'];
            if (!in_array($fileType, $allowedTypes, true)) {
                throw new Exception('Invalid file type. Only PDF and DOC files are allowed.');
            }

            $newFileName = uniqid() . '_permit_' . $permit_id . '.' . $fileType;
            $uploadDir = '../assets/uploads/documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uploadPath = $uploadDir . $newFileName;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $docQuery = "INSERT INTO documents (plantation_id, document_name, file_name, file_path) 
                            VALUES (:plantation_id, :document_name, :file_name, :file_path)";
                $docStmt = $db->prepare($docQuery);
                $docStmt->execute([
                    ':plantation_id' => $plantation_id,
                    ':document_name' => 'Permit Approval Document',
                    ':file_name' => $fileName,
                    ':file_path' => 'assets/uploads/documents/' . $newFileName
                ]);
            }
        }

        // Create notification for the landowner if status changed
        if ($status !== $old_status) {
            if ($status === 'approved') {
                $notifMessage = 'Your ' . strtolower($ptype_label) . ' for plantation "' . $plantation_name . '" has been approved and issued.';
            } elseif ($status === 'rejected') {
                $notifMessage = 'Your ' . strtolower($ptype_label) . ' for plantation "' . $plantation_name . '" was rejected.';
                if (trim((string) $remarks) !== '') {
                    $notifMessage .= ' Remarks: ' . (strlen($remarks) > 180 ? substr($remarks, 0, 180) . '…' : $remarks);
                }
            } else {
                $notifMessage = 'Your permit for plantation "' . $plantation_name . '" status is now ' . $status . '.';
            }

            if ($owner_user_id > 0) {
                denr_notify_user($db, $owner_user_id, $notifMessage);
            }

            if ($status === 'approved') {
                denr_notify_verifiers(
                    $db,
                    'Permit issued: ' . $ptype_label . ' for plantation "' . $plantation_name . '".'
                );
            }
        }

        // Log the action
        $action = "Updated permit #$permit_id status from $old_status to $status";
        $log_query = "INSERT INTO audit_logs (user_id, action, module) VALUES (:user_id, :action, 'permits')";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':action' => $action
        ]);

        $db->commit();

        if (isset($_SESSION['role']) && $_SESSION['role'] === 'verifier') {
            $pid = (int) $permit_id;
            $plid = (int) $plantation_id;
            $extra = $plid > 0 ? sprintf(' (plantation #%d)', $plid) : '';
            denr_notify_admins_verifier_activity(
                $db,
                sprintf('Updated permit #%d to %s%s.', $pid, $status, $extra)
            );
        }

        $_SESSION['success'] = "Permit " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully.";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error processing permit: " . $e->getMessage();
        error_log($e->getMessage());
    }
}

$is_verifier = (isset($_SESSION['role']) && $_SESSION['role'] === 'verifier');
header('Location: ' . ($is_verifier ? '../modules/verifier/permits/permits.php' : '../modules/admin/permits/permits.php'));
exit();