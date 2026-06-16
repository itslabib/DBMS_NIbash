<?php
session_start();
require_once '../includes/db_config.php';

// Ensure the user is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$owner_id = $_SESSION['user_id'];
$success  = false;
$error    = '';
$final_username = '';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../vendor/autoload.php';

// Fetch owner info
$owner_q = mysqli_query($conn,
    "SELECT u.email, a.building_id, a.id AS apt_id, b.building_number, b.building_name, b.address AS building_address
     FROM users u
     LEFT JOIN apartment_assignments aa ON aa.user_id = u.id AND aa.is_active = 1
     LEFT JOIN apartments a ON a.id = aa.apt_id
     LEFT JOIN buildings b ON a.building_id = b.id
     WHERE u.id = '$owner_id'
     LIMIT 1"
);
$owner_data      = mysqli_fetch_assoc($owner_q);
$owner_email     = $owner_data['email'] ?? '';
$owner_prefix    = explode('@', $owner_email)[0];
$owner_building_id = $owner_data['building_id'] ?? '';
$owner_apt_id    = $owner_data['apt_id'] ?? null;
$building_number = $owner_data['building_number'] ?? '';
$building_name   = $owner_data['building_name'] ?? '';
$building_addr   = $owner_data['building_address'] ?? '';

// Format resident address
$address_parts = array_filter([$building_name, $building_number, $building_addr]);
$resident_full_address = mysqli_real_escape_string($conn, implode(", ", $address_parts));

// Handle Edit Mode
$edit_id = isset($_GET['edit_id']) ? intval($_GET['edit_id']) : 0;
$edit_data = [];
$is_edit_mode = false;

if ($edit_id > 0) {
    // Verify owner rights to edit this user
    $verify_q = "SELECT u.email, p.*, a.apt_number, aa.monthly_rent, aa.start_date, rv.plate_number AS car_number 
                 FROM users u 
                 JOIN user_profiles p ON u.id = p.user_id 
                 JOIN apartment_assignments aa ON u.id = aa.user_id 
                 JOIN apartments a ON aa.apt_id = a.id
                 JOIN apartment_assignments aa_own ON a.id = aa_own.apt_id
                 LEFT JOIN resident_vehicles rv ON u.id = rv.user_id
                 WHERE u.id = $edit_id AND aa_own.user_id = $owner_id AND aa_own.role = 'owner' AND aa.role='tenant' AND aa.is_active=1";
    $verify_res = mysqli_query($conn, $verify_q);
    if (mysqli_num_rows($verify_res) > 0) {
        $edit_data = mysqli_fetch_assoc($verify_res);
        $is_edit_mode = true;
    }
}

