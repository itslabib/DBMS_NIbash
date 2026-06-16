<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

$owner_id = $_SESSION['user_id'];

$success_msg = '';
$error_msg = '';

if (isset($_SESSION['success_msg'])) {
    $success_msg = $_SESSION['success_msg'];
    unset($_SESSION['success_msg']);
}
if (isset($_SESSION['error_msg'])) {
    $error_msg = $_SESSION['error_msg'];
    unset($_SESSION['error_msg']);
}

// Update Ticket logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $status = mysqli_real_escape_string($conn, trim($_POST['status']));
    $provider_id = empty($_POST['provider_id']) ? 'NULL' : intval($_POST['provider_id']);
    
    $upd = mysqli_query($conn, "UPDATE service_requests SET status = '$status', assigned_provider_id = $provider_id WHERE id = '$ticket_id' AND owner_id = '$owner_id'");
    
    if($upd) {
        $_SESSION['success_msg'] = "Ticket updated successfully.";
        header("Location: tickets.php");
        exit();
    } else {
        $error_msg = "Error updating ticket.";
    }
}

// Delete Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $ticket_id = intval($_POST['delete_id']);
    $del = mysqli_query($conn, "DELETE FROM service_requests WHERE id = '$ticket_id' AND owner_id = '$owner_id'");
    if($del) {
        $_SESSION['success_msg'] = "Ticket deleted.";
        header("Location: tickets.php");
        exit();
    }
}
?>
<?php
$resident_building_name = 'Nibash';
$owner_building_id = 0;
try {
    $uid_for_b = $_SESSION['user_id'] ?? 0;
    if ($uid_for_b) {
        $bq = @mysqli_query($conn, "SELECT b.id as b_id, b.building_name, b.building_number FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id JOIN buildings b ON a.building_id = b.id WHERE aa.user_id = '$uid_for_b' AND aa.is_active=1 LIMIT 1");
        if ($bq && mysqli_num_rows($bq) > 0) {
            $brow = mysqli_fetch_assoc($bq);
            $resident_building_name = !empty($brow['building_name']) ? $brow['building_name'] : $brow['building_number'];
            $owner_building_id = $brow['b_id'];
        } else {
            $mq = @mysqli_query($conn, "SELECT b.id as b_id, b.building_name, b.building_number FROM building_managers bm JOIN buildings b ON bm.building_id = b.id WHERE bm.user_id = '$uid_for_b' LIMIT 1");
            if ($mq && mysqli_num_rows($mq) > 0) {
                $mrow = mysqli_fetch_assoc($mq);
                $resident_building_name = !empty($mrow['building_name']) ? $mrow['building_name'] : $mrow['building_number'];
                $owner_building_id = $mrow['b_id'];
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
    <title>Manage Tickets | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php include '../includes/owner_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8">
        
       <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl border border-emerald-100/60 transition-all">
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
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Service Requests</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50 space-y-8">
                <?php if ($success_msg): ?>
                    <div class="bg-emerald-100 text-emerald-800 p-4 rounded-xl mb-4 text-sm font-bold"><?= $success_msg ?></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="bg-rose-100 text-rose-800 p-4 rounded-xl mb-4 text-sm font-bold"><?= $error_msg ?></div>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 border-b border-slate-200">
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            Manage Tickets
                        </h1>
                        <p class="text-slate-500 text-sm mt-1">Assign providers and update status of resident maintenance requests.</p>
                    </div>
                </div>

                <div class="space-y-6">
                <?php
                // Fetch all requests for this owner
                $tickets_q = mysqli_query($conn, "SELECT sr.*, 
                                                  up.full_name as resident_name, 
                                                  sp.name as provider_name 
                                                  FROM service_requests sr 
                                                  LEFT JOIN user_profiles up ON sr.user_id = up.user_id
                                                  LEFT JOIN service_providers sp ON sr.assigned_provider_id = sp.id 
                                                  WHERE sr.owner_id = '$owner_id' ORDER BY sr.created_at DESC");
                
                // Fetch valid providers for assignment
                $providers_q = mysqli_query($conn, "SELECT id, name, category_id, (SELECT category_name FROM service_categories WHERE id = category_id) as cat_name FROM service_providers WHERE (building_id = '$owner_building_id' OR building_id IS NULL) AND is_active = 1 ORDER BY name ASC");
                $providers = [];
                while($p = mysqli_fetch_assoc($providers_q)) {
                    $providers[] = $p;
                }

                if(mysqli_num_rows($tickets_q) > 0) {
                    while($t = mysqli_fetch_assoc($tickets_q)) {
                        $status_colors = [
                            'Pending' => 'bg-amber-100 text-amber-700',
                            'Assigned' => 'bg-blue-100 text-blue-700',
                            'In Progress' => 'bg-purple-100 text-purple-700',
                            'Completed' => 'bg-emerald-100 text-emerald-700',
                            'Cancelled' => 'bg-rose-100 text-rose-700'
                        ];
                        $badge = $status_colors[$t['status']] ?? 'bg-slate-100 text-slate-700';
                        ?>
                        <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-sm border border-slate-200 flex flex-col xl:flex-row gap-8 xl:items-start group transition-all hover:shadow-md hover:border-emerald-100 relative overflow-hidden">
                            <?php if($t['priority'] === 'Emergency'): ?>
                                <div class="absolute top-0 right-0 w-2 h-full bg-rose-500"></div>
                            <?php endif; ?>
                            
                            <div class="flex-1 space-y-4">
                                <div>
                                    <div class="flex items-center gap-3 flex-wrap">
                                        <h3 class="font-black text-slate-900 text-xl"><?= htmlspecialchars($t['issue_title']) ?></h3>
                                        <span class="px-2.5 py-1 text-[10px] uppercase tracking-widest font-bold rounded-md <?= $badge ?>"><?= $t['status'] ?></span>
                                        <?php if($t['priority'] === 'Emergency'): ?>
                                            <span class="px-2.5 py-1 text-[10px] uppercase tracking-widest font-bold rounded-md bg-rose-100 text-rose-700 flex items-center gap-1"><i data-lucide="alert-triangle" class="w-3 h-3"></i> Emergency</span>
                                        <?php elseif($t['priority'] === 'High'): ?>
                                            <span class="px-2.5 py-1 text-[10px] uppercase tracking-widest font-bold rounded-md bg-orange-100 text-orange-700">High Priority</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2 text-sm text-slate-600 bg-slate-50 p-4 rounded-xl border border-slate-100">
                                        <?= nl2br(htmlspecialchars($t['description'])) ?>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-medium text-slate-500">
                                    <div class="flex items-center gap-1.5"><i data-lucide="user" class="w-4 h-4 text-emerald-500"></i> Resident: <span class="font-bold text-slate-800"><?= htmlspecialchars($t['resident_name']) ?></span></div>
                                    <div class="flex items-center gap-1.5"><i data-lucide="calendar" class="w-4 h-4 text-emerald-500"></i> Submitted: <?= date('M d, Y h:i A', strtotime($t['created_at'])) ?></div>
                                    <?php if($t['rating']): ?>
                                        <div class="flex items-center gap-1.5"><i data-lucide="star" class="w-4 h-4 text-amber-400 fill-amber-400"></i> Rating: <span class="font-bold text-slate-800"><?= $t['rating'] ?>/5</span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="xl:w-80 shrink-0 bg-slate-50 p-5 rounded-2xl border border-slate-100">
                                <h4 class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <i data-lucide="settings-2" class="w-3 h-3"></i> Update Status
                                </h4>
                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                    
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Status</label>
                                        <select name="status" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all font-medium">
                                            <option value="Pending" <?= $t['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Assigned" <?= $t['status'] == 'Assigned' ? 'selected' : '' ?>>Assigned</option>
                                            <option value="In Progress" <?= $t['status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="Completed" <?= $t['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                            <option value="Cancelled" <?= $t['status'] == 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1.5">Assign Provider</label>
                                        <select name="provider_id" class="w-full text-sm bg-white border border-slate-200 rounded-lg px-3 py-2 outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200 transition-all font-medium">
                                            <option value="">-- Unassigned --</option>
                                            <?php foreach($providers as $p): ?>
                                                <option value="<?= $p['id'] ?>" <?= $t['assigned_provider_id'] == $p['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['cat_name']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 pt-2">
                                        <button type="submit" name="update_ticket" class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wide rounded-lg transition-colors shadow-sm">Update</button>
                                        
                                        <button type="submit" name="delete_ticket" onclick="return confirm('Are you sure you want to delete this ticket?');" class="py-2 px-3 bg-white border border-slate-200 text-rose-500 hover:bg-rose-50 rounded-lg transition-colors" title="Delete Ticket">
                                            <input type="hidden" name="delete_id" value="<?= $t['id'] ?>">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='text-center py-16 bg-white rounded-2xl border-2 border-dashed border-slate-200'><div class='w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4'><i data-lucide=".'ticket'." class='w-6 h-6 text-slate-400'></i></div><h3 class='text-lg font-black text-slate-800 mb-1'>No Service Requests</h3><p class='text-slate-500 text-sm'>There are currently no maintenance tickets open.</p></div>";
                }
                ?>
                </div>

            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>js/owner_logic.js"></script>
    <script>lucide.createIcons();</script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>