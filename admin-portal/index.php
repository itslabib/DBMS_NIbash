<?php
$current_page = 'index';
$page_title   = 'Overview';
require_once 'includes/auth.php';

$total_users    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role_id!=3"))['c'];
$total_owners   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role_id=1"))['c'];
$total_residents= mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role_id=2"))['c'];
$total_providers= mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role_id=4"))['c'];

$active_subs    = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM subscriptions WHERE subscriber_type='building' AND status IN('active','trial') AND expires_at>NOW()"))['c'];
$expired_subs   = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM subscriptions WHERE subscriber_type='building' AND (status='expired' OR (expires_at<NOW() AND status NOT IN('suspended','active','trial')))"))['c'];
$total_apts     = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM apartments"))['c'];

// Revenue Calculation (Current MRR)
$mrr_row = mysqli_fetch_assoc(mysqli_query($conn,"
    SELECT SUM(sp.price_monthly) as mrr 
    FROM subscriptions bs 
    JOIN subscription_plans sp ON bs.plan_id = sp.id 
    WHERE bs.subscriber_type='building' AND bs.status='active' AND bs.expires_at > NOW()
"));
$mrr = $mrr_row['mrr'] ?? 0;

// Provider pending count
$pending_prov_count = mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) c FROM users WHERE role_id=4 AND status='inactive'"))['c'];

// Recent signups
$recent = [];
$r = mysqli_query($conn,"
    SELECT u.id, u.email, u.role_id, u.created_at, up.full_name, sp.name as provider_name 
    FROM users u 
    LEFT JOIN user_profiles up ON u.id=up.user_id 
    LEFT JOIN service_providers sp ON u.id=sp.user_id
    WHERE u.role_id!=3 
    ORDER BY u.created_at DESC LIMIT 6
");
if($r) while($row=mysqli_fetch_assoc($r)) $recent[]=$row;

include 'includes/layout_header.php';
?>

<div class="mb-8">
    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">System Overview</h1>
    <p class="text-slate-500 mt-2 text-sm md:text-base">Monitor platform growth, revenue, and pending actions.</p>
</div>

<!-- Key Metrics -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    
    <!-- MRR Card (Special Design) -->
    <div class="col-span-2 lg:col-span-1 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl p-6 shadow-lg shadow-emerald-500/20 text-white relative overflow-hidden group">
        <div class="absolute -right-6 -top-6 w-24 h-24 bg-white/10 rounded-full blur-2xl group-hover:scale-150 transition-transform duration-700"></div>
        <div class="flex items-center gap-3 mb-2">
            <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center backdrop-blur-sm">
                <i data-lucide="trending-up" class="w-4 h-4 text-white"></i>
            </div>
            <p class="text-emerald-50 text-sm font-medium">Monthly Revenue</p>
        </div>
        <p class="text-3xl font-black mt-4">৳<?=number_format($mrr, 0)?></p>
        <p class="text-xs text-emerald-100/80 mt-1">From active subscriptions</p>
    </div>

    <?php
    $stats = [
        ['label'=>'Total Users',    'val'=>$total_users,     'icon'=>'users',       'color'=>'blue'],
        ['label'=>'Buildings',      'val'=>$total_apts,      'icon'=>'building',    'color'=>'violet'],
        ['label'=>'Active Subs',    'val'=>$active_subs,     'icon'=>'credit-card', 'color'=>'emerald'],
    ];
    foreach($stats as $s): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow group">
        <div class="flex items-center justify-between mb-4">
            <div class="w-10 h-10 rounded-xl bg-<?=$s['color']?>-50 text-<?=$s['color']?>-600 flex items-center justify-center group-hover:scale-110 transition-transform">
                <i data-lucide="<?=$s['icon']?>" class="w-5 h-5"></i>
            </div>
        </div>
        <p class="text-2xl font-black text-slate-900"><?=number_format($s['val'])?></p>
        <p class="text-sm text-slate-500 font-medium mt-1"><?=$s['label']?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Secondary Stats -->
<div class="grid grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
        <p class="text-xl font-bold text-slate-800"><?=number_format($total_owners)?></p>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mt-1">Owners</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
        <p class="text-xl font-bold text-slate-800"><?=number_format($total_residents)?></p>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mt-1">Residents</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
        <p class="text-xl font-bold text-slate-800"><?=number_format($total_providers)?></p>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mt-1">Providers</p>
    </div>
    <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
        <p class="text-xl font-bold text-red-500"><?=number_format($expired_subs)?></p>
        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mt-1">Expired Subs</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Pending Approvals Shortcut -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h2 class="font-bold text-slate-800">Pending Actions</h2>
                <p class="text-xs text-slate-500 mt-0.5">Accounts requiring manual verification.</p>
            </div>
            <?php $total_pending = $pending_count + $pending_prov_count; ?>
            <?php if($total_pending>0): ?>
            <span class="bg-amber-100 text-amber-700 text-xs font-bold px-2.5 py-1 rounded-md shadow-sm"><?=$total_pending?> Pending</span>
            <?php endif; ?>
        </div>
        <div class="p-6 flex-1 flex flex-col justify-center">
            
            <?php if($total_pending===0): ?>
            <div class="text-center py-6">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="check" class="w-6 h-6"></i>
                </div>
                <p class="text-slate-500 text-sm font-medium">All caught up! No pending approvals.</p>
            </div>
            <?php else: ?>
            <div class="space-y-4 mb-6">
                <div class="flex items-center justify-between p-4 rounded-xl border <?= $pending_count>0 ? 'border-amber-200 bg-amber-50' : 'border-slate-100 bg-slate-50' ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="shield" class="w-5 h-5 <?= $pending_count>0 ? 'text-amber-600' : 'text-slate-400' ?>"></i>
                        <span class="font-semibold text-slate-700">Owner Registrations</span>
                    </div>
                    <span class="font-black text-lg <?= $pending_count>0 ? 'text-amber-700' : 'text-slate-400' ?>"><?=$pending_count?></span>
                </div>
                <div class="flex items-center justify-between p-4 rounded-xl border <?= $pending_prov_count>0 ? 'border-amber-200 bg-amber-50' : 'border-slate-100 bg-slate-50' ?>">
                    <div class="flex items-center gap-3">
                        <i data-lucide="briefcase" class="w-5 h-5 <?= $pending_prov_count>0 ? 'text-amber-600' : 'text-slate-400' ?>"></i>
                        <span class="font-semibold text-slate-700">Provider Registrations</span>
                    </div>
                    <span class="font-black text-lg <?= $pending_prov_count>0 ? 'text-amber-700' : 'text-slate-400' ?>"><?=$pending_prov_count?></span>
                </div>
            </div>
            <a href="approvals.php" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-slate-900 text-white hover:bg-slate-800 rounded-xl text-sm font-bold transition shadow-sm">
                Review Approvals <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Signups -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h2 class="font-bold text-slate-800">Recent Signups</h2>
                <p class="text-xs text-slate-500 mt-0.5">Latest users to join the platform.</p>
            </div>
            <a href="users.php" class="text-xs text-emerald-600 font-bold hover:underline">View all</a>
        </div>
        <div class="divide-y divide-slate-100 flex-1">
            <?php foreach($recent as $u): 
                $name = $u['full_name'] ?? $u['provider_name'] ?? $u['email'];
                $role_badge = match((int)$u['role_id']) {
                    1 => ['bg'=>'bg-blue-50', 'text'=>'text-blue-700', 'lbl'=>'Owner'],
                    2 => ['bg'=>'bg-cyan-50', 'text'=>'text-cyan-700', 'lbl'=>'Resident'],
                    4 => ['bg'=>'bg-violet-50', 'text'=>'text-violet-700', 'lbl'=>'Provider'],
                    default => ['bg'=>'bg-slate-50', 'text'=>'text-slate-700', 'lbl'=>'User']
                };
            ?>
            <div class="px-6 py-4 flex items-center gap-4 hover:bg-slate-50/50 transition-colors">
                <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 text-slate-500 flex items-center justify-center text-sm font-black uppercase shrink-0 shadow-sm">
                    <?=strtoupper(substr($name,0,2))?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-900 truncate"><?=htmlspecialchars($name)?></p>
                    <p class="text-xs font-medium text-slate-500 mt-0.5"><?=date('M d, Y',strtotime($u['created_at']))?></p>
                </div>
                <div>
                    <span class="px-2.5 py-1 rounded-md text-[10px] font-black uppercase tracking-wider <?= $role_badge['bg'] ?> <?= $role_badge['text'] ?>">
                        <?= $role_badge['lbl'] ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if(empty($recent)): ?><p class="text-center text-slate-400 text-sm py-10 font-medium">No users yet.</p><?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/layout_footer.php'; ?>
