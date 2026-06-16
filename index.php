<?php
session_start();
require_once 'includes/db_config.php';

// Fetch 2 most recent listings
$recent_rentals = [];
$hasColumn = static function ($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $check && mysqli_num_rows($check) > 0;
};

$hasTitle = $hasColumn($conn, 'rental_listings', 'title');
$hasAddress = $hasColumn($conn, 'rental_listings', 'address');
$hasVerified = $hasColumn($conn, 'rental_listings', 'is_verified');

$apartmentNameExpr = $hasTitle
    ? "COALESCE(NULLIF(TRIM(rl.title), ''), COALESCE(NULLIF(TRIM(b.building_name), ''), CONCAT('Apartment ', COALESCE(a.apt_number, rl.id))))"
    : "COALESCE(NULLIF(TRIM(b.building_name), ''), CONCAT('Apartment ', a.apt_number))";

$addressExpr = $hasAddress
    ? "COALESCE(NULLIF(TRIM(rl.address), ''), COALESCE(NULLIF(TRIM(b.address), ''), CONCAT('Apt ', COALESCE(a.apt_number, 'N/A'))))"
    : "COALESCE(NULLIF(TRIM(b.address), ''), CONCAT('Apt ', COALESCE(a.apt_number, 'N/A')))";

$verifiedWhere = ""; // Show all listings regardless of verification status for now

$rentals_query = "
    SELECT
        rl.id,
        $apartmentNameExpr AS apartment_name,
        $addressExpr AS address,
        rl.description,
        rl.rent_amount,
        COALESCE(b.area, 'N/A') AS area,
        COALESCE(rl.total_bedrooms, 0) AS total_bedrooms,
        0 AS living_rooms,
        COALESCE(rl.balconies, 0) AS balconies,
        COALESCE(rl.washrooms, 0) AS washrooms,
        COALESCE(a.floor_number, 0) AS floor_number,
        COALESCE(NULLIF(TRIM(up.phone), ''), '') AS contact_number,
        COALESCE(NULLIF(TRIM(u.email), ''), '') AS contact_email,
        COALESCE(NULLIF(TRIM(up.full_name), ''), 'Verified Owner') AS owner_name,
        rl.created_at,
        ri.image_path,
        rl.rental_type,
        pd.vehicle_type,
        pd.parking_length,
        pd.parking_width,
        pd.measurement_unit
    FROM rental_listings rl
    LEFT JOIN apartments a ON a.id = rl.apt_id
    LEFT JOIN buildings b ON a.building_id = b.id
    LEFT JOIN parking_details pd ON pd.listing_id = rl.id
    LEFT JOIN (
        SELECT listing_id, MAX(CASE WHEN image_category = 'cover' THEN image_path END) AS image_path
        FROM rental_images
        GROUP BY listing_id
    ) ri ON ri.listing_id = rl.id
    LEFT JOIN apartment_assignments aa ON aa.apt_id = rl.apt_id AND aa.role = 'owner' AND aa.is_active = 1
    LEFT JOIN users u ON u.id = COALESCE(aa.user_id, rl.owner_id)
    LEFT JOIN user_profiles up ON up.user_id = u.id
    $verifiedWhere
    ORDER BY rl.created_at DESC
    LIMIT 2
";
$rentals_result = mysqli_query($conn, $rentals_query);
if ($rentals_result) {
    while ($row = mysqli_fetch_assoc($rentals_result)) {
        $recent_rentals[] = $row;
    }
}

// Calculate statistics for landing page
$total_buildings = 0;
$total_users = 0;
$total_listings = 0;
$secure_logs = 0;

