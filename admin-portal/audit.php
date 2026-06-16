<?php
$current_page = 'audit';
$page_title   = 'Audit Logs';
require_once 'includes/auth.php';

// Aggregate meaningful events from existing tables
$events = [];

// Recent owner registrations
$r = mysqli_query($conn,"SELECT 'owner_reg' as type, CONCAT('New owner registered: ',COALESCE(up.full_name,u.email)) as msg, u.created_at as ts
    FROM users u LEFT JOIN user_profiles up ON u.id=up.user_id WHERE u.role_id=1 ORDER BY u.created_at DESC LIMIT 10");
if($r) while($row=mysqli_fetch_assoc($r)) $events[]=$row;

// Recent provider registrations
$r = mysqli_query($conn,"SELECT 'provider_reg' as type, CONCAT('New provider registered: ',COALESCE(sp.name,u.email)) as msg, u.created_at as ts
    FROM users u LEFT JOIN service_providers sp ON u.id=sp.user_id WHERE u.role_id=4 ORDER BY u.created_at DESC LIMIT 10");
if($r) while($row=mysqli_fetch_assoc($r)) $events[]=$row;

// Subscription changes
$r = mysqli_query($conn,"SELECT 'subscription' as type,
    CONCAT('Subscription updated for Building ', b.building_name, ' → ', sp.plan_name, ' (', os.status, ')') as msg,
    os.updated_at as ts
    FROM subscriptions os
    JOIN buildings b ON os.subscriber_id=b.id AND os.subscriber_type='building'
    LEFT JOIN subscription_plans sp ON os.plan_id=sp.id
    ORDER BY os.updated_at DESC LIMIT 15");
if($r) while($row=mysqli_fetch_assoc($r)) $events[]=$row;

// Coupons created (if any exist)
$r = mysqli_query($conn,"SELECT 'coupon' as type, CONCAT('Coupon generated: ', code, ' (-', discount_percent, '%)') as msg, created_at as ts FROM coupons ORDER BY created_at DESC LIMIT 10");
if($r) while($row=mysqli_fetch_assoc($r)) $events[]=$row;

// CCTV alerts
$r = mysqli_query($conn,"SELECT 'alert' as type, CONCAT('[CCTV] ',message) as msg, created_at as ts FROM cctv_alerts ORDER BY created_at DESC LIMIT 10");
if($r) while($row=mysqli_fetch_assoc($r)) $events[]=$row;

// Sort all events by ts desc
usort($events, fn($a,$b) => strtotime($b['ts']) - strtotime($a['ts']));
$events = array_slice($events, 0, 40);

$icon_map = [
    'owner_reg'    => ['icon'=>'user-plus',     'color'=>'blue'],
    'provider_reg' => ['icon'=>'briefcase',     'color'=>'violet'],
    'subscription' => ['icon'=>'credit-card',    'color'=>'emerald'],
    'coupon'       => ['icon'=>'ticket',        'color'=>'cyan'],
    'alert'        => ['icon'=>'shield-alert',   'color'=>'red'],
];

include 'includes/layout_header.php';
?>

<div class="mb-8">
    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Audit Logs</h1>
    <p class="text-slate-500 mt-2 text-sm md:text-base">Comprehensive timeline of platform activity and system alerts.</p>
</div>

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-800">System Activity Timeline</h2>
        </div>
        <button class="px-3 py-1.5 bg-white border border-slate-200 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-50 transition shadow-sm flex items-center gap-1.5">
            <i data-lucide="download" class="w-3.5 h-3.5"></i> Export
        </button>
    </div>
    
    <div class="p-6 md:p-8 max-w-3xl">
        <?php if(empty($events)): ?>
        <div class="py-12 text-center">
            <div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i data-lucide="activity" class="w-8 h-8 text-slate-400"></i>
            </div>
            <h3 class="text-slate-800 font-bold mb-1">No Activity Found</h3>
            <p class="text-slate-500 text-sm font-medium">System events will appear here.</p>
        </div>
        <?php else: ?>
        <div class="relative space-y-8 before:absolute before:inset-y-0 before:left-[23px] before:w-0.5 before:bg-slate-200">
            <?php foreach($events as $e):
                $ic = $icon_map[$e['type']] ?? ['icon'=>'activity','color'=>'slate'];
            ?>
            <div class="relative flex gap-6 group hover:bg-slate-50/50 p-2 -m-2 rounded-xl transition-colors">
                <div class="w-12 h-12 rounded-2xl bg-<?=$ic['color']?>-50 border border-<?=$ic['color']?>-200 flex items-center justify-center shrink-0 z-10 text-<?=$ic['color']?>-600 shadow-sm group-hover:scale-110 transition-transform">
                    <i data-lucide="<?=$ic['icon']?>" class="w-5 h-5"></i>
                </div>
                <div class="pt-2 flex-1">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-1">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-600">
                            <?=str_replace('_',' ',$e['type'])?>
                        </span>
                        <span class="text-xs font-bold text-slate-400 font-mono flex items-center gap-1">
                            <i data-lucide="clock" class="w-3 h-3"></i> <?=date('M d, Y H:i', strtotime($e['ts']))?>
                        </span>
                    </div>
                    <p class="text-sm font-medium text-slate-800 leading-relaxed"><?=htmlspecialchars($e['msg'])?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/layout_footer.php'; ?>
