<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}
if ($_SESSION['role_id'] == 1) {
    header("Location: ../owner/dashboard.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// Database Schema Fixes
try {
    // Add missing profile fields
    @mysqli_query($conn, "ALTER TABLE user_profiles ADD COLUMN blood_group VARCHAR(10) NULL");
    // @mysqli_query($conn, "ALTER TABLE user_profiles ADD COLUMN age INT NULL");
    @mysqli_query($conn, "ALTER TABLE user_profiles ADD COLUMN address TEXT NULL");
    @mysqli_query($conn, "ALTER TABLE user_profiles ADD COLUMN occupation VARCHAR(100) NULL");

    // Create family_members table if missing
    $fm_query = "CREATE TABLE IF NOT EXISTS family_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        member_name VARCHAR(100) NOT NULL,
        relation VARCHAR(50) NOT NULL,
        age INT NULL,
        blood_group VARCHAR(10) NULL,
        occupation VARCHAR(100) NULL
    )";
    @mysqli_query($conn, $fm_query);
    @mysqli_query($conn, "ALTER TABLE family_members ADD COLUMN blood_group VARCHAR(10) NULL");
} catch (Throwable $e) {
}

$has_family_table = false;
try {
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'family_members'");
    if ($table_check && mysqli_num_rows($table_check) > 0) {
        $has_family_table = true;
    }
} catch (Throwable $e) {
}

$profile_cols = [];
try {
    $cols_res = mysqli_query($conn, "SHOW COLUMNS FROM user_profiles");
    if ($cols_res) {
        while ($col = mysqli_fetch_assoc($cols_res)) {
            $profile_cols[$col['Field']] = true;
        }
    }
} catch (Throwable $e) {
}

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone_number']);
        $dob = !empty($_POST['dob']) ? mysqli_real_escape_string($conn, $_POST['dob']) : '';
        $location = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
        $occup = mysqli_real_escape_string($conn, $_POST['occupation']);
        $bg = mysqli_real_escape_string($conn, $_POST['blood_group']);
        $car_num = mysqli_real_escape_string($conn, $_POST['car_number'] ?? '');

        $exists_res = @mysqli_query($conn, "SELECT id FROM user_profiles WHERE user_id = '$user_id' LIMIT 1");

        $set_parts = ["full_name = '$name'"];
        if (!empty($profile_cols['phone'])) {
            $set_parts[] = "phone = '$phone'";
        }
        if (!empty($profile_cols['dob']) || isset($profile_cols['dob'])) { // ensure dob is handled
            $set_parts[] = "dob = " . ($dob ? "'$dob'" : "NULL");
        }
        if (!empty($profile_cols['address'])) {
            $set_parts[] = "address = '$location'";
        }
        if (!empty($profile_cols['occupation'])) {
            $set_parts[] = "occupation = '$occup'";
        }
        if (!empty($profile_cols['blood_group'])) {
            $set_parts[] = "blood_group = '$bg'";
        }

        if ($exists_res && mysqli_num_rows($exists_res) > 0) {
            $update_query = "UPDATE user_profiles SET " . implode(', ', $set_parts) . " WHERE user_id = '$user_id'";
            $ok = mysqli_query($conn, $update_query);
        } else {
            $cols = ["user_id", "full_name"];
            $vals = ["'$user_id'", "'$name'"];
            if (!empty($profile_cols['phone'])) {
                $cols[] = "phone";
                $vals[] = "'$phone'";
            }
            if (!empty($profile_cols['dob']) || isset($profile_cols['dob'])) {
                $cols[] = "dob";
                $vals[] = ($dob ? "'$dob'" : "NULL");
            }
            if (!empty($profile_cols['address'])) {
                $cols[] = "address";
                $vals[] = "'$location'";
            }
            if (!empty($profile_cols['occupation'])) {
                $cols[] = "occupation";
                $vals[] = "'$occup'";
            }
            if (!empty($profile_cols['blood_group'])) {
                $cols[] = "blood_group";
                $vals[] = "'$bg'";
            }

            $insert_query = "INSERT INTO user_profiles (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
            $ok = mysqli_query($conn, $insert_query);
        }

        // Handle Profile Image Upload
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/Nibash/assets/uploads/residents/';
                if (!is_dir($upload_dir))
                    mkdir($upload_dir, 0777, true);

                $new_filename = 'resident_' . $user_id . '_' . time() . '.' . $ext;
                $destination = $upload_dir . $new_filename;
                $destination_web = 'assets/uploads/residents/' . $new_filename; // relative path stored in DB

                if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                    // Get old image to delete if exists
                    $old_img_query = mysqli_query($conn, "SELECT profile_image FROM user_profiles WHERE user_id = '$user_id'");
                    if ($old_img_row = mysqli_fetch_assoc($old_img_query)) {
                        if (!empty($old_img_row['profile_image'])) {
                            $old_abs = $_SERVER['DOCUMENT_ROOT'] . '/Nibash/' . ltrim($old_img_row['profile_image'], '/');
                            if (file_exists($old_abs))
                                @unlink($old_abs);
                        }
                    }

                    mysqli_query($conn, "UPDATE user_profiles SET profile_image = '$destination_web' WHERE user_id = '$user_id'");
                }
            }
        }

        if ($ok) {
            if (!empty($car_num)) {
                $apt_q = mysqli_query($conn, "SELECT a.apt_number, a.id as apt_id, a.building_id FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id WHERE aa.user_id = '$user_id' AND aa.is_active = 1 LIMIT 1");
                if ($apt = mysqli_fetch_assoc($apt_q)) {
                    $apt_num = $apt['apt_number'];
                    $b_id = $apt['building_id'];
                    $apt_id = $apt['apt_id'];

                    // Ensure vehicle is recorded in resident_vehicles (3NF)
                    $check_vehicle_q = mysqli_query($conn, "SELECT id FROM resident_vehicles WHERE user_id = $user_id AND plate_number = '$car_num' LIMIT 1");
                    if (mysqli_num_rows($check_vehicle_q) == 0) {
                        mysqli_query($conn, "INSERT INTO resident_vehicles (user_id, apt_id, plate_number) VALUES ($user_id, $apt_id, '$car_num')");
                    }

                    // Check if the apartment already has a parking slot assigned
                    $check_existing_q = mysqli_query($conn, "SELECT id FROM parking_slots WHERE apt_id = $apt_id AND building_id = '$b_id' LIMIT 1");
                    
                    if (mysqli_num_rows($check_existing_q) == 0) {
                        // Find an existing unassigned parking slot
                        $free_slot_q = mysqli_query($conn, "SELECT id, slot_number FROM parking_slots WHERE building_id = '$b_id' AND apt_id IS NULL ORDER BY id ASC LIMIT 1");
                        
                        if ($free_slot_q && mysqli_num_rows($free_slot_q) > 0) {
                            $free_slot = mysqli_fetch_assoc($free_slot_q);
                            $slot_id = $free_slot['id'];
                            mysqli_query($conn, "UPDATE parking_slots SET current_status = 'Occupied', apt_id = $apt_id, license_plate = '$car_num' WHERE id = $slot_id");
                        } else {
                            // Generate a new spot name like P-X if none available
                            $count_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM parking_slots WHERE building_id = '$b_id' AND slot_number LIKE 'P-%'");
                            $cnt = mysqli_fetch_assoc($count_q)['cnt'];
                            $slot_number = 'P-' . ($cnt + 1);
                            while(mysqli_num_rows(mysqli_query($conn, "SELECT id FROM parking_slots WHERE building_id = '$b_id' AND slot_number = '$slot_number'")) > 0) {
                                $cnt++;
                                $slot_number = 'P-' . ($cnt + 1);
                            }
                            mysqli_query($conn, "INSERT INTO parking_slots (building_id, slot_number, apt_id, license_plate, current_status) VALUES ('$b_id', '$slot_number', $apt_id, '$car_num', 'Occupied')");
                        }
                    }
                }
            }
            $msg = "<div class='bg-emerald-50 text-emerald-600 p-4 rounded-xl mb-6'>Profile updated successfully!</div>";
        } else {
            $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6'>Error updating profile.</div>";
        }
    } elseif (isset($_POST['update_password'])) {
        $old_pass = mysqli_real_escape_string($conn, $_POST['old_password']);
        $new_pass = mysqli_real_escape_string($conn, $_POST['new_password']);

        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{6,}$/', $new_pass)) {
            $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6'>Password must be at least 6 characters long, with at least one uppercase letter, one lowercase letter, and one number.</div>";
        } else {
            $pass_check = mysqli_query($conn, "SELECT password FROM users WHERE id = '$user_id'");
            $pass_row = mysqli_fetch_assoc($pass_check);
            if ($pass_row['password'] === $old_pass) {
                mysqli_query($conn, "UPDATE users SET password = '$new_pass' WHERE id = '$user_id'");
                $msg = "<div class='bg-emerald-50 text-emerald-600 p-4 rounded-xl mb-6'>Password updated successfully!</div>";
            } else {
                $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6'>Incorrect current password.</div>";
            }
        }
    } elseif (isset($_POST['add_family'])) {
        // dynamic array of members
        if ($has_family_table && isset($_POST['f_name']) && is_array($_POST['f_name'])) {
            for ($i = 0; $i < count($_POST['f_name']); $i++) {
                $f_name = mysqli_real_escape_string($conn, $_POST['f_name'][$i]);
                if (trim($f_name) == '')
                    continue; // skip empty

                $f_age = intval($_POST['f_age'][$i]);
                $f_rel = mysqli_real_escape_string($conn, $_POST['f_rel'][$i]);
                $f_occ = mysqli_real_escape_string($conn, $_POST['f_occ'][$i]);
                $f_bg = mysqli_real_escape_string($conn, $_POST['f_bg'][$i]);

                mysqli_query($conn, "INSERT INTO family_members (user_id, member_name, age, relation, occupation, blood_group) VALUES ('$user_id', '$f_name', $f_age, '$f_rel', '$f_occ', '$f_bg')");
            }
            $msg = "<div class='bg-emerald-50 text-emerald-600 p-4 rounded-xl mb-6'>Family members added successfully!</div>";
        } elseif (!$has_family_table) {
            $msg = "<div class='bg-red-50 text-red-600 p-4 rounded-xl mb-6'>Family members table is missing. Please run the database query provided.</div>";
        }
    } elseif (isset($_POST['remove_family'])) {
        if ($has_family_table) {
            $fam_id = intval($_POST['fam_id']);
            mysqli_query($conn, "DELETE FROM family_members WHERE id = $fam_id AND user_id = '$user_id'");
            header("Location: ../resident/profile.php?tab=family");
            exit();
        }
    }
}

