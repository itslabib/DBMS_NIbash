<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

require 'includes/db_config.php';

$user_id = (int) $_SESSION['user_id'];
$role_id = isset($_SESSION['role_id']) ? (int) $_SESSION['role_id'] : 2; // Default to resident if not set
$is_admin = ($role_id == 1);

$guest_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$guest = null;

if ($guest_id > 0) {
    if ($is_admin) {
        $q = 'SELECT g.*, g.full_name as guest_name, g.phone_number as guest_phone, g.nid_passport_no as id_number,
                     vr.id as visit_id, vr.purpose, vr.digital_pass_code, vr.status, vr.resident_id,
                     gv.plate_number as vehicle_plate
              FROM guests g 
              JOIN visit_requests vr ON g.id = vr.guest_id 
              LEFT JOIN guest_vehicles gv ON vr.id = gv.visit_id
              WHERE g.id = ?
              ORDER BY vr.id DESC LIMIT 1';
        $stmt = mysqli_prepare($conn, $q);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $guest_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                $guest = mysqli_fetch_assoc($result);
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $q = 'SELECT g.*, g.full_name as guest_name, g.phone_number as guest_phone, g.nid_passport_no as id_number,
                     vr.id as visit_id, vr.purpose, vr.digital_pass_code, vr.status,
                     gv.plate_number as vehicle_plate
              FROM guests g 
              JOIN visit_requests vr ON g.id = vr.guest_id 
              LEFT JOIN guest_vehicles gv ON vr.id = gv.visit_id
              WHERE g.id = ? AND vr.resident_id = ?
              ORDER BY vr.id DESC LIMIT 1';
        $stmt = mysqli_prepare($conn, $q);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ii', $guest_id, $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($result) {
                $guest = mysqli_fetch_assoc($result);
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($guest) {
        // Fetch latest entry scan if exists
        $visit_id = $guest['visit_id'];
        $entry_q = "SELECT id FROM entry_logs WHERE visit_id = $visit_id ORDER BY entry_time DESC LIMIT 1";
        $entry_res = mysqli_query($conn, $entry_q);
        if ($entry_res && $e_row = mysqli_fetch_assoc($entry_res)) {
            $entry_id = $e_row['id'];
            $scan_path = 'assets/uploads/scans/known/entry_' . $entry_id . '.jpg';
            if (file_exists(__DIR__ . '/' . $scan_path)) {
                $guest['scanned_photo'] = $scan_path;
            }
        }

        // Prepare images
        $profile_photo_path = 'assets/uploads/guests/guest_photo_' . $guest_id . '.jpg';
        if (file_exists(__DIR__ . '/' . $profile_photo_path)) {
            $guest['guest_photo'] = $profile_photo_path;
        }

        // Extract metadata from purpose string if possible
        $purpose_raw = $guest['purpose'] ?? '';
        
        // Extract Relationship
        if (preg_match('/^\[(.*?)\]/', $purpose_raw, $matches)) {
            $guest['relationship'] = $matches[1];
        } else {
            $guest['relationship'] = null;
        }

        // Extract Total Guests
        if (preg_match('/Total: (\d+)/', $purpose_raw, $matches)) {
            $guest['total_guests'] = $matches[1];
        } else {
            $guest['total_guests'] = 1;
        }

        // Extract Expected Date/Time
        if (preg_match('/Exp: ([\d-]+)(?:\s+([\d:]+))?/', $purpose_raw, $matches)) {
            $guest['expected_date'] = $matches[1];
            $guest['expected_time'] = isset($matches[2]) ? $matches[2] : null;
        } else {
            $guest['expected_date'] = null;
            $guest['expected_time'] = null;
        }
    }
}

function display_or_null($value)
{
    if ($value === null || $value === '') {
        return 'Null';
    }
    return htmlspecialchars((string) $value);
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
    <title>Guest Details | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?><?php echo $is_admin ? 'css/owner_style.css' : 'css/resident_style.css'; ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .hover-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -8px rgba(16, 185, 129, 0.15); border-color: #6ee7b7; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #ecfdf5; }
        ::-webkit-scrollbar-thumb { background: #a7f3d0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6ee7b7; }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php 
    if ($is_admin) {
        $active_page = 'guests';
        include 'includes/owner_sidebar.php'; 
    } else {
        include 'includes/resident_sidebar.php'; 
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
            
            <header class="bg-white/80 backdrop-blur-xl border-b border-emerald-50 sticky top-0 z-40 shadow-sm">
                <div class="px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Verification Details</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50 space-y-10">
                
                <?php if (!$guest): ?>
                    <div class="py-20 text-center flex flex-col items-center gap-4 bg-white rounded-[2rem] border-2 border-dashed border-slate-300 shadow-sm">
                        <div class="w-20 h-20 bg-slate-50 border border-slate-100 rounded-full flex items-center justify-center text-slate-300 shadow-sm">
                            <i data-lucide="user-x" class="w-10 h-10"></i>
                        </div>
                        <p class="text-slate-900 font-black text-xl">Guest Not Found</p>
                        <p class="text-sm text-slate-500 font-medium max-w-sm">This guest pass may have been deleted or does not exist.</p>
                    </div>
                <?php else: ?>

                    <div class="flex items-center mb-10">
                        <a href="<?php echo BASE_URL; ?><?php echo $is_admin ? 'owner/guest_entries.php' : 'resident/guest_passes.php'; ?>" class="flex items-center gap-2 text-sm font-black text-slate-500 hover:text-emerald-600 transition-colors">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                        </a>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 border-b border-slate-200">
                        <div class="space-y-3">
                            <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                                Guest Profile
                            </h1>
                            <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base">
                                <span class="p-1.5 bg-emerald-100 border border-emerald-200 rounded-lg"><i data-lucide="shield-check" class="w-4 h-4 text-emerald-700"></i></span>
                                Security details and scan records for this visitor.
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest px-4 py-2.5 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-200 shadow-sm">
                            <i data-lucide="check-circle" class="w-4 h-4"></i> Access Active
                        </span>
                    </div>

                    <div class="flex flex-col lg:flex-row gap-10 items-center lg:items-start bg-white p-8 sm:p-10 rounded-[2rem] border border-slate-200 shadow-sm relative overflow-hidden">
                        <div class="absolute left-0 top-0 w-1 h-full bg-emerald-400"></div>
                        
                        <div class="flex flex-col sm:flex-row gap-8 items-center justify-center">
                            <div class="flex flex-col items-center gap-3 group">
                                <div class="w-32 h-32 sm:w-40 sm:h-40 rounded-[1.5rem] overflow-hidden bg-slate-50 border-4 border-white shadow-md shadow-emerald-900/5 ring-1 ring-slate-100 relative group-hover:shadow-lg group-hover:shadow-emerald-500/20 transition-all">
                                    <?php if (!empty($guest['guest_photo'])): ?>
                                        <img src="<?php echo BASE_URL . htmlspecialchars($guest['guest_photo']); ?>"
                                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                                            alt="Guest Photo">
                                    <?php else: ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center text-slate-300">
                                            <i data-lucide="user" class="w-12 h-12 mb-1"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[9px] uppercase font-black tracking-widest text-slate-500 px-3 py-1 bg-slate-50 rounded-lg border border-slate-200 shadow-sm">Registered Profile</span>
                            </div>

                            <div class="flex flex-col items-center gap-3 group">
                                <div class="w-32 h-32 sm:w-40 sm:h-40 rounded-[1.5rem] overflow-hidden bg-slate-50 border-4 border-white shadow-md shadow-emerald-900/5 ring-1 <?php echo empty($guest['scanned_photo']) ? 'ring-slate-100' : 'ring-emerald-300'; ?> relative group-hover:shadow-lg group-hover:shadow-emerald-500/20 transition-all">
                                    <?php if (!empty($guest['scanned_photo'])): ?>
                                        <img src="<?php echo (strpos($guest['scanned_photo'], 'data:') === 0) ? $guest['scanned_photo'] : BASE_URL . htmlspecialchars($guest['scanned_photo']); ?>"
                                            class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
                                            alt="Real-time scan">
                                        <div class="absolute inset-0 bg-emerald-500/10 mix-blend-overlay"></div>
                                        <div class="absolute bottom-2 right-2 w-6 h-6 bg-emerald-500 rounded-full flex items-center justify-center border-2 border-white shadow-sm">
                                            <i data-lucide="check" class="w-3 h-3 text-white"></i>
                                        </div>
                                    <?php else: ?>
                                        <div class="w-full h-full flex flex-col items-center justify-center text-slate-400 bg-slate-50/50 border-2 border-dashed border-slate-200 rounded-xl m-1" style="width: calc(100% - 8px); height: calc(100% - 8px);">
                                            <i data-lucide="camera-off" class="w-8 h-8 opacity-40 mb-1"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <span class="text-[9px] uppercase font-black tracking-widest <?php echo empty($guest['scanned_photo']) ? 'text-slate-400 bg-slate-50 border-slate-200' : 'text-emerald-700 bg-emerald-50 border-emerald-200'; ?> px-3 py-1 rounded-lg border shadow-sm">Latest Scan</span>
                            </div>
                        </div>

                        <div class="flex-1 text-center lg:text-left w-full mt-2 lg:mt-0 lg:pt-6 border-t lg:border-t-0 lg:border-l border-slate-100 lg:pl-10">
                            <h3 class="text-3xl font-black text-slate-900 tracking-tight mb-2"><?php echo htmlspecialchars($guest['guest_name']); ?></h3>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center justify-center lg:justify-start gap-2">
                                <i data-lucide="phone" class="w-4 h-4 text-emerald-500"></i>
                                <?php echo htmlspecialchars($guest['guest_phone']); ?>
                            </p>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center gap-3 mb-6">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100 shadow-inner">
                                <i data-lucide="clipboard-list" class="w-5 h-5"></i>
                            </div>
                            <h2 class="text-xl font-black text-slate-900">Visit Information</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                            <?php
                            $details = [
                                ['icon' => 'users', 'label' => 'Total Guests', 'value' => display_or_null($guest['total_guests'] ?? 1)],
                                ['icon' => 'link', 'label' => 'Relationship', 'value' => display_or_null($guest['relationship'] ?? null)],
                                ['icon' => 'calendar', 'label' => 'Expected Date', 'value' => display_or_null($guest['expected_date'] ?? null)],
                                ['icon' => 'clock', 'label' => 'Expected Time', 'value' => display_or_null($guest['expected_time'] ?? null)],
                                ['icon' => 'credit-card', 'label' => 'Government ID', 'value' => display_or_null($guest['id_number'] ?? null)],
                                ['icon' => 'car', 'label' => 'Vehicle Plate', 'value' => display_or_null($guest['vehicle_plate'] ?? null)],
                            ];
                            foreach ($details as $d):
                            ?>
                            <div class="bg-white p-6 rounded-[1.5rem] border border-slate-200 shadow-sm hover-card relative group">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shadow-sm border border-slate-100 group-hover:bg-emerald-50 group-hover:border-emerald-100 transition-colors">
                                        <i data-lucide="<?php echo $d['icon']; ?>" class="w-4 h-4 text-slate-400 group-hover:text-emerald-500 transition-colors"></i>
                                    </div>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo $d['label']; ?></span>
                                </div>
                                <p class="text-base font-bold text-slate-800 pl-11"><?php echo $d['value']; ?></p>
                            </div>
                            <?php endforeach; ?>

                            <div class="bg-white p-6 rounded-[1.5rem] border border-slate-200 shadow-sm hover-card lg:col-span-2 relative group">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shadow-sm border border-slate-100 group-hover:bg-emerald-50 group-hover:border-emerald-100 transition-colors">
                                        <i data-lucide="align-left" class="w-4 h-4 text-slate-400 group-hover:text-emerald-500 transition-colors"></i>
                                    </div>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Purpose of Visit</span>
                                </div>
                                <p class="text-base font-bold text-slate-800 pl-11">
                                    <?php 
                                        $clean_purpose = preg_replace('/^\[.*?\]\s*/', '', $guest['purpose'] ?? '');
                                        $clean_purpose = preg_replace('/\s*\(Exp:.*?\)$/', '', $clean_purpose);
                                        echo display_or_null($clean_purpose); 
                                    ?>
                                </p>
                            </div>

                            <div class="bg-white p-6 rounded-[1.5rem] border border-slate-200 shadow-sm hover-card relative group">
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shadow-sm border border-slate-100 group-hover:bg-emerald-50 group-hover:border-emerald-100 transition-colors">
                                        <i data-lucide="file-text" class="w-4 h-4 text-slate-400 group-hover:text-emerald-500 transition-colors"></i>
                                    </div>
                                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">ID Document</span>
                                </div>
                                <div class="pl-11 mt-2">
                                    <?php 
                                    $doc_found = null;
                                    $doc_pattern = __DIR__ . '/assets/uploads/guests/guest_doc_' . $guest_id . '.*';
                                    $files = glob($doc_pattern);
                                    if ($files && count($files) > 0) {
                                        $doc_found = 'assets/uploads/guests/' . basename($files[0]);
                                    }
                                    
                                    if ($doc_found): ?>
                                        <a href="<?php echo BASE_URL . htmlspecialchars($doc_found); ?>" target="_blank"
                                            class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-50 text-emerald-700 font-black text-[10px] uppercase tracking-widest rounded-xl hover:bg-emerald-100 transition-colors border border-emerald-200 shadow-sm">
                                            View Document <i data-lucide="external-link" class="w-3 h-3"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-base font-bold text-slate-400">Null</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 pt-6 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-end gap-4">
                        <?php if ($is_admin): ?>
                            <?php if ($guest['resident_id'] == $user_id): ?>
                                <a href="<?php echo BASE_URL; ?>resident/edit_guest.php?id=<?php echo htmlspecialchars($guest['id']); ?>"
                                    class="w-full sm:w-auto flex items-center justify-center gap-2 px-8 py-3.5 bg-slate-50 border border-slate-200 text-slate-700 font-black text-sm rounded-xl hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 transition-all shadow-sm">
                                    <i data-lucide="edit" class="w-4 h-4"></i> Edit Guest
                                </a>
                                <a href="<?php echo BASE_URL; ?>api/gate.php?action=archive_pass&visit_id=<?php echo htmlspecialchars($guest['visit_id']); ?>"
                                    onclick="return confirm('Are you sure you want to completely delete your guest from the database?');"
                                    class="w-full sm:w-auto flex items-center justify-center gap-2 px-8 py-3.5 bg-white text-rose-500 border border-rose-200 font-black text-sm rounded-xl hover:bg-rose-50 hover:text-rose-600 hover:border-rose-300 transition-all shadow-sm group">
                                    <i data-lucide="trash-2" class="w-4 h-4 group-hover:scale-110 transition-transform"></i> Delete Guest Data
                                </a>
                            <?php else: ?>
                                <?php if (isset($entry_id) && $entry_id > 0): ?>
                                <a href="<?php echo BASE_URL; ?>owner/delete_guest_entry.php?entry_id=<?php echo $entry_id; ?>"
                                    onclick="return confirm('Delete this scan entry to clear it from your dashboard? The guest data will remain for the resident.');"
                                    class="w-full sm:w-auto flex items-center justify-center gap-2 px-8 py-3.5 bg-white text-orange-500 border border-orange-200 font-black text-sm rounded-xl hover:bg-orange-50 hover:text-orange-600 hover:border-orange-300 transition-all shadow-sm group">
                                    <i data-lucide="minus-circle" class="w-4 h-4 group-hover:scale-110 transition-transform"></i> Remove Scan Entry
                                </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?php echo BASE_URL; ?>resident/edit_guest.php?id=<?php echo htmlspecialchars($guest['id']); ?>"
                                class="w-full sm:w-auto flex items-center justify-center gap-2 px-8 py-3.5 bg-slate-50 border border-slate-200 text-slate-700 font-black text-sm rounded-xl hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 transition-all shadow-sm">
                                <i data-lucide="edit" class="w-4 h-4"></i> Edit Guest
                            </a>
                            <a href="<?php echo BASE_URL; ?>resident/delete_guest.php?id=<?php echo htmlspecialchars($guest['id']); ?>"
                                onclick="return confirm('Are you sure you want to revoke access for this guest?');"
                                class="w-full sm:w-auto flex items-center justify-center gap-2 px-8 py-3.5 bg-white text-rose-500 border border-rose-200 font-black text-sm rounded-xl hover:bg-rose-50 hover:text-rose-600 hover:border-rose-300 transition-all shadow-sm group">
                                <i data-lucide="user-minus" class="w-4 h-4 group-hover:scale-110 transition-transform"></i> Revoke Access
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <?php if (!$is_admin): ?>
    <script src="<?php echo BASE_URL; ?>js/resident_logic.js"></script>
    <?php endif; ?>
    <script>
        lucide.createIcons();
    </script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include 'chatbot/chat_widget.php'; ?>
</body>
</html>