<?php
/**
 * Admin: register a plantation on behalf of a landowner.
 * Landowner: dropdown user_id and/or landowner_lookup (full name or email).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/contact_utils.php';
require_once __DIR__ . '/../config/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $target_user_id = (int) ($_POST['landowner_user_id'] ?? 0);
    $lookup = trim($_POST['landowner_lookup'] ?? '');

    if ($target_user_id > 0) {
        $u = $db->prepare('SELECT user_id FROM users WHERE user_id = ? AND role = ?');
        $u->execute([$target_user_id, 'landowner']);
        if ($u->rowCount() === 0) {
            throw new Exception('Invalid landowner selected');
        }
    } elseif ($lookup !== '') {
        $st = $db->prepare('SELECT user_id FROM users WHERE role = ? AND LOWER(TRIM(email)) = LOWER(?) LIMIT 3');
        $st->execute(['landowner', $lookup]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 1) {
            $target_user_id = (int) $rows[0]['user_id'];
        } elseif (count($rows) > 1) {
            throw new Exception('Multiple accounts share that email. Use the dropdown.');
        } else {
            $st2 = $db->prepare('SELECT user_id FROM users WHERE role = ? AND LOWER(TRIM(full_name)) = LOWER(?) LIMIT 3');
            $st2->execute(['landowner', $lookup]);
            $rows2 = $st2->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows2) === 1) {
                $target_user_id = (int) $rows2[0]['user_id'];
            } elseif (count($rows2) > 1) {
                throw new Exception('Multiple landowners have that exact name. Type their email or use the dropdown.');
            } else {
                $like = '%' . $lookup . '%';
                $st3 = $db->prepare('SELECT user_id, full_name, email FROM users WHERE role = ? AND (full_name LIKE ? OR email LIKE ?) LIMIT 5');
                $st3->execute(['landowner', $like, $like]);
                $rows3 = $st3->fetchAll(PDO::FETCH_ASSOC);
                if (count($rows3) === 1) {
                    $target_user_id = (int) $rows3[0]['user_id'];
                } elseif (count($rows3) > 1) {
                    throw new Exception('Multiple landowners match that text. Use the dropdown or type the exact email.');
                } else {
                    throw new Exception('No landowner found for that name or email.');
                }
            }
        }
    } else {
        throw new Exception('Select a landowner from the list or type their full name or email.');
    }

    if ($target_user_id <= 0) {
        throw new Exception('Could not resolve landowner account.');
    }

    $plantation_name = trim($_POST['plantation_name'] ?? '');
    $tree_species_raw = $_POST['tree_species'] ?? '';
    if (is_array($tree_species_raw)) {
        $tree_species = trim(implode(',', $tree_species_raw));
    } else {
        $tree_species = trim((string) $tree_species_raw);
    }
    $land_area = floatval($_POST['land_area'] ?? 0);
    $age_of_plantation = isset($_POST['age_of_plantation']) && $_POST['age_of_plantation'] !== ''
        ? floatval($_POST['age_of_plantation'])
        : null;
    $location_address = trim($_POST['location_address'] ?? '');
    $district = trim($_POST['district'] ?? '');
    if ($district === '') {
        $district = null;
    }
    $contact_person_name = trim($_POST['contact_person_name'] ?? '');
    $contact_address = trim($_POST['contact_address'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);

    if ($plantation_name === '' || $tree_species === '' || $land_area <= 0 || $location_address === '') {
        throw new Exception('Plantation name, species, land area, and address are required');
    }
    if ($age_of_plantation === null || $age_of_plantation < 0) {
        throw new Exception('Age of plantation is required (years, 0 or greater).');
    }
    if ($contact_person_name === '' || $contact_address === '' || $contact_phone === '') {
        throw new Exception('Contact name, address, and phone are required');
    }
    $cd = denr_normalize_contact_number($contact_phone);
    if ($cd === null) {
        throw new Exception('Contact phone must be digits only (7–15 digits)');
    }
    $contact_phone = $cd;

    if (!$latitude || !$longitude) {
        throw new Exception('Set the lot location on the map');
    }

    $mohon_points_json = null;
    $landmark_latitude = $latitude;
    $landmark_longitude = $longitude;
    if (!empty($_POST['mohon_points_json'])) {
        $decoded = json_decode($_POST['mohon_points_json'], true);
        if (is_array($decoded) && count($decoded) > 0) {
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
        $mohon_points_json = json_encode([['lat' => $latitude, 'lng' => $longitude]]);
    }

    $mohon_count = 0;
    if ($mohon_points_json) {
        $tmp = json_decode($mohon_points_json, true);
        $mohon_count = is_array($tmp) ? count($tmp) : 0;
    }
    if ($mohon_count < 2) {
        throw new Exception('Define the boundary: place at least two Mohon points on the map.');
    }

    $verification_document = null;

    $query = "INSERT INTO plantations (
        user_id, plantation_name, tree_species, land_area, age_of_plantation,
        location_address, district, contact_person_name, contact_address, contact_phone,
        latitude, longitude, lot_number, specifications,
        landmark_latitude, landmark_longitude, mohon_points_json, boundary_geojson, verification_document, status, registered_at, applied_at
    ) VALUES (
        :user_id, :plantation_name, :tree_species, :land_area, :age_of_plantation,
        :location_address, :district, :contact_person_name, :contact_address, :contact_phone,
        :latitude, :longitude, NULL, NULL,
        :landmark_latitude, :landmark_longitude, :mohon_points_json, :boundary_geojson, :verification_document, 'pending', NOW(), NOW()
    )";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':user_id', $target_user_id, PDO::PARAM_INT);
    $stmt->bindParam(':plantation_name', $plantation_name);
    $stmt->bindParam(':tree_species', $tree_species);
    $stmt->bindParam(':land_area', $land_area);
    $stmt->bindValue(':age_of_plantation', $age_of_plantation);
    $stmt->bindParam(':location_address', $location_address);
    $stmt->bindValue(':district', $district, $district === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindParam(':contact_person_name', $contact_person_name);
    $stmt->bindParam(':contact_address', $contact_address);
    $stmt->bindParam(':contact_phone', $contact_phone);
    $stmt->bindParam(':latitude', $latitude);
    $stmt->bindParam(':longitude', $longitude);
    $stmt->bindParam(':landmark_latitude', $landmark_latitude);
    $stmt->bindParam(':landmark_longitude', $landmark_longitude);
    $stmt->bindValue(':mohon_points_json', $mohon_points_json, PDO::PARAM_STR);
    $stmt->bindValue(':boundary_geojson', null, PDO::PARAM_NULL);
    $stmt->bindValue(':verification_document', $verification_document, PDO::PARAM_NULL);

    if (!$stmt->execute()) {
        throw new Exception('Could not save plantation');
    }

    $new_id = (int) $db->lastInsertId();
    try {
        $pal = $db->prepare('INSERT INTO plantation_activity_log (plantation_id, actor_user_id, action, old_status, new_status, detail) VALUES (?, ?, ?, NULL, ?, ?)');
        $pal->execute([$new_id, (int) $_SESSION['user_id'], 'application', 'pending', 'Admin registered plantation']);
    } catch (Throwable $e) {
        error_log('admin_add_plantation log: ' . $e->getMessage());
    }

    denr_notify_user(
        $db,
        $target_user_id,
        'A plantation "' . $plantation_name . '" was registered on your account by DENR staff and is pending review.'
    );
    denr_notify_verifiers(
        $db,
        'New plantation pending review: "' . $plantation_name . '" (registered by admin).'
    );

    echo json_encode(['status' => 'success', 'message' => 'Plantation created', 'plantation_id' => $new_id]);
} catch (Throwable $e) {
    error_log('admin_add_plantation: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
