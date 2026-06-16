<?php
$session_started = session_start();
header('Content-Type: application/json');

require_once '../includes/db_config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
if (!$action && isset($data['action'])) {
    $action = $data['action'];
}

// Allow unauthenticated access only to the save_capture action (from local processor)
if ($action !== 'save_capture') {
    if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] ?? null) != 1) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
        exit();
    }
    $user_id = (int)$_SESSION['user_id'];
} else {
    // Processor posts captures without a session; set user_id to 0
    $user_id = 0;
}

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
    exit();
}

function normalize_image_url(?string $path): string
{
    if (empty($path)) {
        return '';
    }

    if (preg_match('/^https?:\/\//i', $path)) {
        return $path;
    }

    return BASE_URL . ltrim($path, '/');
}

function fetch_accessible_buildings(mysqli $conn, int $user_id): array
{
    $buildings = [];
    $queries = [
        "SELECT DISTINCT a.building_id FROM apartment_assignments aa JOIN apartments a ON a.id = aa.apt_id WHERE aa.user_id = $user_id AND aa.role = 'owner' AND aa.is_active = 1 AND a.building_id IS NOT NULL",
        "SELECT DISTINCT a.building_id FROM rental_listings rl JOIN apartments a ON a.id = rl.apt_id WHERE rl.owner_id = $user_id AND a.building_id IS NOT NULL",
        "SELECT DISTINCT building_id FROM apartments WHERE building_id IS NOT NULL"
    ];

    foreach ($queries as $query) {
        $result = mysqli_query($conn, $query);
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $building_id = trim((string)($row['building_id'] ?? ''));
                if ($building_id !== '') {
                    $buildings[$building_id] = [
                        'id' => $building_id,
                        'label' => 'Building ' . $building_id
                    ];
                }
            }
        }
        if (!empty($buildings) && $query !== end($queries)) {
            break;
        }
    }

    return array_values($buildings);
}

