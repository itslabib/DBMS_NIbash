<?php
session_start();
require_once '../includes/db_config.php';
mysqli_report(MYSQLI_REPORT_ERROR);

// Ensure the user is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
if (isset($_GET['id'])) {
    $resident_id = (int)$_GET['id'];
    
    // Safety check - does this resident exist with role_id=2?
    $check_query = "SELECT u.id, p.full_name FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = '$resident_id' AND u.role_id = 2";
    $check_res = @mysqli_query($conn, $check_query);
    if ($check_res && mysqli_num_rows($check_res) > 0) {
        $row = mysqli_fetch_assoc($check_res);
        $resident_name = !empty($row['full_name']) ? $row['full_name'] : "Resident";

        // Get assigned apartments
        $apt_res = @mysqli_query($conn, "SELECT apt_id FROM apartment_assignments WHERE user_id = '$resident_id'");
        $assigned_apts = [];
        if ($apt_res) {
            while($a = mysqli_fetch_assoc($apt_res)) {
                $assigned_apts[] = $a['apt_id'];
            }
        }

        // Clear resident's guests, logs, etc if needed
        @mysqli_query($conn, "DELETE FROM entry_logs WHERE guest_id IN (SELECT id FROM guests WHERE resident_id = '$resident_id')");
        @mysqli_query($conn, "DELETE FROM guests WHERE resident_id = '$resident_id'");

        // Delete family members if exist
        @mysqli_query($conn, "DELETE FROM family_members WHERE user_id = '$resident_id'");
        @mysqli_query($conn, "DELETE FROM resident_vehicles WHERE user_id = '$resident_id'");

        // Remove the resident from any assigned apartments or rentals
        @mysqli_query($conn, "DELETE FROM apartment_assignments WHERE user_id = '$resident_id'");
        @mysqli_query($conn, "DELETE FROM parking_requests WHERE requester_id = '$resident_id' OR target_resident_id = '$resident_id'");

        // Free parking slots and update apartment status
        foreach ($assigned_apts as $apt_id) {
            // Unlink parking slot from the apartment so it becomes fully available
            @mysqli_query($conn, "UPDATE parking_slots SET current_status = 'Vacant', apt_id = NULL, temporary_name = NULL, temporary_until = NULL WHERE apt_id = '$apt_id'");
            
            // Update apartment status if no other active tenants
            $check_apt = @mysqli_query($conn, "SELECT id FROM apartment_assignments WHERE apt_id = '$apt_id' AND role = 'tenant' AND is_active = 1");
            if ($check_apt && mysqli_num_rows($check_apt) == 0) {
                @mysqli_query($conn, "UPDATE apartments SET status = 'available' WHERE id = '$apt_id'");
            }
        }

        // Delete profile and user
        @mysqli_query($conn, "DELETE FROM user_profiles WHERE user_id = '$resident_id'");
        @mysqli_query($conn, "DELETE FROM users WHERE id = '$resident_id'");
        
        $_SESSION['msg'] = "Resident '" . htmlspecialchars($resident_name) . "' has been deleted.";
    } else {
        $_SESSION['error'] = "Resident not found or you are unauthorized.";
    }
}
header("Location: ../owner/residents.php");
exit();
?>
