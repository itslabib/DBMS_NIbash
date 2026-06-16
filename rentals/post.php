<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION["user_id"])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized&redirect=rentals/post.php");
    exit;
}

$success_msg = "";
$error_msg = "";

if (isset($_SESSION["rental_success"])) {
    $success_msg = $_SESSION["rental_success"];
    unset($_SESSION["rental_success"]);
}

if (!function_exists("uploadImages")) {
    function uploadImages($files, $category, $rental_id, $conn, $dir) {
        if (!empty($files["name"][0])) {
            foreach ($files["name"] as $key => $name) {
                if ($files["error"][$key] == 0 && $files["size"][$key] <= 10 * 1024 * 1024) {
                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $new_name = time() . "_" . rand(1000, 9999) . "_$category." . $ext;
                    $target_file = $dir . $new_name;
                    $db_path = "assets/uploads/rentals/" . $new_name;
                    if (move_uploaded_file($files["tmp_name"][$key], $target_file)) {
                        $d = mysqli_prepare($conn, "DELETE FROM rental_images WHERE listing_id = ? AND image_category = ?");
                        mysqli_stmt_bind_param($d, "is", $rental_id, $category);
                        mysqli_stmt_execute($d);
                        $s = mysqli_prepare($conn, "INSERT INTO rental_images (listing_id, image_category, image_path) VALUES (?, ?, ?)");
                        mysqli_stmt_bind_param($s, "iss", $rental_id, $category, $db_path);
                        mysqli_stmt_execute($s);
                    }
                }
            }
        }
    }
}

if (!function_exists("slugifyRentalName")) {
    function slugifyRentalName($value) {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        $value = trim($value, '-');
        return $value !== '' ? $value : 'apartment';
    }
}

// Fetch owner default building info
$user_id_fetch = $_SESSION['user_id'];
$owner_q = mysqli_query($conn,
    "SELECT b.id AS building_id, b.building_name, b.building_number, b.address
     FROM buildings b
     LEFT JOIN building_managers bm ON bm.building_id = b.id
     LEFT JOIN apartments a ON a.building_id = b.id
     LEFT JOIN apartment_assignments aa ON aa.apt_id = a.id
     WHERE bm.user_id = '$user_id_fetch' OR aa.user_id = '$user_id_fetch'
     ORDER BY a.id DESC
     LIMIT 1"
);
$owner_data = mysqli_fetch_assoc($owner_q);
$default_building_name = $owner_data['building_name'] ?? '';
$default_building_number = $owner_data['building_number'] ?? '';
$default_building_address = $owner_data['address'] ?? '';
$default_building_id = $owner_data['building_id'] ?? 0;


