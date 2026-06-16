<?php
session_start();
require_once '../includes/db_config.php';
mysqli_report(MYSQLI_REPORT_ERROR);

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = "Admin";
try {
    $query = "SELECT full_name FROM user_profiles WHERE user_id = '$user_id'";
    $result = @mysqli_query($conn, $query);
    if($result && mysqli_num_rows($result) > 0) {
        $user_profile = mysqli_fetch_assoc($result);
        $user_name = $user_profile['full_name'];
    }
} catch (Exception $e) {}

// ── Building Isolation Context ───────────────────────────────────────────
$building_id = '';
$building_q = mysqli_query($conn, "SELECT a.building_id 
                                   FROM apartment_assignments aa 
                                   JOIN apartments a ON a.id = aa.apt_id 
                                   WHERE aa.user_id = '$user_id' AND aa.is_active = 1 
                                   LIMIT 1");

if ($building_q && mysqli_num_rows($building_q) > 0) {
    $building_id = mysqli_fetch_assoc($building_q)['building_id'];
}
$safe_building_id = mysqli_real_escape_string($conn, $building_id);

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Visit_History_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Guest Name', 'Guest Phone', 'Purpose', 'Destination Unit', 'Host Name', 'Entry Time', 'Verification Score']);
    
    if (!empty($building_id)) {
        $export_query = "SELECT g.full_name as guest_name, g.phone_number as guest_phone,
                    vr.purpose, a.apt_number, p.full_name as host_name,
                    e.entry_time, e.verification_score
                    FROM entry_logs e
                    JOIN visit_requests vr ON e.visit_id = vr.id
                    JOIN guests g ON vr.guest_id = g.id
                    JOIN apartments a ON vr.apt_id = a.id
                    JOIN users u ON vr.resident_id = u.id
                    LEFT JOIN user_profiles p ON u.id = p.user_id
                    WHERE a.building_id = '$safe_building_id'
                    ORDER BY e.entry_time DESC";
        $export_res = mysqli_query($conn, $export_query);
        if ($export_res) {
            while ($row = mysqli_fetch_assoc($export_res)) {
                $score = round(($row['verification_score'] ?? 0) * 100) . '%';
                fputcsv($output, [
                    $row['guest_name'],
                    $row['guest_phone'],
                    $row['purpose'],
                    $row['apt_number'],
                    $row['host_name'],
                    $row['entry_time'],
                    $score
                ]);
            }
        }
    }
    fclose($output);
    exit();
}

// Stats fetching (Filtered by building)
$today_entries = 0;
if (!empty($building_id)) {
    $today_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM entry_logs e JOIN visit_requests vr ON e.visit_id = vr.id JOIN apartments a ON vr.apt_id = a.id WHERE DATE(e.entry_time) = CURDATE() AND a.building_id = '$safe_building_id'");
    if($today_res) $today_entries = mysqli_fetch_assoc($today_res)['count'];
}

$avg_score = 0;
if (!empty($building_id)) {
    $score_res = mysqli_query($conn, "SELECT AVG(e.verification_score) as avg_score FROM entry_logs e JOIN visit_requests vr ON e.visit_id = vr.id JOIN apartments a ON vr.apt_id = a.id WHERE a.building_id = '$safe_building_id'");
    if($score_res) {
        $row = mysqli_fetch_assoc($score_res);
        $avg_score = round(($row['avg_score'] ?? 0) * 100);
    }
}

