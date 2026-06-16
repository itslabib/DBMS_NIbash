<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$provider_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($provider_id === 0) {
    header("Location: ../essentials/index.php");
    exit();
}

$success_msg = '';
$error_msg = '';

$display_booking_date = isset($_SESSION['last_booking_date']) ? $_SESSION['last_booking_date'] : date('Y-m-d');
unset($_SESSION['last_booking_date']);

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}

if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../vendor/autoload.php';

// Handle Booking Submission for Hourly Providers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_provider'])) {
    $booking_date = mysqli_real_escape_string($conn, trim($_POST['booking_date']));
    $time_slot = mysqli_real_escape_string($conn, trim($_POST['time_slot']));
    
    // Calculate new booking start and end times (1-hour duration)
    $new_start_time = date('H:i:s', strtotime($time_slot));
    $new_end_time = date('H:i:s', strtotime($time_slot) + 3600);

    // Check provider availability
    $prov_query = mysqli_query($conn, "SELECT availability_schedule FROM service_providers WHERE id = '$provider_id'");
    if ($prov_data = mysqli_fetch_assoc($prov_query)) {
        $scheduleParts = explode(' - ', $prov_data['availability_schedule']);
        if (count($scheduleParts) === 2) {
            $avail_start = strtotime($scheduleParts[0]);
            $avail_end = strtotime($scheduleParts[1]);
            
            $req_start = strtotime($time_slot);
            $req_end = $req_start + 3600;
            
            if ($avail_end <= $avail_start) {
                $avail_end += 86400; // crosses midnight
            }
            if ($req_start < $avail_start && ($req_start + 86400) <= $avail_end) {
                $req_start += 86400;
                $req_end += 86400;
            }

            if ($req_start < $avail_start || $req_end > $avail_end) {
                $_SESSION['error_msg'] = "Please select a time within the provider's availability ({$prov_data['availability_schedule']}).";
                header("Location: provider.php?id=$provider_id");
                exit();
            }
        }
    }

    // Check for overlapping bookings
    $conflict_query = "SELECT id FROM provider_bookings 
                       WHERE provider_id = '$provider_id' 
                       AND booking_date = '$booking_date' 
                       AND status = 'Booked' 
                       AND time_slot < '$new_end_time' 
                       AND end_time > '$new_start_time'";
                       
    $check_conflict = mysqli_query($conn, $conflict_query);

    if (mysqli_num_rows($check_conflict) > 0) {
        $_SESSION['error_msg'] = "This provider is already booked around the selected time. Please choose another time slot.";
        $_SESSION['last_booking_date'] = $booking_date;
        header("Location: provider.php?id=$provider_id");
        exit();
    } else {
        $insert_booking = "INSERT INTO provider_bookings (provider_id, resident_id, booking_date, time_slot, end_time, status) VALUES ('$provider_id', '$user_id', '$booking_date', '$time_slot', '$new_end_time', 'Booked')";
        if (mysqli_query($conn, $insert_booking)) {
            
            // Fetch provider and resident details to send email
            $p_query = mysqli_query($conn, "SELECT name, email FROM service_providers WHERE id = '$provider_id'");
            $p_data = mysqli_fetch_assoc($p_query);
            $r_query = mysqli_query($conn, "SELECT full_name FROM user_profiles WHERE user_id = '$user_id'");
            $r_data = mysqli_fetch_assoc($r_query);
            
            if ($p_data && !empty($p_data['email'])) {
                $resident_name = (!empty($r_data['full_name'])) ? $r_data['full_name'] : 'A Resident';
                
                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com'; 
                    $mail->SMTPAuth   = true;
                    $mail->Username   = 'suchak9931@gmail.com'; 
                    $mail->Password   = 'ubaz ayum yhyy hyis';   
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    $mail->setFrom('suchak9931@gmail.com', 'Nibash System');
                    $mail->addAddress($p_data['email'], $p_data['name']);

                    $mail->isHTML(true);
                    $mail->Subject = 'New Booking Alert | Nibash System';
                    $mail->Body    = "<h3>Hello {$p_data['name']},</h3>
                                      <p>You have a new booking from <b>{$resident_name}</b>.</p>
                                      <p><b>Date:</b> {$booking_date}<br>
                                      <b>Time Slot:</b> {$time_slot} to {$new_end_time}</p>
                                      <p>Please make sure to arrive on time. Thank you!</p>";
                    $mail->send();
                } catch (Exception $e) {
                    // Log error or ignore
                }
            }

            $_SESSION['last_booking_date'] = $booking_date;
            $_SESSION['success_msg'] = ($_SESSION['role_id'] != 1) ? "Successfully booked the provider for $booking_date at $time_slot." : "Successfully booked the provider as admin.";
            header("Location: provider.php?id=$provider_id");
            exit();
        } else {
            $_SESSION['last_booking_date'] = $booking_date;
            $_SESSION['error_msg'] = "Failed to book provider. Try again.";
            header("Location: provider.php?id=$provider_id");
            exit();
        }
    }
}

