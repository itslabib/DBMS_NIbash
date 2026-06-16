<?php
session_start();
require_once '../includes/db_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../vendor/autoload.php';

// Protection: Only allow access if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle SOS Trigger Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['trigger_sos'])) {
    
    // Get user profile ID, Name, and Phone
    $p_query = mysqli_query($conn, "SELECT id, full_name, phone FROM user_profiles WHERE user_id = '$user_id'");
    $p_data = mysqli_fetch_assoc($p_query);
    $profile_id = $p_data['id'] ?? 0;
    $sender_name = $p_data['full_name'] ?? 'A Resident';
    
    // Fallback logic for sender's phone number
    $sender_phone = !empty($p_data['phone']) ? $p_data['phone'] : '01301085365';

    // Fetch personal SOS contacts
    $sos_query = mysqli_query($conn, "SELECT * FROM emergency_contacts WHERE user_profile_id = '$profile_id' AND contact_type = 'Personal'");
    
    $whatsapp_number = null;
    $emails_sent = 0;

    if (mysqli_num_rows($sos_query) > 0) {
        $mail = new PHPMailer(true);
        try {
            // Email Configuration
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com'; 
            $mail->SMTPAuth   = true;
            $mail->Username   = 'suchak9931@gmail.com'; 
            $mail->Password   = 'ubaz ayum yhyy hyis';   
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('suchak9931@gmail.com', 'Nibash SOS System');
            $mail->isHTML(true);
            $mail->Subject = "🚨 EMERGENCY SOS ALERT from $sender_name";
            $mail->Body    = "<div style='font-family: sans-serif; padding: 20px; border: 2px solid red; border-radius: 10px;'>
                                <h2 style='color:red;'>🚨 SOS ALERT TRIGGERED</h2>
                                <p><b>$sender_name</b> has triggered an emergency SOS from the Nibash Building Management System.</p>
                                <p><b>Sender Contact Number:</b> $sender_phone</p>
                                <p>Please contact them immediately or dispatch help to their location!</p>
                              </div>";

            // Prepare the dynamic WhatsApp text
            $whatsapp_text = "🚨 *SOS ALERT* 🚨\n\nEmergency triggered by: *$sender_name*\nSender Number: *$sender_phone*\n\nPlease contact them immediately or dispatch help!";

            // ⚠️ BACKGROUND WHATSAPP API CREDENTIALS ⚠️
            // To send messages without opening a new tab, you must use a gateway like UltraMsg.
            // Replace these with your actual keys and uncomment the block below.
            $instance_id = "YOUR_ULTRAMSG_INSTANCE_ID"; 
            $token = "YOUR_ULTRAMSG_TOKEN";

            while ($contact = mysqli_fetch_assoc($sos_query)) {
                
                // 1. Add to Email List
                if (!empty($contact['email'])) {
                    $mail->addAddress($contact['email'], $contact['title']);
                    $emails_sent++;
                }
                
                // 2. Prepare WhatsApp 
                if (!empty($contact['phone_number'])) {
                    $raw_number = preg_replace('/[^0-9]/', '', $contact['phone_number']);
                    // Ensure number has country code (Assuming BD +88)
                    $formatted_receiver = (substr($raw_number, 0, 2) === "01") ? "+88" . $raw_number : $raw_number;

                    // --- UNCOMMENT THIS BLOCK TO SEND API MESSAGES IN BACKGROUND ---
                    /*
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                      CURLOPT_URL => "https://api.ultramsg.com/$instance_id/messages/chat",
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => "",
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 30,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => "POST",
                      CURLOPT_POSTFIELDS => http_build_query(array(
                        'token' => $token,
                        'to' => $formatted_receiver,
                        'body' => $whatsapp_text
                      )),
                      CURLOPT_HTTPHEADER => array(
                        "content-type: application/x-www-form-urlencoded"
                      ),
                    ));
                    $response = curl_exec($curl);
                    curl_close($curl);
                    */

                    // Save the first available phone number for frontend WhatsApp redirect (Fallback)
                    if (!$whatsapp_number) {
                        $whatsapp_number = $formatted_receiver;
                    }
                }
            }
            
            if ($emails_sent > 0) {
                $mail->send();
            }
            $_SESSION['success_msg'] = "SOS Alerts sent successfully!";
            
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "Failed to send SOS emails. Please try calling directly.";
        }
    } else {
        $_SESSION['error_msg'] = "No SOS contacts configured! Please add them in the Emergency Console.";
    }

    // Frontend WhatsApp Redirect Fallback (Uses the new dynamic text)
    if ($whatsapp_number) {
        $msg = urlencode($whatsapp_text);
        $_SESSION['wa_redirect'] = "https://wa.me/{$whatsapp_number}?text={$msg}";
    }
    
    header("Location: dashboard.php");
    exit();
}

