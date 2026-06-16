<?php
session_start();
require_once 'includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];

// Fetch user profile
$user_name = "User";
try {
    $q = "SELECT full_name FROM user_profiles WHERE user_id = '$user_id'";
    $res = mysqli_query($conn, $q);
    if ($res && mysqli_num_rows($res) > 0) {
        $p = mysqli_fetch_assoc($res);
        $user_name = $p['full_name'];
    }
} catch (Exception $e) {}

// Mark all as read if requested
if (isset($_GET['action']) && $_GET['action'] === 'mark_all_read') {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = $user_id");
    header("Location: notifications_history.php");
    exit();
}

// Fetch all notifications
$notifications = [];
$q = "SELECT * FROM notifications WHERE user_id = $user_id ORDER BY created_at DESC";
$res = mysqli_query($conn, $q);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $notifications[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification History | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .canvas-shadow {
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.1), 0 20px 40px -20px rgba(0, 0, 0, 0.05);
        }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-[#F0FAF4] text-slate-900 flex" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php 
    if ($role_id == 1) {
        include 'includes/owner_sidebar.php';
    } else {
        include 'includes/resident_sidebar.php';
    }
    ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[256px]' : 'lg:ml-[100px]'"
        class="flex-1 transition-all duration-300 ease-in-out flex flex-col min-h-screen pt-4 pb-8 px-4 sm:px-6 lg:px-8 gap-4">
        
        <!-- Brand Header -->
        <div class="flex items-center justify-center py-2">
            <h2 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2">
                <i data-lucide="home" class="w-6 h-6 text-emerald-500"></i>
                Nibash
            </h2>
        </div>

        <!-- Floating Canvas -->
        <div class="flex-1 bg-white/95 backdrop-blur-2xl rounded-[2.5rem] canvas-shadow border border-emerald-500/10 flex flex-col overflow-hidden relative">
            
            <!-- Page Header -->
            <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-xl border-b border-emerald-50 px-8 py-6 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-emerald-50 rounded-2xl flex items-center justify-center shadow-sm border border-emerald-100">
                        <i data-lucide="bell" class="w-6 h-6 text-emerald-600"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl font-black text-slate-900 tracking-tight">Notification History</h1>
                        <p class="text-sm font-bold text-slate-500 uppercase tracking-widest">All your alerts in one place</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <?php if (!empty($notifications)): ?>
                        <a href="?action=mark_all_read" class="flex items-center gap-2 px-5 py-2.5 bg-slate-100 hover:bg-emerald-50 text-slate-600 hover:text-emerald-700 text-sm font-bold rounded-2xl transition-all">
                            <i data-lucide="check-check" class="w-4 h-4"></i>
                            Mark All Read
                        </a>
                    <?php endif; ?>
                    <button onclick="window.history.back()" class="p-2.5 text-slate-400 hover:text-emerald-600 rounded-2xl hover:bg-emerald-50 transition-colors">
                        <i data-lucide="arrow-left" class="w-5 h-5"></i>
                    </button>
                </div>
            </header>

            <!-- Notifications List -->
            <div class="flex-1 overflow-y-auto p-8 max-w-4xl mx-auto w-full">
                <?php if (empty($notifications)): ?>
                    <div class="flex flex-col items-center justify-center h-full text-center py-20">
                        <div class="w-20 h-20 bg-slate-50 rounded-[2rem] flex items-center justify-center mb-6">
                            <i data-lucide="bell-off" class="w-10 h-10 text-slate-300"></i>
                        </div>
                        <h3 class="text-xl font-bold text-slate-800">No Notifications Yet</h3>
                        <p class="text-slate-500 mt-2">When something happens in the community, you'll see it here.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($notifications as $note): ?>
                            <a href="<?= htmlspecialchars(BASE_URL . $note['link']) ?>" 
                               onclick="markSingleRead(<?= $note['id'] ?>)"
                               class="group block p-6 rounded-[2rem] border transition-all duration-300 <?= $note['is_read'] ? 'bg-white border-slate-100 hover:border-emerald-200 hover:shadow-xl hover:shadow-emerald-500/5' : 'bg-emerald-50/30 border-emerald-100 shadow-sm hover:shadow-lg' ?>">
                                <div class="flex items-start gap-5">
                                    <div class="w-12 h-12 shrink-0 rounded-2xl flex items-center justify-center transition-colors <?= $note['is_read'] ? 'bg-slate-50 text-slate-400 group-hover:bg-emerald-50 group-hover:text-emerald-600' : 'bg-emerald-100 text-emerald-600' ?>">
                                        <?php 
                                            $icon = 'bell';
                                            if (strpos($note['title'], 'mentioned') !== false) $icon = 'at-sign';
                                            if (strpos($note['title'], 'Reply') !== false) $icon = 'message-circle';
                                            if (strpos($note['title'], 'Unknown') !== false) $icon = 'shield-alert';
                                        ?>
                                        <i data-lucide="<?= $icon ?>" class="w-5 h-5"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between mb-1">
                                            <h4 class="font-bold text-slate-900 group-hover:text-emerald-600 transition-colors"><?= htmlspecialchars($note['title']) ?></h4>
                                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider"><?= date('M d, Y • h:i A', strtotime($note['created_at'])) ?></span>
                                        </div>
                                        <p class="text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($note['message']) ?></p>
                                    </div>
                                    <?php if (!$note['is_read']): ?>
                                        <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 mt-2 shrink-0 shadow-[0_0_10px_rgba(16,185,129,0.5)]"></div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <script>
        lucide.createIcons();

        function markSingleRead(id) {
            fetch('api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_read', id: id })
            });
        }
    </script>

    <?php include 'chatbot/chat_widget.php'; ?>
</body>
</html>
