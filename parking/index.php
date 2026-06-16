<?php
session_start();
require_once '../includes/db_config.php';
require_once '../api/check_overstay.php';

// Protection: Only allow access if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];

// Cleanup expired temporary names automatically on page load
// Check for overstay notifications before clearing
@mysqli_query($conn, "ALTER TABLE parking_requests ADD COLUMN IF NOT EXISTS overstay_notified TINYINT(1) DEFAULT 0");

$overstay_q = mysqli_query($conn, "SELECT pr.id, pr.requester_id, pr.end_time, ps.slot_number, u.email, p.full_name, 
                                  (SELECT building_name FROM buildings WHERE id = pr.building_id) as building_name
                                  FROM parking_requests pr 
                                  JOIN users u ON pr.requester_id = u.id 
                                  LEFT JOIN user_profiles p ON u.id = p.user_id 
                                  JOIN parking_slots ps ON pr.slot_id = ps.id
                                  WHERE pr.status = 'approved' 
                                  AND pr.end_time <= NOW() 
                                  AND pr.overstay_notified = 0
                                  AND ps.temporary_until = pr.end_time");

if ($overstay_q && mysqli_num_rows($overstay_q) > 0) {
    require_once '../vendor/autoload.php';
    while ($ov = mysqli_fetch_assoc($overstay_q)) {
        $to_email = $ov['email'];
        $to_name = $ov['full_name'] ?? 'Resident';
        $slot = $ov['slot_number'];
        $b_name = $ov['building_name'] ?? 'Nibash';
        
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'suchak9931@gmail.com'; 
            $mail->Password   = 'ubaz ayum yhyy hyis';   
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom('suchak9931@gmail.com', 'Nibash System');
            $mail->addAddress($to_email, $to_name);
            $mail->isHTML(true);
            $mail->Subject = 'Parking Time Exceeded Alert';
            $mail->Body    = "Hello $to_name,<br><br>Your parking booking for slot <strong>$slot</strong> in $b_name has exceeded its time limit (ended at {$ov['end_time']}).<br><br>Please move your vehicle immediately to avoid penalties or towing.<br><br>Thank you,<br>Nibash Management";
            $mail->send();
        } catch (Exception $e) {}
        
        // Notify in system
        $msg = mysqli_real_escape_string($conn, "Your parking time for slot $slot has exceeded. Please move your car immediately.");
        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link) VALUES ('{$ov['requester_id']}', 'Parking Time Exceeded', '$msg', 'parking/index.php')");
        
        mysqli_query($conn, "UPDATE parking_requests SET overstay_notified = 1 WHERE id = '{$ov['id']}'");
    }
}

mysqli_query($conn, "UPDATE parking_slots SET temporary_name = NULL, temporary_until = NULL WHERE temporary_until IS NOT NULL AND temporary_until <= NOW()");

$user_building_id = '';
$user_apt_id = null;
$user_apt_number = '';

