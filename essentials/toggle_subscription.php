<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$q = mysqli_query($conn, "SELECT is_subscribed FROM service_providers WHERE user_id = '$user_id'");
if ($row = mysqli_fetch_assoc($q)) {
    $new_status = $row['is_subscribed'] ? 0 : 1;
    mysqli_query($conn, "UPDATE service_providers SET is_subscribed = $new_status WHERE user_id = '$user_id'");
}

$referer = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: " . $referer);
exit();
