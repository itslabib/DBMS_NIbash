<?php
require_once '../includes/db_config.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $nid = trim($_POST['nid'] ?? '');
    
    // Check Email
    if (!empty($email)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            echo json_encode(['error' => 'An account with this Email already exists.', 'field' => 'email']);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Check Phone
    if (!empty($phone)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM user_profiles WHERE phone = ?");
        mysqli_stmt_bind_param($stmt, "s", $phone);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            echo json_encode(['error' => 'This Phone Number is already registered.', 'field' => 'phone']);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
    
    // Check NID
    if (!empty($nid)) {
        $stmt = mysqli_prepare($conn, "SELECT id FROM user_profiles WHERE nid = ?");
        mysqli_stmt_bind_param($stmt, "s", $nid);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            echo json_encode(['error' => 'This NID is already registered.', 'field' => 'nid_number']);
            exit;
        }
        mysqli_stmt_close($stmt);
    }
    
    echo json_encode(['success' => true]);
}
?>