$stats_res = mysqli_query($conn, "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role_id = 1) as b_count,
        (SELECT COUNT(*) FROM users WHERE role_id IN (1, 2)) as u_count,
        (SELECT COUNT(*) FROM rental_listings) as l_count,
        (SELECT COUNT(*) FROM entry_logs) as s_count
    ");
if ($stats_res && $row = mysqli_fetch_assoc($stats_res)) {
    $total_buildings = (int) $row['b_count'];
    $total_users = (int) $row['u_count'];
    $total_listings = (int) $row['l_count'];
    $secure_logs = (int) $row['s_count'];
}

$unknown_log_file = __DIR__ . '/assets/uploads/scans/unknown/log.json';
if (file_exists($unknown_log_file)) {
    $decoded = json_decode(file_get_contents($unknown_log_file), true);
    if (is_array($decoded)) {
        $secure_logs += count($decoded);
    }
}

function formatStatNumber($num)
{
    if ($num >= 1000) {
        return round($num / 1000, 1) . 'k+';
    }
    return number_format($num);
}
?>

<!DOCTYPE html>
<html class="scroll-smooth" lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nibash - Centralized Digital Resident Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                    },
                    colors: {
                        emerald: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        },
                        slate: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
    <style>
        .reveal {
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.6s ease-out;
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Updated Floating Card Style for better visibility & pop-out effect */
        .hover-card {
            background-color: #ffffff;
            /* Default floating shadow so they immediately pop out from the background */
            box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.08);
            border: 1px solid #f1f5f9;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            z-index: 1;
        }

        .hover-card:hover {
            /* Distinct lift and scale for a premium tactile feel */
            transform: translateY(-8px) scale(1.02);
            /* Soft emerald tinted shadow on hover */
            box-shadow: 0 25px 50px -12px rgba(16, 185, 129, 0.15);
            border-color: #a7f3d0;
            z-index: 10;
        }

        .nav-scrolled {
            background-color: rgba(255, 255, 255, 0.90);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid #f1f5f9;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }

        .btn-press {
            transition: transform 0.15s ease;
        }

        .btn-press:active {
            transform: scale(0.97);
        }

        html {
            scroll-behavior: smooth;
        }

        @keyframes scan-line {
            0% {
                transform: translateY(0);
                opacity: 0;
            }

            10% {
                opacity: 1;
            }

            90% {
                opacity: 1;
            }

            100% {
                transform: translateY(100%);
                opacity: 0;
            }
        }

        .animate-scan {
            animation: scan-line 2.5s ease-in-out infinite;
        }
    </style>
</head>

<body
    class="bg-slate-50 text-slate-900 font-sans antialiased overflow-x-hidden selection:bg-emerald-100 selection:text-emerald-900">

    <!-- Navbar -->
    <nav id="navbar" class="fixed top-0 inset-x-0 z-50 bg-white/70 backdrop-blur-xl border-b border-emerald-100/50 transition-all duration-300 py-2">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-14">
                <div class="flex items-center gap-3 cursor-pointer" onclick="window.scrollTo(0,0)">
                    <div
                        class="w-12 h-12 rounded-2xl bg-white text-emerald-600 flex items-center justify-center border-2 border-emerald-100 shadow-sm">
                        <i data-lucide="building" class="w-6 h-6"></i>
                    </div>
                    <div class="leading-tight">
                        <span class="block text-2xl font-bold tracking-tight text-slate-900">Nibash</span>
                        <span
                            class="block text-[0.65rem] uppercase tracking-[0.2em] text-emerald-600 font-semibold">Centralized
                            Living</span>
                    </div>
                </div>
                <div
                    class="hidden lg:flex items-center gap-10 bg-white/80 backdrop-blur-md px-8 py-3 rounded-full border border-slate-200/50 shadow-sm">
                    <a href="#features"
                        class="text-sm font-semibold text-slate-600 hover:text-emerald-600 transition-colors">Platform</a>
                    <a href="#rentals"
                        class="text-sm font-semibold text-slate-600 hover:text-emerald-600 transition-colors">Rentals</a>
                    <a href="#security"
                        class="text-sm font-semibold text-slate-600 hover:text-emerald-600 transition-colors">Security</a>
                </div>
                <div class="hidden lg:flex items-center gap-4">
                    <button onclick="if(typeof toggleDarkMode === 'function') { toggleDarkMode(); }" class="flex items-center justify-center w-8 h-8 rounded-full border border-slate-200 hover:bg-slate-50 transition-colors text-slate-700 bg-white shadow-sm" title="Toggle Theme">
                        <i data-lucide="moon" class="w-4 h-4 dark:hidden"></i>
                        <i data-lucide="sun" class="w-4 h-4 hidden dark:block text-amber-500"></i>
                    </button>
                    <a href="<?= BASE_URL ?>login.php"
                        class="text-sm font-semibold text-slate-600 hover:text-emerald-600 transition-colors px-3">
                        Log In
                    </a>
                    <a href="<?= BASE_URL ?>owner/register.php"
                        class="btn-press px-6 py-2.5 rounded-full bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700 transition-colors border border-emerald-600 shadow-sm">
                        Register Building
                    </a>
                </div>
                <button id="mobile-menu-btn"
                    class="lg:hidden w-12 h-12 rounded-xl bg-white shadow-sm border border-slate-200 text-slate-600 flex items-center justify-center hover:bg-emerald-50 hover:text-emerald-600">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div id="mobile-panel"
            class="hidden lg:hidden absolute top-full left-4 right-4 mt-2 bg-white rounded-2xl p-5 shadow-lg border border-slate-100 flex flex-col gap-2">
            <a href="#features" class="rounded-xl px-4 py-3 hover:bg-slate-50 text-slate-800 font-medium">Platform
                Features</a>
            <a href="#rentals" class="rounded-xl px-4 py-3 hover:bg-slate-50 text-slate-800 font-medium">Browse
                Rentals</a>
            <a href="#security" class="rounded-xl px-4 py-3 hover:bg-slate-50 text-slate-800 font-medium">Security</a>
            <div class="border-t border-slate-100 my-2"></div>
            <button onclick="if(typeof toggleDarkMode === 'function') { toggleDarkMode(); }" class="flex items-center justify-center gap-2 rounded-xl px-4 py-3 bg-slate-50 hover:bg-slate-100 text-slate-800 font-medium text-center border border-slate-200">
                <i data-lucide="moon" class="w-5 h-5 text-slate-600 dark:hidden"></i>
                <i data-lucide="sun" class="w-5 h-5 hidden dark:block text-amber-500"></i>
                <span class="dark:hidden">Dark Mode</span>
                <span class="hidden dark:inline">Light Mode</span>
            </button>

            <a href="<?= BASE_URL ?>login.php"
                class="rounded-xl px-4 py-3 hover:bg-slate-50 text-slate-800 font-medium text-center">Log In</a>
            <a href="<?= BASE_URL ?>register_owner.php"
                class="rounded-xl px-4 py-3 bg-emerald-600 text-white font-medium text-center shadow-sm">Register
                Building</a>
        </div>
        <!-- Announcement Banner -->
        <div class="bg-gradient-to-r from-emerald-900 to-emerald-800 text-emerald-50 relative overflow-hidden hidden sm:block border-t border-emerald-700/50 h-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full flex items-center justify-between relative z-10">
                <div class="flex items-center gap-3">
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    <p class="text-sm font-medium tracking-wide">
                        Want to become a partner service provider? Create an account and find related services in your area.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="<?= BASE_URL ?>essentials/login.php" class="text-sm font-semibold text-emerald-100 hover:text-white transition-colors px-2">Provider Login</a>
                    <a href="<?= BASE_URL ?>essentials/register.php" class="text-sm font-semibold bg-emerald-500/20 border border-emerald-500/50 text-white hover:bg-emerald-500/40 px-4 py-1 rounded-full transition-colors">Join as Provider</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- FORMAL PRODUCTION HERO SECTION -->
    <!-- FORMAL PRODUCTION HERO SECTION -->
    <section class="min-h-screen flex items-center pt-28 pb-20 bg-white overflow-hidden relative">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
            <div class="grid lg:grid-cols-2 gap-16 items-center">
                <div class="reveal">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-100 text-slate-700 text-xs font-bold uppercase tracking-wider mb-6">
                        🏢 BUILDING OPERATIONS PLATFORM • Centralized Infrastructure
                    </div>
                    <h1 class="text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-tight mb-6">
                        Centralized Residential <br><span class="text-emerald-600">Ecosystem</span>
                    </h1>
                    <p class="text-lg text-slate-600 mb-8 leading-relaxed max-w-lg">
                        The complete platform to oversee residents, browse rentals, secure gate access, and connect with trusted local service providers.
                    </p>
                    <div class="flex flex-col sm:flex-row flex-wrap gap-4 mb-10">
                        <a href="<?= BASE_URL ?>rentals/browse.php"
                            class="btn-press inline-flex justify-center items-center gap-3 px-8 py-4 rounded-2xl bg-emerald-600 text-white font-bold text-lg shadow-[0_8px_30px_rgb(16,185,129,0.3)] hover:bg-emerald-700 hover:shadow-[0_8px_30px_rgb(16,185,129,0.5)] hover:-translate-y-1 transition-all duration-300 w-full sm:w-auto group">
                            <i data-lucide="search" class="w-6 h-6 group-hover:scale-110 transition-transform"></i> Find a Rental
                        </a>
                        <a href="<?= BASE_URL ?>owner/register.php"
                            class="btn-press inline-flex justify-center items-center gap-3 px-8 py-4 rounded-2xl bg-white border-2 border-slate-200 text-slate-700 font-bold text-lg hover:bg-slate-50 hover:border-slate-300 transition-all duration-300 w-full sm:w-auto">
                            <i data-lucide="building" class="w-6 h-6 text-emerald-600"></i> Register Building
                        </a>
                    </div>
                    <div
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-50 border border-emerald-200 rounded-full text-sm text-slate-700 font-semibold">
                        <i data-lucide="building" class="w-4 h-4 text-emerald-600"></i>
                        Serving <?php echo formatStatNumber($total_buildings); ?> registered buildings across Bangladesh
                    </div>
                </div>

                <div class="reveal relative lg:ml-10">
                    <div
                        class="bg-white rounded-2xl shadow-xl border border-slate-200 border-l-4 border-l-emerald-500 p-6 relative z-10">
                        <div class="flex items-center justify-between mb-4 pb-4 border-b border-slate-100">
                            <h3 class="font-bold text-slate-900 text-lg">Platform Highlights</h3>
                            <i data-lucide="bar-chart-2" class="text-slate-400"></i>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-slate-50 rounded-xl">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="p-2 bg-blue-100 text-blue-600 rounded-lg"><i data-lucide="building"
                                            class="w-5 h-5"></i></div>
                                    <span class="text-sm font-medium text-slate-600">Registered Buildings</span>
                                </div>
                                <div class="text-3xl font-bold text-slate-900"><?= formatStatNumber($total_buildings) ?>
                                </div>
                            </div>
                            <div class="p-4 bg-slate-50 rounded-xl">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="p-2 bg-emerald-100 text-emerald-600 rounded-lg"><i data-lucide="users"
                                            class="w-5 h-5"></i></div>
                                    <span class="text-sm font-medium text-slate-600">Total Users</span>
                                </div>
                                <div class="text-3xl font-bold text-slate-900"><?= formatStatNumber($total_users) ?>
                                </div>
                            </div>
                            <div class="p-4 bg-slate-50 rounded-xl">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="p-2 bg-amber-100 text-amber-600 rounded-lg"><i data-lucide="home"
                                            class="w-5 h-5"></i></div>
                                    <span class="text-sm font-medium text-slate-600">Listings</span>
                                </div>
                                <div class="text-3xl font-bold text-slate-900"><?= formatStatNumber($total_listings) ?>
                                </div>
                            </div>
                            <div class="p-4 bg-slate-50 rounded-xl">
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="p-2 bg-purple-100 text-purple-600 rounded-lg"><i
                                            data-lucide="shield-check" class="w-5 h-5"></i></div>
                                    <span class="text-sm font-medium text-slate-600">Secure Logs</span>
                                </div>
                                <div class="text-3xl font-bold text-slate-900"><?= formatStatNumber($secure_logs) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gate Kiosk Link -->
                    <div class="mt-6 relative z-10">
                        <a href="<?= BASE_URL ?>gate_kiosk.php" target="_blank"
                            class="group btn-press flex items-center justify-between p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-xl hover:shadow-2xl hover:border-emerald-500/50 transition-all duration-300">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-slate-800 flex items-center justify-center group-hover:bg-emerald-500/20 transition-colors border border-slate-700 group-hover:border-emerald-500/30">
                                    <i data-lucide="monitor-smartphone" class="w-6 h-6 text-emerald-400"></i>
                                </div>
                                <div>
                                    <span class="block text-base font-bold text-white mb-0.5">Scan Dashboard</span>
                                    <span class="block text-xs text-slate-400">Scanner interface for building guards</span>
                                </div>
                            </div>
                            <div class="w-10 h-10 rounded-full bg-slate-800/50 flex items-center justify-center text-slate-400 group-hover:text-white transition-colors group-hover:bg-emerald-500">
                                <i data-lucide="arrow-right" class="w-5 h-5"></i>
                            </div>
                        </a>
                    </div>
                    <div
                        class="absolute -top-10 -right-10 w-64 h-64 bg-emerald-100 rounded-full blur-3xl opacity-50 -z-10">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Feature Section  -->
    <section id="features" class="py-24 bg-slate-50 relative overflow-hidden">
        <!-- Subtle background decoration to enhance the floating effect -->
        <div
            class="absolute top-0 right-0 w-full h-[600px] bg-gradient-to-b from-white/50 to-transparent pointer-events-none">
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-16 reveal">
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4 tracking-tight">Comprehensive Property
                    Infrastructure</h2>
                <p class="text-lg text-slate-600">Replace disjointed tools with a unified system. Designed for building
                    administrators, owners, and residents.</p>
            </div>

            <!-- Standard 3-Column Enterprise Grid with Floating Hover Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                <!-- Feature 1 (Role-Based Control) -->
                <div
                    class="bg-white rounded-2xl p-8 hover-card reveal flex flex-col border border-slate-200 shadow-md transition-all duration-300 hover:-translate-y-2 hover:scale-[1.02] hover:shadow-2xl hover:shadow-indigo-500/20 hover:border-indigo-300 group relative z-10 hover:z-20 cursor-pointer">
                    <!-- Hover Accent Line -->
                    <div
                        class="absolute top-0 left-0 w-full h-1.5 bg-indigo-500 rounded-t-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    </div>

                    <div
                        class="w-14 h-14 rounded-xl bg-slate-50 flex items-center justify-center mb-6 border border-slate-200 shadow-sm group-hover:scale-110 transition-transform duration-300">
                        <i data-lucide="shield-check" class="w-7 h-7 text-slate-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-3 group-hover:text-indigo-700 transition-colors">
                        Role-Based Control</h3>
                    <p class="text-slate-600 leading-relaxed text-sm mb-6 flex-1">Access dedicated and secure dashboards designed specifically for building administrators, apartment owners, and residents.</p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white rounded-2xl p-8 hover-card reveal flex flex-col border border-slate-200 shadow-md transition-all duration-300 hover:-translate-y-2 hover:scale-[1.02] hover:shadow-2xl hover:shadow-cyan-500/20 hover:border-cyan-300 group relative z-10 hover:z-20 cursor-pointer"
                    style="transition-delay: 50ms;">
                    <!-- Hover Accent Line -->
                    <div
                        class="absolute top-0 left-0 w-full h-1.5 bg-cyan-500 rounded-t-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    </div>

                    <div
                        class="w-14 h-14 rounded-xl bg-cyan-50 flex items-center justify-center mb-6 border border-cyan-100 shadow-sm group-hover:scale-110 transition-transform duration-300">
                        <i data-lucide="home" class="w-7 h-7 text-cyan-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-3 group-hover:text-cyan-700 transition-colors">
                        Verified Marketplace</h3>
                    <p class="text-slate-600 leading-relaxed text-sm mb-6 flex-1">Browse available rental properties safely. The platform ensures community safety by verifying all building owners.</p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white rounded-2xl p-8 hover-card reveal flex flex-col border border-slate-200 shadow-md transition-all duration-300 hover:-translate-y-2 hover:scale-[1.02] hover:shadow-2xl hover:shadow-yellow-500/20 hover:border-yellow-300 group relative z-10 hover:z-20 cursor-pointer"
                    style="transition-delay: 100ms;">
                    <!-- Hover Accent Line -->
                    <div
                        class="absolute top-0 left-0 w-full h-1.5 bg-yellow-400 rounded-t-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    </div>

                    <div
                        class="w-14 h-14 rounded-xl bg-yellow-50 flex items-center justify-center mb-6 border border-yellow-100 shadow-sm group-hover:scale-110 transition-transform duration-300">
                        <i data-lucide="receipt" class="w-7 h-7 text-yellow-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-3 group-hover:text-yellow-700 transition-colors">
                        Financial Ledger</h3>
                    <p class="text-slate-600 leading-relaxed text-sm mb-6 flex-1">Keep a transparent digital record of rent payments, maintenance fees, and verify DESCO utility bills automatically.</p>
                </div>

                <!-- Feature 4 (Digital Gate Access) -->
                <div
                    class="bg-white rounded-2xl p-8 hover-card reveal flex flex-col border border-slate-200 shadow-md transition-all duration-300 hover:-translate-y-2 hover:scale-[1.02] hover:shadow-2xl hover:shadow-emerald-500/20 hover:border-emerald-300 group relative z-10 hover:z-20 cursor-pointer">
                    <!-- Hover Accent Line -->
                    <div
                        class="absolute top-0 left-0 w-full h-1.5 bg-emerald-500 rounded-t-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    </div>

                    <div
                        class="w-14 h-14 rounded-xl bg-emerald-50 flex items-center justify-center mb-6 border border-emerald-100 shadow-sm group-hover:scale-110 transition-transform duration-300">
                        <i data-lucide="scan-face" class="w-7 h-7 text-emerald-600"></i>
                    </div>
                    <h3
                        class="text-xl font-semibold text-slate-900 mb-3 group-hover:text-emerald-700 transition-colors">
                        Digital Gate Access</h3>
                    <p class="text-slate-600 leading-relaxed text-sm mb-6 flex-1">Replace paper logbooks with modern digital entry. The system uses facial recognition to control building access.</p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white rounded-2xl p-8 hover-card reveal flex flex-col border border-slate-200 shadow-md transition-all duration-300 hover:-translate-y-2 hover:scale-[1.02] hover:shadow-2xl hover:shadow-emerald-500/20 hover:border-emerald-300 group relative z-10 hover:z-20 cursor-pointer"
                    style="transition-delay: 50ms;">
                    <!-- Hover Accent Line -->
                    <div
                        class="absolute top-0 left-0 w-full h-1.5 bg-emerald-500 rounded-t-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    </div>

                    <div
                        class="w-14 h-14 rounded-xl bg-emerald-50 flex items-center justify-center mb-6 border border-emerald-100 shadow-sm group-hover:scale-110 transition-transform duration-300">
                        <i data-lucide="users" class="w-7 h-7 text-emerald-600"></i>
                    </div>
                    <h3
                        class="text-xl font-semibold text-slate-900 mb-3 group-hover:text-emerald-700 transition-colors">
                        Community Terminal</h3>
                    <p class="text-slate-600 leading-relaxed text-sm mb-6 flex-1">Connect with your building community. Share announcements, discussions, and updates on a unified digital notice board.</p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white rounded-2xl p-8 hover-card reveal flex flex-col border border-slate-200 shadow-md transition-all duration-300 hover:-translate-y-2 hover:scale-[1.02] hover:shadow-2xl hover:shadow-cyan-500/20 hover:border-cyan-300 group relative z-10 hover:z-20 cursor-pointer"
                    style="transition-delay: 100ms;">
                    <!-- Hover Accent Line -->
                    <div
                        class="absolute top-0 left-0 w-full h-1.5 bg-cyan-500 rounded-t-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                    </div>

                    <div
                        class="w-14 h-14 rounded-xl bg-cyan-50 flex items-center justify-center mb-6 border border-cyan-100 shadow-sm group-hover:scale-110 transition-transform duration-300">
                        <i data-lucide="briefcase" class="w-7 h-7 text-cyan-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-3 group-hover:text-cyan-700 transition-colors">
                        Daily Essentials</h3>
                    <p class="text-slate-600 leading-relaxed text-sm mb-6 flex-1">Connect with verified local service providers like electricians, plumbers, and internet providers directly from the platform.</p>
                </div>

            </div>
        </div>
    </section>

    <!-- FORMAL RENTALS SECTION -->
    <!-- MINIMALIST RENTALS SECTION -->
    <section id="rentals" class="py-24 bg-white border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-16 reveal">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-bold text-slate-900 mb-3 tracking-tight">Available Properties</h2>
                    <p class="text-slate-500 text-lg">Browse recently verified listings from trusted building owners in
                        our network.</p>
                </div>
                <a href="<?= BASE_URL ?>rentals/browse.php"
                    class="inline-flex items-center gap-2 text-emerald-600 font-semibold hover:text-emerald-700 transition-colors group">
                    View Directory <i data-lucide="arrow-right"
                        class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                </a>
            </div>

            <div class="grid md:grid-cols-2 gap-10">
                <?php if (empty($recent_rentals)): ?>
                    <div class="md:col-span-2 text-center py-20 bg-slate-50 rounded-3xl border border-slate-100 reveal">
                        <div
                            class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm border border-slate-100">
                            <i data-lucide="home" class="w-8 h-8 text-slate-300"></i>
                        </div>
                        <h3 class="text-slate-900 font-medium mb-1">No Listings Yet</h3>
                        <p class="text-slate-500">Check back later for newly verified properties.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recent_rentals as $rental): ?>
                        <a href="<?= BASE_URL ?>rentals/details.php?id=<?= (int) ($rental['id'] ?? 0) ?>"
                            class="group block reveal">
                            <!-- Image Header -->
                            <div
                                class="rounded-2xl overflow-hidden mb-5 relative bg-slate-100 aspect-[4/3] sm:aspect-[16/9] border border-slate-200/60">
                                <?php if (!empty($rental['image_path']) && file_exists($rental['image_path'])): ?>
                                    <img src="<?= htmlspecialchars($rental['image_path']) ?>" alt="Property"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out">
                                <?php else: ?>
                                    <div
                                        class="w-full h-full flex items-center justify-center bg-slate-50 group-hover:scale-105 transition-transform duration-700 ease-out">
                                        <i data-lucide="image" class="w-8 h-8 text-slate-300"></i>
                                    </div>
                                <?php endif; ?>
                                <div
                                    class="absolute top-4 left-4 bg-white/90 backdrop-blur-md px-3 py-1.5 rounded-md text-slate-900 font-bold text-sm border border-white/20 shadow-sm">
                                    ৳<?= number_format((float) ($rental['rent_amount'] ?? 0)) ?> <span
                                        class="text-slate-500 font-normal text-xs">/mo</span>
                                </div>
                            </div>

                            <!-- Minimal Card Body -->
                            <div>
                                <div class="flex items-start justify-between gap-4 mb-2">
                                    <h3 class="font-bold text-slate-900 text-xl group-hover:text-emerald-600 transition-colors">
                                        <?= htmlspecialchars((string) ($rental['apartment_name'] ?? '')) ?>
                                    </h3>
                                </div>
                                <p class="text-slate-500 text-sm mb-4 flex items-center gap-1.5">
                                    <i data-lucide="map-pin" class="w-4 h-4"></i>
                                    <?= htmlspecialchars((string) ($rental['address'] ?? '')) ?>
                                </p>

                                <div class="flex items-center gap-4 text-sm text-slate-600 border-t border-slate-100 pt-4">
                                    <?php if (($rental['rental_type'] ?? '') === 'parking'): ?>
                                        <span class="flex items-center gap-1.5"><i data-lucide="<?= ($rental['vehicle_type'] ?? 'car') === 'motorbike' ? 'bike' : 'car' ?>"
                                                class="w-4 h-4 text-slate-400"></i>
                                            <?= htmlspecialchars(ucfirst($rental['vehicle_type'] ?? 'car')) ?></span>
                                        <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                        <span class="flex items-center gap-1.5"><i data-lucide="maximize"
                                                class="w-4 h-4 text-slate-400"></i>
                                            <?= (float)($rental['parking_length'] ?? 0) ?> &times; <?= (float)($rental['parking_width'] ?? 0) ?> <?= htmlspecialchars($rental['measurement_unit'] ?? 'feet') ?></span>
                                        <?php if (($rental['floor_number'] ?? 0) != 0): ?>
                                            <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                            <span class="flex items-center gap-1.5"><i data-lucide="layers"
                                                    class="w-4 h-4 text-slate-400"></i> Floor
                                                <?= htmlspecialchars((string) ($rental['floor_number'] ?? 0)) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="flex items-center gap-1.5"><i data-lucide="bed"
                                                class="w-4 h-4 text-slate-400"></i>
                                            <?= htmlspecialchars((string) ($rental['total_bedrooms'] ?? 0)) ?> Beds</span>
                                        <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                        <span class="flex items-center gap-1.5"><i data-lucide="bath"
                                                class="w-4 h-4 text-slate-400"></i>
                                            <?= htmlspecialchars((string) ($rental['washrooms'] ?? 0)) ?> Baths</span>
                                        <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                        <span class="flex items-center gap-1.5"><i data-lucide="layers"
                                                class="w-4 h-4 text-slate-400"></i> Floor
                                            <?= htmlspecialchars((string) ($rental['floor_number'] ?? 0)) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- FORMAL SECURITY SECTION (2-Column Data Layout) -->
    <section id="security" class="py-24 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 lg:gap-20 items-center">

                <!-- Left: Info -->
                <div class="reveal">
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200/50 border border-slate-300 text-slate-700 text-xs font-semibold uppercase tracking-wider mb-6">
                        <i data-lucide="shield" class="w-3.5 h-3.5 text-slate-500"></i> Smart Security
                    </div>

                    <h2 class="text-3xl lg:text-4xl font-bold text-slate-900 mb-6 tracking-tight">Smart security that <br>keeps the building safe.</h2>
                    <p class="text-slate-600 mb-8 leading-relaxed">
                        The system uses smart cameras to process resident entry seamlessly. If an unrecognized person attempts to enter, the CCTV captures and saves their image for review. Additionally, the system scans vehicle number plates to automatically assign parking spots and track availability.
                    </p>

                    <div class="space-y-4 mb-8">
                        <div class="flex gap-4">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                                <i data-lucide="scan-face" class="w-4 h-4 text-emerald-700"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-900">Face Scan Entry</h4>
                                <p class="text-sm text-slate-600">The access camera recognizes registered residents and automatically opens the gate.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                                <i data-lucide="camera" class="w-4 h-4 text-emerald-700"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-900">Unknown Visitor Alerts</h4>
                                <p class="text-sm text-slate-600">The CCTV takes a photo and logs the event immediately if an unregistered person tries to enter.</p>
                            </div>
                        </div>
                        <div class="flex gap-4">
                            <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                                <i data-lucide="car" class="w-4 h-4 text-emerald-700"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-900">Number Plate Scanner</h4>
                                <p class="text-sm text-slate-600">Scans vehicles to allocate parking spaces and maintain real-time parking records.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Dashboard Mockup -->
                <div class="reveal">
                    <div
                        class="bg-white rounded-2xl border border-slate-200 shadow-[0_10px_30px_-10px_rgba(15,23,42,0.08)] overflow-hidden hover-card">
                        <div class="bg-slate-100 border-b border-slate-200 px-4 py-3 flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-slate-300"></div>
                            <div class="w-3 h-3 rounded-full bg-slate-300"></div>
                            <div class="w-3 h-3 rounded-full bg-slate-300"></div>
                            <span class="ml-2 text-xs font-medium text-slate-500 font-mono">system_metrics_live</span>
                        </div>

                        <div class="p-6 lg:p-8">
                            <h3
                                class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-6 border-b border-slate-100 pb-2">
                                System Telemetry</h3>

                            <div class="space-y-4">
                                <div
                                    class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="scan-face" class="w-5 h-5 text-emerald-600"></i>
                                        <div>
                                            <div class="font-semibold text-slate-900 text-sm">Total Access Events</div>
                                            <div class="text-xs text-slate-500">Authorized entries logged</div>
                                        </div>
                                    </div>
                                    <div class="text-xl font-bold text-slate-900 font-mono">
                                        <?php echo formatStatNumber($secure_logs); ?>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="user-check" class="w-5 h-5 text-cyan-600"></i>
                                        <div>
                                            <div class="font-semibold text-slate-900 text-sm">Verified Identities</div>
                                            <div class="text-xs text-slate-500">Active signatures in database</div>
                                        </div>
                                    </div>
                                    <div class="text-xl font-bold text-slate-900 font-mono">
                                        <?php echo formatStatNumber($total_users); ?>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center justify-between p-4 bg-slate-50 rounded-xl border border-slate-100">
                                    <div class="flex items-center gap-3">
                                        <i data-lucide="alert-circle" class="w-5 h-5 text-yellow-600"></i>
                                        <div>
                                            <div class="font-semibold text-slate-900 text-sm">Security Anomalies</div>
                                            <div class="text-xs text-slate-500">Unidentified scan attempts</div>
                                        </div>
                                    </div>
                                    <div class="text-xl font-bold text-slate-900 font-mono">
                                        <?php
                                        $unknown_file = __DIR__ . '/assets/uploads/scans/unknown/log.json';
                                        $alerts = 0;
                                        if (file_exists($unknown_file)) {
                                            $decoded = json_decode(file_get_contents($unknown_file), true);
                                            $alerts = is_array($decoded) ? count($decoded) : 0;
                                        }
                                        echo formatStatNumber($alerts);
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FORMAL CTA SECTION -->
    <section class="py-24 bg-white border-t border-slate-100">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center reveal">
            <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4 tracking-tight">Ready to upgrade your building operations?</h2>
            <p class="text-lg text-slate-600 mb-8 max-w-2xl mx-auto">Join the property owners who use Nibash to run their daily operations smoothly and securely.</p>

            <div class="flex flex-col justify-center items-center gap-4">
                <a href="<?= BASE_URL ?>owner/register.php"
                    class="btn-press px-8 py-3 bg-emerald-600 text-white font-semibold rounded-lg hover:bg-emerald-700 transition-colors shadow-sm w-full sm:w-auto">
                    Register Property Free
                </a>
            </div>
        </div>
    </section>

    <!-- CLEAN FOOTER -->
    <footer class="bg-slate-50 pt-16 pb-8 border-t border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row justify-between items-center gap-8 mb-12">
                <div class="flex flex-col items-center md:items-start max-w-sm">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center">
                            <i data-lucide="building" class="w-4 h-4"></i>
                        </div>
                        <span class="text-lg font-bold text-slate-900 tracking-tight">Nibash</span>
                    </div>
                    <p class="text-sm text-slate-500 text-center md:text-left">The comprehensive platform for modern building operations and residential infrastructure.</p>
                </div>

                <div class="flex flex-wrap justify-center gap-6 text-sm">
                    <a href="#features" class="text-slate-500 font-semibold hover:text-emerald-600 transition-colors">Features</a>
                    <a href="<?= BASE_URL ?>rentals/browse.php" class="text-slate-500 font-semibold hover:text-emerald-600 transition-colors">Rental Directory</a>
                    <a href="<?= BASE_URL ?>essentials/register.php" class="text-slate-500 font-semibold hover:text-emerald-600 transition-colors">Provider Registration</a>
                    <a href="<?= BASE_URL ?>contact.php" class="text-slate-500 font-semibold hover:text-emerald-600 transition-colors">Contact</a>
                    <a href="<?= BASE_URL ?>admin-portal/index.php" class="text-slate-400 font-semibold hover:text-emerald-600 transition-colors">Admin Portal</a>
                </div>
            </div>

            <div class="pt-8 border-t border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-slate-500">
                <div>&copy; 2026 Nibash Systems. All rights reserved.</div>
                <div class="flex items-center gap-1 font-medium">Developed by <a href="<?= BASE_URL ?>team.php" class="text-emerald-600 hover:text-emerald-700 transition-colors font-bold">Team Zero-NF</a></div>
            </div>
        </div>
    </footer>



    <!-- FORMAL AI SCAN MODAL -->
    <div id="biometric-modal"
        class="hidden fixed inset-0 bg-slate-900/70 z-[100] flex items-center justify-center p-4 backdrop-blur-md transition-opacity duration-300"
        aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div id="biometric-panel"
            class="bg-white rounded-3xl shadow-[0_20px_60px_-15px_rgba(0,0,0,0.2)] overflow-hidden max-w-2xl w-full transform transition-all duration-300 scale-95 opacity-0 flex flex-col border border-slate-200/60 ring-1 ring-slate-900/5">

            <div
                class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white relative overflow-hidden">
                <div
                    class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-full blur-3xl -z-10 translate-x-10 -translate-y-10">
                </div>

                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center border border-slate-100 shadow-sm">
                        <i data-lucide="cpu" class="w-5 h-5 text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 tracking-tight leading-tight">Gate Terminal</h3>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span class="text-[0.65rem] uppercase tracking-wider text-slate-500 font-semibold">System
                                Online</span>
                        </div>
                    </div>
                </div>
                <button onclick="toggleBiometricModal(false)"
                    class="btn-press w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-700 border border-slate-200 transition-colors">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <div class="p-4 bg-slate-50/50 flex flex-col items-center relative">

                <div class="relative w-full aspect-video bg-slate-900 rounded-2xl overflow-hidden shadow-xl ring-1 ring-slate-900/5 border border-slate-700/50"
                    id="video-container">

                    <div id="bio-status-container"
                        class="absolute top-4 left-0 right-0 w-full text-center z-40 flex justify-center pointer-events-none">
                        <p id="bio-status"
                            class="inline-flex items-center justify-center gap-2 px-5 py-2 rounded-full bg-white/90 backdrop-blur-md border border-white/20 shadow-lg text-slate-700 text-sm font-semibold pointer-events-auto">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-emerald-600"></i> Initializing
                            Hardware...
                        </p>
                    </div>

                    <video id="gate-video" class="w-full h-full object-cover transform scale-x-[-1]" autoplay muted
                        playsinline></video>

                    <div id="scan-line"
                        class="absolute left-0 right-0 h-1 bg-emerald-500 shadow-[0_0_15px_rgba(16,185,129,1)] opacity-0 z-20 pointer-events-none">
                    </div>

                    <div id="scan-overlay"
                        class="absolute inset-0 border-[3px] border-emerald-500/40 opacity-0 transition-opacity duration-200 z-10 pointer-events-none rounded-2xl">
                        <div
                            class="absolute top-0 left-0 w-8 h-8 border-t-[3px] border-l-[3px] border-emerald-500 rounded-tl-2xl">
                        </div>
                        <div
                            class="absolute top-0 right-0 w-8 h-8 border-t-[3px] border-r-[3px] border-emerald-500 rounded-tr-2xl">
                        </div>
                        <div
                            class="absolute bottom-0 left-0 w-8 h-8 border-b-[3px] border-l-[3px] border-emerald-500 rounded-bl-2xl">
                        </div>
                        <div
                            class="absolute bottom-0 right-0 w-8 h-8 border-b-[3px] border-r-[3px] border-emerald-500 rounded-br-2xl">
                        </div>
                    </div>

                    <canvas id="gate-canvas"
                        class="absolute inset-0 z-30 pointer-events-none w-full h-full object-cover"></canvas>
                </div>

                <div id="match-result"
                    class="w-full p-4 rounded-xl text-center hidden opacity-0 transition-all transform translate-y-2 z-20 mt-4 border shadow-sm">
                </div>
            </div>

            <div
                class="bg-white border-t border-slate-100 px-6 py-4 flex justify-between items-center text-xs text-slate-500 font-medium">
                <div class="flex items-center gap-1.5 font-mono text-slate-400">
                    <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> End-to-End Encrypted
                </div>
                <div class="flex items-center gap-1">
                    <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-500 fill-amber-500"></i> Powered by Nibash AI
                </div>
            </div>
        </div>
    </div>


    <!-- PARKING SCANNER MODAL -->
    <div id="parking-scanner-modal" class="hidden fixed inset-0 bg-slate-900/70 z-[100] flex items-center justify-center p-4 backdrop-blur-md transition-opacity duration-300" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div id="parking-scanner-panel" class="bg-white rounded-3xl shadow-[0_20px_60px_-15px_rgba(0,0,0,0.2)] overflow-hidden max-w-2xl w-full transform transition-all duration-300 scale-95 opacity-0 flex flex-col border border-slate-200/60 ring-1 ring-slate-900/5">
            <div class="px-6 py-5 border-b border-slate-100 flex justify-between items-center bg-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-50 rounded-full blur-3xl -z-10 translate-x-10 -translate-y-10"></div>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-50 flex items-center justify-center border border-slate-100 shadow-sm">
                        <i data-lucide="car-front" class="w-5 h-5 text-emerald-600"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 tracking-tight leading-tight">Parking Scanner</h3>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <span class="text-[0.65rem] uppercase tracking-wider text-slate-500 font-semibold">System Online</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button id="parking-flip-btn" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-700 border border-slate-200 transition-colors" title="Flip Camera">
                        <i data-lucide="flip-horizontal" class="w-4 h-4"></i>
                    </button>
                    <button onclick="toggleParkingScannerModal(false)" class="btn-press w-8 h-8 flex items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 text-slate-400 hover:text-slate-700 border border-slate-200 transition-colors">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
            <div class="p-4 bg-slate-50/50 flex flex-col items-center relative">
                <div class="relative w-full aspect-video bg-slate-900 rounded-2xl overflow-hidden shadow-xl ring-1 ring-slate-900/5 border border-slate-700/50" id="parking-video-container">
                    <div id="parking-bio-status-container" class="absolute top-4 left-0 right-0 w-full text-center z-40 flex justify-center pointer-events-none">
                        <p id="parking-bio-status" class="inline-flex items-center justify-center gap-2 px-5 py-2 rounded-full bg-white/90 backdrop-blur-md border border-white/20 shadow-lg text-slate-700 text-sm font-semibold pointer-events-auto">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin text-emerald-600"></i> Initializing Camera...
                        </p>
                    </div>
                    <video id="parking-webcam" class="w-full h-full object-cover" autoplay playsinline></video>
                    <!-- Scanner Overlay -->
                    <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
                        <div class="w-[80%] max-w-[400px] h-[120px] border-4 border-dashed border-emerald-500/60 rounded-xl relative overflow-hidden" style="box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.5);">
                            <div id="parking-scan-line" class="w-full h-1 bg-emerald-500 shadow-[0_0_10px_#10b981] absolute top-0 left-0 hidden" style="animation: scan 2s infinite linear;"></div>
                        </div>
                    </div>
                    <style>
                        @keyframes scan { 0% { top: 0; } 50% { top: 100%; } 100% { top: 0; } }
                    </style>
                    <canvas id="parking-canvas" class="hidden"></canvas>
                    <canvas id="parking-crop-canvas" class="hidden"></canvas>
                </div>
                <!-- Detected Text Debugging -->
                <div id="parking-debug-text" class="absolute bottom-6 bg-slate-900/80 px-4 py-2 rounded-lg text-emerald-400 font-mono text-sm tracking-wider opacity-0 transition-opacity z-20 pointer-events-none">
                    Scanning...
                </div>
                <div id="parking-match-result" class="w-full p-4 rounded-xl text-center hidden opacity-0 transition-all transform translate-y-2 z-20 mt-4 border shadow-sm">
                </div>
            </div>
            <div class="bg-white border-t border-slate-100 px-6 py-4 flex justify-between items-center text-xs text-slate-500 font-medium">
                <div class="flex items-center gap-1.5 font-mono text-slate-400">
                    <i data-lucide="car-front" class="w-3.5 h-3.5"></i> LPR Engine
                </div>
                <div class="flex items-center gap-1">
                    <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-500 fill-amber-500"></i> Powered by Nibash AI
                </div>
            </div>
        </div>
    </div>

    <script>
        // Init Icons
        lucide.createIcons();

        // Mobile Menu
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const mobilePanel = document.getElementById('mobile-panel');
        if (mobileBtn && mobilePanel) {
            mobileBtn.addEventListener('click', () => {
                mobilePanel.classList.toggle('hidden');
            });
        }

        // Scroll Navbar
        const navbar = document.getElementById('navbar');
        window.addEventListener('scroll', () => {
            if (window.scrollY > 10) {
                navbar.classList.add('nav-scrolled');
            } else {
                navbar.classList.remove('nav-scrolled');
            }
        });

        // Scroll Animations
        const observerOptions = { root: null, rootMargin: '0px', threshold: 0.1 };
        const observer = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        document.querySelectorAll('.reveal').forEach(element => {
            observer.observe(element);
        });

        // --- Core Web Terminal Face Recog logic ---
        let videoStream = null;
        let isFaceRecogRunning = false;
        let labeledDescriptors = null;
        let faceMatcher = null;
        let scanInterval = null;
        let modelsLoaded = false;
        let scanAttemptCount = 0;
        let isBiometricModalOpen = false;

        let guestsLoaded = false;
        let isCameraWarmingUp = false;

        async function preloadModels() {
            if (modelsLoaded) return;
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
            try {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                modelsLoaded = true;

                const dummyCanvas = document.createElement('canvas');
                dummyCanvas.width = 100;
                dummyCanvas.height = 100;
                await faceapi.detectAllFaces(dummyCanvas, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptors();
            } catch (err) {
                console.error("Error preloading models:", err);
            }
        }

        async function fetchGuestSignatures(isPreload = false) {
            if (guestsLoaded) return;
            try {
                const response = await fetch('<?= BASE_URL ?>api/gate.php?action=get_guests');
                const data = await response.json();

                if (data.status !== 'success' || !data.data || data.data.length === 0) {
                    if (!isPreload) faceMatcher = null;
                    guestsLoaded = true;
                    return;
                }

                labeledDescriptors = data.data.map(guest => {
                    const descArray = Object.values(guest.face_descriptor);
                    const float32Desc = new Float32Array(descArray);
                    return new faceapi.LabeledFaceDescriptors(guest.id + ':' + guest.guest_name + ':' + guest.guest_type, [float32Desc]);
                });

                faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.45);
                guestsLoaded = true;
            } catch (err) {
                console.error(err);
                if (!isPreload) document.getElementById('bio-status').innerText = 'Error loading database.';
            }
        }

        async function startVideo() {
            const video = document.getElementById('gate-video');
            const bioStatus = document.getElementById('bio-status');
            try {
                bioStatus.innerHTML = '<span class="text-slate-600"><i data-lucide="camera" class="w-4 h-4 inline mr-1 text-emerald-600"></i> Connecting camera...</span>';
                if (typeof lucide !== 'undefined') lucide.createIcons();

                videoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });

                if (!isBiometricModalOpen) {
                    videoStream.getTracks().forEach(track => track.stop());
                    videoStream = null;
                    return;
                }

                video.srcObject = videoStream;
                return new Promise((resolve) => {
                    video.onloadedmetadata = () => {
                        bioStatus.innerHTML = '<span class="text-emerald-700 font-semibold"><i data-lucide="scan" class="w-4 h-4 inline mr-1"></i> Scan active. Please look at camera.</span>';
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                        isCameraWarmingUp = true;
                        setTimeout(() => { isCameraWarmingUp = false; }, 2000);
                        resolve();
                    };
                });
            } catch (err) {
                console.error(err);
                bioStatus.innerHTML = '<span class="text-rose-600 font-medium"><i data-lucide="video-off" class="w-4 h-4 inline mr-1"></i> Camera access denied.</span>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }

        function stopVideo() {
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
                videoStream = null;
            }
            if (scanInterval) clearInterval(scanInterval);
            isFaceRecogRunning = false;
            document.getElementById('gate-video').srcObject = null;
        }

        async function runRecognitionLoop() {
            const video = document.getElementById('gate-video');
            const canvas = document.getElementById('gate-canvas');
            const resultBox = document.getElementById('match-result');
            const overlay = document.getElementById('scan-overlay');
            const bioStatus = document.getElementById('bio-status');

            const displaySize = { width: video.videoWidth, height: video.videoHeight };
            faceapi.matchDimensions(canvas, displaySize);

            let hasRecognized = false;
            let scanStartTime = Date.now();
            let lastUnknownFaceImage = '';

            scanInterval = setInterval(async () => {
                if (!isFaceRecogRunning || hasRecognized) return;

                if (Date.now() - scanStartTime > 10000) {
                    isFaceRecogRunning = false;
                    clearInterval(scanInterval);
                    overlay.classList.remove('opacity-100');
                    overlay.classList.add('opacity-0');

                    const scanLine = document.getElementById('scan-line');
                    if (scanLine) {
                        scanLine.classList.remove('animate-scan');
                        scanLine.style.opacity = '0';
                    }

                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);

                    resultBox.classList.remove('hidden');
                    void resultBox.offsetWidth;
                    resultBox.classList.remove('translate-y-2', 'opacity-0');

                    scanAttemptCount++;

                    let additionalTextDiv = scanAttemptCount === 1
                        ? '<div class="text-xs text-slate-500 mb-4">Please try again or use manual entry.</div>'
                        : '<div class="text-xs text-slate-500 mb-4">Multiple failed attempts. Proceed to manual entry.</div>';

                    let buttonsHtml = `
                        <div class="flex gap-2 w-full mt-2">
                            <button onclick="restartScan()" class="flex-1 py-2 bg-white border border-slate-300 text-slate-700 font-medium rounded-md text-sm hover:bg-slate-50 transition-colors">Retry</button>
                            <button onclick="window.location.href='<?= BASE_URL ?>owner/manual_guest.php'" class="flex-1 py-2 bg-slate-900 border border-slate-900 text-white font-medium rounded-md text-sm hover:bg-slate-800 transition-colors">Manual Entry</button>
                        </div>
                    `;

                    if (lastUnknownFaceImage !== '') {
                        bioStatus.innerHTML = '<span class="text-rose-600 font-semibold">Access Denied</span>';

                        fetch('<?= BASE_URL ?>api/gate.php?action=log_unknown', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ scan_image: lastUnknownFaceImage })
                        }).catch(console.error);

                        resultBox.innerHTML = `
                            <div class="flex flex-col items-center">
                                <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mb-2"><i data-lucide="x" class="w-5 h-5"></i></div>
                                <div class="text-slate-900 font-semibold text-sm mb-1">Face Not Recognized</div>
                                ${additionalTextDiv}
                                ${buttonsHtml}
                            </div>
                        `;
                        resultBox.className = 'w-full p-4 rounded-lg text-center opacity-100 transition-all z-20 mt-4 bg-rose-50 border border-rose-200';
                    } else {
                        bioStatus.innerHTML = '<span class="text-slate-600 font-semibold">Scan Timeout</span>';

                        resultBox.innerHTML = `
                            <div class="flex flex-col items-center">
                                <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center mb-2"><i data-lucide="clock" class="w-5 h-5"></i></div>
                                <div class="text-slate-900 font-semibold text-sm mb-1">No Face Detected</div>
                                ${additionalTextDiv}
                                ${buttonsHtml}
                            </div>
                        `;
                        resultBox.className = 'w-full p-4 rounded-lg text-center opacity-100 transition-all z-20 mt-4 bg-slate-50 border border-slate-200';
                    }

                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    return;
                }

                if (!guestsLoaded) return;

                let detections;
                try {
                    detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptors();
                } catch (e) { return; }

                if (!isFaceRecogRunning || hasRecognized || !isBiometricModalOpen) return;

                const resizedDetections = faceapi.resizeResults(detections, displaySize);
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                if (detections.length > 0) {
                    overlay.classList.remove('opacity-0');
                    overlay.classList.add('opacity-100');
                    if (bioStatus.innerText !== 'Analyzing Profile...') {
                        bioStatus.innerHTML = '<span class="text-emerald-600 font-semibold"><i data-lucide="loader-2" class="w-4 h-4 inline animate-spin mr-1"></i> Analyzing Profile...</span>';
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    }

                    resizedDetections.forEach(detection => {
                        const box = detection.detection.box;
                        ctx.strokeStyle = 'rgba(16, 185, 129, 0.8)';
                        ctx.lineWidth = 2;

                        // Professional corner bracket style instead of round rect
                        const d = 15;
                        ctx.beginPath(); ctx.moveTo(box.x, box.y + d); ctx.lineTo(box.x, box.y); ctx.lineTo(box.x + d, box.y); ctx.stroke();
                        ctx.beginPath(); ctx.moveTo(box.x + box.width - d, box.y); ctx.lineTo(box.x + box.width, box.y); ctx.lineTo(box.x + box.width, box.y + d); ctx.stroke();
                        ctx.beginPath(); ctx.moveTo(box.x, box.y + box.height - d); ctx.lineTo(box.x, box.y + box.height); ctx.lineTo(box.x + d, box.y + box.height); ctx.stroke();
                        ctx.beginPath(); ctx.moveTo(box.x + box.width - d, box.y + box.height); ctx.lineTo(box.x + box.width, box.y + box.height); ctx.lineTo(box.x + box.width, box.y + box.height - d); ctx.stroke();
                    });
                } else {
                    overlay.classList.remove('opacity-100');
                    overlay.classList.add('opacity-0');
                    if (!isCameraWarmingUp) {
                        bioStatus.innerHTML = '<span class="text-emerald-700 font-semibold"><i data-lucide="scan" class="w-4 h-4 inline mr-1"></i> Scan active. Please look at camera.</span>';
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    }
                }

                const results = faceMatcher
                    ? resizedDetections.map(d => faceMatcher.findBestMatch(d.descriptor))
                    : resizedDetections.map(d => ({ label: 'unknown', distance: 1.0 }));

                const matchIndex = results.findIndex(r => r.label !== 'unknown');

                const captureFace = (index) => {
                    try {
                        const detectionBox = detections[index].detection.box;
                        const tmpCanvas = document.createElement('canvas');
                        tmpCanvas.width = 300;
                        tmpCanvas.height = 300;
                        const tmpCtx = tmpCanvas.getContext('2d');
                        const pad = 40;
                        const x = Math.max(0, detectionBox.x - pad);
                        const y = Math.max(0, detectionBox.y - pad * 1.5);
                        const w = Math.min(video.videoWidth - x, detectionBox.width + pad * 2);
                        const h = Math.min(video.videoHeight - y, detectionBox.height + pad * 2.5);
                        tmpCtx.drawImage(video, x, y, w, h, 0, 0, 300, 300);
                        return tmpCanvas.toDataURL('image/jpeg', 0.85);
                    } catch (e) { return ''; }
                };

                if (matchIndex !== -1) {
                    hasRecognized = true;
                    clearInterval(scanInterval);
                    const bestMatch = results[matchIndex];
                    const matchedFaceImage = captureFace(matchIndex);

                    resultBox.classList.remove('hidden');
                    void resultBox.offsetWidth;
                    resultBox.classList.remove('translate-y-2', 'opacity-0');

                    const [gId, gName, gType] = bestMatch.label.split(':');
                    resultBox.innerHTML = `
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0"><i data-lucide="check" class="w-6 h-6"></i></div>
                            <div class="text-left flex-1">
                                <div class="text-emerald-700 font-semibold text-xs uppercase tracking-wide">Access Granted</div>
                                <div class="text-slate-900 text-lg font-bold">${gName}</div>
                                <div class="text-slate-500 text-sm">${gType} Profile</div>
                            </div>
                        </div>
                    `;
                    resultBox.className = 'w-full p-4 rounded-lg opacity-100 transition-all z-20 mt-4 bg-emerald-50 border border-emerald-200';
                    bioStatus.innerHTML = '<span class="text-emerald-700 font-semibold">Verification Complete</span>';

                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    overlay.classList.remove('opacity-100');
                    const scanLine = document.getElementById('scan-line');
                    if (scanLine) {
                        scanLine.classList.remove('animate-scan');
                        scanLine.style.opacity = '0';
                    }

                    fetch('<?= BASE_URL ?>api/gate.php?action=log_entry', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ guest_id: gId, scan_image: matchedFaceImage })
                    }).catch(console.error);

                    if (typeof lucide !== 'undefined') lucide.createIcons();
                } else if (detections.length > 0) {
                    lastUnknownFaceImage = captureFace(0);
                }

            }, 200);
        }

        function restartScan() {
            clearInterval(scanInterval);
            isFaceRecogRunning = true;

            const resultBox = document.getElementById('match-result');
            if (resultBox) {
                resultBox.className = 'w-full p-4 rounded-lg text-center hidden opacity-0 transition-all transform translate-y-2 z-20 mt-4 border';
                resultBox.innerHTML = '';
            }
            const overlay = document.getElementById('scan-overlay');
            if (overlay) {
                overlay.classList.remove('opacity-100');
                overlay.classList.add('opacity-0');
            }
            const scanLine = document.getElementById('scan-line');
            if (scanLine) {
                scanLine.style.opacity = '1';
                scanLine.classList.add('animate-scan');
            }
            document.getElementById('bio-status').innerHTML = '<span class="text-emerald-700 font-semibold"><i data-lucide="scan" class="w-4 h-4 inline mr-1"></i> Scan active. Please look at camera.</span>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            runRecognitionLoop();
        }

        async function toggleBiometricModal(show) {
            const modal = document.getElementById('biometric-modal');
            const panel = document.getElementById('biometric-panel');

            if (show) {
                isBiometricModalOpen = true;
                scanAttemptCount = 0;

                preloadModels();
                fetchGuestSignatures();

                const resultBox = document.getElementById('match-result');
                if (resultBox) {
                    resultBox.className = 'w-full p-4 rounded-lg text-center hidden opacity-0 transition-all transform translate-y-2 z-20 mt-4 border';
                    resultBox.innerHTML = '';
                }
                const overlay = document.getElementById('scan-overlay');
                if (overlay) {
                    overlay.classList.remove('opacity-100');
                    overlay.classList.add('opacity-0');
                }
                const scanLine = document.getElementById('scan-line');
                if (scanLine) {
                    scanLine.classList.remove('animate-scan');
                    scanLine.style.opacity = '0';
                }

                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.classList.add('opacity-100');
                    modal.classList.remove('opacity-0');
                    panel.classList.replace('scale-95', 'scale-100');
                    panel.classList.replace('opacity-0', 'opacity-100');
                }, 10);

                isFaceRecogRunning = true;
                const bioStatus = document.getElementById('bio-status');

                if (!modelsLoaded) {
                    bioStatus.innerHTML = '<span class="text-slate-600 text-sm font-medium flex items-center justify-center gap-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin text-emerald-600"></i> Initializing Hardware...</span>';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    await preloadModels();
                }
                if (!isBiometricModalOpen) return;

                if (!guestsLoaded) {
                    bioStatus.innerHTML = '<span class="text-slate-600 text-sm font-medium flex items-center justify-center gap-2"><i data-lucide="loader-2" class="w-4 h-4 animate-spin text-emerald-600"></i> Synchronizing Database...</span>';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    await fetchGuestSignatures(false);
                }
                if (!isBiometricModalOpen) return;

                await startVideo();
                if (!isBiometricModalOpen) {
                    stopVideo();
                    return;
                }

                if (scanLine) {
                    scanLine.classList.add('animate-scan');
                }
                runRecognitionLoop();

            } else {
                isBiometricModalOpen = false;
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0');
                panel.classList.replace('scale-100', 'scale-95');
                panel.classList.replace('opacity-100', 'opacity-0');

                stopVideo();

                const scanLine = document.getElementById('scan-line');
                if (scanLine) {
                    scanLine.classList.remove('animate-scan');
                    scanLine.style.opacity = '0';
                }

                setTimeout(() => {
                    modal.classList.add('hidden');
                }, 300);
            }
        }
        // --- Parking Scanner Logic ---
        let parkingVideoStream = null;
        let isParkingScanning = false;
        let parkingScanComplete = false;
        let lastParkingScanTime = 0;
        const parkingCooldownMs = 3000;
        let isParkingMirrored = false;

        const parkingVideo = document.getElementById('parking-webcam');
        const parkingCanvas = document.getElementById('parking-canvas');
        const parkingCropCanvas = document.getElementById('parking-crop-canvas');
        const parkingScanLine = document.getElementById('parking-scan-line');
        const parkingDebugText = document.getElementById('parking-debug-text');
        const parkingBioStatus = document.getElementById('parking-bio-status');
        const parkingMatchResult = document.getElementById('parking-match-result');
        const parkingFlipBtn = document.getElementById('parking-flip-btn');

        if (parkingFlipBtn && parkingVideo) {
            parkingFlipBtn.addEventListener('click', () => {
                isParkingMirrored = !isParkingMirrored;
                parkingVideo.style.transform = isParkingMirrored ? 'scaleX(-1)' : 'none';
            });
        }

        async function startParkingCamera() {
            try {
                parkingBioStatus.innerHTML = '<span class="text-slate-600"><i data-lucide="camera" class="w-4 h-4 inline mr-1 text-emerald-600"></i> Connecting camera...</span>';
                if (typeof lucide !== 'undefined') lucide.createIcons();

                parkingVideoStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'environment' } 
                });
                
                parkingVideo.srcObject = parkingVideoStream;
                
                parkingVideo.addEventListener('play', () => {
                    parkingCanvas.width = parkingVideo.videoWidth;
                    parkingCanvas.height = parkingVideo.videoHeight;
                    parkingScanLine.style.display = 'block';
                    parkingBioStatus.innerHTML = '<span class="text-emerald-700 font-semibold"><i data-lucide="scan" class="w-4 h-4 inline mr-1"></i> Scanning active. Please align plate.</span>';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    requestAnimationFrame(processParkingFrame);
                }, { once: true });
            } catch (err) {
                console.error(err);
                parkingBioStatus.innerHTML = '<span class="text-rose-600 font-medium"><i data-lucide="video-off" class="w-4 h-4 inline mr-1"></i> Camera access denied.</span>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }

        function stopParkingCamera() {
            if (parkingVideoStream) {
                parkingVideoStream.getTracks().forEach(track => track.stop());
                parkingVideoStream = null;
            }
            isParkingScanning = false;
            parkingVideo.srcObject = null;
            if(parkingScanLine) parkingScanLine.style.display = 'none';
        }

        async function processParkingFrame() {
            if (isParkingScanning || parkingScanComplete || !parkingVideoStream) {
                if (!parkingScanComplete && parkingVideoStream) requestAnimationFrame(processParkingFrame);
                return;
            }

            const now = Date.now();
            if (now - lastParkingScanTime < parkingCooldownMs) {
                requestAnimationFrame(processParkingFrame);
                return;
            }

            isParkingScanning = true;

            const ctx = parkingCanvas.getContext('2d');
            ctx.save();
            if (isParkingMirrored) {
                ctx.translate(parkingCanvas.width, 0);
                ctx.scale(-1, 1);
            }
            ctx.drawImage(parkingVideo, 0, 0, parkingCanvas.width, parkingCanvas.height);
            ctx.restore();
            
            const boxWidth = Math.min(parkingCanvas.width * 0.8, 400);
            const boxHeight = 120;
            const x = (parkingCanvas.width - boxWidth) / 2;
            const y = (parkingCanvas.height - boxHeight) / 2;
            
            const cropCtx = parkingCropCanvas.getContext('2d', { willReadFrequently: true });
            parkingCropCanvas.width = boxWidth;
            parkingCropCanvas.height = boxHeight;
            cropCtx.drawImage(parkingCanvas, x, y, boxWidth, boxHeight, 0, 0, boxWidth, boxHeight);

            let imgData = cropCtx.getImageData(0, 0, boxWidth, boxHeight);
            let pixels = imgData.data;
            
            let totalBrightness = 0;
            for (let i = 0; i < pixels.length; i += 4) {
                totalBrightness += (pixels[i] * 0.299 + pixels[i+1] * 0.587 + pixels[i+2] * 0.114);
            }
            let isDarkMode = (totalBrightness / (boxWidth * boxHeight)) < 110;

            for (let i = 0; i < pixels.length; i += 4) {
                let avg = (pixels[i] * 0.299 + pixels[i+1] * 0.587 + pixels[i+2] * 0.114);
                if (isDarkMode) avg = 255 - avg;
                let c = (avg - 128) * 2.0 + 128;
                c = Math.max(0, Math.min(255, c));
                pixels[i] = c;
                pixels[i+1] = c;
                pixels[i+2] = c;
            }
            cropCtx.putImageData(imgData, 0, 0);

            try {
                const base64Image = parkingCropCanvas.toDataURL('image/jpeg', 0.9);

                const response = await fetch('<?= BASE_URL ?>parking/run_easyocr.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ image: base64Image })
                });

                const result = await response.json();
                
                if (result.status === 'success' && result.text) {
                    const cleanText = result.text.replace(/[^A-Z0-9-]/gi, '').trim();

                    if (cleanText.length > 0) {
                        parkingDebugText.textContent = 'Detected: ' + cleanText;
                        parkingDebugText.classList.remove('opacity-0');
                        setTimeout(() => parkingDebugText.classList.add('opacity-0'), 2000);
                    }
                    
                    if (cleanText.length >= 4) {
                        lastParkingScanTime = Date.now();
                        await verifyParkingPlate(cleanText);
                    }
                }
            } catch (err) {
                console.error("OCR Error:", err);
            }

            isParkingScanning = false;
            requestAnimationFrame(processParkingFrame);
        }

        async function verifyParkingPlate(plateNumber) {
            parkingBioStatus.innerHTML = '<span class="text-emerald-600 font-semibold"><i data-lucide="loader-2" class="w-4 h-4 inline animate-spin mr-1"></i> Verifying Plate...</span>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            
            try {
                const fd = new FormData();
                fd.append('license_plate', plateNumber);

                const response = await fetch('<?= BASE_URL ?>api/api_scan.php', {
                    method: 'POST',
                    body: fd
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    parkingScanComplete = true;
                    parkingVideo.pause();
                    parkingScanLine.style.display = 'none';
                    
                    parkingMatchResult.classList.remove('hidden');
                    void parkingMatchResult.offsetWidth;
                    parkingMatchResult.classList.remove('translate-y-2', 'opacity-0');
                    
                    parkingMatchResult.innerHTML = `
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0"><i data-lucide="check-circle" class="w-6 h-6"></i></div>
                            <div class="text-left flex-1">
                                <div class="text-emerald-700 font-semibold text-xs uppercase tracking-wide">Access Granted</div>
                                <div class="text-slate-900 text-sm font-bold mt-1">${data.message}</div>
                            </div>
                        </div>
                    `;
                    parkingMatchResult.className = 'w-full p-4 rounded-lg opacity-100 transition-all z-20 mt-4 bg-emerald-50 border border-emerald-200';
                    parkingBioStatus.innerHTML = '<span class="text-emerald-700 font-semibold">Verification Complete</span>';
                } else {
                    parkingMatchResult.classList.remove('hidden');
                    void parkingMatchResult.offsetWidth;
                    parkingMatchResult.classList.remove('translate-y-2', 'opacity-0');
                    
                    parkingMatchResult.innerHTML = `
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0"><i data-lucide="x-circle" class="w-6 h-6"></i></div>
                            <div class="text-left flex-1">
                                <div class="text-rose-700 font-semibold text-xs uppercase tracking-wide">Access Denied</div>
                                <div class="text-slate-900 text-sm font-bold mt-1">${data.message}</div>
                            </div>
                        </div>
                    `;
                    parkingMatchResult.className = 'w-full p-4 rounded-lg opacity-100 transition-all z-20 mt-4 bg-rose-50 border border-rose-200';
                    parkingBioStatus.innerHTML = '<span class="text-emerald-700 font-semibold"><i data-lucide="scan" class="w-4 h-4 inline mr-1"></i> Scan active. Please align plate.</span>';
                    
                    setTimeout(() => {
                        if (!parkingScanComplete) {
                            parkingMatchResult.classList.add('translate-y-2', 'opacity-0');
                            setTimeout(() => parkingMatchResult.classList.add('hidden'), 300);
                        }
                    }, 4000);
                }
                if (typeof lucide !== 'undefined') lucide.createIcons();
            } catch (err) {
                console.error(err);
            }
        }

        function toggleParkingScannerModal(show) {
            const modal = document.getElementById('parking-scanner-modal');
            const panel = document.getElementById('parking-scanner-panel');

            if (show) {
                parkingScanComplete = false;
                
                const resultBox = document.getElementById('parking-match-result');
                if (resultBox) {
                    resultBox.className = 'w-full p-4 rounded-lg text-center hidden opacity-0 transition-all transform translate-y-2 z-20 mt-4 border';
                    resultBox.innerHTML = '';
                }

                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.classList.add('opacity-100');
                    modal.classList.remove('opacity-0');
                    panel.classList.replace('scale-95', 'scale-100');
                    panel.classList.replace('opacity-0', 'opacity-100');
                }, 10);

                startParkingCamera();
            } else {
                modal.classList.remove('opacity-100');
                modal.classList.add('opacity-0');
                panel.classList.replace('scale-100', 'scale-95');
                panel.classList.replace('opacity-100', 'opacity-0');

                stopParkingCamera();

                setTimeout(() => {
                    modal.classList.add('hidden');
                }, 300);
            }
        }
    </script>
</body>

</html>