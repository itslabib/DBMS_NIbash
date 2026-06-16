<?php
session_start();
require_once '../includes/db_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../vendor/autoload.php';

function sendParkingEmail($to_email, $to_name, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'suchak9931@gmail.com'; 
        $mail->Password   = 'ubaz ayum yhyy hyis';   
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->setFrom('suchak9931@gmail.com', 'Nibash System');
        $mail->addAddress($to_email, $to_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->send();
    } catch (Exception $e) {}
}

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_spot' && $role_id == 1) {
        $building_id = mysqli_real_escape_string($conn, $_POST['building_id']);
        $slot_number = mysqli_real_escape_string($conn, $_POST['slot_number'] ?? '');

        if (empty($slot_number)) {
            $count_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM parking_slots WHERE building_id = '$building_id' AND slot_number LIKE 'P-%'");
            $cnt_data = mysqli_fetch_assoc($count_q);
            $cnt = $cnt_data ? $cnt_data['cnt'] : 0;
            $slot_number = 'P-' . ($cnt + 1);
            
            while(mysqli_num_rows(mysqli_query($conn, "SELECT id FROM parking_slots WHERE building_id = '$building_id' AND slot_number = '$slot_number'")) > 0) {
                $cnt++;
                $slot_number = 'P-' . ($cnt + 1);
            }
        }

        if (!empty($building_id) && !empty($slot_number)) {
            $check_q = mysqli_query($conn, "SELECT id FROM parking_slots WHERE building_id = '$building_id' AND slot_number = '$slot_number'");
            if (mysqli_num_rows($check_q) > 0) {
                $_SESSION['toast_msg'] = "Error: Parking slot number '$slot_number' already exists.";
                $_SESSION['toast_type'] = "error";
            } else {
                try {
                    $query = "INSERT INTO parking_slots (building_id, slot_number, current_status) 
                              VALUES ('$building_id', '$slot_number', 'Vacant')";
                    mysqli_query($conn, $query);
                    
                    $_SESSION['toast_msg'] = "Parking space added successfully.";
                    $_SESSION['toast_type'] = "success";
                } catch (Exception $e) {
                    $_SESSION['toast_msg'] = "Database error: " . $e->getMessage();
                    $_SESSION['toast_type'] = "error";
                }
            }
        }
        header("Location: ../parking/index.php");
        exit();

    } elseif ($action === 'delete_spot' && $role_id == 1) {
        $slot_id = mysqli_real_escape_string($conn, $_POST['slot_id']);
        if (!empty($slot_id)) {
            mysqli_query($conn, "DELETE FROM parking_requests WHERE slot_id = '$slot_id'");
            mysqli_query($conn, "DELETE FROM parking_slots WHERE id = '$slot_id'");
            $_SESSION['toast_msg'] = "Parking space deleted successfully.";
            $_SESSION['toast_type'] = "success";
        }
        header("Location: ../parking/index.php");
        exit();

    } elseif ($action === 'book_spot') {
        $slot_id = mysqli_real_escape_string($conn, $_POST['slot_id']);
        $target_resident_id = !empty($_POST['target_resident_id']) ? mysqli_real_escape_string($conn, $_POST['target_resident_id']) : 'NULL';
        $building_id = mysqli_real_escape_string($conn, $_POST['building_id']);
        $start_time = mysqli_real_escape_string($conn, $_POST['start_time']);
        $end_time = mysqli_real_escape_string($conn, $_POST['end_time']);
        $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
        $for_whom = mysqli_real_escape_string($conn, $_POST['for_whom'] ?? 'Self');
        $license_plate = mysqli_real_escape_string($conn, $_POST['license_plate'] ?? '');
        
        $status = 'pending_owner';
        if ($role_id == 1 && $target_resident_id !== 'NULL') {
            $status = 'pending_resident';
        } elseif ($role_id == 1 && $target_resident_id === 'NULL') {
            $status = 'approved';
        }

        $query = "INSERT INTO parking_requests (slot_id, requester_id, target_resident_id, building_id, start_time, end_time, license_plate, purpose, for_whom, status) 
                  VALUES ('$slot_id', '$user_id', $target_resident_id, '$building_id', '$start_time', '$end_time', '$license_plate', '$purpose', '$for_whom', '$status')";
        
        if (mysqli_query($conn, $query)) {
            if ($status === 'pending_owner') {
                // Notify Owner
                $owner_q = mysqli_query($conn, "SELECT u.id, u.email, p.full_name FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.role_id = 1 LIMIT 1");
                if ($owner = mysqli_fetch_assoc($owner_q)) {
                    $owner_id = $owner['id'];
                    $owner_email = $owner['email'];
                    $owner_name = $owner['full_name'] ?? 'Owner';
                    $msg = "A resident requested a parking spot.";
                    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link) VALUES ('$owner_id', 'New Parking Request', '$msg', 'parking/index.php')");
                    sendParkingEmail($owner_email, $owner_name, 'New Parking Request', "Hello $owner_name,<br><br>A resident has requested a parking spot.<br>Reason: $purpose<br>From: $start_time to $end_time<br><br>Please check your dashboard to approve or reject the request.");
                }
                $_SESSION['toast_msg'] = "Booking request submitted to owner.";
            } elseif ($status === 'pending_resident') {
                // Notify Target Resident
                mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link) VALUES ('$target_resident_id', 'Owner Requested Your Spot', 'The owner requested your parking spot. Please approve or reject.', 'parking/index.php')");
                
                $target_user_q = mysqli_query($conn, "SELECT u.email, p.full_name FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = '$target_resident_id'");
                if ($target_user = mysqli_fetch_assoc($target_user_q)) {
                    sendParkingEmail($target_user['email'], $target_user['full_name'] ?? 'Resident', 'Owner Requested Your Spot', "Hello,<br><br>The owner has requested to use your parking spot.<br>Reason: $purpose<br>From: $start_time to $end_time<br><br>Please check your dashboard to approve or reject.");
                }
                $_SESSION['toast_msg'] = "Request sent to resident.";
            } elseif ($status === 'approved') {
                // Owner booked empty spot
                $req_apt_q = mysqli_query($conn, "SELECT a.apt_number FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id WHERE aa.user_id = '$user_id' AND aa.is_active = 1 LIMIT 1");
                $req_apt_name = ($req_apt_q && mysqli_num_rows($req_apt_q) > 0) ? mysqli_fetch_assoc($req_apt_q)['apt_number'] : 'Owner';
                mysqli_query($conn, "UPDATE parking_slots SET temporary_name = '$req_apt_name', temporary_until = '$end_time', temporary_plate = '$license_plate' WHERE id = '$slot_id'");
                $_SESSION['toast_msg'] = "Slot booked successfully.";
            }
            $_SESSION['toast_type'] = "success";
        } else {
            $_SESSION['toast_msg'] = "Failed to submit request.";
            $_SESSION['toast_type'] = "error";
        }
        header("Location: ../parking/index.php");
        exit();

    } elseif ($action === 'approve_request' || $action === 'reject_request') {
        $request_id = mysqli_real_escape_string($conn, $_POST['request_id']);
        
        $req_q = mysqli_query($conn, "SELECT * FROM parking_requests WHERE id = '$request_id'");
        $req = mysqli_fetch_assoc($req_q);
        
        if (!$req) {
            header("Location: ../parking/index.php");
            exit();
        }
        
        // Fetch requester info
        $req_user_q = mysqli_query($conn, "SELECT u.email, p.full_name FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = '{$req['requester_id']}'");
        $req_user = mysqli_fetch_assoc($req_user_q);
        $req_email = $req_user['email'];
        $req_name = $req_user['full_name'] ?? 'Resident';

        if ($action === 'reject_request') {
            mysqli_query($conn, "UPDATE parking_requests SET status = 'rejected' WHERE id = '$request_id'");
            
            // Notify Requester
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ('{$req['requester_id']}', 'Parking Request Rejected', 'Your parking request was rejected.')");
            sendParkingEmail($req_email, $req_name, 'Parking Request Rejected', "Hello $req_name,<br><br>Unfortunately, your parking request has been rejected.");
            
            $_SESSION['toast_msg'] = "Request rejected.";
            $_SESSION['toast_type'] = "success";
        } elseif ($action === 'approve_request') {
            $is_fully_approved = false;
            if ($role_id == 1) { // Owner Approving
                if (empty($req['target_resident_id'])) {
                    // Spot is unassigned, approve directly
                    mysqli_query($conn, "UPDATE parking_requests SET status = 'approved' WHERE id = '$request_id'");
                    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ('{$req['requester_id']}', 'Parking Request Approved', 'Your parking request has been fully approved.')");
                    sendParkingEmail($req_email, $req_name, 'Parking Request Approved', "Hello $req_name,<br><br>Your parking request has been fully approved by the owner.");
                    $is_fully_approved = true;
                } else {
                    // Spot belongs to another resident, pass to them
                    mysqli_query($conn, "UPDATE parking_requests SET status = 'pending_resident' WHERE id = '$request_id'");
                    mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link) VALUES ('{$req['target_resident_id']}', 'Parking Request Approval Needed', 'Owner approved a request for your parking spot. Your permission is required.', 'parking/index.php')");
                    
                    $target_user_q = mysqli_query($conn, "SELECT u.email, p.full_name FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = '{$req['target_resident_id']}'");
                    if ($target_user = mysqli_fetch_assoc($target_user_q)) {
                        sendParkingEmail($target_user['email'], $target_user['full_name'] ?? 'Resident', 'Parking Request Approval Needed', "Hello,<br><br>The owner has approved a request from another resident to use your parking spot. Your final permission is required. Please check your dashboard.");
                    }
                }
            } else { // Resident Approving
                mysqli_query($conn, "UPDATE parking_requests SET status = 'approved' WHERE id = '$request_id'");
                mysqli_query($conn, "INSERT INTO notifications (user_id, title, message) VALUES ('{$req['requester_id']}', 'Parking Request Approved', 'Your parking request has been fully approved by the resident.')");
                sendParkingEmail($req_email, $req_name, 'Parking Request Approved', "Hello $req_name,<br><br>Your parking request has been fully approved by the resident.");
                $is_fully_approved = true;
            }
            
            if ($is_fully_approved) {
                // Fetch requester's apartment name
                $req_apt_q = mysqli_query($conn, "SELECT a.apt_number FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id WHERE aa.user_id = '{$req['requester_id']}' AND aa.is_active = 1 LIMIT 1");
                $req_apt_name = ($req_apt_q && mysqli_num_rows($req_apt_q) > 0) ? mysqli_fetch_assoc($req_apt_q)['apt_number'] : 'Guest';
                $end_time = $req['end_time'];
                $temp_plate = $req['license_plate'] ?? '';
                mysqli_query($conn, "UPDATE parking_slots SET temporary_name = '$req_apt_name', temporary_until = '$end_time', temporary_plate = '$temp_plate' WHERE id = '{$req['slot_id']}'");
            }
            
            $_SESSION['toast_msg'] = "Request approved.";
            $_SESSION['toast_type'] = "success";
        }
        
        header("Location: ../parking/index.php");
        exit();

    } elseif ($action === 'delete_history') {
        $request_id = mysqli_real_escape_string($conn, $_POST['request_id']);
        mysqli_query($conn, "DELETE FROM parking_requests WHERE id = '$request_id'");
        $_SESSION['toast_msg'] = "Booking history deleted.";
        $_SESSION['toast_type'] = "success";
        header("Location: ../parking/index.php");
        exit();
        
    } elseif ($action === 'cancel_booking') {
        $request_id = mysqli_real_escape_string($conn, $_POST['request_id']);
        $req_q = mysqli_query($conn, "SELECT * FROM parking_requests WHERE id = '$request_id'");
        $req = mysqli_fetch_assoc($req_q);
        if ($req) {
            // Check permission: Only requester or owner can cancel
            if ($req['requester_id'] == $user_id || $role_id == 1) {
                mysqli_query($conn, "UPDATE parking_requests SET status = 'cancelled' WHERE id = '$request_id'");
                
                // Clear the slot if this was the active booking
                $slot_id = $req['slot_id'];
                $end_time = $req['end_time'];
                mysqli_query($conn, "UPDATE parking_slots SET temporary_name = NULL, temporary_until = NULL, temporary_plate = NULL WHERE id = '$slot_id' AND temporary_until = '$end_time'");
                
                $_SESSION['toast_msg'] = "Booking cancelled successfully.";
                $_SESSION['toast_type'] = "success";
            } else {
                $_SESSION['toast_msg'] = "Invalid booking or permission denied.";
                $_SESSION['toast_type'] = "error";
            }
        } else {
            $_SESSION['toast_msg'] = "Invalid booking.";
            $_SESSION['toast_type'] = "error";
        }
        header("Location: ../parking/index.php");
        exit();
    }
}
