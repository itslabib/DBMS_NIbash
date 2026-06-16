<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4) {
    header("Location: ../login.php?error=unauthorized");
    exit();
}
$user_id = $_SESSION['user_id'];

$success_msg = '';
$error_msg = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_coverage'])) {
        $coverage_radius = intval($_POST['coverage_radius']);
        $address = mysqli_real_escape_string($conn, trim($_POST['address']));
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : 'NULL';
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : 'NULL';
        
        $up_sql = "UPDATE user_profiles SET address='$address', latitude=$latitude, longitude=$longitude WHERE user_id='$user_id'";
        $sp_sql = "UPDATE service_providers SET address='$address', latitude=$latitude, longitude=$longitude, coverage_radius=$coverage_radius WHERE user_id='$user_id'";
        
        if (mysqli_query($conn, $up_sql) && mysqli_query($conn, $sp_sql)) {
            $success_msg = "Coverage area and location updated successfully.";
        } else {
            $error_msg = "Failed to update coverage area.";
        }
    }

    if (isset($_POST['update_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];

        $u_q = mysqli_query($conn, "SELECT password FROM users WHERE id='$user_id'");
        $u_r = mysqli_fetch_assoc($u_q);

        if (!password_verify($current, $u_r['password'])) {
            $error_msg = "Incorrect current password.";
        } elseif ($new !== $confirm) {
            $error_msg = "New passwords do not match.";
        } elseif (strlen($new) < 6) {
            $error_msg = "New password must be at least 6 characters long.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            if (mysqli_query($conn, "UPDATE users SET password='$hash' WHERE id='$user_id'")) {
                $success_msg = "Password updated successfully.";
            } else {
                $error_msg = "Failed to update password.";
            }
        }
    }
}

// Fetch provider's current profile data
$sp_q = mysqli_query($conn, "SELECT id, address, latitude, longitude, coverage_radius, name FROM service_providers WHERE user_id = '$user_id'");
$sp_data = mysqli_fetch_assoc($sp_q);

$lat = $sp_data['latitude'] ?? 23.8103;
$lng = $sp_data['longitude'] ?? 90.4125;
$provider_id = $sp_data['id'] ?? 0;
$provider_name = $sp_data['name'] ?? 'Provider';

// Fetch Reviews
$reviews = [];
$avg_rating = 0;
$total_reviews = 0;

