<?php
session_start();
require_once '../includes/db_config.php';
mysqli_report(MYSQLI_REPORT_ERROR);

// Check if exactly owner
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = "Owner";
try {
    $query = "SELECT full_name FROM user_profiles WHERE user_id = '$user_id'";
    $result = @mysqli_query($conn, $query);
    if($result && mysqli_num_rows($result) > 0) {
        $user_profile = mysqli_fetch_assoc($result);
        $user_name = $user_profile['full_name'];
    }
} catch (Exception $e) {}

// Edit Resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_resident'])) {
    $res_id = intval($_POST['resident_id']); 
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    
    $verify_q = "SELECT aa_res.id FROM apartment_assignments aa_res
                 JOIN apartment_assignments aa_own ON aa_res.apt_id = aa_own.apt_id
                 WHERE aa_res.user_id = $res_id AND aa_own.user_id = $user_id AND aa_own.role = 'owner'";
    $verify_res = mysqli_query($conn, $verify_q);
    
    if(mysqli_num_rows($verify_res) > 0) {
        mysqli_query($conn, "UPDATE user_profiles SET full_name = '$name', phone = '$phone' WHERE user_id = $res_id");
        $success_msg = "Resident details updated.";
    } else {
        $error_msg = "Unauthorized action.";
    }
}

// Delete Resident
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_resident'])) {
    $res_id = intval($_POST['resident_id']);
    
    $verify_q = "SELECT aa_res.id, aa_res.apt_id FROM apartment_assignments aa_res
                 JOIN apartment_assignments aa_own ON aa_res.apt_id = aa_own.apt_id
                 WHERE aa_res.user_id = $res_id AND aa_own.user_id = $user_id AND aa_own.role = 'owner'";
    $verify_res = mysqli_query($conn, $verify_q);
    
    if(mysqli_num_rows($verify_res) > 0) {
        $row = mysqli_fetch_assoc($verify_res);
        $assignment_id = $row['id'];
        $apt_id = $row['apt_id'];
        
        // Remove assignment
        mysqli_query($conn, "DELETE FROM apartment_assignments WHERE id = $assignment_id");
        $active_tenants = mysqli_query($conn, "SELECT id FROM apartment_assignments WHERE apt_id = $apt_id AND is_active = 1 AND role = 'tenant'");
        if(mysqli_num_rows($active_tenants) == 0){
             mysqli_query($conn, "UPDATE apartments SET status = 'available' WHERE id = $apt_id");
        }
        
        // Delete all resident's dependent data
        mysqli_query($conn, "DELETE FROM community_interactions WHERE user_id = $res_id OR reference_user_id = $res_id");
        mysqli_query($conn, "DELETE FROM community_comments WHERE user_id = $res_id");
        mysqli_query($conn, "DELETE FROM community_posts WHERE user_id = $res_id");
        
        // Delete payments and bill items dependent on bills
        mysqli_query($conn, "DELETE FROM payments WHERE bill_id IN (SELECT id FROM bills WHERE resident_id = $res_id)");
        mysqli_query($conn, "DELETE FROM bill_items WHERE bill_id IN (SELECT id FROM bills WHERE resident_id = $res_id)");
        mysqli_query($conn, "DELETE FROM bills WHERE resident_id = $res_id");
        
        mysqli_query($conn, "DELETE FROM cctv_captures WHERE user_id = $res_id");
        mysqli_query($conn, "DELETE FROM family_members WHERE primary_user_id = $res_id");
        mysqli_query($conn, "DELETE FROM notifications WHERE user_id = $res_id");
        mysqli_query($conn, "DELETE FROM parking_requests WHERE requester_id = $res_id OR target_resident_id = $res_id");
        mysqli_query($conn, "UPDATE parking_slots SET current_status = 'Vacant', temporary_name = NULL, temporary_until = NULL WHERE (SELECT user_id FROM apartment_assignments WHERE apt_id = parking_slots.apt_id AND is_active = 1 LIMIT 1) = $res_id");
        mysqli_query($conn, "DELETE FROM provider_bookings WHERE resident_id = $res_id");
        mysqli_query($conn, "DELETE FROM resident_vehicles WHERE user_id = $res_id");
        mysqli_query($conn, "DELETE FROM visit_requests WHERE resident_id = $res_id");
        
        // Additional dependent data
        if ($res = mysqli_query($conn, "SHOW TABLES LIKE 'service_requests'")) {
            if (mysqli_num_rows($res) > 0) mysqli_query($conn, "DELETE FROM service_requests WHERE user_id = $res_id");
        }
        
        // Delete emergency contacts dependent on user_profiles
        mysqli_query($conn, "DELETE FROM emergency_contacts WHERE user_profile_id IN (SELECT id FROM user_profiles WHERE user_id = $res_id)");
        
        mysqli_query($conn, "DELETE FROM user_profiles WHERE user_id = $res_id");
        mysqli_query($conn, "DELETE FROM users WHERE id = $res_id");
        
        $success_msg = "Resident and all associated data permanently removed.";
    } else {
        $error_msg = "Unauthorized action.";
    }
}