$edit_mode = false;
$edit_data = [];
$edit_id   = 0;
if (isset($_GET["edit_id"])) {
    $edit_id   = intval($_GET["edit_id"]);
    $edit_stmt = mysqli_prepare(
        $conn,
        "SELECT rl.id, rl.apt_id, rl.building_id, rl.rental_type, rl.custom_title AS apartment_name, b.address, b.area, rl.description, rl.rent_amount, rl.total_bedrooms, rl.balconies, rl.washrooms, rl.verification_doc_path, rl.floor_number, pd.vehicle_type, pd.parking_length, pd.parking_width, pd.measurement_unit, COALESCE(up.full_name, '') AS owner_name, COALESCE(up.phone, '') AS contact_number, COALESCE(u.email, '') AS contact_email FROM rental_listings rl LEFT JOIN buildings b ON b.id = rl.building_id LEFT JOIN users u ON u.id = rl.owner_id LEFT JOIN user_profiles up ON up.user_id = u.id LEFT JOIN parking_details pd ON pd.listing_id = rl.id WHERE rl.id = ? AND rl.owner_id = ?"
    );
    mysqli_stmt_bind_param($edit_stmt, "ii", $edit_id, $_SESSION["user_id"]);
    mysqli_stmt_execute($edit_stmt);
    $result_edit = mysqli_stmt_get_result($edit_stmt);
    if ($row = mysqli_fetch_assoc($result_edit)) {
        $edit_mode = true;
        $edit_data = $row;
        $edit_images = ['cover' => [], 'gallery' => []];
        $img_stmt = mysqli_prepare($conn, "SELECT image_category, image_path FROM rental_images WHERE listing_id = ?");
        mysqli_stmt_bind_param($img_stmt, "i", $edit_id);
        mysqli_stmt_execute($img_stmt);
        $img_res = mysqli_stmt_get_result($img_stmt);
        while ($img_row = mysqli_fetch_assoc($img_res)) {
            $edit_images[$img_row['image_category']][] = $img_row['image_path'];
        }
        mysqli_stmt_close($img_stmt);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && in_array($_POST["action"], ["post_rental", "update_rental"])) {
    $owner_id        = $_SESSION["user_id"];
    $apartment_name  = trim($_POST["apartment_name"]); // This is now building_name
    $building_number = trim($_POST["building_number"] ?? '');
    $building_id     = intval($_POST["building_id"] ?? 0);
    $area            = trim($_POST["area"]);
    $address         = trim($_POST["address"]);
    $description     = trim($_POST["description"] ?? '');
    $rent_amount     = floatval($_POST["rent_amount"]);
    $rental_type     = $_POST["rental_type"] ?? 'house';
    $total_bedrooms  = $rental_type == 'house' ? intval($_POST["total_bedrooms"] ?? 0) : 0;
    $balconies       = $rental_type == 'house' ? intval($_POST["balconies"] ?? 0) : 0;
    $washrooms       = $rental_type == 'house' ? intval($_POST["washrooms"] ?? 0) : 0;
    $floor_number    = intval($_POST["floor_number"] ?? 0);
    $vehicle_type    = $_POST["vehicle_type"] ?? 'car';
    $parking_length  = floatval($_POST["parking_length"] ?? 0);
    $parking_width   = floatval($_POST["parking_width"] ?? 0);
    $measurement_unit= $_POST["measurement_unit"] ?? 'feet';

    $upload_dir_rentals      = "../assets/uploads/rentals/";
    $upload_dir_verification = "../assets/uploads/verification/";

    $verification_path = "";
    if (isset($_FILES["verification_doc"]) && $_FILES["verification_doc"]["error"] == 0) {
        if ($_FILES["verification_doc"]["size"] > 10 * 1024 * 1024) {
            $error_msg = "Verification document exceeds 10MB.";
        } else {
            $fn                = time() . "_verif_" . basename($_FILES["verification_doc"]["name"]);
            $verification_path = "assets/uploads/verification/" . $fn;
            move_uploaded_file($_FILES["verification_doc"]["tmp_name"], $upload_dir_verification . $fn);
        }
    }

    if (empty($error_msg)) {
        if ($_POST["action"] == "update_rental" && isset($_POST["rental_id"])) {
            $rental_id = intval($_POST["rental_id"]);
            
            // Update rental_listings table directly (No dummy apartments needed!)
            if (!empty($verification_path)) {
                $stmt = mysqli_prepare($conn, "UPDATE rental_listings SET building_id=?, floor_number=?, custom_title=?, rental_type=?, description=?, rent_amount=?, total_bedrooms=?, balconies=?, washrooms=?, verification_doc_path=? WHERE id=? AND owner_id=?");
                mysqli_stmt_bind_param($stmt, "iisssdiiisii", $building_id, $floor_number, $apartment_name, $rental_type, $description, $rent_amount, $total_bedrooms, $balconies, $washrooms, $verification_path, $rental_id, $owner_id);
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE rental_listings SET building_id=?, floor_number=?, custom_title=?, rental_type=?, description=?, rent_amount=?, total_bedrooms=?, balconies=?, washrooms=? WHERE id=? AND owner_id=?");
                mysqli_stmt_bind_param($stmt, "iisssdiiiii", $building_id, $floor_number, $apartment_name, $rental_type, $description, $rent_amount, $total_bedrooms, $balconies, $washrooms, $rental_id, $owner_id);
            }
            
            if (mysqli_stmt_execute($stmt)) {
                if ($rental_type == 'parking') {
                    $p_stmt = mysqli_prepare($conn, "INSERT INTO parking_details (listing_id, vehicle_type, parking_length, parking_width, measurement_unit) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE vehicle_type=VALUES(vehicle_type), parking_length=VALUES(parking_length), parking_width=VALUES(parking_width), measurement_unit=VALUES(measurement_unit)");
                    mysqli_stmt_bind_param($p_stmt, "isdds", $rental_id, $vehicle_type, $parking_length, $parking_width, $measurement_unit);
                    mysqli_stmt_execute($p_stmt);
                } else {
                    $p_stmt = mysqli_prepare($conn, "DELETE FROM parking_details WHERE listing_id = ?");
                    mysqli_stmt_bind_param($p_stmt, "i", $rental_id);
                    mysqli_stmt_execute($p_stmt);
                }
                if (isset($_FILES["cover_image"])) uploadImages($_FILES["cover_image"], "cover", $rental_id, $conn, $upload_dir_rentals);
                if (isset($_FILES["gallery_images"])) uploadImages($_FILES["gallery_images"], "gallery", $rental_id, $conn, $upload_dir_rentals);
                $_SESSION["rental_success"] = "Listing updated successfully.";
                header("Location: ../rentals/browse.php"); exit;
            } else { $error_msg = "DB Error: " . mysqli_error($conn); }
        } else {
            // New Post - Insert directly into rental_listings
            $is_verified = (($_SESSION['role_id'] ?? 0) == 1) ? 1 : 0;
            $stmt = mysqli_prepare($conn, "INSERT INTO rental_listings (building_id, floor_number, custom_title, owner_id, rental_type, description, rent_amount, total_bedrooms, balconies, washrooms, verification_doc_path, is_verified) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, "iisisssdiiisi", $building_id, $floor_number, $apartment_name, $owner_id, $rental_type, $description, $rent_amount, $total_bedrooms, $balconies, $washrooms, $verification_path, $is_verified);
            if (mysqli_stmt_execute($stmt)) {
                $rental_id = mysqli_insert_id($conn);
                if ($rental_type == 'parking') {
                    $p_stmt = mysqli_prepare($conn, "INSERT INTO parking_details (listing_id, vehicle_type, parking_length, parking_width, measurement_unit) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($p_stmt, "isdds", $rental_id, $vehicle_type, $parking_length, $parking_width, $measurement_unit);
                    mysqli_stmt_execute($p_stmt);
                }
                
                // We no longer insert into apartment_assignments, keeping residents' primary apartments clean!
                
                if (isset($_FILES["cover_image"])) uploadImages($_FILES["cover_image"], "cover", $rental_id, $conn, $upload_dir_rentals);
                if (isset($_FILES["gallery_images"])) uploadImages($_FILES["gallery_images"], "gallery", $rental_id, $conn, $upload_dir_rentals);
                $_SESSION["rental_success"] = "Your listing has been successfully posted!";
                header("Location: ../rentals/browse.php"); exit;
            } else { $error_msg = "DB Error: " . mysqli_error($conn); }
        }
    }
}

$ev = function($k) use ($edit_data) { return htmlspecialchars($edit_data[$k] ?? ""); };
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
    <title><?= $edit_mode ? "Update Listing" : "Post Rental" ?> | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <?php if (isset($_SESSION["role_id"]) && $_SESSION["role_id"] == 1): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <?php else: ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <?php endif; ?>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #ecfdf5; }
        ::-webkit-scrollbar-thumb { background: #a7f3d0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6ee7b7; }
        .uslot input[type="file"]{display:none;}
        .step-hidden { display: none; }
        .step-indicator { transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php 
    if (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1) {
        include '../includes/owner_sidebar.php';
    } else {
        include '../includes/resident_sidebar.php';
    } 
    ?>

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
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Asset Management</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto w-full bg-slate-50/50">
                <div class="max-w-5xl mx-auto">

                    <?php if ($error_msg): ?>
                        <script>document.addEventListener("DOMContentLoaded",function(){if(typeof showCustomPopup==="function")showCustomPopup("<?= addslashes($error_msg) ?>","error");});</script>
                    <?php endif; ?>
                    
                    <div class="flex items-center mb-10">
                        <a href="<?php echo BASE_URL; ?>rentals/browse.php" class="flex items-center gap-2 text-sm font-black text-slate-500 hover:text-emerald-600 transition-colors">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Marketplace
                        </a>
                    </div>

                    <div class="mb-10 text-center">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight mb-3">
                            <?= $edit_mode ? "Update Listing" : "Post a Rental" ?>
                        </h1>
                        <p class="text-slate-500 font-medium text-sm sm:text-base">
                            <?= $edit_mode ? "Modify your asset listing details below." : "Register and list your apartment on the Nibash marketplace." ?>
                            <?php if ($edit_mode): ?>
                                <br><span class="inline-block mt-2 font-mono font-black text-emerald-700 text-xs bg-emerald-100 border border-emerald-200 px-3 py-1 rounded-lg shadow-sm">#NB-<?= str_pad($edit_data['id'], 5, '0', STR_PAD_LEFT) ?></span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <?php if ($success_msg): ?>
                        <div class="bg-white border border-emerald-200 rounded-[2rem] p-8 text-center shadow-[0_8px_30px_-6px_rgba(16,185,129,0.15)] relative overflow-hidden mb-8">
                            <div class="absolute top-0 left-0 w-full h-1 bg-emerald-400"></div>
                            <div class="w-20 h-20 bg-emerald-50 border-4 border-emerald-100 rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm">
                                <i data-lucide="check-circle" class="w-8 h-8 text-emerald-500"></i>
                            </div>
                            <h2 class="text-2xl font-black text-slate-900 mb-2">Success!</h2>
                            <p class="text-slate-500 font-medium mb-6"><?= htmlspecialchars($success_msg) ?></p>
                            <a href="<?php echo BASE_URL; ?>rentals/browse.php" class="inline-flex px-8 py-3.5 bg-slate-900 hover:bg-emerald-600 text-white font-black text-sm rounded-xl transition-colors shadow-md">View Marketplace</a>
                        </div>
                    <?php endif; ?>

                    <form id="postForm" x-data="{ rentalType: '<?= $edit_mode ? ($ev('rental_type') ?: 'house') : 'house' ?>', unit: '<?= $edit_mode ? ($ev('measurement_unit') ?: 'feet') : 'feet' ?>' }" action="<?php echo BASE_URL; ?>rentals/post.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?= $edit_mode ? "update_rental" : "post_rental" ?>">
                        <?php if ($edit_mode): ?><input type="hidden" name="rental_id" value="<?= $ev('id') ?>"><?php endif; ?>

                        <div class="flex items-center justify-center mb-10 overflow-x-auto pb-2">
                            <div id="ind-step-1" class="step-indicator flex items-center gap-2 text-emerald-700 font-black text-sm whitespace-nowrap">
                                <span id="circle-step-1" class="w-6 h-6 rounded-full border-2 border-emerald-600 bg-emerald-600 text-white flex items-center justify-center text-xs transition-colors">1</span>
                                Property Details & Specs
                            </div>
                            <div class="w-8 sm:w-16 md:w-20 h-[2px] bg-slate-200 mx-2 sm:mx-3 md:mx-4"></div>

                            <div id="ind-step-2" class="step-indicator flex items-center gap-2 text-slate-400 font-black text-sm whitespace-nowrap">
                                <span id="circle-step-2" class="w-6 h-6 rounded-full border-2 border-slate-200 bg-transparent flex items-center justify-center text-xs transition-colors">2</span>
                                Media & Verify
                            </div>
                        </div>

                        <div id="step-1-content" class="space-y-8">
                            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
                                <div class="bg-white border-b border-slate-100 px-8 py-6 flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center shadow-sm">
                                        <i data-lucide="building-2" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-black text-slate-900">1. Property Identity</h2>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mt-1">Basic Location & Pricing</p>
                                    </div>
                                </div>
                                
                                <div class="p-8">
                                    <div class="mb-8">
                                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-3">What are you listing? <span class="text-emerald-500">*</span></label>
                                        <div class="flex gap-4">
                                            <label class="cursor-pointer relative flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl border-2 transition-all font-bold text-sm w-1/2 shadow-sm"
                                                :class="rentalType === 'house' ? 'border-emerald-500 bg-emerald-50 text-emerald-800' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'">
                                                <input type="radio" name="rental_type" value="house" x-model="rentalType" class="hidden">
                                                <i data-lucide="home" class="w-4 h-4"></i> House / Apartment
                                            </label>
                                            <label class="cursor-pointer relative flex items-center justify-center gap-2 px-6 py-3.5 rounded-xl border-2 transition-all font-bold text-sm w-1/2 shadow-sm"
                                                :class="rentalType === 'parking' ? 'border-indigo-500 bg-indigo-50 text-indigo-800' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50'">
                                                <input type="radio" name="rental_type" value="parking" x-model="rentalType" class="hidden">
                                                <i data-lucide="car" class="w-4 h-4"></i> Parking Slot
                                            </label>
                                        </div>
                                    </div>
                                    <?php $form_building_name = $edit_mode ? $ev('apartment_name') : (htmlspecialchars($default_building_name) ?: htmlspecialchars($default_building_number)); ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <div class="md:col-span-2">
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2" x-text="rentalType === 'house' ? 'Building Name' : 'Building / Location Name'"></label>
                                            <input type="text" name="apartment_name" value="<?= $form_building_name ?>" required placeholder="e.g. Skyline Residency"
                                                class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                            <input type="hidden" name="building_number" value="<?= $edit_mode ? htmlspecialchars($edit_data['building_number'] ?? '') : htmlspecialchars($default_building_number) ?>">
                                            <input type="hidden" name="building_id" value="<?= htmlspecialchars($default_building_id) ?>">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Area for Verification <span class="text-emerald-500">*</span></label>
                                            <div class="flex gap-2 mb-2">
                                                <input type="text" id="area_input" name="area" value="<?= $ev('area') ?>" required placeholder="e.g. West Agargaon, Dhaka"
                                                    class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                                <button type="button" onclick="verifyLocation()" class="px-5 py-3.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-sm font-black rounded-xl transition-colors whitespace-nowrap border border-indigo-200">Verify</button>
                                            </div>
                                            <p id="area_error" class="text-xs text-red-500 hidden mt-1 font-bold"></p>
                                            <p id="area_success" class="text-xs text-emerald-600 hidden mt-1 font-bold flex items-center gap-1"><i data-lucide="check-circle" class="w-3 h-3"></i> Location verified!</p>
                                            <input type="hidden" id="area_verified" value="<?= $edit_mode ? "1" : "0" ?>">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Detailed Address <span class="text-emerald-500">*</span></label>
                                            <input type="text" name="address" value="<?= $edit_mode ? $ev('address') : htmlspecialchars($default_building_address) ?>" required placeholder="e.g. National Parliament Secretariat Residential Complex, West Agargaon, Dhaka"
                                                class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                        </div>
                                        <div class="md:col-span-2">
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Rent / Month <span class="text-emerald-500">*</span></label>
                                            <div class="relative">
                                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm font-black text-slate-400 pointer-events-none">Tk</span>
                                                <input type="number" name="rent_amount" value="<?= $ev('rent_amount') ?>" required placeholder="25000"
                                                    class="w-full pl-10 pr-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 outline-none transition-all text-sm font-black text-slate-800 shadow-sm">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
                                <div class="bg-white border-b border-slate-100 px-8 py-6 flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center shadow-sm">
                                        <i data-lucide="layout-grid" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-black text-slate-900">2. Unit Metrics</h2>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mt-1">Rooms & Specifications</p>
                                    </div>
                                </div>
                                
                                <div class="p-8">
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-8">
                                        <template x-if="rentalType === 'house'">
                                            <div class="contents">
                                                <?php foreach([['total_bedrooms','Bedrooms','3'],['washrooms','Bathrooms','2'],['balconies','Balconies','1']] as [$fn,$lb,$ph]): ?>
                                                <div>
                                                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2"><?= $lb ?> <span class="text-emerald-500">*</span></label>
                                                    <input type="number" name="<?= $fn ?>" value="<?= $ev($fn) ?>" required min="0" placeholder="<?= $ph ?>"
                                                        class="w-full px-5 py-3.5 text-center bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-blue-400 focus:ring-4 focus:ring-blue-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </template>
                                        
                                        <template x-if="rentalType === 'parking'">
                                            <div class="contents">
                                                <div>
                                                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Vehicle Type <span class="text-emerald-500">*</span></label>
                                                    <select name="vehicle_type" required class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-blue-400 focus:ring-4 focus:ring-blue-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm appearance-none">
                                                        <option value="car" <?= $ev('vehicle_type') == 'car' ? 'selected' : '' ?>>Car</option>
                                                        <option value="motorbike" <?= $ev('vehicle_type') == 'motorbike' ? 'selected' : '' ?>>Motorbike</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Length <span class="text-emerald-500">*</span></label>
                                                    <div class="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl focus-within:bg-white focus-within:border-blue-400 focus-within:ring-4 focus-within:ring-blue-500/10 transition-all shadow-sm">
                                                        <input type="number" name="parking_length" value="<?= $ev('parking_length') ?>" required min="0" step="0.1" placeholder="15"
                                                            class="w-full pl-5 pr-16 py-3.5 bg-transparent border-none outline-none text-center text-sm font-bold text-slate-800 focus:ring-0">
                                                        <select name="measurement_unit" x-model="unit" class="absolute right-1.5 bg-slate-200/60 hover:bg-slate-300/80 border-none rounded-lg text-[10px] font-black uppercase text-slate-700 focus:ring-0 py-1.5 pl-2 pr-6 cursor-pointer transition-colors">
                                                            <option value="feet">FT</option>
                                                            <option value="meter">M</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Width <span class="text-emerald-500">*</span></label>
                                                    <div class="relative flex items-center bg-slate-50 border border-slate-200 rounded-xl focus-within:bg-white focus-within:border-blue-400 focus-within:ring-4 focus-within:ring-blue-500/10 transition-all shadow-sm">
                                                        <input type="number" name="parking_width" value="<?= $ev('parking_width') ?>" required min="0" step="0.1" placeholder="8"
                                                            class="w-full pl-5 pr-12 py-3.5 bg-transparent border-none outline-none text-center text-sm font-bold text-slate-800 focus:ring-0">
                                                        <div class="absolute right-4 text-[10px] font-black text-slate-400 pointer-events-none uppercase" x-text="unit === 'feet' ? 'FT' : 'M'"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>

                                        <div>
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Floor <span class="text-emerald-500" x-show="rentalType === 'house'">*</span></label>
                                            <input type="number" name="floor_number" value="<?= $ev('floor_number') ?>" x-bind:required="rentalType === 'house'" placeholder="e.g. 4 or -1"
                                                class="w-full px-5 py-3.5 text-center bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-blue-400 focus:ring-4 focus:ring-blue-500/10 outline-none transition-all text-sm font-bold text-slate-800 shadow-sm">
                                        </div>

                                        <div class="col-span-2 sm:col-span-4 mt-2">
                                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Description</label>
                                            <textarea name="description" rows="4" placeholder="Key features, nearby facilities, rules..."
                                                class="w-full px-5 py-4 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-blue-400 focus:ring-4 focus:ring-blue-500/10 outline-none transition-all text-sm font-medium resize-none text-slate-800 shadow-sm"><?= $ev('description') ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 flex justify-end">
                                <button type="button" onclick="nextStep(1, 2)" class="px-10 py-3.5 bg-slate-900 hover:bg-emerald-600 text-white font-black text-sm rounded-xl shadow-md transition-all flex items-center gap-2 group">
                                    Next Step <i data-lucide="arrow-right" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                                </button>
                            </div>
                        </div>

                        <div id="step-2-content" class="step-hidden space-y-8">
                            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
                                <div class="bg-white border-b border-slate-100 px-8 py-6 flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-amber-50 border border-amber-100 text-amber-600 flex items-center justify-center shadow-sm">
                                        <i data-lucide="image" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-black text-slate-900">2. Property Images</h2>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mt-1">Visual Preview</p>
                                    </div>
                                </div>
                                
                                <div class="p-8">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                        <label class="uslot block relative cursor-pointer group bg-emerald-50 border-2 border-emerald-200 hover:border-emerald-400 rounded-[1.5rem] p-6 transition-all min-h-[140px] flex flex-col items-center justify-center overflow-hidden shadow-sm">
                                            <input type="file" name="cover_image[]" accept="image/*" <?= $edit_mode?"":"required" ?> onchange="prevSlot(this,'p0')">
                                            <div id="p0" class="flex flex-col items-center gap-2 pointer-events-none relative z-10 w-full h-full justify-center text-center">
                                                <?php if ($edit_mode && !empty($edit_images['cover'][0])): ?>
                                                    <img src="<?= BASE_URL . htmlspecialchars($edit_images['cover'][0]) ?>" class="w-full h-full object-cover rounded-[1.25rem] absolute inset-0 z-0">
                                                <?php else: ?>
                                                    <i data-lucide="image" class="w-8 h-8 text-emerald-500 group-hover:scale-110 transition-transform"></i>
                                                    <span class="text-[10px] font-black uppercase tracking-widest text-emerald-700">Cover Image <span class="text-emerald-500">*</span></span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                        
                                        <label class="uslot block relative cursor-pointer group bg-slate-50 border-2 border-dashed border-slate-300 hover:border-amber-400 hover:bg-amber-50 rounded-[1.5rem] p-6 transition-all min-h-[140px] flex flex-col items-center justify-center overflow-hidden shadow-sm">
                                            <input type="file" name="gallery_images[]" accept="image/*" multiple onchange="prevSlotGallery(this,'p1')">
                                            <div id="p1" class="flex flex-col items-center gap-2 pointer-events-none relative z-10 w-full h-full justify-center text-center">
                                                <?php if ($edit_mode && !empty($edit_images['gallery'])): ?>
                                                    <div class="flex flex-col items-center justify-center h-full w-full bg-amber-100 rounded-[1.25rem] absolute inset-0 z-0 border border-amber-200">
                                                        <i data-lucide="images" class="w-6 h-6 text-amber-600 mb-1"></i>
                                                        <span class="text-xs font-bold text-amber-800"><?= count($edit_images['gallery']) ?> images selected</span>
                                                    </div>
                                                <?php else: ?>
                                                    <i data-lucide="images" class="w-8 h-8 text-slate-400 group-hover:text-amber-500 group-hover:scale-110 transition-transform"></i>
                                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-500 group-hover:text-amber-600">Gallery Images (Multiple)</span>
                                                <?php endif; ?>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden">
                                <div class="bg-white border-b border-slate-100 px-8 py-6 flex items-center gap-4">
                                    <div class="w-10 h-10 rounded-xl bg-rose-50 border border-rose-100 text-rose-600 flex items-center justify-center shadow-sm">
                                        <i data-lucide="file-check-2" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-black text-slate-900">3. Verification</h2>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-500 mt-1">Proof of Ownership</p>
                                    </div>
                                </div>
                                
                                <div class="p-8">
                                    <div class="bg-rose-50/50 border-2 border-rose-100 rounded-[1.5rem] p-6 relative overflow-hidden flex flex-col sm:flex-row items-center gap-6 shadow-sm">
                                        <div class="absolute -right-4 -top-4 text-rose-200/40 pointer-events-none"><i data-lucide="file-check-2" class="w-32 h-32"></i></div>
                                        <div class="w-16 h-16 bg-rose-100 rounded-2xl flex items-center justify-center shrink-0 relative z-10 border border-rose-200 shadow-sm">
                                            <i data-lucide="file-check-2" class="w-8 h-8 text-rose-600"></i>
                                        </div>
                                        <div class="flex-1 relative z-10 text-center sm:text-left">
                                            <label class="block text-xs font-black text-rose-800 uppercase tracking-widest mb-1">Verification Document <span class="text-rose-500">*</span></label>
                                            <p class="text-xs text-rose-600/80 font-bold mb-4">Upload Utility Bill or NID (JPG/PNG/PDF). Max 10MB.<?= $edit_mode?" Leave empty to keep existing.":"" ?></p>
                                            <input type="file" name="verification_doc" accept=".jpg,.jpeg,.png,.pdf" <?= $edit_mode?"":"required" ?>
                                                class="w-full text-xs font-bold text-rose-800 file:mr-4 file:py-2.5 file:px-5 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-rose-600 file:text-white hover:file:bg-rose-700 cursor-pointer transition-colors shadow-sm">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row items-center justify-between gap-6 pt-4 border-t border-slate-200">
                                <p class="text-xs font-bold text-slate-500 flex items-center gap-2">
                                    <span class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-100 flex items-center justify-center shadow-sm">
                                        <i data-lucide="shield-check" class="w-4 h-4 text-emerald-500"></i>
                                    </span>
                                    All data is encrypted and processed securely.
                                </p>
                                
                                <div class="flex items-center gap-3 w-full sm:w-auto">
                                    <button type="button" onclick="prevStep(2, 1)" class="w-full sm:w-auto px-10 py-3.5 bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 font-black text-sm rounded-xl shadow-sm transition-colors flex items-center justify-center gap-2">
                                        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
                                    </button>
                                    <button type="submit" onclick="return validateFinalStep()" class="flex-1 sm:flex-none px-10 py-3.5 bg-slate-900 hover:bg-emerald-600 text-white font-black text-sm rounded-xl shadow-md hover:shadow-lg hover:shadow-emerald-500/30 transition-all flex items-center justify-center gap-2 group cursor-pointer">
                                        <?= $edit_mode ? "Commit Updates" : "Submit Listing" ?> <i data-lucide="check-circle" class="w-4 h-4 group-hover:scale-110 transition-transform"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();

        // Image Preview logic
        function prevSlot(input, id) {
            const el = document.getElementById(id);
            if(!el || !input.files || !input.files[0]) return;
            const r = new FileReader();
            r.onload = e => {
                el.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover rounded-[1.25rem] absolute inset-0 z-0">`;
            };
            r.readAsDataURL(input.files[0]);
        }

        // Stepper validation and navigation logic
        function validateCurrentStep(stepNumber) {
            const currentStep = document.getElementById(`step-${stepNumber}-content`);
            const inputs = currentStep.querySelectorAll('input[required], textarea[required], select[required]');
            let isValid = true;

            inputs.forEach(input => {
                if (!input.checkValidity()) {
                    input.reportValidity();
                    isValid = false;
                }
            });

            return isValid;
        }

        function prevSlotGallery(input, id) {
            const el = document.getElementById(id);
            if(!el || !input.files || input.files.length === 0) return;
            const count = input.files.length;
            el.innerHTML = `<div class="flex flex-col items-center justify-center h-full w-full bg-amber-100 rounded-[1.25rem] absolute inset-0 z-0 border border-amber-200"><i data-lucide="images" class="w-6 h-6 text-amber-600 mb-1"></i><span class="text-xs font-bold text-amber-800">${count} images selected</span></div>`;
            lucide.createIcons();
        }

        async function verifyLocation() {
            const areaInput = document.getElementById('area_input').value.trim();
            const addrError = document.getElementById('area_error');
            const addrSuccess = document.getElementById('area_success');
            
            if (!areaInput) {
                addrError.textContent = "Please enter an area to verify.";
                addrError.classList.remove('hidden');
                addrSuccess.classList.add('hidden');
                return;
            }

            addrError.classList.add('hidden');
            addrSuccess.classList.add('hidden');
            document.getElementById('area_verified').value = "0";

            try {
                // Geocode via Nominatim
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(areaInput)}&countrycodes=bd`);
                const data = await res.json();
                
                if (data && data.length > 0) {
                    document.getElementById('area_verified').value = "1";
                    addrSuccess.classList.remove('hidden');
                } else {
                    addrError.textContent = `Location not found in Bangladesh. Please add a larger area like your city/district (e.g., "${areaInput}, Dhaka").`;
                    addrError.classList.remove('hidden');
                }
            } catch(e) {
                addrError.textContent = "Error verifying location. Please try again.";
                addrError.classList.remove('hidden');
            }
        }

        function updateStepperUI(currentStep) {
            for (let i = 1; i <= 2; i++) {
                document.getElementById(`ind-step-${i}`).classList.replace('text-emerald-700', 'text-slate-400');
                const circle = document.getElementById(`circle-step-${i}`);
                circle.classList.remove('border-emerald-600', 'bg-emerald-600', 'text-white', 'bg-emerald-50', 'text-emerald-600');
                circle.classList.add('border-slate-200', 'bg-transparent');
                circle.innerHTML = i;
            }

            for (let i = 1; i < currentStep; i++) {
                document.getElementById(`ind-step-${i}`).classList.replace('text-slate-400', 'text-emerald-700');
                const circle = document.getElementById(`circle-step-${i}`);
                circle.classList.remove('border-slate-200', 'bg-transparent');
                circle.classList.add('border-emerald-600', 'bg-emerald-600', 'text-white');
                circle.innerHTML = '<i data-lucide="check" class="w-3 h-3"></i>';
            }

            document.getElementById(`ind-step-${currentStep}`).classList.replace('text-slate-400', 'text-emerald-700');
            const activeCircle = document.getElementById(`circle-step-${currentStep}`);
            activeCircle.classList.remove('border-slate-200', 'bg-transparent');
            activeCircle.classList.add('border-emerald-600', 'bg-emerald-600', 'text-white');

            lucide.createIcons();
        }

        function nextStep(current, next) {
            if (validateCurrentStep(current)) {
                document.getElementById(`step-${current}-content`).classList.add('step-hidden');
                document.getElementById(`step-${next}-content`).classList.remove('step-hidden');
                updateStepperUI(next);
                document.querySelector('.overflow-y-auto').scrollTo(0, 0);
            }
        }

        function prevStep(current, prev) {
            document.getElementById(`step-${current}-content`).classList.add('step-hidden');
            document.getElementById(`step-${prev}-content`).classList.remove('step-hidden');
            updateStepperUI(prev);
            document.querySelector('.overflow-y-auto').scrollTo(0, 0);
        }

        function validateFinalStep() {
            if (document.getElementById('area_verified').value === "0") {
                document.getElementById('area_error').textContent = "Please verify your location first.";
                document.getElementById('area_error').classList.remove('hidden');
                prevStep(2, 1);
                return false;
            }
            return validateCurrentStep(2);
        }
    </script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>