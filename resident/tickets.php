<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
if ($_SESSION['role_id'] == 1) {
    header("Location: ../owner/tickets.php");
    exit();
}

// Get owner_id
$q = mysqli_query($conn, "SELECT aa_o.user_id as owner_id 
                          FROM apartment_assignments aa_t
                          JOIN apartment_assignments aa_o ON aa_t.apt_id = aa_o.apt_id
                          WHERE aa_t.user_id = '$user_id' 
                          AND aa_t.is_active = 1 
                          AND aa_o.role = 'owner' 
                          AND aa_o.is_active = 1 
                          LIMIT 1");
$r = mysqli_fetch_assoc($q);
$owner_id = $r['owner_id'] ?? 0;

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

// Create Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $title = mysqli_real_escape_string($conn, trim($_POST['issue_title']));
    $desc = mysqli_real_escape_string($conn, trim($_POST['description']));
    $priority = mysqli_real_escape_string($conn, trim($_POST['priority']));

    $sql = "INSERT INTO service_requests (user_id, owner_id, issue_title, description, priority, status) VALUES ('$user_id', '$owner_id', '$title', '$desc', '$priority', 'Pending')";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success_msg'] = "Service request submitted successfully.";
        header("Location: tickets.php");
        exit();
    } else {
        $error_msg = "Error submitting request.";
    }
}

// Edit Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_ticket'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $title = mysqli_real_escape_string($conn, trim($_POST['edit_title']));
    $desc = mysqli_real_escape_string($conn, trim($_POST['edit_desc']));
    $priority = mysqli_real_escape_string($conn, trim($_POST['edit_priority']));

    $sql = "UPDATE service_requests SET issue_title='$title', description='$desc', priority='$priority' WHERE id='$ticket_id' AND user_id='$user_id' AND status='Pending'";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success_msg'] = "Ticket updated successfully.";
        header("Location: tickets.php");
        exit();
    }
}

// Delete Ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $ticket_id = intval($_POST['delete_id']);

    $sql = "DELETE FROM service_requests WHERE id='$ticket_id' AND user_id='$user_id' AND status='Pending'";
    if (mysqli_query($conn, $sql)) {
        $_SESSION['success_msg'] = "Ticket deleted successfully.";
        header("Location: tickets.php");
        exit();
    }
}

