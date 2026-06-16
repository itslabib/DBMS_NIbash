<?php
session_start();
require_once '../includes/db_config.php';
mysqli_report(MYSQLI_REPORT_ERROR);

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: ../owner/residents.php");
    exit();
}

$resident_id = (int) $_GET['id'];

// Check if resident exists and has role_id=2
$check_owner = @mysqli_query($conn, "SELECT id FROM users WHERE id='$resident_id' AND role_id=2");
if (!$check_owner || mysqli_num_rows($check_owner) == 0) {
    header("Location: ../owner/residents.php?error=not_found");
    exit();
}

$res_query = @mysqli_query($conn, "SELECT p.*, u.email as username FROM user_profiles p JOIN users u ON p.user_id = u.id WHERE u.id='$resident_id'");
$resident_info = mysqli_fetch_assoc($res_query);

$profile_image = $resident_info['profile_image'] ?? '';
$profile_image_path = '../assets/uploads/profiles/' . $profile_image;
$avatar_url = (!empty($profile_image) && file_exists($profile_image_path) && $profile_image !== 'default_avatar.png') ? BASE_URL . 'assets/uploads/profiles/' . htmlspecialchars($profile_image) : 'https://ui-avatars.com/api/?name=' . urlencode($resident_info['full_name']) . '&background=random';

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
    <title>View Resident | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
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
    $active_page = 'dashboard';
    include '../includes/owner_sidebar.php'; 
    ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8" id="main-content">
        
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
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Resident Details</span>
                        </h2>
                    </div>
                    
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50 space-y-10">
                <div class="flex items-center">
                    <a href="<?php echo BASE_URL; ?>owner/residents.php" class="flex items-center gap-2 text-sm font-black text-slate-500 hover:text-emerald-600 transition-colors">
                            <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-1 transition-transform"></i> <span class="hidden sm:inline">Directory</span>
                    </a>
                </div>
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 border-b border-slate-200">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            Full Profile
                        </h1>
                        <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base">
                            <span class="p-1.5 bg-emerald-100 border border-emerald-200 rounded-lg"><i data-lucide="user" class="w-4 h-4 text-emerald-700"></i></span>
                            Comprehensive information for <?= htmlspecialchars($resident_info['full_name']) ?>.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest px-4 py-2.5 bg-emerald-50 text-emerald-700 rounded-xl border border-emerald-200 shadow-sm">
                        <i data-lucide="check-circle" class="w-4 h-4"></i> Active Resident
                    </span>
                </div>

                <div class="flex flex-col lg:flex-row gap-10 items-center lg:items-start bg-white p-8 sm:p-10 rounded-[2rem] border border-slate-200 shadow-sm relative overflow-hidden">
                    <div class="absolute left-0 top-0 w-1 h-full bg-emerald-400"></div>
                    
                    <div class="flex flex-col items-center gap-3 group">
                        <div class="w-32 h-32 sm:w-40 sm:h-40 rounded-[1.5rem] overflow-hidden bg-slate-50 border-4 border-white shadow-md shadow-emerald-900/5 ring-1 ring-slate-100 relative group-hover:shadow-lg group-hover:shadow-emerald-500/20 transition-all">
                            <img src="<?= $avatar_url ?>" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110" alt="Profile">
                        </div>
                        <span class="text-[9px] uppercase font-black tracking-widest text-slate-500 px-3 py-1 bg-slate-50 rounded-lg border border-slate-200 shadow-sm">Verified Account</span>
                    </div>

                    <div class="flex-1 text-center lg:text-left w-full mt-2 lg:mt-0 lg:pt-6 border-t lg:border-t-0 lg:border-l border-slate-100 lg:pl-10">
                        <h3 class="text-3xl font-black text-slate-900 tracking-tight mb-2"><?= htmlspecialchars($resident_info['full_name']) ?></h3>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center justify-center lg:justify-start gap-2 mb-4">
                            <i data-lucide="home" class="w-4 h-4 text-emerald-500"></i>
                            Unit/Apt: <span class="text-emerald-600 ml-1"><?= htmlspecialchars($resident_info['unit_number'] ?? 'Not Assigned') ?></span>
                        </p>
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100 shadow-inner">
                            <i data-lucide="info" class="w-5 h-5"></i>
                        </div>
                        <h2 class="text-xl font-black text-slate-900">Personal Data & Verification</h2>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                        
                        <div class="bg-white p-6 rounded-[1.5rem] border border-slate-200 shadow-sm hover-card relative group">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shadow-sm border border-slate-100 group-hover:bg-emerald-50 group-hover:border-emerald-100 transition-colors">
                                    <i data-lucide="phone" class="w-4 h-4 text-slate-400 group-hover:text-emerald-500 transition-colors"></i>
                                </div>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Phone Number</span>
                            </div>
                            <p class="text-base font-bold text-slate-800 pl-11"><?= htmlspecialchars($resident_info['phone'] ?? 'N/A') ?></p>
                        </div>

                        <div class="bg-white p-6 rounded-[1.5rem] border border-slate-200 shadow-sm hover-card relative group">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shadow-sm border border-slate-100 group-hover:bg-emerald-50 group-hover:border-emerald-100 transition-colors">
                                    <i data-lucide="mail" class="w-4 h-4 text-slate-400 group-hover:text-emerald-500 transition-colors"></i>
                                </div>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Email / Login ID</span>
                            </div>
                            <p class="text-base font-bold text-slate-800 pl-11 truncate" title="<?= htmlspecialchars($resident_info['username'] ?? 'N/A') ?>">
                                <?= htmlspecialchars($resident_info['username'] ?? 'N/A') ?>
                            </p>
                        </div>

                        <div class="bg-white p-6 rounded-[1.5rem] border border-slate-200 shadow-sm hover-card relative group">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shadow-sm border border-slate-100 group-hover:bg-emerald-50 group-hover:border-emerald-100 transition-colors">
                                    <i data-lucide="calendar" class="w-4 h-4 text-slate-400 group-hover:text-emerald-500 transition-colors"></i>
                                </div>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Age</span>
                            </div>
                            <p class="text-base font-bold text-slate-800 pl-11"><?= !empty($resident_info['dob']) ? (new DateTime())->diff(new DateTime($resident_info['dob']))->y : 'N/A' ?> years</p>
                        </div>

                        <div class="bg-white p-6 rounded-[1.5rem] border border-slate-200 shadow-sm hover-card relative group">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shadow-sm border border-slate-100 group-hover:bg-emerald-50 group-hover:border-emerald-100 transition-colors">
                                    <i data-lucide="credit-card" class="w-4 h-4 text-slate-400 group-hover:text-emerald-500 transition-colors"></i>
                                </div>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">NID / Passport</span>
                            </div>
                            <p class="text-base font-bold text-slate-800 pl-11">
                                <?= htmlspecialchars($resident_info['nid'] ?? 'Unverified') ?>
                            </p>
                        </div>

                        <div class="bg-white p-6 rounded-[1.5rem] border border-slate-200 shadow-sm hover-card lg:col-span-2 relative group">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center shadow-sm border border-slate-100 group-hover:bg-emerald-50 group-hover:border-emerald-100 transition-colors">
                                    <i data-lucide="briefcase" class="w-4 h-4 text-slate-400 group-hover:text-emerald-500 transition-colors"></i>
                                </div>
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Occupation</span>
                            </div>
                            <p class="text-base font-bold text-slate-800 pl-11">
                                <?= htmlspecialchars($resident_info['occupation'] ?? 'N/A') ?>
                            </p>
                        </div>

                    </div>
                </div>

                <div class="mt-8 pt-6 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-end gap-4">
                    <a href="<?php echo BASE_URL; ?>owner/edit_resident.php?id=<?= $resident_id ?>"
                        class="w-full sm:w-auto flex items-center justify-center gap-2 px-8 py-3.5 bg-slate-50 border border-slate-200 text-slate-700 font-black text-sm rounded-xl hover:bg-emerald-50 hover:text-emerald-700 hover:border-emerald-200 transition-all shadow-sm">
                        <i data-lucide="edit" class="w-4 h-4"></i> Edit Profile
                    </a>
                </div>

            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();
    </script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>