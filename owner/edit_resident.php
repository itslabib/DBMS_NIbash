<?php
session_start();
require_once '../includes/db_config.php';
mysqli_report(MYSQLI_REPORT_ERROR);

// Ensure the user is an owner
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = false;
$error = '';

if (!isset($_GET['id'])) {
    header("Location: ../owner/residents.php");
    exit();
}

$resident_id = (int) $_GET['id'];

// Check if resident exists and has role_id=2
$check_owner = @mysqli_query($conn, "SELECT id FROM users WHERE id='$resident_id' AND role_id=2");
if (!$check_owner || mysqli_num_rows($check_owner) == 0) {
    header("Location: ../owner/residents.php?error=not_found");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $nid = mysqli_real_escape_string($conn, $_POST['nid']);
    $dob = mysqli_real_escape_string($conn, $_POST['dob'] ?? '');
    $occupation = mysqli_real_escape_string($conn, $_POST['occupation']);
    $unit_number = mysqli_real_escape_string($conn, $_POST['unit_number']);

    // Handle File Upload for profile_image if exists
    $photo_sql = "";
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
        $file_mime = mime_content_type($_FILES['profile_image']['tmp_name']);
        if (in_array($file_mime, $allowed_types)) {
            if (!is_dir('assets/uploads/guests'))
                mkdir('assets/uploads/guests', 0777, true);
            $filename = 'res_' . time() . '_' . rand(1000, 9999) . '.jpg';
            $photo_path = 'assets/uploads/guests/' . $filename;
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $photo_path)) {
                $photo_sql = ", profile_image = '$photo_path'";
            }
        }
    }

    $update_prof = "UPDATE user_profiles SET full_name='$full_name', phone='$phone', nid='$nid', dob=" . ($dob ? "'$dob'" : "NULL") . ", occupation='$occupation', unit_number='$unit_number' $photo_sql WHERE user_id='$resident_id'";

    if (mysqli_query($conn, $update_prof)) {
        $success = true;
    } else {
        $error = "Failed to update resident details.";
    }
}

// Fetch Resident Current Data
$res_query = @mysqli_query($conn, "SELECT p.*, u.email as username FROM user_profiles p JOIN users u ON p.user_id = u.id WHERE u.id='$resident_id'");
$resident_info = mysqli_fetch_assoc($res_query);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Resident | EasyHome</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 font-sans antialiased text-slate-800" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">
    
    <?php $active_page = 'dashboard';
    include '../includes/owner_sidebar.php'; ?>

    <!-- Main Content -->
    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen" id="main-content">
        <header class="bg-white border-b border-slate-200 sticky top-0 z-40">
            <div class="px-6 sm:px-8 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    
                    <button onclick="toggleSidebar()" class="md:hidden p-2 text-slate-400 hover:bg-slate-100 rounded-lg">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    <div>
                        <h1 class="text-2xl font-bold text-slate-800">Edit Resident</h1>
                        <p class="text-sm text-slate-500 mt-1">Update resident information for <?= htmlspecialchars($resident_info['full_name']) ?></p>
                    </div>
                </div>
                <a href="<?php echo BASE_URL; ?>owner/residents.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-lg flex items-center gap-2">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                    Back to Directory
                </a>
            </div>
        </header>

        <div class="p-6 sm:px-8 flex-1">
            <div class="max-w-4xl mx-auto">
                <?php if ($success): ?>
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                if(typeof showCustomPopup === "function") showCustomPopup("Resident Updated Successfully!", "success");
                            });
                        </script>
                <?php endif; ?>

                <?php if ($error): ?>
                        <script>
                            document.addEventListener("DOMContentLoaded", function() {
                                if(typeof showCustomPopup === "function") showCustomPopup("<?= addslashes($error) ?>", "error");
                            });
                        </script>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" class="space-y-8 relative">
                    <!-- Personal info -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50 flex gap-3 items-center">
                            <i data-lucide="user" class="w-5 h-5 text-indigo-500"></i>
                            <h3 class="font-bold text-slate-800">Personal Information</h3>
                        </div>
                        <div class="p-6 flex flex-col md:flex-row gap-8">
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1">Full Name *</label>
                                    <input type="text" name="full_name" required value="<?= htmlspecialchars($resident_info['full_name']) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1">Phone Number *</label>
                                    <input type="tel" name="phone" required value="<?= htmlspecialchars($resident_info['phone']) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-semibold text-slate-700 mb-1">Upload Photo (Optional)</label>
                                    <input type="file" name="profile_image" accept="image/*" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 bg-white">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security ID and Assignment -->
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200 bg-slate-50/50 flex gap-3 items-center">
                            <i data-lucide="shield-check" class="w-5 h-5 text-indigo-500"></i>
                            <h3 class="font-bold text-slate-800">Verification & Apartment</h3>
                        </div>
                        <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">NID / Passport Number *</label>
                                <input type="text" name="nid" required value="<?= htmlspecialchars($resident_info['nid'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Date of Birth *</label>
                                <input type="date" name="dob" required value="<?= htmlspecialchars($resident_info['dob'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Occupation</label>
                                <input type="text" name="occupation" required value="<?= htmlspecialchars($resident_info['occupation'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Assigned Flat/Unit Number *</label>
                                <input type="text" name="unit_number" required value="<?= htmlspecialchars($resident_info['unit_number'] ?? '') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-300 focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>
                    </div>

                    <!-- Submit -->
                    <div class="flex justify-end gap-4 border-t border-slate-200 pt-6">
                        <button type="submit" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-sm transition-all focus:ring-4 focus:ring-indigo-500/20 flex items-center gap-2">
                            <i data-lucide="save" class="w-5 h-5"></i>
                            Save Changes
                        </button>
                    </div>
                </form>
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