if ($provider_id > 0) {
    $rev_q = mysqli_query($conn, "
        SELECT pr.*, p.full_name as resident_name 
        FROM provider_reviews pr
        JOIN users u ON pr.resident_id = u.id
        LEFT JOIN user_profiles p ON u.id = p.user_id
        WHERE pr.provider_id = '$provider_id'
        ORDER BY pr.created_at DESC
    ");
    while ($r = mysqli_fetch_assoc($rev_q)) {
        $reviews[] = $r;
    }
    
    $rating_q = mysqli_query($conn, "SELECT AVG(rating) as avg_r, COUNT(id) as c FROM provider_reviews WHERE provider_id = '$provider_id'");
    $rating_res = mysqli_fetch_assoc($rating_q);
    $avg_rating = round($rating_res['avg_r'], 1) ?? 0;
    $total_reviews = $rating_res['c'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Management - Provider - Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            400: '#2dd4bf',
                            500: '#14b8a6',
                            600: '#0d9488',
                            900: '#134e4a',
                        },
                        slate: {
                            850: '#152033',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.05);
        }
        .nav-tab-active {
            border-bottom-width: 2px;
            border-color: #0d9488;
            color: #0d9488;
            font-weight: 600;
        }
        .nav-tab {
            border-bottom-width: 2px;
            border-color: transparent;
            color: #64748b;
            font-weight: 500;
        }
        .nav-tab:hover {
            color: #0d9488;
            border-color: #99f6e4;
        }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-800 relative">
    
    <!-- Background Decor -->
    <div class="absolute top-0 left-0 w-full h-72 bg-gradient-to-br from-brand-900 via-slate-850 to-slate-900 -z-10 rounded-b-[3rem] shadow-xl"></div>

    <!-- Navbar -->
    <nav class="bg-transparent text-white pt-6 pb-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="p-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/10 backdrop-blur-md transition-all duration-300 text-white/80 hover:text-white">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="font-bold text-2xl tracking-tight">Profile Settings</h1>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 z-10 relative">
        
        <?php if ($success_msg): ?>
            <div class="bg-emerald-50/90 backdrop-blur-md text-emerald-700 p-4 rounded-2xl border border-emerald-200 shadow-sm mb-6 flex items-center">
                <i class="fas fa-check-circle text-emerald-500 text-xl mr-3"></i>
                <span class="font-medium"><?= $success_msg ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($error_msg): ?>
            <div class="bg-red-50/90 backdrop-blur-md text-red-700 p-4 rounded-2xl border border-red-200 shadow-sm mb-6 flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 text-xl mr-3"></i>
                <span class="font-medium"><?= $error_msg ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Sidebar / Provider Info -->
            <div class="lg:col-span-4 space-y-6">
                <div class="glass-card rounded-3xl p-8 text-center relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-br from-brand-400 to-brand-600"></div>
                    
                    <div class="relative z-10 flex flex-col items-center mt-6">
                        <div class="p-1 bg-white rounded-full shadow-lg mb-4">
                            <img src="https://ui-avatars.com/api/?name=<?= urlencode($provider_name) ?>&size=120&background=0D9488&color=fff" class="w-24 h-24 rounded-full border-4 border-white" alt="Avatar">
                        </div>
                        <h2 class="text-2xl font-bold text-slate-800"><?= htmlspecialchars($provider_name) ?></h2>
                        <p class="text-slate-500 font-medium mt-1 uppercase tracking-widest text-xs">Service Provider</p>
                        
                        <div class="mt-6 flex items-center space-x-2 bg-yellow-50 text-yellow-600 px-4 py-2 rounded-xl border border-yellow-100">
                            <i class="fas fa-star text-yellow-500"></i>
                            <span class="font-bold"><?= $avg_rating ?> Rating</span>
                            <span class="text-yellow-500/70 text-sm">(<?= $total_reviews ?>)</span>
                        </div>
                    </div>
                </div>

                <!-- Navigation Tabs (JS controlled) -->
                <div class="glass-card rounded-3xl p-4 flex flex-col space-y-2">
                    <button onclick="switchTab('location')" id="tab-btn-location" class="flex items-center text-left w-full px-4 py-3 rounded-xl bg-brand-50 text-brand-700 font-semibold transition-all">
                        <i class="fas fa-map-marked-alt w-6"></i> Service Location
                    </button>
                    <button onclick="switchTab('security')" id="tab-btn-security" class="flex items-center text-left w-full px-4 py-3 rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-800 font-medium transition-all">
                        <i class="fas fa-shield-alt w-6"></i> Security Settings
                    </button>
                    <button onclick="switchTab('reviews')" id="tab-btn-reviews" class="flex items-center text-left w-full px-4 py-3 rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-800 font-medium transition-all">
                        <i class="fas fa-comments w-6"></i> Client Reviews
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="lg:col-span-8">
                
                <!-- Tab Content: Service Coverage Area -->
                <div id="tab-location" class="glass-card rounded-3xl shadow-sm border border-slate-100 p-8 transition-all duration-300">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-xl bg-brand-100 text-brand-600 flex items-center justify-center mr-4">
                            <i class="fas fa-location-arrow text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">Service Coverage Validation</h3>
                    </div>
                    
                    <form method="POST" action="" class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Base Location / Address</label>
                            <textarea name="address" rows="2" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all shadow-sm bg-white/50 focus:bg-white"><?= htmlspecialchars($sp_data['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Service Coverage Radius (in KM)</label>
                            <div class="relative w-full md:w-1/2">
                                <input type="number" name="coverage_radius" value="<?= $sp_data['coverage_radius'] ?? 5 ?>" min="1" max="100" class="w-full pl-4 pr-12 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none transition-all shadow-sm bg-white/50 focus:bg-white">
                                <div class="absolute inset-y-0 right-0 pr-4 flex items-center pointer-events-none text-slate-400 font-medium">
                                    KM
                                </div>
                            </div>
                            <p class="text-xs text-slate-500 mt-2 flex items-start"><i class="fas fa-info-circle mr-1.5 mt-0.5 text-brand-500"></i> You will only appear in search results for residents within this radius of your pinned location.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2 flex justify-between">
                                <span>Pin Your Base Location</span>
                                <span class="text-xs text-brand-600 font-medium cursor-pointer hover:underline" onclick="map.setView([<?= $lat ?>, <?= $lng ?>], 13)">Recenter Map</span>
                            </label>
                            <div id="map" class="w-full h-72 border-2 border-slate-200 rounded-2xl mb-2 shadow-inner z-0"></div>
                            <input type="hidden" name="latitude" id="latitude" value="<?= $lat ?>">
                            <input type="hidden" name="longitude" id="longitude" value="<?= $lng ?>">
                        </div>

                        <div class="pt-4 border-t border-slate-100 flex justify-end">
                            <button type="submit" name="update_coverage" class="bg-brand-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-brand-700 shadow-lg shadow-brand-500/30 transition-all hover:-translate-y-0.5">
                                Save Location Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab Content: Password Change -->
                <div id="tab-security" class="hidden glass-card rounded-3xl shadow-sm border border-slate-100 p-8 transition-all duration-300">
                    <div class="flex items-center mb-6">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center mr-4">
                            <i class="fas fa-shield-alt text-xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">Security Settings</h3>
                    </div>
                    
                    <form method="POST" action="" class="space-y-6 md:w-3/4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Current Password</label>
                            <input type="password" name="current_password" required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none shadow-sm bg-white/50 focus:bg-white transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">New Password</label>
                            <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none shadow-sm bg-white/50 focus:bg-white transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Confirm New Password</label>
                            <input type="password" name="confirm_password" required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none shadow-sm bg-white/50 focus:bg-white transition-all">
                        </div>
                        <div class="pt-4 border-t border-slate-100">
                            <button type="submit" name="update_password" class="bg-slate-800 text-white px-8 py-3 rounded-xl font-semibold hover:bg-slate-900 shadow-lg shadow-slate-900/20 transition-all hover:-translate-y-0.5">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tab Content: Reviews -->
                <div id="tab-reviews" class="hidden glass-card rounded-3xl shadow-sm border border-slate-100 p-8 transition-all duration-300">
                    <div class="flex items-center mb-8">
                        <div class="w-10 h-10 rounded-xl bg-yellow-100 text-yellow-600 flex items-center justify-center mr-4">
                            <i class="fas fa-comment-dots text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-slate-800">Client Reviews</h3>
                            <p class="text-sm text-slate-500 font-medium">Feedback from residents who booked your services</p>
                        </div>
                    </div>
                    
                    <?php if (count($reviews) > 0): ?>
                        <div class="space-y-5">
                            <?php foreach($reviews as $rev): ?>
                            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-600 font-bold text-lg border border-slate-200">
                                            <?= substr($rev['resident_name'], 0, 1) ?>
                                        </div>
                                        <div>
                                            <h4 class="font-bold text-slate-800 text-lg"><?= htmlspecialchars($rev['resident_name']) ?></h4>
                                            <p class="text-xs text-slate-400 font-medium"><i class="far fa-calendar-alt mr-1"></i> <?= date('F d, Y', strtotime($rev['created_at'])) ?></p>
                                        </div>
                                    </div>
                                    <div class="flex bg-yellow-50 px-3 py-1 rounded-lg border border-yellow-100">
                                        <?php for($i=1; $i<=5; $i++): ?>
                                            <i class="fas fa-star text-sm mt-0.5 <?= $i <= $rev['rating'] ? 'text-yellow-400' : 'text-yellow-200' ?> <?= $i < 5 ? 'mr-0.5' : '' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="pl-16">
                                    <p class="text-slate-600 text-sm leading-relaxed relative">
                                        <i class="fas fa-quote-left absolute -left-5 top-0.5 text-slate-200 text-lg"></i>
                                        <?= nl2br(htmlspecialchars($rev['review_text'])) ?>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-16 bg-slate-50/50 rounded-2xl border border-dashed border-slate-200">
                            <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-comment-slash text-3xl text-slate-300"></i>
                            </div>
                            <p class="text-slate-600 font-semibold text-lg">No reviews yet.</p>
                            <p class="text-sm text-slate-500 mt-1">When residents review your service, they will appear here.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

    </div>

    <script>
        // Tab switching logic
        function switchTab(tabId) {
            // Hide all tabs
            document.getElementById('tab-location').classList.add('hidden');
            document.getElementById('tab-security').classList.add('hidden');
            document.getElementById('tab-reviews').classList.add('hidden');
            
            // Reset all buttons
            const btns = ['location', 'security', 'reviews'];
            btns.forEach(btn => {
                const el = document.getElementById('tab-btn-' + btn);
                el.className = 'flex items-center text-left w-full px-4 py-3 rounded-xl text-slate-600 hover:bg-slate-100 hover:text-slate-800 font-medium transition-all';
            });
            
            // Show selected tab
            document.getElementById('tab-' + tabId).classList.remove('hidden');
            
            // Activate selected button
            const activeEl = document.getElementById('tab-btn-' + tabId);
            activeEl.className = 'flex items-center text-left w-full px-4 py-3 rounded-xl bg-brand-50 text-brand-700 font-semibold transition-all shadow-inner border border-brand-100';
            
            // Recalculate map size if map tab is shown
            if (tabId === 'location') {
                setTimeout(() => {
                    map.invalidateSize();
                }, 100);
            }
        }

        // Initialize map
        var map = L.map('map').setView([<?= $lat ?>, <?= $lng ?>], 13);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap contributors, &copy; CARTO'
        }).addTo(map);

        // Custom icon
        var providerIcon = L.divIcon({
            html: '<div class="w-10 h-10 bg-brand-600 rounded-full flex items-center justify-center text-white shadow-lg border-2 border-white"><i class="fas fa-tools"></i></div>',
            className: '',
            iconSize: [40, 40],
            iconAnchor: [20, 40]
        });

        var marker = L.marker([<?= $lat ?>, <?= $lng ?>], {icon: providerIcon}).addTo(map);

        map.on('click', function(e) {
            var lat = e.latlng.lat;
            var lng = e.latlng.lng;
            
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            
            if (marker) {
                map.removeLayer(marker);
            }
            marker = L.marker([lat, lng], {icon: providerIcon}).addTo(map);
            
            // Simple animation
            map.flyTo([lat, lng], map.getZoom(), {
                duration: 0.5
            });
        });
    </script>
</body>
</html>