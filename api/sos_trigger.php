<?php
// api/sos_trigger.php
// Dedicated AJAX endpoint to trigger SOS alerts from any page (chatbot, dashboard, etc.)
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
error_reporting(0);

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $error['message']]);
    } else {
        ob_end_flush();
    }
});

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

require_once __DIR__ . '/../includes/db_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../vendor/autoload.php';

$user_id = (int)$_SESSION['user_id'];

// Get user profile
$p_query = mysqli_query($conn, "SELECT id, full_name, phone FROM user_profiles WHERE user_id = '$user_id'");
$p_data  = mysqli_fetch_assoc($p_query);
$profile_id  = $p_data['id']        ?? 0;
$sender_name = $p_data['full_name'] ?? 'A Resident';
$sender_phone = !empty($p_data['phone']) ? $p_data['phone'] : 'N/A';

// Fetch personal SOS contacts
$sos_query = mysqli_query($conn,
    "SELECT * FROM emergency_contacts WHERE user_profile_id = '$profile_id' AND contact_type = 'Personal'"
);

if (!$sos_query || mysqli_num_rows($sos_query) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'No SOS contacts configured. Please add contacts in the Emergency Console first.'
    ]);
    exit;
}

// Get optional location from POST
$lat = isset($_POST['lat']) ? floatval($_POST['lat']) : null;
$lng = isset($_POST['lng']) ? floatval($_POST['lng']) : null;

$location_line = '';
if ($lat && $lng) {
    $maps_url     = "https://maps.google.com/?q={$lat},{$lng}";
    $location_line = "<p><b>📍 Location:</b> <a href='{$maps_url}' style='color:#e11d48;'>View on Google Maps</a> (Lat: {$lat}, Lng: {$lng})</p>";
    $whatsapp_location = "\n📍 Location: {$maps_url}";
} else {
    $location_line = "<p><b>📍 Location:</b> GPS coordinates not available</p>";
    $whatsapp_location = '';
}

$timestamp = date('d M Y, h:i A');

$mail = new PHPMailer(true);
$emails_sent = 0;
$whatsapp_number = null;
$whatsapp_text   = "🚨 *SOS ALERT* 🚨\n\n"
                 . "Emergency triggered by: *{$sender_name}*\n"
                 . "Contact: *{$sender_phone}*\n"
                 . "Time: {$timestamp}"
                 . $whatsapp_location
                 . "\n\nPlease contact them immediately or dispatch help!";

try {
    // --- SMTP CONFIG (Gmail App Password) ---
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'suchak9931@gmail.com';
    $mail->Password   = 'ubaz ayum yhyy hyis';   // Gmail App Password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom('suchak9931@gmail.com', 'Nibash SOS System');
    $mail->isHTML(true);
    $mail->Subject = "🚨 EMERGENCY SOS ALERT — {$sender_name} needs help!";
    $mail->Body    = "
        <div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 3px solid #e11d48; border-radius: 12px; overflow: hidden;'>
            <div style='background: #e11d48; padding: 20px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>🚨 SOS EMERGENCY ALERT</h1>
                <p style='color: #fecdd3; margin: 6px 0 0;'>Nibash Building Management System</p>
            </div>
            <div style='padding: 24px; background: #fff;'>
                <p style='font-size: 16px; color: #1e293b;'><b>{$sender_name}</b> has triggered an <b style='color:#e11d48;'>EMERGENCY SOS</b> and needs immediate assistance.</p>
                <table style='width:100%; border-collapse:collapse; margin: 16px 0;'>
                    <tr style='background:#fef2f2;'>
                        <td style='padding:10px; font-weight:bold; color:#7f1d1d; border:1px solid #fecaca;'>👤 Name</td>
                        <td style='padding:10px; border:1px solid #fecaca;'>{$sender_name}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; font-weight:bold; color:#7f1d1d; border:1px solid #fecaca;'>📞 Phone</td>
                        <td style='padding:10px; border:1px solid #fecaca;'>{$sender_phone}</td>
                    </tr>
                    <tr style='background:#fef2f2;'>
                        <td style='padding:10px; font-weight:bold; color:#7f1d1d; border:1px solid #fecaca;'>🕐 Time</td>
                        <td style='padding:10px; border:1px solid #fecaca;'>{$timestamp}</td>
                    </tr>
                    <tr>
                        <td style='padding:10px; font-weight:bold; color:#7f1d1d; border:1px solid #fecaca;'>📍 Location</td>
                        <td style='padding:10px; border:1px solid #fecaca;'>" . ($lat ? "<a href='https://maps.google.com/?q={$lat},{$lng}' style='color:#e11d48;'>View on Google Maps</a>" : "Not available") . "</td>
                    </tr>
                </table>
                <div style='background:#fef2f2; border-left:4px solid #e11d48; padding:14px; border-radius:6px; margin-top:12px;'>
                    <b style='color:#e11d48;'>⚠️ Immediate action required.</b>
                    <p style='margin:6px 0 0; color:#1e293b;'>Please contact {$sender_name} immediately at <b>{$sender_phone}</b> or dispatch help to their location.</p>
                </div>
            </div>
            <div style='background:#f8fafc; padding: 14px; text-align:center; color:#94a3b8; font-size:12px; border-top:1px solid #e2e8f0;'>
                This alert was sent automatically by the Nibash Building Management System.
            </div>
        </div>
    ";
    $mail->AltBody = "EMERGENCY SOS from {$sender_name} ({$sender_phone}) at {$timestamp}. Please contact immediately!";

    while ($contact = mysqli_fetch_assoc($sos_query)) {
        if (!empty($contact['email'])) {
            $mail->addAddress($contact['email'], $contact['title']);
            $emails_sent++;
        }
        if (!empty($contact['phone_number']) && !$whatsapp_number) {
            $raw = preg_replace('/[^0-9]/', '', $contact['phone_number']);
            $whatsapp_number = (substr($raw, 0, 2) === '01') ? '+88' . $raw : $raw;
        }
    }

    if ($emails_sent > 0) {
        $mail->send();
    }

    // Log the SOS trigger in notifications table
    $notif_check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
    if (mysqli_num_rows($notif_check) > 0) {
        $title   = mysqli_real_escape_string($conn, "🚨 SOS Alert Triggered");
        $note    = mysqli_real_escape_string($conn, "SOS triggered by {$sender_name} at {$timestamp}. Emails sent to {$emails_sent} contact(s).");
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES ('$user_id', '$title', '$note', 'emergency_console.php', 0, NOW())");
    }

    $wa_url = null;
    if ($whatsapp_number) {
        $wa_url = "https://wa.me/{$whatsapp_number}?text=" . urlencode($whatsapp_text);
    }

    echo json_encode([
        'success'       => true,
        'emails_sent'   => $emails_sent,
        'whatsapp_url'  => $wa_url,
        'message'       => $emails_sent > 0
            ? "🚨 SOS sent! {$emails_sent} contact(s) emailed successfully."
            : "SOS logged, but no email addresses were configured for your contacts."
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send SOS email: ' . $mail->ErrorInfo . '. Please call your contacts directly.'
    ]);
}