// Find building
$building_q = mysqli_query($conn, "SELECT a.building_id, a.id as apt_id, a.apt_number 
                                   FROM apartment_assignments aa 
                                   JOIN apartments a ON a.id = aa.apt_id 
                                   WHERE aa.user_id = '$user_id' AND aa.is_active = 1 
                                   LIMIT 1");

if ($building_q && mysqli_num_rows($building_q) > 0) {
    $row = mysqli_fetch_assoc($building_q);
    $user_building_id = $row['building_id'];
    $user_apt_id = $row['apt_id'];
    $user_apt_number = $row['apt_number'];
} else {
    // If owner with no apartment assignment, try to fetch any building
    if ($role_id == 1) {
        $own_q = mysqli_query($conn, "SELECT id as building_id FROM apartments LIMIT 1");
        if ($own_q && mysqli_num_rows($own_q) > 0) {
            $user_building_id = mysqli_fetch_assoc($own_q)['building_id'];
        }
    }
}

// Fetch parking slots
$slots_q = mysqli_query($conn, "SELECT p.*, a.apt_number, 
    (SELECT aa.user_id FROM apartment_assignments aa WHERE aa.apt_id = p.apt_id AND aa.is_active = 1 LIMIT 1) as target_resident_id,
    (SELECT start_time FROM parking_requests pr WHERE pr.slot_id = p.id AND pr.status = 'approved' AND pr.end_time = p.temporary_until LIMIT 1) as current_booking_start,
    (SELECT requester_id FROM parking_requests pr WHERE pr.slot_id = p.id AND pr.status = 'approved' AND pr.end_time = p.temporary_until LIMIT 1) as current_booking_requester_id,
    (SELECT id FROM parking_requests pr WHERE pr.slot_id = p.id AND pr.status = 'approved' AND pr.end_time = p.temporary_until LIMIT 1) as current_booking_request_id
    FROM parking_slots p 
    LEFT JOIN apartments a ON p.apt_id = a.id 
    WHERE p.building_id = '$user_building_id' 
    ORDER BY p.slot_number ASC");

$all_slots = [];
$total_spots = 0;
$available_now = 0;
$my_spots = 0;

if ($slots_q) {
    while ($slot = mysqli_fetch_assoc($slots_q)) {
        $total_spots++;
        $slot['target_resident_id'] = $slot['target_resident_id'] ?? null;
        
        if ($slot['apt_id'] == $user_apt_id && $user_apt_id != null) {
            $my_spots++;
            $slot['display_status'] = ($slot['current_status'] == 'Occupied') ? 'mine_occupied' : 'mine_vacant';
        } else {
            if ($slot['current_status'] == 'Occupied') {
                $slot['display_status'] = 'unavailable';
            } else {
                $slot['display_status'] = 'available';
                $available_now++;
            }
        }
        
        $slot['booking_start'] = $slot['current_booking_start'] ?? '';
        $slot['booking_end'] = $slot['temporary_until'] ?? '';
        $slot['active_requester_id'] = $slot['current_booking_requester_id'] ?? '';
        $slot['active_request_id'] = $slot['current_booking_request_id'] ?? '';
        $all_slots[] = $slot;
    }
}

$slot_blocks = array_chunk($all_slots, 16);

// Dynamic sizing based on total spots
$gap_class = "gap-4";
$slot_w_class = "w-28";
$slot_h_class = "h-40";
$slot_text_class = "text-2xl";
$car_w_class = "w-16";
$car_h_class = "h-24";
$car_text_class = "text-[9px]";

if ($total_spots <= 6) {
    $gap_class = "gap-12";
    $slot_w_class = "w-40";
    $slot_h_class = "h-56";
    $slot_text_class = "text-4xl";
    $car_w_class = "w-24";
    $car_h_class = "h-36";
    $car_text_class = "text-xs";
} elseif ($total_spots <= 12) {
    $gap_class = "gap-8";
    $slot_w_class = "w-32";
    $slot_h_class = "h-48";
    $slot_text_class = "text-3xl";
    $car_w_class = "w-20";
    $car_h_class = "h-32";
    $car_text_class = "text-[10px]";
}

// Fetch Requests
$requests = [];
if ($role_id == 1) {
    $req_q = mysqli_query($conn, "SELECT pr.*, ps.slot_number, u.email as requester_email, p.full_name as requester_name 
                                  FROM parking_requests pr 
                                  JOIN parking_slots ps ON pr.slot_id = ps.id 
                                  JOIN users u ON pr.requester_id = u.id 
                                  LEFT JOIN user_profiles p ON u.id = p.user_id 
                                  WHERE pr.building_id = '$user_building_id' 
                                  AND pr.status = 'pending_owner' 
                                  ORDER BY pr.created_at DESC");
} else {
    $req_q = mysqli_query($conn, "SELECT pr.*, ps.slot_number, u.email as requester_email, p.full_name as requester_name 
                                  FROM parking_requests pr 
                                  JOIN parking_slots ps ON pr.slot_id = ps.id 
                                  JOIN users u ON pr.requester_id = u.id 
                                  LEFT JOIN user_profiles p ON u.id = p.user_id 
                                  WHERE pr.target_resident_id = '$user_id' 
                                  AND pr.status = 'pending_resident' 
                                  ORDER BY pr.created_at DESC");
}

if ($req_q) {
    while ($r = mysqli_fetch_assoc($req_q)) {
        $r['requester_name'] = $r['requester_name'] ?? $r['requester_email'];
        $requests[] = $r;
    }
}
$pending_count = count($requests);

// Fetch History
$history = [];
if ($role_id == 1) {
    $hist_q = mysqli_query($conn, "SELECT pr.*, ps.slot_number, u.email as requester_email, p.full_name as requester_name,
                                  (SELECT COUNT(*) FROM parking_requests pr2 WHERE pr2.requester_id = pr.requester_id AND pr2.slot_id = pr.slot_id AND pr2.status = 'approved') as times_booked
                                  FROM parking_requests pr 
                                  JOIN parking_slots ps ON pr.slot_id = ps.id 
                                  JOIN users u ON pr.requester_id = u.id 
                                  LEFT JOIN user_profiles p ON u.id = p.user_id 
                                  WHERE pr.building_id = '$user_building_id' AND pr.status = 'approved'
                                  ORDER BY pr.created_at DESC LIMIT 50");
} else {
    $hist_q = mysqli_query($conn, "SELECT pr.*, ps.slot_number, u.email as requester_email, p.full_name as requester_name,
                                  (SELECT COUNT(*) FROM parking_requests pr2 WHERE pr2.requester_id = '$user_id' AND pr2.status = 'approved') as total_own_bookings
                                  FROM parking_requests pr 
                                  JOIN parking_slots ps ON pr.slot_id = ps.id 
                                  JOIN users u ON pr.requester_id = u.id 
                                  LEFT JOIN user_profiles p ON u.id = p.user_id 
                                  WHERE pr.requester_id = '$user_id' 
                                  ORDER BY pr.created_at DESC LIMIT 50");
}

if ($hist_q) {
    while ($r = mysqli_fetch_assoc($hist_q)) {
        $r['requester_name'] = $r['requester_name'] ?? $r['requester_email'];
        $history[] = $r;
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
    <title>Parking Terminal | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php if ($role_id == 1): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <?php else: ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <?php endif; ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .canvas-shadow { box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.1), 0 20px 40px -20px rgba(0, 0, 0, 0.05); }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        .car-shape {
            box-shadow: inset 0 4px 10px rgba(255,255,255,0.4), 0 10px 15px -3px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: localStorage.getItem('desktopSidebar') === 'false' ? false : true, bookingModal: false, addSpotModal: false, selectedSpot: '', selectedSpotId: '', selectedSpotTarget: '', bookingStart: '', bookingEnd: '', minStart: '', userId: <?php echo $user_id; ?>, activeRequestId: '', activeRequesterId: '' }" x-init="$watch('desktopSidebarOpen', val => localStorage.setItem('desktopSidebar', val))">

    <?php 
        $active_page = 'parking';
        if ($role_id == 1) {
            include '../includes/owner_sidebar.php'; 
        } else {
            include '../includes/resident_sidebar.php'; 
        }
    ?>

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
            
            <!-- Header -->
            <header class="bg-white/80 backdrop-blur-xl border-b border-emerald-50 sticky top-0 z-40 shadow-sm px-8 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Parking Terminal</span>
                            <span class="text-emerald-600 font-medium text-[10px] uppercase tracking-widest bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-100">Live</span>
                        </h2>
                    </div>
                </div>

                <div class="flex items-center gap-5">
                    <?php if ($role_id == 1): ?>
                    <button @click="addSpotModal = true" class="hidden sm:flex items-center gap-2 px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-full text-sm font-bold transition-all shadow-md">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        Add Parking Space
                    </button>
                    <?php endif; ?>
                    
                    <button class="hidden sm:flex items-center gap-2 px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-full text-sm font-bold transition-all shadow-md shadow-emerald-200">
                        <i data-lucide="megaphone" class="w-4 h-4"></i>
                        Post Parking Space Rent
                    </button>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto scrollbar-hide p-6 sm:p-8 lg:p-10">
                
                <!-- Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                    <div class="bg-white p-6 rounded-[2rem] border border-emerald-100 shadow-sm flex items-center gap-5 hover:-translate-y-1 transition-transform">
                        <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center">
                            <i data-lucide="car" class="w-6 h-6 text-emerald-600"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Total Spots</p>
                            <h3 class="text-3xl font-black text-slate-800"><?php echo $total_spots; ?></h3>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-[2rem] border border-emerald-100 shadow-sm flex items-center gap-5 hover:-translate-y-1 transition-transform">
                        <div class="w-14 h-14 bg-sky-50 rounded-2xl flex items-center justify-center">
                            <i data-lucide="check-circle-2" class="w-6 h-6 text-sky-600"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Available Now</p>
                            <h3 class="text-3xl font-black text-slate-800"><?php echo $available_now; ?></h3>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-[2rem] border border-emerald-100 shadow-sm flex items-center gap-5 hover:-translate-y-1 transition-transform">
                        <div class="w-14 h-14 bg-indigo-50 rounded-2xl flex items-center justify-center">
                            <i data-lucide="key" class="w-6 h-6 text-indigo-600"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest"><?php echo $role_id == 1 ? 'Owner Spots' : 'My Spots'; ?></p>
                            <h3 class="text-3xl font-black text-slate-800"><?php echo $my_spots; ?></h3>
                        </div>
                    </div>
                </div>

                <!-- Interactive Parking Map -->
                <div class="mb-12">
                    <h3 class="text-lg font-bold text-slate-900 mb-6 flex items-center gap-2">
                        <i data-lucide="map" class="w-5 h-5 text-emerald-500"></i>
                        Interactive Zone Map
                    </h3>
                    
                    <div class="w-full overflow-x-auto pb-4 scrollbar-hide">
                        <div class="min-w-[900px] bg-slate-50/50 rounded-[3rem] p-10 relative border border-slate-200">
                            
                            <?php foreach ($slot_blocks as $block_index => $block): ?>
                                <?php
                                    $half = ceil(count($block) / 2);
                                    $top_row = array_slice($block, 0, $half);
                                    $bottom_row = array_slice($block, $half);
                                ?>
                                <?php if ($block_index > 0): ?>
                                    <div class="my-16 flex items-center justify-center relative">
                                        <div class="absolute inset-x-0 h-px bg-gradient-to-r from-transparent via-slate-300 to-transparent"></div>
                                        <div class="relative bg-slate-50 px-4 text-slate-400 backdrop-blur-sm rounded-full border border-slate-200 py-1">
                                            <span class="text-[10px] font-black tracking-[0.2em] uppercase">Next Block</span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Top Row of Spaces -->
                                <div class="flex justify-center <?php echo $gap_class; ?>">
                                    <?php foreach ($top_row as $spot): ?>
                                    <?php 
                                        $bg = 'bg-white';
                                        $border = 'border-slate-200';
                                        $textColor = 'text-slate-300';
                                        
                                        if (strpos($spot['display_status'], 'mine') !== false) {
                                            $bg = 'bg-indigo-50 border-indigo-200';
                                            $textColor = 'text-indigo-300';
                                            $cursor = 'cursor-default';
                                            $clickAction = "";
                                        } elseif ($spot['display_status'] == 'unavailable') {
                                            $bg = 'bg-rose-50 border-rose-200';
                                            $textColor = 'text-rose-300';
                                            $cursor = 'cursor-pointer hover:border-rose-400 hover:shadow-md';
                                            $clickAction = "@click=\"selectedSpot = '{$spot['slot_number']}'; selectedSpotId = '{$spot['id']}'; selectedSpotTarget = '{$spot['target_resident_id']}'; bookingModal = true; activeRequestId = '{$spot['active_request_id']}'; activeRequesterId = '{$spot['active_requester_id']}'\"";
                                        } else {
                                            $cursor = 'cursor-pointer hover:border-emerald-400 hover:shadow-md';
                                            $clickAction = "@click=\"selectedSpot = '{$spot['slot_number']}'; selectedSpotId = '{$spot['id']}'; selectedSpotTarget = '{$spot['target_resident_id']}'; bookingModal = true; activeRequestId = '{$spot['active_request_id']}'; activeRequesterId = '{$spot['active_requester_id']}'\"";
                                        }
                                        
                                        $slot_display = !empty($spot['apt_number']) ? $spot['apt_number'] : $spot['slot_number'];
                                        if (!empty($spot['temporary_name']) && !empty($spot['temporary_until']) && strtotime($spot['temporary_until']) > time()) {
                                            $slot_display = $spot['temporary_name'];
                                        }
                                    ?>
                                    <div class="<?php echo $slot_w_class; ?> <?php echo $slot_h_class; ?> border-2 <?php echo $border; ?> rounded-t-2xl border-b-0 flex flex-col items-center justify-start pt-4 relative transition-all shadow-sm <?php echo $bg; ?> <?php echo $cursor; ?>" <?php echo $clickAction; ?>>
                                        <?php if ($role_id == 1): ?>
                                        <form action="../api/parking_actions.php" method="POST" class="absolute top-1 right-1 z-20" onsubmit="return confirm('Are you sure you want to delete this parking slot?');" onclick="event.stopPropagation();">
                                            <input type="hidden" name="action" value="delete_spot">
                                            <input type="hidden" name="slot_id" value="<?php echo $spot['id']; ?>">
                                            <button type="submit" class="w-6 h-6 flex items-center justify-center bg-white/80 hover:bg-rose-100 text-rose-500 rounded-full border border-slate-200 transition-colors" onclick="event.stopPropagation();">
                                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <span class="<?php echo $textColor; ?> <?php echo $slot_text_class; ?> font-black truncate w-full text-center px-1" title="<?php echo $slot_display; ?>"><?php echo $slot_display; ?></span>
                                        
                                        <?php if ($spot['display_status'] == 'available'): ?>
                                            <div class="my-auto px-3 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full uppercase tracking-widest">Available</div>
                                        <?php elseif ($spot['display_status'] == 'unavailable'): ?>
                                            <div class="<?php echo $car_w_class; ?> <?php echo $car_h_class; ?> bg-rose-400 rounded-2xl my-auto shadow-sm relative car-shape flex items-center justify-center">
                                                <div class="absolute inset-2 bg-rose-300 rounded-xl"></div>
                                                <span class="text-white <?php echo $car_text_class; ?> font-bold relative z-10 rotate-90 whitespace-nowrap tracking-widest"><?php echo !empty($spot['temporary_plate']) ? htmlspecialchars($spot['temporary_plate']) : (!empty($spot['license_plate']) ? htmlspecialchars($spot['license_plate']) : 'UNAVAILABLE'); ?></span>
                                            </div>
                                        <?php elseif ($spot['display_status'] == 'mine_occupied'): ?>
                                            <div class="<?php echo $car_w_class; ?> <?php echo $car_h_class; ?> bg-indigo-500 rounded-2xl my-auto shadow-lg relative car-shape flex items-center justify-center">
                                                <div class="absolute inset-2 bg-indigo-400 rounded-xl"></div>
                                                <span class="text-white <?php echo $car_text_class; ?> font-bold relative z-10 rotate-90 whitespace-nowrap tracking-widest"><?php echo !empty($spot['temporary_plate']) ? htmlspecialchars($spot['temporary_plate']) : (!empty($spot['license_plate']) ? htmlspecialchars($spot['license_plate']) : 'MY CAR'); ?></span>
                                            </div>
                                        <?php elseif ($spot['display_status'] == 'mine_vacant'): ?>
                                            <div class="my-auto px-3 py-1 bg-indigo-100 text-indigo-700 text-[10px] font-bold rounded-full uppercase tracking-widest text-center">My Spot<br>Empty</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Central Driveway -->
                            <div class="h-20 w-full flex items-center justify-center relative my-2">
                                <div class="w-full border-t-4 border-dashed border-slate-300"></div>
                                <div class="absolute px-6 bg-slate-50/50 text-slate-400 font-black tracking-[0.3em] uppercase text-xl backdrop-blur-sm rounded-full">
                                    <i data-lucide="arrow-left-right" class="w-6 h-6 inline-block mr-2 -mt-1"></i> Driveway
                                </div>
                            </div>
                            
                            <!-- Bottom Row of Spaces -->
                            <div class="flex justify-center <?php echo $gap_class; ?>">
                                <?php foreach ($bottom_row as $spot): ?>
                                    <?php 
                                        $bg = 'bg-white';
                                        $border = 'border-slate-200';
                                        $textColor = 'text-slate-300';
                                        
                                        if (strpos($spot['display_status'], 'mine') !== false) {
                                            $bg = 'bg-indigo-50 border-indigo-200';
                                            $textColor = 'text-indigo-300';
                                            $cursor = 'cursor-default';
                                            $clickAction = "";
                                        } elseif ($spot['display_status'] == 'unavailable') {
                                            $bg = 'bg-rose-50 border-rose-200';
                                            $textColor = 'text-rose-300';
                                            $cursor = 'cursor-pointer hover:border-rose-400 hover:shadow-md';
                                            $clickAction = "@click=\"selectedSpot = '{$spot['slot_number']}'; selectedSpotId = '{$spot['id']}'; selectedSpotTarget = '{$spot['target_resident_id']}'; bookingStart = '{$spot['booking_start']}'; bookingEnd = '{$spot['booking_end']}'; minStart = bookingEnd ? bookingEnd.replace(' ', 'T').slice(0, 16) : ''; bookingModal = true; activeRequestId = '{$spot['active_request_id']}'; activeRequesterId = '{$spot['active_requester_id']}'\"";
                                        } else {
                                            $cursor = 'cursor-pointer hover:border-emerald-400 hover:shadow-md';
                                            $clickAction = "@click=\"selectedSpot = '{$spot['slot_number']}'; selectedSpotId = '{$spot['id']}'; selectedSpotTarget = '{$spot['target_resident_id']}'; bookingStart = '{$spot['booking_start']}'; bookingEnd = '{$spot['booking_end']}'; minStart = bookingEnd ? bookingEnd.replace(' ', 'T').slice(0, 16) : ''; bookingModal = true; activeRequestId = '{$spot['active_request_id']}'; activeRequesterId = '{$spot['active_requester_id']}'\"";
                                        }
                                        
                                        $slot_display = !empty($spot['apt_number']) ? $spot['apt_number'] : $spot['slot_number'];
                                        if (!empty($spot['temporary_name']) && !empty($spot['temporary_until']) && strtotime($spot['temporary_until']) > time()) {
                                            $slot_display = $spot['temporary_name'];
                                        }
                                    ?>
                                    <div class="<?php echo $slot_w_class; ?> <?php echo $slot_h_class; ?> border-2 <?php echo $border; ?> rounded-b-2xl border-t-0 flex flex-col items-center justify-end pb-4 relative transition-all shadow-sm <?php echo $bg; ?> <?php echo $cursor; ?>" <?php echo $clickAction; ?>>
                                        <?php if ($role_id == 1): ?>
                                        <form action="../api/parking_actions.php" method="POST" class="absolute bottom-1 right-1 z-20" onsubmit="return confirm('Are you sure you want to delete this parking slot?');" onclick="event.stopPropagation();">
                                            <input type="hidden" name="action" value="delete_spot">
                                            <input type="hidden" name="slot_id" value="<?php echo $spot['id']; ?>">
                                            <button type="submit" class="w-6 h-6 flex items-center justify-center bg-white/80 hover:bg-rose-100 text-rose-500 rounded-full border border-slate-200 transition-colors" onclick="event.stopPropagation();">
                                                <i data-lucide="trash-2" class="w-3 h-3"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($spot['display_status'] == 'available'): ?>
                                            <div class="my-auto px-3 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-bold rounded-full uppercase tracking-widest">Available</div>
                                        <?php elseif ($spot['display_status'] == 'unavailable'): ?>
                                            <div class="<?php echo $car_w_class; ?> <?php echo $car_h_class; ?> bg-rose-400 rounded-2xl my-auto shadow-sm relative car-shape flex items-center justify-center">
                                                <div class="absolute inset-2 bg-rose-300 rounded-xl"></div>
                                                <span class="text-white <?php echo $car_text_class; ?> font-bold relative z-10 rotate-90 whitespace-nowrap tracking-widest"><?php echo !empty($spot['temporary_plate']) ? htmlspecialchars($spot['temporary_plate']) : (!empty($spot['license_plate']) ? htmlspecialchars($spot['license_plate']) : 'UNAVAILABLE'); ?></span>
                                            </div>
                                        <?php elseif ($spot['display_status'] == 'mine_occupied'): ?>
                                            <div class="<?php echo $car_w_class; ?> <?php echo $car_h_class; ?> bg-indigo-500 rounded-2xl my-auto shadow-lg relative car-shape flex items-center justify-center">
                                                <div class="absolute inset-2 bg-indigo-400 rounded-xl"></div>
                                                <span class="text-white <?php echo $car_text_class; ?> font-bold relative z-10 rotate-90 whitespace-nowrap tracking-widest"><?php echo !empty($spot['temporary_plate']) ? htmlspecialchars($spot['temporary_plate']) : (!empty($spot['license_plate']) ? htmlspecialchars($spot['license_plate']) : 'MY CAR'); ?></span>
                                            </div>
                                        <?php elseif ($spot['display_status'] == 'mine_vacant'): ?>
                                            <div class="my-auto px-3 py-1 bg-indigo-100 text-indigo-700 text-[10px] font-bold rounded-full uppercase tracking-widest text-center">My Spot<br>Empty</div>
                                        <?php endif; ?>
                                        
                                        <span class="<?php echo $textColor; ?> <?php echo $slot_text_class; ?> font-black mt-2 truncate w-full text-center px-1" title="<?php echo $slot_display; ?>"><?php echo $slot_display; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                            
                        </div>
                    </div>
                </div>

                <!-- Requests Section -->
                <div class="bg-white rounded-[2rem] border border-emerald-100 p-8 shadow-sm">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                            <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center border border-emerald-100">
                                <i data-lucide="inbox" class="w-5 h-5 text-emerald-600"></i>
                            </div>
                            <?php echo $role_id == 1 ? "Pending Approvals" : "Requests for My Spot"; ?>
                        </h3>
                        <span class="px-3 py-1 bg-emerald-100 border border-emerald-200 text-emerald-700 text-xs font-bold rounded-full shadow-sm"><?php echo $pending_count; ?> Pending</span>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if ($pending_count == 0): ?>
                            <div class="p-8 text-center text-slate-400 font-medium bg-slate-50/50 rounded-[1.5rem] border border-slate-100">
                                No pending requests at the moment.
                            </div>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                                <?php 
                                    $initials = strtoupper(substr($req['requester_name'], 0, 2));
                                    $start = date('M d, h:i A', strtotime($req['start_time']));
                                    $end = date('M d, h:i A', strtotime($req['end_time']));
                                ?>
                                <div class="p-5 rounded-[1.5rem] border border-slate-100 hover:border-emerald-200 transition-colors bg-slate-50/50 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                                    <div class="flex items-start md:items-center gap-4">
                                        <div class="w-12 h-12 bg-white border border-slate-200 rounded-full flex items-center justify-center font-bold text-slate-500 shadow-sm shrink-0">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($req['requester_name']); ?> <span class="text-xs text-slate-400 font-medium ml-1">requested Spot <?php echo $req['slot_number']; ?></span></h4>
                                            <div class="flex items-center gap-2 mt-1.5 text-xs text-slate-500 font-medium bg-white px-3 py-1.5 rounded-lg border border-slate-100 w-fit">
                                                <i data-lucide="clock" class="w-3.5 h-3.5 text-slate-400"></i>
                                                <?php echo $start; ?> - <?php echo $end; ?>
                                            </div>
                                            <p class="text-sm font-medium text-emerald-700 mt-2 italic border-l-2 border-emerald-300 pl-3">"<?php echo htmlspecialchars($req['purpose']); ?>"</p>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 w-full md:w-auto shrink-0 mt-4 md:mt-0">
                                        <form action="../api/parking_actions.php" method="POST" class="inline-block flex-1 md:flex-none">
                                            <input type="hidden" name="action" value="approve_request">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" class="w-full px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-xl transition-all shadow-md shadow-emerald-200 flex items-center justify-center gap-2">
                                                <i data-lucide="check" class="w-4 h-4"></i> Approve
                                            </button>
                                        </form>
                                        <form action="../api/parking_actions.php" method="POST" class="inline-block flex-1 md:flex-none">
                                            <input type="hidden" name="action" value="reject_request">
                                            <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                            <button type="submit" class="w-full px-6 py-2.5 bg-white border border-slate-200 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 text-slate-600 text-sm font-bold rounded-xl transition-all flex items-center justify-center gap-2">
                                                <i data-lucide="x" class="w-4 h-4"></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- History Section -->
                <div class="mt-8 bg-white rounded-[2rem] border border-emerald-100 p-8 shadow-sm">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                            <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center border border-slate-100">
                                <i data-lucide="history" class="w-5 h-5 text-slate-600"></i>
                            </div>
                            Booking History
                        </h3>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-slate-200">
                                    <th class="py-4 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Date</th>
                                    <?php if ($role_id == 1): ?>
                                    <th class="py-4 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Resident</th>
                                    <?php endif; ?>
                                    <th class="py-4 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Spot</th>
                                    <th class="py-4 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Duration</th>
                                    <th class="py-4 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest">Status</th>
                                    <th class="py-4 px-4 text-xs font-bold text-slate-400 uppercase tracking-widest text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm font-medium text-slate-600">
                                <?php if (empty($history)): ?>
                                    <tr>
                                        <td colspan="6" class="py-8 text-center text-slate-400">No booking history available.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($history as $h): ?>
                                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                                            <td class="py-4 px-4 whitespace-nowrap"><?php echo date('M d, Y', strtotime($h['created_at'])); ?></td>
                                            <?php if ($role_id == 1): ?>
                                            <td class="py-4 px-4 font-bold text-slate-800"><?php echo htmlspecialchars($h['requester_name']); ?></td>
                                            <?php endif; ?>
                                            <td class="py-4 px-4">
                                                <span class="px-2.5 py-1 bg-slate-100 rounded-lg border border-slate-200 font-bold"><?php echo htmlspecialchars($h['slot_number']); ?></span>
                                            </td>
                                            <td class="py-4 px-4 whitespace-nowrap text-xs">
                                                <div class="text-slate-600">
                                                    <span class="font-bold text-slate-800"><?php echo date('M d', strtotime($h['start_time'])); ?></span> <?php echo date('h:i A', strtotime($h['start_time'])); ?>
                                                    <br><span class="text-[10px] text-slate-400">to</span><br>
                                                    <span class="font-bold text-slate-800"><?php echo date('M d', strtotime($h['end_time'])); ?></span> <?php echo date('h:i A', strtotime($h['end_time'])); ?>
                                                </div>
                                            </td>
                                            <td class="py-4 px-4">
                                                <?php if ($h['status'] == 'approved'): ?>
                                                    <span class="px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-full text-[10px] font-bold uppercase tracking-widest">Approved</span>
                                                <?php elseif ($h['status'] == 'rejected'): ?>
                                                    <span class="px-2.5 py-1 bg-rose-100 text-rose-700 rounded-full text-[10px] font-bold uppercase tracking-widest">Rejected</span>
                                                <?php elseif ($h['status'] == 'cancelled'): ?>
                                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-full text-[10px] font-bold uppercase tracking-widest">Cancelled</span>
                                                <?php else: ?>
                                                    <span class="px-2.5 py-1 bg-amber-100 text-amber-700 rounded-full text-[10px] font-bold uppercase tracking-widest">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-4 px-4 text-right">
                                                <?php if ($h['status'] == 'approved' && strtotime($h['end_time']) > time() && $h['requester_id'] == $user_id): ?>
                                                    <form action="../api/parking_actions.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this active booking?');" class="inline-block mr-2">
                                                        <input type="hidden" name="action" value="cancel_booking">
                                                        <input type="hidden" name="request_id" value="<?php echo $h['id']; ?>">
                                                        <button type="submit" class="p-2 bg-amber-50 hover:bg-amber-100 text-amber-500 rounded-lg transition-colors" title="Cancel Booking">
                                                            <i data-lucide="x-circle" class="w-4 h-4"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form action="../api/parking_actions.php" method="POST" onsubmit="return confirm('Are you sure you want to delete this booking record?');" class="inline-block">
                                                    <input type="hidden" name="action" value="delete_history">
                                                    <input type="hidden" name="request_id" value="<?php echo $h['id']; ?>">
                                                    <button type="submit" class="p-2 bg-rose-50 hover:bg-rose-100 text-rose-500 rounded-lg transition-colors" title="Delete Record">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Booking Request Modal -->
    <div x-show="bookingModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <!-- Backdrop -->
        <div x-show="bookingModal" x-transition.opacity class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="bookingModal = false"></div>
        
        <!-- Modal Content -->
        <div x-show="bookingModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-8 scale-95"
             class="relative bg-white w-full max-w-lg rounded-[2.5rem] shadow-2xl overflow-hidden border border-emerald-100">
            
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-xl font-black text-slate-800 flex items-center gap-2">
                    <i data-lucide="calendar-plus" class="w-5 h-5 text-emerald-600"></i>
                    Book Spot <span x-text="selectedSpot" class="text-emerald-600"></span>
                </h3>
                <button @click="bookingModal = false" class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-xl transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-8">
                <!-- Unavailable Notice -->
                <template x-if="bookingEnd && new Date(bookingEnd.replace(' ', 'T')) > new Date()">
                    <div class="mb-6 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-700 font-medium flex gap-3 items-start shadow-sm">
                        <i data-lucide="info" class="w-5 h-5 text-rose-500 shrink-0 mt-0.5"></i>
                        <div>
                            This spot is currently booked and unavailable from 
                            <strong x-text="new Date(bookingStart.replace(' ', 'T')).toLocaleString('en-US', {month:'short', day:'numeric', hour:'numeric', minute:'2-digit'})"></strong> 
                            to <strong x-text="new Date(bookingEnd.replace(' ', 'T')).toLocaleString('en-US', {month:'short', day:'numeric', hour:'numeric', minute:'2-digit'})"></strong>.
                            <br>Your booking start time has automatically been adjusted to prevent overlap.
                        </div>
                    </div>
                </template>

                <template x-if="activeRequesterId && activeRequesterId == userId">
                    <div class="space-y-5 text-center">
                        <div class="mb-4 p-5 bg-indigo-50 border border-indigo-100 rounded-2xl">
                            <i data-lucide="check-circle" class="w-12 h-12 text-indigo-500 mx-auto mb-3"></i>
                            <h4 class="text-lg font-bold text-slate-800 mb-1">Your Booking is Active</h4>
                            <p class="text-sm text-slate-600">You currently have this spot booked until <strong x-text="new Date(bookingEnd.replace(' ', 'T')).toLocaleString('en-US', {month:'short', day:'numeric', hour:'numeric', minute:'2-digit'})"></strong>.</p>
                        </div>
                        <form action="../api/parking_actions.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this active booking?');">
                            <input type="hidden" name="action" value="cancel_booking">
                            <input type="hidden" name="request_id" :value="activeRequestId">
                            <button type="submit" class="w-full py-3.5 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-amber-500/20 transition-all transform hover:-translate-y-0.5 flex items-center justify-center gap-2">
                                <i data-lucide="x-circle" class="w-4 h-4"></i> Cancel Current Booking
                            </button>
                        </form>
                        <div class="relative flex items-center py-5">
                            <div class="flex-grow border-t border-slate-200"></div>
                            <span class="shrink-0 px-6 text-slate-400 text-sm font-medium">Or request a new time</span>
                            <div class="flex-grow border-t border-slate-200"></div>
                        </div>
                    </div>
                </template>

                <form action="../api/parking_actions.php" method="POST" class="space-y-5">
                        <input type="hidden" name="action" value="book_spot">
                        <input type="hidden" name="slot_id" :value="selectedSpotId">
                        <input type="hidden" name="target_resident_id" :value="selectedSpotTarget">
                        <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($user_building_id); ?>">
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Start Time</label>
                                <input type="datetime-local" name="start_time" required :min="minStart" :value="minStart" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">End Time</label>
                                <input type="datetime-local" name="end_time" required :min="minStart" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all">
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Guest License Plate</label>
                            <input type="text" name="license_plate" placeholder="e.g. DHK-123" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all uppercase">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-widest mb-2">Purpose of Booking</label>
                            <textarea name="purpose" placeholder="e.g. My brother is visiting for the weekend..." required class="w-full h-28 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition-all resize-none"></textarea>
                        </div>
                        
                        <div class="pt-4">
                            <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-emerald-600/20 transition-all transform hover:-translate-y-0.5">
                                Submit Request
                            </button>
                            <p class="text-center text-xs text-slate-400 font-medium mt-3">Request will be sent for approval.</p>
                        </div>
                    </form>
            </div>
        </div>
    </div>

    <?php if ($role_id == 1): ?>
    <!-- Add Parking Space Modal -->
    <div x-show="addSpotModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
        <!-- Backdrop -->
        <div x-show="addSpotModal" x-transition.opacity class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="addSpotModal = false"></div>
        
        <!-- Modal Content -->
        <div x-show="addSpotModal" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-8 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-8 scale-95"
             class="relative bg-white w-full max-w-sm rounded-[2.5rem] shadow-2xl overflow-hidden border border-emerald-100 z-50">
            
            <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <h3 class="text-xl font-black text-slate-800 flex items-center gap-2">
                    <i data-lucide="plus-circle" class="w-5 h-5 text-emerald-600"></i>
                    Add Space
                </h3>
                <button @click="addSpotModal = false" class="p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-xl transition-colors">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            
            <div class="p-8">
                <form action="../api/parking_actions.php" method="POST" class="space-y-5">
                    <input type="hidden" name="action" value="add_spot">
                    <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($user_building_id); ?>">
                    
                    <div>
                        <p class="text-sm font-bold text-slate-600 mb-2 text-center bg-slate-100 p-4 rounded-xl">A new parking slot will be generated automatically.</p>
                    </div>
                    
                    <div class="pt-4">
                        <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-emerald-600/20 transition-all transform hover:-translate-y-0.5">
                            Save Space
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>
    <script>
        lucide.createIcons();
    </script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>