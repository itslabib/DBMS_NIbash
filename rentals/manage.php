<?php
session_start();
require_once '../includes/db_config.php';

// Protection
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

// Handle Delete Request
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    
    // Owners can only delete their own
    $check_stmt = mysqli_prepare($conn, "SELECT id, apt_id FROM rental_listings WHERE id = ? AND owner_id = ?");
    mysqli_stmt_bind_param($check_stmt, "ii", $delete_id, $_SESSION['user_id']);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $check_row = mysqli_fetch_assoc($check_result);
    if ($check_row) {
        $apt_id = (int) $check_row['apt_id'];
        mysqli_stmt_close($check_stmt);
        
        $del_img_stmt = mysqli_prepare($conn, "DELETE FROM rental_images WHERE listing_id = ?");
        mysqli_stmt_bind_param($del_img_stmt, "i", $delete_id);
        mysqli_stmt_execute($del_img_stmt);

        $del_assign_stmt = mysqli_prepare($conn, "DELETE FROM apartment_assignments WHERE apt_id = ?");
        if ($del_assign_stmt) {
            mysqli_stmt_bind_param($del_assign_stmt, "i", $apt_id);
            mysqli_stmt_execute($del_assign_stmt);
            mysqli_stmt_close($del_assign_stmt);
        }
        
        $del_stmt = mysqli_prepare($conn, "DELETE FROM rental_listings WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, "i", $delete_id);
        if (mysqli_stmt_execute($del_stmt)) {
            $del_apt_stmt = mysqli_prepare($conn, "DELETE FROM apartments WHERE id = ?");
            if ($del_apt_stmt) {
                mysqli_stmt_bind_param($del_apt_stmt, "i", $apt_id);
                mysqli_stmt_execute($del_apt_stmt);
                mysqli_stmt_close($del_apt_stmt);
            }
            $_SESSION['msg'] = "Rental post deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete rental post.";
        }
    } else {
        $_SESSION['error'] = "Unauthorized or rental not found.";
    }
    header("Location: manage.php");
    exit();
}

// Handle Update Request - No longer used as editing goes through post_rental.php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_rental') {
    $rental_id = intval($_POST['rental_id']);
    $apartment_name = trim($_POST['apartment_name']);
    $rent_amount = floatval($_POST['rent_amount']);
    $area = trim($_POST['area']);

    // Admin update bypass owner check
    // 1. Get apt_id from rental_listings
    $apt_stmt = mysqli_prepare($conn, "SELECT apt_id FROM rental_listings WHERE id = ?");
    mysqli_stmt_bind_param($apt_stmt, "i", $rental_id);
    mysqli_stmt_execute($apt_stmt);
    $apt_res = mysqli_stmt_get_result($apt_stmt);
    $apt_row = mysqli_fetch_assoc($apt_res);

    if ($apt_row) {
        $apt_id = $apt_row['apt_id'];
        
        // 2. Update buildings table
        $update_b = mysqli_prepare($conn, "UPDATE buildings SET building_name = ?, area = ? WHERE id = (SELECT building_id FROM apartments WHERE id = ? LIMIT 1)");
        mysqli_stmt_bind_param($update_b, "ssi", $apartment_name, $area, $apt_id);
        mysqli_stmt_execute($update_b);

        // 3. Update rental_listings table
        $update_rl = mysqli_prepare($conn, "UPDATE rental_listings SET rent_amount = ? WHERE id = ?");
        mysqli_stmt_bind_param($update_rl, "di", $rent_amount, $rental_id);
        
        if (mysqli_stmt_execute($update_rl)) {
            $_SESSION['msg'] = "Rental post updated successfully by Admin!";
        } else {
            $_SESSION['error'] = "Failed to update rental post.";
        }
    } else {
        $_SESSION['error'] = "Listing not found.";
    }
    header("Location: manage.php");
    exit();
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
    <title>Manage Rentals | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <?php if ($_SESSION['role_id'] == 1): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <?php else: ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <?php endif; ?>
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
    </style>
