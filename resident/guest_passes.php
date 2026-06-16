<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] == 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$resident_id = (int) $_SESSION['user_id'];
$user_name = "Resident";
$user_image = "";

// Fetch user profile for the navbar
try {
    $q = "SELECT full_name, profile_image FROM user_profiles WHERE user_id = '$resident_id'";
    $res = mysqli_query($conn, $q);
    if ($res && mysqli_num_rows($res) > 0) {
        $p = mysqli_fetch_assoc($res);
        $user_name = $p['full_name'];
        $user_image = $p['profile_image'];
    }
} catch (Exception $e) {}

// Fetch recent guests (excluding archived ones)
$recent_guests = [];
$recent_stmt = mysqli_prepare($conn, "SELECT DISTINCT g.id, g.full_name as guest_name, g.phone_number as guest_phone 
                                       FROM guests g 
                                       JOIN visit_requests vr ON g.id = vr.guest_id 
                                       WHERE vr.resident_id = ? AND vr.status = 'Approved' 
                                       ORDER BY vr.id DESC LIMIT 10");
if ($recent_stmt) {
    mysqli_stmt_bind_param($recent_stmt, 'i', $resident_id);
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
    <title>Guest Access Hub | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <style>
        .hover-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -8px rgba(20, 184, 166, 0.15); border-color: #5eead4; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f0fdfa; }
        ::-webkit-scrollbar-thumb { background: #99f6e4; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #2dd4bf; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true, searchQuery: '' }">

    <?php include '../includes/resident_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8">
        
        <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-teal-100/60 hover:shadow-[0_4px_15px_-3px_rgba(20,184,166,0.15)] hover:border-teal-200 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>

        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(20,184,166,0.1)] flex-1 flex flex-col overflow-hidden border border-teal-100/80 relative">
            
            <header class="bg-white/80 backdrop-blur-xl border-b border-teal-50 sticky top-0 z-40 shadow-sm">
                <div class="px-8 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-teal-50 hover:text-teal-600 rounded-xl transition-colors">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-teal-500 shadow-[0_0_8px_rgba(20,184,166,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Guest Terminal</span>
                        </h2>
                    </div>

                    <div class="flex items-center gap-4 w-full sm:w-auto">
                        <div class="relative group w-full sm:w-64">
                            <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2 group-focus-within:text-teal-500 transition-colors"></i>
                            <input type="text" x-model="searchQuery" 
                                   class="pl-11 pr-5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 w-full transition-all outline-none font-bold text-slate-800 shadow-sm" 
                                   placeholder="Find guest by name...">
                        </div>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50 space-y-10">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 border-b border-slate-200">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            Security-First Access
                        </h1>
                        <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base max-w-lg">
                            <span class="p-1.5 bg-teal-100 border border-teal-200 rounded-lg shrink-0"><i data-lucide="scan-face" class="w-4 h-4 text-teal-700"></i></span>
                            Manage visitors' biometric registration. 
                        </p>
                    </div>
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                        <a href="<?php echo BASE_URL; ?>post_guest.php" class="w-full sm:w-auto px-8 py-3 bg-teal-600 hover:bg-teal-700 text-white font-black text-sm rounded-xl transition-all shadow-md flex items-center justify-center gap-2 group">
                            <i data-lucide="plus" class="w-4 h-4 group-hover:rotate-90 transition-transform"></i> New Pass
                        </a>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="flex items-center justify-between mb-8">
                        <h2 class="text-xl font-black text-slate-900 flex items-center gap-3">
                            <span class="p-2 bg-teal-50 border border-teal-100 text-teal-600 rounded-xl shadow-inner"><i data-lucide="users" class="w-5 h-5"></i></span>
                            Active Guest Passes
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php if (empty($recent_guests)): ?>
                            <div class="col-span-full py-20 text-center flex flex-col items-center gap-4 bg-white rounded-[2rem] border-2 border-dashed border-slate-200 shadow-sm">
                                <div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-2xl flex items-center justify-center shadow-sm text-slate-300">
                                    <i data-lucide="user-plus" class="w-8 h-8"></i>
                                </div>
                                <div>
                                    <p class="text-slate-900 font-black text-lg">No active passes found</p>
                                    <p class="text-slate-500 font-medium text-sm mt-1">Create a pass to enable biometric gate entry for your guests.</p>
                                </div>
                                <a href="<?php echo BASE_URL; ?>post_guest.php" class="mt-4 px-8 py-3 bg-slate-900 text-white rounded-xl font-black text-sm hover:bg-teal-600 transition-colors shadow-md">
                                    Create First Pass
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_guests as $guest): 
                                $gid = $guest['id'];
                                $photo_path = 'assets/uploads/guests/guest_photo_' . $gid . '.jpg';
                                $full_photo_path = '../' . $photo_path;
                                $has_photo = file_exists($full_photo_path);
                            ?>
                                <div class="hover-card bg-white border border-slate-200 p-6 rounded-[2rem] flex flex-col sm:flex-row items-center gap-6 relative overflow-hidden shadow-sm"
                                     x-show="searchQuery === '' || '<?php echo strtolower(addslashes($guest['guest_name'])); ?>'.includes(searchQuery.toLowerCase())">
                                    
                                    <div class="absolute top-0 left-0 w-1 h-full bg-teal-400"></div>

                                    <div class="relative shrink-0">
                                        <div class="w-20 h-20 rounded-full overflow-hidden bg-slate-50 border-4 border-slate-100 shadow-sm flex items-center justify-center">
                                            <?php if ($has_photo): ?>
                                                <img src="<?php echo BASE_URL . $photo_path . '?v=' . time(); ?>" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <i data-lucide="user" class="w-8 h-8 text-slate-300"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="absolute bottom-0 right-0 w-6 h-6 bg-teal-500 border-2 border-white rounded-full flex items-center justify-center shadow-sm" title="Biometric Access Active">
                                            <i data-lucide="check" class="w-3 h-3 text-white stroke-[3px]"></i>
                                        </div>
                                    </div>

                                    <div class="flex-1 min-w-0 text-center sm:text-left">
                                        <h4 class="text-lg font-black text-slate-900 truncate mb-1"><?php echo htmlspecialchars($guest['guest_name']); ?></h4>
                                        <span class="inline-flex items-center justify-center gap-1 text-[9px] font-black uppercase tracking-widest px-2 py-0.5 bg-teal-50 text-teal-700 rounded-md border border-teal-100 mb-3 w-max mx-auto sm:mx-0">
                                            <i data-lucide="scan-face" class="w-3 h-3"></i> Biometric Active
                                        </span>
                                        <p class="text-xs font-bold text-slate-500 flex items-center justify-center sm:justify-start gap-1.5 truncate">
                                            <span class="bg-slate-50 border border-slate-200 px-2 py-1 rounded-md flex items-center gap-1.5 shadow-sm">
                                                <i data-lucide="phone" class="w-3 h-3 text-teal-500"></i>
                                                <?php echo htmlspecialchars($guest['guest_phone']); ?>
                                            </span>
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2 w-full sm:w-auto pt-4 sm:pt-0 border-t border-slate-100 sm:border-t-0 mt-4 sm:mt-0 justify-center sm:justify-end">
                                        <a href="<?php echo BASE_URL; ?>guest_details.php?id=<?php echo $guest['id']; ?>" 
                                           class="w-10 h-10 flex items-center justify-center bg-white text-slate-400 font-bold text-xs rounded-xl hover:bg-teal-50 hover:text-teal-600 transition-all shadow-sm border border-slate-200 hover:border-teal-200"
                                           title="View Details">
                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                        </a>
                                        <button onclick="confirmDelete(<?php echo $guest['id']; ?>)" 
                                                class="w-10 h-10 flex items-center justify-center bg-white text-rose-400 font-bold text-xs rounded-xl hover:bg-rose-50 hover:text-rose-600 transition-all shadow-sm border border-slate-200 hover:border-rose-200"
                                                title="Revoke Access">
                                            <i data-lucide="shield-off" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        function confirmDelete(guestId) {
            if (confirm("Are you sure you want to revoke access for this guest? Their past entry logs will be preserved, but they will no longer be recognized by the biometric gate.")) {
                window.location.href = "delete_guest.php?id=" + guestId;
            }
        }
    </script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>