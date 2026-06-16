<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] == 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

require '../includes/db_config.php';

$resident_id = (int) $_SESSION['user_id'];
$guest_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($guest_id > 0) {
    // 1. Verify that this guest belongs to one or more visit requests created by this resident
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM visit_requests WHERE guest_id = ? AND resident_id = ?");
    if ($check_stmt) {
        mysqli_stmt_bind_param($check_stmt, 'ii', $guest_id, $resident_id);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);

        $visit_ids = [];
        while ($row = mysqli_fetch_assoc($check_result)) {
            $visit_ids[] = (int)$row['id'];
        }
        mysqli_stmt_close($check_stmt);

        foreach ($visit_ids as $visit_id) {
            $logs_res = mysqli_query($conn, "SELECT id FROM entry_logs WHERE visit_id = $visit_id LIMIT 1");
            if ($logs_res && mysqli_num_rows($logs_res) > 0) {
                // Guest has scanned face -> soft delete this visit
                mysqli_query($conn, "UPDATE visit_requests SET status = 'Cancelled' WHERE id = $visit_id");
            } else {
                // No scans -> hard delete this visit
                mysqli_query($conn, "DELETE FROM guest_vehicles WHERE visit_id = $visit_id");
                mysqli_query($conn, "DELETE FROM visit_requests WHERE id = $visit_id");
            }
        }

        if (!empty($visit_ids)) {
            $other_visits = mysqli_query($conn, "SELECT id FROM visit_requests WHERE guest_id = $guest_id");
            if ($other_visits && mysqli_num_rows($other_visits) == 0) {
                mysqli_query($conn, "DELETE FROM guests WHERE id = $guest_id");
            }
        }
    }
}

header("Location: ../resident/guest_passes.php?success=deleted");
exit();
?>