// Handle Review Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $rating = intval($_POST['rating']);
    if ($rating < 1 || $rating > 5) $rating = 5;
    $review_text = mysqli_real_escape_string($conn, trim($_POST['review_text']));
    
    // Check if user already reviewed
    $check_rev = mysqli_query($conn, "SELECT id FROM provider_reviews WHERE provider_id = '$provider_id' AND resident_id = '$user_id'");
    if (mysqli_num_rows($check_rev) > 0) {
        $update_rev = "UPDATE provider_reviews SET rating = '$rating', review_text = '$review_text', created_at = CURRENT_TIMESTAMP WHERE provider_id = '$provider_id' AND resident_id = '$user_id'";
        mysqli_query($conn, $update_rev);
        $_SESSION['success_msg'] = "Review updated successfully.";
    } else {
        $insert_rev = "INSERT INTO provider_reviews (provider_id, resident_id, rating, review_text) VALUES ('$provider_id', '$user_id', '$rating', '$review_text')";
        mysqli_query($conn, $insert_rev);
        $_SESSION['success_msg'] = "Review submitted successfully.";
    }
    
    // Recalculate average rating
    $avg_query = mysqli_query($conn, "SELECT AVG(rating) as avg_rating FROM provider_reviews WHERE provider_id = '$provider_id'");
    if ($avg_data = mysqli_fetch_assoc($avg_query)) {
        $new_avg = number_format((float)$avg_data['avg_rating'], 2, '.', '');
        mysqli_query($conn, "UPDATE service_providers SET rating = '$new_avg' WHERE id = '$provider_id'");
    }
    
    header("Location: provider.php?id=$provider_id");
    exit();
}

