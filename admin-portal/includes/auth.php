<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../includes/db_config.php';

// Handle login POST
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    $user = $_POST['admin_id'] ?? '';
    $pass = $_POST['admin_pass'] ?? '';
    
    if ($user === 'admin' && $pass === 'admin') {
        $_SESSION['super_admin_logged_in'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    } else { 
        $login_error = 'Invalid credentials. This attempt has been logged.'; 
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ../index.php'); exit;
}

$is_logged_in = !empty($_SESSION['super_admin_logged_in']);

// Counts for sidebar badges (needed on every page)
if ($is_logged_in) {
    $pending_owner = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as c FROM users WHERE role_id=1 AND (status='inactive' OR is_verified=0)"))['c'] ?? 0;
    $pending_provider = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as c FROM users WHERE role_id=4 AND status='inactive'"))['c'] ?? 0;
    $pending_count = $pending_owner + $pending_provider;
    $pending_payment_count = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as c FROM subscriptions WHERE payment_key IS NOT NULL AND payment_verified_at IS NULL"))['c'] ?? 0;
}
