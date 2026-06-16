<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

require 'includes/db_config.php';

$form_error = '';
$resident_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['guest_name'] ?? '');
    $phone = trim($_POST['guest_phone'] ?? '');
    $id_number = trim($_POST['id_number'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $vehicle_plate = trim($_POST['vehicle_plate'] ?? '');
    $face_descriptor = trim($_POST['face_descriptor'] ?? '');

    if ($name === '' || $phone === '' || $purpose === '' || empty($_FILES['guest_photo']['name'])) {
        $form_error = 'Please provide a guest photo, name, phone number, and purpose of visit.';
    } else {
        $upload_dir = __DIR__ . '/assets/uploads/guests/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $photo_path = '';
        if (!empty($_FILES['guest_photo']['name'])) {
            $photo_ext = strtolower(pathinfo($_FILES['guest_photo']['name'], PATHINFO_EXTENSION));
            $photo_name = 'guest_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $photo_ext;
            $photo_target = $upload_dir . $photo_name;
            if (move_uploaded_file($_FILES['guest_photo']['tmp_name'], $photo_target)) {
                $photo_path = 'assets/uploads/guests/' . $photo_name;
            }
        }

        $id_doc_path = null;
        if (!empty($_FILES['id_document']['name'])) {
            $doc_ext = strtolower(pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION));
            $doc_name = 'guest_doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $doc_ext;
            $doc_target = $upload_dir . $doc_name;
            if (move_uploaded_file($_FILES['id_document']['tmp_name'], $doc_target)) {
                $id_doc_path = 'assets/uploads/guests/' . $doc_name;
            }
        }

        $id_number = $id_number !== '' ? $id_number : null;
        $purpose = $purpose !== '' ? $purpose : null;
        $vehicle_plate = $vehicle_plate !== '' ? $vehicle_plate : null;
        $face_descriptor = $face_descriptor !== '' ? $face_descriptor : null;

        // Fix: According to Database Schema
        // 1. Get apt_id for the resident
        $apt_id = 0;
        $apt_res = @mysqli_query($conn, "SELECT apt_id FROM apartment_assignments WHERE user_id = $resident_id AND is_active = 1 LIMIT 1");
        if ($apt_res && $apt_row = mysqli_fetch_assoc($apt_res)) {
            $apt_id = (int)$apt_row['apt_id'];
        } else if ($_SESSION['role_id'] == 1) {
            $fallback_res = mysqli_query($conn, "SELECT id FROM apartments LIMIT 1");
            if ($fallback_res && $fb_row = mysqli_fetch_assoc($fallback_res)) {
                $apt_id = (int)$fb_row['id'];
            }
        }

        // 2. Create or Get Guest Identity
        $new_guest_id = 0;
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM guests WHERE phone_number = ?");
        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, 's', $phone);
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);
            if ($grow = mysqli_fetch_assoc($check_res)) {
                $new_guest_id = $grow['id'];
                // Update identity if needed
                $esc_name = mysqli_real_escape_string($conn, $name);
                $esc_id_number = mysqli_real_escape_string($conn, $id_number);
                mysqli_query($conn, "UPDATE guests SET full_name = '$esc_name', nid_passport_no = '$esc_id_number' WHERE id = $new_guest_id");
            } else {
                $guest_stmt = mysqli_prepare($conn, "INSERT INTO guests (full_name, phone_number, nid_passport_no, face_descriptor) VALUES (?, ?, ?, ?)");
                if ($guest_stmt) {
                    mysqli_stmt_bind_param($guest_stmt, 'ssss', $name, $phone, $id_number, $face_descriptor);
                    if (mysqli_stmt_execute($guest_stmt)) {
                        $new_guest_id = mysqli_insert_id($conn);
                    }
                    mysqli_stmt_close($guest_stmt);
                }
            }
            mysqli_stmt_close($check_stmt);
        }

        // Rename guest photo to guest_photo_{id}.jpg
        if ($new_guest_id > 0 && !empty($photo_path)) {
            $old_abs  = __DIR__ . '/' . $photo_path;
            $new_name = 'guest_photo_' . $new_guest_id . '.jpg';
            $new_abs  = $upload_dir . $new_name;
            if (file_exists($old_abs)) {
                rename($old_abs, $new_abs);
            }
        }

        // Rename ID document to guest_doc_{id}.ext
        if ($new_guest_id > 0 && !empty($id_doc_path)) {
            $old_abs = __DIR__ . '/' . $id_doc_path;
            $doc_ext = strtolower(pathinfo($old_abs, PATHINFO_EXTENSION));
            $new_name = 'guest_doc_' . $new_guest_id . '.' . $doc_ext;
            $new_abs = $upload_dir . $new_name;
            if (file_exists($old_abs)) {
                rename($old_abs, $new_abs);
            }
        }

        // 3. Create Visit Request (The Pass)
        if ($new_guest_id > 0) {
            $pass_code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $vr_stmt = mysqli_prepare($conn, "INSERT INTO visit_requests (guest_id, resident_id, apt_id, purpose, digital_pass_code, status) VALUES (?, ?, ?, ?, ?, 'Approved')");
            if ($vr_stmt) {
                mysqli_stmt_bind_param($vr_stmt, 'iiiss', $new_guest_id, $resident_id, $apt_id, $purpose, $pass_code);
                if (mysqli_stmt_execute($vr_stmt)) {
                    $visit_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($vr_stmt);

                    // 4. Handle Vehicle Plate
                    if ($vehicle_plate) {
                        $v_stmt = mysqli_prepare($conn, "INSERT INTO guest_vehicles (visit_id, plate_number) VALUES (?, ?)");
                        if ($v_stmt) {
                            mysqli_stmt_bind_param($v_stmt, 'is', $visit_id, $vehicle_plate);
                            mysqli_stmt_execute($v_stmt);
                            mysqli_stmt_close($v_stmt);
                        }
                    }

                    if ($_SESSION['role_id'] == 1) {
                        header('Location: ' . BASE_URL . 'owner/guest_entries.php?success=1');
                    } else {
                        header('Location: ' . BASE_URL . 'resident/guest_passes.php?success=1');
                    }
                    exit();
                }
                mysqli_stmt_close($vr_stmt);
            }
            $form_error = 'Unable to save guest pass. Please check your database connection.';
        } else {
            $form_error = 'Failed to create guest identity. Please try again.';
        }
    }
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
    <title>Register Guest | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if ($_SESSION["role_id"] == 1): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <?php else: ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <?php endif; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #ecfdf5; }
        ::-webkit-scrollbar-thumb { background: #a7f3d0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6ee7b7; }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php if ($_SESSION['role_id'] == 1) {
        include 'includes/owner_sidebar.php';
    } else {
        include 'includes/resident_sidebar.php';
    } ?>

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
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Security & Access</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto w-full bg-slate-50/50">
                <div class="max-w-5xl mx-auto space-y-10">

                    <?php if ($form_error !== ''): ?>
                        <script>document.addEventListener("DOMContentLoaded", function () { if (typeof showCustomPopup === "function") showCustomPopup("<?php echo addslashes(htmlspecialchars($form_error)); ?>", "error"); });</script>
                    <?php endif; ?>
                    
                    <div class="flex items-center">
                        <a href="<?php echo BASE_URL . ($_SESSION['role_id'] == 1 ? 'owner/guest_entries.php' : 'resident/guest_passes.php'); ?>" class="flex items-center gap-2 text-sm font-black text-slate-500 hover:text-emerald-600 transition-colors">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Passes
                        </a>
                    </div>

                    <div class="mb-8 pb-6 border-b border-slate-200">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3 mb-3">
                            Register Guest Pass
                        </h1>
                        <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base">
                            <span class="p-1.5 bg-emerald-100 border border-emerald-200 rounded-lg"><i data-lucide="scan-face" class="w-4 h-4 text-emerald-700"></i></span>
                            Pre-register guests for secure, automated biometric entry.
                        </p>
                    </div>

                    <div class="bg-indigo-50/50 border border-indigo-200 rounded-[1.5rem] px-6 py-5 flex flex-col sm:flex-row items-start sm:items-center gap-4 shadow-sm relative overflow-hidden">
                        <div class="absolute left-0 top-0 w-1 h-full bg-indigo-500"></div>
                        <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center shrink-0"><i data-lucide="shield-check" class="w-5 h-5 text-indigo-600"></i></div>
                        <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <p class="text-xs font-bold text-indigo-700 flex items-center gap-1.5"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Provide a clear face photo.</p>
                            <p class="text-xs font-bold text-indigo-700 flex items-center gap-1.5"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Verification done at the gate.</p>
                            <p class="text-xs font-bold text-indigo-700 flex items-center gap-1.5"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i> NID optional but recommended.</p>
                        </div>
                    </div>

                    <form method="post" enctype="multipart/form-data" class="space-y-8">
                        
                        <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
                            <div class="bg-white border-b border-slate-100 px-8 py-6 flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-600 flex items-center justify-center shadow-sm">
                                    <i data-lucide="user" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-black text-slate-900">1. Guest Information</h2>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mt-1">Identity Details</p>
                                </div>
                            </div>
                            
                            <div class="p-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Guest Full Name <span class="text-emerald-500">*</span></label>
                                        <input type="text" name="guest_name" required placeholder="e.g. John Doe"
                                            class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Phone Number <span class="text-emerald-500">*</span></label>
                                        <input type="tel" name="guest_phone" required pattern="[0-9]{11}" required placeholder="01700-123456"  oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);"
                                            class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Vehicle Plate <span class="text-slate-400 ml-1 font-bold">(Optional)</span></label>
                                        <input type="text" name="vehicle_plate" placeholder="Optional"
                                            class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">NID / Passport Number <span class="text-slate-400 ml-1 font-bold">(Optional)</span></label>
                                        <input type="text" name="id_number" placeholder="Optional"
                                            class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Purpose of Visit <span class="text-emerald-500">*</span></label>
                                        <input type="text" name="purpose" required placeholder="e.g. Dinner, maintenance, delivery"
                                            class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
                            <div class="bg-white border-b border-slate-100 px-8 py-6 flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center shadow-sm">
                                    <i data-lucide="image" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <h2 class="text-xl font-black text-slate-900">2. Documents & Biometrics</h2>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mt-1">Photo & ID</p>
                                </div>
                            </div>
                            
                            <div class="p-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                    <div class="bg-emerald-50/50 border-2 border-emerald-200 rounded-[1.5rem] p-6 relative overflow-hidden">
                                        <div class="absolute -right-3 -top-3 text-emerald-200/50 pointer-events-none"><i data-lucide="scan-face" class="w-24 h-24"></i></div>
                                        <label class="block text-[10px] font-black text-emerald-800 uppercase tracking-widest mb-2 relative z-10">Guest Photo <span class="text-emerald-500">*</span></label>
                                        <p class="text-xs text-emerald-600/80 font-bold mb-4 relative z-10">Clear face required for AI scan. Max 5MB.</p>
                                        <input type="file" name="guest_photo" accept="image/*" required
                                            class="relative z-10 w-full text-xs font-bold text-emerald-800 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-emerald-600 file:text-white hover:file:bg-emerald-700 cursor-pointer transition-colors shadow-sm">
                                    </div>
                                    
                                    <div class="bg-slate-50/50 border-2 border-slate-200 rounded-[1.5rem] p-6">
                                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">ID Document <span class="text-slate-400 ml-1 font-bold">(Optional)</span></label>
                                        <p class="text-xs text-slate-500 font-bold mb-4">Optional NID or Passport upload.</p>
                                        <input type="file" name="id_document" accept="image/*,application/pdf"
                                            class="w-full text-xs font-bold text-slate-600 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-slate-200 file:text-slate-700 hover:file:bg-slate-300 cursor-pointer transition-colors shadow-sm">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-4 border-t border-slate-200">
                            <p class="text-xs font-bold text-slate-500 flex items-center gap-2">
                                <span class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center shadow-sm">
                                    <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500"></i>
                                </span>
                                Biometric data is processed securely on-device.
                            </p>
                            
                            <div class="flex items-center gap-3 w-full sm:w-auto">
                                <a href="<?php echo BASE_URL . ($_SESSION['role_id'] == 1 ? 'owner/guest_entries.php' : 'resident/guest_passes.php'); ?>" class="flex-1 sm:flex-none text-center px-8 py-3.5 bg-white border border-slate-200 text-slate-600 font-black text-sm rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                                    Cancel
                                </a>
                                <button type="submit" class="flex-1 sm:flex-none px-10 py-3.5 bg-slate-900 hover:bg-emerald-600 text-white font-black text-sm rounded-xl shadow-md hover:shadow-lg hover:shadow-emerald-500/30 transition-all flex items-center justify-center gap-2 group cursor-pointer">
                                    Submit Pass <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <div id="processing-modal"
        class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[150] hidden items-center justify-center opacity-0 transition-opacity duration-300 p-4">
        <div class="bg-white rounded-[2rem] p-8 max-w-sm w-full mx-4 shadow-2xl transform scale-95 transition-transform duration-300 border border-emerald-100 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-blue-500" id="modal-top-bar"></div>
            <div class="flex flex-col items-center text-center">
                <div id="modal-icon-bg"
                    class="w-16 h-16 bg-blue-50 text-blue-500 border border-blue-100 rounded-full flex items-center justify-center mb-5 shadow-sm">
                    <div id="modal-icon-wrapper"><i data-lucide="loader-2" class="w-8 h-8 animate-spin"></i></div>
                </div>
                <h3 class="text-xl font-black text-slate-900 mb-2" id="modal-title">Processing Photo</h3>
                <p class="text-slate-500 font-medium text-sm mb-6" id="modal-message">Please wait for face recognition to finish.</p>
                <button type="button" onclick="closeProcessingModal()" id="modal-ok-btn"
                    class="w-full py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-sm rounded-xl transition-colors cursor-pointer border border-slate-200">Understood</button>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
        function showProcessingModal(title = 'Processing Photo', message = 'Please wait...', type = 'loading') {
            const modal = document.getElementById('processing-modal');
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-message').textContent = message;
            
            const iconBg = document.getElementById('modal-icon-bg');
            const iconWrapper = document.getElementById('modal-icon-wrapper');
            const okBtn = document.getElementById('modal-ok-btn');
            const topBar = document.getElementById('modal-top-bar');
            
            if (type === 'success') { 
                iconBg.className = 'w-16 h-16 bg-emerald-50 text-emerald-500 border border-emerald-100 rounded-full flex items-center justify-center mb-5 shadow-sm'; 
                iconWrapper.innerHTML = '<i data-lucide="check-circle" class="w-8 h-8"></i>'; 
                topBar.className = 'absolute top-0 left-0 w-full h-1 bg-emerald-500';
                okBtn.className = 'w-full py-3.5 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 font-black text-sm rounded-xl transition-colors cursor-pointer hidden border border-emerald-200'; 
            }
            else if (type === 'error') { 
                iconBg.className = 'w-16 h-16 bg-rose-50 text-rose-500 border border-rose-100 rounded-full flex items-center justify-center mb-5 shadow-sm'; 
                iconWrapper.innerHTML = '<i data-lucide="alert-circle" class="w-8 h-8"></i>'; 
                topBar.className = 'absolute top-0 left-0 w-full h-1 bg-rose-500';
                okBtn.className = 'w-full py-3.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-black text-sm rounded-xl transition-colors cursor-pointer block border border-rose-200'; 
                okBtn.textContent = 'Try Again'; 
            }
            else { 
                iconBg.className = 'w-16 h-16 bg-blue-50 text-blue-500 border border-blue-100 rounded-full flex items-center justify-center mb-5 shadow-sm'; 
                iconWrapper.innerHTML = '<i data-lucide="loader-2" class="w-8 h-8 animate-spin"></i>'; 
                topBar.className = 'absolute top-0 left-0 w-full h-1 bg-blue-500';
                okBtn.className = 'w-full py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-sm rounded-xl transition-colors cursor-pointer hidden border border-slate-200'; 
            }
            lucide.createIcons();
            modal.classList.remove('hidden'); modal.classList.add('flex');
            setTimeout(() => { modal.classList.remove('opacity-0'); modal.firstElementChild.classList.remove('scale-95'); }, 10);
        }
        function closeProcessingModal() {
            const modal = document.getElementById('processing-modal');
            modal.classList.add('opacity-0'); modal.firstElementChild.classList.add('scale-95');
            setTimeout(() => { modal.classList.remove('flex'); modal.classList.add('hidden'); }, 300);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
            await faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL);
            await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);
            await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);
            
            const fileInput = document.querySelector('input[name="guest_photo"]');
            const form = document.querySelector('form');
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden'; hiddenInput.name = 'face_descriptor'; hiddenInput.id = 'face_descriptor';
            form.appendChild(hiddenInput);
            
            fileInput.addEventListener('change', () => { document.getElementById('face_descriptor').value = ''; });
            
            form.addEventListener('submit', async (e) => {
                if (fileInput.files.length > 0 && !document.getElementById('face_descriptor').value) {
                    e.preventDefault();
                    showProcessingModal('Image on Processing', 'Extracting facial biometrics. Please wait...', 'loading');
                    setTimeout(async () => {
                        try {
                            const img = await faceapi.bufferToImage(fileInput.files[0]);
                            const detection = await faceapi.detectSingleFace(img).withFaceLandmarks().withFaceDescriptor();
                            if (detection) {
                                document.getElementById('face_descriptor').value = JSON.stringify(Array.from(detection.descriptor));
                                showProcessingModal('Process Successfully', 'Biometric data generated. Submitting now...', 'success');
                                setTimeout(() => { form.submit(); }, 1500);
                            } else {
                                showProcessingModal('No Face Detected', 'No clear face detected. Please upload a clear photo.', 'error');
                                fileInput.value = '';
                            }
                        } catch (err) { showProcessingModal('Error', 'An error occurred while processing the photo.', 'error'); }
                    }, 50);
                }
            });
        });
    </script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include 'chatbot/chat_widget.php'; ?>
</body>
</html>