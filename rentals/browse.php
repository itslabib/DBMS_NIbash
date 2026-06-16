<?php
session_start();
require_once '../includes/db_config.php';

$success_msg = '';
$error_msg = '';

if (isset($_SESSION['rental_success'])) {
    $success_msg = $_SESSION['rental_success'];
    unset($_SESSION['rental_success']);
}

// Handle Search / Filter
$search_area = $_GET['area'] ?? '';
$search_min_price = $_GET['min_price'] ?? '';
$search_max_price = $_GET['max_price'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'date_desc';
$filter_type = $_GET['rental_type'] ?? 'all';

$query = "
    SELECT
        rl.id,
        COALESCE(NULLIF(TRIM(b.building_name), ''), rl.custom_title) AS apartment_name,
        COALESCE(NULLIF(TRIM(b.address), ''), 'N/A') AS address,
        COALESCE(NULLIF(TRIM(b.area), ''), NULLIF(TRIM(b.address), ''), 'N/A') AS area,
        rl.description,
        rl.rent_amount,
        COALESCE(rl.total_bedrooms, 0) AS total_bedrooms,
        COALESCE(rl.washrooms, 0) AS washrooms,
        COALESCE(rl.balconies, 0) AS balconies,
        COALESCE(rl.floor_number, 0) AS floor_number,
        COALESCE(NULLIF(TRIM(up.full_name), ''), 'Verified User') AS owner_name,
        COALESCE(NULLIF(TRIM(up.phone), ''), '') AS contact_number,
        COALESCE(NULLIF(TRIM(u.email), ''), '') AS contact_email,
        rl.created_at,
        rl.is_verified,
        rl.rental_type,
        pd.vehicle_type,
        pd.parking_length,
        pd.parking_width,
        pd.measurement_unit,
        ri.image_path
    FROM rental_listings rl
    LEFT JOIN buildings b ON rl.building_id = b.id
    LEFT JOIN parking_details pd ON pd.listing_id = rl.id
    LEFT JOIN (
        SELECT listing_id, MAX(CASE WHEN image_category = 'cover' THEN image_path END) AS image_path
        FROM rental_images
        GROUP BY listing_id
    ) ri ON ri.listing_id = rl.id
    LEFT JOIN users u ON u.id = rl.owner_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE 1=1
";

$params = [];
$types = "";

if ($search_area != '') {
    $query .= " AND CONCAT_WS(' ', COALESCE(b.area, ''), COALESCE(b.address, ''), COALESCE(b.building_name, ''), COALESCE(rl.custom_title, '')) LIKE ?";
    $params[] = "%$search_area%";
    $types .= "s";
}
if ($search_min_price != '') {
    $query .= " AND rl.rent_amount >= ?";
    $params[] = $search_min_price;
    $types .= "d";
}
if ($search_max_price != '') {
    $query .= " AND rl.rent_amount <= ?";
    $params[] = $search_max_price;
    $types .= "d";
}
if ($filter_type == 'house') {
    $query .= " AND rl.rental_type = 'house'";
} elseif ($filter_type == 'parking') {
    $query .= " AND rl.rental_type = 'parking'";
}

if ($sort_by == 'price_asc') {
    $query .= " ORDER BY rl.rent_amount ASC";
} elseif ($sort_by == 'price_desc') {
    $query .= " ORDER BY rl.rent_amount DESC";
} else {
    $query .= " ORDER BY rl.created_at DESC";
}

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
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
    <title>Marketplace - Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .group-input:focus-within i { color: #10B981; }
    </style>
</head>
<body class="bg-[#F0FAF4] text-slate-800 font-sans antialiased min-h-screen flex flex-col">

    <!-- Premium Clear Glass Navbar -->
    <nav class="sticky top-0 z-50 bg-white/70 backdrop-blur-xl border-b border-emerald-100/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-20 items-center">
                <!-- Logo Section (Center of Attention) -->
                <a href="<?php echo BASE_URL; ?>index.php" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 bg-emerald-500 rounded-xl flex items-center justify-center transform group-hover:scale-105 transition-transform duration-300 shadow-md shadow-emerald-500/20">
                        <i data-lucide="home" class="w-5 h-5 text-white"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-black tracking-tight text-slate-900 leading-none"><?= htmlspecialchars($resident_building_name) ?></span>
                    </div>
                </a>

                <!-- Navigation Links -->
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-3 text-sm tracking-tight">
                        <?php if (isset($_SESSION['user_id'])): ?>
                                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                                        <a href="<?php echo BASE_URL; ?>owner/dashboard.php" class="flex items-center gap-2 text-slate-700 font-bold hover:text-emerald-600 transition-colors px-4 py-2 hover:bg-emerald-50 rounded-xl">
                                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                                        </a>
                                <?php else: ?>
                                        <a href="<?php echo BASE_URL; ?>resident/dashboard.php" class="flex items-center gap-2 text-slate-700 font-bold hover:text-emerald-600 transition-colors px-4 py-2 hover:bg-emerald-50 rounded-xl">
                                            <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Dashboard
                                        </a>
                                <?php endif; ?>
                                <a href="<?php echo BASE_URL; ?>logout.php" class="flex items-center gap-2 bg-rose-50 text-rose-600 border border-rose-100 hover:bg-rose-500 hover:text-white px-4 py-2 rounded-xl font-bold transition-all shadow-sm ml-2">
                                    <i data-lucide="log-out" class="w-4 h-4"></i> Logout
                                </a>
                        <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>login.php" class="flex items-center gap-2 text-slate-700 font-bold hover:text-emerald-600 transition-colors px-4 py-2 hover:bg-emerald-50 rounded-xl mr-2">
                                    <i data-lucide="log-in" class="w-4 h-4"></i> Sign In
                                </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="py-10 flex-grow max-w-[1400px] mx-auto px-4 sm:px-6 w-full">

        <?php if ($success_msg): ?>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        if(typeof showCustomPopup === "function") showCustomPopup("<?= addslashes($success_msg) ?>", "success");
                    });
                </script>
        <?php endif; ?>
        <?php if ($error_msg): ?>
                <script>
                    document.addEventListener("DOMContentLoaded", function() {
                        if(typeof showCustomPopup === "function") showCustomPopup("<?= addslashes($error_msg) ?>", "error");
                    });
                </script>
        <?php endif; ?>

        <!-- Floating Main Area -->
        <div class="flex flex-col xl:flex-row gap-8">
            
            <!-- Listings Area -->
            <div class="bg-white rounded-[32px] shadow-[0_20px_60px_-15px_rgba(0,0,0,0.05)] p-6 md:p-10 border border-slate-100 flex flex-col w-full xl:w-3/4">
                <div class="mb-8">
                    <h1 class="text-4xl font-black text-slate-950 tracking-tight">Apartment Rental Hub</h1>
                    <p class="text-slate-500 text-base mt-2">Explore verified apartment and flat rentals across your preferred areas.</p>
                </div>

                <!-- Command Strip (Sleek Filters) -->
                <form method="GET" class="bg-slate-50 p-3 rounded-2xl border border-slate-200 mb-10 flex flex-col md:flex-row gap-3 items-center">
                    <div class="flex-1 w-full grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div class="relative group-input flex items-center bg-white border border-slate-200 rounded-xl px-4 py-2 hover:border-emerald-300 focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20 transition-all">
                            <i data-lucide="map-pin" class="w-4 h-4 text-slate-400 transition-colors mr-3"></i>
                            <input type="text" name="area" placeholder="Location or Area" value="<?= htmlspecialchars($search_area) ?>" class="w-full bg-transparent border-none p-0 text-sm focus:ring-0 text-slate-700 placeholder-slate-400 font-medium outline-none">
                        </div>
                        <div class="relative group-input flex items-center bg-white border border-slate-200 rounded-xl px-4 py-2 hover:border-emerald-300 focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20 transition-all">
                            <i data-lucide="bdt" class="w-4 h-4 text-slate-400 transition-colors mr-3"></i>
                            <input type="number" name="min_price" placeholder="Min Budget" value="<?= htmlspecialchars($search_min_price) ?>" class="w-full bg-transparent border-none p-0 text-sm focus:ring-0 text-slate-700 placeholder-slate-400 font-medium outline-none">
                        </div>
                        <div class="relative group-input flex items-center bg-white border border-slate-200 rounded-xl px-4 py-2 hover:border-emerald-300 focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20 transition-all">
                            <i data-lucide="bdt" class="w-4 h-4 text-slate-400 transition-colors mr-3"></i>
                            <input type="number" name="max_price" placeholder="Max Budget" value="<?= htmlspecialchars($search_max_price) ?>" class="w-full bg-transparent border-none p-0 text-sm focus:ring-0 text-slate-700 placeholder-slate-400 font-medium outline-none">
                        </div>
                    </div>
                    
                    <div class="w-full md:w-auto flex gap-3 flex-wrap md:flex-nowrap">
                        <div class="relative bg-white border border-slate-200 rounded-xl px-4 py-2 flex items-center min-w-[130px]">
                            <select name="rental_type" class="w-full bg-transparent border-none p-0 text-sm font-semibold text-slate-700 focus:ring-0 cursor-pointer outline-none appearance-none pr-6" onchange="this.form.submit()">
                                <option value="all" <?= $filter_type == 'all' ? 'selected' : '' ?>>All Types</option>
                                <option value="house" <?= $filter_type == 'house' ? 'selected' : '' ?>>Houses</option>
                                <option value="parking" <?= $filter_type == 'parking' ? 'selected' : '' ?>>Parking</option>
                            </select>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 absolute right-4 pointer-events-none"></i>
                        </div>
                        <div class="relative bg-white border border-slate-200 rounded-xl px-4 py-2 flex items-center min-w-[140px]">
                            <select name="sort_by" class="w-full bg-transparent border-none p-0 text-sm font-semibold text-slate-700 focus:ring-0 cursor-pointer outline-none appearance-none pr-6" onchange="this.form.submit()">
                                <option value="date_desc" <?= $sort_by == 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                                <option value="price_asc" <?= $sort_by == 'price_asc' ? 'selected' : '' ?>>Price: Low-High</option>
                                <option value="price_desc" <?= $sort_by == 'price_desc' ? 'selected' : '' ?>>Price: High-Low</option>
                            </select>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 absolute right-4 pointer-events-none"></i>
                        </div>
                        <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm transition-colors shadow-sm shadow-emerald-500/20 flex-shrink-0 flex items-center justify-center">
                            <i data-lucide="search" class="w-4 h-4 md:mr-2"></i> <span class="hidden md:inline">Search</span>
                        </button>
                    </div>
                </form>

                <!-- Cards -->
                <div class="space-y-6">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                            <?php while ($rental = mysqli_fetch_assoc($result)): ?>
                                    <div class="group bg-white rounded-3xl p-4 border border-slate-100 flex flex-col md:flex-row gap-8 shadow-sm hover:shadow-xl hover:border-slate-200 transition-all duration-300">
                                        <!-- Image Section -->
                                        <div class="w-full md:w-80 h-64 rounded-[20px] overflow-hidden relative flex-shrink-0 bg-slate-100">
                                            <?php if ($rental['image_path'] && file_exists(__DIR__ . '/../' . $rental['image_path'])): ?>
                                                    <img src="<?= BASE_URL . $rental['image_path'] ?>" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-700 ease-out">
                                            <?php elseif ($rental['image_path'] && file_exists(__DIR__ . '/' . $rental['image_path'])): ?>
                                                    <img src="<?= BASE_URL . 'rentals/' . $rental['image_path'] ?>" class="w-full h-full object-cover transform group-hover:scale-105 transition-transform duration-700 ease-out">
                                            <?php else: ?>
                                                    <div class="w-full h-full flex flex-col items-center justify-center text-slate-300">
                                                        <i data-lucide="image" class="w-12 h-12 mb-2 opacity-50"></i>
                                                        <span class="text-sm font-semibold">No Preview Image</span>
                                                    </div>
                                            <?php endif; ?>
                                            
                                            <!-- Floating Pills -->
                                            <div class="absolute top-4 left-4 bg-white/70 backdrop-blur-md text-emerald-600 px-3 py-1.5 rounded-xl font-black text-sm shadow-sm flex items-center gap-1.5 border border-white/40">
                                                <i data-lucide="bdt" class="w-4 h-4"></i>
                                                <?= number_format($rental['rent_amount']) ?> <span class="text-xs text-slate-600 font-semibold mix-blend-multiply">/mo</span>
                                            </div>
                                            <div class="absolute bottom-4 left-4 bg-slate-900/60 backdrop-blur-md text-white px-3 py-1.5 rounded-xl font-semibold text-xs shadow-sm flex items-center gap-1.5 border border-white/10">
                                                <i data-lucide="map-pin" class="w-3.5 h-3.5 text-emerald-400"></i>
                                                <?= htmlspecialchars($rental['area']) ?>
                                            </div>
                                        </div>

                                        <!-- Content Section -->
                                        <div class="flex-1 flex flex-col py-2 justify-between">
                                            <div>
                                                <div class="flex justify-between items-start mb-3">
                                                    <h3 class="text-2xl font-bold text-slate-950 leading-tight group-hover:text-emerald-600 transition-colors line-clamp-2 pr-4"><?= htmlspecialchars($rental['apartment_name']) ?></h3>
                                                    <span class="text-xs text-slate-500 font-medium bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-100 whitespace-nowrap">
                                                        <?= date('M d, Y', strtotime($rental['created_at'])) ?>
                                                    </span>
                                                </div>
                                                <p class="text-sm text-slate-500 mb-6 line-clamp-1 flex items-center">
                                                    <i data-lucide="map" class="w-4 h-4 mr-1.5 text-slate-400"></i> <?= htmlspecialchars($rental['address']) ?>
                                                </p>

                                                <!-- Specs -->
                                                <div class="flex flex-wrap gap-3 mb-6">
                                                    <?php if ($rental['rental_type'] == 'parking'): ?>
                                                        <div class="flex items-center gap-2 bg-indigo-50 px-3.5 py-2 rounded-xl text-indigo-700 border border-indigo-100/50">
                                                            <i data-lucide="<?= $rental['vehicle_type'] == 'motorbike' ? 'bike' : 'car' ?>" class="w-4 h-4 text-indigo-500"></i>
                                                            <span class="text-sm font-semibold capitalize"><?= $rental['vehicle_type'] ?></span>
                                                        </div>
                                                        <div class="flex items-center gap-2 bg-slate-50 px-3.5 py-2 rounded-xl text-slate-700">
                                                            <i data-lucide="maximize" class="w-4 h-4 text-indigo-500"></i>
                                                            <span class="text-sm font-semibold"><?= (float)$rental['parking_length'] ?> &times; <?= (float)$rental['parking_width'] ?> <?= $rental['measurement_unit'] ?></span>
                                                        </div>
                                                        <?php if ($rental['floor_number'] != 0): ?>
                                                        <div class="flex items-center gap-2 bg-slate-50 px-3.5 py-2 rounded-xl text-slate-700">
                                                            <i data-lucide="layers" class="w-4 h-4 text-indigo-500"></i>
                                                            <span class="text-sm font-semibold">Floor <?= $rental['floor_number'] ?></span>
                                                        </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="flex items-center gap-2 bg-slate-50 px-3.5 py-2 rounded-xl text-slate-700">
                                                            <i data-lucide="bed-double" class="w-4 h-4 text-emerald-500"></i>
                                                            <span class="text-sm font-semibold"><?= $rental['total_bedrooms'] ?> Beds</span>
                                                        </div>
                                                        <div class="flex items-center gap-2 bg-slate-50 px-3.5 py-2 rounded-xl text-slate-700">
                                                            <i data-lucide="bath" class="w-4 h-4 text-emerald-500"></i>
                                                            <span class="text-sm font-semibold"><?= $rental['washrooms'] ?> Baths</span>
                                                        </div>
                                                        <div class="flex items-center gap-2 bg-slate-50 px-3.5 py-2 rounded-xl text-slate-700">
                                                            <i data-lucide="layers" class="w-4 h-4 text-emerald-500"></i>
                                                            <span class="text-sm font-semibold">
                                                                <?php 
                                                                    $f = $rental['floor_number'];
                                                                    $v = $f % 100;
                                                                    $sfx = ($v >= 11 && $v <= 13) ? 'th' : (['th','st','nd','rd','th','th','th','th','th','th'][$f % 10]);
                                                                    echo $f . $sfx . ' Floor';
                                                                ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="pt-5 border-t border-slate-100 flex items-center justify-between mt-auto">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-black text-xs uppercase border border-emerald-200">
                                                        <?= substr(htmlspecialchars($rental['owner_name']), 0, 2) ?>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-bold text-slate-900 leading-none mb-0.5"><?= htmlspecialchars($rental['owner_name']) ?></p>
                                                        <p class="text-xs text-slate-500 font-medium">Verified User</p>
                                                    </div>
                                                </div>
                                                <a href="<?php echo BASE_URL; ?>rentals/details.php?id=<?= $rental['id'] ?>" class="border-2 border-emerald-500 text-emerald-600 hover:bg-emerald-500 hover:text-white px-6 py-2.5 rounded-xl font-bold text-sm transition-all shadow-sm flex items-center gap-2">
                                                    View Space <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                            <?php endwhile; ?>
                    <?php else: ?>
                            <!-- Empty State -->
                            <div class="text-center py-24 bg-slate-50 rounded-3xl border border-dashed border-slate-200 flex flex-col items-center justify-center">
                                <div class="w-16 h-16 bg-white shadow-sm rounded-2xl flex items-center justify-center mb-5 border border-slate-100">
                                    <i data-lucide="search-x" class="w-8 h-8 text-slate-400"></i>
                                </div>
                                <h3 class="text-xl font-bold text-slate-900 mb-2">No matching properties found</h3>
                                <p class="text-sm text-slate-500 mb-6 max-w-sm">We couldn't find any spaces matching your criteria. Try adjusting your filters or changing the location.</p>
                                <a href="<?php echo BASE_URL; ?>rentals/browse.php" class="bg-white border border-slate-200 hover:border-emerald-500 text-slate-700 hover:text-emerald-600 px-6 py-2.5 rounded-xl font-bold transition shadow-sm inline-flex items-center gap-2">
                                    <i data-lucide="refresh-cw" class="w-4 h-4"></i> Clear Filters
                                </a>
                            </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- CTA Side (Floating Setup Card) -->
            <div class="w-full xl:w-1/4">
                <div class="bg-white/95 backdrop-blur-2xl rounded-3xl p-8 border border-emerald-100 shadow-2xl shadow-emerald-900/10 sticky top-28 flex flex-col justify-between min-h-[560px] overflow-hidden relative">
                    <!-- Background Icon -->
                    <i data-lucide="building-2" class="absolute -right-8 -bottom-8 w-64 h-64 text-emerald-500 opacity-5 pointer-events-none transform -rotate-6"></i>
                    
                    <div class="relative z-10 flex flex-col items-start flex-grow">
                        <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center mb-6 border border-emerald-100 shadow-inner">
                            <i data-lucide="plus-circle" class="w-7 h-7 text-emerald-600"></i>
                        </div>
                        <h2 class="text-2xl font-black text-emerald-950 mb-2 tracking-tight">Have a Space?</h2>
                        <p class="text-slate-500 text-sm leading-relaxed mb-8 font-medium">
                            Ready to find a tenant? List your property in our verified community and connect with reliable renters faster.
                        </p>

                        <!-- Feature List -->
                        <div class="space-y-6 mb-8 w-full">
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0 border border-emerald-100/50 shadow-sm">
                                    <i data-lucide="users" class="w-5 h-5 text-emerald-600"></i>
                                </div>
                                <div class="mt-0.5">
                                    <h4 class="text-[15px] font-bold text-slate-900">Verified Reach</h4>
                                    <p class="text-[13px] text-slate-500 mt-1 leading-snug">Connect with our exclusive community of pre-vetted, high-quality tenants.</p>
                                </div>
                            </div>
                            
                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0 border border-emerald-100/50 shadow-sm">
                                    <i data-lucide="zap" class="w-5 h-5 text-emerald-600"></i>
                                </div>
                                <div class="mt-0.5">
                                    <h4 class="text-[15px] font-bold text-slate-900">Rapid Deployment</h4>
                                    <p class="text-[13px] text-slate-500 mt-1 leading-snug">Your rental listing goes live instantly after a secure administrative validation.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-4">
                                <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center flex-shrink-0 border border-emerald-100/50 shadow-sm">
                                    <i data-lucide="bar-chart-2" class="w-5 h-5 text-emerald-600"></i>
                                </div>
                                <div class="mt-0.5">
                                    <h4 class="text-[15px] font-bold text-slate-900">Advanced Insights</h4>
                                    <p class="text-[13px] text-slate-500 mt-1 leading-snug">Monitor views and manage tenant inquiries directly from your professional dashboard.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="relative z-10 w-full pt-4 mt-auto border-t border-slate-100/50">
                        <?php if (!isset($_SESSION['user_id'])): ?>
                                <a href="<?php echo BASE_URL; ?>login.php?error=unauthorized&redirect=rentals/post.php" class="bg-emerald-600 text-white font-bold text-[15px] px-6 py-3.5 rounded-xl w-full flex justify-center items-center gap-2 hover:bg-emerald-700 hover:shadow-lg transition-all shadow-md shadow-emerald-600/20">
                                    <i data-lucide="lock" class="w-4 h-4"></i> Sign In to Post
                                </a>
                        <?php else: ?>
                                <a href="<?php echo BASE_URL; ?>rentals/post.php" class="bg-emerald-600 text-white font-bold text-[15px] px-6 py-3.5 rounded-xl w-full flex justify-center items-center gap-2 hover:bg-emerald-700 hover:shadow-lg transition-all shadow-md shadow-emerald-500/20">
                                    <i data-lucide="plus-circle" class="w-5 h-5"></i> Post Your Rental
                                </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
<script src="<?php echo BASE_URL; ?>js/toast.js"></script>
</body>
</html>
