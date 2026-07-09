<?php
/**
 * Geocode proxy to avoid CORS when calling Nominatim/Photon from the browser.
 * Tries Nominatim first; on failure (502, timeout, etc.) falls back to Photon.
 * Nominatim: https://operations.osmfoundation.org/policies/nominatim/
 * Photon: https://photon.komoot.io/
 */
session_start();
// Optional: restrict to logged-in users if you prefer
// if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit; }

header('Content-Type: application/json; charset=utf-8');

$action = isset($_GET['action']) ? $_GET['action'] : '';
$allowed = ['reverse', 'search'];
if (!in_array($action, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action. Use action=reverse or action=search']);
    exit;
}

/**
 * Fetch URL with cURL (timeout, User-Agent). Returns [body, http_code] or [false, 0].
 */
function fetchUrl($url, $userAgent, $timeout = 10) {
    if (!function_exists('curl_init')) {
        return [false, 0];
    }
    $ch = curl_init($url);
    if (!$ch) {
        return [false, 0];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 2,
        CURLOPT_TIMEOUT => (int) $timeout,
        CURLOPT_HTTPHEADER => [
            'User-Agent: ' . $userAgent,
            'Accept-Language: en'
        ]
    ]);
    $body = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$body, $httpCode];
}

/**
 * Build display_name from Photon feature properties.
 */
function photonDisplayName($props) {
    $street = $props['street'] ?? '';
    $housenumber = $props['housenumber'] ?? '';
    $streetPart = trim($street . ' ' . $housenumber);
    $parts = array_filter([
        $props['name'] ?? null,
        $streetPart ?: null,
        $props['postcode'] ?? null,
        $props['city'] ?? $props['town'] ?? $props['village'] ?? null,
        $props['state'] ?? null,
        $props['country'] ?? null
    ]);
    return implode(', ', $parts) ?: 'Unknown';
}

/**
 * Convert Photon search response to Nominatim-like array [{ lat, lon, display_name }].
 */
function photonSearchToNominatim($json) {
    $data = json_decode($json, true);
    if (!isset($data['features']) || !is_array($data['features'])) {
        return [];
    }
    $out = [];
    foreach ($data['features'] as $f) {
        $coords = $f['geometry']['coordinates'] ?? null;
        if (!$coords || count($coords) < 2) {
            continue;
        }
        $out[] = [
            'lat' => (string) $coords[1],
            'lon' => (string) $coords[0],
            'display_name' => photonDisplayName($f['properties'] ?? [])
        ];
    }
    return $out;
}

/**
 * Convert Photon reverse response to Nominatim-like object { display_name }.
 */
function photonReverseToNominatim($json) {
    $data = json_decode($json, true);
    if (!isset($data['features'][0])) {
        return ['display_name' => ''];
    }
    $props = $data['features'][0]['properties'] ?? [];
    return ['display_name' => photonDisplayName($props)];
}

$userAgent = 'DENR-Plantation-App/1.0 (contact@example.com)';
$timeout = 10;

if ($action === 'reverse') {
    $lat = isset($_GET['lat']) ? trim($_GET['lat']) : '';
    $lon = isset($_GET['lon']) ? trim($_GET['lon']) : '';
    if ($lat === '' || $lon === '' || !is_numeric($lat) || !is_numeric($lon)) {
        http_response_code(400);
        echo json_encode(['error' => 'lat and lon required']);
        exit;
    }

    $nominatimUrl = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' . urlencode($lat) . '&lon=' . urlencode($lon);
    list($result, $code) = fetchUrl($nominatimUrl, $userAgent, $timeout);

    if ($result !== false && $code >= 200 && $code < 300) {
        echo $result;
        exit;
    }

    $photonUrl = 'https://photon.komoot.io/reverse?lat=' . urlencode($lat) . '&lon=' . urlencode($lon);
    list($photonResult, $photonCode) = fetchUrl($photonUrl, $userAgent, $timeout);
    if ($photonResult !== false && $photonCode >= 200 && $photonCode < 300) {
        echo json_encode(photonReverseToNominatim($photonResult));
        exit;
    }
} else {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    if ($q === '') {
        http_response_code(400);
        echo json_encode(['error' => 'q required for search']);
        exit;
    }

    $nominatimUrl = 'https://nominatim.openstreetmap.org/search?format=json&q=' . urlencode($q);
    list($result, $code) = fetchUrl($nominatimUrl, $userAgent, $timeout);

    if ($result !== false && $code >= 200 && $code < 300) {
        $decoded = json_decode($result, true);
        if (is_array($decoded)) {
            echo $result;
            exit;
        }
    }

    $photonUrl = 'https://photon.komoot.io/api/?q=' . urlencode($q) . '&limit=5';
    list($photonResult, $photonCode) = fetchUrl($photonUrl, $userAgent, $timeout);
    if ($photonResult !== false && $photonCode >= 200 && $photonCode < 300) {
        $converted = photonSearchToNominatim($photonResult);
        echo json_encode($converted);
        exit;
    }
}

http_response_code(502);
echo json_encode(['error' => 'Geocoding service unavailable. Please try again later.']);