// Fetch Profile
$user_email = '';
$user_name = 'User';
$profile = [
    'full_name' => '',
    'phone' => '',
    'dob' => '',
    'address' => '',
    'occupation' => '',
    'blood_group' => '',
    'car_number' => '',
    'profile_image' => '',
    'unit_number' => ''
];
try {
    $q = mysqli_query($conn, "SELECT p.*, u.email, a.apt_number AS unit_number, rv.plate_number AS car_number 
                                FROM users u 
                                LEFT JOIN user_profiles p ON u.id = p.user_id 
                                LEFT JOIN apartment_assignments aa ON u.id = aa.user_id AND aa.is_active = 1 AND aa.role = 'tenant'
                                LEFT JOIN apartments a ON aa.apt_id = a.id
                                LEFT JOIN resident_vehicles rv ON u.id = rv.user_id
                              WHERE u.id = '$user_id' LIMIT 1");
    if ($f = mysqli_fetch_assoc($q)) {
        $profile = array_merge($profile, $f);
        $user_email = $f['email'];
        $user_name = $f['full_name'] ?? 'User';
    }
} catch (Exception $e) {
}

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'resident';
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
    <title>My Profile | Resident Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <style>
        .input-solid {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            width: 100%;
            transition: all 0.2s;
        }

        .input-solid:focus {
            outline: none;
            border-color: #3b82f6;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .hero-pattern {
            background-color: #f8fafc;
            background-image: radial-gradient(#94a3b8 0.5px, transparent 0.5px), radial-gradient(#94a3b8 0.5px, #f8fafc 0.5px);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
            opacity: 0.6;
        }
    </style>
</head>

<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden"
    x-data="{ sidebarOpen: false, desktopSidebarOpen: localStorage.getItem('desktopSidebar') === 'false' ? false : true, activeTab: '<?php echo $active_tab; ?>' }"
    x-init="$watch('desktopSidebarOpen', val => localStorage.setItem('desktopSidebar', val))">

    <div class="fixed inset-0 hero-pattern z-[-1] pointer-events-none"></div>

    <?php include '../includes/resident_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8">
        
        <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-blue-100/60 hover:shadow-[0_4px_15px_-3px_rgba(59,130,246,0.15)] hover:border-blue-200 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>

        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(59,130,246,0.1)] flex-1 flex flex-col overflow-hidden border border-blue-100/80 relative">
            
            <!-- Header -->
            <header class="bg-white/80 backdrop-blur-xl border-b border-blue-50 sticky top-0 z-40 shadow-sm px-8 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-blue-50 hover:text-blue-600 rounded-xl transition-colors">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">My Profile</span>
                        </h2>
                    </div>
                </div>

                <div class="flex items-center gap-6" x-data="{ noticeOpen: false }">
                    <div class="relative">
                        <button @click="noticeOpen = !noticeOpen" @click.outside="noticeOpen = false"
                            class="w-10 h-10 flex items-center justify-center text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-xl transition-colors relative">
                            <i data-lucide="bell" class="w-5 h-5"></i>
                            <span
                                class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
                        </button>
                        <div x-show="noticeOpen" x-transition.opacity.duration.200ms
                            class="absolute right-0 mt-3 w-80 h-56 bg-white border border-slate-100 rounded-2xl shadow-xl shadow-slate-200/50 z-50 flex flex-col overflow-hidden"
                            style="display: none;">
                            <div
                                class="bg-slate-50 border-b border-slate-100 px-4 py-3 flex items-center justify-between">
                                <h3 class="font-bold text-sm text-slate-800 flex items-center gap-2"><i
                                        data-lucide="bell-ring" class="w-4 h-4 text-blue-500"></i> Notifications</h3>
                                <a href="<?php echo BASE_URL; ?>community_hub.php"
                                    class="text-xs text-blue-600 font-semibold hover:underline">View All</a>
                            </div>
                            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                                <?php
                                try {
                                    $n_res = @mysqli_query($conn, "SELECT title, content, created_at FROM community_posts ORDER BY created_at DESC LIMIT 3");
                                    if ($n_res && mysqli_num_rows($n_res) > 0) {
                                        while ($notice = mysqli_fetch_assoc($n_res)) {
                                            echo '<div class="border-b border-slate-50 pb-2 last:border-0"><p class="text-sm font-bold text-slate-800 line-clamp-1">' . htmlspecialchars($notice['title']) . '</p><p class="text-xs text-slate-500 mt-1 line-clamp-1">' . htmlspecialchars($notice['content']) . '</p></div>';
                                        }
                                    } else {
                                        echo '<div class="text-sm text-slate-500 italic text-center py-4">No new notices.</div>';
                                    }
                                } catch (Throwable $e) {
                                    echo '<div class="text-sm text-slate-500 italic text-center py-4">Notice board unavailable.</div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <a href="<?php echo BASE_URL; ?>resident/profile.php"
                        class="flex items-center gap-3 group pl-2 border-l border-slate-200 pr-5">
                        <div class="hidden sm:block text-right">
                            <p class="text-sm font-bold text-slate-800 group-hover:text-blue-600 transition-colors">
                                <?php echo htmlspecialchars($user_name); ?></p>
                            <p class="text-[10px] text-slate-400 capitalize">Resident</p>
                        </div>
                        <?php
                        $prof_img_web = '';
                        if (!empty($profile['profile_image']) && $profile['profile_image'] !== 'default_avatar.png') {
                            $pi_clean = ltrim($profile['profile_image'], '/');
                            if (strpos($pi_clean, 'assets/uploads/profiles/') === false) {
                                $pi_clean = 'assets/uploads/profiles/' . $pi_clean;
                            }
                            $pi_abs = $_SERVER['DOCUMENT_ROOT'] . '/Nibash/' . ltrim($pi_clean, '/');
                            if (file_exists($pi_abs))
                                $prof_img_web = BASE_URL . ltrim($pi_clean, '/');
                        }
                        ?>
                        <?php if (!empty($prof_img_web)): ?>
                            <img src="<?php echo $prof_img_web; ?>" alt="Profile"
                                class="w-10 h-10 rounded-full border border-slate-200 object-cover">
                        <?php else: ?>
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=eff6ff&color=2563eb"
                                alt="Profile" class="w-10 h-10 rounded-full border border-slate-200 object-cover">
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </header>

        <div class="flex-1 p-6 lg:p-10 max-w-6xl mx-auto w-full">
            <?php echo $msg; ?>

            <!-- Navbar Subsections -->
            <div class="bg-white p-2 rounded-2xl shadow-sm border border-slate-100 mb-8 inline-flex space-x-2">
                <button @click="activeTab = 'resident'"
                    :class="activeTab === 'resident' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                    class="px-6 py-2.5 rounded-xl font-bold transition-all flex items-center gap-2 text-sm">
                    <i data-lucide="user" class="w-4 h-4"></i> Resident Info
                </button>
                <button @click="activeTab = 'family'"
                    :class="activeTab === 'family' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                    class="px-6 py-2.5 rounded-xl font-bold transition-all flex items-center gap-2 text-sm">
                    <i data-lucide="users" class="w-4 h-4"></i> Family Info
                </button>
                <button @click="activeTab = 'settings'"
                    :class="activeTab === 'settings' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-800'"
                    class="px-6 py-2.5 rounded-xl font-bold transition-all flex items-center gap-2 text-sm">
                    <i data-lucide="settings" class="w-4 h-4"></i> Profile Settings
                </button>
            </div>

            <!-- Tab: Resident Info -->
            <div x-show="activeTab === 'resident'" x-transition.opacity.duration.300ms class="grid grid-cols-1 gap-8"
                style="display: none;">
                <div class="bg-white/80 backdrop-blur-md rounded-3xl p-8 border border-slate-100 shadow-sm">
                    <h3 class="text-2xl font-black text-slate-800 mb-8 flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center"><i
                                data-lucide="user"></i></div>
                        General Information
                    </h3>

                    <form method="POST" class="space-y-6" enctype="multipart/form-data">
                        <input type="hidden" name="update_profile" value="1">

                        <!-- Profile Image Upload -->
                        <div class="flex items-center gap-6 mb-8 bg-slate-50 p-6 rounded-2xl border border-slate-100">
                            <div class="relative group w-24 h-24 shrink-0">
                                <?php
                                $prof_img_web2 = '';
                                if (!empty($profile['profile_image']) && $profile['profile_image'] !== 'default_avatar.png') {
                                    $pi = $profile['profile_image'];
                                    if (strpos($pi, '/') === false) {
                                        $pi = 'assets/uploads/profiles/' . $pi;
                                    }
                                    $pi_clean2 = ltrim($pi, '/');
                                    $pi_abs2 = $_SERVER['DOCUMENT_ROOT'] . '/Nibash/' . $pi_clean2;
                                    if (file_exists($pi_abs2))
                                        $prof_img_web2 = BASE_URL . $pi_clean2;
                                }
                                ?>
                                <?php if (!empty($prof_img_web2)): ?>
                                    <img src="<?php echo $prof_img_web2; ?>"
                                        class="w-24 h-24 rounded-2xl object-cover border-4 border-white shadow-sm">
                                <?php else: ?>
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=eff6ff&color=2563eb&size=96"
                                        class="w-24 h-24 rounded-2xl object-cover border-4 border-white shadow-sm">
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-bold text-slate-700 mb-2">Profile Image</label>
                                <input type="file" name="profile_image" accept="image/*" class="block w-full text-sm text-slate-500
                                  file:mr-4 file:py-2 file:px-4
                                  file:rounded-xl file:border-0
                                  file:text-sm file:font-semibold
                                  file:bg-blue-50 file:text-blue-600
                                  hover:file:bg-blue-100 cursor-pointer transition-all
                                " />
                                <p class="text-xs text-slate-400 mt-2">JPG, PNG or GIF. Max size 2MB.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Full Name</label>
                                <input type="text" name="full_name"
                                    value="<?php echo htmlspecialchars($profile['full_name'] ?? ''); ?>"
                                    class="input-solid">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Email (Unchangeable)</label>
                                <input type="email" value="<?php echo htmlspecialchars($user_email); ?>"
                                    class="input-solid bg-slate-100 text-slate-500" readonly>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Phone Number</label>
                                <input type="text" name="phone_number"
                                    value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>"
                                    class="input-solid">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Date of Birth</label>
                                <input type="date" name="dob"
                                    value="<?php echo htmlspecialchars($profile['dob'] ?? ''); ?>" class="input-solid">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Blood Group</label>
                                <select name="blood_group" class="input-solid border-r-8 border-transparent">
                                    <option value="">Select Blood Group</option>
                                    <?php
                                    $bgs = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                                    $c_bg = $profile['blood_group'] ?? '';
                                    foreach ($bgs as $bg) {
                                        $sel = ($c_bg === $bg) ? 'selected' : '';
                                        echo "<option value=\"$bg\" $sel>$bg</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Occupation</label>
                                <input type="text" name="occupation"
                                    value="<?php echo htmlspecialchars($profile['occupation'] ?? ''); ?>"
                                    class="input-solid">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">Car Number (Parking)</label>
                                <input type="text" name="car_number"
                                    value="<?php echo htmlspecialchars($profile['car_number'] ?? ''); ?>"
                                    class="input-solid">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-slate-700 mb-2">Unit / Apartment Number
                                    (Assigned)</label>
                                <input type="text" name="unit_number"
                                    value="<?php echo htmlspecialchars($profile['unit_number'] && $profile['unit_number'] !== 'Not Assigned' ? $profile['unit_number'] : 'Not Assigned'); ?>"
                                    class="input-solid bg-slate-100 text-slate-500 font-medium" readonly>
                                <p class="text-[11px] text-slate-400 mt-1">Your residential unit is assigned by property
                                    management and cannot be changed here.</p>
                            </div>
                        </div>
                        <div class="flex justify-end pt-4 border-t border-slate-100">
                            <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg shadow-blue-500/30">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tab: Family Info -->
            <div x-show="activeTab === 'family'" x-transition.opacity.duration.300ms style="display: none;"
                x-data="{ membersCount: 2 }">

                <div class="mb-8">
                    <h3 class="text-2xl font-black text-slate-800 flex items-center gap-3">
                        <div
                            class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center">
                            <i data-lucide="users"></i></div>
                        Family Members
                    </h3>
                    <p class="text-slate-500 ml-14 mt-1">Manage details for your family members residing with you.</p>
                </div>

                <!-- Existing Members List -->
                <?php
                if ($has_family_table) {
                    try {
                        $fam_res = mysqli_query($conn, "SELECT * FROM family_members WHERE user_id = '$user_id'");
                        if ($fam_res && mysqli_num_rows($fam_res) > 0) {
                            echo '<div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm mb-8">
                                    <h4 class="font-bold text-slate-800 mb-4 border-b border-slate-100 pb-3">Existing Members</h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">';
                            while ($f = mysqli_fetch_assoc($fam_res)) {
                                echo '<div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 flex flex-col relative group">
                                        <form method="POST" onsubmit="return confirm(\'Remove this member?\');" class="absolute top-3 right-3 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <input type="hidden" name="fam_id" value="' . $f['id'] . '">
                                            <button type="submit" name="remove_family" class="text-slate-400 hover:text-red-500 bg-white p-1.5 rounded-lg shadow-sm">
                                                  <i data-lucide="trash" class="w-4 h-4"></i>
                                              </button>
                                        </form>
                                        <p class="font-bold text-slate-800 mb-1 max-w-[80%] line-clamp-1">' . htmlspecialchars($f['member_name']) . '</p>
                                        <div class="text-xs text-slate-500 space-y-1 mt-2">
                                            <p><span class="font-semibold">Age:</span> ' . $f['age'] . '</p>
                                            <p><span class="font-semibold">Relation:</span> ' . htmlspecialchars($f['relation'] ?? 'N/A') . '</p>
                                            <p class="line-clamp-1"><span class="font-semibold">Occupation:</span> ' . htmlspecialchars($f['occupation'] ?? 'N/A') . '</p>
                                            <p><span class="font-semibold inline-block mt-1 bg-red-50 text-red-600 px-2 py-0.5 rounded">Blood: ' . htmlspecialchars($f['blood_group'] ?? 'N/A') . '</span></p>
                                        </div>
                                      </div>';
                            }
                            echo '</div></div>';
                        }
                    } catch (Throwable $e) {
                        echo '<div class="text-sm text-slate-500 italic">Family members unavailable.</div>';
                    }
                } else {
                    echo '<div class="text-sm text-slate-500 italic">Family members table is missing. Please run the database query provided.</div>';
                }
                ?>

                <!-- Add New Members Form -->
                <div class="bg-white rounded-3xl p-8 border border-slate-100 shadow-sm">
                    <div class="flex items-center justify-between mb-6 border-b border-slate-100 pb-4">
                        <h4 class="font-bold text-slate-800 text-lg">Add New Members</h4>
                        <button @click="membersCount++" type="button"
                            class="bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-4 py-2 rounded-xl font-bold text-sm flex items-center gap-2 transition-colors">
                            <i data-lucide="plus-circle" class="w-4 h-4"></i> Add More
                        </button>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="add_family" value="1">
                        <div class="space-y-6">

                            <template x-for="i in membersCount" :key="i">
                                <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl relative">
                                    <div class="absolute -top-3 -left-3 w-8 h-8 bg-indigo-600 text-white rounded-full flex items-center justify-center font-bold shadow-lg text-sm"
                                        x-text="i"></div>
                                    <button x-show="membersCount > 2" @click="membersCount--" type="button"
                                        class="absolute top-4 right-4 text-slate-400 hover:text-red-500">
                                        <i data-lucide="x-circle" class="w-5 h-5"></i>
                                    </button>
                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-600 mb-2">Member Full
                                                Name</label>
                                            <input type="text" name="f_name[]" class="input-solid !py-2.5"
                                                placeholder="e.g. Jane Doe">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-600 mb-2">Relation to
                                                you</label>
                                            <input type="text" name="f_rel[]" class="input-solid !py-2.5"
                                                placeholder="e.g. Spouse, Son">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-600 mb-2">Age</label>
                                            <input type="number" name="f_age[]" class="input-solid !py-2.5"
                                                placeholder="e.g. 30">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-600 mb-2">Blood
                                                Group</label>
                                            <select name="f_bg[]"
                                                class="input-solid !py-2.5 border-r-8 border-transparent">
                                                <option value="">Select Group</option>
                                                <?php foreach ($bgs as $bg)
                                                    echo "<option value=\"$bg\">$bg</option>"; ?>
                                            </select>
                                        </div>
                                        <div class="md:col-span-2">
                                            <label
                                                class="block text-xs font-bold text-slate-600 mb-2">Occupation</label>
                                            <input type="text" name="f_occ[]" class="input-solid !py-2.5"
                                                placeholder="e.g. Software Engineer, Student">
                                        </div>
                                    </div>
                                </div>
                            </template>

                        </div>

                        <div class="mt-8 flex justify-end">
                            <button type="submit"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg shadow-indigo-500/30 flex items-center gap-2">
                                <i data-lucide="save" class="w-5 h-5"></i> Save Members
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tab: Profile Settings (Password) -->
            <div x-show="activeTab === 'settings'" x-transition.opacity.duration.300ms style="display: none;">
                <div class="bg-white rounded-3xl p-8 border border-red-100 shadow-sm max-w-2xl">
                    <h3 class="text-2xl font-black text-slate-800 mb-8 flex items-center gap-3">
                        <div class="w-10 h-10 bg-red-100 text-red-600 rounded-xl flex items-center justify-center"><i
                                data-lucide="shield-alert"></i></div>
                        Security Settings
                    </h3>

                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="update_password" value="1">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Current Password</label>
                            <div class="relative">
                                <input type="password" name="old_password" id="old_password" required class="input-solid pr-10">
                                <button type="button" onclick="const p = document.getElementById('old_password'); const i = this.querySelector('i'); if (p.type === 'password') { p.type = 'text'; i.setAttribute('data-lucide', 'eye-off'); } else { p.type = 'password'; i.setAttribute('data-lucide', 'eye'); } lucide.createIcons();" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-red-500 transition-colors focus:outline-none">
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">New Password</label>
                            <div class="relative">
                                <input type="password" name="new_password" id="new_password" required pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}" title="Must contain at least one number and one uppercase and lowercase letter, and at least 6 or more characters" class="input-solid pr-10">
                                <button type="button" onclick="const p = document.getElementById('new_password'); const i = this.querySelector('i'); if (p.type === 'password') { p.type = 'text'; i.setAttribute('data-lucide', 'eye-off'); } else { p.type = 'password'; i.setAttribute('data-lucide', 'eye'); } lucide.createIcons();" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-red-500 transition-colors focus:outline-none">
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>
                            </div>
                            <p class="text-xs text-slate-400 mt-2">Must be at least 6 characters long with a mix of uppercase, lowercase, and numbers.</p>
                        </div>
                        <div class="pt-4 border-t border-slate-100">
                            <button type="submit"
                                class="bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg shadow-red-500/30">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>
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