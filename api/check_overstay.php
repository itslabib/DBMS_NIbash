<?php
// This script checks for any active parking bookings that have exceeded their time limit,
// but the car is still physically in the spot (current_status = 'Occupied').
// It sends an email notification to the resident/user.

// It assumes $conn is available if required from another file, but we'll include db_config just in case.
if (!isset($conn)) {
    require_once __DIR__ . '/../includes/db_config.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Only load PHPMailer if not already loaded
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

$overstay_query = "
    SELECT pr.id, pr.slot_id, pr.requester_id, pr.end_time, pr.license_plate, 
           u.email, p.full_name, ps.slot_number
    FROM parking_requests pr
    JOIN parking_slots ps ON pr.slot_id = ps.id
    JOIN users u ON pr.requester_id = u.id
    LEFT JOIN user_profiles p ON u.id = p.user_id
    WHERE pr.status = 'approved'
      AND pr.end_time < NOW()
      AND pr.overstay_notified = 0
      AND ps.current_status = 'Occupied'
      AND ps.temporary_until = pr.end_time
";

$overstay_res = mysqli_query($conn, $overstay_query);

if ($overstay_res && mysqli_num_rows($overstay_res) > 0) {
    while ($row = mysqli_fetch_assoc($overstay_res)) {
        $req_id = $row['id'];
        $email = $row['email'];
        $name = $row['full_name'] ?? 'Resident';
        $slot_num = $row['slot_number'];
        $end_time = date('h:i A', strtotime($row['end_time']));
        
        // Send email
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
            $mail->addAddress($email, $name);

            $mail->isHTML(true);
            $mail->Subject = 'URGENT: Parking Time Limit Exceeded';
            $mail->Body    = "Hello {$name},<br><br>
                              Your booked time for parking slot <b>{$slot_num}</b> expired at <b>{$end_time}</b>.<br>
                              Our system detects that your vehicle is still occupying the spot.<br><br>
                              <b>Please move your vehicle immediately</b> to avoid inconveniencing others or incurring penalties.<br><br>
                              Thank you,<br>Nibash Management";

            if ($mail->send()) {
                // Mark as notified
                mysqli_query($conn, "UPDATE parking_requests SET overstay_notified = 1 WHERE id = '$req_id'");
            }
        } catch (Exception $e) {
            // Log or ignore
        }
    }
}
?>