// Rate Provider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rate_ticket'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $rating = intval($_POST['rating']);
    
    $upd = mysqli_query($conn, "UPDATE service_requests SET rating = '$rating' WHERE id = '$ticket_id' AND user_id = '$user_id'");
    if($upd) {
        $_SESSION['success_msg'] = "Thanks for your feedback!";
        header("Location: tickets.php");
        exit();
    }
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
    <title>Service Tickets | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php include '../includes/resident_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8">
        
       <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl border border-blue-100/60 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>

        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(59,130,246,0.15)] flex-1 flex flex-col overflow-hidden border border-blue-100/80 relative">
            <header class="bg-white/80 backdrop-blur-xl border-b border-blue-50 sticky top-0 z-40 shadow-sm">
                <div class="px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Service Tickets</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50 space-y-10">
                <?php if ($success_msg): ?>
                    <div class="bg-emerald-100 text-emerald-800 p-4 rounded-xl mb-4 text-sm font-bold"><?= $success_msg ?></div>
                <?php endif; ?>
                <?php if ($error_msg): ?>
                    <div class="bg-rose-100 text-rose-800 p-4 rounded-xl mb-4 text-sm font-bold"><?= $error_msg ?></div>
                <?php endif; ?>

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 border-b border-slate-200">
                    <div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Maintenance Requests</h1>
                        <p class="text-slate-500 text-sm mt-1">Submit issues to management and track resolution.</p>
                    </div>
                    <button onclick="document.getElementById('ticket-form').classList.toggle('hidden')" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl shadow-md flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i> New Request
                    </button>
                </div>

                <div id="ticket-form" class="hidden bg-white p-6 rounded-2xl shadow-sm border border-slate-200 mb-8">
                    <form method="POST">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Issue Title</label>
                                <input type="text" name="issue_title" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. AC broken in living room">
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Priority</label>
                                <select name="priority" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                    <option value="Emergency">Emergency</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Description</label>
                                <textarea name="description" required rows="3" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Describe the issue in detail..."></textarea>
                            </div>
                        </div>
                        <div class="mt-4 flex justify-end gap-3">
                            <button type="button" onclick="document.getElementById('ticket-form').classList.add('hidden')" class="px-6 py-3 bg-slate-100 text-slate-600 font-bold rounded-xl">Cancel</button>
                            <button type="submit" name="create_ticket" class="px-6 py-3 bg-slate-900 text-white font-bold rounded-xl">Submit Ticket</button>
                        </div>
                    </form>
                </div>

                <div class="space-y-4">
                <?php
                $tickets_q = mysqli_query($conn, "SELECT sr.*, sp.name as provider_name, sp.phone as provider_phone, sp.email as provider_email 
                                                  FROM service_requests sr 
                                                  LEFT JOIN service_providers sp ON sr.assigned_provider_id = sp.id 
                                                  WHERE sr.user_id = '$user_id' ORDER BY sr.created_at DESC");
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
                        <div x-data="{ editing: false, viewingProvider: false }" class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col gap-4 transition-all relative">
                            
                            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4" x-show="!editing">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2 flex-wrap">
                                        <h3 class="font-bold text-slate-900 text-lg"><?= htmlspecialchars($t['issue_title']) ?></h3>
                                        <span class="px-2.5 py-1 text-[10px] uppercase tracking-widest font-bold rounded-md <?= $badge ?>"><?= $t['status'] ?></span>
                                        <?php if($t['priority'] === 'Emergency'): ?>
                                            <span class="px-2.5 py-1 text-[10px] uppercase tracking-widest font-bold rounded-md bg-rose-100 text-rose-700 flex items-center gap-1"><i data-lucide="alert-triangle" class="w-3 h-3"></i> Emergency</span>
                                        <?php elseif($t['priority'] === 'High'): ?>
                                            <span class="px-2.5 py-1 text-[10px] uppercase tracking-widest font-bold rounded-md bg-orange-100 text-orange-700">High Priority</span>
                                        <?php endif; ?>
                                        
                                        <?php if($t['status'] === 'Pending'): ?>
                                            <div class="ml-auto flex items-center gap-2">
                                                <button @click="editing = true" class="text-slate-400 hover:text-blue-600 transition-colors" title="Edit">
                                                    <i data-lucide="edit-2" class="w-4 h-4"></i>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Delete this ticket?');">
                                                    <input type="hidden" name="delete_id" value="<?= $t['id'] ?>">
                                                    <button type="submit" name="delete_ticket" class="text-slate-400 hover:text-rose-600 transition-colors" title="Delete">
                                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-sm text-slate-600 mb-2"><?= nl2br(htmlspecialchars($t['description'])) ?></p>
                                    <div class="text-xs text-slate-400 font-medium flex flex-wrap items-center gap-4">
                                        <span>Added: <?= date('M d, Y h:i A', strtotime($t['created_at'])) ?></span>
                                        <?php if($t['provider_name']): ?>
                                            <button @click="viewingProvider = !viewingProvider" class="text-blue-600 hover:text-blue-800 focus:outline-none font-bold flex items-center gap-1 transition-colors px-2 py-1 rounded bg-blue-50">
                                                <i data-lucide="wrench" class="w-3 h-3"></i> Assigned to: <?= htmlspecialchars($t['provider_name']) ?> <i data-lucide="chevron-down" class="w-3 h-3 transition-transform" :class="viewingProvider ? 'rotate-180' : ''"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if($t['provider_name']): ?>
                                        <div x-show="viewingProvider" x-transition class="mt-3 bg-blue-50/50 rounded-xl p-4 border border-blue-100 flex flex-col sm:flex-row gap-4 sm:items-center text-sm" style="display: none;">
                                            <div class="flex items-center gap-2 text-slate-700">
                                                <i data-lucide="phone" class="w-4 h-4 text-blue-500"></i> 
                                                <?= $t['provider_phone'] ? htmlspecialchars($t['provider_phone']) : 'N/A' ?>
                                            </div>
                                            <div class="flex items-center gap-2 text-slate-700">
                                                <i data-lucide="mail" class="w-4 h-4 text-blue-500"></i> 
                                                <?= $t['provider_email'] ? htmlspecialchars($t['provider_email']) : 'N/A' ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($t['status'] === 'Completed' && !$t['rating']): ?>
                                <div class="shrink-0 bg-slate-50 p-4 rounded-xl border border-slate-100">
                                    <p class="text-[10px] font-bold text-slate-500 mb-2 uppercase tracking-widest">Rate the service</p>
                                    <form method="POST" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                                        <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                        <select name="rating" required class="text-sm bg-white border border-slate-200 rounded-lg px-2 py-1.5 focus:ring-blue-500 text-amber-500 font-bold">
                                            <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
                                            <option value="4">⭐⭐⭐⭐ (4/5)</option>
                                            <option value="3">⭐⭐⭐ (3/5)</option>
                                            <option value="2">⭐⭐ (2/5)</option>
                                            <option value="1">⭐ (1/5)</option>
                                        </select>
                                        <button type="submit" name="rate_ticket" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-1.5 rounded-lg text-xs font-bold transition-colors">Submit</button>
                                    </form>
                                </div>
                                <?php elseif ($t['rating']): ?>
                                <div class="shrink-0 text-center bg-slate-50 p-3 rounded-xl border border-slate-100">
                                    <span class="block text-lg">
                                        <?php for($i=1; $i<=5; $i++) { echo $i <= $t['rating'] ? '⭐' : '☆'; } ?>
                                    </span>
                                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">You Rated</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Edit Form inline -->
                            <?php if($t['status'] === 'Pending'): ?>
                            <div x-show="editing" x-transition class="bg-slate-50 p-4 rounded-xl border border-slate-200" style="display: none;">
                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Issue Title</label>
                                            <input type="text" name="edit_title" required value="<?= htmlspecialchars($t['issue_title']) ?>" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Priority</label>
                                            <select name="edit_priority" required class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                <option value="Low" <?= $t['priority'] == 'Low' ? 'selected' : '' ?>>Low</option>
                                                <option value="Medium" <?= $t['priority'] == 'Medium' ? 'selected' : '' ?>>Medium</option>
                                                <option value="High" <?= $t['priority'] == 'High' ? 'selected' : '' ?>>High</option>
                                                <option value="Emergency" <?= $t['priority'] == 'Emergency' ? 'selected' : '' ?>>Emergency</option>
                                            </select>
                                        </div>
                                        <div class="col-span-1 sm:col-span-2">
                                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1.5">Description</label>
                                            <textarea name="edit_desc" required rows="2" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"><?= htmlspecialchars($t['description']) ?></textarea>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="editing = false" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-lg transition-colors">Cancel</button>
                                        <button type="submit" name="edit_ticket" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg transition-colors">Save Changes</button>
                                    </div>
                                </form>
                            </div>
                            <?php endif; ?>
                            
                        </div>
                        <?php
                    }
                } else {
                    echo "<div class='text-center py-12 bg-white rounded-2xl border-2 border-dashed border-slate-200'><p class='text-slate-500 font-medium'>You haven't submitted any service requests.</p></div>";
                }
                ?>
                </div>

            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>js/resident_logic.js"></script>
    <script>lucide.createIcons();</script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>