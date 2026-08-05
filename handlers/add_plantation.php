<?php
session_start();
require_once '../config/database.php';
require_once '../config/contact_utils.php';
require_once '../config/notifications.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'landowner') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get form data
    $plantation_id = isset($_POST['plantation_id']) ? trim($_POST['plantation_id']) : null;
    $user_id = $_SESSION['user_id'];
    $plantation_name = trim($_POST['plantation_name']);
    
    // Handle multiple tree species
    $tree_species_array = isset($_POST['tree_species']) ? $_POST['tree_species'] : [];
    $tree_species = is_array($tree_species_array) ? implode(', ', $tree_species_array) : trim($tree_species_array);
    
    $land_area = floatval($_POST['land_area']);
    $age_of_plantation = isset($_POST['age_of_plantation']) && $_POST['age_of_plantation'] !== ''
        ? floatval($_POST['age_of_plantation'])
        : null;
    $location_address = trim($_POST['location_address']);
    $district = isset($_POST['district']) ? trim($_POST['district']) : null;
    if ($district === '') {
        $district = null;
    }
    $contact_person_name = isset($_POST['contact_person_name']) ? trim($_POST['contact_person_name']) : '';
    $contact_address = isset($_POST['contact_address']) ? trim($_POST['contact_address']) : '';
    $contact_phone = isset($_POST['contact_phone']) ? trim($_POST['contact_phone']) : '';
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $lot_number = isset($_POST['lot_number']) ? trim($_POST['lot_number']) : null;
    $specifications = isset($_POST['specifications']) ? trim($_POST['specifications']) : null;

    // Mohon: multiple boundary points (JSON). First point is mirrored to landmark_* for legacy views.
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

    // Validate required fields
    if (empty($plantation_name) || empty($tree_species) || $land_area <= 0 || empty($location_address)) {
        throw new Exception('All fields are required');
    }
    if ($age_of_plantation === null || $age_of_plantation < 0) {
        throw new Exception('Age of plantation is required (years, 0 or greater).');
    }
    if ($contact_person_name === '' || $contact_address === '' || $contact_phone === '') {
        throw new Exception('Contact name, address, and contact number are required');
    }
    $contact_digits = denr_normalize_contact_number($contact_phone);
    if ($contact_digits === null) {
        throw new Exception('Contact number must be digits only (7–15 digits). No letters.');
    }
    $contact_phone = $contact_digits;

    $mohon_count = 0;
    if ($mohon_points_json) {
        $tmp = json_decode($mohon_points_json, true);
        $mohon_count = is_array($tmp) ? count($tmp) : 0;
    }
    if (!$plantation_id && $mohon_count < 2) {
        throw new Exception('Define the plantation boundary: place at least two Mohon points on the map.');
    }

    $allowed_doc_types = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $allowed_image_types = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 5 * 1024 * 1024; // 5MB
    $upload_dir = '../assets/uploads/verification_documents/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    // Ensure columns exist (migrations may not have been run on hosting yet)
    try {
        $existingCols = $db->query('SHOW COLUMNS FROM plantations')->fetchAll(PDO::FETCH_COLUMN);
        $ensureCols = [
            'age_of_plantation' => 'ADD COLUMN age_of_plantation decimal(5,1) DEFAULT NULL',
            'tax_declaration_path' => 'ADD COLUMN tax_declaration_path VARCHAR(255) DEFAULT NULL',
            'site_photo_path' => 'ADD COLUMN site_photo_path VARCHAR(255) DEFAULT NULL',
            'rejection_reason' => 'ADD COLUMN rejection_reason VARCHAR(255) DEFAULT NULL',
        ];
        foreach ($ensureCols as $col => $ddl) {
            if (!in_array($col, $existingCols, true)) {
                $db->exec("ALTER TABLE plantations {$ddl}");
                $existingCols[] = $col;
            }
        }
        try {
            $db->exec("ALTER TABLE plantations MODIFY COLUMN status ENUM('pending','validated','verified','registered','rejected') DEFAULT 'pending'");
        } catch (Throwable $e) {
            // enum already includes rejected, or hosting restricts MODIFY
        }
    } catch (Throwable $e) {
        error_log('plantations column check: ' . $e->getMessage());
    }

    $saveUpload = function (array $file, array $allowed_types, string $label) use ($upload_dir, $max_size): string {
        if (!in_array($file['type'], $allowed_types, true)) {
            throw new Exception('Invalid file type for ' . $label . '.');
        }
        if ($file['size'] > $max_size) {
            throw new Exception($label . ' file size exceeds 5MB limit.');
        }
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = uniqid() . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $unique_filename;
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Failed to upload ' . $label . '.');
        }
        return 'assets/uploads/verification_documents/' . $unique_filename;
    };

    // Handle file uploads
    $verification_document = null;
    if (isset($_FILES['verification_document']) && $_FILES['verification_document']['error'] === UPLOAD_ERR_OK) {
        $verification_document = $saveUpload($_FILES['verification_document'], $allowed_doc_types, 'verification document');
    }

    $tax_declaration_path = null;
    if (isset($_FILES['tax_declaration']) && $_FILES['tax_declaration']['error'] === UPLOAD_ERR_OK) {
        $tax_declaration_path = $saveUpload($_FILES['tax_declaration'], $allowed_doc_types, 'Tax Declaration');
    }

    $site_photo_path = null;
    if (isset($_FILES['site_photo']) && $_FILES['site_photo']['error'] === UPLOAD_ERR_OK) {
        $site_photo_path = $saveUpload($_FILES['site_photo'], $allowed_image_types, 'site photo');
    }

    // New applications require Tax Declaration + site photo (+ location already validated above)
    if (!$plantation_id) {
        if (!$tax_declaration_path) {
            throw new Exception('Tax Declaration is required.');
        }
        if (!$site_photo_path) {
            throw new Exception('Picture of the site is required.');
        }
    }

    // If plantation_id exists, update existing record
    if ($plantation_id) {
        $query = "UPDATE plantations SET 
                    plantation_name = :plantation_name,
                    tree_species = :tree_species,
                    land_area = :land_area,
                    age_of_plantation = :age_of_plantation,
                    location_address = :location_address,
                    district = :district,
                    contact_person_name = :contact_person_name,
                    contact_address = :contact_address,
                    contact_phone = :contact_phone,
                    latitude = :latitude,
                    longitude = :longitude,
                    lot_number = :lot_number,
                    specifications = :specifications,
                    landmark_latitude = :landmark_latitude,
                    landmark_longitude = :landmark_longitude,
                    mohon_points_json = :mohon_points_json,
                    boundary_geojson = :boundary_geojson,
                    updated_at = NOW()";
        
        // Add uploaded docs to update if provided
        if ($verification_document) {
            $query .= ", verification_document = :verification_document";
        }
        if ($tax_declaration_path) {
            $query .= ", tax_declaration_path = :tax_declaration_path";
        }
        if ($site_photo_path) {
            $query .= ", site_photo_path = :site_photo_path";
        }
        
        $query .= " WHERE plantation_id = :plantation_id AND user_id = :user_id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':plantation_id', $plantation_id);
        $stmt->bindValue(':district', $district, $district === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':boundary_geojson', null, PDO::PARAM_NULL);
        
        if ($verification_document) {
            $stmt->bindParam(':verification_document', $verification_document);
        }
        if ($tax_declaration_path) {
            $stmt->bindParam(':tax_declaration_path', $tax_declaration_path);
        }
        if ($site_photo_path) {
            $stmt->bindParam(':site_photo_path', $site_photo_path);
        }
    } else {
        // Insert new record (specification of plantation, lot, landmark coordinates)
        $query = "INSERT INTO plantations (
                    user_id, plantation_name, tree_species, land_area, age_of_plantation,
                    location_address, district, contact_person_name, contact_address, contact_phone,
                    latitude, longitude, lot_number, specifications,
                    landmark_latitude, landmark_longitude, mohon_points_json, boundary_geojson,
                    verification_document, tax_declaration_path, site_photo_path, status, registered_at, applied_at
                ) VALUES (
                    :user_id, :plantation_name, :tree_species, :land_area, :age_of_plantation,
                    :location_address, :district, :contact_person_name, :contact_address, :contact_phone,
                    :latitude, :longitude, :lot_number, :specifications,
                    :landmark_latitude, :landmark_longitude, :mohon_points_json, :boundary_geojson,
                    :verification_document, :tax_declaration_path, :site_photo_path, 'pending', NOW(), NOW()
                )";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':verification_document', $verification_document);
        $stmt->bindParam(':tax_declaration_path', $tax_declaration_path);
        $stmt->bindParam(':site_photo_path', $site_photo_path);
    }

    // Bind parameters
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':plantation_name', $plantation_name);
    $stmt->bindParam(':tree_species', $tree_species);
    $stmt->bindParam(':land_area', $land_area);
    $stmt->bindValue(':age_of_plantation', $age_of_plantation);
    $stmt->bindParam(':location_address', $location_address);
    if (!isset($plantation_id) || !$plantation_id) {
        $stmt->bindValue(':district', $district, $district === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':boundary_geojson', null, PDO::PARAM_NULL);
    }
    $stmt->bindParam(':contact_person_name', $contact_person_name);
    $stmt->bindParam(':contact_address', $contact_address);
    $stmt->bindParam(':contact_phone', $contact_phone);
    $stmt->bindParam(':latitude', $latitude);
    $stmt->bindParam(':longitude', $longitude);
    $stmt->bindParam(':lot_number', $lot_number);
    $stmt->bindParam(':specifications', $specifications);
    $stmt->bindValue(':landmark_latitude', $landmark_latitude, $landmark_latitude === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':landmark_longitude', $landmark_longitude, $landmark_longitude === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':mohon_points_json', $mohon_points_json, $mohon_points_json === null ? PDO::PARAM_NULL : PDO::PARAM_STR);

    if ($stmt->execute()) {
        $new_id = $plantation_id ? (int) $plantation_id : (int) $db->lastInsertId();
        if (!$plantation_id) {
            try {
                $pal = $db->prepare('INSERT INTO plantation_activity_log (plantation_id, actor_user_id, action, old_status, new_status, detail) VALUES (?, ?, ?, NULL, ?, ?)');
                $pal->execute([$new_id, (int) $user_id, 'application', 'pending', 'Application submitted']);
            } catch (Throwable $e) {
                error_log('plantation_activity_log submit: ' . $e->getMessage());
            }

            $owner_label = isset($_SESSION['full_name']) ? trim((string) $_SESSION['full_name']) : 'Landowner';
            if ($owner_label === '') {
                $owner_label = 'Landowner #' . (int) $user_id;
            }
            denr_notify_staff(
                $db,
                'New plantation application pending: "' . $plantation_name . '" submitted by ' . $owner_label . '.'
            );
            denr_notify_user(
                $db,
                (int) $user_id,
                'Your plantation application "' . $plantation_name . '" has been submitted and is pending review.'
            );
        }
        echo json_encode([
            'status' => 'success',
            'message' => ($plantation_id ? 'Plantation updated successfully' : 'Plantation registered successfully'),
            'plantation_id' => $new_id
        ]);
    } else {
        throw new Exception('Failed to save plantation');
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}