// Handle POST insertion / update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name      = mysqli_real_escape_string($conn, trim($_POST['full_name']    ?? ''));
    $email          = mysqli_real_escape_string($conn, trim($_POST['email']        ?? ''));
    $phone          = mysqli_real_escape_string($conn, trim($_POST['phone']        ?? ''));
    $nid            = mysqli_real_escape_string($conn, trim($_POST['nid']          ?? ''));
    $dob            = mysqli_real_escape_string($conn, trim($_POST['dob']          ?? ''));
    $occupation     = mysqli_real_escape_string($conn, trim($_POST['occupation']   ?? ''));
    $apt_number     = mysqli_real_escape_string($conn, trim($_POST['apt_number']   ?? ''));
    $monthly_rent   = mysqli_real_escape_string($conn, trim($_POST['monthly_rent'] ?? ''));
    $start_date     = mysqli_real_escape_string($conn, trim($_POST['start_date']   ?? ''));
    $car_number     = mysqli_real_escape_string($conn, trim($_POST['car_number']   ?? ''));

    // Validate DOB (Must be 20 or older)
    $dob_valid = true;
    if (!empty($dob)) {
        $age = (new DateTime())->diff(new DateTime($dob))->y;
        if ($age < 20) {
            $dob_valid = false;
            $error = "Resident must be at least 20 years old.";
        }
    }

    // Validate Phone Format (exactly 11 digits)
    $phone_valid = preg_match('/^[0-9]{11}$/', $phone);
    if (!$phone_valid && !$error) {
        $error = "Phone number must be exactly 11 digits.";
    }

    // Validate NID Format (exactly 13 digits, if provided)
    $nid_valid = true;
    if (!empty($nid) && !preg_match('/^[0-9]{13}$/', $nid) && !$error) {
        $nid_valid = false;
        $error = "NID must be exactly 13 digits.";
    }

    // ==========================================
    // DUPLICATE DATA CHECKS (PREVENTS FATAL CRASHES)
    // ==========================================
    
    // 1. Check for Duplicate Email
    if (!$error) {
        $email_cond = $is_edit_mode ? "AND id != $edit_id" : "";
        $check_email = mysqli_query($conn, "SELECT id FROM users WHERE email = '$email' $email_cond");
        if (mysqli_num_rows($check_email) > 0) {
            $error = "This email is already registered to another account.";
        }
    }

    // 2. Check for Duplicate Phone Number
    if (!$error) {
        $phone_cond = $is_edit_mode ? "AND user_id != $edit_id" : "";
        $check_phone = mysqli_query($conn, "SELECT id FROM user_profiles WHERE phone = '$phone' $phone_cond");
        if (mysqli_num_rows($check_phone) > 0) {
            $error = "This phone number is already associated with another resident.";
        }
    }

    // 3. Check for Duplicate NID
    if (!$error && !empty($nid)) {
        $nid_cond = $is_edit_mode ? "AND user_id != $edit_id" : "";
        $check_nid = mysqli_query($conn, "SELECT id FROM user_profiles WHERE nid = '$nid' $nid_cond");
        if (mysqli_num_rows($check_nid) > 0) {
            $error = "This National ID (NID) is already registered in the system.";
        }
    }
    // ==========================================

    // Proceed only if no errors were found
    if (!$error) { 
        
        $apt_id = null;
        
        // 1. Check if the apartment ALREADY exists and belongs to you
        $apt_q = mysqli_query($conn, "SELECT a.id, a.status FROM apartments a JOIN apartment_assignments aa ON a.id = aa.apt_id WHERE aa.user_id = '$owner_id' AND aa.role = 'owner' AND aa.is_active = 1 AND a.apt_number = '$apt_number'");
        
        if (mysqli_num_rows($apt_q) > 0) {
            $apt_row = mysqli_fetch_assoc($apt_q);
            
            $is_current_occupant = false;
            if ($is_edit_mode) {
                $occ_check = mysqli_query($conn, "SELECT id FROM apartment_assignments WHERE apt_id = {$apt_row['id']} AND user_id = $edit_id AND role = 'tenant' AND is_active = 1");
                if (mysqli_num_rows($occ_check) > 0) {
                    $is_current_occupant = true;
                }
            }
            
            if ($apt_row['status'] !== 'available' && !$is_current_occupant) {
                $error = "Apartment '$apt_number' is already taken by someone.";
            } else {
                $apt_id = $apt_row['id']; // It exists and is available (or occupied by same user)!
            }
        } else {
            // 2. It doesn't exist yet! Let's AUTO-CREATE it in the database.
            mysqli_begin_transaction($conn);
            try {
                // Double check it doesn't belong to another owner in the same building
                $global_check = mysqli_query($conn, "SELECT id FROM apartments WHERE apt_number = '$apt_number' AND building_id = '$owner_building_id'");
                if (mysqli_num_rows($global_check) > 0) {
                    $error = "Apartment '$apt_number' is already taken by someone.";
                    mysqli_rollback($conn);
                } else {
                    // Create the apartment
                    mysqli_query($conn, "INSERT INTO apartments (building_id, apt_number, status) VALUES ('$owner_building_id', '$apt_number', 'available')");
                    $apt_id = mysqli_insert_id($conn);
                    
                    // Assign you as the owner
                    mysqli_query($conn, "INSERT INTO apartment_assignments (apt_id, user_id, role, start_date, is_active) VALUES ($apt_id, '$owner_id', 'owner', CURDATE(), 1)");
                    mysqli_commit($conn);
                }
            } catch (\Throwable $e) { // Using Throwable catches ALL fatal errors safely
                mysqli_rollback($conn);
                $error = "Failed to auto-create apartment: " . $e->getMessage();
            }
        }

        // 3. If we successfully found or created the apartment, add the resident!
        if (!$error && $apt_id) {
            
            // Handle Profile Image Upload
            $image_path = 'default_avatar.png'; // Fallback
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    $new_filename = uniqid() . '.' . $ext;
                    $upload_dir = '../assets/uploads/profiles/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_filename)) {
                        $image_path = $new_filename;
                    }
                }
            }

            mysqli_begin_transaction($conn);
            try {
                if ($is_edit_mode) {
                    // --- EDIT MODE ---
                    // Update user
                    mysqli_query($conn, "UPDATE users SET email = '$email' WHERE id = $edit_id");
                    
                    // Update profile
                    $dob_val = !empty($dob) ? "'$dob'" : "NULL";
                    $img_q = ($image_path !== 'default_avatar.png') ? ", profile_image = '$image_path'" : "";
                    mysqli_query($conn, "UPDATE user_profiles SET full_name = '$full_name', phone = '$phone', nid = '$nid', dob = $dob_val, occupation = '$occupation', address = '$resident_full_address', permanent_address = '$resident_full_address' $img_q WHERE user_id = $edit_id");
                    
                    // Release old apartment if it changed
                    $old_apt_id = $edit_data['apt_id'] ?? null;
                    if ($old_apt_id && $old_apt_id != $apt_id) {
                        mysqli_query($conn, "UPDATE apartment_assignments SET is_active = 0, end_date = CURDATE() WHERE user_id = $edit_id AND apt_id = $old_apt_id AND role = 'tenant'");
                        $active_tenants = mysqli_query($conn, "SELECT id FROM apartment_assignments WHERE apt_id = $old_apt_id AND is_active = 1 AND role = 'tenant'");
                        if(mysqli_num_rows($active_tenants) == 0){
                             mysqli_query($conn, "UPDATE apartments SET status = 'available' WHERE id = $old_apt_id");
                        }
                        
                        // Assign to new apartment
                        $rent_val = !empty($monthly_rent) ? "'$monthly_rent'" : "NULL";
                        $start_val = !empty($start_date) ? "'$start_date'" : "CURDATE()";
                        mysqli_query($conn, "INSERT INTO apartment_assignments (apt_id, user_id, role, start_date, monthly_rent) 
                                             VALUES ($apt_id, $edit_id, 'tenant', $start_val, $rent_val)");
                        mysqli_query($conn, "UPDATE apartments SET status = 'occupied' WHERE id = $apt_id");
                    } else {
                        // Just update rent/date
                        $rent_val = !empty($monthly_rent) ? "'$monthly_rent'" : "NULL";
                        $start_val = !empty($start_date) ? "'$start_date'" : "CURDATE()";
                        mysqli_query($conn, "UPDATE apartment_assignments SET monthly_rent = $rent_val, start_date = $start_val WHERE user_id = $edit_id AND apt_id = $apt_id AND role = 'tenant' AND is_active = 1");
                    }
                    
                    $success = true;
                    // End Edit Mode early to skip inserted items
                } else {
                    // --- INSERT MODE ---
                    // Generate a random password
                    $default_password = bin2hex(random_bytes(4)); // 8 characters
                    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);

                    // Insert user
                    mysqli_query($conn, "INSERT INTO users (email, password, role_id, is_verified) VALUES ('$email', '$hashed_password', 2, 1)");
                    $new_user_id = mysqli_insert_id($conn);
                    
                    // Generate username
                    $base_username = explode('@', $email)[0];
                    $check_un_q = mysqli_query($conn, "SELECT id FROM users WHERE username = '$base_username'");
                    if (mysqli_num_rows($check_un_q) > 0) {
                        $final_username = $base_username . $new_user_id;
                    } else {
                        $final_username = $base_username;
                    }
                    mysqli_query($conn, "UPDATE users SET username = '$final_username' WHERE id = $new_user_id");

                    // Insert profile (Using the new image_path)
                    $dob_val = !empty($dob) ? "'$dob'" : "NULL";
                    mysqli_query($conn, "INSERT INTO user_profiles (user_id, full_name, phone, nid, dob, occupation, profile_image, address, permanent_address) 
                                         VALUES ($new_user_id, '$full_name', '$phone', '$nid', $dob_val, '$occupation', '$image_path', '$resident_full_address', '$resident_full_address')");
                    
                    // Assign to apartment as a tenant
                    $rent_val = !empty($monthly_rent) ? "'$monthly_rent'" : "NULL";
                    $start_val = !empty($start_date) ? "'$start_date'" : "CURDATE()";
                    mysqli_query($conn, "INSERT INTO apartment_assignments (apt_id, user_id, role, start_date, monthly_rent) 
                                         VALUES ($apt_id, $new_user_id, 'tenant', $start_val, $rent_val)");
                    
                    // Mark apartment occupied
                    mysqli_query($conn, "UPDATE apartments SET status = 'occupied' WHERE id = $apt_id");
                    
                    // Create the initial rent bill
                    if (!empty($monthly_rent)) {
                        $month_name = date('F', strtotime($start_date));
                        $year = date('Y', strtotime($start_date));
                        $due_date = date('Y-m-d', strtotime($start_date . ' + 7 days'));
                        
                        mysqli_query($conn, "INSERT INTO bills (apt_id, resident_id, month, year, issue_date, due_date, subtotal, discount, tax, status, notes, created_by) 
                                             VALUES ($apt_id, '$new_user_id', '$month_name', '$year', '$start_date', '$due_date', '$monthly_rent', '0.00', '0.00', 'Pending', 'Initial Rent', '$owner_id')");
                        $bill_id = mysqli_insert_id($conn);
                        
                        // Link with a utility, normally rent
                        $rent_util_q = mysqli_query($conn, "SELECT id FROM utility_types WHERE utility_name LIKE '%Rent%' LIMIT 1");
                        if ($rent_util_q && mysqli_num_rows($rent_util_q) > 0) {
                            $rent_util_id = mysqli_fetch_assoc($rent_util_q)['id'];
                        } else {
                            mysqli_query($conn, "INSERT IGNORE INTO utility_types (utility_name) VALUES ('Rent')");
                            $rent_util_id = mysqli_insert_id($conn);
                            if (!$rent_util_id) {
                                $rent_util_q = mysqli_query($conn, "SELECT id FROM utility_types WHERE utility_name = 'Rent' LIMIT 1");
                                $rent_util_id = mysqli_fetch_assoc($rent_util_q)['id'];
                            }
                        }
                        
                        mysqli_query($conn, "INSERT INTO bill_items (bill_id, utility_type_id, item_name, quantity, unit_price, amount) 
                                             VALUES ($bill_id, $rent_util_id, 'Monthly Rent', 1, '$monthly_rent', '$monthly_rent')");
                    }

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
                        $mail->addAddress($email, $full_name);

                        $mail->isHTML(true);
                        $mail->Subject = 'Your Resident Account Created';
                        $mail->Body    = "Hello {$full_name},<br><br>Your resident account has been created for Apartment {$apt_number}.<br>Username: <b>{$final_username}</b><br>Password: <b>{$default_password}</b><br><br>Please find your initial invoice attached.<br><br>Please login and change your password.";

                        // Generate PDF Attachment if bill was created
                        if (!empty($monthly_rent) && isset($bill_id)) {
                            $template_path = '../pdf_templates/resident_bill_invoice.html';
                            if (file_exists($template_path)) {
                                $html = file_get_contents($template_path);
                                
                                $trx_id = 'INV-' . strtoupper(uniqid());
                                $payment_date = date('M j, Y h:i A');
                                $billing_month = (isset($month_name) ? $month_name : date('F')) . ' ' . (isset($year) ? $year : date('Y'));
                                $current_year = date('Y');
                                $total_amount_paid = 'Tk. ' . number_format((float)$monthly_rent, 2);
                                
                                $html = str_replace('{{TRANSACTION_ID}}', $trx_id, $html);
                                $html = str_replace('{{DATE_OF_PAYMENT}}', $payment_date, $html);
                                $html = str_replace('{{RESIDENT_NAME}}', $full_name, $html);
                                $html = str_replace('{{APT_NUMBER}}', $apt_number, $html);
                                $html = str_replace('{{BILLING_MONTH}}', $billing_month, $html);
                                $html = str_replace('{{CURRENT_YEAR}}', $current_year, $html);
                                
                                $items_html = '<tr>
                                                <td>Monthly Rent</td>
                                                <td class="amount">Tk. ' . number_format((float)$monthly_rent, 2) . '</td>
                                            </tr>';
                                $items_html .= '<tr>
                                                <td>Discount</td>
                                                <td class="amount text-rose">-Tk. 0.00</td>
                                            </tr>';
                                $items_html .= '<tr>
                                                <td>VAT / Tax</td>
                                                <td class="amount text-emerald">+Tk. 0.00</td>
                                            </tr>';
                                            
                                $start_tag = '<!-- Replace these rows dynamically from your PHP backend -->';
                                $end_tag = '<!-- Dynamic rows end -->';
                                $start_pos = strpos($html, $start_tag);
                                $end_pos = strpos($html, $end_tag);

                                if ($start_pos !== false && $end_pos !== false) {
                                    $end_pos += strlen($end_tag);
                                    $html = substr_replace($html, $items_html, $start_pos, $end_pos - $start_pos);
                                }

                                $html = str_replace('{{TOTAL_AMOUNT_PAID}}', $total_amount_paid, $html);

                                $options = new \Dompdf\Options();
                                $options->set('isHtml5ParserEnabled', true);
                                $options->set('isRemoteEnabled', true);

                                $dompdf = new \Dompdf\Dompdf($options);
                                $dompdf->loadHtml($html);
                                $dompdf->setPaper('A4', 'portrait');
                                $dompdf->render();
                                $pdf_output = $dompdf->output();
                                
                                $mail->addStringAttachment($pdf_output, 'Initial_Invoice_' . str_replace(' ', '_', $billing_month) . '.pdf', 'base64', 'application/pdf');
                            }
                        }

                        $mail->send();
                    } catch (Exception $e) {}

                    $success = true;
                }

                mysqli_commit($conn);
                
                // --- PARKING SLOT AUTO-ADD & VEHICLE RECORDING ---
                if (!empty($car_number)) {
                    $target_user_id = $is_edit_mode ? $edit_id : $new_user_id;

                    // Ensure vehicle is recorded in resident_vehicles (3NF)
                    $check_vehicle_q = mysqli_query($conn, "SELECT id FROM resident_vehicles WHERE user_id = $target_user_id AND plate_number = '$car_number' LIMIT 1");
                    if (mysqli_num_rows($check_vehicle_q) == 0) {
                        mysqli_query($conn, "INSERT INTO resident_vehicles (user_id, apt_id, plate_number) VALUES ($target_user_id, $apt_id, '$car_number')");
                    }

                    // Check if the apartment already has a parking slot assigned
                    $check_existing_q = mysqli_query($conn, "SELECT id FROM parking_slots WHERE apt_id = $apt_id AND building_id = '$owner_building_id' LIMIT 1");
                    
                    if (mysqli_num_rows($check_existing_q) == 0) {
                        // Find an existing unassigned parking slot
                        $free_slot_q = mysqli_query($conn, "SELECT id, slot_number FROM parking_slots WHERE building_id = '$owner_building_id' AND apt_id IS NULL ORDER BY id ASC LIMIT 1");
                        
                        if ($free_slot_q && mysqli_num_rows($free_slot_q) > 0) {
                            $free_slot = mysqli_fetch_assoc($free_slot_q);
                            $slot_id = $free_slot['id'];
                            mysqli_query($conn, "UPDATE parking_slots SET current_status = 'Occupied', apt_id = $apt_id, license_plate = '$car_number' WHERE id = $slot_id");
                        } else {
                            // Generate a new spot name like P-X if none available
                            $count_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM parking_slots WHERE building_id = '$owner_building_id' AND slot_number LIKE 'P-%'");
                            $cnt = mysqli_fetch_assoc($count_q)['cnt'];
                            $slot_number = 'P-' . ($cnt + 1);
                            while(mysqli_num_rows(mysqli_query($conn, "SELECT id FROM parking_slots WHERE building_id = '$owner_building_id' AND slot_number = '$slot_number'")) > 0) {
                                $cnt++;
                                $slot_number = 'P-' . ($cnt + 1);
                            }
                            mysqli_query($conn, "INSERT INTO parking_slots (building_id, slot_number, apt_id, license_plate, current_status) VALUES ('$owner_building_id', '$slot_number', $apt_id, '$car_number', 'Occupied')");
                        }
                    }
                }
            } catch (\Throwable $e) { // Using Throwable to safely catch any unexpected database panics
                mysqli_rollback($conn);
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }
}

// Prepare form values to retain input on error, or pre-fill for edit mode
$form_full_name = '';
$form_email = '';
$form_phone = '';
$form_nid = '';
$form_dob = '';
$form_occupation = '';
$form_car_number = '';
$form_apt_number = '';
$form_monthly_rent = '';
$form_start_date = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error)) {
    $form_full_name = $_POST['full_name'] ?? '';
    $form_email = (strpos(strtolower($error), 'email') === false) ? ($_POST['email'] ?? '') : '';
    $form_phone = (strpos(strtolower($error), 'phone') === false) ? ($_POST['phone'] ?? '') : '';
    $form_nid = (strpos(strtolower($error), 'nid') === false) ? ($_POST['nid'] ?? '') : '';
    $form_dob = $_POST['dob'] ?? '';
    $form_occupation = $_POST['occupation'] ?? '';
    $form_car_number = $_POST['car_number'] ?? '';
    $form_apt_number = (strpos(strtolower($error), 'apartment') === false) ? ($_POST['apt_number'] ?? '') : '';
    $form_monthly_rent = $_POST['monthly_rent'] ?? '';
    $form_start_date = $_POST['start_date'] ?? date('Y-m-d');
} elseif ($is_edit_mode) {
    $form_full_name = $edit_data['full_name'] ?? '';
    $form_email = $edit_data['email'] ?? '';
    $form_phone = $edit_data['phone'] ?? '';
    $form_nid = $edit_data['nid'] ?? '';
    $form_dob = $edit_data['dob'] ?? '';
    $form_occupation = $edit_data['occupation'] ?? '';
    $form_car_number = $edit_data['car_number'] ?? '';
    $form_apt_number = $edit_data['apt_number'] ?? '';
    $form_monthly_rent = $edit_data['monthly_rent'] ?? '';
    $form_start_date = $edit_data['start_date'] ?? date('Y-m-d');
}
?>
<?php
$resident_building_name = 'Nibash';
try {
    $uid_for_b = $_SESSION['user_id'] ?? 0;
    if ($uid_for_b) {
        $bq = @mysqli_query($conn, "SELECT b.building_name, b.building_number FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id JOIN buildings b ON a.building_id = b.id WHERE aa.user_id = '$uid_for_b' AND aa.is_active=1 LIMIT 1");
        if ($bq && mysqli_num_rows($bq) > 0) {
            $brow = mysqli_fetch_assoc($bq);
            $resident_building_name = !empty($brow['building_name']) ? $brow['building_name'] : $brow['building_number'];
        } else {
            $mq = @mysqli_query($conn, "SELECT b.building_name, b.building_number FROM building_managers bm JOIN buildings b ON bm.building_id = b.id WHERE bm.user_id = '$uid_for_b' LIMIT 1");
            if ($mq && mysqli_num_rows($mq) > 0) {
                $mrow = mysqli_fetch_assoc($mq);
                $resident_building_name = !empty($mrow['building_name']) ? $mrow['building_name'] : $mrow['building_number'];
            }
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_edit_mode ? 'Edit Resident' : 'Onboard Resident' ?> | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #ecfdf5; }
        ::-webkit-scrollbar-thumb { background: #a7f3d0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6ee7b7; }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php include '../includes/owner_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8">
        
        <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-emerald-100/60 hover:shadow-[0_4px_15px_-3px_rgba(16,185,129,0.15)] hover:border-emerald-200 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>

        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(16,185,129,0.15)] flex-1 flex flex-col overflow-hidden border border-emerald-100/80 relative">
            
            <header class="bg-white/80 backdrop-blur-xl border-b border-emerald-50 sticky top-0 z-40 shadow-sm">
                <div class="px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Resident Management</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto w-full bg-slate-50/50">
                <div class="max-w-5xl mx-auto space-y-10">
                    
                    <div class="flex items-center">
                        <a href="<?php echo BASE_URL; ?>owner/residents.php" class="flex items-center gap-2 text-sm font-black text-slate-500 hover:text-emerald-600 transition-colors">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Directory
                        </a>
                    </div>

                    <div class="mb-8 pb-6 border-b border-slate-200">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3 mb-3">
                            <?= $is_edit_mode ? 'Edit Resident Profile' : 'Onboard New Resident' ?>
                        </h1>
                        <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base">
                            <span class="p-1.5 bg-emerald-100 border border-emerald-200 rounded-lg"><i data-lucide="user-check" class="w-4 h-4 text-emerald-700"></i></span>
                            <?= $is_edit_mode ? 'Update resident information and lease assignment.' : 'Create a profile and assign a lease directly to an available apartment.' ?>
                        </p>
                    </div>

                    <?php if ($success): ?>
                        <div class="bg-white border border-emerald-200 rounded-[2rem] p-10 sm:p-14 text-center shadow-[0_8px_30px_-6px_rgba(16,185,129,0.15)] relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-full h-1 bg-emerald-400"></div>
                            
                            <div class="w-24 h-24 bg-emerald-50 border-4 border-emerald-100 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
                                <i data-lucide="check-circle" class="w-10 h-10 text-emerald-500"></i>
                            </div>
                            
                            <h2 class="text-3xl font-black text-slate-900 mb-3 tracking-tight">Resident Onboarded Successfully!</h2>
                            <p class="text-slate-500 font-medium mb-10 max-w-lg mx-auto">The resident profile has been securely created and the targeted apartment status has been updated to occupied.</p>
                            
                            <div class="bg-slate-50 border border-slate-200 rounded-2xl p-6 text-left max-w-sm mx-auto mb-10 shadow-inner">
                                <div class="flex justify-between items-center mb-4 pb-4 border-b border-slate-200/60">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Username</span>
                                    <span class="text-sm font-black font-mono text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1.5 rounded-lg shadow-sm"><?= htmlspecialchars($final_username) ?></span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500">Password</span>
                                    <span class="text-xs font-bold text-slate-600 bg-white border border-slate-200 px-3 py-1.5 rounded-lg shadow-sm"><i data-lucide="mail" class="w-3 h-3 inline"></i> Emailed to User</span>
                                </div>
                            </div>
                            
                            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                                <a href="add_resident.php" class="w-full sm:w-auto px-8 py-3.5 bg-white border border-slate-200 text-slate-600 font-black text-sm rounded-xl hover:bg-slate-50 hover:text-slate-900 transition-colors shadow-sm">Add Another</a>
                                <a href="residents.php" class="w-full sm:w-auto px-8 py-3.5 bg-slate-900 hover:bg-emerald-600 text-white font-black text-sm rounded-xl transition-colors shadow-md flex items-center justify-center gap-2">View Directory <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
                            </div>
                        </div>

                    <?php else: ?>

                        <?php if ($error): ?>
                            <div class="bg-rose-50 border border-rose-200 p-5 mb-8 rounded-[1.5rem] flex items-center gap-4 shadow-sm relative overflow-hidden">
                                <div class="absolute left-0 top-0 w-1 h-full bg-rose-500"></div>
                                <div class="w-10 h-10 rounded-full bg-white flex items-center justify-center border border-rose-100 shrink-0 shadow-sm">
                                    <i data-lucide="alert-triangle" class="text-rose-500 w-5 h-5"></i>
                                </div>
                                <div>
                                    <h4 class="text-sm font-black text-rose-800">Action Failed</h4>
                                    <p class="text-xs font-bold text-rose-600/80 mt-0.5"><?= htmlspecialchars($error) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
                            
                            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
                                <div class="bg-white border-b border-slate-100 px-8 py-6 flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shadow-sm">
                                        <i data-lucide="user" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-black text-slate-900">1. Personal Profile</h2>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mt-1">Identity & Contact Info</p>
                                    </div>
                                </div>
                                
                                <div class="p-8">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Full Name <span class="text-emerald-500">*</span></label>
                                            <input type="text" name="full_name" required placeholder="e.g. Sarah Connor" value="<?= htmlspecialchars($form_full_name) ?>"
                                                class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Email Address <span class="text-emerald-500">*</span></label>
                                            <input type="email" name="email" required placeholder="sarah@example.com" value="<?= htmlspecialchars($form_email) ?>"
                                                class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Phone Number <span class="text-emerald-500">*</span></label>
                                            <input type="tel" name="phone" required pattern="[0-9]{11}" title="Phone number must be exactly 11 digits" placeholder="e.g. 01712345678" value="<?= htmlspecialchars($form_phone) ?>"
                                                class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">National ID <span class="text-emerald-500">*</span></label>
                                            <input type="text" name="nid" pattern="[0-9]{13}" title="NID must be exactly 13 digits" placeholder="13-digit National ID" value="<?= htmlspecialchars($form_nid) ?>"
                                                class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm"
                                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13);">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Date of Birth <span class="text-slate-500 font-bold ml-1">(Optional)</span></label>
                                            <input type="date" name="dob" max="<?= date('Y-m-d', strtotime('-20 years')) ?>" value="<?= htmlspecialchars($form_dob) ?>"
                                                class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Occupation <span class="text-emerald-500">*</span></label>
                                            <input type="text" name="occupation" required placeholder="e.g. Software Engineer" value="<?= htmlspecialchars($form_occupation) ?>"
                                                class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Car Number <span class="text-slate-500 font-bold ml-1">(Optional)</span></label>
                                            <input type="text" name="car_number" placeholder="e.g. DHK-12-3456" value="<?= htmlspecialchars($form_car_number) ?>"
                                                class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Profile Picture <span class="text-emerald-500">*</span></label>
                                            <div class="relative w-full px-5 py-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center gap-3 hover:bg-slate-100 transition-colors shadow-sm">
                                                <input type="file" name="profile_image" accept="image/*" <?= $is_edit_mode ? '' : 'required' ?> class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('file-name').textContent = this.files[0] ? this.files[0].name : 'No file chosen'">
                                                <div class="px-4 py-2 bg-white text-slate-700 border border-slate-200 text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm">Choose File</div>
                                                <span id="file-name" class="text-xs font-bold text-slate-500 truncate">No file chosen</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
                                <div class="bg-white border-b border-slate-100 px-8 py-6 flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shadow-sm">
                                        <i data-lucide="file-text" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-black text-slate-900">2. Lease & Assignment</h2>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mt-1">Property Allocation</p>
                                    </div>
                                </div>
                                
                                <div class="p-8">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                                        <div class="md:col-span-1">
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Assign Apartment <span class="text-emerald-500">*</span></label>
                                            <div class="relative">
                                                <input type="text" name="apt_number" required placeholder="e.g. 5-B" value="<?= htmlspecialchars($form_apt_number) ?>"
                                                    class="w-full px-5 py-3.5 bg-emerald-50/50 border-2 border-emerald-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all font-black text-emerald-800 shadow-sm">
                                                <i data-lucide="home" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-emerald-600 pointer-events-none"></i>
                                            </div>
                                        </div>
                                        <div class="md:col-span-1">
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Monthly Rent <span class="text-slate-500 font-bold ml-1">(Optional)</span></label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <span class="text-xs font-black text-slate-500">Tk</span>
                                                </div>
                                                <input type="number" name="monthly_rent" placeholder="0.00" value="<?= htmlspecialchars($form_monthly_rent) ?>"
                                                    class="w-full pl-10 pr-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                            </div>
                                        </div>
                                        <div class="md:col-span-1">
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Start Date <span class="text-emerald-500">*</span></label>
                                            <input type="date" name="start_date" required value="<?= htmlspecialchars($form_start_date) ?>"
                                                class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-4 border-t border-slate-200">
                                <?php if (!$is_edit_mode): ?>
                                <p class="text-xs font-bold text-slate-500 flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center shadow-sm">
                                        <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500"></i>
                                    </span>
                                    Initial password is auto-generated securely.
                                </p>
                                <?php else: ?>
                                <p class="text-xs font-bold text-slate-500 flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center shadow-sm">
                                        <i data-lucide="image" class="w-4 h-4 text-indigo-500"></i>
                                    </span>
                                    Leave picture blank to keep current image.
                                </p>
                                <?php endif; ?>
                                
                                <div class="flex items-center gap-3 w-full sm:w-auto">
                                    <a href="residents.php" class="flex-1 sm:flex-none text-center px-10 py-3.5 bg-white border border-slate-200 text-slate-600 font-black text-sm rounded-xl hover:bg-slate-50 hover:text-slate-800 transition-colors shadow-sm">
                                        Cancel
                                    </a>
                                    <button type="submit" class="flex-1 sm:flex-none px-10 py-3.5 bg-slate-900 hover:bg-emerald-600 text-white font-black text-sm rounded-xl shadow-md hover:shadow-lg hover:shadow-emerald-500/30 transition-all flex items-center justify-center gap-2 group">
                                        <?= $is_edit_mode ? 'Save Changes' : 'Create Account' ?> <i data-lucide="<?= $is_edit_mode ? 'save' : 'arrow-right' ?>" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                                    </button>
                                </div>
                            </div>

                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>js/owner_logic.js"></script>
    <script>lucide.createIcons();</script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>