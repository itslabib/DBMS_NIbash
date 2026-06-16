<?php
session_start();
require_once '../includes/db_config.php';

$user_id = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guest_name = $_POST['guest_name'] ?? '';
    $guest_phone = $_POST['guest_phone'] ?? '';
    $purpose = $_POST['purpose'] ?? 'General Visit';
    $target_resident = isset($_POST['resident_id']) ? (int)$_POST['resident_id'] : 0;
    
    $photo_path = null;
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'assets/uploads/guests/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle manual guest photo capture (base64 string from webcam)
    if (!empty($_POST['photo_data'])) {
        $img = $_POST['photo_data'];
        $img = str_replace('data:image/jpeg;base64,', '', $img);
        $img = str_replace(' ', '+', $img);
        $data = base64_decode($img);
        
        $file_name = 'manual_guest_' . time() . '_' . uniqid() . '.jpg';
        $photo_path = $upload_dir . $file_name;
        file_put_contents($photo_path, $data);
    }

    if (!empty($guest_name)) {
        $vehicle_plate = !empty($_POST['vehicle_plate']) ? $_POST['vehicle_plate'] : null;
        $total_guests = !empty($_POST['total_guests']) ? (int)$_POST['total_guests'] : 1;

        // Fix: According to Database Schema
        $new_guest_id = 0;
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM guests WHERE phone_number = ?");
        if ($check_stmt) {
            mysqli_stmt_bind_param($check_stmt, 's', $guest_phone);
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);
            if ($grow = mysqli_fetch_assoc($check_res)) {
                $new_guest_id = $grow['id'];
                mysqli_query($conn, "UPDATE guests SET full_name = '$guest_name' WHERE id = $new_guest_id");
            } else {
                $guest_stmt = mysqli_prepare($conn, "INSERT INTO guests (full_name, phone_number) VALUES (?, ?)");
                if ($guest_stmt) {
                    mysqli_stmt_bind_param($guest_stmt, 'ss', $guest_name, $guest_phone);
                    if (mysqli_stmt_execute($guest_stmt)) {
                        $new_guest_id = mysqli_insert_id($conn);
                    }
                    mysqli_stmt_close($guest_stmt);
                }
            }
            mysqli_stmt_close($check_stmt);
        }

        if ($new_guest_id > 0) {
            // Get resident's apt_id
            $apt_id = 0;
            $apt_q = mysqli_query($conn, "SELECT apt_id FROM apartment_assignments WHERE user_id = $target_resident AND is_active = 1 LIMIT 1");
            if ($arow = mysqli_fetch_assoc($apt_q)) { $apt_id = (int)$arow['apt_id']; }

            // Create Visit Request
            $rich_purpose = "[MANUAL] $purpose (Total: $total_guests)";
            $pass_code = "MANUAL-" . time();
            $vr_stmt = mysqli_prepare($conn, "INSERT INTO visit_requests (guest_id, resident_id, apt_id, purpose, digital_pass_code, status) VALUES (?, ?, ?, ?, ?, 'Approved')");
            if ($vr_stmt) {
                mysqli_stmt_bind_param($vr_stmt, 'iiiss', $new_guest_id, $target_resident, $apt_id, $rich_purpose, $pass_code);
                if (mysqli_stmt_execute($vr_stmt)) {
                    $visit_id = mysqli_insert_id($conn);
                    mysqli_stmt_close($vr_stmt);

                    // Handle Vehicle
                    if ($vehicle_plate) {
                        mysqli_query($conn, "INSERT INTO guest_vehicles (visit_id, plate_number) VALUES ($visit_id, '$vehicle_plate')");
                    }

                    // Log Entry
                    $insert_log = "INSERT INTO entry_logs (visit_id, entry_method) VALUES (?, 'FaceScan')";
                    $log_stmt = mysqli_prepare($conn, $insert_log);
                    mysqli_stmt_bind_param($log_stmt, "i", $visit_id);
                    mysqli_stmt_execute($log_stmt);
                    mysqli_stmt_close($log_stmt);
                    
                    header("Location: ../owner/manual_guest.php?success=1");
                    exit();
                }
                mysqli_stmt_close($vr_stmt);
            }
        }
    }
}

