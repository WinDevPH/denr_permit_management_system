<?php
session_start();
require_once '../config/database.php';
require_once '../config/notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Ensure cutting detail columns exist
    try {
        $cols = $db->query('SHOW COLUMNS FROM permits')->fetchAll(PDO::FETCH_COLUMN);
        $add = [
            'applicant_name' => "ADD COLUMN `applicant_name` VARCHAR(150) DEFAULT NULL",
            'contact_number' => "ADD COLUMN `contact_number` VARCHAR(50) DEFAULT NULL",
            'property_location' => "ADD COLUMN `property_location` TEXT DEFAULT NULL",
            'proof_of_ownership' => "ADD COLUMN `proof_of_ownership` VARCHAR(255) DEFAULT NULL",
            'cutting_land_area' => "ADD COLUMN `cutting_land_area` DECIMAL(10,2) DEFAULT NULL",
            'cutting_tree_species' => "ADD COLUMN `cutting_tree_species` VARCHAR(255) DEFAULT NULL",
            'trees_to_cut' => "ADD COLUMN `trees_to_cut` INT DEFAULT NULL",
            'reason_for_cutting' => "ADD COLUMN `reason_for_cutting` TEXT DEFAULT NULL",
            'intended_use' => "ADD COLUMN `intended_use` TEXT DEFAULT NULL",
            'supporting_docs_json' => "ADD COLUMN `supporting_docs_json` TEXT DEFAULT NULL",
        ];
        foreach ($add as $col => $ddl) {
            if (!in_array($col, $cols, true)) {
                $db->exec("ALTER TABLE permits {$ddl}");
            }
        }
    } catch (Throwable $e) {
        // continue — insert may still work if columns exist
    }

    if (empty($_POST['plantation_id'])) {
        throw new Exception('Please select a plantation');
    }

    $required = [
        'applicant_name' => 'Applicant name',
        'contact_number' => 'Contact number',
        'property_location' => 'Property location',
        'proof_of_ownership' => 'Proof of ownership',
        'cutting_land_area' => 'Land area',
        'cutting_tree_species' => 'Tree species',
        'trees_to_cut' => 'Number of trees to be cut',
        'reason_for_cutting' => 'Reason for cutting',
        'intended_use' => 'Intended use of the timber',
    ];
    foreach ($required as $key => $label) {
        if (!isset($_POST[$key]) || trim((string) $_POST[$key]) === '') {
            throw new Exception($label . ' is required');
        }
    }

    // Verify plantation ownership
    $query = "SELECT p.*, u.user_id 
              FROM plantations p 
              JOIN users u ON p.user_id = u.user_id 
              WHERE p.plantation_id = :plantation_id 
              AND p.user_id = :user_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':plantation_id', $_POST['plantation_id']);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $plantation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plantation) {
        throw new Exception('Plantation not found or you do not have permission');
    }

    if ($plantation['status'] !== 'registered') {
        throw new Exception('Plantation must be officially registered before requesting a cutting permit');
    }

    // Check for existing pending permits
    $query = "SELECT permit_id FROM permits 
              WHERE plantation_id = :plantation_id 
              AND status = 'pending'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':plantation_id', $_POST['plantation_id']);
    $stmt->execute();

    if ($stmt->fetch()) {
        throw new Exception('There is already a pending permit request for this plantation');
    }

    // Supporting documents upload
    $upload_dir = '../assets/uploads/permit_documents/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    $saved_docs = [];
    if (empty($_FILES['supporting_docs']) || empty($_FILES['supporting_docs']['name'])) {
        throw new Exception('Please upload supporting documents (Valid ID, Tax Declaration/Title, Plantation Photos)');
    }

    $names = $_FILES['supporting_docs']['name'];
    $tmps = $_FILES['supporting_docs']['tmp_name'];
    $errors = $_FILES['supporting_docs']['error'];
    $types = $_FILES['supporting_docs']['type'];
    if (!is_array($names)) {
        $names = [$names];
        $tmps = [$tmps];
        $errors = [$errors];
        $types = [$types];
    }
    $uploaded_count = 0;
    for ($i = 0; $i < count($names); $i++) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (($errors[$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new Exception('Failed to upload one of the supporting documents');
        }
        $mime = $types[$i] ?? '';
        if ($mime === '' || !in_array($mime, $allowed_types, true)) {
            // Fallback by extension
            $ext = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx'], true)) {
                throw new Exception('Invalid file type for supporting documents');
            }
        }
        $ext = pathinfo($names[$i], PATHINFO_EXTENSION);
        $unique = 'permit_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $upload_dir . $unique;
        if (!move_uploaded_file($tmps[$i], $dest)) {
            throw new Exception('Failed to save supporting document');
        }
        $saved_docs[] = [
            'name' => $names[$i],
            'path' => 'assets/uploads/permit_documents/' . $unique,
        ];
        $uploaded_count++;
    }
    if ($uploaded_count < 1) {
        throw new Exception('Please upload at least one supporting document');
    }

    $db->beginTransaction();

    try {
        // Cutting permit only — Registration Certificate removed from request flow
        $permit_type = 'cutting';
        $applicant_name = trim((string) $_POST['applicant_name']);
        $contact_number = trim((string) $_POST['contact_number']);
        $property_location = trim((string) $_POST['property_location']);
        $proof_of_ownership = trim((string) $_POST['proof_of_ownership']);
        $cutting_land_area = (float) $_POST['cutting_land_area'];
        $cutting_tree_species = trim((string) $_POST['cutting_tree_species']);
        $trees_to_cut = (int) $_POST['trees_to_cut'];
        $reason_for_cutting = trim((string) $_POST['reason_for_cutting']);
        $intended_use = trim((string) $_POST['intended_use']);
        $remarks = trim((string) ($_POST['remarks'] ?? ''));
        $docs_json = json_encode($saved_docs);

        $query = "INSERT INTO permits (
                    plantation_id, permit_type, remarks,
                    applicant_name, contact_number, property_location, proof_of_ownership,
                    cutting_land_area, cutting_tree_species, trees_to_cut,
                    reason_for_cutting, intended_use, supporting_docs_json
                  ) VALUES (
                    :plantation_id, :permit_type, :remarks,
                    :applicant_name, :contact_number, :property_location, :proof_of_ownership,
                    :cutting_land_area, :cutting_tree_species, :trees_to_cut,
                    :reason_for_cutting, :intended_use, :supporting_docs_json
                  )";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':plantation_id' => $_POST['plantation_id'],
            ':permit_type' => $permit_type,
            ':remarks' => $remarks,
            ':applicant_name' => $applicant_name,
            ':contact_number' => $contact_number,
            ':property_location' => $property_location,
            ':proof_of_ownership' => $proof_of_ownership,
            ':cutting_land_area' => $cutting_land_area,
            ':cutting_tree_species' => $cutting_tree_species,
            ':trees_to_cut' => $trees_to_cut,
            ':reason_for_cutting' => $reason_for_cutting,
            ':intended_use' => $intended_use,
            ':supporting_docs_json' => $docs_json,
        ]);

        $permit_id = (int) $db->lastInsertId();
        $ptype_label = denr_permit_type_label($permit_type);
        $owner_label = isset($_SESSION['full_name']) ? trim((string) $_SESSION['full_name']) : 'Landowner';

        denr_notify_staff(
            $db,
            'New permit request pending: ' . $ptype_label . ' for plantation "' . $plantation['plantation_name'] . '" by ' . $owner_label . '.'
        );
        denr_notify_user(
            $db,
            (int) $_SESSION['user_id'],
            'Your ' . strtolower($ptype_label) . ' request for plantation "' . $plantation['plantation_name'] . '" has been submitted and is pending review.'
        );

        $action = "Submitted cutting permit request for plantation ID: " . $_POST['plantation_id'];
        $query = "INSERT INTO audit_logs (user_id, action, module) VALUES (:user_id, :action, 'permits')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':action', $action);
        $stmt->execute();

        $db->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Cutting permit request submitted successfully',
            'permit_id' => $permit_id,
        ]);
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
