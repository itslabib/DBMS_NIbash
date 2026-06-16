<?php
session_start();
require_once '../includes/db_config.php';

// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Require Composer's autoloader
require_once '../vendor/autoload.php';

$error = '';
$success = '';
$error_step = 1; // Default to step 1. We will change this to keep the user on the correct step if an error occurs.

// Initialize variables so the form doesn't throw warnings on first load
$email = $name = $phone = $nid_number = $dob = $occupation = '';
$permanent_address = $building_number = $building_name = $apartment_name = $unit_number = $floor_number = '';

// Fetch all existing buildings to populate dropdown for new owners joining an existing building
$existing_buildings = [];
$b_sql = mysqli_query($conn, "SELECT id, building_number, address FROM buildings ORDER BY building_number ASC");
if ($b_sql) {
    while ($r = mysqli_fetch_assoc($b_sql)) {
        $existing_buildings[] = $r;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Extract variables
    extract(array_map('trim', array_intersect_key($_POST, array_flip(['email', 'name', 'phone', 'nid_number', 'dob', 'occupation', 'permanent_address', 'building_number', 'apartment_name', 'unit_number', 'building_name']))));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $latitude = !empty($_POST['latitudes']) ? (float)$_POST['latitudes'] : null;
    $longitude = !empty($_POST['longitudes']) ? (float)$_POST['longitudes'] : null;

    $birth_year = !empty($dob) ? (int) date('Y', strtotime($dob)) : 0;

    // --- Validation Logic ---
    if (empty($email) || empty($password) || empty($name) || empty($phone) || empty($nid_number) || empty($dob) || empty($permanent_address) || empty($building_number) || empty($unit_number)) {
        $error = "Please fill in all required fields.";
        $error_step = 1;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
        $email = ''; // Clear the invalid email
        $error_step = 1;
    } elseif (!preg_match('/^[0-9]{11}$/', $phone)) {
        $error = "Phone number must be exactly 11 digits.";
        $phone = ''; // Clear the invalid phone
        $error_step = 1;
    } elseif (!preg_match('/^[0-9]{13}$/', $nid_number)) {
        $error = "NID must be exactly 13 digits.";
        $nid_number = ''; // Clear the invalid NID
        $error_step = 1;
    } elseif ($birth_year >= 2000) {
        $error = "Date of birth year must be before 2000 (must be over 25 years old).";
        $dob = ''; // Clear invalid dob
        $error_step = 1;
    } elseif (!preg_match('/^[0-9A-Za-z\/\-\.\s]+$/', $building_number)) {
        $error = "Building Number format is invalid. Use formats like 729/A.";
        $building_number = ''; // Clear invalid building number
        $error_step = 2;
    } elseif (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] != 0) {
        $error = "Profile image is required.";
        $error_step = 1;
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
        $error_step = 3;
    } elseif (strlen($password) < 6 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = "Password must be at least 6 characters and include a lowercase letter, an uppercase letter, and a digit.";
        $error_step = 3;
    } 
    // --- Database Duplicate Checks ---
    else {
        // 1. Check if Email already exists
        $stmt_email = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt_email, "s", $email);
        mysqli_stmt_execute($stmt_email);
        mysqli_stmt_store_result($stmt_email);
        
        if (mysqli_stmt_num_rows($stmt_email) > 0) {
            $error = "An account with this Email already exists.";
            $email = ''; // Clear ONLY the email field
            $error_step = 1;
        }
        mysqli_stmt_close($stmt_email);

        // 2. Check if Phone already exists
        if (empty($error)) {
            $stmt_phone = mysqli_prepare($conn, "SELECT id FROM user_profiles WHERE phone = ?");
            mysqli_stmt_bind_param($stmt_phone, "s", $phone);
            mysqli_stmt_execute($stmt_phone);
            mysqli_stmt_store_result($stmt_phone);
            
            if (mysqli_stmt_num_rows($stmt_phone) > 0) {
                $error = "This Phone Number is already registered to another user.";
                $phone = ''; // Clear ONLY the phone field
                $error_step = 1;
            }
            mysqli_stmt_close($stmt_phone);
        }

        // 3. Check if NID already exists
        if (empty($error)) {
            $stmt_nid = mysqli_prepare($conn, "SELECT id FROM user_profiles WHERE nid = ?");
            mysqli_stmt_bind_param($stmt_nid, "s", $nid_number);
            mysqli_stmt_execute($stmt_nid);
            mysqli_stmt_store_result($stmt_nid);
            
            if (mysqli_stmt_num_rows($stmt_nid) > 0) {
                $error = "This NID Number is already registered in the system.";
                $nid_number = ''; // Clear ONLY the NID field
                $error_step = 1;
            }
            mysqli_stmt_close($stmt_nid);
        }

        // If no duplicates found, proceed with registration
        if (empty($error)) {
            $profile_image = '';
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $new_filename = uniqid() . '.' . $ext;
                $upload_dir = '../assets/uploads/profiles/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_filename)) {
                    $profile_image = $new_filename;
                } else {
                    $error = "Error uploading image.";
                    $error_step = 1;
                }
            } else {
                $error = "Invalid image format. Allowed: jpg, jpeg, png, gif.";
                $error_step = 1;
            }

            if (empty($error)) {
                $role_check = mysqli_query($conn, "SELECT id FROM roles WHERE role_name='Owner' LIMIT 1");
                $role_id = ($role_check && mysqli_num_rows($role_check) > 0) ? mysqli_fetch_assoc($role_check)['id'] : 1;
                if (!($role_check && mysqli_num_rows($role_check) > 0))
                    mysqli_query($conn, "INSERT IGNORE INTO roles (id,role_name) VALUES (1,'Owner'),(2,'Resident')");
                
                // === AUTO-GENERATE UNIQUE USERNAME ===
                $base_username = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0]));
                if (empty($base_username)) $base_username = 'owner';
                
                $final_username = $base_username;
                $counter = 1;
                
                while (true) {
                    $un_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
                    mysqli_stmt_bind_param($un_stmt, "s", $final_username);
                    mysqli_stmt_execute($un_stmt);
                    mysqli_stmt_store_result($un_stmt);
                    $exists = mysqli_stmt_num_rows($un_stmt) > 0;
                    mysqli_stmt_close($un_stmt);
                    
                    if (!$exists) break; 
                    
                    $final_username = $base_username . $counter;
                    $counter++;
                }

                mysqli_begin_transaction($conn);
                try {
                    $otp = (string) rand(100000, 999999);
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    $s = mysqli_prepare($conn, "INSERT INTO users (username, email, password, role_id, is_verified, verification_code, status) VALUES (?, ?, ?, ?, 0, ?, 'inactive')");
                    mysqli_stmt_bind_param($s, "sssis", $final_username, $email, $hashed_password, $role_id, $otp);
                    mysqli_stmt_execute($s);
                    $user_id = mysqli_insert_id($conn);

                    $s2 = mysqli_prepare($conn, "INSERT INTO user_profiles (user_id,full_name,phone,nid,dob,profile_image,occupation,permanent_address) VALUES (?,?,?,?,?,?,?,?)");
                    mysqli_stmt_bind_param($s2, "isssssss", $user_id, $name, $phone, $nid_number, $dob, $profile_image, $occupation, $permanent_address);
                    mysqli_stmt_execute($s2);

                    $b_stmt = mysqli_prepare($conn, "SELECT id FROM buildings WHERE building_number = ? LIMIT 1");
                    mysqli_stmt_bind_param($b_stmt, "s", $building_number);
                    mysqli_stmt_execute($b_stmt);
                    mysqli_stmt_store_result($b_stmt);
                    
                    if (mysqli_stmt_num_rows($b_stmt) > 0) {
                        mysqli_stmt_bind_result($b_stmt, $existing_b_id);
                        mysqli_stmt_fetch($b_stmt);
                        $final_building_id = $existing_b_id;
                    } else {
                        if (empty($building_name)) {
                            throw new Exception("Building Name is required for new buildings.");
                        }
                        $area_val = '';
                        $b_ins = mysqli_prepare($conn, "INSERT INTO buildings (building_number, building_name, address, area, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($b_ins, "ssssdd", $building_number, $building_name, $permanent_address, $area_val, $latitude, $longitude);
                        mysqli_stmt_execute($b_ins);
                        $final_building_id = mysqli_insert_id($conn);

                        // Assign this first owner as building admin
                        $m_ins = mysqli_prepare($conn, "INSERT INTO building_managers (building_id, user_id, role) VALUES (?, ?, 'admin')");
                        mysqli_stmt_bind_param($m_ins, "ii", $final_building_id, $user_id);
                        mysqli_stmt_execute($m_ins);
                    }
                    mysqli_stmt_close($b_stmt);

                    $s3 = mysqli_prepare($conn, "INSERT INTO apartments (building_id, apt_number, floor_number) VALUES (?, ?, ?)");
                    preg_match('/\d+/', $unit_number, $matches);
                    $fi = !empty($matches) ? (int)$matches[0] : 1;
                    mysqli_stmt_bind_param($s3, "isi", $final_building_id, $unit_number, $fi);
                    mysqli_stmt_execute($s3);
                    $new_apt_id = mysqli_insert_id($conn);

                    $s4 = mysqli_prepare($conn, "INSERT INTO apartment_assignments (apt_id, user_id, role, start_date, is_active) VALUES (?, ?, 'owner', CURDATE(), 1)");
                    mysqli_stmt_bind_param($s4, "ii", $new_apt_id, $user_id);
                    mysqli_stmt_execute($s4);

                    mysqli_commit($conn);

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
                        $mail->Subject = 'Verify your Nibash Account';
                        $mail->Body    = "Hello {$name},<br><br>Thank you for registering. Your verification code is: <b style='font-size:20px; color:#10b981;'>{$otp}</b><br><br>Your automatically assigned username is: <b>{$final_username}</b><br><br>Please enter this code on the verification page to activate your account.";

                        $mail->send();
                        
                        $success = true;
                        $created_username = $final_username;
                        $created_email = $email;
                        $created_building = $building_number;
                        
                    } catch (Exception $e) {
                        $error = "Account created successfully, but the verification email could not be sent.";
                    }

                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = "Error: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Registration | Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .input-clean { display: block; width: 100%; padding: 0.65rem 1rem; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; color: #1e293b; font-size: 0.875rem; outline: none; transition: all 0.2s ease; }
        .input-clean:focus { border-color: #10b981; box-shadow: 0 0 0 1px #10b981; }
        .input-clean::placeholder { color: #94a3b8; }
        .label-clean { display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 0.375rem; }
        .step-hidden { display: none; }
        .step-indicator { transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-[#fafcfa] text-slate-900 min-h-screen antialiased relative overflow-x-hidden">

    <div class="fixed inset-0 pointer-events-none z-0">
        <div class="absolute top-0 right-0 w-[40vw] h-[40vw] rounded-full bg-emerald-50/60 blur-3xl translate-x-1/3 -translate-y-1/3"></div>
        <div class="absolute bottom-0 left-0 w-[30vw] h-[30vw] rounded-full bg-emerald-100/40 blur-3xl -translate-x-1/3 translate-y-1/3"></div>
    </div>

    <header class="relative z-50 w-full px-6 py-6 md:px-12 flex items-center justify-between">
        <div class="flex items-center gap-4 md:gap-6">
            <a href="../index.php" class="w-10 h-10 flex items-center justify-center rounded-full bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 hover:text-slate-700 transition-all shadow-sm group" aria-label="Go Back">
                <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform"></i>
            </a>
            
            <a href="../index.php" class="flex items-center gap-2 transition-opacity hover:opacity-80">
                <div class="w-6 h-6 border-4 border-emerald-600 rounded-full flex items-center justify-center"></div>
                <span class="font-bold text-lg tracking-tight text-slate-800">Nibash</span>
            </a>
        </div>
        <div class="text-sm font-medium text-slate-600 hidden sm:block">
            Already a member? <a href="../login.php" class="text-emerald-600 font-bold hover:text-emerald-700 transition-colors">Login</a>
        </div>
    </header>

    <main class="relative z-10 pt-4 pb-20 px-4">
        <div class="max-w-4xl mx-auto">

            <div class="text-center mb-10">
                <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mb-2">Let's get you started</h1>
                <p class="text-slate-500 text-sm">Enter the details to register your building</p>
            </div>

            <?php if ($success): ?>
                <div class="max-w-2xl mx-auto bg-white border border-emerald-100 rounded-2xl p-8 shadow-sm text-center">
                    <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="mail" class="w-8 h-8 text-emerald-600"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-slate-900 mb-2">Verification Email Sent!</h2>
                    <p class="text-slate-500 text-sm mb-8">We've sent a 6-digit verification code to <b><?php echo htmlspecialchars($created_email); ?></b>. Please verify your account to continue.</p>

                    <div class="bg-slate-50 border border-slate-100 rounded-xl p-6 mb-8 text-left inline-block w-full max-w-md">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-slate-500 text-sm">Auto-Generated Username</span>
                            <span class="text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded font-bold font-mono text-sm"><?php echo htmlspecialchars($created_username); ?></span>
                        </div>
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-slate-500 text-sm">Password</span>
                            <span class="text-slate-900 font-medium font-mono text-sm">••••••••</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-500 text-sm">Building No.</span>
                            <span class="text-emerald-600 font-semibold font-mono text-sm"><?php echo htmlspecialchars($created_building); ?></span>
                        </div>
                    </div>

                    <div>
                        <a href="../verify.php?email=<?php echo urlencode($created_email); ?>"
                            class="inline-flex items-center justify-center px-8 py-2.5 bg-emerald-600 text-white font-medium rounded-lg hover:bg-emerald-700 transition-colors text-sm w-full max-w-xs gap-2">
                            Verify Account <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <?php if ($error): ?>
                    <div class="max-w-3xl mx-auto mb-8 p-4 bg-red-50 border border-red-100 rounded-xl flex items-center gap-3">
                        <i data-lucide="info" class="w-5 h-5 text-red-500"></i>
                        <p class="text-sm text-red-700 font-medium"><?= htmlspecialchars($error) ?></p>
                    </div>
                <?php endif; ?>

                <form id="registrationForm" action="" method="POST" enctype="multipart/form-data" class="max-w-3xl mx-auto">

                    <div class="flex items-center justify-center mb-12 overflow-x-auto pb-2">
                        <div id="ind-step-1" class="step-indicator flex items-center gap-2 text-emerald-700 font-medium text-sm whitespace-nowrap">
                            <span id="circle-step-1" class="w-6 h-6 rounded-full border-2 border-emerald-600 bg-emerald-600 text-white flex items-center justify-center text-xs transition-colors">1</span>
                            Personal Details
                        </div>
                        <div class="w-12 md:w-24 h-[1px] bg-slate-200 mx-3 md:mx-6"></div>

                        <div id="ind-step-2" class="step-indicator flex items-center gap-2 text-slate-400 font-medium text-sm whitespace-nowrap">
                            <span id="circle-step-2" class="w-6 h-6 rounded-full border-2 border-slate-200 bg-transparent flex items-center justify-center text-xs transition-colors">2</span>
                            Building Details
                        </div>
                        <div class="w-12 md:w-24 h-[1px] bg-slate-200 mx-3 md:mx-6"></div>

                        <div id="ind-step-3" class="step-indicator flex items-center gap-2 text-slate-400 font-medium text-sm whitespace-nowrap">
                            <span id="circle-step-3" class="w-6 h-6 rounded-full border-2 border-slate-200 bg-transparent flex items-center justify-center text-xs transition-colors">3</span>
                            Security
                        </div>
                    </div>

                    <div class="bg-transparent space-y-8">

                        <div id="step-1-content">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                                <div>
                                    <label class="label-clean">Full Name*</label>
                                    <input type="text" name="name" required placeholder="Enter your full name" class="input-clean" value="<?= htmlspecialchars($name) ?>">
                                </div>
                                <div>
                                    <label class="label-clean">Email Address*</label>
                                    <input type="email" name="email" required placeholder="john@example.com" class="input-clean" value="<?= htmlspecialchars($email) ?>">
                                </div>
                                <div>
                                    <label class="label-clean">Phone Number*</label>
                                    <input type="tel" name="phone" required pattern="[0-9]{11}" title="Phone number must be exactly 11 digits" placeholder="e.g. 01712345678" class="input-clean" value="<?= htmlspecialchars($phone) ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);">
                                </div>
                                <div>
                                    <label class="label-clean">NID Number*</label>
                                    <input type="text" name="nid_number" required pattern="[0-9]{13}" title="NID must be exactly 13 digits" placeholder="13-digit National ID" class="input-clean" value="<?= htmlspecialchars($nid_number) ?>" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 13);">
                                </div>
                                <div>
                                    <label class="label-clean">Date of Birth*</label>
                                    <input type="date" name="dob" required max="1999-12-31" class="input-clean text-slate-600" value="<?= htmlspecialchars($dob) ?>">
                                </div>
                                <div>
                                    <label class="label-clean">Occupation</label>
                                    <input type="text" name="occupation" placeholder="Enter your occupation" class="input-clean" value="<?= htmlspecialchars($occupation) ?>">
                                </div>
                                <div class="md:col-span-2">
                                    <label class="label-clean">Profile Image*</label>
                                    <input type="file" name="profile_image" required accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer border border-slate-200 rounded-lg bg-white p-1">
                                    <?php if(isset($_FILES['profile_image']) && !empty($_FILES['profile_image']['name'])): ?>
                                        <p class="text-xs text-amber-600 mt-1"><i data-lucide="info" class="w-3 h-3 inline"></i> Please re-select your image.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="pt-8 flex justify-end border-t border-slate-100 mt-8">
                                <button type="button" onclick="nextStep(1, 2)" class="px-8 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                                    Next Step <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <div id="step-2-content" class="step-hidden">
                            <div class="mb-6 bg-emerald-50 border border-emerald-100 p-4 rounded-xl flex items-start gap-3">
                                <i data-lucide="info" class="w-5 h-5 text-emerald-600 mt-0.5"></i>
                                <div>
                                    <p class="text-sm font-medium text-emerald-800">Establish your Building Profile</p>
                                    <p class="text-xs text-emerald-600 mt-1">Select your building if it's already registered by another owner, or register a new one.</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                                <div class="md:col-span-2">
                                    <label class="label-clean">Building Selection*</label>
                                    <select id="building_selector" class="input-clean cursor-pointer mb-2" onchange="toggleBuildingMode(this.value)">
                                        <option value="new">+ Register New Building</option>
                                        <optgroup label="Existing Buildings">
                                            <?php foreach($existing_buildings as $b): ?>
                                                <option value="<?= htmlspecialchars($b['building_number']) ?>" data-address="<?= htmlspecialchars($b['address']) ?>">
                                                    <?= htmlspecialchars($b['building_number']) ?> (<?= htmlspecialchars(substr($b['address'] ?? '', 0, 30)) ?>...)
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </div>
                                <div id="building_number_container">
                                    <label class="label-clean">Building Number/Block*</label>
                                    <input type="text" id="building_number" name="building_number" required placeholder="e.g., 729/A" pattern="[0-9A-Za-z\/\-\.\s]+" class="input-clean" value="<?= htmlspecialchars($building_number) ?>">
                                </div>
                                <div id="building_name_container">
                                    <label class="label-clean">Building Name*</label>
                                    <input type="text" id="building_name" name="building_name" placeholder="e.g., Sunrise Tower" class="input-clean" value="<?= htmlspecialchars($building_name) ?>">
                                </div>
                                <div>
                                    <label class="label-clean">Unit No.*</label>
                                    <input type="text" name="unit_number" required placeholder="e.g., A-101" class="input-clean" value="<?= htmlspecialchars($unit_number) ?>">
                                </div>

                                <div class="md:col-span-2" id="building_address_container">
                                    <label class="label-clean">Full Address*</label>
                                    <div class="flex gap-2 mb-2">
                                        <input type="text" id="permanent_address" name="permanent_address" required placeholder="Enter full building address (e.g. Mirpur, Dhaka)" class="input-clean" value="<?= htmlspecialchars($permanent_address) ?>">
                                        <button type="button" onclick="verifyAddress()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg transition-colors whitespace-nowrap">Verify</button>
                                    </div>
                                    <p id="address_error" class="text-xs text-red-500 hidden mt-1"></p>
                                    <p id="address_success" class="text-xs text-emerald-600 hidden mt-1 flex items-center gap-1"><i data-lucide="check-circle" class="w-3 h-3"></i> Address successfully located in Bangladesh!</p>
                                    
                                    <input type="hidden" name="latitudes" id="latitudes" value="">
                                    <input type="hidden" name="longitudes" id="longitudes" value="">
                                    <input type="hidden" id="address_verified" value="0">
                                </div>
                            </div>

                            <script>
                                function toggleBuildingMode(val) {
                                    const nameInput = document.getElementById('building_number');
                                    const bNameInput = document.getElementById('building_name');
                                    const bNameContainer = document.getElementById('building_name_container');
                                    const addressContainer = document.getElementById('building_address_container');
                                    const addressInput = document.getElementById('permanent_address');
                                    const opt = document.querySelector(`#building_selector option[value="${val.replace(/"/g, '\\"')}"]`);
                                    
                                    if(val === 'new') {
                                        nameInput.value = '';
                                        nameInput.readOnly = false;
                                        bNameInput.required = true;
                                        bNameContainer.style.display = 'block';
                                        addressInput.value = '';
                                        addressInput.required = true;
                                        addressContainer.style.display = 'block';
                                        document.getElementById('address_verified').value = "0";
                                    } else {
                                        nameInput.value = val;
                                        nameInput.readOnly = true;
                                        bNameInput.required = false;
                                        bNameContainer.style.display = 'none';
                                        if (opt && opt.dataset.address) {
                                            addressInput.value = opt.dataset.address;
                                        }
                                        addressInput.required = false;
                                        addressContainer.style.display = 'none';
                                        document.getElementById('address_verified').value = "1"; // Existing building needs no verify
                                    }
                                }
                            </script>

                            <div class="pt-8 flex items-center justify-between border-t border-slate-100 mt-8">
                                <button type="button" onclick="prevStep(2, 1)" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                                </button>
                                <button type="button" onclick="nextStep(2, 3)" class="px-8 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                                    Next Step <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <div id="step-3-content" class="step-hidden">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                                <div>
                                    <label class="label-clean">Password*</label>
                                    <div class="relative">
                                        <input type="password" id="password" name="password" required pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}" title="Must contain at least 6 characters, including one uppercase letter, one lowercase letter, and one number" placeholder="Create a strong password" class="input-clean pr-10">
                                        <button type="button" onclick="togglePasswordVisibility('password', 'eye-icon-pass')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-600 transition-colors focus:outline-none">
                                            <i data-lucide="eye" id="eye-icon-pass" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="label-clean">Confirm Password*</label>
                                    <div class="relative">
                                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm your password" class="input-clean pr-10">
                                        <button type="button" onclick="togglePasswordVisibility('confirm_password', 'eye-icon-confirm')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-600 transition-colors focus:outline-none">
                                            <i data-lucide="eye" id="eye-icon-confirm" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                    <p id="password-error" class="text-red-500 text-xs mt-1 hidden">Passwords do not match.</p>
                                </div>
                            </div>

                            <div class="pt-8 flex items-center justify-between border-t border-slate-100 mt-8">
                                <button type="button" onclick="prevStep(3, 2)" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                                </button>
                                <button type="submit" onclick="return validateFinalStep()" class="px-8 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                                    Submit & Register
                                </button>
                            </div>
                        </div>

                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <script>
        lucide.createIcons();

        // Check if PHP passed an error step to automatically open
        const errorStep = <?= $error_step ?>;
        
        if(errorStep > 1) {
            // Hide step 1, show the error step, and update UI
            document.getElementById('step-1-content').classList.add('step-hidden');
            document.getElementById(`step-${errorStep}-content`).classList.remove('step-hidden');
            updateStepperUI(errorStep);
        }

        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.setAttribute('data-lucide', 'eye-off');
            } else {
                input.type = 'password';
                icon.setAttribute('data-lucide', 'eye');
            }
            lucide.createIcons();
        }

        function validateCurrentStep(stepNumber) {
            const currentStep = document.getElementById(`step-${stepNumber}-content`);
            const inputs = currentStep.querySelectorAll('input[required], textarea[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    isValid = false;
                }
            });

            return isValid;
        }

        function updateStepperUI(currentStep) {
            for (let i = 1; i <= 3; i++) {
                document.getElementById(`ind-step-${i}`).classList.replace('text-emerald-700', 'text-slate-400');
                const circle = document.getElementById(`circle-step-${i}`);
                circle.classList.remove('border-emerald-600', 'bg-emerald-600', 'text-white', 'bg-emerald-50', 'text-emerald-600');
                circle.classList.add('border-slate-200', 'bg-transparent');
                circle.innerHTML = i;
            }

            for (let i = 1; i < currentStep; i++) {
                document.getElementById(`ind-step-${i}`).classList.replace('text-slate-400', 'text-emerald-700');
                const circle = document.getElementById(`circle-step-${i}`);
                circle.classList.remove('border-slate-200', 'bg-transparent');
                circle.classList.add('border-emerald-600', 'bg-emerald-600', 'text-white');
                circle.innerHTML = '<i data-lucide="check" class="w-3 h-3"></i>';
            }

            document.getElementById(`ind-step-${currentStep}`).classList.replace('text-slate-400', 'text-emerald-700');
            const activeCircle = document.getElementById(`circle-step-${currentStep}`);
            activeCircle.classList.remove('border-slate-200', 'bg-transparent');
            activeCircle.classList.add('border-emerald-600', 'bg-emerald-600', 'text-white');

            lucide.createIcons();
        }

        function nextStep(current, next) {
            if (validateCurrentStep(current)) {
                document.getElementById(`step-${current}-content`).classList.add('step-hidden');
                document.getElementById(`step-${next}-content`).classList.remove('step-hidden');
                updateStepperUI(next);
                window.scrollTo(0, 0);
            }
        }

        function prevStep(current, prev) {
            document.getElementById(`step-${current}-content`).classList.add('step-hidden');
            document.getElementById(`step-${prev}-content`).classList.remove('step-hidden');
            updateStepperUI(prev);
            window.scrollTo(0, 0);
        }

        function validateFinalStep() {
            if (!validateCurrentStep(3)) return false;

            const pass = document.getElementById('password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            const errorMsg = document.getElementById('password-error');

            if (pass !== confirmPass) {
                errorMsg.classList.remove('hidden');
                return false;
            } else {
                errorMsg.classList.add('hidden');
                return true;
            }
        }

        async function verifyAddress() {
            const addressInput = document.getElementById('permanent_address').value.trim();
            const addrError = document.getElementById('address_error');
            const addrSuccess = document.getElementById('address_success');
            
            if (!addressInput) {
                addrError.textContent = "Please enter an address to verify.";
                addrError.classList.remove('hidden');
                addrSuccess.classList.add('hidden');
                return;
            }

            addrError.classList.add('hidden');
            addrSuccess.classList.add('hidden');
            document.getElementById('address_verified').value = "0";

            try {
                // Geocode via Nominatim
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(addressInput)}&countrycodes=bd`);
                const data = await res.json();
                
                if (data && data.length > 0) {
                    const lat = data[0].lat;
                    const lon = data[0].lon;
                    
                    document.getElementById('latitudes').value = lat;
                    document.getElementById('longitudes').value = lon;
                    document.getElementById('address_verified').value = "1";
                    
                    addrSuccess.classList.remove('hidden');
                } else {
                    addrError.textContent = `Location not found in Bangladesh. Please add a larger area like your city/district (e.g., "${addressInput}, Dhaka").`;
                    addrError.classList.remove('hidden');
                }
            } catch(e) {
                addrError.textContent = "Error verifying location. Please try again.";
                addrError.classList.remove('hidden');
            }
        }
    </script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>