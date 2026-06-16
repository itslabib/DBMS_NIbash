<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_GET['id'])) {
    header('Location: ' . BASE_URL . 'rentals/browse.php');
    exit();
}

$rental_id = intval($_GET['id']);

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
        COALESCE(NULLIF(TRIM(u.email), ''), '') AS account_email,
        rl.verification_doc_path,
        rl.created_at,
        rl.rental_type,
        pd.vehicle_type,
        pd.parking_length,
        pd.parking_width,
        pd.measurement_unit
    FROM rental_listings rl
    LEFT JOIN buildings b ON rl.building_id = b.id
    LEFT JOIN parking_details pd ON pd.listing_id = rl.id
    LEFT JOIN users u ON u.id = rl.owner_id
    LEFT JOIN user_profiles up ON up.user_id = u.id
    WHERE rl.id = ?
";
$stmt = mysqli_prepare($conn, $query);

if (!$stmt) {
    die('Unable to prepare rental query.');
}

mysqli_stmt_bind_param($stmt, 'i', $rental_id);
mysqli_stmt_execute($stmt);
$rental_result = mysqli_stmt_get_result($stmt);
$rental = mysqli_fetch_assoc($rental_result);

if (!$rental) {
    die('Rental not found.');
}

$images = [
    'cover' => [],
    'bedroom' => [],
    'kitchen' => [],
    'living' => [],
    'washroom' => []
];

$img_query = 'SELECT image_category, image_path FROM rental_images WHERE listing_id = ?';
$img_stmt = mysqli_prepare($conn, $img_query);

if ($img_stmt) {
    mysqli_stmt_bind_param($img_stmt, 'i', $rental_id);
    mysqli_stmt_execute($img_stmt);
    $img_res = mysqli_stmt_get_result($img_stmt);

    while ($row = mysqli_fetch_assoc($img_res)) {
        $category = $row['image_category'];
        if (isset($images[$category])) {
            $images[$category][] = $row['image_path'];
        } else {
            $images['cover'][] = $row['image_path'];
        }
    }
}

$all_gallery_images = [];
foreach ($images as $items) {
    foreach ($items as $path) {
        $all_gallery_images[] = $path;
    }
}

$cover_image_path = !empty($images['cover']) ? $images['cover'][0] : '';
$account_email = !empty($rental['account_email']) ? $rental['account_email'] : 'unlinked-account@nibash.local';
$contact_phone = !empty($rental['contact_number']) ? $rental['contact_number'] : 'Not provided';
$contact_email = !empty($rental['contact_email']) ? $rental['contact_email'] : $account_email;
$wa_phone = preg_replace('/[^0-9]/', '', (string) $contact_phone);

$verification_path = '';
$verification_exists = false;
$verification_ext = '';

if (!empty($rental['verification_doc_path'])) {
    $normalized_path = str_replace('\\', '/', trim((string) $rental['verification_doc_path']));
    $normalized_path = preg_replace('#^(\.\./)+#', '', $normalized_path);
    $normalized_path = ltrim($normalized_path, '/');

    if (stripos($normalized_path, 'assets/') !== 0) {
        $normalized_path = 'assets/uploads/verification/' . basename($normalized_path);
    }

    $candidate_primary = __DIR__ . '/../' . $normalized_path;
    $candidate_secondary = __DIR__ . '/' . $normalized_path;

    if (file_exists($candidate_primary) || file_exists($candidate_secondary)) {
        $verification_path = $normalized_path;
        $verification_exists = true;
        $verification_ext = strtolower(pathinfo($verification_path, PATHINFO_EXTENSION));
    }
}

