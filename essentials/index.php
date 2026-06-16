<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
if ($_SESSION['role_id'] == 1) {
    $owner_id = $user_id;
} else {
    // Get owner_id from apartment_assignments for residents
    $q = mysqli_query($conn, "SELECT aa_o.user_id as owner_id 
                              FROM apartment_assignments aa_t
                              JOIN apartment_assignments aa_o ON aa_t.apt_id = aa_o.apt_id
                              WHERE aa_t.user_id = '$user_id' 
                              AND aa_t.is_active = 1 
                              AND aa_o.role = 'owner' 
                              AND aa_o.is_active = 1 
                              LIMIT 1");
    $r = mysqli_fetch_assoc($q);
    $owner_id = $r['owner_id'] ?? 0;
}

$b_id = $_SESSION['building_id'] ?? 0;

// Try to get building ID if not in session
if (!$b_id) {
    $b_q = mysqli_query($conn, "SELECT a.building_id FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id WHERE aa.user_id = '$user_id' AND aa.is_active = 1 LIMIT 1");
    if ($b_row = mysqli_fetch_assoc($b_q)) {
        $b_id = $b_row['building_id'];
    }
}

$success_msg = '';
$error_msg = '';

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Handle Adding a New Provider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_provider']) && $_SESSION['role_id'] == 1) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $nid = mysqli_real_escape_string($conn, trim($_POST['nid_number'])); 
    
    // New Internet Provider fields
    $website_url = mysqli_real_escape_string($conn, trim($_POST['website_url'] ?? ''));
    if (!empty($website_url) && !preg_match("~^(?:f|ht)tps?://~i", $website_url)) {
        $website_url = "https://" . $website_url;
    }
    $pricing_details = mysqli_real_escape_string($conn, trim($_POST['pricing_details'] ?? ''));
    
    // Format the time schedule (e.g., "10:00 AM - 04:00 PM")
    $start_time = date("h:i A", strtotime($_POST['start_time']));
    $end_time = date("h:i A", strtotime($_POST['end_time']));
    $schedule = $start_time . " - " . $end_time;

    // Handle Category Logic
    $category_id = $_POST['category_id'];
    
    if ($category_id === 'other') {
        $custom_category = mysqli_real_escape_string($conn, trim($_POST['custom_category']));
        
        $check_cat = mysqli_query($conn, "SELECT id FROM service_categories WHERE category_name = '$custom_category'");
        if (mysqli_num_rows($check_cat) > 0) {
            $cat_row = mysqli_fetch_assoc($check_cat);
            $category_id = $cat_row['id'];
        } else {
            mysqli_query($conn, "INSERT INTO service_categories (category_name, icon_name) VALUES ('$custom_category', 'wrench')");
            $category_id = mysqli_insert_id($conn);
        }
    }

    // Handle Image Upload
    $image_path = 'default_avatar.jpg';
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $new_filename = uniqid() . '.' . $ext;
            $upload_dir = '../assets/uploads/essentials/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_dir . $new_filename)) {
                $image_path = $new_filename;
            }
        }
    }

    // Insert Provider (Updated with website_url and pricing_details)
    $insert_query = "INSERT INTO service_providers (category_id, building_id, name, phone, email, website_url, pricing_details, nid_number, image_path, availability_schedule, is_active) 
                     VALUES ('$category_id', '$b_id', '$name', '$phone', '$email', '$website_url', '$pricing_details', '$nid', '$image_path', '$schedule', 1)";
    
    if (mysqli_query($conn, $insert_query)) {
        $_SESSION['success_msg'] = "Provider added successfully!";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error_msg'] = "Error: " . mysqli_error($conn);
        header("Location: index.php");
        exit();
    }
}

// Handle Deleting a Provider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_provider']) && $_SESSION['role_id'] == 1) {
    $delete_id = mysqli_real_escape_string($conn, $_POST['delete_provider_id']);
    $delete_query = "UPDATE service_providers SET is_active = 0 WHERE id = '$delete_id' AND (building_id = '$b_id' OR building_id IS NULL)";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['success_msg'] = "Provider deleted successfully!";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error_msg'] = "Error deleting provider.";
        header("Location: index.php");
        exit();
    }
}

