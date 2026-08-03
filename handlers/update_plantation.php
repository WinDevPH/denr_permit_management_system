<?php
session_start();
require_once '../config/database.php';
require_once '../config/contact_utils.php';

// Check if user is logged in and is a landowner
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get POST data
    $plantation_id = $_POST['plantation_id'] ?? null;
    $plantation_name = $_POST['plantation_name'] ?? '';
    
    // Handle multiple tree species
    $tree_species_array = isset($_POST['tree_species']) ? $_POST['tree_species'] : [];
    $tree_species = is_array($tree_species_array) ? implode(', ', $tree_species_array) : trim($tree_species_array);
    
    $land_area = $_POST['land_area'] ?? 0;
    $age_of_plantation = isset($_POST['age_of_plantation']) && $_POST['age_of_plantation'] !== ''
        ? floatval($_POST['age_of_plantation'])
        : null;
    $location_address = $_POST['location_address'] ?? '';
    $district = isset($_POST['district']) ? trim($_POST['district']) : null;
    if ($district === '') {
        $district = null;
    }
    $contact_person_name = isset($_POST['contact_person_name']) ? trim($_POST['contact_person_name']) : '';
    $contact_address = isset($_POST['contact_address']) ? trim($_POST['contact_address']) : '';
    $contact_phone = isset($_POST['contact_phone']) ? trim($_POST['contact_phone']) : '';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $lot_number = isset($_POST['lot_number']) ? trim($_POST['lot_number']) : null;
    $specifications = isset($_POST['specifications']) ? trim($_POST['specifications']) : null;

    $mohon_points_json = null;
    $landmark_latitude = null;
    $landmark_longitude = null;
    if (!empty($_POST['mohon_points_json'])) {
        $decoded = json_decode($_POST['mohon_points_json'], true);
        if (is_array($decoded)) {
            $clean = [];
            foreach ($decoded as $pt) {
                if (isset($pt['lat'], $pt['lng']) && is_numeric($pt['lat']) && is_numeric($pt['lng'])) {
                    $clean[] = ['lat' => round((float) $pt['lat'], 8), 'lng' => round((float) $pt['lng'], 8)];
                }
            }
            if (count($clean) > 0) {
                $mohon_points_json = json_encode($clean);
                $landmark_latitude = $clean[0]['lat'];
                $landmark_longitude = $clean[0]['lng'];
            }
        }
    }
    if ($mohon_points_json === null) {
        $la = isset($_POST['landmark_latitude']) && $_POST['landmark_latitude'] !== '' ? floatval($_POST['landmark_latitude']) : null;
        $lo = isset($_POST['landmark_longitude']) && $_POST['landmark_longitude'] !== '' ? floatval($_POST['landmark_longitude']) : null;
        if ($la !== null && $lo !== null) {
            $landmark_latitude = $la;
            $landmark_longitude = $lo;
            $mohon_points_json = json_encode([['lat' => $la, 'lng' => $lo]]);
        }
    }

    $user_id = $_SESSION['user_id'];

    // Validate plantation ownership
    $check_query = "SELECT user_id FROM plantations WHERE plantation_id = ? AND user_id = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$plantation_id, $user_id]);

    if ($check_stmt->rowCount() === 0) {
        echo json_encode(['status' => 'error', 'message' => 'Plantation not found or unauthorized']);
        exit();
    }

    if ($contact_person_name === '' || $contact_address === '' || $contact_phone === '') {
        echo json_encode(['status' => 'error', 'message' => 'Contact name, address, and contact number are required']);
        exit();
    }
    $contact_digits = denr_normalize_contact_number($contact_phone);
    if ($contact_digits === null) {
        echo json_encode(['status' => 'error', 'message' => 'Contact number must be digits only (7–15 digits). No letters.']);
        exit();
    }
    $contact_phone = $contact_digits;

    if ($age_of_plantation === null || $age_of_plantation < 0) {
        echo json_encode(['status' => 'error', 'message' => 'Age of plantation is required (years, 0 or greater).']);
        exit();
    }

    // Shapefile / polygon boundary removed — clear stored boundary_geojson on save (Mohon-only).
    $boundary_geojson = null;

    $allowed_doc_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $allowed_image_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024;
    $upload_dir = '../assets/uploads/verification_documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $saveUpload = function (array $file, array $allowed_types, string $label) use ($upload_dir, $max_size): string {
        if (!in_array($file['type'], $allowed_types, true)) {
            throw new Exception('Invalid file type for ' . $label . '.');
        }
        if ($file['size'] > $max_size) {
            throw new Exception($label . ' file size exceeds 5MB limit.');
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = uniqid() . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $upload_dir . $name)) {
            throw new Exception('Failed to upload ' . $label . '.');
        }
        return 'assets/uploads/verification_documents/' . $name;
    };

    $tax_declaration_path = null;
    if (isset($_FILES['tax_declaration']) && $_FILES['tax_declaration']['error'] === UPLOAD_ERR_OK) {
        $tax_declaration_path = $saveUpload($_FILES['tax_declaration'], $allowed_doc_types, 'Tax Declaration');
    }
    $site_photo_path = null;
    if (isset($_FILES['site_photo']) && $_FILES['site_photo']['error'] === UPLOAD_ERR_OK) {
        $site_photo_path = $saveUpload($_FILES['site_photo'], $allowed_image_types, 'site photo');
    }
    $verification_document = null;
    if (isset($_FILES['verification_document']) && $_FILES['verification_document']['error'] === UPLOAD_ERR_OK) {
        $verification_document = $saveUpload($_FILES['verification_document'], $allowed_doc_types, 'verification document');
    }

    // Update plantation data (incl. lot, specifications, landmark coordinates)
    $query = "UPDATE plantations SET 
              plantation_name = ?,
              tree_species = ?,
              land_area = ?,
              age_of_plantation = ?,
              location_address = ?,
              district = ?,
              contact_person_name = ?,
              contact_address = ?,
              contact_phone = ?,
              latitude = ?,
              longitude = ?,
              lot_number = ?,
              specifications = ?,
              landmark_latitude = ?,
              landmark_longitude = ?,
              mohon_points_json = ?,
              boundary_geojson = ?";
    $params = [
        $plantation_name,
        $tree_species,
        $land_area,
        $age_of_plantation,
        $location_address,
        $district,
        $contact_person_name,
        $contact_address,
        $contact_phone,
        $latitude,
        $longitude,
        $lot_number,
        $specifications,
        $landmark_latitude,
        $landmark_longitude,
        $mohon_points_json,
        $boundary_geojson,
    ];
    if ($verification_document) {
        $query .= ', verification_document = ?';
        $params[] = $verification_document;
    }
    if ($tax_declaration_path) {
        $query .= ', tax_declaration_path = ?';
        $params[] = $tax_declaration_path;
    }
    if ($site_photo_path) {
        $query .= ', site_photo_path = ?';
        $params[] = $site_photo_path;
    }
    $query .= ' WHERE plantation_id = ? AND user_id = ?';
    $params[] = $plantation_id;
    $params[] = $user_id;

    $stmt = $db->prepare($query);
    $result = $stmt->execute($params);

    if ($result) {
        // Log the update action
        $log_query = "INSERT INTO audit_logs (user_id, action, module) VALUES (?, ?, ?)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([
            $user_id,
            "Updated plantation: $plantation_name",
            "plantations"
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Plantation updated successfully',
            'data' => [
                'plantation_id' => $plantation_id,
                'plantation_name' => $plantation_name
            ]
        ]);
    } else {
        throw new Exception('Failed to update plantation');
    }
} catch (Exception $e) {
    error_log("Error updating plantation: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while updating the plantation'
    ]);
}