function fetch_dashboard_data(mysqli $conn, string $building_id): array
{
    $safe_building_id = mysqli_real_escape_string($conn, $building_id);

    $building_row = null;
    $building_query = mysqli_query($conn, "SELECT building_id FROM apartments WHERE building_id = '$safe_building_id' LIMIT 1");
    if ($building_query && mysqli_num_rows($building_query) > 0) {
        $building_row = mysqli_fetch_assoc($building_query);
    }

    $devices = [];
    $devices_query = "
        SELECT
            d.id,
            d.building_id,
            d.camera_name,
            d.ip_address,
            d.location_description,
            d.status,
            d.created_at,
            lc.id AS last_capture_id,
            lc.image_path AS last_image_path,
            lc.detection_type AS last_detection_type,
            lc.captured_at AS last_captured_at,
            lc.matched_confidence AS last_confidence,
            lc.user_id AS last_user_id,
            COALESCE(up.full_name, '') AS resident_name
        FROM cctv_devices d
        LEFT JOIN (
            SELECT c1.*
            FROM cctv_captures c1
            INNER JOIN (
                SELECT camera_id, MAX(captured_at) AS max_captured_at
                FROM cctv_captures
                GROUP BY camera_id
            ) latest ON latest.camera_id = c1.camera_id AND latest.max_captured_at = c1.captured_at
        ) lc ON lc.camera_id = d.id
        LEFT JOIN user_profiles up ON up.user_id = lc.user_id
        WHERE d.building_id = '$safe_building_id'
        ORDER BY d.created_at DESC, d.id DESC
    ";
    $devices_result = mysqli_query($conn, $devices_query);
    if ($devices_result) {
        while ($row = mysqli_fetch_assoc($devices_result)) {
            $devices[] = [
                'id' => (int)$row['id'],
                'building_id' => $row['building_id'],
                'camera_name' => $row['camera_name'],
                'ip_address' => $row['ip_address'],
                'location_description' => $row['location_description'] ?? '',
                'status' => $row['status'] ?? 'inactive',
                'created_at' => $row['created_at'],
                'last_capture' => [
                    'id' => isset($row['last_capture_id']) ? (int)$row['last_capture_id'] : null,
                    'image_url' => normalize_image_url($row['last_image_path'] ?? ''),
                    'detection_type' => $row['last_detection_type'] ?? 'motion',
                    'captured_at' => $row['last_captured_at'] ?? null,
                    'confidence' => isset($row['last_confidence']) ? (float)$row['last_confidence'] : null,
                    'resident_name' => $row['resident_name'] ?? ''
                ]
            ];
        }
    }

    $captures = [];
    $captures_query = "
        SELECT
            c.id,
            c.camera_id,
            c.apt_id,
            c.user_id,
            c.image_path,
            c.detection_type,
            c.matched_confidence,
            c.captured_at,
            c.is_reviewed,
            d.camera_name,
            d.location_description,
            COALESCE(up.full_name, '') AS resident_name
        FROM cctv_captures c
        LEFT JOIN cctv_devices d ON d.id = c.camera_id
        LEFT JOIN user_profiles up ON up.user_id = c.user_id
        WHERE d.building_id = '$safe_building_id'
        ORDER BY c.captured_at DESC
        LIMIT 40
    ";
    $captures_result = mysqli_query($conn, $captures_query);
    if ($captures_result) {
        while ($row = mysqli_fetch_assoc($captures_result)) {
            $captures[] = [
                'id' => (int)$row['id'],
                'camera_id' => (int)$row['camera_id'],
                'camera_name' => $row['camera_name'] ?? 'Camera',
                'location_description' => $row['location_description'] ?? '',
                'image_url' => normalize_image_url($row['image_path'] ?? ''),
                'detection_type' => $row['detection_type'] ?? 'motion',
                'matched_confidence' => isset($row['matched_confidence']) ? (float)$row['matched_confidence'] : null,
                'captured_at' => $row['captured_at'],
                'is_reviewed' => (bool)($row['is_reviewed'] ?? 0),
                'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
                'resident_name' => $row['resident_name'] ?? ''
            ];
        }
    }

    $alerts = [];
    $alerts_query = "
        SELECT
            a.id,
            a.capture_id,
            a.building_id,
            a.alert_type,
            a.message,
            a.is_sent,
            a.sent_at,
            a.created_at,
            c.image_path,
            c.detection_type,
            d.camera_name,
            d.location_description
        FROM cctv_alerts a
        LEFT JOIN cctv_captures c ON c.id = a.capture_id
        LEFT JOIN cctv_devices d ON d.id = c.camera_id
        WHERE a.building_id = '$safe_building_id'
        ORDER BY a.created_at DESC
        LIMIT 8
    ";
    $alerts_result = mysqli_query($conn, $alerts_query);
    $unread_count = 0;
    if ($alerts_result) {
        while ($row = mysqli_fetch_assoc($alerts_result)) {
            if ((int)$row['is_sent'] === 0) {
                $unread_count++;
            }

            $alerts[] = [
                'id' => (int)$row['id'],
                'capture_id' => (int)$row['capture_id'],
                'camera_name' => $row['camera_name'] ?? 'Camera',
                'location_description' => $row['location_description'] ?? '',
                'alert_type' => $row['alert_type'] ?? 'unknown_face',
                'message' => $row['message'],
                'is_sent' => (bool)$row['is_sent'],
                'created_at' => $row['created_at'],
                'sent_at' => $row['sent_at']
            ];
        }
    }

    return [
        'building_id' => $building_id,
        'building_label' => 'Building ' . $building_id,
        'devices' => $devices,
        'captures' => $captures,
        'alerts' => $alerts,
        'unread_count' => $unread_count,
        'camera_count' => count($devices),
        'building_exists' => (bool)$building_row
    ];
}