// Fetch dynamic user profile data
$user_name = "User";
$user_email = "";
$user_image = "";
$active_rentals = 0;
$has_notification = false;
try {
    $query = "SELECT p.full_name, p.profile_image, u.email, a.apt_number, a.building_id 
              FROM users u 
              LEFT JOIN user_profiles p ON u.id = p.user_id 
              LEFT JOIN apartment_assignments aa ON u.id = aa.user_id AND aa.is_active = 1
              LEFT JOIN apartments a ON aa.apt_id = a.id
              WHERE u.id = '$user_id'";
    $result = @mysqli_query($conn, $query);
    if ($result && mysqli_num_rows($result) > 0) {
        $user_profile = mysqli_fetch_assoc($result);
        $user_name = $user_profile['full_name'] ?? 'User';
        $user_image = $user_profile['profile_image'] ?? '';
        $user_email = $user_profile['email'] ?? '';
        $unit_number = $user_profile['apt_number'] ?? 'N/A';
        $building_id = $user_profile['building_id'] ?? 0;
    }

    $rent_query = "SELECT COUNT(*) as rental_count FROM rental_listings WHERE owner_id = '$user_id'";
    $rent_result = @mysqli_query($conn, $rent_query);
    if ($rent_result) {
        $active_rentals = mysqli_fetch_assoc($rent_result)['rental_count'] ?? 0;
    }

    $notif_q = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = '$user_id' AND is_read = 0";
    $notif_res = @mysqli_query($conn, $notif_q);
    $has_notification = false;
    if ($notif_res) {
        $notif_data = mysqli_fetch_assoc($notif_res);
        $has_notification = ($notif_data['unread'] > 0);
    }

    $bills_query = "SELECT b.id FROM bills b WHERE b.resident_id = '$user_id' AND b.status IN ('Pending', 'Partially Paid', 'Overdue')";
    $bills_res = @mysqli_query($conn, $bills_query);
    $total_outstanding_amount = 0;
    
    if ($bills_res) {
        while ($row = mysqli_fetch_assoc($bills_res)) {
            $bill_id = $row['id'];
            $items_q = "SELECT i.*, u.utility_name FROM bill_items i LEFT JOIN utility_types u ON i.utility_type_id = u.id WHERE i.bill_id = '$bill_id'";
            $items_res = @mysqli_query($conn, $items_q);
            if ($items_res) {
                while ($item = mysqli_fetch_assoc($items_res)) {
                    $is_electricity = false;
                    if (stripos($item['item_name'], 'electric') !== false || stripos($item['utility_name'] ?? '', 'electric') !== false) {
                        $is_electricity = true;
                    }
                    if (!$is_electricity) {
                        $total_outstanding_amount += (float)$item['amount'];
                    }
                }
            }
        }
    }
} catch (Exception $e) {}

