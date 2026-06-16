<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get Provider details
$query = mysqli_query($conn, "SELECT sp.*, sc.category_name, sc.icon_name FROM service_providers sp 
                              LEFT JOIN service_categories sc ON sp.category_id = sc.id 
                              WHERE sp.user_id = '$user_id' LIMIT 1");
$provider = mysqli_fetch_assoc($query);

if (!$provider) {
    echo "Provider profile not found.";
    exit();
}

$provider_id = $provider['id'];
$is_subscribed = (bool)$provider['is_subscribed'];

// --- GET METRICS ---
// 1. Total Requests/Bookings
$req_query = mysqli_query($conn, "SELECT COUNT(id) as total_req, 
                                  COUNT(id) as pending_req,
                                  SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed_req
                                  FROM provider_bookings WHERE provider_id = '$provider_id'");
$req_stats = mysqli_fetch_assoc($req_query);

// 2. Earnings logic (simplified for mockup based on completed bookings)
// Assume default pricing is flat rate per job
$price_per_job = floatval($provider['default_pricing'] ?? 500);
$total_earnings = $req_stats['completed_req'] * $price_per_job;
$monthly_earnings = $total_earnings * 0.4; // mock calculation for this month

// 3. Reviews & Rating
$reviews = [];
$avg_rating = 0;
$total_reviews = 0;

$has_reviews_table = false;
$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'provider_reviews'");
if (mysqli_num_rows($check_table) > 0) {
    $has_reviews_table = true;
    $rev_q = mysqli_query($conn, "
        SELECT pr.*, p.full_name as resident_name 
        FROM provider_reviews pr
        JOIN users u ON pr.resident_id = u.id
        LEFT JOIN user_profiles p ON u.id = p.user_id
        WHERE pr.provider_id = '$provider_id'
        ORDER BY pr.created_at DESC
        LIMIT 4
    ");
    while ($r = mysqli_fetch_assoc($rev_q)) {
        $reviews[] = $r;
    }
    
    $rating_q = mysqli_query($conn, "SELECT AVG(rating) as avg_r, COUNT(id) as c FROM provider_reviews WHERE provider_id = '$provider_id'");
    $rating_res = mysqli_fetch_assoc($rating_q);
    $avg_rating = round($rating_res['avg_r'], 1) ?? 0;
    $total_reviews = $rating_res['c'];
}

// 4. Upcoming Schedule
$bookings = [];
$check_bookings = mysqli_query($conn, "SHOW TABLES LIKE 'provider_bookings'");
if (mysqli_num_rows($check_bookings) > 0) {
    $bq = mysqli_query($conn, "
        SELECT pb.*, 
               up.full_name as resident_name, 
               up.phone as resident_phone,
               b.building_name, 
               b.building_number,
               a.apt_number as apartment_number
        FROM provider_bookings pb
        JOIN users u ON pb.resident_id = u.id
        LEFT JOIN user_profiles up ON u.id = up.user_id
        LEFT JOIN apartment_assignments aa ON u.id = aa.user_id AND aa.is_active = 1
        LEFT JOIN apartments a ON aa.apt_id = a.id
        LEFT JOIN buildings b ON a.building_id = b.id
        WHERE pb.provider_id = '$provider_id' 
        AND pb.booking_date >= CURRENT_DATE 
        GROUP BY pb.id
        ORDER BY pb.booking_date ASC, pb.time_slot ASC 
        LIMIT 5
    ");
    while($row = mysqli_fetch_assoc($bq)) {
        $bookings[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Portal | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="text-slate-800 min-h-screen flex flex-col">

    <!-- Formal Top Navigation Bar -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div class="flex items-center gap-8">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 bg-blue-600 text-white rounded-lg flex items-center justify-center font-bold">
                        P
                    </div>
                    <h1 class="text-lg font-bold text-slate-900 tracking-tight">Provider Portal</h1>
                </div>
                
                <nav class="hidden md:flex items-center gap-6">
                    <a href="<?php echo BASE_URL; ?>essentials/dashboard.php" class="text-sm font-semibold text-blue-600 border-b-2 border-blue-600 py-[19px]">Overview</a>
                    <a href="#" class="text-sm font-semibold text-slate-500 hover:text-slate-900 py-[19px] transition-colors">Schedule</a>
                    <a href="<?= BASE_URL ?>messages/index.php" class="text-sm font-semibold text-slate-500 hover:text-slate-900 py-[19px] transition-colors">Messages</a>
                    <a href="profile.php" class="text-sm font-semibold text-slate-500 hover:text-slate-900 py-[19px] transition-colors">Profile</a>
                </nav>
            </div>
            
            <div class="flex items-center gap-5">
                <?php if ($is_subscribed): ?>
                    <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-amber-50 text-amber-700 text-xs font-bold border border-amber-200">
                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> PRO Active
                    </span>
                <?php endif; ?>
                <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>
                <div class="flex items-center gap-3">
                    <span class="text-sm font-semibold text-slate-700"><?= htmlspecialchars(explode(' ', trim($provider['name']))[0]) ?></span>
                    <a href="<?php echo BASE_URL; ?>logout.php" class="text-slate-400 hover:text-rose-600 transition-colors" title="Sign Out">
                        <i data-lucide="log-out" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:py-10">
        
        <!-- Header Section -->
        <div class="mb-8 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">Dashboard</h2>
                <p class="text-slate-500 mt-1 font-medium">Your daily performance summary and upcoming tasks.</p>
            </div>
            <?php if (!$is_subscribed): ?>
                <a href="subscribe.php" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white text-sm font-semibold rounded-lg transition-colors shadow-sm">
                    <i data-lucide="arrow-up-circle" class="w-4 h-4"></i> Upgrade to PRO
                </a>
            <?php endif; ?>
        </div>

        <!-- Formal Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            
            <!-- Pending Jobs -->
            <div class="bg-white rounded-xl p-6 shadow-md border-l-[6px] border-l-blue-500 border-y border-r border-slate-200 flex items-center justify-between hover:shadow-lg transition-shadow cursor-pointer">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">New Requests</p>
                    <div class="flex items-end gap-2">
                        <h3 class="text-3xl font-bold text-slate-900 leading-none"><?= $req_stats['pending_req'] ?></h3>
                        <?php if($req_stats['pending_req'] > 0): ?>
                            <span class="w-2 h-2 rounded-full bg-blue-500 mb-1.5 animate-pulse"></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
                    <i data-lucide="inbox" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Completed Jobs -->
            <div class="bg-white rounded-xl p-6 shadow-md border-l-[6px] border-l-emerald-500 border-y border-r border-slate-200 flex items-center justify-between hover:shadow-lg transition-shadow cursor-pointer">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Completed Jobs</p>
                    <h3 class="text-3xl font-bold text-slate-900 leading-none"><?= number_format($req_stats['completed_req']) ?></h3>
                </div>
                <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100">
                    <i data-lucide="check-square" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Rating -->
            <div class="bg-white rounded-xl p-6 shadow-md border-l-[6px] border-l-amber-500 border-y border-r border-slate-200 flex items-center justify-between hover:shadow-lg transition-shadow cursor-pointer">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Overall Rating</p>
                    <div class="flex items-end gap-1">
                        <h3 class="text-3xl font-bold text-slate-900 leading-none"><?= $avg_rating ?></h3>
                        <span class="text-sm font-semibold text-slate-400 mb-0.5">/ 5.0</span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center border border-amber-100">
                    <i data-lucide="star" class="w-6 h-6 fill-amber-500"></i>
                </div>
            </div>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Upcoming Schedule (Formal List) -->
            <div class="bg-white border border-slate-300 rounded-[1.5rem] shadow-[0_8px_30px_rgb(0,0,0,0.08)] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900">Upcoming Schedule</h3>
                    <button class="text-sm font-semibold text-blue-600 hover:text-blue-800 transition-colors">View All</button>
                </div>
                
                <div class="p-0 flex-1">
                    <?php if (empty($bookings)): ?>
                        <div class="p-12 text-center">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-100">
                                <i data-lucide="calendar" class="w-8 h-8 text-slate-300"></i>
                            </div>
                            <h4 class="text-base font-semibold text-slate-900 mb-1">No upcoming appointments</h4>
                            <p class="text-sm text-slate-500">Your schedule is clear.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($bookings as $b): ?>
                                <div class="px-6 py-4 flex flex-col gap-2 hover:bg-slate-50 transition-colors cursor-pointer" x-data="{ expanded: false }" @click="expanded = !expanded">
                                    <div class="flex items-center gap-4">
                                        <div class="w-12 h-12 rounded border border-slate-200 bg-white flex flex-col items-center justify-center shrink-0 shadow-sm">
                                            <span class="text-[10px] font-bold uppercase text-slate-500 mb-0.5"><?= date('M', strtotime($b['booking_date'])) ?></span>
                                            <span class="text-lg font-bold text-slate-900 leading-none"><?= date('d', strtotime($b['booking_date'])) ?></span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-sm font-bold text-slate-900 truncate mb-0.5"><?= htmlspecialchars($b['resident_name'] ?? 'Resident') ?></h4>
                                            <p class="text-xs font-medium text-slate-500 flex items-center gap-1.5">
                                                <i data-lucide="clock" class="w-3.5 h-3.5"></i> <?= date('g:i A', strtotime($b['time_slot'])) ?>
                                            </p>
                                        </div>
                                        <div>
                                            <?php if (in_array($b['status'], ['pending', 'Booked'])): ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">Pending</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">Confirmed</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div x-show="expanded" x-transition class="mt-4 pt-4 border-t border-slate-100 flex flex-col gap-3">
                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Location</span>
                                                <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                                                    <i data-lucide="map-pin" class="w-4 h-4 text-emerald-500"></i>
                                                    <span><?= htmlspecialchars($b['building_name'] ?? $b['building_number'] ?? 'N/A') ?>, Apt <?= htmlspecialchars($b['apartment_number'] ?? 'N/A') ?></span>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Contact</span>
                                                <div class="flex items-center gap-1.5 text-sm font-semibold text-slate-700">
                                                    <i data-lucide="phone" class="w-4 h-4 text-blue-500"></i>
                                                    <span><?= htmlspecialchars($b['resident_phone'] ?? 'N/A') ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex gap-2 mt-1">
                                            <a href="tel:<?= htmlspecialchars($b['resident_phone'] ?? '') ?>" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-colors flex items-center justify-center gap-1.5 border border-slate-200" @click.stop>
                                                <i data-lucide="phone-call" class="w-3.5 h-3.5"></i> Call
                                            </a>
                                            <a href="../messages/index.php?user_id=<?= $b['resident_id'] ?>" class="px-4 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-bold rounded-lg transition-colors flex items-center justify-center gap-1.5 border border-blue-200" @click.stop>
                                                <i data-lucide="message-square" class="w-3.5 h-3.5"></i> Message
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Customer Reviews (Formal List) -->
            <div class="bg-white border border-slate-300 rounded-[1.5rem] shadow-[0_8px_30px_rgb(0,0,0,0.08)] overflow-hidden flex flex-col">
                <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50 flex justify-between items-center">
                    <h3 class="text-base font-bold text-slate-900">Recent Reviews</h3>
                </div>
                
                <div class="p-0 flex-1 overflow-y-auto max-h-[400px]">
                    <?php if (empty($reviews)): ?>
                        <div class="p-12 text-center">
                            <div class="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-100">
                                <i data-lucide="message-square" class="w-8 h-8 text-slate-300"></i>
                            </div>
                            <h4 class="text-base font-semibold text-slate-900 mb-1">No reviews yet</h4>
                            <p class="text-sm text-slate-500">Complete jobs to receive ratings.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-100">
                            <?php foreach ($reviews as $rev): ?>
                                <div class="px-6 py-5 hover:bg-slate-50 transition-colors">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h4 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($rev['resident_name'] ?? 'Customer') ?></h4>
                                            <span class="text-xs text-slate-400 font-medium"><?= date('M j, Y', strtotime($rev['created_at'])) ?></span>
                                        </div>
                                        <div class="flex items-center gap-0.5">
                                            <?php for ($i = 0; $i < 5; $i++): ?>
                                                <i data-lucide="star" class="w-4 h-4 <?= $i < $rev['rating'] ? 'fill-amber-400 text-amber-400' : 'text-slate-200 fill-slate-100' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="text-sm text-slate-600 font-medium">"<?= htmlspecialchars($rev['review_text'] ?? '') ?>"</p>
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
    </script>
</body>
</html>