// Fetch Owner's Own Guests
$recent_guests = [];
$recent_stmt = mysqli_prepare($conn, "SELECT g.*, vr.id as visit_id, vr.status as visit_status, g.created_at as visit_created
                                       FROM guests g 
                                       JOIN visit_requests vr ON g.id = vr.guest_id 
                                       WHERE vr.resident_id = ? AND vr.status = 'Approved' 
                                       ORDER BY vr.id DESC LIMIT 10");
if ($recent_stmt) {
    mysqli_stmt_bind_param($recent_stmt, 'i', $user_id);
    mysqli_stmt_execute($recent_stmt);
    $result = mysqli_stmt_get_result($recent_stmt);
    if ($result) {
        $recent_guests = mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
    mysqli_stmt_close($recent_stmt);
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
    <title>Guest Management | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .hover-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -8px rgba(16, 185, 129, 0.15); border-color: #6ee7b7; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #ecfdf5; }
        ::-webkit-scrollbar-thumb { background: #a7f3d0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6ee7b7; }
        .entry-row { transition: all 0.2s ease; }
        .entry-row:hover { background-color: rgba(248, 250, 252, 0.8); }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php 
    $active_page = 'guest_entries.php'; // Updated to match sidebar logic
    include '../includes/owner_sidebar.php'; 
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
                <div class="px-8 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Security & Access</span>
                        </h2>
                    </div>

                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <div class="relative group w-full sm:w-64">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2 group-focus-within:text-emerald-500 transition-colors"></i>
                            <input type="text" id="searchInput" onkeyup="filterTables()" 
                                   class="pl-11 pr-5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 w-full transition-all outline-none font-medium" 
                                   placeholder="Search entries...">
                        </div>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50 space-y-10">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 border-b border-slate-200">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            Guest Entry Logs
                        </h1>
                        <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base">
                            <span class="p-1.5 bg-emerald-100 border border-emerald-200 rounded-lg"><i data-lucide="scan-face" class="w-4 h-4 text-emerald-700"></i></span>
                            Real-time monitoring of all building entries and active passes.
                        </p>
                    </div>
                    <a href="<?= BASE_URL ?>post_guest.php" class="w-full sm:w-auto px-6 py-3 bg-slate-900 hover:bg-emerald-600 text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-emerald-500/40 flex items-center justify-center gap-2 group">
                        <i data-lucide="plus" class="w-4 h-4 group-hover:scale-110 transition-transform"></i> Add Guest Pass
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-[1.5rem] shadow-sm border border-slate-200 flex items-center gap-5 relative overflow-hidden group">
                        <div class="absolute top-0 left-0 w-1 h-full bg-blue-400"></div>
                        <div class="w-14 h-14 bg-blue-50 border border-blue-100 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                            <i data-lucide="door-open" class="w-6 h-6 text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">Today's Entries</p>
                            <h3 class="text-3xl font-black text-slate-900 tracking-tight"><?= $today_entries ?></h3>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-[1.5rem] shadow-sm border border-slate-200 flex items-center gap-5 relative overflow-hidden group">
                        <div class="absolute top-0 left-0 w-1 h-full bg-emerald-400"></div>
                        <div class="w-14 h-14 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                            <i data-lucide="cpu" class="w-6 h-6 text-emerald-600"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">Biometric Accuracy</p>
                            <h3 class="text-3xl font-black text-slate-900 tracking-tight"><?= $avg_score ?>%</h3>
                        </div>
                    </div>

                    <?php
                    $unknown_log_file = $_SERVER['DOCUMENT_ROOT'] . '/Nibash/assets/uploads/scans/unknown/log.json';
                    $unknown_count = 0;
                    if (file_exists($unknown_log_file)) {
                        $decoded = json_decode(file_get_contents($unknown_log_file), true);
                        if (is_array($decoded)) $unknown_count = count($decoded);
                    }
                    ?>
                    <div class="bg-white p-6 rounded-[1.5rem] shadow-sm border border-slate-200 flex items-center gap-5 relative overflow-hidden group">
                        <div class="absolute top-0 left-0 w-1 h-full bg-rose-400"></div>
                        <div class="w-14 h-14 bg-rose-50 border border-rose-100 rounded-2xl flex items-center justify-center group-hover:scale-110 transition-transform shadow-sm">
                            <i data-lucide="alert-octagon" class="w-6 h-6 text-rose-600"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest mb-1">Suspicious Alerts</p>
                            <h3 class="text-3xl font-black text-slate-900 tracking-tight"><?= $unknown_count ?></h3>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="flex items-center justify-between px-2">
                        <h3 class="text-xl font-black text-slate-900 flex items-center gap-3">
                            <span class="p-2 bg-indigo-50 border border-indigo-100 text-indigo-600 rounded-xl shadow-inner"><i data-lucide="users" class="w-5 h-5"></i></span>
                            My Registered Guests
                        </h3>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php if (empty($recent_guests)): ?>
                            <div class="col-span-full bg-white rounded-[1.5rem] border-2 border-dashed border-slate-300 p-12 text-center shadow-sm">
                                <div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm">
                                    <i data-lucide="user-plus" class="w-8 h-8 text-slate-400"></i>
                                </div>
                                <p class="text-slate-900 font-black text-lg">No Active Passes</p>
                                <p class="text-slate-500 font-medium text-sm mb-6">Create a pass to enable biometric entry for your guests.</p>
                                <a href="<?= BASE_URL ?>post_guest.php" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-50 border border-emerald-100 hover:bg-emerald-600 hover:text-white hover:border-emerald-600 text-emerald-700 font-bold rounded-xl transition-all shadow-sm">
                                    Create First Pass
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_guests as $guest): 
                                $gid = $guest['id'];
                                $photo_path = 'assets/uploads/guests/guest_photo_' . $gid . '.jpg';
                                $full_photo_path = $_SERVER['DOCUMENT_ROOT'] . '/Nibash/' . $photo_path;
                                $has_photo = file_exists($full_photo_path);
                            ?>
                                <div class="hover-card bg-white rounded-[1.5rem] border border-slate-200 shadow-[0_8px_30px_-6px_rgba(0,0,0,0.08)] p-6 flex flex-col sm:flex-row items-center gap-6 relative group overflow-hidden">
                                    <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-400 to-emerald-400"></div>
                                    
                                    <div class="relative shrink-0">
                                        <div class="w-16 h-16 rounded-full overflow-hidden bg-slate-50 border-4 border-slate-100 shadow-sm flex items-center justify-center group-hover:scale-105 transition-transform">
                                            <?php if ($has_photo): ?>
                                                <img src="<?= BASE_URL . $photo_path . '?v=' . time() ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i data-lucide="user" class="w-6 h-6 text-slate-300"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="absolute -bottom-1 -right-1 w-6 h-6 bg-emerald-500 border-2 border-white rounded-full flex items-center justify-center shadow-sm" title="Biometric Active">
                                            <i data-lucide="check" class="w-3 h-3 text-white stroke-[3px]"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="flex-1 text-center sm:text-left min-w-0">
                                        <h4 class="text-base font-black text-slate-900 truncate"><?= htmlspecialchars($guest['full_name']) ?></h4>
                                        <p class="text-xs font-bold text-slate-500 mt-1 flex items-center justify-center sm:justify-start gap-1.5">
                                            <i data-lucide="phone" class="w-3 h-3 text-emerald-500"></i> <?= htmlspecialchars($guest['phone_number']) ?>
                                        </p>
                                    </div>
                                    
                                    <div class="shrink-0 flex items-center gap-2">
                                        <a href="<?= BASE_URL ?>guest_details.php?id=<?= $gid ?>" class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 hover:bg-emerald-50 hover:border-emerald-200 hover:text-emerald-600 rounded-xl text-slate-500 transition-all shadow-sm" title="View Details">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                        <a href="<?= BASE_URL ?>api/gate.php?action=archive_pass&visit_id=<?= $guest['visit_id'] ?>" 
                                           onclick="return confirm('Archive this guest pass? Biometric scan history will be preserved.')"
                                           class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 rounded-xl text-slate-400 transition-all shadow-sm" 
                                           title="Archive Pass">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-[1.5rem] border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-8 py-6 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white">
                        <div>
                            <h3 class="text-xl font-black text-slate-900 flex items-center gap-3">
                                <span class="p-2 bg-emerald-50 border border-emerald-100 text-emerald-600 rounded-xl shadow-inner"><i data-lucide="list-checks" class="w-5 h-5"></i></span>
                                Authenticated Visit History
                            </h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="?export=csv" class="px-4 py-2 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-600 rounded-xl transition-colors font-bold text-sm flex items-center gap-2 shadow-sm" title="Export Log">
                                <i data-lucide="download" class="w-4 h-4"></i> Export
                            </a>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left" id="guestsTable">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-widest text-slate-500">
                                    <th class="py-5 px-8">Verification Identity</th>
                                    <th class="py-5 px-6">Purpose & Details</th>
                                    <th class="py-5 px-6">Destination Unit</th>
                                    <th class="py-5 px-6">Time / Score</th>
                                    <th class="py-5 px-8 text-right">Review</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php
                                if (!empty($building_id)) {
                                    $query = "SELECT g.id, g.full_name as guest_name, g.phone_number as guest_phone,
                                              vr.purpose, vr.status, vr.apt_id,
                                              e.id as entry_id, e.entry_time, e.exit_time, e.entry_method, e.verification_score,
                                              a.apt_number,
                                              u.email as host_email, p.full_name as host_name
                                              FROM entry_logs e
                                              JOIN visit_requests vr ON e.visit_id = vr.id
                                              JOIN guests g ON vr.guest_id = g.id
                                              JOIN apartments a ON vr.apt_id = a.id
                                              JOIN users u ON vr.resident_id = u.id
                                              LEFT JOIN user_profiles p ON u.id = p.user_id
                                              WHERE a.building_id = '$safe_building_id'
                                              ORDER BY e.entry_time DESC";
                                } else {
                                    $query = "SELECT 1 WHERE 1=0";
                                }
                                $res = @mysqli_query($conn, $query);

                                if ($res && mysqli_num_rows($res) > 0) {
                                    while ($row = mysqli_fetch_assoc($res)) {
                                        $entry_time = date('d M, h:i A', strtotime($row['entry_time']));
                                        $score = round(($row['verification_score'] ?? 0) * 100);
                                        $status = strtoupper($row['status'] ?? 'APPROVED');
                                        $guest_id = (int)$row['id'];
                                        $entry_id = (int)$row['entry_id'];

                                        $guest_photo_url = BASE_URL . 'assets/uploads/guests/guest_photo_' . $guest_id . '.jpg';
                                        $scan_url = BASE_URL . 'assets/uploads/scans/known/entry_' . $entry_id . '.jpg';
                                        
                                        // Check files existence (internal path)
                                        $gp_exists = file_exists($_SERVER['DOCUMENT_ROOT'] . '/Nibash/assets/uploads/guests/guest_photo_' . $guest_id . '.jpg');
                                        $sp_exists = file_exists($_SERVER['DOCUMENT_ROOT'] . '/Nibash/assets/uploads/scans/known/entry_' . $entry_id . '.jpg');

                                        ?>
                                        <tr class="entry-row group bg-white">
                                            <td class="py-5 px-8">
                                                <div class="flex items-center gap-5">
                                                    <div class="flex items-center -space-x-3 group/photos">
                                                        <div class="relative w-12 h-12 rounded-2xl overflow-hidden border-2 border-white shadow-sm z-20 group-hover/photos:translate-x-[-8px] transition-transform duration-300 bg-slate-50">
                                                            <?php if($gp_exists): ?>
                                                                <img src="<?= $guest_photo_url ?>" class="w-full h-full object-cover">
                                                            <?php else: ?>
                                                                <div class="w-full h-full flex items-center justify-center text-slate-300">
                                                                    <i data-lucide="user" class="w-5 h-5"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <span class="absolute bottom-0 left-0 w-full bg-slate-900/60 text-[7px] text-white font-black text-center py-0.5 uppercase tracking-wider">Reg</span>
                                                        </div>
                                                        <div class="relative w-12 h-12 rounded-2xl overflow-hidden border-2 border-emerald-400 shadow-sm z-10 group-hover/photos:translate-x-[8px] transition-transform duration-300 bg-emerald-50">
                                                            <?php if($sp_exists): ?>
                                                                <img src="<?= $scan_url ?>" class="w-full h-full object-cover">
                                                            <?php else: ?>
                                                                <div class="w-full h-full flex items-center justify-center text-emerald-300">
                                                                    <i data-lucide="camera" class="w-5 h-5"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <span class="absolute bottom-0 left-0 w-full bg-emerald-600/80 text-[7px] text-white font-black text-center py-0.5 uppercase tracking-wider">Gate</span>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <p class="font-black text-slate-900 text-sm"><?= htmlspecialchars($row['guest_name'] ?? 'Deleted Guest') ?></p>
                                                        <p class="text-[10px] font-bold text-slate-400 mt-1 flex items-center gap-1.5 uppercase tracking-widest bg-slate-50 px-2 py-0.5 rounded border border-slate-100 w-fit">
                                                            <i data-lucide="hash" class="w-3 h-3"></i> G-<?= str_pad($guest_id, 4, '0', STR_PAD_LEFT) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-5 px-6">
                                                <p class="text-sm font-bold text-slate-800"><?= htmlspecialchars($row['guest_phone'] ?? 'N/A') ?></p>
                                                <p class="text-xs font-medium text-slate-500 mt-1 flex items-center gap-1.5 italic">
                                                    <i data-lucide="text-quote" class="w-3 h-3 text-emerald-500"></i>
                                                    <?= htmlspecialchars($row['purpose'] ?? 'N/A') ?>
                                                </p>
                                            </td>
                                            <td class="py-5 px-6">
                                                <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl shadow-sm">
                                                    <span class="text-xs font-black text-slate-900">Unit <?= htmlspecialchars($row['apt_number'] ?? 'N/A') ?></span>
                                                </div>
                                                <p class="text-[10px] font-bold text-slate-400 mt-2 uppercase tracking-widest">Host: <?= htmlspecialchars($row['host_name'] ?? 'N/A') ?></p>
                                            </td>
                                            <td class="py-5 px-6">
                                                <p class="text-sm font-black text-slate-900"><?= $entry_time ?></p>
                                                <div class="flex items-center gap-2 mt-2">
                                                    <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden shadow-inner">
                                                        <div class="h-full bg-emerald-500" style="width: <?= $score ?>%"></div>
                                                    </div>
                                                    <span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100"><?= $score ?>%</span>
                                                </div>
                                            </td>
                                            <td class="py-5 px-8 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <a href="<?= BASE_URL ?>guest_details.php?id=<?= $guest_id ?>" 
                                                       class="w-8 h-8 flex items-center justify-center bg-white text-slate-400 hover:bg-emerald-50 hover:border-emerald-200 hover:text-emerald-600 rounded-lg transition-all border border-slate-200 shadow-sm" title="View Details">
                                                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                                    </a>
                                                    <a href="<?= BASE_URL ?>owner/delete_guest_entry.php?entry_id=<?= $entry_id ?>" 
                                                       onclick="return confirm('Delete this log entry permanently?')"
                                                       class="w-8 h-8 flex items-center justify-center bg-white text-rose-400 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 rounded-lg transition-all border border-slate-200 shadow-sm" title="Delete Log">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    ?>
                                    <tr>
                                        <td colspan="5" class="py-20 text-center bg-white">
                                            <div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm text-slate-300">
                                                <i data-lucide="inbox" class="w-8 h-8"></i>
                                            </div>
                                            <h4 class="text-slate-900 font-black text-lg">No Validation Entries Found</h4>
                                            <p class="text-slate-500 text-sm mt-1 font-medium">Logs will appear as guests validate their biometric passes at the gate.</p>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between px-2 gap-4">
                        <div>
                            <h3 class="text-xl font-black text-slate-900 flex items-center gap-3">
                                <span class="p-2 bg-rose-50 border border-rose-100 text-rose-600 rounded-xl shadow-inner"><i data-lucide="alert-triangle" class="w-5 h-5"></i></span>
                                Suspicious Scan Attempts
                            </h3>
                            <p class="text-sm font-medium text-slate-500 mt-1">Faces detected at the gate with no biometric matching.</p>
                        </div>
                        <div class="relative group w-full sm:w-80">
                            <i data-lucide="clock" class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2 group-focus-within:text-rose-500 transition-colors"></i>
                            <input type="text" id="suspiciousSearch" onkeyup="filterSuspiciousScans()" 
                                   class="pl-11 pr-5 py-2.5 bg-white border border-slate-200 rounded-xl text-sm focus:ring-4 focus:ring-rose-500/10 focus:border-rose-400 w-full transition-all outline-none font-medium shadow-sm" 
                                   placeholder="Filter by time (e.g. 09:09 PM)...">
                        </div>
                    </div>

                    <?php
                    $unknown_scans = [];
                    if (file_exists($unknown_log_file)) {
                        $decoded = json_decode(file_get_contents($unknown_log_file), true);
                        if (is_array($decoded)) $unknown_scans = array_reverse($decoded);
                    }

                    if (empty($unknown_scans)): ?>
                        <div class="bg-white rounded-[1.5rem] border-2 border-dashed border-slate-200 p-16 text-center shadow-sm">
                            <div class="w-16 h-16 bg-emerald-50 border border-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-sm">
                                <i data-lucide="shield-check" class="w-8 h-8 text-emerald-500"></i>
                            </div>
                            <p class="text-slate-900 font-black text-lg">Secure Zone</p>
                            <p class="text-slate-500 text-sm font-medium mt-1">No unidentified faces detected recently.</p>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6" id="suspiciousGrid">
                            <?php foreach ($unknown_scans as $scan):
                                $scan_img_url = BASE_URL . 'assets/uploads/scans/unknown/' . htmlspecialchars($scan['filename']);
                                $scan_time    = date('d M Y, h:i A', $scan['timestamp'] + (4 * 3600));
                            ?>
                            <div class="suspicious-card hover-card bg-white border border-slate-200 shadow-sm rounded-[1.5rem] p-5 flex items-center justify-between group relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-1 h-full bg-rose-400"></div>
                                <div class="flex items-center gap-5">
                                    <div class="relative w-16 h-16 sm:w-20 sm:h-20 rounded-2xl overflow-hidden border-2 border-slate-100 shadow-sm shrink-0 bg-slate-50 group-hover:scale-105 transition-transform duration-300">
                                        <img src="<?= $scan_img_url ?>" class="w-full h-full object-cover">
                                    </div>
                                    
                                    <div>
                                        <h4 class="text-sm sm:text-base font-black text-slate-900">
                                            Unidentified Face
                                        </h4>
                                        <p class="text-xs font-bold text-slate-500 mt-1 flex items-center gap-1.5 uppercase tracking-widest scan-time-text bg-slate-50 px-2 py-0.5 rounded border border-slate-100 w-fit">
                                            <i data-lucide="clock" class="w-3 h-3 text-slate-400"></i>
                                            <?= $scan_time ?>
                                        </p>
                                        <div class="hidden sm:inline-flex mt-2 items-center gap-1.5 px-2.5 py-1 bg-rose-50 border border-rose-100 rounded-lg shadow-sm">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                                            <span class="text-[9px] font-black text-rose-600 uppercase tracking-widest">Alert</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-3">
                                    <a href="<?= BASE_URL ?>api/gate.php?action=delete_unknown&filename=<?= urlencode($scan['filename']) ?>" 
                                       onclick="return confirm('Delete this security log permanently?')"
                                       class="w-10 h-10 flex items-center justify-center bg-white text-slate-400 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-600 rounded-xl transition-all border border-slate-200 shadow-sm" 
                                       title="Remove Evidence">
                                        <i data-lucide="trash-2" class="w-4.5 h-4.5"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();
        
        function filterSuspiciousScans() {
            let input = document.getElementById('suspiciousSearch');
            let filter = input.value.toUpperCase();
            let grid = document.getElementById('suspiciousGrid');
            if(!grid) return;
            
            let cards = grid.getElementsByClassName('suspicious-card');

            for (let i = 0; i < cards.length; i++) {
                let timeText = cards[i].querySelector('.scan-time-text');
                if (timeText) {
                    let txtValue = timeText.textContent || timeText.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        cards[i].style.display = "";
                    } else {
                        cards[i].style.display = "none";
                    }
                }
            }
        }

        function filterTables() {
            let input = document.getElementById('searchInput');
            let filter = input.value.toUpperCase();
            let table = document.getElementById('guestsTable');
            let tr = table.getElementsByTagName('tr');

            for (let i = 1; i < tr.length; i++) {
                let found = false;
                let tds = tr[i].getElementsByTagName('td');
                for(let j=0; j<tds.length; j++) {
                    if (tds[j]) {
                        let txtValue = tds[j].textContent || tds[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = found ? "" : "none";
            }
        }
    </script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>