</head>
<body class="bg-[#f2fbf6] text-slate-800 tracking-tight flex overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php 
    $active_page = 'manage.php'; // Updated to match sidebar logic
    if ($_SESSION['role_id'] == 1) {
        include '../includes/owner_sidebar.php';
    } else {
        include '../includes/resident_sidebar.php';
    } 
    ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8 w-full" id="main-content">
        
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
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Rentals & Properties</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 mb-8 border-b border-slate-200 relative">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            Property Portfolio
                        </h1>
                        <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base">
                            <span class="p-1.5 bg-emerald-100 border border-emerald-200 rounded-lg"><i data-lucide="building-2" class="w-4 h-4 text-emerald-700"></i></span>
                            Manage, update, and monitor your listed assets.
                        </p>
                    </div>
                    <a href="<?php echo BASE_URL; ?>rentals/browse.php" class="w-full sm:w-auto px-6 py-3 bg-slate-900 hover:bg-emerald-600 text-white font-bold rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-emerald-500/40 flex items-center justify-center gap-2 group whitespace-nowrap">
                        <i data-lucide="layout-grid" class="w-4 h-4 group-hover:-translate-y-0.5 transition-transform"></i> View Marketplace
                    </a>
                </div>

                <?php if(isset($_SESSION['msg'])): ?>
                    <div class="mb-8 bg-emerald-50 border border-emerald-200 text-emerald-700 px-6 py-4 rounded-2xl font-bold flex gap-3 shadow-sm items-center">
                        <i data-lucide="check-circle" class="w-6 h-6 text-emerald-500"></i> 
                        <?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?>
                    </div>
                <?php endif; ?>
                <?php if(isset($_SESSION['error'])): ?>
                    <div class="mb-8 bg-rose-50 border border-rose-200 text-rose-700 px-6 py-4 rounded-2xl font-bold flex gap-3 shadow-sm items-center">
                        <i data-lucide="alert-circle" class="w-6 h-6 text-rose-500"></i> 
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php
                    $owner_id = $_SESSION['user_id'];
                    $query = "SELECT rl.id, b.building_name AS apartment_name, b.area, b.address, rl.description, rl.rent_amount, rl.created_at,
                                     rl.total_bedrooms, rl.washrooms, rl.balconies,
                                     (SELECT image_path FROM rental_images WHERE listing_id = rl.id LIMIT 1) as cover_image
                              FROM rental_listings rl 
                              JOIN apartments a ON rl.apt_id = a.id 
                              LEFT JOIN buildings b ON a.building_id = b.id
                              WHERE rl.owner_id = ? 
                              ORDER BY rl.created_at DESC";
                    $stmt = mysqli_prepare($conn, $query);
                    mysqli_stmt_bind_param($stmt, "i", $owner_id);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);

                    if (mysqli_num_rows($result) > 0) {
                        while($row = mysqli_fetch_assoc($result)):
                    ?>
                        <div class="hover-card bg-white rounded-3xl p-4 shadow-sm border border-slate-100 flex flex-col group transition-all duration-300 overflow-hidden relative">
                            <div class="w-full h-56 rounded-2xl overflow-hidden mb-5 relative bg-slate-100 shrink-0">
                                <?php if (!empty($row['cover_image'])): ?>
                                    <img src="<?php echo BASE_URL; ?><?= htmlspecialchars($row['cover_image']) ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                                <?php else: ?>
                                    <div class="w-full h-full flex flex-col items-center justify-center text-slate-300">
                                        <i data-lucide="image" class="w-10 h-10 mb-2"></i>
                                        <span class="text-xs font-bold uppercase tracking-widest">No Image</span>
                                    </div>
                                <?php endif; ?>
                                <div class="absolute top-3 right-3 bg-white/95 backdrop-blur-sm text-emerald-700 text-xs font-black px-3.5 py-1.5 rounded-full shadow-sm border border-emerald-100/50">
                                    ৳<?= number_format($row['rent_amount']) ?>
                                </div>
                            </div>
                            
                            <div class="flex-1 flex flex-col px-2 pb-2">
                                <h3 class="text-xl font-black text-slate-900 group-hover:text-emerald-600 transition-colors line-clamp-1 mb-2">
                                    <?= htmlspecialchars($row['apartment_name'] ?? 'Untitled Property') ?>
                                </h3>
                                <?php 
                                $location = !empty($row['area']) ? $row['area'] : (!empty($row['address']) ? $row['address'] : 'Location Not Specified');
                                ?>
                                <p class="text-xs text-slate-500 mb-4 font-bold flex items-center gap-1.5 bg-slate-50 w-fit max-w-full overflow-hidden px-2.5 py-1 rounded-md border border-slate-100 shadow-sm">
                                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-emerald-500 shrink-0"></i> <span class="truncate"><?= htmlspecialchars($location) ?></span>
                                </p>

                                <div class="flex flex-wrap items-center gap-3 text-xs font-bold text-slate-600 mb-4">
                                    <div class="flex items-center gap-1.5 bg-indigo-50/50 px-2 py-1 rounded-md border border-indigo-50"><i data-lucide="bed-double" class="w-4 h-4 text-indigo-400"></i> <?= $row['total_bedrooms'] ?: '0' ?> Beds</div>
                                    <div class="flex items-center gap-1.5 bg-blue-50/50 px-2 py-1 rounded-md border border-blue-50"><i data-lucide="bath" class="w-4 h-4 text-blue-400"></i> <?= $row['washrooms'] ?: '0' ?> Baths</div>
                                    <div class="flex items-center gap-1.5 bg-amber-50/50 px-2 py-1 rounded-md border border-amber-50"><i data-lucide="layout" class="w-4 h-4 text-amber-400"></i> <?= $row['balconies'] ?: '0' ?> Bal</div>
                                </div>
                                
                                <?php if (!empty(trim($row['description']))): ?>
                                <p class="text-sm text-slate-500 mb-6 line-clamp-2 leading-relaxed font-medium">
                                    <?= htmlspecialchars($row['description']) ?>
                                </p>
                                <?php else: ?>
                                <div class="mb-6"></div>
                                <?php endif; ?>
                                
                                <div class="flex gap-2 mt-auto">
                                    <a href="<?php echo BASE_URL; ?>rentals/post.php?edit_id=<?= $row['id'] ?>" class="flex-1 bg-slate-900 hover:bg-emerald-600 text-white py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all shadow-sm">
                                        <i data-lucide="edit" class="w-4 h-4"></i> Edit Listing
                                    </a>
                                    <a href="manage.php?delete_id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this listing?');" class="flex-none bg-rose-50 hover:bg-rose-500 text-rose-600 hover:text-white w-12 rounded-xl flex items-center justify-center transition-all shadow-sm" title="Delete Listing">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php 
                        endwhile;
                    } else {
                        echo "<div class=\"col-span-full text-center py-24 text-slate-500 bg-white rounded-[1.5rem] border-2 border-dashed border-slate-300 shadow-sm\">
                                <div class=\"w-20 h-20 bg-emerald-50 border border-emerald-100 rounded-full flex items-center justify-center mx-auto mb-5 text-emerald-400 shadow-sm\">
                                    <i data-lucide='inbox' class='w-10 h-10'></i>
                                </div>
                                <p class='font-black text-xl text-slate-900 mb-2'>No Assets Listed</p>
                                <p class='text-sm font-medium'>You haven't posted any properties to the marketplace yet.</p>
                              </div>";
                    }
                    ?>
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