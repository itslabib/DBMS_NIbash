<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'update_profile') {
        // Only allow updating these specific fields
        $full_name = mysqli_real_escape_string($conn, trim($_POST['full_name']));
        $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
        $dob = !empty($_POST['dob']) ? mysqli_real_escape_string($conn, trim($_POST['dob'])) : NULL;
        $occupation = mysqli_real_escape_string($conn, trim($_POST['occupation']));
        
        $check_prof = mysqli_query($conn, "SELECT id FROM user_profiles WHERE user_id='$user_id'");
        if (mysqli_num_rows($check_prof) > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE user_profiles SET full_name=?, phone=?, dob=?, occupation=? WHERE user_id=?");
            mysqli_stmt_bind_param($stmt, "ssssi", $full_name, $phone, $dob, $occupation, $user_id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Profile information updated successfully.";
            } else {
                $error = "Failed to update profile information.";
            }
        } else {
            // Fallback just in case profile wasn't created during registration
            $stmt = mysqli_prepare($conn, "INSERT INTO user_profiles (user_id, full_name, phone, dob, occupation) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issss", $user_id, $full_name, $phone, $dob, $occupation);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Profile information created successfully.";
            } else {
                $error = "Failed to create profile information.";
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'change_password') {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        $check_pass = mysqli_query($conn, "SELECT password FROM users WHERE id='$user_id'");
        $row = mysqli_fetch_assoc($check_pass);
        
        if ($row['password'] === $old_password) {
            if ($new_password === $confirm_password) {
                // Incorporate strict password validation
                if (strlen($new_password) < 6 || !preg_match('/[a-z]/', $new_password) || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                    $error = "Password must be at least 6 characters and include a lowercase letter, an uppercase letter, and a digit.";
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE users SET password=? WHERE id=?");
                    mysqli_stmt_bind_param($stmt, "si", $new_password, $user_id);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = "Password changed successfully.";
                    } else {
                        $error = "Failed to change password.";
                    }
                }
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            $error = "Incorrect current password.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_account') {
        $confirm_delete = $_POST['confirm_delete'];
        if ($confirm_delete === 'DELETE') {
            @mysqli_query($conn, "DELETE FROM user_profiles WHERE user_id = '$user_id'");
            @mysqli_query($conn, "DELETE FROM apartment_assignments WHERE user_id = '$user_id'");
            
            $del_stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
            mysqli_stmt_bind_param($del_stmt, "i", $user_id);
            if (mysqli_stmt_execute($del_stmt)) {
                session_destroy();
                header("Location: " . BASE_URL . "index.php?msg=account_deleted");
                exit();
            } else {
                $error = "Failed to delete account. Please ensure all related data is removed first.";
            }
        } else {
            $error = "You must type DELETE to confirm.";
        }
    }
}

// Fetch comprehensive user, profile, and apartment data
$query_str = "SELECT u.username, u.email, p.full_name, p.phone, p.nid, p.dob, p.occupation, p.permanent_address, 
                     b.building_number, b.building_name AS apartment_name, a.apt_number AS unit_number, a.floor_number 
              FROM users u 
              LEFT JOIN user_profiles p ON u.id = p.user_id 
              LEFT JOIN apartment_assignments aa ON aa.user_id = u.id AND aa.role = 'owner' AND aa.is_active = 1 
              LEFT JOIN apartments a ON aa.apt_id = a.id 
              LEFT JOIN buildings b ON a.building_id = b.id
              WHERE u.id='$user_id' LIMIT 1";

$query = mysqli_query($conn, $query_str);
$owner_info = mysqli_fetch_assoc($query);

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'information';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($_POST['action'] == 'change_password' || $_POST['action'] == 'delete_account') {
        $active_tab = 'security';
    } else {
        $active_tab = 'information';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Profile | EasyHome</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #ecfdf5; }
        ::-webkit-scrollbar-thumb { background: #a7f3d0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6ee7b7; }
    </style>
</head>
<body class="bg-[#f2fbf6] text-slate-800 tracking-tight flex overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true, activeTab: '<?= $active_tab ?>' }">

    <?php $active_page = 'profile'; include '../includes/owner_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8 w-full" id="main-content">
        
        <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-emerald-100/60 hover:shadow-[0_4px_15px_-3px_rgba(16,185,129,0.15)] hover:border-emerald-200 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    Easy<span class="text-emerald-600">Home</span>
                </span>
            </a>
        </div>

        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(16,185,129,0.15)] flex-1 flex flex-col overflow-hidden border border-emerald-100/80 relative">
            <header class="bg-white/80 backdrop-blur-xl border-b border-emerald-50 sticky top-0 z-40 shadow-sm">
                <div class="px-8 py-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Profile & Settings</span>
                        </h2>
                    </div>
                                
                    <div class="flex gap-6 sm:mt-0 overflow-x-auto">
                        <button @click="activeTab = 'information'" :class="activeTab === 'information' ? 'border-emerald-500 text-emerald-600 font-black' : 'border-transparent text-slate-400 hover:text-emerald-500 font-bold'" class="pb-1 pt-1 border-b-[3px] transition-all flex items-center gap-2 text-sm uppercase tracking-widest shrink-0">
                            <i data-lucide="user" class="w-4 h-4"></i> Information
                        </button>
                        <button @click="activeTab = 'security'" :class="activeTab === 'security' ? 'border-emerald-500 text-emerald-600 font-black' : 'border-transparent text-slate-400 hover:text-emerald-500 font-bold'" class="pb-1 pt-1 border-b-[3px] transition-all flex items-center gap-2 text-sm uppercase tracking-widest shrink-0">
                            <i data-lucide="shield-alert" class="w-4 h-4"></i> Security
                        </button>
                        <a href="subscribe.php" class="pb-1 pt-1 border-b-[3px] border-transparent text-amber-500 hover:text-amber-600 font-bold transition-all flex items-center gap-2 text-sm uppercase tracking-widest shrink-0">
                            <i data-lucide="crown" class="w-4 h-4"></i> Subscription
                        </a>
                    </div>
                </div>
            </header>

            <div class="flex-1 p-8 sm:p-12 max-w-5xl mx-auto w-full bg-white overflow-y-auto">
                <?php if ($success): ?>
                    <div class="bg-emerald-50 text-emerald-700 border border-emerald-200 p-5 rounded-2xl mb-8 flex items-start gap-4 shadow-sm">
                        <i data-lucide="check-circle" class="w-6 h-6 shrink-0 text-emerald-500"></i>
                        <div>
                            <h4 class="font-extrabold">Success</h4>
                            <p class="text-sm font-medium mt-1"><?= htmlspecialchars($success) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-rose-50 text-rose-700 border border-rose-200 p-5 rounded-2xl mb-8 flex items-start gap-4 shadow-sm">
                        <i data-lucide="alert-circle" class="w-6 h-6 shrink-0 text-rose-500"></i>
                        <div>
                            <h4 class="font-extrabold">Error</h4>
                            <p class="text-sm font-medium mt-1"><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <div x-cloak x-show="activeTab === 'information'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="bg-white rounded-[1.5rem] border border-emerald-50 shadow-[0_8px_24px_-4px_rgba(0,0,0,0.04)] overflow-hidden p-8 sm:p-10">
                        <div class="flex items-center gap-4 mb-8 pb-6 border-b border-emerald-50">
                            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center border border-emerald-100 shadow-sm">
                                <i data-lucide="user" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-black tracking-tight text-slate-900">Profile Details</h2>
                                <p class="text-sm text-slate-500 font-bold mt-1">Update your editable information</p>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="space-y-10">
                                <div>
                                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6 flex justify-between items-center bg-slate-50/50 p-3 rounded-lg border border-slate-100">
                                        Editable Information
                                        <span class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded shadow-sm font-bold capitalize flex items-center gap-1.5"><i data-lucide="edit-2" class="w-3.5 h-3.5"></i> Allowed</span>
                                    </h3>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                                        <div>
                                            <label class="block text-sm font-bold text-slate-700 mb-2">Full Name <span class="text-rose-500">*</span></label>
                                            <input type="text" name="full_name" class="w-full bg-slate-50/50 border border-slate-200 rounded-xl px-5 py-3.5 text-sm font-medium focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all outline-none" value="<?= htmlspecialchars($owner_info['full_name'] ?? '') ?>" required>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-slate-700 mb-2">Phone Number <span class="text-rose-500">*</span></label>
                                            <input type="text" name="phone" pattern="[0-9]{11}" title="Phone number must be exactly 11 digits" class="w-full bg-slate-50/50 border border-slate-200 rounded-xl px-5 py-3.5 text-sm font-medium focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all outline-none" value="<?= htmlspecialchars($owner_info['phone'] ?? '') ?>" required oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-slate-700 mb-2">Date of Birth</label>
                                            <input type="date" name="dob" class="w-full bg-slate-50/50 border border-slate-200 rounded-xl px-5 py-3.5 text-sm font-medium focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all outline-none" value="<?= htmlspecialchars($owner_info['dob'] ?? '') ?>">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-bold text-slate-700 mb-2">Occupation</label>
                                            <input type="text" name="occupation" class="w-full bg-slate-50/50 border border-slate-200 rounded-xl px-5 py-3.5 text-sm font-medium focus:bg-white focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all outline-none" value="<?= htmlspecialchars($owner_info['occupation'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-4">
                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-black py-3.5 px-8 rounded-xl transition-all shadow-md shadow-emerald-500/20 hover:shadow-lg hover:shadow-emerald-500/40 hover:-translate-y-0.5 inline-flex items-center gap-2">
                                        <i data-lucide="save" class="w-5 h-5"></i> Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>

                        <hr class="border-emerald-50 my-10">

                        <div class="space-y-10">
                            <div>
                                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6 flex justify-between items-center bg-slate-50/50 p-3 rounded-lg border border-slate-100">
                                    Identity & Contact
                                    <span class="text-slate-500 bg-white border border-slate-200 px-2 py-1 rounded shadow-sm font-bold capitalize flex items-center gap-1.5"><i data-lucide="lock" class="w-3.5 h-3.5"></i> Read Only</span>
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Username</label>
                                        <input type="text" class="w-full bg-slate-100/80 border border-slate-200 text-slate-500 cursor-not-allowed rounded-xl px-5 py-3.5 text-sm font-bold" value="<?= htmlspecialchars($owner_info['username'] ?? '') ?>" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Email Address</label>
                                        <input type="email" class="w-full bg-slate-100/80 border border-slate-200 text-slate-500 cursor-not-allowed rounded-xl px-5 py-3.5 text-sm font-bold" value="<?= htmlspecialchars($owner_info['email'] ?? '') ?>" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">NID Number</label>
                                        <input type="text" class="w-full bg-slate-100/80 border border-slate-200 text-slate-500 cursor-not-allowed rounded-xl px-5 py-3.5 text-sm font-mono tracking-widest font-bold" value="<?= htmlspecialchars($owner_info['nid'] ?? 'Not Provided') ?>" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Full Address</label>
                                        <input type="text" class="w-full bg-slate-100/80 border border-slate-200 text-slate-500 cursor-not-allowed rounded-xl px-5 py-3.5 text-sm font-bold" value="<?= htmlspecialchars($owner_info['permanent_address'] ?? 'Not Provided') ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <hr class="border-emerald-50">

                            <div>
                                <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-6 flex justify-between items-center bg-slate-50/50 p-3 rounded-lg border border-slate-100">
                                    Registered Property Details
                                    <span class="text-slate-500 bg-white border border-slate-200 px-2 py-1 rounded shadow-sm font-bold capitalize flex items-center gap-1.5"><i data-lucide="lock" class="w-3.5 h-3.5"></i> Read Only</span>
                                </h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8">
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Building Number</label>
                                        <input type="text" class="w-full bg-emerald-50/30 border border-emerald-100 text-emerald-800 cursor-not-allowed rounded-xl px-5 py-3.5 text-sm font-bold shadow-inner" value="<?= htmlspecialchars($owner_info['building_number'] ?? 'Not Assigned') ?>" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Building Name</label>
                                        <input type="text" class="w-full bg-emerald-50/30 border border-emerald-100 text-emerald-800 cursor-not-allowed rounded-xl px-5 py-3.5 text-sm font-bold shadow-inner" value="<?= htmlspecialchars($owner_info['apartment_name'] ?? 'Not Assigned') ?>" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Unit Number</label>
                                        <input type="text" class="w-full bg-emerald-50/30 border border-emerald-100 text-emerald-800 cursor-not-allowed rounded-xl px-5 py-3.5 text-sm font-bold shadow-inner" value="<?= htmlspecialchars($owner_info['unit_number'] ?? 'Not Assigned') ?>" readonly>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Floor Number</label>
                                        <input type="text" class="w-full bg-emerald-50/30 border border-emerald-100 text-emerald-800 cursor-not-allowed rounded-xl px-5 py-3.5 text-sm font-bold shadow-inner" value="<?= htmlspecialchars($owner_info['floor_number'] ?? 'Not Assigned') ?>" readonly>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-400 font-bold mt-6 bg-slate-50 p-4 rounded-xl border border-slate-100 flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-full bg-white flex items-center justify-center shrink-0 shadow-sm border border-slate-200"><i data-lucide="info" class="w-4 h-4 text-slate-500"></i></span>
                                    If property details or identity details need updating, please contact system administration.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div x-cloak x-show="activeTab === 'security'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                        
                        <div class="bg-white rounded-[1.5rem] border border-emerald-50 shadow-[0_8px_24px_-4px_rgba(0,0,0,0.04)] overflow-hidden p-8 sm:p-10">
                            <div class="flex items-center gap-4 mb-8 pb-6 border-b border-emerald-50">
                                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100 shadow-sm">
                                    <i data-lucide="shield" class="w-6 h-6"></i>
                                </div>
                                <h2 class="text-2xl font-black tracking-tight text-slate-900">Change Password</h2>
                            </div>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="change_password">
                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Current Password</label>
                                        <input type="password" name="old_password" class="w-full bg-slate-50/50 border border-slate-200 rounded-xl px-5 py-3.5 text-sm focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 transition-all outline-none font-medium" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">New Password</label>
                                        <input type="password" name="new_password" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}" title="Must contain at least 6 characters, including one uppercase letter, one lowercase letter, and one number" class="w-full bg-slate-50/50 border border-slate-200 rounded-xl px-5 py-3.5 text-sm focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 transition-all outline-none font-medium" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-bold text-slate-700 mb-2">Confirm New Password</label>
                                        <input type="password" name="confirm_password" class="w-full bg-slate-50/50 border border-slate-200 rounded-xl px-5 py-3.5 text-sm focus:bg-white focus:border-indigo-400 focus:ring-4 focus:ring-indigo-500/10 transition-all outline-none font-medium" required>
                                    </div>
                                </div>
                                <div class="mt-10">
                                    <button type="submit" class="w-full bg-slate-900 hover:bg-indigo-600 text-white font-black py-4 px-6 rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-indigo-500/30 flex items-center justify-center gap-2">
                                        <i data-lucide="key" class="w-5 h-5"></i> Update Password
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="bg-rose-50/30 rounded-[1.5rem] border border-rose-100 shadow-[0_8px_24px_-4px_rgba(244,63,94,0.1)] p-8 sm:p-10 text-center relative overflow-hidden flex flex-col justify-center group">
                            <div class="absolute -top-10 -right-10 w-40 h-40 bg-rose-500/5 blur-3xl rounded-full pointer-events-none group-hover:bg-rose-500/10 transition-colors"></div>
                            <div class="absolute -bottom-10 -left-10 w-40 h-40 bg-rose-500/5 blur-3xl rounded-full pointer-events-none group-hover:bg-rose-500/10 transition-colors"></div>

                            <div class="w-20 h-20 bg-white border border-rose-100 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-6 shadow-sm">
                                <i data-lucide="alert-triangle" class="w-10 h-10"></i>
                            </div>
                            <h3 class="text-2xl font-black text-rose-700 mb-3 tracking-tight">Danger Zone</h3>
                            <p class="text-sm font-bold text-rose-600/70 mb-10 leading-relaxed max-w-sm mx-auto">
                                Once you delete your account, there is no going back. All data linked to this account will be irreversibly removed.
                            </p>
                            
                            <button onclick="document.getElementById('delete-modal').classList.remove('hidden'); document.getElementById('delete-modal').classList.add('flex');" class="w-full mt-auto bg-white text-rose-600 hover:bg-rose-600 hover:text-white border-2 border-rose-200 hover:border-rose-600 font-black py-4 px-6 rounded-xl transition-all shadow-sm">
                                Delete Account
                            </button>
                        </div>

                    </div>
                </div>
                
            </div>
        </div>
    </main>

    <div id="delete-modal" class="hidden fixed inset-0 z-[100] bg-slate-900/60 backdrop-blur-md items-center justify-center -m-4 sm:m-0 p-4">
        <div class="bg-white max-w-md w-full rounded-[2rem] shadow-2xl border border-slate-100 overflow-hidden transform transition-all">
            <div class="bg-rose-50 p-8 flex flex-col items-center border-b border-rose-100 relative overflow-hidden">
                <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMiIgY3k9IjIiIHI9IjIiIGZpbGw9IiNmZGUwZTQiLz48L3N2Zz4=')] opacity-50"></div>
                <div class="w-20 h-20 bg-white text-rose-600 rounded-full flex items-center justify-center mb-5 ring-8 ring-rose-100 shadow-sm relative z-10">
                    <i data-lucide="trash-2" class="w-10 h-10"></i>
                </div>
                <h3 class="text-2xl font-black text-slate-900 tracking-tight text-center relative z-10">Are you absolutely sure?</h3>
            </div>
            <div class="p-8 text-center">
                <p class="text-slate-500 font-bold mb-8 text-sm leading-relaxed">This action <strong class="text-rose-600">cannot be undone</strong>. This will permanently delete your account and remove your data from our servers.</p>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="delete_account">
                    <div class="mb-8 text-left bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <label class="block text-sm font-bold text-slate-700 mb-3 text-center">Type <span class="bg-white border border-slate-200 px-2 py-1 rounded-md text-slate-900 font-mono tracking-widest shadow-sm user-select-all">DELETE</span> to confirm</label>
                        <input type="text" name="confirm_delete" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-center font-mono tracking-widest font-bold text-slate-900 focus:border-rose-500 focus:ring-4 focus:ring-rose-500/20 transition-all outline-none shadow-inner" autocomplete="off">
                    </div>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button type="button" onclick="document.getElementById('delete-modal').classList.add('hidden'); document.getElementById('delete-modal').classList.remove('flex');" class="flex-1 px-4 py-3.5 bg-white border-2 border-slate-200 text-slate-600 font-black rounded-xl hover:bg-slate-50 hover:border-slate-300 transition-colors">Cancel</button>
                        <button type="submit" class="flex-1 px-4 py-3.5 bg-rose-600 text-white font-black rounded-xl hover:bg-rose-700 transition-all shadow-md shadow-rose-500/20 hover:-translate-y-0.5">Delete Forever</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
<script src="<?php echo BASE_URL; ?>js/toast.js"></script>

<?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>