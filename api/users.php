<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

require_once '../includes/db_config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
if (!$action && isset($data['action'])) {
    $action = $data['action'];
}

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
    exit();
}

$user_id = $_SESSION['user_id'];

switch ($action) {

    // ---------------------------------------------------------
    // GET BUILDING USERS (for community hub @mention feature)
    // ---------------------------------------------------------
    case 'get_building_users':
        $user_building_id = '';

        $apt_q   = "SELECT aa.apt_id, a.building_id 
                    FROM apartment_assignments aa 
                    JOIN apartments a ON a.id = aa.apt_id 
                    WHERE aa.user_id = '$user_id' AND aa.is_active = 1 
                    LIMIT 1";
        $apt_res = mysqli_query($conn, $apt_q);

        if ($apt_res && mysqli_num_rows($apt_res) > 0) {
            $user_building_id = mysqli_fetch_assoc($apt_res)['building_id'];
        } else {
            // Fallback: grab first known building (owners may not have an assignment row)
            $fb_res = mysqli_query($conn, "SELECT building_id FROM apartments WHERE building_id != '' LIMIT 1");
            if ($fb_res && mysqli_num_rows($fb_res) > 0) {
                $user_building_id = mysqli_fetch_assoc($fb_res)['building_id'];
            }
        }

        if (empty($user_building_id)) {
            echo json_encode([]);
            exit();
        }

        $safe_building_id = mysqli_real_escape_string($conn, $user_building_id);

        $query  = "SELECT u.id as user_id, p.full_name, u.role_id,
                          GROUP_CONCAT(a.apt_number SEPARATOR ', ') as apt_numbers
                   FROM users u
                   JOIN user_profiles p ON u.id = p.user_id
                   JOIN apartment_assignments aa ON aa.user_id = u.id AND aa.is_active = 1
                   JOIN apartments a ON a.id = aa.apt_id
                   WHERE a.building_id = '$safe_building_id' AND u.status = 'active'
                   GROUP BY u.id, p.full_name, u.role_id";
        $result = mysqli_query($conn, $query);

        $users = [];
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $apt = 'Resident';
                if ($row['role_id'] == 1) {
                    $apt = 'OWNER';
                } else {
                    $apts = explode(', ', $row['apt_numbers']);
                    $real_apts = [];
                    foreach ($apts as $a) {
                        if (strlen($a) < 15) {
                            $real_apts[] = $a;
                        }
                    }
                    if (!empty($real_apts)) {
                        $apt = $real_apts[0]; // just show one for brevity
                    }
                }
                $users[] = [
                    'id'  => $row['user_id'],
                    'name' => $row['full_name'],
                    'apt' => $apt
                ];
            }
        }
        echo json_encode($users);
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . $action]);
}
?>
