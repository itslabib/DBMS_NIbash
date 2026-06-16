<?php
$current_page = 'subscriptions';
$page_title   = 'Subscription Management';
require_once 'includes/auth.php';

$msg = ''; $msg_type = 'success';

if ($_SERVER['REQUEST_METHOD']==='POST' && $is_logged_in) {
    $act = $_POST['action'] ?? '';

    // Admin manually updates a plan for a building
    if ($act === 'update_subscription') {
        $sid        = (int)($_POST['sub_owner_id'] ?? 0); // Actually building_id
        $plan_id    = (int)($_POST['sub_plan_id']  ?? 1);
        $status     = in_array($_POST['sub_status']??'',['active','trial','expired','suspended']) ? $_POST['sub_status'] : 'active';
        $expires    = mysqli_real_escape_string($conn, $_POST['sub_expires'] ?? date('Y-m-d', strtotime('+30 days')));
        if ($sid > 0) {
            mysqli_query($conn, "INSERT INTO subscriptions (subscriber_type,subscriber_id,plan_id,status,expires_at,assigned_by_admin)
                VALUES ('building',$sid,$plan_id,'$status','$expires 23:59:59',1)
                ON DUPLICATE KEY UPDATE plan_id=$plan_id,status='$status',expires_at='$expires 23:59:59',assigned_by_admin=1,payment_verified_at=NOW()");
            $msg = 'Subscription updated successfully.';
        }
    }

    // Admin verifies a payment key
    if ($act === 'verify_payment_key') {
        $key = trim(mysqli_real_escape_string($conn, $_POST['payment_key'] ?? ''));
        if (!empty($key)) {
            $sub = mysqli_fetch_assoc(mysqli_query($conn,
                "SELECT * FROM subscriptions 
                 WHERE payment_key='$key' AND payment_verified_at IS NULL LIMIT 1"));
            if ($sub) {
                $dur = max(1, (int)($sub['duration_months'] ?? 1));
                mysqli_query($conn,
                    "UPDATE subscriptions SET status='active', payment_verified_at=NOW(),
                     expires_at=DATE_ADD(NOW(), INTERVAL $dur MONTH)
                     WHERE payment_key='$key'");
                
                if ($sub['subscriber_type'] === 'provider') {
                    $prov_id = $sub['subscriber_id'];
                    mysqli_query($conn, "UPDATE service_providers SET is_subscribed=1 WHERE id=$prov_id");
                    $msg = "Key verified! Provider Subscription is now Active.";
                } else {
                    $msg = "Key verified! Building Subscription is now Active.";
                }
            } else {
                $msg = "No pending subscription found for that key, or it was already verified.";
                $msg_type = 'error';
            }
        }
    }

    // Admin edits a subscription plan's features/pricing
    if ($act === 'edit_plan') {
        $pid = (int)($_POST['plan_id']??0);
        $price = (float)($_POST['price_monthly']??0);
        $max_r = (int)($_POST['max_residents']??0);
        $max_c = (int)($_POST['max_cameras']??0);
        $hc = isset($_POST['has_cctv'])?1:0;
        $ha = isset($_POST['has_analytics'])?1:0;
        $hb = isset($_POST['has_ai_chatbot'])?1:0;
        if($pid>0){
            mysqli_query($conn,"UPDATE subscription_plans SET price_monthly=$price, max_residents=$max_r, max_cameras=$max_c, has_cctv=$hc, has_analytics=$ha, has_ai_chatbot=$hb WHERE id=$pid");
            $msg = "Plan updated successfully.";
        }
    }

    if ($act === 'edit_provider_plan') {
        $pid = (int)($_POST['plan_id']??0);
        $price = (float)($_POST['price']??0);
        $save = (float)($_POST['save_amount']??0);
        if($pid>0){
            mysqli_query($conn,"UPDATE provider_subscription_plans SET price=$price, save_amount=$save WHERE id=$pid");
            $msg = "Provider Plan updated successfully.";
        }
    }

    // Admin creates a coupon
    if ($act === 'create_coupon') {
        $code = mysqli_real_escape_string($conn, trim($_POST['code']??''));
        $discount = (int)($_POST['discount_percent']??0);
        $valid_until = mysqli_real_escape_string($conn, $_POST['valid_until']??'');
        $max_uses = (int)($_POST['max_uses']??0);
        $target_user = (int)($_POST['target_user_id']??0);
        
        $vu_sql = !empty($valid_until) ? "'$valid_until 23:59:59'" : "NULL";
        $mu_sql = $max_uses>0 ? $max_uses : "NULL";
        $tu_sql = $target_user>0 ? $target_user : "NULL";
        
        if(!empty($code) && $discount>0 && $discount<=100){
            // Check duplicate code
            $chk = mysqli_query($conn, "SELECT id FROM coupons WHERE code='$code'");
            if(mysqli_num_rows($chk)==0){
                mysqli_query($conn,"INSERT INTO coupons (code, discount_percent, valid_until, max_uses, target_user_id) VALUES ('$code', $discount, $vu_sql, $mu_sql, $tu_sql)");
                $msg = "Coupon generated successfully.";
            } else {
                $msg = "Coupon code already exists!";
                $msg_type = 'error';
            }
        }
    }

    // Admin toggles a coupon status
    if ($act === 'toggle_coupon') {
        $cid = (int)($_POST['coupon_id']??0);
        if($cid>0){
            mysqli_query($conn,"UPDATE coupons SET is_active = NOT is_active WHERE id=$cid");
            $msg = "Coupon status updated.";
        }
    }

    header('Location: subscriptions.php?msg='.urlencode($msg).'&type='.$msg_type); exit;
}

if (!empty($_GET['msg'])) { $msg = $_GET['msg']; $msg_type = $_GET['type'] ?? 'success'; }

// Fetch plans
$plans = [];
$r = mysqli_query($conn,"SELECT * FROM (SELECT * FROM subscription_plans WHERE is_active=1 ORDER BY id DESC) as temp GROUP BY plan_name ORDER BY price_monthly ASC");
if($r) while($row=mysqli_fetch_assoc($r)) $plans[]=$row;

// Fetch provider plans
$prov_plans = [];
$r3 = mysqli_query($conn,"SELECT * FROM provider_subscription_plans ORDER BY duration_months ASC");
if($r3) while($row=mysqli_fetch_assoc($r3)) $prov_plans[]=$row;

// Fetch all building subscriptions
$subs=[];
$r=mysqli_query($conn,"SELECT b.id, b.building_name as building_name,
    u.email, up.full_name,
    COALESCE(os.status,'none') as sub_status,
    COALESCE(sp.plan_name,'No Plan') as plan_name,
    COALESCE(sp.price_monthly,0) as price_monthly,
    os.expires_at, os.plan_id, os.payment_key, os.tran_id, os.payment_verified_at, os.id as sub_id
    FROM buildings b
    LEFT JOIN building_managers bm ON b.id=bm.building_id AND bm.role='admin'
    LEFT JOIN users u ON bm.user_id=u.id
    LEFT JOIN user_profiles up ON u.id=up.user_id
    LEFT JOIN subscriptions os ON b.id=os.subscriber_id AND os.subscriber_type='building'
    LEFT JOIN subscription_plans sp ON os.plan_id=sp.id
    GROUP BY b.id
    ORDER BY (os.payment_key IS NOT NULL AND os.payment_verified_at IS NULL) DESC, b.created_at DESC");
if($r) while($row=mysqli_fetch_assoc($r)) $subs[]=$row;

$pending_keys=array_filter($subs, fn($s) => !empty($s['payment_key']) && empty($s['payment_verified_at']));

// Fetch provider pending subscriptions
$pending_provider_keys = [];
$r2 = mysqli_query($conn, "SELECT ps.*, sp.user_id, up.full_name, u.email 
    FROM subscriptions ps
    JOIN service_providers sp ON ps.subscriber_id=sp.id
    LEFT JOIN users u ON sp.user_id=u.id
    LEFT JOIN user_profiles up ON u.id=up.user_id
    WHERE ps.subscriber_type='provider' AND ps.payment_key IS NOT NULL AND ps.payment_verified_at IS NULL AND ps.status='pending'
    ORDER BY ps.created_at DESC");
if($r2) while($row=mysqli_fetch_assoc($r2)) $pending_provider_keys[]=$row;

// Fetch coupons
$coupons=[];
$r = mysqli_query($conn,"SELECT c.*, u.email as target_email, up.full_name as target_name 
    FROM coupons c 
    LEFT JOIN users u ON c.target_user_id=u.id 
    LEFT JOIN user_profiles up ON u.id=up.user_id 
    ORDER BY c.created_at DESC");
if($r) while($row=mysqli_fetch_assoc($r)) $coupons[]=$row;

// Fetch owners for coupon dropdown
$owners=[];
$r = mysqli_query($conn,"SELECT u.id, u.email, up.full_name FROM users u JOIN user_profiles up ON u.id=up.user_id WHERE u.role_id=1");
if($r) while($row=mysqli_fetch_assoc($r)) $owners[]=$row;

include 'includes/layout_header.php';
?>

<div class="mb-8">
    <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900 tracking-tight">Subscription Hub</h1>
    <p class="text-slate-500 mt-2 text-sm md:text-base">Manage plans, verify payments, and generate discount coupons.</p>
</div>

<?php if($msg): ?>
<div class="mb-6 flex items-center gap-3 p-4 rounded-xl border <?=$msg_type==='error'?'bg-red-50 border-red-200 text-red-700':'bg-emerald-50 border-emerald-200 text-emerald-700'?> text-sm font-medium shadow-sm">
    <i data-lucide="<?=$msg_type==='error'?'alert-circle':'check-circle'?>" class="w-5 h-5 shrink-0"></i>
    <?=htmlspecialchars($msg)?>
</div>
<?php endif; ?>

<!-- Tabs -->
<div class="flex space-x-2 mb-6 p-1 bg-slate-100 rounded-xl inline-flex overflow-x-auto max-w-full">
    <button onclick="switchSubTab('buildings')" id="tab-buildings" class="px-5 py-2.5 text-sm font-bold rounded-lg transition-all bg-white text-emerald-600 shadow-sm border border-slate-200/60 whitespace-nowrap">
        Building Subscriptions
    </button>
    <button onclick="switchSubTab('plans')" id="tab-plans" class="px-5 py-2.5 text-sm font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700 whitespace-nowrap">
        Manage Plans
    </button>
    <button onclick="switchSubTab('coupons')" id="tab-coupons" class="px-5 py-2.5 text-sm font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700 whitespace-nowrap">
        Coupons & Discounts
    </button>
</div>

<!-- ======================= -->
<!-- TAB: BUILDING SUBS      -->
<!-- ======================= -->
<div id="content-buildings" class="block">
    
    <!-- Pending Payment Keys -->
    <?php if(!empty($pending_keys)): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-6 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="p-2 bg-amber-100 rounded-lg"><i data-lucide="key" class="w-5 h-5 text-amber-700"></i></div>
            <h2 class="font-bold text-amber-800">Pending Payment Keys (<?=count($pending_keys)?>)</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach($pending_keys as $pk): ?>
        <div class="bg-white rounded-xl border border-amber-200 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm hover:shadow-md transition-shadow">
            <div>
                <p class="font-bold text-slate-900">Building <?=htmlspecialchars($pk['building_name'])?></p>
                <p class="text-xs text-slate-500 mt-1">Managed by: <?=htmlspecialchars($pk['full_name']??$pk['email']??'Unknown')?></p>
                <div class="mt-2 flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded">Plan: <?=htmlspecialchars($pk['plan_name'])?></span>
                    <span class="text-xs font-mono text-amber-700 bg-amber-100 px-2 py-1 rounded">Key: <?=htmlspecialchars($pk['payment_key'])?></span>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="verify_payment_key">
                <input type="hidden" name="payment_key" value="<?=htmlspecialchars($pk['payment_key'])?>">
                <button class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 text-white text-sm font-bold rounded-xl hover:bg-emerald-700 transition shadow-sm shadow-emerald-500/30 flex items-center justify-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i> Verify
                </button>
            </form>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pending Provider Payment Keys -->
    <?php if(!empty($pending_provider_keys)): ?>
    <div class="bg-indigo-50 border border-indigo-200 rounded-2xl p-5 mb-6 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="p-2 bg-indigo-100 rounded-lg"><i data-lucide="key" class="w-5 h-5 text-indigo-700"></i></div>
            <h2 class="font-bold text-indigo-800">Pending Provider Keys (<?=count($pending_provider_keys)?>)</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach($pending_provider_keys as $ppk): ?>
        <div class="bg-white rounded-xl border border-indigo-200 p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-sm hover:shadow-md transition-shadow">
            <div>
                <p class="font-bold text-slate-900">Provider: <?=htmlspecialchars($ppk['full_name']??$ppk['email']??'Unknown')?></p>
                <div class="mt-2 flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded">Duration: <?=$ppk['duration_months']?> Months</span>
                    <span class="text-xs font-mono text-indigo-700 bg-indigo-100 px-2 py-1 rounded">Key: <?=htmlspecialchars($ppk['payment_key'])?></span>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="verify_payment_key">
                <input type="hidden" name="payment_key" value="<?=htmlspecialchars($ppk['payment_key'])?>">
                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm transition shadow-sm whitespace-nowrap">Verify Now</button>
            </form>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Manual Key Verification -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8 flex flex-col md:flex-row gap-6 items-center justify-between">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-blue-50 flex items-center justify-center border border-blue-100 shrink-0">
                <i data-lucide="search" class="w-6 h-6 text-blue-500"></i>
            </div>
            <div>
                <h2 class="font-bold text-slate-800 text-lg">Verify Payment Key Manually</h2>
                <p class="text-sm text-slate-500">Paste an SSLCommerz payment key submitted by an owner.</p>
            </div>
        </div>
        <form method="POST" class="flex w-full md:w-auto gap-3">
            <input type="hidden" name="action" value="verify_payment_key">
            <input type="text" name="payment_key" required placeholder="Enter key here..." class="flex-1 w-full md:w-64 bg-slate-50 border border-slate-200 text-sm rounded-xl px-4 py-2.5 focus:ring-2 focus:ring-emerald-500 outline-none font-mono font-medium">
            <button class="px-6 py-2.5 bg-slate-900 text-white text-sm font-bold rounded-xl hover:bg-slate-800 transition flex items-center justify-center gap-2 shadow-sm">
                <i data-lucide="check-circle" class="w-4 h-4"></i> Activate
            </button>
        </form>
    </div>

    <!-- All Subscriptions Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Building Subscriptions</h2>
                <p class="text-sm text-slate-500 mt-0.5">View and manually adjust subscription details.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-wider text-slate-500 font-semibold">
                    <tr>
                        <th class="px-6 py-4">Building</th>
                        <th class="px-6 py-4">Plan & Price</th>
                        <th class="px-6 py-4">Status & Expire</th>
                        <th class="px-6 py-4">Payment Key</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php foreach($subs as $s):
                    $badge = match($s['sub_status']) {
                        'active'    => 'bg-emerald-100 text-emerald-700',
                        'trial'     => 'bg-blue-100 text-blue-700',
                        'expired'   => 'bg-red-100 text-red-700',
                        'suspended' => 'bg-orange-100 text-orange-700',
                        default     => 'bg-slate-100 text-slate-500',
                    };
                    $exp_txt   = !empty($s['expires_at']) ? date('M d, Y', strtotime($s['expires_at'])) : '—';
                    $exp_soon  = !empty($s['expires_at']) && strtotime($s['expires_at']) < strtotime('+7 days') && strtotime($s['expires_at']) > time();
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="px-6 py-5">
                        <p class="font-bold text-slate-900 text-sm">Building <?=htmlspecialchars($s['building_name'])?></p>
                        <p class="text-xs text-slate-500 mt-1"><?=htmlspecialchars($s['full_name']??$s['email']??'No Manager')?></p>
                    </td>
                    <td class="px-6 py-5">
                        <span class="font-bold text-slate-800 text-sm"><?=htmlspecialchars($s['plan_name'])?></span>
                        <?php if($s['price_monthly']>0): ?>
                        <p class="text-xs font-semibold text-emerald-600 mt-1">৳<?=number_format($s['price_monthly'],0)?>/mo</p>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-5">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-[10px] uppercase tracking-wider font-black <?=$badge?>"><?=ucfirst($s['sub_status'])?></span>
                            <?php if($exp_soon): ?><span class="text-xs text-amber-500 font-black">⚠ SOON</span><?php endif; ?>
                        </div>
                        <p class="text-xs <?=$exp_soon?'text-amber-600 font-semibold':'text-slate-500'?>"><?=$exp_txt?></p>
                    </td>
                    <td class="px-6 py-5">
                        <?php if(!empty($s['payment_key']) && empty($s['payment_verified_at'])): ?>
                        <span class="text-xs font-mono font-bold bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-1 rounded-md shadow-sm">Pending</span>
                        <?php elseif(!empty($s['payment_key']) && !empty($s['payment_verified_at'])): ?>
                        <span class="text-xs font-mono font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-md shadow-sm">Verified</span>
                        <?php else: ?>
                        <span class="text-xs text-slate-400 font-medium">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-5 text-right">
                        <button onclick='openEditModal(<?=json_encode($s)?>)' class="px-3 py-2 bg-white text-slate-600 hover:text-emerald-600 hover:bg-emerald-50 border border-slate-200 hover:border-emerald-200 rounded-xl text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5">
                            <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> Edit
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======================= -->
<!-- TAB: MANAGE PLANS       -->
<!-- ======================= -->
<div id="content-plans" class="hidden">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($plans as $p): ?>
        <form method="POST" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 relative group overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-50 to-white pointer-events-none -z-10"></div>
            
            <input type="hidden" name="action" value="edit_plan">
            <input type="hidden" name="plan_id" value="<?=$p['id']?>">
            
            <div class="mb-6 flex justify-between items-start">
                <h3 class="text-lg font-black uppercase tracking-wider text-slate-800"><?=htmlspecialchars($p['plan_name'])?></h3>
                <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded text-xs font-bold">ID: <?=$p['id']?></span>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Monthly Price (৳)</label>
                    <input type="number" step="0.01" name="price_monthly" value="<?=$p['price_monthly']?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Max Residents</label>
                        <input type="number" name="max_residents" value="<?=$p['max_residents']?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Max Cameras</label>
                        <input type="number" name="max_cameras" value="<?=$p['max_cameras']?>" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none">
                    </div>
                </div>
                
                <div class="pt-2 space-y-2 border-t border-slate-100">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="has_cctv" <?=$p['has_cctv']?'checked':''?> class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-slate-700">CCTV Module Access</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="has_analytics" <?=$p['has_analytics']?'checked':''?> class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-slate-700">Advanced Analytics</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="has_ai_chatbot" <?=$p['has_ai_chatbot']?'checked':''?> class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500">
                        <span class="text-sm font-medium text-slate-700">AI Chatbot Access</span>
                    </label>
                </div>
            </div>

            <button type="submit" class="mt-6 w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Save Changes
            </button>
        </form>
        <?php endforeach; ?>
    </div>

    <h2 class="text-xl font-bold mt-10 mb-6 text-slate-800 border-b pb-2">Provider Subscription Plans</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach($prov_plans as $pp): ?>
        <form method="POST" class="bg-indigo-50/50 rounded-2xl border border-indigo-100 shadow-sm p-6 relative group overflow-hidden">
            <input type="hidden" name="action" value="edit_provider_plan">
            <input type="hidden" name="plan_id" value="<?=$pp['id']?>">
            
            <div class="mb-6 flex justify-between items-start">
                <h3 class="text-lg font-black uppercase tracking-wider text-indigo-900"><?=htmlspecialchars($pp['plan_name'])?></h3>
                <span class="bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded text-xs font-bold">Duration: <?=$pp['duration_months']?> M</span>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-indigo-500 uppercase tracking-wide mb-1">Total Price (৳)</label>
                    <input type="number" step="0.01" name="price" value="<?=$pp['price']?>" class="w-full bg-white border border-indigo-200 rounded-lg px-3 py-2 text-sm font-bold text-indigo-900 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-indigo-500 uppercase tracking-wide mb-1">Save Amount (৳)</label>
                    <input type="number" step="0.01" name="save_amount" value="<?=$pp['save_amount']?>" class="w-full bg-white border border-indigo-200 rounded-lg px-3 py-2 text-sm font-bold text-indigo-900 focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>

            <button type="submit" class="mt-6 w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition shadow-sm flex items-center justify-center gap-2">
                <i data-lucide="save" class="w-4 h-4"></i> Save Changes
            </button>
        </form>
        <?php endforeach; ?>
    </div>
</div>

<!-- ======================= -->
<!-- TAB: COUPONS            -->
<!-- ======================= -->
<div id="content-coupons" class="hidden">
    
    <!-- Generate Coupon Form -->
    <div class="bg-gradient-to-r from-emerald-600 to-teal-600 rounded-2xl shadow-lg p-1 mb-8">
        <div class="bg-white rounded-[14px] p-6">
            <div class="flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center">
                    <i data-lucide="ticket" class="w-5 h-5 text-emerald-600"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-800">Generate New Coupon</h2>
                    <p class="text-sm text-slate-500">Create one-time or time-limited discount codes.</p>
                </div>
            </div>
            
            <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <input type="hidden" name="action" value="create_coupon">
                
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Coupon Code *</label>
                    <input type="text" name="code" required placeholder="e.g. SUMMER50" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold uppercase focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Discount % *</label>
                    <input type="number" name="discount_percent" required min="1" max="100" placeholder="e.g. 20" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-bold focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Valid Until</label>
                    <input type="date" name="valid_until" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium focus:ring-2 focus:ring-emerald-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-1">Specific Owner (Optional)</label>
                    <select name="target_user_id" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-sm font-medium focus:ring-2 focus:ring-emerald-500 outline-none">
                        <option value="">-- Anyone --</option>
                        <?php foreach($owners as $o): ?>
                            <option value="<?=$o['id']?>"><?=htmlspecialchars($o['full_name'])?> (<?=$o['email']?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition shadow-md shadow-emerald-500/20 flex items-center justify-center gap-2 border border-emerald-600">
                        <i data-lucide="plus" class="w-4 h-4"></i> Create
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Coupons Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
                <h2 class="text-lg font-bold text-slate-800">Active & Past Coupons</h2>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-slate-50 border-b border-slate-100 text-xs uppercase tracking-wider text-slate-500 font-semibold">
                    <tr>
                        <th class="px-6 py-4">Code & Discount</th>
                        <th class="px-6 py-4">Validity</th>
                        <th class="px-6 py-4">Target / Usage</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if(empty($coupons)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-slate-500 font-medium">No coupons generated yet.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach($coupons as $c): 
                    $is_expired = !empty($c['valid_until']) && strtotime($c['valid_until']) < time();
                    $is_active = $c['is_active'] && !$is_expired;
                ?>
                <tr class="hover:bg-slate-50/80 transition-colors">
                    <td class="px-6 py-5">
                        <span class="text-sm font-black tracking-widest text-slate-800 bg-slate-100 border border-slate-200 px-2 py-1 rounded-md uppercase"><?=htmlspecialchars($c['code'])?></span>
                        <span class="ml-2 text-sm font-bold text-emerald-600">-<?=$c['discount_percent']?>%</span>
                    </td>
                    <td class="px-6 py-5 text-sm text-slate-600">
                        <?php if(!empty($c['valid_until'])): ?>
                            <span class="<?= $is_expired ? 'text-red-500' : 'text-slate-600' ?>">
                                <?=date('M d, Y', strtotime($c['valid_until']))?>
                            </span>
                        <?php else: ?>
                            Never expires
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-5 text-sm">
                        <?php if($c['target_user_id']): ?>
                            <p class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded inline-block mb-1">Specific User</p>
                            <p class="text-xs text-slate-500"><?=htmlspecialchars($c['target_email'])?></p>
                        <?php else: ?>
                            <p class="text-xs font-bold text-slate-500">Anyone</p>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-5">
                        <?php if($is_active): ?>
                            <span class="bg-emerald-100 text-emerald-700 px-2 py-1 rounded text-xs font-bold flex items-center gap-1 w-max"><i data-lucide="check-circle" class="w-3 h-3"></i> Active</span>
                        <?php elseif($is_expired): ?>
                            <span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold flex items-center gap-1 w-max"><i data-lucide="clock" class="w-3 h-3"></i> Expired</span>
                        <?php else: ?>
                            <span class="bg-slate-100 text-slate-500 px-2 py-1 rounded text-xs font-bold flex items-center gap-1 w-max"><i data-lucide="x-circle" class="w-3 h-3"></i> Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-5 text-right">
                        <form method="POST">
                            <input type="hidden" name="action" value="toggle_coupon">
                            <input type="hidden" name="coupon_id" value="<?=$c['id']?>">
                            <button class="px-3 py-1.5 <?= $c['is_active'] ? 'bg-red-50 text-red-600 hover:bg-red-100 border-red-200' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 border-emerald-200' ?> border rounded-lg text-xs font-bold transition shadow-sm">
                                <?= $c['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Subscription Modal (Building Subscriptions) -->
<div id="editModal" class="fixed inset-0 z-[100] hidden">
    <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeEditModal()"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl p-6 md:p-8 transform transition-all border border-slate-100">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-slate-900">Edit Building Subscription</h3>
                    <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-slate-500 bg-slate-50 hover:bg-slate-100 p-2 rounded-full transition-colors">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                
                <form method="POST" class="space-y-5">
                    <input type="hidden" name="action" value="update_subscription">
                    <input type="hidden" name="sub_owner_id" id="edit_owner_id">
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Building</label>
                        <input type="text" id="edit_building_name" readonly class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm font-bold text-slate-600 outline-none cursor-not-allowed">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Assigned Plan</label>
                        <select name="sub_plan_id" id="edit_plan_id" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none">
                            <?php foreach($plans as $p): ?>
                                <option value="<?=$p['id']?>"><?=htmlspecialchars($p['plan_name'])?> (৳<?=number_format($p['price_monthly'],0)?>/mo)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Status</label>
                            <select name="sub_status" id="edit_status" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none">
                                <option value="trial">Trial</option>
                                <option value="active">Active</option>
                                <option value="expired">Expired</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Expires At</label>
                            <input type="date" name="sub_expires" id="edit_expires" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-sm font-semibold text-slate-900 focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition shadow-lg shadow-emerald-500/30">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function switchSubTab(tab) {
    document.getElementById('content-buildings').classList.add('hidden');
    document.getElementById('content-plans').classList.add('hidden');
    document.getElementById('content-coupons').classList.add('hidden');
    
    document.getElementById('tab-buildings').className = 'px-5 py-2.5 text-sm font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700 whitespace-nowrap';
    document.getElementById('tab-plans').className = 'px-5 py-2.5 text-sm font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700 whitespace-nowrap';
    document.getElementById('tab-coupons').className = 'px-5 py-2.5 text-sm font-bold rounded-lg transition-all text-slate-500 hover:text-slate-700 whitespace-nowrap';
    
    document.getElementById('content-' + tab).classList.remove('hidden');
    document.getElementById('tab-' + tab).className = 'px-5 py-2.5 text-sm font-bold rounded-lg transition-all bg-white text-emerald-600 shadow-sm border border-slate-200/60 whitespace-nowrap';
}

function openEditModal(sub) {
    document.getElementById('edit_owner_id').value = sub.id; // b.id (Building ID)
    document.getElementById('edit_building_name').value = 'Bldg ' + sub.building_name + (sub.full_name ? ' (' + sub.full_name + ')' : '');
    
    // Set plan
    const pSelect = document.getElementById('edit_plan_id');
    for(let i=0; i<pSelect.options.length; i++) {
        if(pSelect.options[i].value == sub.plan_id) {
            pSelect.selectedIndex = i; break;
        }
    }
    
    // Set status
    const sSelect = document.getElementById('edit_status');
    for(let i=0; i<sSelect.options.length; i++) {
        if(sSelect.options[i].value === (sub.sub_status === 'none' ? 'active' : sub.sub_status)) {
            sSelect.selectedIndex = i; break;
        }
    }
    
    // Set expiry (convert datetime to date)
    if(sub.expires_at) {
        document.getElementById('edit_expires').value = sub.expires_at.split(' ')[0];
    } else {
        const d = new Date();
        d.setDate(d.getDate() + 30);
        document.getElementById('edit_expires').value = d.toISOString().split('T')[0];
    }

    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}
</script>

<?php include 'includes/layout_footer.php'; ?>