// Toggle User Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $res_id = intval($_POST['resident_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    
    $verify_q = "SELECT aa_res.id FROM apartment_assignments aa_res
                 JOIN apartment_assignments aa_own ON aa_res.apt_id = aa_own.apt_id
                 WHERE aa_res.user_id = $res_id AND aa_own.user_id = $user_id AND aa_own.role = 'owner'";
    $verify_res = mysqli_query($conn, $verify_q);
    
    if(mysqli_num_rows($verify_res) > 0) {
        if(in_array($new_status, ['active', 'suspended'])) {
            mysqli_query($conn, "UPDATE users SET status = '$new_status' WHERE id = $res_id");
            $success_msg = "Resident status updated to $new_status.";
        }
    }
}

// Fetch Owner's Apartments for Filter
$apts_query = "SELECT DISTINCT a.id, a.apt_number FROM apartments a
               JOIN apartment_assignments aa_own ON a.id = aa_own.apt_id
               JOIN apartment_assignments aa_tenant ON a.id = aa_tenant.apt_id
               WHERE aa_own.user_id = $user_id AND aa_own.role = 'owner' AND aa_own.is_active = 1
               AND aa_tenant.role = 'tenant' AND aa_tenant.is_active = 1";
$apts_result = mysqli_query($conn, $apts_query);
$apartments = [];
while($row = mysqli_fetch_assoc($apts_result)){
    $apartments[] = $row;
}

// Fetch Residents
$residents_query = "
    SELECT u.id as user_id, u.email, u.status, p.full_name, p.phone, p.profile_image, a.apt_number, a.id as apt_id, aa.start_date
    FROM users u
    JOIN user_profiles p ON u.id = p.user_id
    JOIN apartment_assignments aa ON u.id = aa.user_id
    JOIN apartments a ON aa.apt_id = a.id
    JOIN apartment_assignments aa_own ON a.id = aa_own.apt_id
    WHERE aa_own.user_id = $user_id AND aa_own.role = 'owner' AND aa_own.is_active = 1
    AND aa.role = 'tenant' AND aa.is_active = 1
    ORDER BY a.apt_number ASC, p.full_name ASC