switch ($action) {
    case 'get_dashboard':
        $buildings = fetch_accessible_buildings($conn, $user_id);
        $requested_building = trim((string)($_GET['building_id'] ?? ($data['building_id'] ?? '')));

        if (empty($buildings)) {
            echo json_encode([
                'status' => 'success',
                'buildings' => [],
                'building_id' => '',
                'building_label' => '',
                'devices' => [],
                'captures' => [],
                'alerts' => [],
                'unread_count' => 0,
                'camera_count' => 0
            ]);
            exit();
        }

        $building_ids = array_column($buildings, 'id');
        if ($requested_building === '' || !in_array($requested_building, $building_ids, true)) {
            $requested_building = $buildings[0]['id'];
        }

        $payload = fetch_dashboard_data($conn, $requested_building);
        $payload['buildings'] = $buildings;
        $payload['status'] = 'success';

        echo json_encode($payload);
        break;

    case 'get_capture_history':
        $building_id = trim((string)($_GET['building_id'] ?? ($data['building_id'] ?? '')));
        $capture_id = (int)($_GET['capture_id'] ?? ($data['capture_id'] ?? 0));

        if ($building_id === '' || $capture_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Missing building or capture id']);
            exit();
        }

        $safe_building_id = mysqli_real_escape_string($conn, $building_id);
        $capture_sql = "
            SELECT
                c.id,
                c.camera_id,
                c.apt_id,
                c.user_id,
                c.image_path,
                c.detection_type,
                c.matched_confidence,
                c.captured_at,
                d.camera_name,
                d.location_description,
                COALESCE(up.full_name, '') AS resident_name
            FROM cctv_captures c
            LEFT JOIN cctv_devices d ON d.id = c.camera_id
            LEFT JOIN user_profiles up ON up.user_id = c.user_id
            WHERE d.building_id = '$safe_building_id' AND c.id = $capture_id
            LIMIT 1
        ";
        $capture_result = mysqli_query($conn, $capture_sql);
        if (!$capture_result || mysqli_num_rows($capture_result) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Capture not found']);
            exit();
        }

        $capture = mysqli_fetch_assoc($capture_result);
        $person_capture_key = !empty($capture['user_id']) ? ('user:' . (int)$capture['user_id']) : ('capture:' . (int)$capture['id']);

        $history = [];
        if (!empty($capture['user_id'])) {
            $user_id_int = (int)$capture['user_id'];
            $history_sql = "
                SELECT
                    c.id,
                    c.camera_id,
                    c.captured_at,
                    c.detection_type,
                    c.matched_confidence,
                    d.camera_name,
                    d.location_description
                FROM cctv_captures c
                LEFT JOIN cctv_devices d ON d.id = c.camera_id
                WHERE d.building_id = '$safe_building_id' AND c.user_id = $user_id_int
                ORDER BY c.captured_at DESC
                LIMIT 30
            ";
            $history_result = mysqli_query($conn, $history_sql);
            if ($history_result) {
                while ($row = mysqli_fetch_assoc($history_result)) {
                    $history[] = [
                        'id' => (int)$row['id'],
                        'camera_id' => (int)$row['camera_id'],
                        'camera_name' => $row['camera_name'] ?? 'Camera',
                        'location_description' => $row['location_description'] ?? '',
                        'captured_at' => $row['captured_at'],
                        'detection_type' => $row['detection_type'] ?? 'face',
                        'matched_confidence' => isset($row['matched_confidence']) ? (float)$row['matched_confidence'] : null
                    ];
                }
            }
        } else {
            $history[] = [
                'id' => (int)$capture['id'],
                'camera_id' => (int)$capture['camera_id'],
                'camera_name' => $capture['camera_name'] ?? 'Camera',
                'location_description' => $capture['location_description'] ?? '',
                'captured_at' => $capture['captured_at'],
                'detection_type' => $capture['detection_type'] ?? 'unknown',
                'matched_confidence' => isset($capture['matched_confidence']) ? (float)$capture['matched_confidence'] : null
            ];
        }

        echo json_encode([
            'status' => 'success',
            'capture' => [
                'id' => (int)$capture['id'],
                'camera_id' => (int)$capture['camera_id'],
                'camera_name' => $capture['camera_name'] ?? 'Camera',
                'location_description' => $capture['location_description'] ?? '',
                'captured_at' => $capture['captured_at'],
                'detection_type' => $capture['detection_type'] ?? 'unknown',
                'matched_confidence' => isset($capture['matched_confidence']) ? (float)$capture['matched_confidence'] : null,
                'user_id' => !empty($capture['user_id']) ? (int)$capture['user_id'] : null,
                'resident_name' => $capture['resident_name'] ?? '',
                'image_url' => normalize_image_url($capture['image_path'] ?? ''),
                'person_capture_key' => $person_capture_key
            ],
            'history' => $history
        ]);
        break;

    case 'save_capture':
        $camera_id = (int)($data['camera_id'] ?? 0);
        $building_id = trim((string)($data['building_id'] ?? ''));
        $image_path = trim((string)($data['image_path'] ?? ''));
        $thumbnail_path = trim((string)($data['thumbnail_path'] ?? ''));
        $detection_type = trim((string)($data['detection_type'] ?? 'motion'));
        $user_id_capture = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $apt_id = isset($data['apt_id']) ? (int)$data['apt_id'] : null;
        $matched_confidence = isset($data['matched_confidence']) ? (float)$data['matched_confidence'] : null;
        $existing_capture_id = isset($data['existing_capture_id']) ? (int)$data['existing_capture_id'] : null;
        $face_hash = isset($data['face_hash']) ? trim((string)$data['face_hash']) : null;

        $camera_check = mysqli_query($conn, 
            "SELECT id FROM cctv_devices WHERE id = $camera_id AND building_id = '" . 
            mysqli_real_escape_string($conn, $building_id) . "' LIMIT 1"
        );

        if (!$camera_check || mysqli_num_rows($camera_check) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid camera or building']);
            exit();
        }

        $valid_types = ['motion', 'face', 'unknown', 'intruder'];
        if (!in_array($detection_type, $valid_types)) {
            $detection_type = 'motion';
        }

        @mysqli_query($conn, "ALTER TABLE cctv_captures ADD COLUMN IF NOT EXISTS face_hash VARCHAR(64) DEFAULT NULL;");

        // Fallback: If python didn't provide an existing ID, try to find an exact hash match within the last 1 day
        if (!$existing_capture_id && !empty($face_hash)) {
            $hash_query = "SELECT id FROM cctv_captures WHERE face_hash = '" . mysqli_real_escape_string($conn, $face_hash) . "' AND camera_id = $camera_id AND captured_at >= NOW() - INTERVAL 1 DAY ORDER BY captured_at DESC LIMIT 1";
            $res = mysqli_query($conn, $hash_query);
            if ($res && mysqli_num_rows($res) > 0) {
                $existing_capture_id = (int)mysqli_fetch_assoc($res)['id'];
            }
        }

        // UPDATE Logic: Replaces the existing image physically and updates the DB
        if ($existing_capture_id) {
            // Retrieve old image path to delete it and free up storage
            $old_paths_query = "SELECT image_path FROM cctv_captures WHERE id = $existing_capture_id";
            $old_paths_res = mysqli_query($conn, $old_paths_query);
            
            if ($old_paths_res && mysqli_num_rows($old_paths_res) > 0) {
                $old_data = mysqli_fetch_assoc($old_paths_res);
                $old_image = '../' . ltrim($old_data['image_path'], '/');

                if (!empty($old_data['image_path']) && file_exists($old_image) && is_file($old_image)) {
                    @unlink($old_image); // Physically delete old image
                }
            }

            $update_query = "UPDATE cctv_captures SET captured_at = NOW(), image_path = '" . mysqli_real_escape_string($conn, $image_path) . "', is_reviewed = 0, face_hash = '" . mysqli_real_escape_string($conn, $face_hash) . "' WHERE id = $existing_capture_id";
            if (!mysqli_query($conn, $update_query)) {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update capture']);
                exit();
            }
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Capture updated successfully',
                'capture_id' => $existing_capture_id,
                'detection_type' => $detection_type,
                'user_id' => $user_id_capture
            ]);
            break;
        }

        // INSERT Logic: First time seeing this face
        $insert_capture = "
            INSERT INTO cctv_captures 
            (camera_id, apt_id, user_id, image_path, detection_type, matched_confidence, captured_at, is_reviewed" . (empty($face_hash) ? '' : ', face_hash') . ")
            VALUES 
            ($camera_id, " .
            ($apt_id ? $apt_id : "NULL") . ", " .
            ($user_id_capture ? $user_id_capture : "NULL") . ", 
            '" . mysqli_real_escape_string($conn, $image_path) . "',
            '$detection_type',
            " . ($matched_confidence ? $matched_confidence : "NULL") . ",
            NOW(),
            0" . (empty($face_hash) ? '' : ", '" . mysqli_real_escape_string($conn, $face_hash) . "'") . ")
        ";

        if (!mysqli_query($conn, $insert_capture)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to save capture: ' . mysqli_error($conn)
            ]);
            exit();
        }

        $capture_id = mysqli_insert_id($conn);

        if ($detection_type === 'unknown' || $detection_type === 'motion') {
            $device_info = mysqli_fetch_assoc(mysqli_query($conn, 
                "SELECT camera_name, location_description FROM cctv_devices WHERE id = $camera_id"
            ));

            $camera_name = $device_info['camera_name'] ?? 'Camera';
            $location = $device_info['location_description'] ?? 'Unknown location';
            
            $alert_type = $detection_type === 'unknown' ? 'unknown_face' : 'motion_detected';
            $alert_message = $detection_type === 'unknown' 
                ? "Unknown person detected at $camera_name ($location)"
                : "Motion detected at $camera_name ($location)";

            $insert_alert = "
                INSERT INTO cctv_alerts 
                (capture_id, building_id, alert_type, message, is_sent, created_at)
                VALUES 
                ($capture_id, '" . mysqli_real_escape_string($conn, $building_id) . "', 
                '$alert_type', 
                '" . mysqli_real_escape_string($conn, $alert_message) . "',
                0,
                NOW())
            ";

            mysqli_query($conn, $insert_alert);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Capture saved successfully',
            'capture_id' => $capture_id,
            'detection_type' => $detection_type,
            'user_id' => $user_id_capture
        ]);
        break;

    case 'add_device':
        // .. (Rest of API code unmodified)
        $building_id = trim((string)($_POST['building_id'] ?? ($data['building_id'] ?? '')));
        $camera_name = trim((string)($_POST['camera_name'] ?? ($data['camera_name'] ?? '')));
        $ip_address = trim((string)($_POST['ip_address'] ?? ($data['ip_address'] ?? '')));
        $location_description = trim((string)($_POST['location_description'] ?? ($data['location_description'] ?? '')));
        $status = trim((string)($_POST['status'] ?? ($data['status'] ?? 'active')));

        if (empty($camera_name) || empty($ip_address)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
            exit();
        }

        if (!empty($building_id)) {
            $building_check = mysqli_query($conn, "SELECT building_id FROM apartments WHERE building_id = '" . mysqli_real_escape_string($conn, $building_id) . "' LIMIT 1");
            if (!$building_check || mysqli_num_rows($building_check) === 0) {
                echo json_encode(['status' => 'error', 'message' => 'Building not found']);
                exit();
            }
        }

        $valid_statuses = ['active', 'inactive', 'maintenance'];
        if (!in_array($status, $valid_statuses)) { $status = 'active'; }

        $building_val = empty($building_id) ? "NULL" : "'" . mysqli_real_escape_string($conn, $building_id) . "'";

        $insert_device = "INSERT INTO cctv_devices (building_id, camera_name, ip_address, location_description, status, created_at) VALUES ($building_val, '" . mysqli_real_escape_string($conn, $camera_name) . "', '" . mysqli_real_escape_string($conn, $ip_address) . "', '" . mysqli_real_escape_string($conn, $location_description) . "', '$status', NOW())";

        if (!mysqli_query($conn, $insert_device)) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add device']);
            exit();
        }

        $device_id = mysqli_insert_id($conn);
        $payload = fetch_dashboard_data($conn, $building_id);
        $payload['status'] = 'success';
        $payload['message'] = 'Camera added successfully';
        $payload['device_id'] = $device_id;
        echo json_encode($payload);
        break;

    case 'delete_device':
        $device_id = (int)($_POST['device_id'] ?? ($data['device_id'] ?? 0));
        $building_id = trim((string)($_POST['building_id'] ?? ($data['building_id'] ?? '')));

        if ($device_id <= 0 || empty($building_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
            exit();
        }

        $device_check = mysqli_query($conn, "SELECT id FROM cctv_devices WHERE id = $device_id AND building_id = '" . mysqli_real_escape_string($conn, $building_id) . "' LIMIT 1");

        if (!$device_check || mysqli_num_rows($device_check) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Device not found']);
            exit();
        }

        // Remove related alerts first to avoid foreign key constraint errors
        mysqli_query($conn, "DELETE FROM cctv_alerts WHERE capture_id IN (SELECT id FROM cctv_captures WHERE camera_id = $device_id)");
        
        // Remove related captures next
        mysqli_query($conn, "DELETE FROM cctv_captures WHERE camera_id = $device_id");

        if (!mysqli_query($conn, "DELETE FROM cctv_devices WHERE id = $device_id")) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete: ' . mysqli_error($conn)]);
            exit();
        }

        $payload = fetch_dashboard_data($conn, $building_id);
        $payload['status'] = 'success';
        echo json_encode($payload);
        break;

    case 'delete_capture':
        $capture_id = (int)($_POST['capture_id'] ?? ($data['capture_id'] ?? 0));
        $building_id = trim((string)($_POST['building_id'] ?? ($data['building_id'] ?? '')));

        if ($capture_id <= 0 || empty($building_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
            exit();
        }

        $capture_check = mysqli_query($conn, "SELECT c.image_path FROM cctv_captures c JOIN cctv_devices d ON c.camera_id = d.id WHERE c.id = $capture_id AND d.building_id = '" . mysqli_real_escape_string($conn, $building_id) . "' LIMIT 1");
        if (!$capture_check || mysqli_num_rows($capture_check) === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Capture not found']);
            exit();
        }

        $capture_data = mysqli_fetch_assoc($capture_check);
        $old_image = '../' . ltrim($capture_data['image_path'], '/');
        
        // Remove related alerts before deleting capture
        mysqli_query($conn, "DELETE FROM cctv_alerts WHERE capture_id = $capture_id");
        
        if (!mysqli_query($conn, "DELETE FROM cctv_captures WHERE id = $capture_id")) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete capture']);
            exit();
        }
        
        // Physically delete old image logic
        if (!empty($capture_data['image_path']) && file_exists($old_image) && is_file($old_image)) {
            @unlink($old_image);
        }

        $payload = fetch_dashboard_data($conn, $building_id);
        $payload['status'] = 'success';
        echo json_encode($payload);
        break;

    case 'delete_all_captures':
        $building_id = trim((string)($_POST['building_id'] ?? ($data['building_id'] ?? '')));

        if (empty($building_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing fields']);
            exit();
        }
        
        $safe_building_id = mysqli_real_escape_string($conn, $building_id);

        $capture_check = mysqli_query($conn, "SELECT c.image_path FROM cctv_captures c JOIN cctv_devices d ON c.camera_id = d.id WHERE d.building_id = '$safe_building_id'");
        if ($capture_check && mysqli_num_rows($capture_check) > 0) {
            while ($capture_data = mysqli_fetch_assoc($capture_check)) {
                $old_image = '../' . ltrim($capture_data['image_path'], '/');
                if (!empty($capture_data['image_path']) && file_exists($old_image) && is_file($old_image)) {
                    @unlink($old_image);
                }
            }
        }

        // Remove related alerts before deleting captures
        mysqli_query($conn, "DELETE FROM cctv_alerts WHERE building_id = '$safe_building_id'");
        
        if (!mysqli_query($conn, "DELETE FROM cctv_captures WHERE camera_id IN (SELECT id FROM cctv_devices WHERE building_id = '$safe_building_id')")) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete captures']);
            exit();
        }

        $payload = fetch_dashboard_data($conn, $building_id);
        $payload['status'] = 'success';
        echo json_encode($payload);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
        break;
}
