<?php
session_start();
require_once '../includes/db_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../vendor/autoload.php';

$error = '';
$success = '';
$error_step = 1;

// Initialize variables
$name = $email = $phone = $nid_number = $address = $availability_schedule = $default_pricing = $category_id = '';
$latitudes = [];
$longitudes = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    extract(array_map('trim', array_intersect_key($_POST, array_flip(['name', 'email', 'phone', 'nid_number', 'address', 'availability_schedule', 'default_pricing', 'category_id']))));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $latitudes_post = $_POST['latitudes'] ?? '';
    $longitudes_post = $_POST['longitudes'] ?? '';
    $address_verified = $_POST['address_verified'] ?? '0';

    if (empty($name) || empty($email) || empty($phone) || empty($nid_number) || empty($address) || empty($category_id) || empty($password)) {
        $error = "Please fill in all required fields.";
        $error_step = 1;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
        $email = '';
        $error_step = 1;
    } elseif (!preg_match('/^[0-9]{11}$/', $phone)) {
        $error = "Phone number must be exactly 11 digits.";
        $phone = '';
        $error_step = 1;
    } elseif (!preg_match('/^[0-9]{13}$/', $nid_number)) {
        $error = "NID must be exactly 13 digits.";
        $nid_number = '';
        $error_step = 1;
    } elseif ($address_verified !== '1' || empty($latitudes_post) || empty($longitudes_post)) {
        $error = "Please verify your address(es) on the map before registering.";
        $error_step = 2;
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
        $error_step = 2;
    } else {
        // Check duplicates
        $stmt_email = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt_email, "s", $email);
        mysqli_stmt_execute($stmt_email);
        mysqli_stmt_store_result($stmt_email);
        if (mysqli_stmt_num_rows($stmt_email) > 0) {
            $error = "An account with this Email already exists.";
            $email = '';
            $error_step = 1;
        }
        mysqli_stmt_close($stmt_email);

        if (empty($error)) {
            $stmt_phone = mysqli_prepare($conn, "SELECT id FROM user_profiles WHERE phone = ?");
            mysqli_stmt_bind_param($stmt_phone, "s", $phone);
            mysqli_stmt_execute($stmt_phone);
            mysqli_stmt_store_result($stmt_phone);
            if (mysqli_stmt_num_rows($stmt_phone) > 0) {
                $error = "This Phone Number is already registered.";
                $phone = '';
                $error_step = 1;
            }
            mysqli_stmt_close($stmt_phone);
        }

        if (empty($error)) {
            $stmt_nid = mysqli_prepare($conn, "SELECT id FROM user_profiles WHERE nid = ?");
            mysqli_stmt_bind_param($stmt_nid, "s", $nid_number);
            mysqli_stmt_execute($stmt_nid);
            mysqli_stmt_store_result($stmt_nid);
            if (mysqli_stmt_num_rows($stmt_nid) > 0) {
                $error = "This NID is already registered.";
                $nid_number = '';
                $error_step = 1;
            }
            mysqli_stmt_close($stmt_nid);
        }

        if (empty($error)) {
            $profile_image = 'default_avatar.jpg';
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
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
            }
        }

        if (empty($error)) {
            $lats = explode(',', $latitudes_post);
            $lngs = explode(',', $longitudes_post);
            
            $base_username = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0]));
            if (empty($base_username)) $base_username = 'provider';
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
                $role_id = 4; // Provider

                $s = mysqli_prepare($conn, "INSERT INTO users (username, email, password, role_id, is_verified, verification_code, status) VALUES (?, ?, ?, ?, 0, ?, 'inactive')");
                mysqli_stmt_bind_param($s, "sssis", $final_username, $email, $hashed_password, $role_id, $otp);
                mysqli_stmt_execute($s);
                $user_id = mysqli_insert_id($conn);

                // Insert into user_profiles
                $primary_lat = $lats[0];
                $primary_lng = $lngs[0];
                $s2 = mysqli_prepare($conn, "INSERT INTO user_profiles (user_id, full_name, phone, nid, address, latitude, longitude, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($s2, "issssdds", $user_id, $name, $phone, $nid_number, $address, $primary_lat, $primary_lng, $profile_image);
                mysqli_stmt_execute($s2);

                // Insert into service_providers
                $d_pricing = !empty($default_pricing) ? floatval($default_pricing) : NULL;
                // Hardcode coverage_radius to 2 in DB (it will be filtered as 2km)
                $coverage_radius = 2;
                
                $s3 = mysqli_prepare($conn, "INSERT INTO service_providers (user_id, category_id, name, phone, email, nid_number, address, latitude, longitude, default_pricing, coverage_radius, availability_schedule, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                mysqli_stmt_bind_param($s3, "iisssssdddiss", $user_id, $category_id, $name, $phone, $email, $nid_number, $address, $primary_lat, $primary_lng, $d_pricing, $coverage_radius, $availability_schedule, $profile_image);
                mysqli_stmt_execute($s3);
                $provider_id = mysqli_insert_id($conn);

                // Insert into provider_locations for all addresses
                if (count($lats) > 1) {
                    $s4 = mysqli_prepare($conn, "INSERT INTO provider_locations (provider_id, latitude, longitude, address) VALUES (?, ?, ?, ?)");
                    // Loop from index 1 since index 0 is already the primary location in service_providers
                    for ($i = 1; $i < count($lats); $i++) {
                        if (isset($lngs[$i])) {
                            $addr_part = "Secondary Location " . $i; // simplified
                            mysqli_stmt_bind_param($s4, "idds", $provider_id, $lats[$i], $lngs[$i], $addr_part);
                            mysqli_stmt_execute($s4);
                        }
                    }
                }

                mysqli_commit($conn);

                // Send OTP Email
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
                    $mail->Subject = 'Verify your Nibash Provider Account';
                    $mail->Body    = "Hello {$name},<br><br>Thank you for registering as a provider. Your verification code is: <b style='font-size:20px; color:#10b981;'>{$otp}</b><br><br>Your automatically assigned username is: <b>{$final_username}</b><br><br>Please enter this code on the verification page to activate your account.";

                    $mail->send();
                    
                    $_SESSION['success_msg'] = "Registration successful! Verification email sent.";
                    header("Location: ../verify.php?email=" . urlencode($email));
                    exit();
                    
                } catch (Exception $e) {
                    $error = "Account created successfully, but verification email could not be sent.";
                }

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Registration failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch categories
$categories = [];
$cat_res = mysqli_query($conn, "SELECT * FROM service_categories ORDER BY category_name ASC");
while ($row = mysqli_fetch_assoc($cat_res)) {
    $categories[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Registration | Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
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
            Already a provider? <a href="login.php" class="text-emerald-600 font-bold hover:text-emerald-700 transition-colors">Login</a>
        </div>
    </header>

    <main class="relative z-10 pt-4 pb-20 px-4">
        <div class="max-w-4xl mx-auto">

            <div class="text-center mb-10">
                <h1 class="text-2xl md:text-3xl font-bold text-slate-900 mb-2">Partner With Us</h1>
                <p class="text-slate-500 text-sm">Offer your services and grow your reach within 2km.</p>
            </div>

            <?php if ($error): ?>
                <div class="max-w-3xl mx-auto mb-8 p-4 bg-red-50 border border-red-100 rounded-xl flex items-center gap-3">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-red-500"></i>
                    <p class="text-sm text-red-700 font-medium"><?= htmlspecialchars($error) ?></p>
                </div>
            <?php endif; ?>

            <form id="registrationForm" action="" method="POST" enctype="multipart/form-data" class="max-w-3xl mx-auto bg-white p-8 rounded-2xl shadow-sm border border-slate-100">

                <div class="flex items-center justify-center mb-12 overflow-x-auto pb-2">
                    <div id="ind-step-1" class="step-indicator flex items-center gap-2 text-emerald-700 font-medium text-sm whitespace-nowrap">
                        <span id="circle-step-1" class="w-6 h-6 rounded-full border-2 border-emerald-600 bg-emerald-600 text-white flex items-center justify-center text-xs transition-colors">1</span>
                        Personal Details
                    </div>
                    <div class="w-16 md:w-32 h-[1px] bg-slate-200 mx-3 md:mx-6"></div>
                    <div id="ind-step-2" class="step-indicator flex items-center gap-2 text-slate-400 font-medium text-sm whitespace-nowrap">
                        <span id="circle-step-2" class="w-6 h-6 rounded-full border-2 border-slate-200 bg-transparent flex items-center justify-center text-xs transition-colors">2</span>
                        Service & Security
                    </div>
                </div>

                <div class="bg-transparent space-y-8">

                    <!-- STEP 1: Personal Details -->
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
                            <div class="md:col-span-2">
                                <label class="label-clean">Profile Image (Optional)</label>
                                <input type="file" name="profile_image" accept="image/*" class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer border border-slate-200 rounded-lg bg-white p-1">
                                <p class="text-xs text-slate-500 mt-1">Leave blank to use the default avatar.</p>
                            </div>
                        </div>

                        <div class="pt-8 flex justify-end border-t border-slate-100 mt-8">
                            <button type="button" onclick="nextStep(1, 2)" class="px-8 py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                                Next Step <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEP 2: Service Details & Security -->
                    <div id="step-2-content" class="step-hidden">
                        
                        <div class="mb-6 bg-emerald-50 border border-emerald-100 p-4 rounded-xl flex items-start gap-3">
                            <i data-lucide="info" class="w-5 h-5 text-emerald-600 mt-0.5"></i>
                            <div>
                                <p class="text-sm font-medium text-emerald-800">Coverage is restricted to 2 KM</p>
                                <p class="text-xs text-emerald-600 mt-1">You can add up to 4 comma-separated addresses. Please click "Verify Addresses" to map them.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 mb-8 border-b border-slate-100 pb-8">
                            <div>
                                <label class="label-clean">Service Category*</label>
                                <select name="category_id" required class="input-clean">
                                    <option value="">Select a category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="label-clean">Working Hours</label>
                                <select name="availability_schedule" class="input-clean">
                                    <option value="" disabled <?= empty($availability_schedule) ? 'selected' : '' ?>>Select working hours</option>
                                    <option value="08:00 AM - 04:00 PM" <?= $availability_schedule == '08:00 AM - 04:00 PM' ? 'selected' : '' ?>>08:00 AM - 04:00 PM</option>
                                    <option value="09:00 AM - 05:00 PM" <?= $availability_schedule == '09:00 AM - 05:00 PM' ? 'selected' : '' ?>>09:00 AM - 05:00 PM</option>
                                    <option value="09:00 AM - 06:00 PM" <?= $availability_schedule == '09:00 AM - 06:00 PM' ? 'selected' : '' ?>>09:00 AM - 06:00 PM</option>
                                    <option value="10:00 AM - 06:00 PM" <?= $availability_schedule == '10:00 AM - 06:00 PM' ? 'selected' : '' ?>>10:00 AM - 06:00 PM</option>
                                    <option value="10:00 AM - 08:00 PM" <?= $availability_schedule == '10:00 AM - 08:00 PM' ? 'selected' : '' ?>>10:00 AM - 08:00 PM</option>
                                    <option value="11:00 AM - 08:00 PM" <?= $availability_schedule == '11:00 AM - 08:00 PM' ? 'selected' : '' ?>>11:00 AM - 08:00 PM</option>
                                    <option value="24/7 Available" <?= $availability_schedule == '24/7 Available' ? 'selected' : '' ?>>24/7 Available</option>
                                    <option value="Flexible Hours" <?= $availability_schedule == 'Flexible Hours' ? 'selected' : '' ?>>Flexible Hours</option>
                                </select>
                            </div>
                            <div>
                                <label class="label-clean">Default Pricing (৳/Visit)</label>
                                <input type="number" step="0.01" name="default_pricing" placeholder="e.g. 500" class="input-clean" value="<?= htmlspecialchars($default_pricing) ?>">
                            </div>
                        </div>

                        <div class="mb-8 border-b border-slate-100 pb-8">
                            <label class="label-clean text-emerald-700">Addresses (Comma-separated for multiple areas)*</label>
                            <div class="flex gap-2">
                                <textarea id="address_input" name="address" rows="2" required placeholder="e.g. Gulshan Dhaka, Banani Dhaka" class="input-clean flex-1"><?= htmlspecialchars($address) ?></textarea>
                                <button type="button" onclick="verifyAddresses()" class="px-4 py-2 bg-emerald-100 text-emerald-700 hover:bg-emerald-200 rounded-lg text-sm font-bold whitespace-nowrap self-start mt-1 transition-colors">
                                    Verify Addresses
                                </button>
                            </div>
                            <p id="address_error" class="text-xs text-red-500 mt-2 hidden font-medium"></p>
                            <p id="address_success" class="text-xs text-emerald-600 mt-2 hidden font-medium">All locations verified and within Bangladesh!</p>

                            <input type="hidden" name="latitudes" id="latitudes" value="<?= htmlspecialchars($latitudes_post) ?>">
                            <input type="hidden" name="longitudes" id="longitudes" value="<?= htmlspecialchars($longitudes_post) ?>">
                            <input type="hidden" name="address_verified" id="address_verified" value="<?= htmlspecialchars($address_verified) ?>">

                            <div class="mt-4">
                                <div id="map" class="w-full h-64 border border-slate-200 rounded-xl mb-2 z-0 relative"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6">
                            <div>
                                <label class="label-clean">Password*</label>
                                <div class="relative">
                                    <input type="password" id="password" name="password" required pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}" title="At least 6 chars, 1 uppercase, 1 lowercase, 1 number" placeholder="Create strong password" class="input-clean pr-10">
                                    <button type="button" onclick="togglePasswordVisibility('password', 'eye-icon-pass')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-600 transition-colors focus:outline-none">
                                        <i data-lucide="eye" id="eye-icon-pass" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="label-clean">Confirm Password*</label>
                                <div class="relative">
                                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Confirm password" class="input-clean pr-10">
                                    <button type="button" onclick="togglePasswordVisibility('confirm_password', 'eye-icon-confirm')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-600 transition-colors focus:outline-none">
                                        <i data-lucide="eye" id="eye-icon-confirm" class="w-4 h-4"></i>
                                    </button>
                                </div>
                                <p id="password-error" class="text-red-500 text-xs mt-1 hidden font-medium">Passwords do not match.</p>
                            </div>
                        </div>

                        <div class="pt-8 flex items-center justify-between border-t border-slate-100 mt-8">
                            <button type="button" onclick="prevStep(2, 1)" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 text-sm font-medium rounded-lg transition-colors shadow-sm flex items-center gap-2">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                            </button>
                            <button type="submit" onclick="return validateFinalStep()" class="px-8 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
                                Register Provider
                            </button>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </main>

    <script>
        lucide.createIcons();

        const errorStep = <?= $error_step ?>;
        if(errorStep === 2) {
            document.getElementById('step-1-content').classList.add('step-hidden');
            document.getElementById('step-2-content').classList.remove('step-hidden');
            updateStepperUI(2);
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
            const inputs = currentStep.querySelectorAll('input[required], textarea[required], select[required]');
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
            for (let i = 1; i <= 2; i++) {
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

        async function nextStep(current, next) {
            if (validateCurrentStep(current)) {
                if (current === 1) {
                    const email = document.querySelector('input[name="email"]').value;
                    const phone = document.querySelector('input[name="phone"]').value;
                    const nid = document.querySelector('input[name="nid_number"]').value;
                    
                    try {
                        const res = await fetch('check_duplicates.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `email=${encodeURIComponent(email)}&phone=${encodeURIComponent(phone)}&nid=${encodeURIComponent(nid)}`
                        });
                        const data = await res.json();
                        if (data.error) {
                            alert(data.error);
                            if (data.field) {
                                document.querySelector(`input[name="${data.field}"]`).value = '';
                                document.querySelector(`input[name="${data.field}"]`).focus();
                            }
                            return;
                        }
                    } catch (e) {
                        console.error('Error checking duplicates', e);
                    }
                }
                
                document.getElementById(`step-${current}-content`).classList.add('step-hidden');
                document.getElementById(`step-${next}-content`).classList.remove('step-hidden');
                updateStepperUI(next);
                window.scrollTo(0, 0);
                setTimeout(() => map.invalidateSize(), 200); // Fix map rendering when hidden
            }
        }

        function prevStep(current, prev) {
            document.getElementById(`step-${current}-content`).classList.add('step-hidden');
            document.getElementById(`step-${prev}-content`).classList.remove('step-hidden');
            updateStepperUI(prev);
            window.scrollTo(0, 0);
        }

        function validateFinalStep() {
            if (!validateCurrentStep(2)) return false;

            const pass = document.getElementById('password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            const errorMsg = document.getElementById('password-error');
            const verified = document.getElementById('address_verified').value;
            const addrError = document.getElementById('address_error');

            if (verified !== "1") {
                addrError.textContent = "Please click 'Verify Addresses' before submitting.";
                addrError.classList.remove('hidden');
                return false;
            }

            if (pass !== confirmPass) {
                errorMsg.classList.remove('hidden');
                return false;
            } else {
                errorMsg.classList.add('hidden');
                return true;
            }
        }

        // Map & Geocoding Logic
        var map = L.map('map').setView([23.8103, 90.4125], 7); // Default to Bangladesh
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        var markers = [];

        async function verifyAddresses() {
            const addressInput = document.getElementById('address_input').value.trim();
            const addrError = document.getElementById('address_error');
            const addrSuccess = document.getElementById('address_success');
            
            if (!addressInput) {
                addrError.textContent = "Please enter an address to verify.";
                addrError.classList.remove('hidden');
                addrSuccess.classList.add('hidden');
                return;
            }

            // Clean previous markers
            markers.forEach(m => map.removeLayer(m));
            markers = [];
            
            const addressList = addressInput.split(',').map(a => a.trim()).filter(a => a.length > 0);
            if (addressList.length > 4) {
                addrError.textContent = "Maximum 4 addresses are allowed.";
                addrError.classList.remove('hidden');
                addrSuccess.classList.add('hidden');
                return;
            }

            addrError.classList.add('hidden');
            addrSuccess.classList.add('hidden');
            document.getElementById('address_verified').value = "0";

            let lats = [];
            let lngs = [];
            let isValid = true;
            let bounds = L.latLngBounds();

            for (let i = 0; i < addressList.length; i++) {
                try {
                    // Geocode via Nominatim
                    const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(addressList[i])}&countrycodes=bd`);
                    const data = await res.json();
                    
                    if (data && data.length > 0) {
                        const lat = data[0].lat;
                        const lon = data[0].lon;
                        lats.push(lat);
                        lngs.push(lon);
                        
                        var marker = L.marker([lat, lon]).addTo(map).bindPopup(addressList[i]);
                        markers.push(marker);
                        bounds.extend([lat, lon]);
                    } else {
                        addrError.textContent = `Location not found or not in Bangladesh for: "${addressList[i]}". Please adjust the address.`;
                        addrError.classList.remove('hidden');
                        isValid = false;
                        break;
                    }
                } catch(e) {
                    addrError.textContent = "Error verifying location. Please try again.";
                    addrError.classList.remove('hidden');
                    isValid = false;
                    break;
                }
                
                // Sleep to avoid nominatim rate limit (1 req/sec)
                await new Promise(r => setTimeout(r, 1000));
            }

            if (isValid && lats.length > 0) {
                document.getElementById('latitudes').value = lats.join(',');
                document.getElementById('longitudes').value = lngs.join(',');
                document.getElementById('address_verified').value = "1";
                addrSuccess.classList.remove('hidden');
                
                if (markers.length === 1) {
                    map.setView(markers[0].getLatLng(), 14);
                } else {
                    map.fitBounds(bounds, {padding: [50, 50]});
                }
            }
        }
    </script>
</body>
</html>