if ($_SESSION['role_id'] == 1) {
    header("Location: ../owner/dashboard.php");
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
    <title>Resident Portal | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <style>
        .hover-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -8px rgba(20, 184, 166, 0.15); border-color: #5eead4; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f0fdfa; }
        ::-webkit-scrollbar-thumb { background: #99f6e4; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #2dd4bf; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" 
      x-data="{ sidebarOpen: false, desktopSidebarOpen: localStorage.getItem('desktopSidebar') === 'false' ? false : true }"
      x-init="$watch('desktopSidebarOpen', val => localStorage.setItem('desktopSidebar', val))">

    <?php include '../includes/resident_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8">
        
        <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-teal-100/60 hover:shadow-[0_4px_15px_-3px_rgba(20,184,166,0.15)] hover:border-teal-200 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>

        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(0,0,0,0.1)] flex-1 flex flex-col overflow-hidden border border-slate-200/80 relative">
            
            <header class="bg-white/80 backdrop-blur-xl border-b border-slate-200/60 px-8 py-4 flex items-center justify-between sticky top-0 z-40 shadow-sm">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-teal-50 hover:text-teal-700 rounded-lg transition-colors">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-teal-500 shadow-[0_0_8px_rgba(20,184,166,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Resident Hub</span>
                        </h2>
                    </div>
                </div>

                <div class="flex items-center gap-5">
                    
                    <button onclick="if(typeof toggleDarkMode === 'function') { toggleDarkMode(); }" class="relative flex items-center justify-center w-9 h-9 bg-slate-50 text-slate-500 hover:text-teal-600 rounded-xl border border-slate-200 hover:border-teal-300 hover:bg-teal-50 transition-all shadow-sm font-bold text-sm group" title="Toggle Theme">
                        <i data-lucide="moon" class="w-4 h-4 group-hover:scale-110 transition-transform dark:hidden"></i>
                        <i data-lucide="sun" class="w-4 h-4 group-hover:scale-110 transition-transform hidden dark:block text-amber-500"></i>
                    </button>



                    <div class="relative flex items-center" x-data="notificationDropdown()" x-init="init()">
                        <button @click="toggle()" @click.away="open = false" class="relative flex items-center justify-center w-10 h-10 bg-slate-50 text-slate-400 hover:text-teal-600 rounded-full border border-slate-200 hover:border-teal-300 hover:bg-teal-50 transition-all shadow-sm group">
                            <i data-lucide="bell" class="w-5 h-5 group-hover:scale-110 transition-transform"></i>
                            <template x-if="unreadCount > 0">
                                <span class="absolute top-0 right-0 flex h-3 w-3 -mt-0.5 -mr-0.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-teal-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-teal-500 ring-2 ring-white"></span>
                                </span>
                            </template>
                        </button>

                        <div x-show="open" x-transition.opacity.duration.200ms class="absolute right-0 top-full mt-3 w-80 bg-white rounded-[1.5rem] shadow-xl border border-slate-200 overflow-hidden z-50 text-left" style="display: none;">
                            <div class="p-5 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                                <h3 class="font-bold text-slate-900">Notifications</h3>
                                <span class="text-xs bg-teal-100 text-teal-700 px-3 py-1 rounded-full font-bold" x-text="unreadCount + ' New'"></span>
                            </div>
                            <div class="max-h-[300px] overflow-y-auto">
                                <template x-if="notifications.length > 0">
                                    <template x-for="note in notifications" :key="note.id">
                                        <a :href="'<?php echo BASE_URL; ?>' + note.link" @click="markRead(note.id)" class="block p-5 border-b border-slate-50 hover:bg-teal-50/50 transition-colors cursor-pointer group" :class="!note.is_read ? 'bg-teal-50/40' : ''">
                                            <h4 class="text-sm font-bold text-slate-800 mb-1.5 group-hover:text-teal-600 transition-colors" x-text="note.title"></h4>
                                            <p class="text-xs text-slate-500 leading-relaxed line-clamp-2" x-text="note.message"></p>
                                            <span class="text-[11px] font-medium text-slate-400 mt-3 flex items-center gap-1.5">
                                                <i data-lucide="clock" class="w-3.5 h-3.5"></i> <span x-text="note.created_at"></span>
                                            </span>
                                        </a>
                                    </template>
                                </template>
                                <template x-if="notifications.length === 0">
                                    <div class="p-8 text-center text-slate-400 text-sm font-medium">No recent notifications.</div>
                                </template>
                            </div>
                            <div class="p-3 border-t border-slate-100 text-center bg-slate-50/50">
                                <a href="<?php echo BASE_URL; ?>notifications_history.php" class="text-xs font-bold text-teal-600 hover:text-teal-700 transition-colors">View All Notifications</a>
                            </div>
                        </div>
                    </div>

                    <a href="<?php echo BASE_URL; ?>resident/profile.php" class="flex items-center gap-3 p-1.5 pr-5 rounded-full border border-slate-200 bg-white hover:border-teal-300 hover:shadow-md transition-all group">
                        <div class="relative w-8 h-8 overflow-hidden rounded-full border border-teal-100 shadow-inner group-hover:border-teal-500 transition-colors bg-teal-50 text-teal-700 flex items-center justify-center font-extrabold text-xs">
                            <?php 
                                $show_image = false;
                                $final_user_image = '';
                                if (!empty($user_image) && $user_image !== 'default_avatar.png') {
                                    $pi = $user_image;
                                    if (strpos($pi, '/') === false) { $pi = 'assets/uploads/profiles/' . $pi; }
                                    $abs_path = $_SERVER['DOCUMENT_ROOT'] . '/Nibash/' . ltrim($pi, '/');
                                    if (file_exists($abs_path)) {
                                        $show_image = true;
                                        $final_user_image = ltrim($pi, '/');
                                    }
                                }
                            ?>
                            <?php if ($show_image): ?>
                                <img src="<?php echo BASE_URL . $final_user_image; ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <?= strtoupper(substr($user_name, 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-col hidden sm:block">
                            <span class="text-sm font-bold text-slate-700 tracking-tight">Unit <?= htmlspecialchars($unit_number) ?></span>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 group-hover:text-teal-600 transition-colors"></i>
                    </a>
                </div>
            </header>

            <div class="p-6 sm:p-8 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-[#FAFAFA]">

                <!-- Main Grid Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                    
                    <!-- Left Column (KPIs + Notice Board) -->
                    <div class="lg:col-span-2 space-y-6 lg:space-y-8 flex flex-col">
                        
                        <!-- Compact KPIs -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                            <!-- Outstanding Balance -->
                            <div class="bg-white p-5 rounded-[1.5rem] border-l-[6px] border-l-rose-500 border-y border-r border-slate-200 shadow-md hover:shadow-xl transition-all cursor-pointer group flex items-center justify-between" onclick="window.location.href='<?php echo BASE_URL; ?>resident/billing.php'">
                                <div>
                                    <h4 class="text-xs font-extrabold text-slate-500 mb-1 uppercase tracking-wider">Unpaid Bills</h4>
                                    <div class="text-3xl font-black text-slate-900 tracking-tight">Tk <?= number_format($total_outstanding_amount, 0) ?></div>
                                </div>
                                <div class="w-12 h-12 rounded-full flex items-center justify-center bg-rose-50 text-rose-500 border border-rose-100 group-hover:scale-110 transition-transform">
                                    <i data-lucide="receipt" class="w-6 h-6"></i>
                                </div>
                            </div>

                            <!-- Gate Passes -->
                            <div class="bg-white p-5 rounded-[1.5rem] border-l-[6px] border-l-teal-500 border-y border-r border-slate-200 shadow-md hover:shadow-xl transition-all cursor-pointer group flex items-center justify-between" onclick="window.location.href='<?php echo BASE_URL; ?>resident/guest_passes.php'">
                                <div>
                                    <h4 class="text-xs font-extrabold text-slate-500 mb-1 uppercase tracking-wider">Active Passes</h4>
                                    <div class="text-3xl font-black text-slate-900 tracking-tight">
                                        <?php 
                                            $gp_q = "SELECT COUNT(*) as active_passes FROM `visit_requests` WHERE `resident_id` = '$user_id' AND `status` = 'Approved'";
                                            $gp_res = @mysqli_query($conn, $gp_q);
                                            echo ($gp_res) ? mysqli_fetch_assoc($gp_res)['active_passes'] : 0;
                                        ?>
                                    </div>
                                </div>
                                <div class="w-12 h-12 rounded-full flex items-center justify-center bg-teal-50 text-teal-600 border border-teal-100 group-hover:scale-110 transition-transform">
                                    <i data-lucide="scan-face" class="w-6 h-6"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Community Hub (Notice Board) -->
                        <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-slate-300 overflow-hidden flex-1 flex flex-col min-h-[350px]">
                            <div class="p-8 pb-4">
                                <div class="flex justify-between items-center mb-6">
                                    <h3 class="text-xl font-extrabold text-slate-900">Notice Board</h3>
                                    <a href="<?php echo BASE_URL; ?>community_hub.php" class="p-2 hover:bg-slate-50 rounded-xl transition-colors cursor-pointer group border border-transparent hover:border-slate-200">
                                        <i data-lucide="arrow-up-right" class="w-5 h-5 text-slate-400 group-hover:text-slate-700"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="overflow-y-auto flex-1 px-8 pb-8">
                                <div class="flex flex-col gap-3">
                                    <?php
                                    try {
                                        $n_query = "SELECT n.*, p.full_name as author_name,
                                                    (SELECT COUNT(*) FROM community_comments cc WHERE cc.post_id = n.id) as reply_count
                                                    FROM community_posts n 
                                                    JOIN apartments a ON n.apt_id = a.id
                                                    LEFT JOIN user_profiles p ON n.user_id = p.user_id 
                                                    WHERE a.`building_id` = '$building_id' AND (n.status IS NULL OR n.status = 'published')
                                                    ORDER BY n.is_pinned DESC, n.created_at DESC LIMIT 5";
                                        $n_res = @mysqli_query($conn, $n_query);
                                        if ($n_res && mysqli_num_rows($n_res) > 0) {
                                            while ($notice = mysqli_fetch_assoc($n_res)) {
                                                $is_mentioned = strpos($notice['content'], "@$user_id") !== false;
                                                ?>
                                                <div class="bg-white border border-slate-200 rounded-[1rem] p-4 hover:border-teal-400 hover:shadow-md transition-all cursor-pointer group flex flex-col sm:flex-row sm:items-center justify-between gap-4 <?php echo $is_mentioned ? 'ring-2 ring-indigo-200 bg-indigo-50/10' : ''; ?>" onclick="window.location.href='<?php echo BASE_URL; ?>community_hub.php'">
                                                    <div class="flex items-center gap-4 flex-1">
                                                        <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex flex-col items-center justify-center shrink-0 border border-teal-100 group-hover:bg-teal-500 group-hover:text-white transition-colors shadow-sm">
                                                            <span class="text-[10px] font-bold uppercase leading-none mb-0.5"><?= date('M', strtotime($notice['created_at'])) ?></span>
                                                            <span class="text-[17px] font-black leading-none"><?= date('d', strtotime($notice['created_at'])) ?></span>
                                                        </div>
                                                        <div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-center justify-between gap-2 sm:gap-4">
                                                            <div class="min-w-0 flex-1">
                                                                <h4 class="font-extrabold text-slate-800 text-[15px] group-hover:text-teal-600 transition-colors line-clamp-1">
                                                                    <?= htmlspecialchars($notice['title']) ?>
                                                                    <?php if($is_mentioned): ?><span class="ml-2 px-1.5 py-0.5 bg-indigo-100 text-indigo-700 text-[9px] font-black rounded uppercase">Mention</span><?php endif; ?>
                                                                </h4>
                                                                <p class="text-xs text-slate-500 mt-1 line-clamp-1 font-medium"><?= htmlspecialchars(strip_tags($notice['content'])) ?></p>
                                                            </div>
                                                            <div class="flex items-center gap-3 text-[11px] font-bold text-slate-500 shrink-0">
                                                                <span class="flex items-center gap-1.5 bg-slate-100 px-2 py-1 rounded-md text-slate-600 border border-slate-200">
                                                                    <i data-lucide="user" class="w-3 h-3 text-slate-400"></i> <?= htmlspecialchars($notice['author_name'] ?? 'System') ?>
                                                                </span>
                                                                <span class="flex items-center gap-1.5 text-teal-700 bg-teal-50 px-2 py-1 rounded-md border border-teal-100">
                                                                    <i data-lucide="message-square" class="w-3 h-3"></i> <?= (int)$notice['reply_count'] ?> Replies
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php
                                            }
                                        } else {
                                            echo '<div class="py-16 text-center border-2 border-dashed border-slate-200 rounded-[1.5rem] bg-slate-50/50">
                                                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto mb-3 border border-slate-200 shadow-sm">
                                                        <i data-lucide="inbox" class="w-5 h-5 text-slate-400"></i>
                                                    </div>
                                                    <span class="text-slate-500 font-bold text-sm">No recent notices available.</span>
                                                </div>';
                                        }
                                    } catch (Throwable $e) {}
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column (Quick Actions & DESCO) -->
                    <div class="lg:col-span-1 space-y-6 lg:space-y-8 flex flex-col">
                        
                        <!-- Quick Actions Compact -->
                        <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.08)] border border-slate-300 p-6 flex flex-col">
                            <h4 class="text-xs font-extrabold text-slate-500 mb-4 uppercase tracking-wider">Quick Actions</h4>
                            <div class="grid grid-cols-3 gap-3">
                                <form method="POST" action="" id="nibash-sos-form" class="w-full">
                                    <button type="submit" name="trigger_sos" class="w-full h-full p-3 rounded-[1rem] bg-rose-50 hover:bg-rose-500 hover:text-white text-rose-600 font-bold transition-all flex flex-col items-center justify-center gap-2 border border-rose-200 hover:border-transparent shadow-sm group" onclick="return confirm('🚨 Trigger EMERGENCY SOS?')">
                                        <i data-lucide="shield-alert" class="w-5 h-5"></i>
                                        <span class="text-[10px] uppercase tracking-wider text-center leading-tight">SOS<br>Alert</span>
                                    </button>
                                </form>
                                <a href="<?php echo BASE_URL; ?>essentials/index.php" class="p-3 rounded-[1rem] bg-slate-50 hover:bg-teal-500 hover:text-white text-slate-600 font-bold transition-all flex flex-col items-center justify-center gap-2 border border-slate-200 hover:border-transparent shadow-sm group">
                                    <i data-lucide="wrench" class="w-5 h-5 group-hover:text-white text-teal-500"></i>
                                    <span class="text-[10px] uppercase tracking-wider text-center leading-tight">Book<br>Service</span>
                                </a>
                                <a href="<?php echo BASE_URL; ?>rentals/manage.php" class="p-3 rounded-[1rem] bg-slate-50 hover:bg-emerald-500 hover:text-white text-slate-600 font-bold transition-all flex flex-col items-center justify-center gap-2 border border-slate-200 hover:border-transparent shadow-sm group">
                                    <i data-lucide="building" class="w-5 h-5 group-hover:text-white text-emerald-500"></i>
                                    <span class="text-[10px] uppercase tracking-wider text-center leading-tight">My<br>Rentals</span>
                                </a>
                            </div>
                        </div>

                        <!-- DESCO Billing Widget -->
                        <div class="bg-white rounded-[2rem] border border-slate-300 shadow-[0_8px_30px_rgb(0,0,0,0.08)] overflow-hidden flex flex-col relative" x-data="descoWidget()">
                            <div class="p-6 border-b border-slate-50 relative z-10">
                                <div class="flex items-center gap-3 mb-6">
                                    <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center border border-indigo-100">
                                        <i data-lucide="zap" class="w-5 h-5"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-black text-slate-900 tracking-tight">DESCO Utility</h3>
                                        <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">Prepaid Meter</p>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i data-lucide="hash" class="w-4 h-4 text-slate-400"></i>
                                        </div>
                                        <input type="text" x-model="descoAccount" placeholder="Meter Account Number" 
                                            class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/10 outline-none text-slate-900 font-mono tracking-wider text-sm transition-all shadow-sm">
                                    </div>
                                </div>
                            </div>
                            <div class="p-5 bg-indigo-50/30 flex-1 relative z-10 flex flex-col gap-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <button @click="viewDescoBalance()" class="py-2.5 px-2 bg-white hover:bg-indigo-50 border border-indigo-100 rounded-xl text-xs font-bold text-indigo-600 transition-all flex flex-col items-center justify-center gap-1.5 shadow-sm">
                                        <i data-lucide="wallet" class="w-4 h-4"></i> Check Bal
                                    </button>
                                    <button @click="viewDescoConsumption()" class="py-2.5 px-2 bg-white hover:bg-indigo-50 border border-indigo-100 rounded-xl text-xs font-bold text-indigo-600 transition-all flex flex-col items-center justify-center gap-1.5 shadow-sm">
                                        <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Usage
                                    </button>
                                </div>
                                <div class="space-y-2 mt-1 border-t border-indigo-100/50 pt-4">
                                    <div class="flex gap-2">
                                        <input type="number" x-model="descoRechargeAmount" placeholder="Amount ৳" 
                                            class="w-full px-3 py-2.5 bg-white border border-indigo-100 rounded-xl focus:border-indigo-400 outline-none text-slate-900 font-bold text-sm transition-all shadow-sm">
                                        <button @click="payDescoBill()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm transition-all shadow-md shrink-0">
                                            Recharge
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- DESCO Modal inside widget context -->
                            <div x-show="descoModalOpen" x-cloak class="fixed inset-0 z-[150] flex items-center justify-center px-4">
                                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="descoModalOpen = false" x-transition.opacity></div>
                                <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-sm relative z-10 p-8 transform transition-all text-center">
                                    <div class="mx-auto w-12 h-12 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mb-4 border border-indigo-100">
                                        <i data-lucide="zap" class="w-6 h-6"></i>
                                    </div>
                                    <h3 class="text-lg font-black text-slate-900 mb-3 tracking-tight" x-text="descoModalTitle"></h3>
                                    
                                    <div class="bg-slate-50 rounded-xl p-3 mb-4 border border-slate-100 text-left">
                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Meter Account</p>
                                        <p class="text-sm font-mono font-bold text-slate-900 tracking-widest" x-text="descoAccount || 'Not Provided'"></p>
                                    </div>
                                    <div class="text-xs font-medium text-slate-700 mb-6 whitespace-pre-wrap bg-slate-100/50 p-3 rounded-xl border border-slate-200 text-left max-h-40 overflow-y-auto font-mono" x-text="descoModalMessage"></div>
                                    
                                    <button @click="descoModalOpen = false" class="w-full py-3 bg-slate-900 hover:bg-indigo-600 text-white rounded-xl font-bold transition-all shadow-md text-sm">
                                        Close
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>
    <script>
        lucide.createIcons();

        // Check for Session Alerts (from the PHP Mailer)
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (isset($_SESSION['success_msg'])): ?>
                showCustomPopup("<?= addslashes($_SESSION['success_msg']) ?>", 'success');
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_msg'])): ?>
                showCustomPopup("<?= addslashes($_SESSION['error_msg']) ?>", 'error');
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>

            // Automatically open WhatsApp in a new tab if triggered
            <?php if (isset($_SESSION['wa_redirect'])): ?>
                window.open("<?= addslashes($_SESSION['wa_redirect']) ?>", '_blank');
                <?php unset($_SESSION['wa_redirect']); ?>
            <?php endif; ?>
        });

        function notificationDropdown() {
            return {
                open: false,
                notifications: [],
                unreadCount: 0,
                init() {
                    this.fetchNotifications();
                    setInterval(() => this.fetchNotifications(), 30000);
                },
                toggle() {
                    this.open = !this.open;
                },
                fetchNotifications() {
                    fetch('<?php echo BASE_URL; ?>api/notifications.php?action=get')
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                this.notifications = data.notifications;
                                this.unreadCount = data.unread_count;
                                setTimeout(() => {
                                    if(window.lucide) lucide.createIcons();
                                }, 50);
                            }
                        });
                },
                markRead(id) {
                    fetch('<?php echo BASE_URL; ?>api/notifications.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'mark_read', id: id })
                    }).then(() => {
                        this.fetchNotifications();
                    });
                }
            }
        }

        // DESCO Widget Alpine component
        function descoWidget() {
            return {
                descoAccount: '',
                descoRechargeAmount: '', 
                descoModalOpen: false,
                descoModalTitle: '',
                descoModalMessage: '',

                validateDesco() {
                    if (!this.descoAccount || this.descoAccount.trim() === '') {
                        alert('Please enter your DESCO account number.');
                        return false;
                    }
                    if (this.descoAccount.length < 8) {
                        alert('Invalid account number. Must be at least 8 digits.');
                        return false;
                    }
                    return true;
                },

                async mockDescoAPI(action, title) {
                    if (!this.validateDesco()) return;
                    
                    try {
                        const res = await fetch('<?php echo BASE_URL; ?>api/desco.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: action, account: this.descoAccount })
                        });
                        const data = await res.json();
                        
                        if (data.success) {
                            this.descoModalTitle = title;
                            this.descoModalMessage = data.output;
                            this.descoModalOpen = true;
                        } else {
                            alert(data.message || 'Error fetching data');
                        }
                    } catch (e) {
                        setTimeout(() => {
                            this.descoModalTitle = title;
                            this.descoModalMessage = "System connection simulated.\nData returned successfully for " + this.descoAccount + ".";
                            this.descoModalOpen = true;
                        }, 500);
                    }
                    setTimeout(() => { if(window.lucide) lucide.createIcons(); }, 50);
                },

                viewDescoDetails() { this.mockDescoAPI('get_customer_info', 'Meter Details'); },
                viewDescoBalance() { this.mockDescoAPI('get_balance', 'Current Balance'); },
                viewDescoConsumption() { this.mockDescoAPI('get_monthly_consumption', 'Monthly Usage'); },
                viewDescoHistory() { this.mockDescoAPI('get_recharge_history', 'Recharge History'); },
                
                payDescoBill() { 
                    if (!this.validateDesco()) return;
                    if (!this.descoRechargeAmount || parseFloat(this.descoRechargeAmount) < 10) {
                        alert('Please enter a valid recharge amount (Min 10 ৳).');
                        return;
                    }
                    setTimeout(() => {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '<?php echo BASE_URL; ?>payment_integration/payment_init.php';

                        const billIdInput = document.createElement('input');
                        billIdInput.type = 'hidden';
                        billIdInput.name = 'bill_id';
                        billIdInput.value = '999999'; 
                        form.appendChild(billIdInput);

                        const amountInput = document.createElement('input');
                        amountInput.type = 'hidden';
                        amountInput.name = 'amount';
                        amountInput.value = this.descoRechargeAmount;
                        form.appendChild(amountInput);

                        document.body.appendChild(form);
                        form.submit();
                    }, 500);
                }
            }
        }
    </script>
    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>