// Fetch provider details WITH category name and review count
$query = mysqli_query($conn, "SELECT sp.*, sc.category_name, 
                              (SELECT COUNT(*) FROM provider_reviews WHERE provider_id = sp.id) as total_reviews
                              FROM service_providers sp 
                              LEFT JOIN service_categories sc ON sp.category_id = sc.id 
                              WHERE sp.id = '$provider_id'");
$provider = mysqli_fetch_assoc($query);

if (!$provider) {
    header("Location: ../essentials/index.php");
    exit();
}

// Fetch all reviews for this provider
$reviews_query = mysqli_query($conn, "SELECT pr.*, up.full_name as reviewer_name 
                                      FROM provider_reviews pr 
                                      JOIN users u ON pr.resident_id = u.id 
                                      LEFT JOIN user_profiles up ON u.id = up.user_id 
                                      WHERE pr.provider_id = '$provider_id' 
                                      ORDER BY pr.created_at DESC");
$all_reviews = [];
$my_review = null;
while ($rev = mysqli_fetch_assoc($reviews_query)) {
    if ($rev['resident_id'] == $user_id) {
        $my_review = $rev;
    }
    $all_reviews[] = $rev;
}

// Check if this is an ISP (We display a different UI instead of hourly booking)
$is_isp = (strcasecmp($provider['category_name'], 'Internet Provider') === 0);

// Check if Maid / Housekeeper
$is_maid = (stripos($provider['category_name'], 'Maid') !== false || stripos($provider['category_name'], 'Housekeeper') !== false);

// Clean phone for whatsapp link
$wa_phone = preg_replace('/[^0-9]/', '', $provider['phone']);
$active_page = 'essentials';
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
    <title><?= htmlspecialchars($provider['name']) ?> | Provider Details</title>
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
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Provider Details</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto w-full bg-slate-50/50">
                <div class="max-w-5xl mx-auto space-y-8">

                    <div class="flex items-center">
                        <a href="<?php echo BASE_URL; ?>essentials/index.php" class="flex items-center gap-2 text-sm font-black text-slate-500 hover:text-emerald-600 transition-colors">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Daily Essentials
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

                    <div class="space-y-8">
                        <!-- Top Profile Banner -->
                        <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8 flex flex-col md:flex-row items-center gap-8 relative overflow-hidden">
                            <div class="absolute top-0 left-0 w-full h-1 bg-emerald-400"></div>
                            
                            <div class="flex-shrink-0 w-32 h-32 rounded-full overflow-hidden border-4 border-slate-50 shadow-sm relative bg-slate-100">
                                <?php 
                                if (!empty($provider['image_path']) && $provider['image_path'] !== 'default_avatar.jpg'): 
                                    $img_src = 'assets/uploads/essentials/' . $provider['image_path'];
                                    if (file_exists('../assets/uploads/profiles/' . $provider['image_path'])) {
                                        $img_src = 'assets/uploads/profiles/' . $provider['image_path'];
                                    }
                                ?>
                                    <img src="<?php echo BASE_URL; ?><?= htmlspecialchars($img_src) ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-slate-300"><i data-lucide="user" class="w-12 h-12"></i></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex-1 text-center md:text-left">
                                <span class="inline-block text-[10px] font-black uppercase tracking-widest bg-emerald-50 text-emerald-700 px-3 py-1 rounded-md mb-3 border border-emerald-100 shadow-sm">
                                    <?= htmlspecialchars($provider['category_name'] ?? 'Service Provider') ?>
                                </span>
                                <h1 class="text-3xl font-black text-slate-900 mb-2 tracking-tight"><?= htmlspecialchars($provider['name']) ?></h1>
                                <p class="text-sm font-bold text-slate-500 flex items-center justify-center md:justify-start gap-1.5">
                                    <i data-lucide="star" class="w-4 h-4 <?= ($provider['total_reviews'] ?? 0) > 0 ? 'text-amber-400 fill-amber-400' : 'text-slate-300 fill-slate-100' ?>"></i> 
                                    <span class="text-slate-800"><?= ($provider['total_reviews'] ?? 0) > 0 ? number_format((float)$provider['rating'], 1) : '0.0' ?> Rating</span> 
                                    <span class="opacity-50 mx-1">•</span>
                                    <span><?= $provider['total_reviews'] ?? 0 ?> Reviews</span>
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-2 w-full md:w-[280px]">
                                <?php if(!empty($provider['user_id'])): ?>
                                <a href="<?php echo BASE_URL; ?>messages/index.php?user_id=<?= $provider['user_id'] ?>" class="px-3 py-2.5 bg-teal-600 hover:bg-teal-700 text-white font-bold text-[11px] uppercase tracking-wide rounded-lg transition-all flex items-center justify-center gap-1.5 shadow-sm hover:shadow-md">
                                    <i data-lucide="messages-square" class="w-3.5 h-3.5"></i> Message
                                </a>
                                <?php endif; ?>
                                <?php if(!empty($provider['email']) && !$is_maid): ?>
                                <a href="mailto:<?= htmlspecialchars($provider['email']) ?>" class="px-3 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-[11px] uppercase tracking-wide rounded-lg transition-all flex items-center justify-center gap-1.5 shadow-sm hover:shadow-md">
                                    <i data-lucide="mail" class="w-3.5 h-3.5"></i> Email
                                </a>
                                <?php endif; ?>
                                <a href="https://wa.me/<?= $wa_phone ?>" target="_blank" class="px-3 py-2.5 bg-emerald-500 hover:bg-emerald-600 text-white font-bold text-[11px] uppercase tracking-wide rounded-lg transition-all flex items-center justify-center gap-1.5 shadow-sm hover:shadow-md">
                                    <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> WhatsApp
                                </a>
                                <a href="tel:<?= htmlspecialchars($provider['phone']) ?>" class="px-3 py-2.5 bg-white hover:bg-slate-50 text-slate-700 font-bold text-[11px] uppercase tracking-wide rounded-lg transition-all flex items-center justify-center gap-1.5 border border-slate-200 shadow-sm hover:shadow-md">
                                    <i data-lucide="phone" class="w-3.5 h-3.5"></i> Call
                                </a>
                            </div>
                        </div>

                        <!-- Bottom 2-Column Grid -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            
                            <!-- Left Column: Details, Booking, Schedule -->
                            <div class="space-y-6">
                                <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8">
                                    <h3 class="text-[11px] font-black text-slate-500 mb-5 uppercase tracking-widest flex items-center gap-2">
                                        <i data-lucide="info" class="w-4 h-4 text-emerald-500"></i> Details
                                    </h3>
                                    <ul class="space-y-5">
                                        <?php if(!$is_isp && !$is_maid): ?>
                                        <li class="flex flex-col gap-1.5 pb-4 border-b border-slate-50">
                                            <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest flex items-center gap-1.5"><i data-lucide="clock" class="w-3 h-3 text-emerald-400"></i> Support Hours</span>
                                            <span class="text-sm text-slate-800 font-bold"><?= htmlspecialchars($provider['availability_schedule'] ?? 'Not Set') ?></span>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($provider['website_url'])): ?>
                                        <li class="flex flex-col gap-1.5 pb-4 border-b border-slate-50">
                                            <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest flex items-center gap-1.5"><i data-lucide="globe" class="w-3 h-3 text-emerald-400"></i> Website</span>
                                            <a href="<?= htmlspecialchars($provider['website_url']) ?>" target="_blank" class="text-sm text-emerald-600 font-bold hover:underline truncate"><?= htmlspecialchars($provider['website_url']) ?></a>
                                        </li>
                                        <?php endif; ?>

                                        <li class="flex flex-col gap-1.5">
                                            <span class="text-[10px] text-slate-500 font-black uppercase tracking-widest flex items-center gap-1.5"><i data-lucide="check-square" class="w-3 h-3 text-emerald-400"></i> Status</span>
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-md border border-emerald-100 w-fit">
                                                Active Provider <i data-lucide="shield-check" class="w-3 h-3"></i>
                                            </span>
                                        </li>
                                    </ul>
                                </div>

                                <?php if ($is_isp): ?>
                                    <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-slate-200 p-8 relative overflow-hidden">
                                        <div class="absolute top-0 right-0 p-8 opacity-5 pointer-events-none">
                                            <i data-lucide="wifi" class="w-48 h-48"></i>
                                        </div>

                                        <div class="mb-8 pb-4 border-b border-slate-100 relative z-10">
                                            <h2 class="text-2xl font-black text-slate-900 mb-2">Internet Packages & Details</h2>
                                            <p class="text-sm font-medium text-slate-500">Review the features below. Booking is handled directly through the provider's portal.</p>
                                        </div>
                                        
                                        <div class="bg-slate-50 rounded-[1.5rem] p-6 border border-slate-100 mb-8 whitespace-pre-line font-medium text-sm text-slate-700 leading-relaxed shadow-inner">
                                            <?php if(!empty($provider['pricing_details'])): ?>
                                                <?= htmlspecialchars($provider['pricing_details']) ?>
                                            <?php else: ?>
                                                <div class="text-center py-6 text-slate-400">
                                                    <i data-lucide="file-question" class="w-8 h-8 mx-auto mb-2 opacity-50"></i>
                                                    <p class="font-bold">No pricing details provided.</p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if(!empty($provider['website_url'])): ?>
                                        <a href="<?= htmlspecialchars($provider['website_url']) ?>" target="_blank" class="w-full py-4 px-4 bg-teal-600 hover:bg-teal-700 text-white font-black text-sm rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-teal-500/30 flex items-center justify-center gap-2 group relative z-10">
                                            <i data-lucide="external-link" class="w-4 h-4 group-hover:scale-110 transition-transform"></i> Visit Official Website to Book
                                        </a>
                                        <?php else: ?>
                                        <button disabled class="w-full py-4 px-4 bg-slate-100 text-slate-400 font-black text-sm rounded-xl border border-slate-200 flex items-center justify-center gap-2 cursor-not-allowed">
                                            <i data-lucide="link-2-off" class="w-4 h-4"></i> No Website Provided
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif ($is_maid): ?>
                                    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8 relative overflow-hidden">
                                        <div class="absolute top-0 right-0 p-8 opacity-5 pointer-events-none">
                                            <i data-lucide="home" class="w-48 h-48"></i>
                                        </div>

                                        <div class="mb-8 pb-4 border-b border-slate-100 relative z-10">
                                            <h2 class="text-2xl font-black text-slate-900 mb-2">Maid & Housekeeping</h2>
                                            <p class="text-sm font-medium text-slate-500">Contact directly via WhatsApp or phone to arrange schedules, duties, and monthly fees.</p>
                                        </div>
                                        
                                        <div class="bg-emerald-50 rounded-[1.5rem] p-8 border border-emerald-100 mb-8 text-center shadow-inner relative z-10">
                                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm border border-emerald-100 text-emerald-500">
                                                <i data-lucide="phone-call" class="w-8 h-8"></i>
                                            </div>
                                            <h3 class="text-lg font-black text-slate-900 mb-2">Direct Contact Required</h3>
                                            <p class="text-sm font-medium text-slate-600 mb-6">Housekeeping services are typically negotiated directly for custom requirements and long-term engagements.</p>
                                            <a href="tel:<?= htmlspecialchars($provider['phone']) ?>" class="inline-flex py-3 px-8 bg-slate-900 hover:bg-slate-800 text-white font-black text-sm rounded-xl transition-all shadow-md group">
                                                <i data-lucide="phone" class="w-4 h-4 mr-2 group-hover:animate-bounce"></i> Call Provider Now
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 p-8">
                                        <div class="mb-8 pb-4 border-b border-slate-100">
                                            <h2 class="text-2xl font-black text-slate-900 mb-2">Book This Provider</h2>
                                            <p class="text-sm font-medium text-slate-500">Select a date and time slot to request service. Once booked, this provider will show as unavailable dynamically.</p>
                                        </div>
                                        
                                        <form action="" method="POST" class="space-y-6">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                                <div>
                                                    <label class="block text-[10px] font-black text-slate-600 uppercase tracking-widest mb-2">Booking Date</label>
                                                    <input type="date" name="booking_date" required min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($display_booking_date) ?>"
                                                        class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-700 shadow-sm">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-black text-slate-600 uppercase tracking-widest mb-2">Time Slot</label>
                                                    <input type="time" name="time_slot" required
                                                        class="w-full px-5 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-bold text-slate-700 shadow-sm">
                                                    <p class="text-[10px] font-bold text-slate-500 mt-2 flex items-center gap-1"><i data-lucide="info" class="w-3 h-3 text-emerald-500"></i> Align with availability window.</p>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" name="book_provider" class="w-full py-4 px-4 bg-slate-900 hover:bg-emerald-600 text-white font-black text-sm rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-emerald-500/30 flex items-center justify-center gap-2 group">
                                                <i data-lucide="calendar-check" class="w-4 h-4 group-hover:scale-110 transition-transform"></i> Confirm Booking Reservation
                                            </button>
                                        </form>
                                    </div>

                                    <?php
                                        // Fetch all future bookings for this provider regardless of status
                                        $all_bookings = [];
                                        $b_query = mysqli_query($conn, "SELECT booking_date, time_slot FROM provider_bookings WHERE provider_id = '$provider_id' AND booking_date >= CURRENT_DATE ORDER BY booking_date ASC, time_slot ASC LIMIT 15");
                                        while($row = mysqli_fetch_assoc($b_query)) {
                                            $all_bookings[] = $row;
                                        }
                                    ?>
                                    <div class="bg-white rounded-[2rem] border border-slate-200 p-8 shadow-sm">
                                        <h3 class="text-[11px] font-black text-slate-500 mb-6 uppercase tracking-widest flex items-center gap-2 pb-3 border-b border-slate-50">
                                            <i data-lucide="calendar" class="w-4 h-4 text-emerald-500"></i> Upcoming Schedule
                                        </h3>
                                        
                                        <?php if(count($all_bookings) > 0): ?>
                                            <div class="grid grid-cols-1 gap-4 max-h-[400px] overflow-y-auto pr-2">
                                                <?php foreach($all_bookings as $b): ?>
                                                    <div class="p-4 bg-slate-50 border border-slate-100 rounded-[1.25rem] flex items-center justify-between shadow-sm">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100 shrink-0"><i data-lucide="clock" class="w-4 h-4"></i></div>
                                                            <div>
                                                                <span class="block text-sm font-black text-slate-800"><?= date('M j, Y', strtotime($b['booking_date'])) ?></span>
                                                                <span class="block text-xs font-bold text-slate-500"><?= date('h:i A', strtotime($b['time_slot'])) ?></span>
                                                            </div>
                                                        </div>
                                                        <span class="text-[10px] font-black uppercase tracking-widest text-rose-600 bg-rose-50 px-3 py-1.5 rounded-lg border border-rose-100 shadow-sm shrink-0">Booked</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-10 bg-slate-50 rounded-2xl border-2 border-dashed border-slate-200">
                                                <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-3 shadow-sm border border-slate-100 text-emerald-400">
                                                    <i data-lucide="calendar-check" class="w-5 h-5"></i>
                                                </div>
                                                <p class="text-sm font-bold text-slate-700">Fully available.</p>
                                                <p class="text-xs font-medium text-slate-500 mt-1">No upcoming bookings found.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Right Column: Review Section -->
                            <div class="space-y-6">
                                <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm overflow-hidden flex flex-col h-full">
                                    <div class="px-8 py-6 border-b border-slate-100 bg-slate-50/50 flex justify-between items-center">
                                        <h2 class="text-xl font-black text-slate-900 flex items-center gap-2">
                                            <i data-lucide="star" class="w-5 h-5 text-amber-500"></i> Customer Reviews
                                        </h2>
                                    </div>
                                    
                                    <div class="p-8 border-b border-slate-100">
                                        <h3 class="text-[11px] font-black text-slate-500 mb-4 uppercase tracking-widest"><?= $my_review ? 'Update Your Review' : 'Leave a Review' ?></h3>
                                        <form method="POST" action="">
                                            <div class="mb-4">
                                                <label class="block text-xs font-bold text-slate-700 mb-2">Rating</label>
                                                <div class="flex items-center gap-2" x-data="{ rating: 5, hoverRating: 0 }">
                                                    <input type="hidden" name="rating" x-model="rating">
                                                    <template x-for="i in 5">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                            class="w-6 h-6 cursor-pointer transition-colors"
                                                            :class="i <= (hoverRating || rating) ? 'fill-amber-400 text-amber-400' : 'text-slate-300 fill-slate-100'"
                                                            @mouseover="hoverRating = i"
                                                            @mouseleave="hoverRating = 0"
                                                            @click="rating = i">
                                                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                                                        </svg>
                                                    </template>
                                                </div>
                                            </div>
                                            <div class="mb-4">
                                                <label class="block text-xs font-bold text-slate-700 mb-2">Review Comment (Optional)</label>
                                                <textarea name="review_text" rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all text-sm font-medium resize-none" placeholder="Share your experience with this provider..."></textarea>
                                            </div>
                                            <button type="submit" name="submit_review" class="px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold text-sm rounded-xl transition-all shadow-sm">
                                                <?= $my_review ? 'Update Review' : 'Submit Review' ?>
                                            </button>
                                        </form>
                                    </div>

                                    <div class="divide-y divide-slate-100 flex-1 overflow-y-auto max-h-[600px]">
                                        <?php if (empty($all_reviews)): ?>
                                            <div class="p-10 text-center text-slate-500">
                                                <div class="w-12 h-12 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-100 text-slate-300">
                                                    <i data-lucide="message-square" class="w-6 h-6"></i>
                                                </div>
                                                <p class="font-bold">No reviews yet.</p>
                                                <p class="text-xs mt-1">Be the first to review this provider!</p>
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($all_reviews as $r): ?>
                                                <div class="p-6 hover:bg-slate-50 transition-colors">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <div class="flex items-center gap-3">
                                                            <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center text-xs">
                                                                <?= strtoupper(substr($r['reviewer_name'] ?? 'U', 0, 1)) ?>
                                                            </div>
                                                            <div>
                                                                <h4 class="text-sm font-bold text-slate-900 leading-none"><?= htmlspecialchars($r['reviewer_name'] ?? 'Customer') ?></h4>
                                                                <span class="text-[10px] text-slate-400 font-medium"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-0.5">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i data-lucide="star" class="w-3.5 h-3.5 <?= $i <= $r['rating'] ? 'fill-amber-400 text-amber-400' : 'text-slate-200 fill-slate-100' ?>"></i>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($r['review_text'])): ?>
                                                        <p class="text-sm text-slate-600 font-medium mt-3 ml-11">"<?= htmlspecialchars($r['review_text']) ?>"</p>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>js/owner_logic.js"></script>
    <script>lucide.createIcons();</script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>
</body>
</html>