// ── Building Isolation Context ───────────────────────────────────────────
$building_id = '';
$building_q = mysqli_query($conn, "SELECT a.building_id 
                                   FROM apartment_assignments aa 
                                   JOIN apartments a ON a.id = aa.apt_id 
                                   WHERE aa.user_id = $user_id AND aa.is_active = 1 
                                   LIMIT 1");

if ($building_q && mysqli_num_rows($building_q) > 0) {
    $building_id = mysqli_fetch_assoc($building_q)['building_id'];
} else {
    // Fallback: Owners who aren't active apartment assignees itself, just get one building they manage
    $fb_res = mysqli_query($conn, "SELECT building_id FROM apartments LIMIT 1");
    if ($fb_res && mysqli_num_rows($fb_res) > 0) {
        $building_id = mysqli_fetch_assoc($fb_res)['building_id'];
    }
}
$safe_building_id = mysqli_real_escape_string($conn, $building_id);

// Fetch residents to populate select dropdown (Filtered by building)
$residents = [];
if (!empty($building_id)) {
    $res_query = "SELECT u.id, p.full_name, a.apt_number 
                  FROM users u 
          JOIN user_profiles p ON u.id = p.user_id 
                  JOIN apartment_assignments aa ON u.id = aa.user_id AND aa.is_active = 1
                  JOIN apartments a ON aa.apt_id = a.id
                  WHERE a.building_id = '$safe_building_id' 
                  ORDER BY a.apt_number ASC, p.full_name ASC";
$res_result = mysqli_query($conn, $res_query);
if ($res_result) {
    while ($row = mysqli_fetch_assoc($res_result)) {
        $residents[] = $row;
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Guest Access | EasyHome Gate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex items-center justify-center p-4">
    
    <?php if(isset($_GET['success']) && $_GET['success'] == '1'): ?>
    <!-- Success Modal Overlay -->
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center animate-in zoom-in duration-300">
            <div class="w-20 h-20 bg-emerald-100 text-emerald-500 rounded-full flex items-center justify-center mx-auto mb-4 shadow-inner">
                <i data-lucide="check-check" class="w-10 h-10"></i>
            </div>
            <h3 class="text-2xl font-extrabold text-slate-800 mb-2">Access Granted</h3>
            <p class="text-slate-500 text-sm mb-6">The manual guest entry has been successfully logged into the system and access is permitted.</p>
            <a href="<?= BASE_URL ?>" class="w-full inline-flex justify-center items-center py-3 bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-xl transition-colors shadow-md gap-2">
                <i data-lucide="home" class="w-4 h-4"></i> Back to Home Gate
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Container -->
    <div class="bg-white rounded-2xl shadow-2xl overflow-hidden max-w-4xl w-full">
        <!-- Header -->
        <div class="bg-emerald-50 px-8 py-6 border-b border-emerald-100">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 bg-emerald-500 text-white rounded-xl shadow-lg flex justify-center items-center">
                    <i data-lucide="clipboard-edit" class="w-6 h-6"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-slate-800">Manual Guest Entry</h2>
                    <p class="text-sm text-slate-500 font-medium">Capture details for visitors without pre-approved digital passes.</p>
                </div>
            </div>
        </div>

        <!-- Form Details -->
        <form action="<?php echo BASE_URL; ?>owner/manual_guest.php" method="POST" class="p-8 space-y-6" id="manualEntryForm">
            
            <div class="flex flex-col md:flex-row gap-8">
                <!-- Camera Section -->
                <div class="w-full md:w-1/3 flex flex-col items-center">
                    <div class="w-40 h-40 bg-slate-100 rounded-2xl border-2 border-dashed border-slate-300 flex items-center justify-center overflow-hidden relative shadow-inner mb-4">
                        <video id="webcam" class="absolute inset-0 w-full h-full object-cover transform scale-x-[-1]" autoplay playsinline></video>
                        <canvas id="photo-canvas" class="hidden"></canvas>
                        <img id="photo-preview" class="absolute inset-0 w-full h-full object-cover hidden z-10" />
                        <div id="cam-placeholder" class="text-center z-0 text-slate-400">
                            <i data-lucide="camera" class="w-8 h-8 mx-auto mb-1"></i>
                            <span class="text-xs font-bold uppercase tracking-wider">Start Camera</span>
                        </div>
                    </div>
                    
                    <!-- Hidden field to hold base64 data -->
                    <input type="hidden" name="photo_data" id="photo_data" required>

                    <div class="flex flex-col w-full gap-2 font-medium">
                        <button type="button" id="start-cam-btn" class="w-full py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs rounded-lg transition-colors flex justify-center items-center gap-1 shadow-md">
                            <i data-lucide="video" class="w-3.5 h-3.5"></i> Enable Camera
                        </button>
                        <button type="button" id="capture-btn" class="hidden w-full py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-xs rounded-lg transition-colors flex justify-center items-center gap-1 shadow-md">
                            <i data-lucide="aperture" class="w-3.5 h-3.5"></i> Capture Photo
                        </button>
                        <button type="button" id="retake-btn" class="hidden w-full py-2 bg-amber-500 hover:bg-amber-600 text-white text-xs rounded-lg transition-colors flex justify-center items-center gap-1 shadow-md">
                            <i data-lucide="refresh-ccw" class="w-3.5 h-3.5"></i> Retake
                        </button>
                    </div>
                    <p class="text-[10px] text-red-500 font-bold mt-2 hidden" id="photo-err">Photo capture required!</p>
                </div>

                <!-- Input Fields Section -->
                <div class="w-full md:w-2/3 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Target Resident / Host</label>
                        <select name="resident_id" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" required>
                            <option value="">Select an apartment...</option>
                            <?php foreach($residents as $res): ?>
                            <option value="<?= htmlspecialchars($res['id']) ?>">
                                Unit <?= htmlspecialchars($res['apt_number'] && $res['apt_number'] !== 'Not Assigned' ? $res['apt_number'] : 'N/A') ?> - <?= htmlspecialchars($res['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Guest Full Name</label>
                        <input type="text" name="guest_name" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Ex: John Doe" required>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Phone Number <span class="text-rose-500">*</span></label>
                            <input type="text" name="guest_phone" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="01XXXXXXXXX" minlength="11" maxlength="11" pattern="[0-9]{11}" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);" title="Please enter exactly 11 digits" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Purpose</label>
                            <input type="text" name="purpose" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Delivery, Visit...">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1 mt-1 truncate">Total Guests</label>
                            <input type="number" name="total_guests" min="1" value="1" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1 mt-1 truncate" title="Vehicle Plate">Vehicle <span class="text-slate-400 font-normal lowercase">(Opt)</span></label>
                            <input type="text" name="vehicle_plate" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" placeholder="Plate No">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="<?= BASE_URL ?>" class="px-6 py-2.5 bg-white border border-slate-200 text-slate-600 font-bold rounded-xl text-sm hover:bg-slate-50 transition-colors">
                    Cancel & Return
                </a>
                <button type="submit" id="submitBtn" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-sm shadow-md transition-colors flex items-center gap-2">
                    <i data-lucide="user-plus" class="w-4 h-4"></i> Allow Entry
                </button>
            </div>
        </form>
    </div>

    <script>
        lucide.createIcons();

        // Camera Initialization & Logic
        const video = document.getElementById('webcam');
        const canvas = document.getElementById('photo-canvas');
        const preview = document.getElementById('photo-preview');
        const startBtn = document.getElementById('start-cam-btn');
        const captureBtn = document.getElementById('capture-btn');
        const retakeBtn = document.getElementById('retake-btn');
        const photoData = document.getElementById('photo_data');
        const photoErr = document.getElementById('photo-err');
        const placeholder = document.getElementById('cam-placeholder');
        let stream = null;

        startBtn.addEventListener('click', async () => {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
                video.srcObject = stream;
                startBtn.classList.add('hidden');
                captureBtn.classList.remove('hidden');
                placeholder.classList.add('hidden');
                video.classList.remove('hidden');
                preview.classList.add('hidden');
                photoErr.classList.add('hidden');
            } catch (err) {
                showCustomPopup("Camera access denied or unavailable.", "error");
                console.error(err);
            }
        });

        captureBtn.addEventListener('click', () => {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            
            const dataUrl = canvas.toDataURL('image/jpeg');
            photoData.value = dataUrl;
            
            preview.src = dataUrl;
            preview.classList.remove('hidden');
            video.classList.add('hidden');
            
            captureBtn.classList.add('hidden');
            retakeBtn.classList.remove('hidden');
            
            // Stop camera stream to free up resources
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });

        retakeBtn.addEventListener('click', async () => {
            photoData.value = '';
            preview.classList.add('hidden');
            retakeBtn.classList.add('hidden');
            startBtn.classList.remove('hidden');
            
            // Restart camera
            try {
                stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
                video.srcObject = stream;
                startBtn.classList.add('hidden');
                captureBtn.classList.remove('hidden');
                video.classList.remove('hidden');
            } catch (err) {
                showCustomPopup("Camera access denied.", "error");
            }
        });

        document.getElementById('manualEntryForm').addEventListener('submit', (e) => {
            if (!photoData.value) {
                e.preventDefault();
                photoErr.classList.remove('hidden');
            }
        });

        function toggleSidebar() {
            const sb = document.getElementById('sidebar');
            if(sb.classList.contains('-translate-x-full')) {
                sb.classList.remove('-translate-x-full');
                sb.classList.remove('hidden');
            } else {
                sb.classList.add('-translate-x-full');
                setTimeout(() => sb.classList.add('hidden'), 300);
            }
        }
        function toggleDesktopSidebar() {
            const sb = document.getElementById('sidebar');
            const main = document.getElementById('main-content');
            const icon = document.getElementById('sidebar-toggle-icon');
            
            if(sb.classList.contains('md:translate-x-0')) {
                sb.classList.remove('md:translate-x-0');
                sb.classList.add('-translate-x-full');
                main.classList.remove('md:ml-64');
                main.classList.add('md:ml-0');
                if(icon) {
                    icon.setAttribute('data-lucide', 'panel-left-open');
                    lucide.createIcons();
                }
            } else {
                sb.classList.add('md:translate-x-0');
                sb.classList.remove('-translate-x-full');
                main.classList.add('md:ml-64');
                main.classList.remove('md:ml-0');
                if(icon) {
                    icon.setAttribute('data-lucide', 'panel-left-close');
                    lucide.createIcons();
                }
            }
        }
    </script>
<script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>