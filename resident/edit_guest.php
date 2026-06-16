<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

require '../includes/db_config.php';

$resident_id = (int) $_SESSION['user_id'];
$is_admin = (isset($_SESSION['role_id']) && $_SESSION['role_id'] == 1);
$guest_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$guest = null;

if ($guest_id > 0) {
    // 1. Fetch Guest, Visit and Vehicle details
    $q = 'SELECT g.*, g.full_name as guest_name, g.phone_number as guest_phone, g.nid_passport_no as id_number,
                 vr.id as visit_id, vr.purpose, vr.resident_id,
                 gv.plate_number as vehicle_plate
          FROM guests g 
          JOIN visit_requests vr ON g.id = vr.guest_id 
          LEFT JOIN guest_vehicles gv ON vr.id = gv.visit_id
          WHERE g.id = ? AND vr.resident_id = ?';
    
    $stmt = mysqli_prepare($conn, $q);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ii', $guest_id, $resident_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($result && $row = mysqli_fetch_assoc($result)) {
            $guest = $row;
            
            // Extract metadata from purpose string for the form
            $purpose_raw = $guest['purpose'] ?? '';
            
            // Extract Relationship
            if (preg_match('/^\[(.*?)\]/', $purpose_raw, $matches)) {
                $guest['relationship'] = $matches[1];
                $guest['purpose_text'] = trim(preg_replace('/^\[.*?\]\s*/', '', preg_replace('/\s*\(Exp:.*?\)$/', '', $purpose_raw)));
            } else {
                $guest['relationship'] = '';
                $guest['purpose_text'] = $purpose_raw;
            }

            // Extract Total Guests
            if (preg_match('/Total: (\d+)/', $purpose_raw, $matches)) {
                $guest['total_guests'] = $matches[1];
            } else {
                $guest['total_guests'] = 1;
            }

            // Extract Expected Date/Time
            if (preg_match('/Exp: ([\d-]+)(?:\s+([\d:]+))?/', $purpose_raw, $matches)) {
                $guest['expected_date'] = $matches[1];
                $guest['expected_time'] = isset($matches[2]) ? $matches[2] : '';
            } else {
                $guest['expected_date'] = '';
                $guest['expected_time'] = '';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if (!$guest) {
    header("Location: ../resident/guest_passes.php?error=notfound");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['guest_name']);
    $phone = trim($_POST['guest_phone']);
    $count = (int) trim($_POST['total_guests']);
    $relation = trim($_POST['relationship']);
    $expected_date = trim($_POST['expected_date']);
    $expected_time = trim($_POST['expected_time']);
    $id_number = trim($_POST['id_number']);
    $purpose_text = trim($_POST['purpose']);
    $vehicle_plate = trim($_POST['vehicle_plate']);

    // Reconstruct the purpose string as used in the project
    // Format: [Relationship] Purpose (Exp: Date Time, Total: Count)
    $full_purpose = "[$relation] $purpose_text (Exp: $expected_date $expected_time, Total: $count)";

    // 1. Update Guests Table
    $u_guest = mysqli_prepare($conn, "UPDATE guests SET full_name=?, phone_number=?, nid_passport_no=? WHERE id=?");
    if ($u_guest) {
        mysqli_stmt_bind_param($u_guest, 'sssi', $name, $phone, $id_number, $guest_id);
        mysqli_stmt_execute($u_guest);
        mysqli_stmt_close($u_guest);
    }

    // 2. Update Visit Requests Table
    $visit_id = (int)$guest['visit_id'];
    $u_visit = mysqli_prepare($conn, "UPDATE visit_requests SET purpose=? WHERE id=? AND resident_id=?");
    if ($u_visit) {
        mysqli_stmt_bind_param($u_visit, 'sii', $full_purpose, $visit_id, $resident_id);
        mysqli_stmt_execute($u_visit);
        mysqli_stmt_close($u_visit);
    }

    // 3. Update Vehicle Plate
    if ($vehicle_plate !== '') {
        $check_v = mysqli_query($conn, "SELECT id FROM guest_vehicles WHERE visit_id = $visit_id");
        if (mysqli_num_rows($check_v) > 0) {
            mysqli_query($conn, "UPDATE guest_vehicles SET plate_number = '" . mysqli_real_escape_string($conn, $vehicle_plate) . "' WHERE visit_id = $visit_id");
        } else {
            mysqli_query($conn, "INSERT INTO guest_vehicles (visit_id, plate_number) VALUES ($visit_id, '" . mysqli_real_escape_string($conn, $vehicle_plate) . "')");
        }
    } else {
        mysqli_query($conn, "DELETE FROM guest_vehicles WHERE visit_id = $visit_id");
    }

    header('Location: ../guest_details.php?id=' . $guest_id . '&success=updated');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Guest | EasyHome Security</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>

<body class="min-h-screen bg-slate-50 text-slate-900 font-sans selection:bg-emerald-200 selection:text-emerald-900"
    :class="{'lg:pl-72': desktopSidebarOpen, 'lg:pl-20': !desktopSidebarOpen}"
    x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php 
    if ($is_admin) {
        $active_page = 'guests';
        include '../includes/owner_sidebar.php'; 
    } else {
        include '../includes/resident_sidebar.php'; 
    }
    ?>

    <div class="flex flex-col min-h-screen">
        <!-- Header / Navbar -->
        <header class="bg-white/80 backdrop-blur-xl border-b border-emerald-100/50 sticky top-0 z-30 shadow-sm shadow-emerald-900/5">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <button @click="sidebarOpen = true" class="lg:hidden p-2 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors">
                            <i data-lucide="menu" class="w-6 h-6"></i>
                        </button>
                        <div>
                            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Edit Guest</h1>
                            <p class="text-xs font-bold text-emerald-500 uppercase tracking-widest mt-0.5">Modify Access Pass</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="<?php echo BASE_URL; ?>guest_details.php?id=<?php echo htmlspecialchars($guest['id']); ?>" class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl font-bold text-sm hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-600 transition-all shadow-sm group">
                            <i data-lucide="arrow-left" class="w-4 h-4 text-slate-400 group-hover:-translate-x-1 transition-transform"></i> <span class="hidden sm:inline">Back to Details</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 max-w-4xl w-full mx-auto p-4 sm:p-6 lg:p-8">
            <div class="bg-white border border-emerald-100/60 rounded-[2.5rem] shadow-sm overflow-hidden">
                <div class="bg-gradient-to-br from-emerald-500/10 via-emerald-50/20 to-transparent px-8 py-7 border-b border-emerald-100/50 relative overflow-hidden">
                    <div class="absolute -right-6 -top-6 text-emerald-200/30 pointer-events-none"><i data-lucide="edit-3" class="w-36 h-36"></i></div>
                    <h2 class="text-xl font-black text-slate-900 flex items-center gap-3 relative z-10">
                        <span class="w-2 h-7 bg-emerald-500 rounded-full"></span>
                        Update Guest Information
                    </h2>
                    <p class="text-sm text-slate-500 font-medium mt-1 ml-5 relative z-10">Changes will be updated in the gate biometric system immediately.</p>
                </div>

                <div class="p-8 sm:p-10">
                    <form action="edit_guest.php?id=<?php echo htmlspecialchars($guest['id']); ?>" method="POST" class="space-y-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Name -->
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Guest Full Name *</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i data-lucide="user" class="w-4 h-4"></i>
                                    </div>
                                    <input type="text" name="guest_name" value="<?php echo htmlspecialchars($guest['guest_name']); ?>" required
                                           class="w-full bg-slate-50 border-2 border-slate-100 text-slate-900 font-bold rounded-xl pl-11 pr-4 py-3.5 focus:bg-white focus:border-emerald-500 focus:ring-0 outline-none transition-all">
                                </div>
                            </div>

                            <!-- Phone -->
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Phone Number *</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i data-lucide="phone" class="w-4 h-4"></i>
                                    </div>
                                    <input type="text" name="guest_phone" value="<?php echo htmlspecialchars($guest['guest_phone']); ?>" required
                                           class="w-full bg-slate-50 border-2 border-slate-100 text-slate-900 font-bold rounded-xl pl-11 pr-4 py-3.5 focus:bg-white focus:border-emerald-500 focus:ring-0 outline-none transition-all">
                                </div>
                            </div>

                            <!-- Relationship -->
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Relationship</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i data-lucide="link" class="w-4 h-4"></i>
                                    </div>
                                    <input type="text" name="relationship" value="<?php echo htmlspecialchars($guest['relationship']); ?>"
                                           class="w-full bg-slate-50 border-2 border-slate-100 text-slate-900 font-bold rounded-xl pl-11 pr-4 py-3.5 focus:bg-white focus:border-emerald-500 focus:ring-0 outline-none transition-all">
                                </div>
                            </div>

                            <!-- Total Guests -->
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Total Guests</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i data-lucide="users" class="w-4 h-4"></i>
                                    </div>
                                    <input type="number" name="total_guests" min="1" value="<?php echo htmlspecialchars($guest['total_guests']); ?>"
                                           class="w-full bg-slate-50 border-2 border-slate-100 text-slate-900 font-bold rounded-xl pl-11 pr-4 py-3.5 focus:bg-white focus:border-emerald-500 focus:ring-0 outline-none transition-all">
                                </div>
                            </div>

                            <!-- Date -->
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Expected Date *</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i data-lucide="calendar" class="w-4 h-4"></i>
                                    </div>
                                    <input type="date" name="expected_date" value="<?php echo htmlspecialchars($guest['expected_date']); ?>" required
                                           class="w-full bg-slate-50 border-2 border-slate-100 text-slate-900 font-bold rounded-xl pl-11 pr-4 py-3.5 focus:bg-white focus:border-emerald-500 focus:ring-0 outline-none transition-all">
                                </div>
                            </div>

                            <!-- Time -->
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Expected Time</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i data-lucide="clock" class="w-4 h-4"></i>
                                    </div>
                                    <input type="time" name="expected_time" value="<?php echo htmlspecialchars($guest['expected_time'] ?? ''); ?>"
                                           class="w-full bg-slate-50 border-2 border-slate-100 text-slate-900 font-bold rounded-xl pl-11 pr-4 py-3.5 focus:bg-white focus:border-emerald-500 focus:ring-0 outline-none transition-all">
                                </div>
                            </div>

                            <!-- ID Number -->
                            <div class="md:col-span-2">
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">NID / Passport Number</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i data-lucide="credit-card" class="w-4 h-4"></i>
                                    </div>
                                    <input type="text" name="id_number" value="<?php echo htmlspecialchars($guest['id_number']); ?>"
                                           class="w-full bg-slate-50 border-2 border-slate-100 text-slate-900 font-bold rounded-xl pl-11 pr-4 py-3.5 focus:bg-white focus:border-emerald-500 focus:ring-0 outline-none transition-all">
                                </div>
                            </div>

                            <!-- Purpose -->
                            <div class="md:col-span-2">
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Purpose of Visit</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i data-lucide="align-left" class="w-4 h-4"></i>
                                    </div>
                                    <input type="text" name="purpose" value="<?php echo htmlspecialchars($guest['purpose_text']); ?>"
                                           class="w-full bg-slate-50 border-2 border-slate-100 text-slate-900 font-bold rounded-xl pl-11 pr-4 py-3.5 focus:bg-white focus:border-emerald-500 focus:ring-0 outline-none transition-all">
                                </div>
                            </div>

                            <!-- Vehicle Plate -->
                            <div class="md:col-span-2">
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2 ml-1">Vehicle Plate (Optional)</label>
                                <div class="relative group">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i data-lucide="car" class="w-4 h-4"></i>
                                    </div>
                                    <input type="text" name="vehicle_plate" value="<?php echo htmlspecialchars($guest['vehicle_plate'] ?? ''); ?>"
                                           class="w-full bg-slate-50 border-2 border-slate-100 text-slate-900 font-bold rounded-xl pl-11 pr-4 py-3.5 focus:bg-white focus:border-emerald-500 focus:ring-0 outline-none transition-all">
                                </div>
                            </div>
                        </div>

                        <div class="pt-8 border-t border-slate-100 flex items-center justify-end gap-3">
                            <a href="<?php echo BASE_URL; ?>guest_details.php?id=<?php echo $guest['id']; ?>" class="px-6 py-3.5 text-slate-500 font-bold text-sm hover:text-slate-800 transition-colors">Cancel</a>
                            <button type="submit"
                                    class="flex items-center gap-2.5 px-8 py-3.5 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white font-black rounded-2xl shadow-lg shadow-emerald-600/30 transition-all text-sm">
                                <i data-lucide="save" class="w-5 h-5"></i> Update Guest Info
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script>lucide.createIcons();</script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
    <script>lucide.createIcons();</script>
    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>

</html>