// Fetch user's coordinates for distance filtering
$user_lat = 0;
$user_lng = 0;
$up_query = mysqli_query($conn, "SELECT latitude, longitude FROM user_profiles WHERE user_id = '$user_id'");
if ($up_data = mysqli_fetch_assoc($up_query)) {
    $user_lat = floatval($up_data['latitude'] ?? 0);
    $user_lng = floatval($up_data['longitude'] ?? 0);
}

// Fallback to building location if user location is 0 or missing
if (($user_lat == 0 || $user_lng == 0) && $b_id) {
    $b_loc_query = mysqli_query($conn, "SELECT latitude, longitude FROM buildings WHERE id = '$b_id'");
    if ($b_loc_data = mysqli_fetch_assoc($b_loc_query)) {
        $user_lat = floatval($b_loc_data['latitude'] ?? 0);
        $user_lng = floatval($b_loc_data['longitude'] ?? 0);
    }
}

// Fetch categories for dropdown and tabs (with distance filter logic: either matches building or within 5km coverage)
$categories_query = mysqli_query($conn, "SELECT sc.*, 
    (SELECT COUNT(DISTINCT sp.id) FROM service_providers sp 
      LEFT JOIN provider_locations pl ON pl.provider_id = sp.id
      WHERE sp.category_id = sc.id AND sp.is_active = 1 
      AND (
          sp.building_id = '$b_id' 
          OR (sp.user_id IS NOT NULL AND sp.latitude IS NOT NULL AND sp.longitude IS NOT NULL AND 
             (6371 * acos(LEAST(1.0, cos(radians($user_lat)) * cos(radians(sp.latitude)) * cos(radians(sp.longitude) - radians($user_lng)) + sin(radians($user_lat)) * sin(radians(sp.latitude))))) <= 2)
          OR (pl.id IS NOT NULL AND 
             (6371 * acos(LEAST(1.0, cos(radians($user_lat)) * cos(radians(pl.latitude)) * cos(radians(pl.longitude) - radians($user_lng)) + sin(radians($user_lat)) * sin(radians(pl.latitude))))) <= 2)
      )
    ) as provider_count 
    FROM service_categories sc ORDER BY sc.category_name ASC");

// Fetch ALL active providers with distance filter
$providers_query = mysqli_query($conn, "SELECT DISTINCT sp.*, sc.category_name, sc.icon_name,
                                      (SELECT COUNT(*) FROM provider_reviews pr WHERE pr.provider_id = sp.id) as total_reviews
                                      FROM service_providers sp 
                                      LEFT JOIN service_categories sc ON sp.category_id = sc.id 
                                      LEFT JOIN provider_locations pl ON pl.provider_id = sp.id
                                      WHERE sp.is_active = 1 
                                      AND (
                                          sp.building_id = '$b_id' 
                                          OR (sp.user_id IS NOT NULL AND sp.latitude IS NOT NULL AND sp.longitude IS NOT NULL AND 
                                             (6371 * acos(LEAST(1.0, cos(radians($user_lat)) * cos(radians(sp.latitude)) * cos(radians(sp.longitude) - radians($user_lng)) + sin(radians($user_lat)) * sin(radians(sp.latitude))))) <= 2)
                                          OR (pl.id IS NOT NULL AND 
                                             (6371 * acos(LEAST(1.0, cos(radians($user_lat)) * cos(radians(pl.latitude)) * cos(radians(pl.longitude) - radians($user_lng)) + sin(radians($user_lat)) * sin(radians(pl.latitude))))) <= 2)
                                      )
                                      ORDER BY sp.is_subscribed DESC, sp.id DESC");

// Dynamic Capsule Colors Mapper based on Category Name
function getCategoryColorClass($categoryName) {
    $colors = [
        'Electrician' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'icon' => 'fill-amber-500 text-amber-500'],
        'Plumber' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'border' => 'border-blue-200', 'icon' => 'fill-blue-500 text-blue-500'],
        'Cleaner' => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'border' => 'border-purple-200', 'icon' => 'fill-purple-500 text-purple-500'],
        'Internet Provider' => ['bg' => 'bg-teal-100', 'text' => 'text-teal-700', 'border' => 'border-teal-200', 'icon' => 'fill-teal-500 text-teal-500'],
        // 'Security Guard' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'icon' => 'fill-rose-500 text-rose-500'],
        'Housekeeper' => ['bg' => 'bg-pink-100', 'text' => 'text-pink-700', 'border' => 'border-pink-200', 'icon' => 'fill-pink-500 text-pink-500'],
    ];
    return $colors[$categoryName] ?? ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'border' => 'border-slate-200', 'icon' => 'fill-slate-500 text-slate-500'];
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
    <title>Local Services | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if ($_SESSION['role_id'] == 1): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <?php else: ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <?php endif; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .hover-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -8px rgba(16, 185, 129, 0.15); border-color: #6ee7b7; }
        .folder-tab { transition: all 0.2s ease; }
        .folder-tab.active { background-color: #0f172a; color: white; border-color: #0f172a; shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #ecfdf5; }
        ::-webkit-scrollbar-thumb { background: #a7f3d0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6ee7b7; }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php if ($_SESSION['role_id'] == 1) {
        include '../includes/owner_sidebar.php';
    } else {
        include '../includes/resident_sidebar.php';
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
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Local Services</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50 space-y-10">
                
                <?php if (!empty($success_msg)): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            if(typeof showCustomPopup === 'function') showCustomPopup("<?= addslashes($success_msg) ?>", 'success');
                        });
                    </script>
                <?php endif; ?>
                <?php if (!empty($error_msg)): ?>
                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            if(typeof showCustomPopup === 'function') showCustomPopup("<?= addslashes($error_msg) ?>", 'error');
                        });
                    </script>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 border-b border-slate-200">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            Local Services
                        </h1>
                        <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base">
                            <span class="p-1.5 bg-emerald-100 border border-emerald-200 rounded-lg"><i data-lucide="briefcase" class="w-4 h-4 text-emerald-700"></i></span>
                            Manage and find service providers seamlessly.
                        </p>
                    </div>
                    <?php if ($_SESSION['role_id'] == 1): ?>
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full sm:w-auto">
                        <a href="<?php echo BASE_URL; ?>owner/tickets.php" class="w-full sm:w-auto px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-blue-500/40 flex items-center justify-center gap-2 group">
                            <i data-lucide="ticket" class="w-4 h-4 group-hover:scale-110 transition-transform"></i> View Tickets
                        </a>
                        <button onclick="document.getElementById('add-provider-form').classList.toggle('hidden')" class="w-full sm:w-auto px-6 py-3 bg-slate-900 hover:bg-emerald-600 text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-emerald-500/40 flex items-center justify-center gap-2 group">
                            <i data-lucide="plus" class="w-4 h-4 group-hover:scale-110 transition-transform"></i> Add Provider
                        </button>
                    </div>
                    <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>resident/tickets.php" class="w-full sm:w-auto px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-blue-500/40 flex items-center justify-center gap-2 group">
                        <i data-lucide="ticket" class="w-4 h-4 group-hover:scale-110 transition-transform"></i> Request Maintenance
                    </a>
                    <?php endif; ?>
                </div>

                <?php if ($_SESSION['role_id'] == 1): ?>
                <div id="add-provider-form" class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8 hidden transition-all duration-300">
                    <div class="flex items-center gap-3 mb-8 border-b border-slate-100 pb-4">
                        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                            <i data-lucide="user-plus" class="w-5 h-5"></i>
                        </div>
                        <h2 class="text-xl font-black text-slate-900">Add New Provider</h2>
                    </div>

                    <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Full Name <span class="text-emerald-500">*</span></label>
                                <input type="text" name="name" required placeholder="e.g. John Doe"
                                    class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-medium">
                            </div>

                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Phone Number <span class="text-emerald-500">*</span></label>
                                <div class="relative">
                                    <i data-lucide="phone" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                    <input type="tel" name="phone" required placeholder="Whatsapp Number"
                                        class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-medium">
                                </div>
                            </div>

                            <div id="email_div">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Email Address <span class="text-emerald-500">*</span></label>
                                <div class="relative">
                                    <i data-lucide="mail" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                    <input type="email" name="email" id="email_input" required placeholder="Provider Email"
                                        class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-medium">
                                </div>
                            </div>

                            <div id="availability_div">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Availability Time <span class="text-emerald-500">*</span></label>
                                <div class="flex items-center gap-2">
                                    <input type="time" name="start_time" id="start_time_input" required
                                        class="w-full px-3 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-700">
                                    <span class="text-[10px] font-black text-slate-400 uppercase">to</span>
                                    <input type="time" name="end_time" id="end_time_input" required
                                        class="w-full px-3 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-700">
                                </div>
                            </div>

                            <div class="relative">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Occupation / Folder <span class="text-emerald-500">*</span></label>
                                <select name="category_id" id="category_select" required onchange="toggleCustomCategory()"
                                    class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all appearance-none cursor-pointer text-sm font-bold text-slate-700">
                                    <option value="" disabled selected>Select Folder</option>
                                    <?php mysqli_data_seek($categories_query, 0); ?>
                                    <?php while($cat = mysqli_fetch_assoc($categories_query)): ?>
                                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                    <?php endwhile; ?>
                                    <option value="other" class="font-black text-emerald-600">+ Create New Folder...</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-4 pt-7 flex items-center text-slate-400">
                                    <i data-lucide="chevron-down" class="w-4 h-4"></i>
                                </div>
                            </div>
                            
                            <div id="website_div" style="display: none;">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Website URL <span class="text-emerald-500 opacity-50">(For ISPs)</span></label>
                                <div class="relative">
                                    <i data-lucide="globe" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                    <input type="text" name="website_url" placeholder="e.g., example.com"
                                        class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-medium">
                                </div>
                            </div>

                            <div id="pricing_div" class="md:col-span-3" style="display: none;">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Pricing & Features <span class="text-emerald-500 opacity-50">(Great for ISP Packages)</span></label>
                                <textarea name="pricing_details" placeholder="List packages here. Example: 30 Mbps - 800 BDT/mo | 50 Mbps - 1200 BDT/mo..."
                                    class="w-full px-5 py-3.5 h-24 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-medium resize-none"></textarea>
                            </div>

                            <div id="custom_category_div" style="display: none;" class="md:col-span-1">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">New Folder Name <span class="text-emerald-500">*</span></label>
                                <input type="text" name="custom_category" id="custom_category_input" placeholder="e.g. Technician"
                                    class="w-full px-5 py-3.5 bg-emerald-50 border border-emerald-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-medium">
                            </div>

                            <div id="nid_div" class="md:col-span-1">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">NID / ID Proof <span class="text-slate-300 font-bold ml-1">(Optional)</span></label>
                                <input type="text" name="nid_number" placeholder="NID Number"
                                    class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-medium">
                            </div>

                            <div class="md:col-span-1">
                                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Profile Picture <span class="text-slate-300 font-bold ml-1">(Optional)</span></label>
                                <div class="relative w-full px-5 py-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center gap-3 hover:bg-slate-100 transition-colors">
                                    <input type="file" name="profile_image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" onchange="document.getElementById('file-name').textContent = this.files[0] ? this.files[0].name : 'No file chosen'">
                                    <div class="px-3 py-1 bg-white border border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm">Choose File</div>
                                    <span id="file-name" class="text-xs text-slate-400 font-bold truncate">No file chosen</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-6 border-t border-slate-100">
                            <button type="button" onclick="document.getElementById('add-provider-form').classList.add('hidden')" class="px-8 py-3 bg-slate-50 border border-slate-200 text-slate-500 font-black text-sm rounded-xl hover:bg-slate-100 hover:text-slate-700 transition-colors shadow-sm">
                                Cancel
                            </button>
                            <button type="submit" name="add_provider" class="px-8 py-3 bg-emerald-600 text-white font-black text-sm rounded-xl hover:bg-emerald-700 transition-colors shadow-sm flex items-center gap-2">
                                <i data-lucide="save" class="w-4 h-4"></i> Save Provider
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <div class="flex flex-col gap-5 border-b border-slate-200 pb-6">
                    
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4 w-full">
                        <div class="w-full sm:w-auto flex-1 max-w-md">
                            <div class="relative w-full">
                                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                <input type="text" id="searchInput" placeholder="Search by name..." 
                                    class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-700 shadow-sm">
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row gap-4 w-full sm:w-auto">
                            <div class="relative w-full sm:w-48">
                                <select id="categorySelect" class="w-full pl-5 pr-10 py-3 bg-white border border-slate-200 rounded-xl focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all appearance-none cursor-pointer text-sm font-bold text-slate-700 shadow-sm">
                                    <option value="all">All Categories</option>
                                    <?php 
                                    mysqli_data_seek($categories_query, 0);
                                    while($cat = mysqli_fetch_assoc($categories_query)): 
                                        $display_name = htmlspecialchars($cat['category_name']);
                                        if ($display_name === 'Internet Provider') $display_name = 'Internet';
                                    ?>
                                        <option value="<?= $cat['id'] ?>"><?= $display_name ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-400">
                                    <i data-lucide="filter" class="w-4 h-4"></i>
                                </div>
                            </div>

                            <div class="relative w-full sm:w-56">
                                <select id="sortSelect" class="w-full pl-5 pr-10 py-3 bg-white border border-slate-200 rounded-xl focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all appearance-none cursor-pointer text-sm font-bold text-slate-700 shadow-sm">
                                    <option value="recent">Sort: Recently Added</option>
                                    <option value="name">Sort: Name (A-Z)</option>
                                    <option value="rating">Sort: Highest Rating</option>
                                    <option value="time">Sort: Available Time</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-slate-400">
                                    <i data-lucide="arrow-down-up" class="w-4 h-4"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-full lg:w-[80%] mx-auto">
                    <?php if (mysqli_num_rows($providers_query) > 0): ?>
                        <div class="flex flex-col gap-4" id="providerGrid">
                            <?php while ($prov = mysqli_fetch_assoc($providers_query)): 
                                // Parse time for JavaScript numeric sorting
                                $scheduleParts = explode(' - ', $prov['availability_schedule']);
                                $startTimeStamp = strtotime($scheduleParts[0]);
                                
                                // Get dynamic colors based on category name
                                $colors = getCategoryColorClass($prov['category_name']);
                                $is_pro = (bool)($prov['is_subscribed'] ?? 0);
                            ?>
                                <div class="provider-card hover-card <?= $is_pro ? 'bg-gradient-to-r from-amber-50 to-white border-amber-300 shadow-amber-500/10 shadow-md ring-1 ring-amber-400' : 'bg-white border-slate-200 shadow-sm' ?> rounded-[1.5rem] border overflow-hidden relative group flex flex-col md:flex-row items-center p-5 gap-6"
                                     data-category-id="<?= $prov['category_id'] ?>"
                                     data-name="<?= strtolower(htmlspecialchars($prov['name'])) ?>"
                                     data-rating="<?= $prov['rating'] ?>"
                                     data-time="<?= $startTimeStamp ?>">
                                     
                                     <?php if ($is_pro): ?>
                                         <div class="absolute right-0 top-0 text-[10px] bg-gradient-to-r from-amber-400 to-amber-500 text-white font-black uppercase tracking-widest px-4 py-1.5 rounded-bl-xl shadow-sm flex items-center gap-1.5 z-10">
                                            <i data-lucide="crown" class="w-3.5 h-3.5 fill-amber-500"></i> PRO Provider
                                         </div>
                                     <?php else: ?>
                                         <div class="absolute left-0 top-0 w-full h-1 md:w-2 md:h-full <?= str_replace('text-', 'bg-', $colors['text']) ?>"></div>
                                     <?php endif; ?>
                                    
                                    <div class="flex-shrink-0 relative mt-4 md:mt-0">
                                        <div class="w-20 h-20 rounded-full overflow-hidden border-4 <?= $is_pro ? 'border-amber-100 ring-2 ring-amber-400' : 'border-slate-50' ?> shadow-sm relative group-hover:scale-105 transition-transform bg-slate-100">
                                            <?php 
                                            if (!empty($prov['image_path']) && $prov['image_path'] !== 'default_avatar.jpg'): 
                                                $img_src = 'assets/uploads/essentials/' . $prov['image_path'];
                                                if (file_exists('../assets/uploads/profiles/' . $prov['image_path'])) {
                                                    $img_src = 'assets/uploads/profiles/' . $prov['image_path'];
                                                }
                                            ?>
                                                <img src="<?php echo BASE_URL; ?><?= htmlspecialchars($img_src) ?>" alt="Provider" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-slate-300">
                                                    <i data-lucide="user" class="w-8 h-8"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="flex-1 text-center md:text-left">
                                        <h3 class="text-xl font-black text-slate-900 mb-1.5 group-hover:<?= $is_pro ? 'text-amber-600' : 'text-emerald-600' ?> transition-colors flex items-center justify-center md:justify-start gap-2">
                                            <?= htmlspecialchars($prov['name']) ?>
                                            <?php if($is_pro): ?><i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i><?php endif; ?>
                                        </h3>
                                        
                                        <div class="flex items-center justify-center md:justify-start gap-1 mb-3">
                                            <div class="flex items-center gap-0.5">
                                                <i data-lucide="star" class="w-3.5 h-3.5 <?= ($prov['total_reviews'] ?? 0) > 0 ? 'fill-amber-400 text-amber-400' : 'text-slate-300 fill-slate-100' ?>"></i>
                                                <span class="text-xs font-bold text-slate-700 ml-1"><?= ($prov['total_reviews'] ?? 0) > 0 ? number_format((float)$prov['rating'], 1) : '0.0' ?></span>
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-bold ml-1">(<?= $prov['total_reviews'] ?? 0 ?> Reviews)</span>
                                        </div>
                                        
                                        <?php
                                            $cat_display = htmlspecialchars($prov['category_name']);
                                            if ($cat_display === 'Internet Provider') $cat_display = 'Internet';
                                        ?>
                                        <div class="flex flex-wrap items-center justify-center md:justify-start gap-3">
                                            <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1 <?= $colors['bg'] ?> <?= $colors['text'] ?> text-[10px] font-black uppercase tracking-widest rounded-md border <?= $colors['border'] ?> shadow-sm">
                                                <i data-lucide="<?= htmlspecialchars($prov['icon_name'] ?? 'briefcase') ?>" class="w-3 h-3 <?= $colors['icon'] ?>"></i>
                                                <?= $cat_display ?>
                                            </span>
                                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1 bg-slate-50 px-2.5 py-1.5 border border-slate-200 rounded-md">
                                                <i data-lucide="clock" class="w-3 h-3"></i> <?= htmlspecialchars($prov['availability_schedule']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-3 w-full md:w-auto mt-4 md:mt-0">
                                        <a href="provider.php?id=<?= $prov['id'] ?>" class="flex-1 md:flex-none px-6 py-3 bg-emerald-50 text-emerald-600 font-bold text-xs rounded-xl hover:bg-emerald-100 transition-colors flex items-center justify-center gap-2">
                                            <i data-lucide="eye" class="w-4 h-4"></i> View
                                        </a>
                                        <?php if (!empty($prov['user_id']) && $prov['user_id'] != $_SESSION['user_id']): ?>
                                        <a href="../messages/index.php?user_id=<?= $prov['user_id'] ?>" class="flex-1 md:flex-none px-6 py-3 bg-blue-50 text-blue-600 font-bold text-xs rounded-xl hover:bg-blue-100 transition-colors flex items-center justify-center gap-2">
                                            <i data-lucide="message-square" class="w-4 h-4"></i> Message
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($_SESSION['role_id'] == 1): ?>
                                        <form method="POST" action="" class="flex-1 md:flex-none m-0" onsubmit="return confirm('Are you sure you want to delete this provider?');">
                                            <input type="hidden" name="delete_provider_id" value="<?= $prov['id'] ?>">
                                            <button type="submit" name="delete_provider" class="w-full px-6 py-3 bg-red-50 text-red-600 font-bold text-xs rounded-xl hover:bg-red-100 transition-colors flex items-center justify-center gap-2">
                                                <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <div id="noResultsMsg" class="hidden text-center py-20 bg-white rounded-[1.5rem] border-2 border-dashed border-slate-300 shadow-sm flex-col items-center">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mb-4 text-slate-300 border border-slate-100 shadow-sm">
                                <i data-lucide="search-x" class="w-8 h-8"></i>
                            </div>
                            <h3 class="text-xl font-black text-slate-900 mb-1">No Providers Found</h3>
                            <p class="text-sm font-medium text-slate-500 max-w-sm">No one matches your current search or filters.</p>
                        </div>
                        
                    <?php else: ?>
                        <div class="text-center py-20 bg-white rounded-[2rem] border-2 border-dashed border-slate-300 flex flex-col items-center shadow-sm">
                            <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mb-4 text-emerald-300 border border-slate-100 shadow-sm">
                                <i data-lucide="inbox" class="w-10 h-10"></i>
                            </div>
                            <h3 class="text-xl font-black text-slate-900 mb-1">Directory Empty</h3>
                            <p class="text-sm font-medium text-slate-500 max-w-sm">There are currently no active providers in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        // Client-side Category Folder, Search & Sorting Logic
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const sortSelect = document.getElementById('sortSelect');
            const providerGrid = document.getElementById('providerGrid');
            const noResultsMsg = document.getElementById('noResultsMsg');
            const categorySelect = document.getElementById('categorySelect');
            
            if(!providerGrid) return;
            
            let currentFolder = 'all';
            const cards = Array.from(document.querySelectorAll('.provider-card'));

            if(categorySelect) {
                categorySelect.addEventListener('change', (e) => {
                    currentFolder = e.target.value;
                    applyFilters();
                });
            }

            function applyFilters() {
                const query = searchInput ? searchInput.value.toLowerCase() : '';
                const sortBy = sortSelect ? sortSelect.value : 'recent';
                let visibleCount = 0;

                // 1. Filter Display (Search + Folder)
                cards.forEach(card => {
                    const name = card.getAttribute('data-name');
                    const categoryId = card.getAttribute('data-category-id');
                    
                    const matchesSearch = name.includes(query);
                    const matchesFolder = (currentFolder === 'all' || currentFolder === categoryId);

                    if (matchesSearch && matchesFolder) {
                        card.style.display = 'flex';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Toggle Empty State
                if(visibleCount === 0) {
                    providerGrid.style.display = 'none';
                    if(noResultsMsg) noResultsMsg.style.display = 'flex';
                } else {
                    providerGrid.style.display = 'flex'; // Changed to flex for your layout
                    if(noResultsMsg) noResultsMsg.style.display = 'none';
                }

                // 2. Sorting (utilizing CSS flex/grid order property)
                const visibleCards = cards.filter(c => c.style.display !== 'none');
                
                visibleCards.sort((a, b) => {
                    if (sortBy === 'name') {
                        return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                    } else if (sortBy === 'rating') {
                        return parseFloat(b.getAttribute('data-rating')) - parseFloat(a.getAttribute('data-rating'));
                    } else if (sortBy === 'time') {
                        return parseInt(a.getAttribute('data-time')) - parseInt(b.getAttribute('data-time'));
                    }
                    return 0; // 'recent'
                });

                // Assign the visual CSS order
                visibleCards.forEach((card, index) => {
                    card.style.order = sortBy === 'recent' ? 0 : index;
                });
            }

            if(searchInput) searchInput.addEventListener('input', applyFilters);
            if(sortSelect) sortSelect.addEventListener('change', applyFilters);
        });

        function toggleCustomCategory() {
            const select = document.getElementById('category_select');
            const customDiv = document.getElementById('custom_category_div');
            const customInput = document.getElementById('custom_category_input');
            const nidDiv = document.getElementById('nid_div');
            
            // Getting fields for toggle logic based on occupation
            const emailDiv = document.getElementById('email_div');
            const emailInput = document.getElementById('email_input');
            const availDiv = document.getElementById('availability_div');
            const startInput = document.getElementById('start_time_input');
            const endInput = document.getElementById('end_time_input');
            const webDiv = document.getElementById('website_div');
            const priceDiv = document.getElementById('pricing_div');

            const selectedText = select.options[select.selectedIndex].text.toLowerCase();

            // Reset defaults
            emailDiv.style.display = 'block';
            emailInput.required = true;
            availDiv.style.display = 'block';
            startInput.required = true;
            endInput.required = true;
            webDiv.style.display = 'none';
            priceDiv.style.display = 'none';

            if (selectedText.includes('internet')) {
                // ISP
                availDiv.style.display = 'none';
                startInput.required = false;
                endInput.required = false;
                webDiv.style.display = 'block';
                priceDiv.style.display = 'block';
            } else if (selectedText.includes('maid') || selectedText.includes('housekeeper')) {
                // Maid
                availDiv.style.display = 'none';
                startInput.required = false;
                endInput.required = false;
                emailDiv.style.display = 'none';
                emailInput.required = false;
            } else if (selectedText.includes('electrician') || selectedText.includes('plumber') || selectedText.includes('cleaner') || selectedText.includes('guard')) {
                // Default handling works here, explicitly listing to clarify it doesn't match above constraints
            }
            
            if (select.value === 'other') {
                customDiv.style.display = 'block';
                customInput.required = true;
                nidDiv.style.display = 'none'; 
            } else {
                customDiv.style.display = 'none';
                customInput.required = false;
                nidDiv.style.display = 'block';
            }
        }
    </script>
    <script src="<?php echo BASE_URL; ?>js/owner_logic.js"></script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>