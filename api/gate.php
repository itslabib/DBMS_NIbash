<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
if (!$action && isset($data['action'])) {
    $action = $data['action'];
}

if (!$action) {
    echo json_encode(['status' => 'error', 'message' => 'No action specified']);
    exit();
}

require_once '../includes/db_config.php';

try {
    switch ($action) {

        // ---------------------------------------------------------
        // GET GUESTS (for face recognition on landing page)
        // ---------------------------------------------------------
        case 'get_guests':
            $sql = "SELECT g.id, g.full_name as guest_name, g.face_descriptor 
                    FROM guests g 
                    JOIN visit_requests vr ON g.id = vr.guest_id 
                    WHERE g.face_descriptor IS NOT NULL AND g.face_descriptor != '' 
                    AND vr.status = 'Approved'";
            $result = mysqli_query($conn, $sql);
            $guests = [];
            if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                    $guests[] = [
                        'id'              => $row['id'],
                        'guest_name'      => $row['guest_name'],
                        'guest_type'      => 'Guest',
                        'face_descriptor' => json_decode($row['face_descriptor'])
                    ];
                }
            }
            echo json_encode(["status" => "success", "data" => $guests]);
            break;

        // ---------------------------------------------------------
        // LOG KNOWN GUEST ENTRY
        // ---------------------------------------------------------
        case 'log_entry':
            if (!isset($data['guest_id'])) {
                echo json_encode(["status" => "error", "message" => "Missing guest ID."]);
                exit;
            }

            $guest_id = (int)$data['guest_id'];

            $vr_query  = "SELECT id FROM visit_requests WHERE guest_id = $guest_id AND status = 'Approved' ORDER BY id DESC LIMIT 1";
            $vr_result = mysqli_query($conn, $vr_query);

            if (!$vr_result || mysqli_num_rows($vr_result) === 0) {
                echo json_encode(["status" => "error", "message" => "No approved guest pass found for this guest identity."]);
                exit;
            }

            $visit_id  = (int)mysqli_fetch_assoc($vr_result)['id'];
            $score     = isset($data['score']) ? (float)$data['score'] : null;
            $score_sql = $score !== null ? $score : 'NULL';

            $insert_log = "INSERT INTO entry_logs (visit_id, entry_time, entry_method, verification_score)
                           VALUES ($visit_id, NOW(), 'FaceScan', $score_sql)";

            if (!mysqli_query($conn, $insert_log)) {
                echo json_encode(["status" => "error", "message" => "Database error: " . mysqli_error($conn)]);
                exit;
            }

            $entry_id = mysqli_insert_id($conn);
            mysqli_query($conn, "UPDATE visit_requests SET status = 'Approved' WHERE id = $visit_id");

            $scan_saved = false;
            if (!empty($data['scan_image'])) {
                $upload_dir = dirname(__DIR__) . '/assets/uploads/scans/known/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                $img     = str_replace([' ', 'data:image/jpeg;base64,'], ['+', ''], $data['scan_image']);
                $decoded = base64_decode($img);
                if (file_put_contents($upload_dir . "entry_{$entry_id}.jpg", $decoded)) {
                    $scan_saved = true;
                }
            }

            echo json_encode([
                "status"   => "success",
                "message"  => "Entry logged." . ($scan_saved ? " Scan image saved." : ""),
                "entry_id" => $entry_id
            ]);
            break;

        // ---------------------------------------------------------
        // LOG UNKNOWN SCAN
        // ---------------------------------------------------------
        case 'log_unknown':
            if (empty($data['scan_image'])) {
                echo json_encode(["status" => "error", "message" => "No image data."]);
                exit;
            }

            $upload_dir = dirname(__DIR__) . '/assets/uploads/scans/unknown/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $timestamp = time();
            $uid       = uniqid();
            $filename  = "unknown_{$timestamp}_{$uid}.jpg";

            $img     = str_replace([' ', 'data:image/jpeg;base64,'], ['+', ''], $data['scan_image']);
            $decoded = base64_decode($img);

            if (file_put_contents($upload_dir . $filename, $decoded)) {
                $log_file = $upload_dir . 'log.json';
                $log      = file_exists($log_file) ? json_decode(file_get_contents($log_file), true) : [];
                $log[]    = ['filename' => $filename, 'timestamp' => $timestamp, 'datetime' => date('Y-m-d H:i:s', $timestamp)];
                file_put_contents($log_file, json_encode($log));

                // Notify all active owners
                $owner_res = mysqli_query($conn, "SELECT id, email FROM users WHERE role_id = 1 AND status = 'active'");
                if ($owner_res) {
                    while ($owner = mysqli_fetch_assoc($owner_res)) {
                        $owner_id = $owner['id'];
                        $owner_email = $owner['email'];
                        
                        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link) 
                                            VALUES ($owner_id, 'Security Alert: Unknown Scan', 
                                                    'An unidentified person attempted to scan their face at the gate.', 
                                                    'owner/guest_entries.php')");
                                                    
                        // Send Email Notification
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
                            $mail->addAddress($owner_email, 'Owner');

                            $mail->isHTML(true);
                            $mail->Subject = 'Security Alert: Unidentified Person at Gate';
                            $formatted_time = date('Y-m-d H:i:s', $timestamp);
                            $mail->Body    = "<h3>Hello Owner,</h3>
                                              <p>An unidentified person attempted to scan their face at the gate. Please check the Nibash dashboard or guest entries for more details.</p>
                                              <p><b>Time:</b> {$formatted_time}</p>
                                              <p>Regards,<br>Nibash Security System</p>";
                            $mail->send();
                        } catch (Exception $e) {
                            // Log error or ignore
                        }
                    }
                }
                echo json_encode(["status" => "success", "message" => "Unknown scan saved."]);
            } else {
                echo json_encode(["status" => "error", "message" => "Failed to save image."]);
            }
            break;

        // ---------------------------------------------------------
        // DELETE UNKNOWN SCAN (owner-only, redirect-based action)
        // ---------------------------------------------------------
        case 'delete_unknown':
            session_start();
            if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
                header("Location: " . BASE_URL . "index.php?error=unauthorized");
                exit();
            }

            $filename    = basename($_GET['filename'] ?? '');
            $unknown_dir = $_SERVER['DOCUMENT_ROOT'] . '/Nibash/assets/uploads/scans/unknown/';
            $image_path  = $unknown_dir . $filename;
            $log_file    = $unknown_dir . 'log.json';

            if ($filename && file_exists($image_path) && is_file($image_path)) {
                unlink($image_path);
            }

            if (file_exists($log_file)) {
                $log_data    = json_decode(file_get_contents($log_file), true);
                $updated_log = array_values(array_filter((array)$log_data, fn($i) => $i['filename'] !== $filename));
                file_put_contents($log_file, json_encode($updated_log, JSON_PRETTY_PRINT));
            }

            header("Location: " . BASE_URL . "owner/guest_entries.php?success=deleted");
            exit();

        // ---------------------------------------------------------
        // ARCHIVE / DELETE GUEST PASS
        // ---------------------------------------------------------
        case 'archive_pass':
            session_start();
            if (!isset($_SESSION['user_id'])) {
                header("Location: " . BASE_URL . "index.php?error=unauthorized");
                exit();
            }

            $visit_id = (int)($_GET['visit_id'] ?? 0);
            $user_id  = $_SESSION['user_id'];
            $role_id  = $_SESSION['role_id'];

            if ($visit_id > 0) {
                $check_sql = "SELECT id, guest_id FROM visit_requests WHERE id = $visit_id";
                if ($role_id != 1) {
                    $check_sql .= " AND resident_id = $user_id";
                }
                $check_res = mysqli_query($conn, $check_sql);

                if ($check_res && mysqli_num_rows($check_res) > 0) {
                    $row      = mysqli_fetch_assoc($check_res);
                    $guest_id = (int)$row['guest_id'];

                    $logs_res = mysqli_query($conn, "SELECT id FROM entry_logs WHERE visit_id = $visit_id LIMIT 1");
                    if (mysqli_num_rows($logs_res) > 0) {
                        // Soft delete: guest scanned — cancel, don't erase
                        mysqli_query($conn, "UPDATE visit_requests SET status = 'Cancelled' WHERE id = $visit_id");
                    } else {
                        // Hard delete
                        mysqli_query($conn, "DELETE FROM guest_vehicles WHERE visit_id = $visit_id");
                        mysqli_query($conn, "DELETE FROM visit_requests WHERE id = $visit_id");
                        $other_visits = mysqli_query($conn, "SELECT id FROM visit_requests WHERE guest_id = $guest_id");
                        if ($other_visits && mysqli_num_rows($other_visits) == 0) {
                            mysqli_query($conn, "DELETE FROM guests WHERE id = $guest_id");
                        }
                    }

                    $redirect = ($role_id == 1) ? "owner/guest_entries.php" : "resident/guest_passes.php";
                    header("Location: " . BASE_URL . $redirect . "?success=deleted");
                    exit();
                }
            }

            $redirect = ($role_id == 1) ? "owner/guest_entries.php" : "resident/guest_passes.php";
            header("Location: " . BASE_URL . $redirect . "?error=failed");
            exit();

        default:
            echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . $action]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