";
$residents_result = mysqli_query($conn, $residents_query);
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
    <title>Resident Directory | Nibash</title>
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

    <?php $active_page = 'residents'; include '../includes/owner_sidebar.php'; ?>

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
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Resident Directory</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50">
                
                <?php if (!empty($success_msg)): ?>
                    <script>document.addEventListener("DOMContentLoaded", function() { if(typeof showCustomPopup === 'function') showCustomPopup("<?= addslashes($success_msg) ?>", 'success'); });</script>
                <?php endif; ?>
                <?php if (!empty($error_msg)): ?>
                    <script>document.addEventListener("DOMContentLoaded", function() { if(typeof showCustomPopup === 'function') showCustomPopup("<?= addslashes($error_msg) ?>", 'error'); });</script>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 mb-8 border-b border-slate-200 relative">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            Resident Directory
                        </h1>
                        <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base">
                            <span class="p-1.5 bg-emerald-100 border border-emerald-200 rounded-lg"><i data-lucide="users" class="w-4 h-4 text-emerald-700"></i></span>
                            Manage and view all residents across your properties.
                        </p>
                    </div>
                    <a href="add_resident.php" class="w-full sm:w-auto px-8 py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl transition-all shadow-[0_8px_20px_-6px_rgba(16,185,129,0.5)] hover:shadow-[0_12px_25px_-6px_rgba(16,185,129,0.6)] flex items-center justify-center gap-3 transform hover:-translate-y-1 group">
                        <i data-lucide="user-plus" class="w-5 h-5 group-hover:rotate-12 transition-transform"></i> Add New Resident
                    </a>
                </div>

                <div class="bg-white p-5 rounded-[1.5rem] shadow-sm border border-slate-200 flex flex-col md:flex-row gap-4 items-center justify-between mb-10">
                    <div class="relative w-full md:w-1/2 lg:w-96">
                        <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                        <input type="text" id="searchInput" placeholder="Search by name, email, or phone..." 
                            class="w-full pl-11 pr-4 py-3.5 bg-slate-50/50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-medium">
                    </div>
                    <div class="w-full md:w-auto flex items-center gap-3">
                        <div class="relative w-full md:w-56">
                            <i data-lucide="building-2" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-emerald-600"></i>
                            <select id="aptFilter" class="w-full pl-10 pr-4 py-3.5 bg-emerald-50/50 hover:bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold appearance-none cursor-pointer">
                                <option value="">All Apartments</option>
                                <?php foreach($apartments as $apt): ?>
                                    <option value="<?= htmlspecialchars($apt['apt_number']) ?>">Apt <?= htmlspecialchars($apt['apt_number']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-emerald-600 pointer-events-none"></i>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap justify-center gap-6 max-w-6xl mx-auto" id="residentGrid">
                    <?php if (mysqli_num_rows($residents_result) > 0): ?>
                        <?php while ($r = mysqli_fetch_assoc($residents_result)): ?>
                            <?php 
                                $status_color = $r['status'] == 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200';
                                $status_dot = $r['status'] == 'active' ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]' : 'bg-rose-500';
                            ?>
                            <div class="resident-card w-full lg:w-[calc(50%-0.75rem)] bg-white p-5 rounded-2xl border border-slate-200 shadow-sm hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 flex flex-col sm:flex-row items-start sm:items-center gap-5 group relative"
                                 data-name="<?= strtolower(htmlspecialchars($r['full_name'])) ?>"
                                 data-email="<?= strtolower(htmlspecialchars($r['email'])) ?>"
                                 data-phone="<?= strtolower(htmlspecialchars($r['phone'])) ?>"
                                 data-apt="<?= htmlspecialchars($r['apt_number']) ?>">
                                
                                <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full border-4 border-slate-50 shadow-sm overflow-hidden shrink-0 bg-slate-100 relative group-hover:border-emerald-50 transition-colors">
                                    <?php if (!empty($r['profile_image'])): ?>
                                        <img src="<?php echo BASE_URL; ?>assets/uploads/profiles/<?= htmlspecialchars($r['profile_image']) ?>" class="w-full h-full object-cover">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-slate-300"><i data-lucide="user" class="w-8 h-8 sm:w-10 sm:h-10"></i></div>
                                    <?php endif; ?>
                                </div>
                            
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-center gap-3 mb-1">
                                        <h3 class="text-lg font-bold text-slate-900 truncate group-hover:text-emerald-600 transition-colors">
                                            <?= htmlspecialchars($r['full_name']) ?>
                                        </h3>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-widest rounded-md border shadow-sm <?= $status_color ?> bg-white">
                                            <span class="w-1.5 h-1.5 rounded-full <?= $status_dot ?>"></span>
                                            <?= htmlspecialchars($r['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-xs font-bold text-slate-500 mb-3 flex items-center gap-1.5">
                                        <span class="bg-slate-50 px-2 py-0.5 rounded border border-slate-100 shadow-sm inline-flex items-center gap-1">
                                            <i data-lucide="building-2" class="w-3.5 h-3.5 text-emerald-500"></i> Unit <?= htmlspecialchars($r['apt_number']) ?>
                                        </span>
                                    </p>
                            
                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-slate-600">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <i data-lucide="mail" class="w-3.5 h-3.5 text-slate-400"></i>
                                            <span class="truncate font-medium text-xs" title="<?= htmlspecialchars($r['email']) ?>"><?= htmlspecialchars($r['email']) ?></span>
                                        </div>
                                        <div class="flex items-center gap-1.5 shrink-0">
                                            <i data-lucide="phone" class="w-3.5 h-3.5 text-slate-400"></i>
                                            <span class="font-medium text-xs"><?= htmlspecialchars($r['phone']) ?: 'Not Provided' ?></span>
                                        </div>
                                    </div>
                                </div>
                            
                                <div class="w-full sm:w-auto flex sm:flex-col gap-2 shrink-0 border-t sm:border-t-0 sm:border-l border-slate-100 pt-4 sm:pt-0 sm:pl-5 mt-2 sm:mt-0">
                                    <a href="view_resident.php?id=<?= $r['user_id'] ?>" 
                                            class="flex-1 sm:flex-none flex items-center justify-center sm:justify-start gap-2 px-4 py-2 bg-slate-50 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors font-bold text-xs border border-slate-200 shadow-sm" title="View Profile">
                                        <i data-lucide="eye" class="w-4 h-4"></i> <span class="sm:hidden lg:inline">View</span>
                                    </a>
                                    <form method="POST" class="flex-1 sm:flex-none m-0 p-0">
                                        <input type="hidden" name="resident_id" value="<?= $r['user_id'] ?>">
                                        <?php if ($r['status'] == 'active'): ?>
                                            <input type="hidden" name="new_status" value="suspended">
                                            <button type="submit" name="toggle_status" class="w-full flex items-center justify-center sm:justify-start gap-2 px-4 py-2 bg-slate-50 text-slate-600 hover:bg-amber-50 hover:text-amber-600 rounded-xl transition-colors font-bold text-xs border border-slate-200 shadow-sm" title="Suspend Resident">
                                                <i data-lucide="ban" class="w-4 h-4"></i> <span class="sm:hidden lg:inline">Suspend</span>
                                            </button>
                                        <?php else: ?>
                                            <input type="hidden" name="new_status" value="active">
                                            <button type="submit" name="toggle_status" class="w-full flex items-center justify-center sm:justify-start gap-2 px-4 py-2 bg-slate-50 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors font-bold text-xs border border-slate-200 shadow-sm" title="Activate Resident">
                                                <i data-lucide="check-circle" class="w-4 h-4"></i> <span class="sm:hidden lg:inline">Activate</span>
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to completely remove this resident from the system?');" class="flex-1 sm:flex-none m-0 p-0">
                                        <input type="hidden" name="resident_id" value="<?= $r['user_id'] ?>">
                                        <button type="submit" name="delete_resident" class="w-full flex items-center justify-center sm:justify-start gap-2 px-4 py-2 bg-slate-50 text-slate-600 hover:bg-rose-50 hover:text-rose-600 rounded-xl transition-colors font-bold text-xs border border-slate-200 shadow-sm" title="Remove Resident">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i> <span class="sm:hidden lg:inline">Remove</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-span-full text-center py-20 bg-white rounded-[1.5rem] border-2 border-dashed border-slate-300 flex flex-col items-center shadow-sm">
                            <div class="w-20 h-20 bg-slate-50 border border-slate-100 shadow-sm rounded-full flex items-center justify-center mb-4 text-emerald-400">
                                <i data-lucide="users" class="w-10 h-10"></i>
                            </div>
                            <h3 class="text-xl font-black text-slate-900 mb-2">No Residents Found</h3>
                            <p class="text-sm text-slate-500 max-w-sm font-medium">You haven't assigned any residents to your properties yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>js/owner_logic.js"></script>
    <script>
        lucide.createIcons();

        const cards = document.querySelectorAll('.resident-card');

        function filterResidents() {
            const searchTerm = searchInput.value.toLowerCase();
            const filterApt = aptFilter.value;

            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const email = card.getAttribute('data-email');
                const phone = card.getAttribute('data-phone');
                const apt = card.getAttribute('data-apt');

                const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm) || phone.includes(searchTerm);
                const matchesApt = filterApt === "" || apt === filterApt;

                if (matchesSearch && matchesApt) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        searchInput.addEventListener('input', filterResidents);
        aptFilter.addEventListener('change', filterResidents);
    </script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>