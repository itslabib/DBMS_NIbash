<?php
session_start();
require_once '../includes/db_config.php';

// Disable strict exception mode so @-suppressed queries return false gracefully
mysqli_report(MYSQLI_REPORT_ERROR);

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = "Owner";
$recent_notices = [];
try {
    $query = "SELECT full_name, profile_image FROM user_profiles WHERE user_id = '$user_id'";
    $result = @mysqli_query($conn, $query);
    if($result && mysqli_num_rows($result) > 0) {
        $user_profile = mysqli_fetch_assoc($result);
        $user_name = $user_profile['full_name'];
        $user_profile_img = $user_profile['profile_image'];
    }

    $building_id = '';
    $bq = @mysqli_query($conn, "SELECT a.building_id FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id WHERE aa.user_id = '$user_id' AND aa.is_active=1 LIMIT 1");
    if ($bq && mysqli_num_rows($bq) > 0) {
        $building_id = mysqli_fetch_assoc($bq)['building_id'];
    } else {
        $mq = @mysqli_query($conn, "SELECT building_id FROM building_managers WHERE user_id = '$user_id' LIMIT 1");
        if ($mq && mysqli_num_rows($mq) > 0) {
            $building_id = mysqli_fetch_assoc($mq)['building_id'];
        } else {
            $fb = @mysqli_query($conn, "SELECT building_id FROM apartments LIMIT 1");
            if ($fb && mysqli_num_rows($fb) > 0) {
                $building_id = mysqli_fetch_assoc($fb)['building_id'];
            }
        }
    }

    $building_filter = "";
    if (!empty($building_id)) {
        $safe_building_id = mysqli_real_escape_string($conn, $building_id);
        $building_filter = " AND a.building_id = '$safe_building_id'";
    }

    // Fetch recent community posts as notices with actual replies count
    $notices_result = @mysqli_query($conn, "
        SELECT cp.id, cp.title, cp.content, cp.created_at, up.full_name as author_name,
               (SELECT COUNT(*) FROM community_comments cc WHERE cc.post_id = cp.id) as reply_count
        FROM community_posts cp
        JOIN apartments a ON cp.apt_id = a.id
        LEFT JOIN user_profiles up ON cp.user_id = up.user_id
        WHERE (cp.status = 'published' OR cp.status IS NULL)$building_filter
        ORDER BY cp.created_at DESC 
        LIMIT 5
    ");
    if ($notices_result) {
        while ($row = mysqli_fetch_assoc($notices_result)) {
            $recent_notices[] = $row;
        }
    }
} catch (Exception $e) {
    // Keep fallback demo user_name
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
    <title>Owner Dashboard | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .metric-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .metric-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px -4px rgba(16, 185, 129, 0.15); 
            border-color: #6ee7b7; 
        }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
<body class="bg-slate-100 text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php 
    $active_page = 'dashboard';
    include '../includes/owner_sidebar.php'; 
    ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8" id="main-content">

        <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-slate-200/60 hover:shadow-[0_4px_15px_-3px_rgba(16,185,129,0.15)] hover:border-emerald-200 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>
        
        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(0,0,0,0.1)] flex-1 flex flex-col overflow-hidden border border-slate-200/80 relative">

            <header class="bg-white/80 backdrop-blur-xl border-b border-slate-200/60 sticky top-0 z-40 shadow-sm">   
            <div class="px-8 py-4 flex items-center justify-between">   
                
                <div class="flex items-center gap-4">
                    <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                        <span class="flex h-6 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                        <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Control Center</span>
                    </h2>
                </div>

                <div class="flex items-center gap-5">
                    
                    <button onclick="if(typeof toggleDarkMode === 'function') { toggleDarkMode(); }" class="relative flex items-center justify-center w-9 h-9 bg-slate-50 text-slate-500 hover:text-emerald-600 rounded-xl border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50 transition-all shadow-sm font-bold text-sm group" title="Toggle Theme">
                        <i data-lucide="moon" class="w-4 h-4 group-hover:scale-110 transition-transform dark:hidden"></i>
                        <i data-lucide="sun" class="w-4 h-4 group-hover:scale-110 transition-transform hidden dark:block text-amber-500"></i>
                    </button>



                    <div class="relative flex items-center" x-data="notificationDropdown()" x-init="init()">
                        <button @click="toggle()" @click.away="open = false" class="relative flex items-center justify-center w-10 h-10 bg-slate-50 text-slate-400 hover:text-emerald-600 rounded-full border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50 transition-all shadow-sm group">
                            <i data-lucide="bell" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                            <template x-if="unreadCount > 0">
                                <span class="absolute top-0 right-0 flex h-3 w-3 -mt-0.5 -mr-0.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500 ring-2 ring-white"></span>
                                </span>
                            </template>
                        </button>

                        <div x-show="open" x-transition.opacity.duration.200ms class="absolute right-0 top-full mt-3 w-80 bg-white rounded-[1.5rem] shadow-xl border border-slate-200 overflow-hidden z-50 text-left" style="display: none;">
                            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                                <h3 class="font-bold text-slate-900">Notifications</h3>
                                <span class="text-xs bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full font-bold" x-text="unreadCount + ' New'"></span>
                            </div>
                            <div class="max-h-[300px] overflow-y-auto">
                                <template x-if="notifications.length > 0">
                                    <template x-for="note in notifications" :key="note.id">
                                        <a :href="'<?php echo BASE_URL; ?>' + note.link" @click="markRead(note.id)" class="block p-5 border-b border-slate-50 hover:bg-emerald-50/50 transition-colors cursor-pointer group" :class="!note.is_read ? 'bg-emerald-50/40' : ''">
                                            <h4 class="text-sm font-bold text-slate-800 mb-1.5 group-hover:text-emerald-600 transition-colors" x-text="note.title"></h4>
                                            <p class="text-xs text-slate-500 leading-relaxed line-clamp-2" x-text="note.message"></p>
                                            <span class="text-[11px] font-medium text-slate-400 mt-3 flex items-center gap-1.5">
                                                <i data-lucide="clock" class="w-3.5 h-3.5"></i> <span x-text="note.created_at"></span>
                                            </span>
                                        </a>
                                    </template>
                                </template>
                                <template x-if="notifications.length === 0">
                                    <div class="p-8 text-center text-slate-400 text-sm font-medium">
                                        No recent notifications.
                                    </div>
                                </template>
                            </div>
                            <div class="p-3 border-t border-slate-100 text-center bg-slate-50/50">
                                <a href="<?php echo BASE_URL; ?>notifications_history.php" class="text-xs font-bold text-emerald-600 hover:text-emerald-700 transition-colors">View All Notifications</a>
                            </div>
                        </div>
                    </div>

                    <a href="<?php echo BASE_URL; ?>owner/profile.php" class="flex items-center gap-3 p-1.5 pr-5 rounded-full border border-slate-200 bg-white hover:border-emerald-300 hover:shadow-md transition-all group">
                        <?php if (!empty($user_profile_img)): ?>
                            <?php
                            $dash_img_clean = ltrim($user_profile_img, '/');
                            if (strpos($dash_img_clean, 'assets/uploads/profiles/') !== 0) {
                                $dash_img_clean = 'assets/uploads/profiles/' . $dash_img_clean;
                            }
                            ?>
                            <img src="<?php echo BASE_URL . $dash_img_clean; ?>" alt="Profile" class="w-8 h-8 rounded-full object-cover border border-emerald-100 shadow-inner group-hover:border-emerald-500 transition-colors">
                        <?php else: ?>
                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-extrabold text-xs shadow-inner group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                                <?= strtoupper(substr($user_name, 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <span class="text-sm font-bold text-slate-700 hidden sm:block tracking-tight"><?= htmlspecialchars($user_name) ?></span>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 group-hover:text-emerald-600 transition-colors"></i>
                    </a>
                </div>
            </div>
        </header>

        <div class="p-6 sm:p-8 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full space-y-6 bg-[#FAFAFA]">


            <?php
                // 1. Identify current building context for isolation (already done at the top)

                // 2. Active Profiles (Count everyone assigned to this building: Owner + Residents)
                if (!empty($building_id)) {
                    $tot_res_q = mysqli_query($conn, "SELECT COUNT(DISTINCT u.id) as cnt 
                                                      FROM users u 
                                                      JOIN apartment_assignments aa ON u.id = aa.user_id 
                                                      JOIN apartments a ON aa.apt_id = a.id 
                                                      WHERE a.building_id = '$building_id' AND aa.is_active = 1");
                } else {
                    // Fallback to only counting the owner if no building assignment is found
                    $tot_res_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users WHERE id = '$user_id'");
                }
                $tot_res = ($tot_res_q && $row = mysqli_fetch_assoc($tot_res_q)) ? ($row['cnt'] ?? 0) : 1;

                // 3. Total Rentals (Owned by this owner)
                $tot_rent_q = @mysqli_query($conn, "SELECT COUNT(*) as cnt FROM rental_listings WHERE owner_id='$user_id'");
                $tot_rent = ($tot_rent_q && $row = mysqli_fetch_assoc($tot_rent_q)) ? ($row['cnt'] ?? 0) : 0;

                // 4. Pending Invoices (Isolated by building)
                if (!empty($building_id)) {
                    $tot_pending_q = mysqli_query($conn, "SELECT COUNT(*) as cnt 
                                                          FROM bills b 
                                                          JOIN apartments a ON b.apt_id = a.id 
                                                          WHERE a.building_id = '$building_id' 
                                                          AND (b.status='Pending' OR b.status='Overdue')");
                } else {
                    $tot_pending_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM bills WHERE (status='Pending' OR status='Overdue') AND resident_id = '0'"); // Return 0
                }
                $tot_pending = ($tot_pending_q && $row = mysqli_fetch_assoc($tot_pending_q)) ? ($row['cnt'] ?? 0) : 0;
            ?>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                
                <!-- Left Column (KPIs + Notice Board) (2/3 width) -->
                <div class="lg:col-span-2 space-y-6 lg:space-y-8 flex flex-col">
                    
                    <!-- Top KPI Row (Moved inside Left Column to reduce width) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6">
                        <!-- Total Users -->
                        <div class="bg-white p-5 rounded-[1.5rem] border-l-[6px] border-l-emerald-500 border-y border-r border-slate-200 shadow-md hover:shadow-xl transition-all cursor-pointer group" onclick="window.location.href='<?php echo BASE_URL; ?>owner/residents.php'">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center bg-emerald-50 text-emerald-500 border border-emerald-100 group-hover:scale-110 transition-transform">
                                    <i data-lucide="users" class="w-4 h-4"></i>
                                </div>
                                <i data-lucide="arrow-up-right" class="w-4 h-4 text-slate-300 group-hover:text-emerald-500 transition-colors"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-extrabold text-slate-500 mb-1 uppercase tracking-wider">Total Users</h4>
                                <div class="text-3xl font-black text-slate-900 tracking-tight"><?php echo htmlspecialchars($tot_res); ?></div>
                            </div>
                        </div>

                        <!-- Unpaid Bills -->
                        <div class="bg-white p-5 rounded-[1.5rem] border-l-[6px] border-l-rose-500 border-y border-r border-slate-200 shadow-md hover:shadow-xl transition-all cursor-pointer group" onclick="window.location.href='<?php echo BASE_URL; ?>owner/billing.php'">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center bg-rose-50 text-rose-500 border border-rose-100 group-hover:scale-110 transition-transform">
                                    <i data-lucide="alert-circle" class="w-4 h-4"></i>
                                </div>
                                <i data-lucide="arrow-up-right" class="w-4 h-4 text-slate-300 group-hover:text-rose-500 transition-colors"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-extrabold text-slate-500 mb-1 uppercase tracking-wider">Pending Invoices</h4>
                                <div class="text-3xl font-black text-slate-900 tracking-tight"><?php echo htmlspecialchars($tot_pending); ?></div>
                            </div>
                        </div>

                        <!-- Rentals -->
                        <div class="bg-white p-5 rounded-[1.5rem] border-l-[6px] border-l-amber-500 border-y border-r border-slate-200 shadow-md hover:shadow-xl transition-all cursor-pointer group" onclick="window.location.href='<?php echo BASE_URL; ?>rentals/index.php'">
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center bg-amber-50 text-amber-500 border border-amber-100 group-hover:scale-110 transition-transform">
                                    <i data-lucide="home" class="w-4 h-4"></i>
                                </div>
                                <i data-lucide="arrow-up-right" class="w-4 h-4 text-slate-300 group-hover:text-amber-500 transition-colors"></i>
                            </div>
                            <div>
                                <h4 class="text-xs font-extrabold text-slate-500 mb-1 uppercase tracking-wider">Total Rentals</h4>
                                <div class="text-3xl font-black text-slate-900 tracking-tight"><?php echo htmlspecialchars($tot_rent); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Notice Board -->
                    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-slate-300 overflow-hidden flex-1 flex flex-col min-h-[350px]">
                        <div class="p-8 pb-4">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-xl font-extrabold text-slate-900">Notice Board</h3>
                                <div class="flex items-center gap-3">
                                    <a href="<?php echo BASE_URL; ?>community_hub.php" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition-all shadow-md flex items-center gap-1.5 shrink-0">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i> <span class="hidden sm:inline">New Notice</span>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>community_hub.php" class="p-2 hover:bg-slate-50 rounded-xl transition-colors cursor-pointer group border border-transparent hover:border-slate-200" title="View All">
                                        <i data-lucide="arrow-up-right" class="w-5 h-5 text-slate-400 group-hover:text-slate-700"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-3 mb-6">
                                <div class="w-full relative flex items-center gap-3 mt-2 sm:mt-0">
                                    <div class="relative w-full">
                                        <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                        <input type="text" placeholder="Search notices..." class="pl-9 pr-4 py-2 bg-slate-50/50 border border-slate-200 rounded-xl text-[13px] font-medium text-slate-700 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 focus:bg-white transition-all w-full placeholder:text-slate-400 shadow-sm">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-y-auto flex-1 px-8 pb-8">
                            <div class="flex flex-col gap-3">
                                <?php if (!empty($recent_notices)): ?>
                                    <?php foreach ($recent_notices as $notice): ?>
                                        <div class="bg-white border border-slate-200 rounded-[1rem] p-4 hover:border-emerald-400 hover:shadow-md transition-all cursor-pointer group flex flex-col sm:flex-row sm:items-center justify-between gap-4" onclick="window.location.href='<?php echo BASE_URL; ?>community_hub.php'">
                                            <div class="flex items-center gap-4 flex-1">
                                                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex flex-col items-center justify-center shrink-0 border border-emerald-100 group-hover:bg-emerald-500 group-hover:text-white transition-colors shadow-sm">
                                                    <span class="text-[10px] font-bold uppercase leading-none mb-0.5"><?= date('M', strtotime($notice['created_at'])) ?></span>
                                                    <span class="text-[17px] font-black leading-none"><?= date('d', strtotime($notice['created_at'])) ?></span>
                                                </div>
                                                <div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-4">
                                                    <div class="min-w-0 flex-1">
                                                        <h4 class="font-extrabold text-slate-800 text-[15px] group-hover:text-emerald-600 transition-colors line-clamp-1"><?= htmlspecialchars($notice['title']) ?></h4>
                                                        <p class="text-xs text-slate-500 mt-1 line-clamp-1 font-medium"><?= htmlspecialchars(strip_tags($notice['content'])) ?></p>
                                                    </div>
                                                    <div class="flex items-center gap-3 text-[11px] font-bold text-slate-500 shrink-0">
                                                        <span class="flex items-center gap-1.5 bg-slate-100 px-2 py-1 rounded-md text-slate-600 border border-slate-200">
                                                            <i data-lucide="user" class="w-3 h-3 text-slate-400"></i> <?= htmlspecialchars($notice['author_name'] ?? 'System') ?>
                                                        </span>
                                                        <span class="flex items-center gap-1.5 text-emerald-700 bg-emerald-50 px-2 py-1 rounded-md border border-emerald-100">
                                                            <i data-lucide="message-square" class="w-3 h-3"></i> <?= (int)$notice['reply_count'] ?> Replies
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="hidden sm:flex items-center gap-2">
                                                <div class="w-8 h-8 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 group-hover:border-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition-colors shadow-sm">
                                                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="py-16 text-center border-2 border-dashed border-slate-200 rounded-[1.5rem] bg-slate-50/50">
                                        <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-200 shadow-sm">
                                            <i data-lucide="inbox" class="w-5 h-5 text-slate-400"></i>
                                        </div>
                                        <span class="text-slate-500 font-bold text-sm">No recent notices available.</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Residents & Entries (1/3 width) -->
                <div class="lg:col-span-1 space-y-6 lg:space-y-8 flex flex-col">
                    
                    <!-- Resident List -->
                    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-slate-300 p-7">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-[17px] font-extrabold text-slate-900">Resident Directory</h3>
                            <a href="<?php echo BASE_URL; ?>owner/residents.php" class="p-2 hover:bg-slate-50 rounded-xl transition-colors cursor-pointer group">
                                <i data-lucide="arrow-up-right" class="w-4 h-4 text-slate-400 group-hover:text-slate-700"></i>
                            </a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php
                            try {
                                if (!empty($building_id)) {
                                    $residents_query = "SELECT u.email as username, p.full_name, a.apt_number 
                                                        FROM users u 
                                                        JOIN user_profiles p ON u.id = p.user_id 
                                                        JOIN apartment_assignments aa ON u.id = aa.user_id
                                                        JOIN apartments a ON aa.apt_id = a.id
                                                        WHERE a.building_id = '$building_id' AND u.role_id = 2 
                                                        LIMIT 4";
                                } else {
                                    $residents_query = "SELECT u.email as username, p.full_name, '' as apt_number FROM users u JOIN user_profiles p ON u.id = p.user_id WHERE 1=0";
                                }
                                $residents_result = @mysqli_query($conn, $residents_query);
                                if($residents_result && mysqli_num_rows($residents_result) > 0) {
                                    while($r = mysqli_fetch_assoc($residents_result)) {
                                        ?>
                                        <div class="flex items-center justify-between group py-1">
                                            <div class="flex items-center gap-3.5">
                                                <div class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center font-black text-slate-600 text-xs border border-slate-200/50 shadow-inner">
                                                    <?= strtoupper(substr($r['full_name'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <h4 class="text-sm font-extrabold text-slate-800 mb-0.5"><?= htmlspecialchars($r['full_name']) ?></h4>
                                                    <p class="text-[11px] text-slate-500 font-medium">Unit <?= htmlspecialchars($r['apt_number'] ?? 'N/A') ?></p>
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-1.5 text-[11px] font-bold text-slate-600 cursor-pointer hover:text-slate-900 hover:bg-slate-50 px-2 py-1 rounded-lg transition-colors">
                                                Resident <i data-lucide="chevron-down" class="w-3 h-3 text-slate-400"></i>
                                            </div>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    echo '<p class="text-sm text-slate-400 text-center py-6 font-medium">No residents found.</p>';
                                }
                            } catch (Exception $e) {}
                            ?>
                        </div>
                    </div>

                    <!-- Recent Entries (Light Green/White Theme) -->
                    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-slate-300 p-7 relative overflow-hidden group cursor-pointer flex-1 flex flex-col" onclick="window.location.href='<?php echo BASE_URL; ?>owner/guest_entries.php'">
                        <div class="relative z-10 flex flex-col h-full">
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <h3 class="text-[17px] font-extrabold text-slate-900 mb-1">Recent Entries</h3>
                                    <p class="text-xs text-slate-500 font-medium">Security gate logs</p>
                                </div>
                                <div class="p-2 hover:bg-slate-50 rounded-xl transition-colors">
                                    <i data-lucide="arrow-up-right" class="w-4 h-4 text-slate-400 group-hover:text-emerald-500 transition-colors"></i>
                                </div>
                            </div>
                            
                            <!-- 1 rectangular entry per row -> grid-cols-1 -->
                            <div class="grid grid-cols-1 gap-3 mb-5">
                                <?php
                                try {
                                    if (!empty($building_id)) {
                                        $logs_query = "SELECT g.full_name as guest_name, a.apt_number as unit_number, e.entry_time 
                                                        FROM entry_logs e 
                                                        JOIN visit_requests vr ON e.visit_id = vr.id
                                                        JOIN guests g ON vr.guest_id = g.id 
                                                        LEFT JOIN apartments a ON vr.apt_id = a.id
                                                        WHERE a.building_id = '$building_id'
                                                        ORDER BY e.entry_time DESC LIMIT 4";
                                    } else {
                                        $logs_query = "SELECT 1 WHERE 1=0";
                                    }
                                    $logs_result = @mysqli_query($conn, $logs_query);

                                    $count = 0;
                                    if($logs_result && mysqli_num_rows($logs_result) > 0) {
                                        while($log = mysqli_fetch_assoc($logs_result)) {
                                            $log_time = date('h:i A', strtotime($log['entry_time']));
                                            ?>
                                            <div class="bg-slate-50/70 rounded-xl p-3.5 border border-slate-100 hover:bg-emerald-50 hover:border-emerald-200 transition-colors flex items-center justify-between">
                                                <div>
                                                    <h4 class="text-xs font-bold text-slate-800 truncate"><?= htmlspecialchars($log['guest_name']) ?></h4>
                                                    <p class="text-[10px] text-slate-500 mt-0.5 font-medium">Unit <?= htmlspecialchars($log['unit_number'] ?? 'N/A') ?></p>
                                                </div>
                                                <div class="flex items-center gap-1.5 px-2 py-1 bg-white border border-slate-200 rounded-lg shadow-sm">
                                                    <i data-lucide="clock" class="w-3 h-3 text-emerald-500"></i>
                                                    <span class="text-[10px] font-bold text-slate-600 uppercase tracking-wider"><?= $log_time ?></span>
                                                </div>
                                            </div>
                                            <?php
                                            $count++;
                                        }
                                        while($count < 4) {
                                            ?>
                                            <div class="bg-slate-50/30 rounded-xl p-3.5 border border-slate-100 border-dashed flex items-center justify-center opacity-70">
                                                <span class="text-[10px] text-slate-400 font-medium">No record</span>
                                            </div>
                                            <?php
                                            $count++;
                                        }
                                    } else {
                                        echo '<div class="text-sm text-slate-400 py-4 text-center">No recent entries.</div>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </div>
                            
                            <div class="mt-auto pt-4 border-t border-slate-100 flex items-start gap-3 bg-emerald-50/50 -mx-7 -mb-7 p-5 px-7">
                                <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500 shrink-0 mt-0.5"></i>
                                <p class="text-[11px] text-emerald-700 leading-relaxed font-medium">
                                    Monitor unexpected entries to maintain security.
                                </p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div> 
    </div> 
</main>

    <script src="<?php echo BASE_URL; ?>js/owner_logic.js"></script>
    <script>lucide.createIcons();</script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>
    <script>
    function notificationDropdown() {
        return {
            open: false,
            notifications: [],
            unreadCount: 0,
            init() {
                this.fetchNotifications();
                setInterval(() => this.fetchNotifications(), 30000);
            },
            toggle() {
                this.open = !this.open;
            },
            fetchNotifications() {
                fetch('<?php echo BASE_URL; ?>api/notifications.php?action=get')
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            this.notifications = data.notifications;
                            this.unreadCount = data.unread_count;
                            setTimeout(() => {
                                if(window.lucide) lucide.createIcons();
                            }, 50);
                        }
                    });
            },
            markRead(id) {
                fetch('<?php echo BASE_URL; ?>api/notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'mark_read', id: id })
                }).then(() => {
                    this.fetchNotifications();
                });
            }
        }
    }
    </script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>