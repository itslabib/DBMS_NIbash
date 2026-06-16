<?php
// admin-portal/includes/layout_header.php
// Usage: include after auth.php. Sets $current_page variable before including.
// $current_page must be set before this file is included.
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Admin Portal' ?> | Smart Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: { fontFamily: { sans: ['Inter', 'sans-serif'] } } }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .nav-active { background:#ecfdf5; color:#047857; font-weight:600; }
        .nav-active i { color:#059669; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
        .fade-in { animation: fadeIn .3s ease; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased h-screen flex overflow-hidden">

<?php if (!$is_logged_in): ?>
<!-- ===== LOGIN SCREEN ===== -->
<div class="flex-1 flex items-center justify-center bg-slate-100">
    <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-slate-50 border border-slate-200 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i data-lucide="shield-alert" class="w-8 h-8 text-slate-700"></i>
            </div>
            <h2 class="text-2xl font-bold text-slate-900">Admin Portal</h2>
            <p class="text-xs uppercase tracking-widest text-emerald-600 font-semibold mt-1">Restricted Access</p>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="mb-5">
                <label class="block text-sm font-medium text-slate-700 mb-2">Admin ID</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center"><i data-lucide="user" class="h-5 w-5 text-slate-400"></i></div>
                    <input type="text" name="admin_id" required class="pl-10 w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 p-3 transition" placeholder="Enter admin ID">
                </div>
            </div>
            <div class="mb-6">
                <label class="block text-sm font-medium text-slate-700 mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center"><i data-lucide="lock" class="h-5 w-5 text-slate-400"></i></div>
                    <input type="password" name="admin_pass" required class="pl-10 w-full bg-slate-50 border border-slate-200 text-slate-900 text-sm rounded-xl focus:ring-emerald-500 focus:border-emerald-500 p-3 transition" placeholder="••••••••">
                </div>
            </div>
            <?php if (!empty($login_error)): ?>
            <div class="mb-5 p-3 rounded-xl bg-red-50 border border-red-100 flex items-center gap-2 text-red-600 text-sm">
                <i data-lucide="alert-circle" class="w-4 h-4"></i> <?= htmlspecialchars($login_error) ?>
            </div>
            <?php endif; ?>
            <button type="submit" class="w-full text-white bg-emerald-600 hover:bg-emerald-700 font-semibold rounded-xl text-sm px-5 py-3.5 flex items-center justify-center gap-2 transition">
                Authenticate <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>
        </form>
    </div>
</div>
<script>if(typeof lucide!=='undefined')lucide.createIcons();</script>
</body></html>
<?php exit; endif; ?>

<!-- ===== SIDEBAR ===== -->
<aside class="w-64 bg-white border-r border-slate-200 flex flex-col h-full z-20 shrink-0 shadow-sm">
    <div class="h-16 flex items-center px-6 border-b border-slate-100 shrink-0">
        <div class="flex items-center gap-3 text-emerald-600">
            <i data-lucide="building" class="w-6 h-6"></i>
            <div>
                <span class="block text-lg font-bold tracking-tight text-slate-900 leading-none">Nibash</span>
                <span class="block text-[0.6rem] uppercase tracking-wider font-semibold text-emerald-600 leading-none mt-1">Super Admin</span>
            </div>
        </div>
    </div>
    <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
        <?php
        $nav = [
            ['page' => 'index',         'icon' => 'layout-dashboard', 'label' => 'Overview'],
            ['page' => 'approvals',     'icon' => 'check-circle',     'label' => 'Approvals',     'badge' => $pending_count ?? 0],
            ['page' => 'users',         'icon' => 'users',            'label' => 'User Management'],
            ['page' => 'subscriptions', 'icon' => 'credit-card',      'label' => 'Subscriptions', 'badge' => $pending_payment_count ?? 0, 'badge_color' => 'emerald'],
            ['page' => 'audit',         'icon' => 'shield',           'label' => 'Audit Logs'],
        ];
        foreach ($nav as $n):
            $active = ($current_page === $n['page']);
            $badge = $n['badge'] ?? 0;
            $bcolor = $n['badge_color'] ?? 'amber';
        ?>
        <a href="<?= $n['page'] ?>.php" class="group flex items-center justify-between px-3 py-2.5 rounded-xl text-sm font-medium transition-colors <?= $active ? 'nav-active' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' ?>">
            <div class="flex items-center gap-3">
                <i data-lucide="<?= $n['icon'] ?>" class="w-5 h-5 <?= $active ? 'text-emerald-600' : 'text-slate-400 group-hover:text-slate-600' ?>"></i>
                <span><?= $n['label'] ?></span>
            </div>
            <?php if ($badge > 0): ?>
            <span class="bg-<?= $bcolor ?>-100 text-<?= $bcolor ?>-700 py-0.5 px-2 rounded-full text-xs font-bold"><?= $badge ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-slate-100 shrink-0">
        <div class="flex items-center gap-3 mb-4 px-2">
            <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold text-sm">AD</div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-900 truncate">System Admin</p>
                <p class="text-xs text-slate-500">Root Access</p>
            </div>
        </div>
        <a href="index.php?logout=1" class="flex items-center justify-center gap-2 w-full px-4 py-2 text-sm font-medium text-red-600 bg-red-50 hover:bg-red-100 rounded-xl transition-colors">
            <i data-lucide="log-out" class="w-4 h-4"></i> Logout
        </a>
    </div>
</aside>

<!-- ===== MAIN CONTENT SHELL ===== -->
<main class="flex-1 flex flex-col min-w-0 bg-slate-50 overflow-y-auto">
    <header class="h-16 flex items-center justify-between px-8 bg-white border-b border-slate-200 shrink-0 shadow-sm sticky top-0 z-10">
        <div>
            <h1 class="text-base font-bold text-slate-900"><?= $page_title ?? '' ?></h1>
            <p class="text-xs text-slate-400 font-mono" id="sys-clock"></p>
        </div>
        <div class="flex items-center gap-3">
            <span class="flex items-center gap-1.5 text-xs font-medium text-emerald-600 bg-emerald-50 border border-emerald-100 px-3 py-1.5 rounded-full">
                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></span> System Live
            </span>
        </div>
    </header>
    <div class="p-8 fade-in">
