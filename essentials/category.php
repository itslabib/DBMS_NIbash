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

$category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($category_id === 0) {
    header("Location: index.php");
    exit();
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

// Handle Deleting a Provider from this category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_provider']) && $_SESSION['role_id'] == 1) {
    $delete_id = mysqli_real_escape_string($conn, $_POST['delete_provider_id']);
    $delete_query = "UPDATE service_providers SET is_active = 0 WHERE id = '$delete_id' AND building_id = \'$b_id\'";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['success_msg'] = "Provider deleted successfully!";
        header("Location: category.php?id=" . $category_id);
        exit();
    } else {
        $_SESSION['error_msg'] = "Error deleting provider.";
        header("Location: category.php?id=" . $category_id);
        exit();
    }
}

// Fetch Category Details
$cat_query = mysqli_query($conn, "SELECT * FROM service_categories WHERE id = '$category_id'");
$category_info = mysqli_fetch_assoc($cat_query);

if (!$category_info) {
    header("Location: index.php");
    exit();
}

// Fetch active providers for THIS category
$providers_query = mysqli_query($conn, "SELECT sp.*, sc.category_name, sc.icon_name 
                                      FROM service_providers sp 
                                      LEFT JOIN service_categories sc ON sp.category_id = sc.id 
                                      WHERE sp.building_id = \'$b_id\' AND sp.is_active = 1 AND sp.category_id = '$category_id'
                                      ORDER BY sp.id DESC");
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
    <title><?= htmlspecialchars($category_info['category_name']) ?> | Nibash</title>
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
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Folder Details</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50 space-y-10">
                
                <div class="flex items-center">
                    <a href="index.php" class="flex items-center gap-2 text-sm font-black text-slate-500 hover:text-emerald-600 transition-colors">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Folders
                    </a>
                </div>

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

                <div>
                    <div class="flex flex-col md:flex-row items-start md:items-end justify-between gap-6 mb-8 pb-6 border-b border-slate-200">
                        <div class="flex items-center gap-4">
                            <h2 class="text-3xl md:text-4xl font-black text-slate-900 flex items-center gap-4 tracking-tight">
                                <span class="p-3 bg-emerald-100 border border-emerald-200 text-emerald-700 rounded-2xl shadow-sm">
                                    <i data-lucide="<?= htmlspecialchars($category_info['icon_name'] ?? 'folder') ?>" class="w-8 h-8"></i>
                                </span>
                                <?= htmlspecialchars($category_info['category_name']) ?>
                            </h2>
                            <span class="px-4 py-2 bg-slate-900 text-white font-black text-xs uppercase tracking-widest rounded-xl shadow-md hidden sm:block">
                                <?= mysqli_num_rows($providers_query) ?> Active
                            </span>
                        </div>

                        <div class="w-full md:w-auto flex flex-col sm:flex-row gap-3">
                            <div class="relative w-full sm:w-64">
                                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                <input type="text" id="searchInput" placeholder="Search by name..." 
                                    class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-700 shadow-sm">
                            </div>
                            
                            <div class="relative w-full sm:w-48">
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

                    <?php if (mysqli_num_rows($providers_query) > 0): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="providerGrid">
                            <?php while ($prov = mysqli_fetch_assoc($providers_query)): 
                                // Parse the time string specifically for the HTML data attribute so JS can sort it numerically
                                $scheduleParts = explode(' - ', $prov['availability_schedule']);
                                $startTimeStamp = strtotime($scheduleParts[0]);
                            ?>
                                <div class="provider-card hover-card block bg-white rounded-[1.5rem] border border-slate-200 overflow-hidden relative group shadow-sm"
                                     data-name="<?= strtolower(htmlspecialchars($prov['name'])) ?>"
                                     data-rating="<?= $prov['rating'] ?>"
                                     data-time="<?= $startTimeStamp ?>">
                                     
                                    <div class="absolute left-0 top-0 w-1 h-full bg-emerald-400"></div>
                                    <div class="p-6 flex flex-col items-center text-center relative">
                                        
                                        <div class="w-20 h-20 rounded-full overflow-hidden mb-4 border-4 border-slate-50 shadow-sm relative group-hover:scale-105 transition-transform bg-slate-100">
                                            <?php if (!empty($prov['image_path']) && $prov['image_path'] !== 'default_avatar.jpg'): ?>
                                                <img src="<?php echo BASE_URL; ?>assets/uploads/essentials/<?= htmlspecialchars($prov['image_path']) ?>" alt="Provider" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <div class="w-full h-full flex items-center justify-center text-slate-300">
                                                    <i data-lucide="user" class="w-8 h-8"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <h3 class="text-lg font-black text-slate-900 mb-1 group-hover:text-emerald-600 transition-colors"><?= htmlspecialchars($prov['name']) ?></h3>
                                        
                                        <span class="inline-flex items-center justify-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-700 text-[10px] font-black uppercase tracking-widest rounded-md mb-4 border border-emerald-100 w-full shadow-sm">
                                            <i data-lucide="star" class="w-3 h-3 fill-emerald-500"></i>
                                            <?= htmlspecialchars($prov['rating']) ?> / 5.0
                                        </span>
                                        
                                        <div class="w-full mt-2 pt-4 border-t border-slate-100">
                                            <div class="flex flex-col items-center gap-1.5">
                                                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i> Availability</span>
                                                <span class="text-xs font-bold text-slate-700 bg-slate-50 px-2 py-1 border border-slate-200 rounded-md"><?= htmlspecialchars($prov['availability_schedule']) ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="w-full mt-5 flex items-center justify-center gap-3">
                                            <a href="provider.php?id=<?= $prov['id'] ?>" class="flex-1 py-2.5 bg-emerald-50 text-emerald-600 font-bold text-xs rounded-xl hover:bg-emerald-100 transition-colors flex items-center justify-center gap-2">
                                                <i data-lucide="eye" class="w-4 h-4"></i> View
                                            </a>
                                            <?php if ($_SESSION['role_id'] == 1): ?>
                                            <form method="POST" action="" class="flex-1 m-0" onsubmit="return confirm('Are you sure you want to delete this provider?');">
                                                <input type="hidden" name="delete_provider_id" value="<?= $prov['id'] ?>">
                                                <button type="submit" name="delete_provider" class="w-full py-2.5 bg-red-50 text-red-600 font-bold text-xs rounded-xl hover:bg-red-100 transition-colors flex items-center justify-center gap-2">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i> Delete
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
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
                                <i data-lucide="folder-open" class="w-10 h-10"></i>
                            </div>
                            <h3 class="text-xl font-black text-slate-900 mb-1">Folder Empty</h3>
                            <p class="text-sm font-medium text-slate-500 max-w-sm">There are currently no active providers in this folder.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        // Client-side Filter & Sorting logic
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const sortSelect = document.getElementById('sortSelect');
            const providerGrid = document.getElementById('providerGrid');
            const noResultsMsg = document.getElementById('noResultsMsg');
            
            if(!searchInput || !providerGrid) return;
            
            const cards = Array.from(document.querySelectorAll('.provider-card'));

            function applyFilters() {
                const query = searchInput.value.toLowerCase();
                const sortBy = sortSelect.value;
                let visibleCount = 0;

                // 1. Filter Display
                cards.forEach(card => {
                    const name = card.getAttribute('data-name');
                    if (name.includes(query)) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });

                // Toggle Empty State
                if(visibleCount === 0) {
                    providerGrid.style.display = 'none';
                    noResultsMsg.style.display = 'flex';
                } else {
                    providerGrid.style.display = 'grid';
                    noResultsMsg.style.display = 'none';
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
                    // default 'recent' uses HTML source order, so we reset the value
                    return 0; 
                });

                // Assign the visual CSS order
                visibleCards.forEach((card, index) => {
                    card.style.order = sortBy === 'recent' ? 0 : index;
                });
            }

            searchInput.addEventListener('input', applyFilters);
            sortSelect.addEventListener('change', applyFilters);
        });
    </script>
    <script src="<?php echo BASE_URL; ?>js/owner_logic.js"></script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>
</body>
</html>