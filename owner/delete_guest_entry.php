<?php
session_start();
require '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$entry_id = isset($_GET['entry_id']) ? (int) $_GET['entry_id'] : 0;

if ($entry_id > 0) {
    $delete_stmt = mysqli_prepare($conn, 'DELETE FROM entry_logs WHERE id = ?');
    if ($delete_stmt) {
        mysqli_stmt_bind_param($delete_stmt, 'i', $entry_id);
        mysqli_stmt_execute($delete_stmt);
        mysqli_stmt_close($delete_stmt);
    }
}

header("Location: ../owner/guest_entries.php?success=deleted");
exit();
?>