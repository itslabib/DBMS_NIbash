<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

require_once '../includes/db_config.php';

$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
if (!$action && isset($data['action'])) {
    $action = $data['action'];
}

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
    exit();
}

if ($action === 'get') {
    // Get all notifications for the user
    $query = "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC LIMIT 50";
    $result = mysqli_query($conn, $query);

    $notifications = [];
    $unread_count = 0;

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'message' => $row['message'],
                'link' => $row['link'],
                'is_read' => (bool)$row['is_read'],
                'created_at' => date('M d, h:i A', strtotime($row['created_at']))
            ];
            if (!$row['is_read']) {
                $unread_count++;
            }
        }
    }

    echo json_encode([
        "status" => "success",
        "notifications" => $notifications,
        "unread_count" => $unread_count
    ]);
} elseif ($action === 'mark_read') {
    if (isset($data['id'])) {
        $notif_id = (int)$data['id'];
        $query = "UPDATE notifications SET is_read = 1 WHERE id = $notif_id AND user_id = $user_id";
    } else {
        // Mark all as read
        $query = "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id";
    }

    if (mysqli_query($conn, $query)) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}
?>
