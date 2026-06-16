<?php
// login_process.php
session_start();
require_once 'includes/db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // This input can now be either the username or the email address
    $login_id = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    // authRole will be either 'resident' or 'admin'
    $authRole = $_POST['authRole'] ?? 'resident';

    if (empty($login_id) || empty($password)) {
        header("Location: " . BASE_URL . "login.php?error=empty_fields");
        exit();
    }

    // UPDATE: We must select the 'is_verified' and 'status' columns from the database here
    $stmt = mysqli_prepare($conn, "SELECT id, username, email, password, role_id, is_verified, status FROM users WHERE username = ? OR email = ?");
    mysqli_stmt_bind_param($stmt, "ss", $login_id, $login_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        // Plaintext password comparison or hashed password comparison
        if ($password === $row['password'] || password_verify($password, $row['password'])) {

            // Block suspended users from logging in
            if (isset($row['status']) && $row['status'] === 'suspended') {
                header("Location: " . BASE_URL . "login.php?error=account_suspended");
                exit();
            }

            // Block inactive providers and owners (pending admin approval)
            if (isset($row['status']) && $row['status'] === 'inactive' && ($row['role_id'] == 4 || $row['role_id'] == 1)) {
                header("Location: " . BASE_URL . "login.php?error=account_pending");
                exit();
            }

            // Block unverified users from logging in
            if ($row['is_verified'] == 0) {
                // Kick them over to the verification page
                header("Location: " . BASE_URL . "verify.php?email=" . urlencode($row['email']));
                exit();
            }

            // Check Role Validation (Admin shouldn't login from resident part)
            if ($authRole === 'admin' && $row['role_id'] != 1) {
                header("Location: " . BASE_URL . "login.php?error=invalid_role");
                exit();
            } else if ($authRole === 'resident' && $row['role_id'] == 1) {
                // Prevent Admin from logging in via Resident portal toggle
                header("Location: " . BASE_URL . "login.php?error=invalid_role_admin");
                exit();
            }

            // Set session variables
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role_id'] = $row['role_id'];
            
            // Determine and set the primary building_id for the session
            $b_id = null;
            if ($row['role_id'] == 1) { // Owner/Admin
                $checkBuilding = mysqli_query($conn, "SELECT building_id FROM building_managers WHERE user_id = {$row['id']} AND role='admin' LIMIT 1");
                if ($checkBuilding && mysqli_num_rows($checkBuilding) > 0) {
                    $b_id = mysqli_fetch_assoc($checkBuilding)['building_id'];
                }
            }
            if (!$b_id) {
                $checkApt = mysqli_query($conn, "SELECT a.building_id FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id WHERE aa.user_id = {$row['id']} AND a.building_id IS NOT NULL LIMIT 1");
                if ($checkApt && mysqli_num_rows($checkApt) > 0) {
                    $b_id = mysqli_fetch_assoc($checkApt)['building_id'];
                }
            }
            $_SESSION['building_id'] = $b_id;

            // Redirect based on exact role
            if (!empty($_POST['redirect'])) {
                header("Location: " . BASE_URL . ltrim($_POST['redirect'], '/'));
                exit();
            } else if ($row['role_id'] == 1) {
                header("Location: " . BASE_URL . "owner/dashboard.php");
                exit();
            } else if ($row['role_id'] == 4) {
                // If it's a provider (role = 4)
                header("Location: " . BASE_URL . "essentials/dashboard.php");
                exit();
            } else {
                // If it's a resident (role = 2 or others)
                header("Location: " . BASE_URL . "resident/dashboard.php");
                exit();
            }

        } else {
            // Invalid Password
            header("Location: " . BASE_URL . "login.php?error=invalid_credentials");
            exit();
        }
    } else {
        // Invalid Username/Email
        header("Location: " . BASE_URL . "login.php?error=invalid_credentials");
        exit();
    }
} else {
    // If not a POST request, kick them back
    header("Location: " . BASE_URL . "login.php");
    exit();
}
?>