$floor_number = intval($rental['floor_number']);
$floor_mod = $floor_number % 100;
$floor_suffix = ($floor_mod >= 11 && $floor_mod <= 13) ? 'th' : ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'][$floor_number % 10];
$floor_text = $floor_number . $floor_suffix;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($rental['apartment_name']) ?> | EasyHome Rental Details</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?= BASE_URL ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<body class="min-h-screen bg-[#F0FAF4] text-slate-800 antialiased font-sans">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-10 lg:py-12">
        <a href="<?= BASE_URL ?>rentals/browse.php" class="group inline-flex items-center gap-3 rounded-2xl border border-emerald-200 bg-white/85 backdrop-blur-xl px-4 py-2.5 text-slate-900 font-extrabold shadow-[0_12px_28px_-14px_rgba(15,23,42,0.45)] hover:-translate-y-0.5 hover:shadow-[0_20px_30px_-16px_rgba(5,150,105,0.45)] transition-all duration-200">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-100 group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
            </span>
            <span class="tracking-tight">Back to Rentals</span>
        </a>

        <section class="mt-6 bg-white/90 backdrop-blur-2xl rounded-[40px] shadow-2xl border border-emerald-100/60 p-5 sm:p-8 lg:p-10">
            <div class="relative h-72 sm:h-96 lg:h-[460px] rounded-[32px] overflow-hidden border border-emerald-200 bg-slate-900">
                <?php if (!empty($cover_image_path)): ?>
                    <?php $path = $cover_image_path; ?>
                    <img src="<?= BASE_URL . htmlspecialchars($path) ?>" alt="Rental cover image" class="w-full h-full object-cover opacity-90">
                <?php else: ?>
                    <div class="absolute inset-0 flex items-center justify-center bg-slate-100">
                        <i data-lucide="image" class="w-16 h-16 text-slate-300"></i>
                    </div>
                <?php endif; ?>

                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/30 to-transparent"></div>
                <div class="absolute bottom-0 left-0 right-0 p-6 sm:p-8 lg:p-10">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-emerald-600 text-white font-extrabold shadow-lg shadow-emerald-600/20">
                        ৳<?= number_format((float) $rental['rent_amount']) ?>
                        <span class="text-emerald-100 text-sm font-semibold">/ month</span>
                    </div>
                    <h1 class="mt-4 text-3xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight">
                        <?= htmlspecialchars($rental['apartment_name']) ?>
                    </h1>
                    <p class="mt-3 text-emerald-50 font-medium flex items-center gap-2">
                        <i data-lucide="map-pin" class="w-5 h-5 text-emerald-300"></i>
                        <?= htmlspecialchars($rental['address']) ?>
                    </p>
                </div>
            </div>

            <div class="mt-10 grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-10">
                <div class="lg:col-span-2 space-y-8">
                    <article class="bg-white rounded-[32px] border border-slate-200 p-6 sm:p-7 shadow-sm">
                        <h2 class="text-2xl font-black text-slate-950 tracking-tight">Apartment Overview</h2>
                        <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <?php if (($rental['rental_type'] ?? '') === 'parking'): ?>
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-center flex flex-col items-center justify-center">
                                    <p class="text-xs font-bold tracking-wider uppercase text-slate-500">Vehicle</p>
                                    <p class="mt-2 text-xl font-black text-emerald-900 flex items-center justify-center gap-2">
                                        <i data-lucide="<?= ($rental['vehicle_type'] ?? 'car') === 'motorbike' ? 'bike' : 'car' ?>" class="w-6 h-6"></i>
                                        <?= htmlspecialchars(ucfirst($rental['vehicle_type'] ?? 'car')) ?>
                                    </p>
                                </div>
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-center">
                                    <p class="text-xs font-bold tracking-wider uppercase text-slate-500">Length</p>
                                    <p class="mt-2 text-2xl sm:text-3xl font-black text-emerald-900"><?= (float)($rental['parking_length'] ?? 0) ?> <span class="text-sm font-semibold"><?= htmlspecialchars($rental['measurement_unit'] ?? 'feet') ?></span></p>
                                </div>
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-center">
                                    <p class="text-xs font-bold tracking-wider uppercase text-slate-500">Width</p>
                                    <p class="mt-2 text-2xl sm:text-3xl font-black text-emerald-900"><?= (float)($rental['parking_width'] ?? 0) ?> <span class="text-sm font-semibold"><?= htmlspecialchars($rental['measurement_unit'] ?? 'feet') ?></span></p>
                                </div>
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-center">
                                    <p class="text-xs font-bold tracking-wider uppercase text-slate-500">Floor</p>
                                    <p class="mt-2 text-2xl sm:text-3xl font-black text-emerald-900"><?= htmlspecialchars($floor_text) ?></p>
                                </div>
                            <?php else: ?>
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-center">
                                    <p class="text-xs font-bold tracking-wider uppercase text-slate-500">Bedrooms</p>
                                    <p class="mt-2 text-3xl font-black text-emerald-900"><?= intval($rental['total_bedrooms']) ?></p>
                                </div>
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-center">
                                    <p class="text-xs font-bold tracking-wider uppercase text-slate-500">Bathrooms</p>
                                    <p class="mt-2 text-3xl font-black text-emerald-900"><?= intval($rental['washrooms']) ?></p>
                                </div>
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-center">
                                    <p class="text-xs font-bold tracking-wider uppercase text-slate-500">Balconies</p>
                                    <p class="mt-2 text-3xl font-black text-emerald-900"><?= intval($rental['balconies']) ?></p>
                                </div>
                                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-center">
                                    <p class="text-xs font-bold tracking-wider uppercase text-slate-500">Floor</p>
                                    <p class="mt-2 text-3xl font-black text-emerald-900"><?= htmlspecialchars($floor_text) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>

                    <article class="bg-white rounded-[32px] border border-slate-200 p-6 sm:p-7 shadow-sm">
                        <h2 class="text-2xl font-black text-slate-950 tracking-tight">Apartment Description</h2>
                        <p class="mt-4 leading-relaxed text-slate-700 text-[15px] sm:text-base">
                            <?= nl2br(htmlspecialchars($rental['description'])) ?>
                        </p>
                    </article>

                    <article class="bg-white rounded-[32px] border border-slate-200 p-6 sm:p-7 shadow-sm">
                        <h2 class="text-2xl font-black text-slate-950 tracking-tight">Room Gallery</h2>
                        <?php if (!empty($all_gallery_images)): ?>
                            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php foreach ($all_gallery_images as $gallery_img): ?>
                                    <?php $path = $gallery_img; ?>
                                    <div class="rounded-[32px] border border-emerald-200 overflow-hidden bg-slate-100">
                                        <img src="<?= BASE_URL . htmlspecialchars($path) ?>" alt="Room gallery image" class="w-full h-56 sm:h-52 lg:h-56 object-cover">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="mt-4 text-slate-500 font-medium">No room gallery images are available for this listing.</p>
                        <?php endif; ?>
                    </article>

                    <article class="bg-white rounded-[32px] border border-slate-200 p-6 sm:p-7 shadow-sm">
                        <h2 class="text-2xl font-black text-slate-950 tracking-tight">Location Overview</h2>
                        <div class="mt-5 rounded-[28px] border border-emerald-100 p-2 bg-white">
                            <div id="property-map" class="h-80 sm:h-96 w-full rounded-[24px] border border-slate-200"></div>
                        </div>
                        <p class="mt-3 text-xs sm:text-sm text-slate-500 font-medium">Map coordinates are estimated using OpenStreetMap lookup.</p>
                    </article>
                </div>

                <aside class="lg:col-span-1">
                    <div class="sticky top-6 bg-white rounded-[32px] border border-emerald-100 shadow-xl overflow-hidden">
                        <div class="p-6 sm:p-7 border-b border-emerald-100 bg-emerald-50/60">
                            <div class="w-16 h-16 rounded-2xl bg-white border border-emerald-100 flex items-center justify-center text-emerald-700 shadow-sm">
                                <i data-lucide="user-check" class="w-8 h-8"></i>
                            </div>
                            <h3 class="mt-4 text-2xl font-black text-emerald-900">Verified user</h3>
                            <p class="mt-2 text-sm font-bold text-emerald-950">name: <?= htmlspecialchars(!empty($rental['owner_name']) ? $rental['owner_name'] : 'not available') ?></p>
                            <p class="mt-2 text-sm font-semibold text-emerald-900/90">id: <?= htmlspecialchars($account_email) ?></p>
                        </div>

                        <div class="p-6 sm:p-7 space-y-5">
                            <div class="flex items-start gap-3">
                                <div class="w-11 h-11 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 flex items-center justify-center">
                                    <i data-lucide="phone" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-widest font-bold text-slate-500">Phone</p>
                                    <p class="mt-1 text-slate-950 font-bold"><?= htmlspecialchars($rental['contact_number']) ?></p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3">
                                <div class="w-11 h-11 rounded-xl border border-slate-200 bg-slate-50 text-slate-700 flex items-center justify-center">
                                    <i data-lucide="mail" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <p class="text-[11px] uppercase tracking-widest font-bold text-slate-500">Contact Email</p>
                                    <p class="mt-1 text-slate-950 font-bold break-all"><?= htmlspecialchars($contact_email) ?></p>
                                </div>
                            </div>

                            <a href="https://wa.me/<?= htmlspecialchars($wa_phone) ?>" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3.5 transition-colors shadow-lg shadow-emerald-600/20">
                                <i data-lucide="message-circle" class="w-5 h-5"></i>
                                WhatsApp Action
                            </a>

                            <div class="pt-5 border-t border-slate-200">
                                <p class="text-[11px] uppercase tracking-widest font-bold text-slate-500 mb-3">Verification Document</p>
                                <?php if ($verification_exists): ?>
                                    <?php if ($verification_ext === 'pdf'): ?>
                                        <a href="<?= BASE_URL . htmlspecialchars($verification_path) ?>" target="_blank" rel="noopener noreferrer" class="h-24 rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-800 font-bold flex items-center justify-center gap-2 hover:bg-emerald-100 transition-colors">
                                            <i data-lucide="file-text" class="w-5 h-5"></i>
                                            Open PDF Document
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL . htmlspecialchars($verification_path) ?>" target="_blank" rel="noopener noreferrer" class="block rounded-2xl border border-emerald-200 overflow-hidden bg-slate-100">
                                            <img src="<?= BASE_URL . htmlspecialchars($verification_path) ?>" alt="Verification document preview" class="w-full h-36 object-cover">
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="h-24 rounded-2xl border border-slate-200 bg-slate-50 text-slate-500 font-semibold flex items-center justify-center">
                                        Verification file unavailable
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </div>

    <script>
        lucide.createIcons();

        document.addEventListener('DOMContentLoaded', function () {
            const mapContainer = document.getElementById('property-map');
            if (!mapContainer) {
                return;
            }

            const addressStr = "<?= htmlspecialchars($rental['address'], ENT_QUOTES) ?>";
            const areaStr = "<?= htmlspecialchars($rental['area'], ENT_QUOTES) ?>";

            function initMap(lat, lon, zoomLevel = 14) {
                const map = L.map('property-map', { scrollWheelZoom: false }).setView([lat, lon], zoomLevel);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                L.marker([lat, lon]).addTo(map)
                    .bindPopup('<b><?= htmlspecialchars($rental['apartment_name'], ENT_QUOTES) ?></b><br>Approx. Area: ' + areaStr)
                    .openPopup();
            }

            const preciseQuery = `${addressStr}, ${areaStr}, Dhaka, Bangladesh`;

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(preciseQuery)}`)
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    if (Array.isArray(data) && data.length > 0) {
                        initMap(data[0].lat, data[0].lon, 14);
                        return;
                    }

                    const fallbackQuery = `${areaStr}, Dhaka, Bangladesh`;
                    return fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(fallbackQuery)}`)
                        .then(function (res2) { return res2.json(); })
                        .then(function (data2) {
                            if (Array.isArray(data2) && data2.length > 0) {
                                initMap(data2[0].lat, data2[0].lon, 13);
                            } else {
                                initMap(23.8103, 90.4125, 11);
                            }
                        });
                })
                .catch(function () {
                    initMap(23.8103, 90.4125, 11);
                });
        });
    </script>
</body>
</html>
