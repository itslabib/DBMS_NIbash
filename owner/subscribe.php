<?php
session_start();
require_once '../includes/db_config.php';

if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$b_id = (int)$_SESSION['building_id'];

// Fetch current subscription
$sub = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT os.*,sp.plan_name,sp.price_monthly,sp.max_residents,sp.max_cameras,sp.has_cctv,sp.has_analytics,sp.has_ai_chatbot
     FROM subscriptions os
     JOIN subscription_plans sp ON os.plan_id=sp.id
     WHERE os.subscriber_type='building' AND os.subscriber_id=$b_id LIMIT 1"));

// Fetch all plans
$plans=[];
$r=mysqli_query($conn,"SELECT * FROM (SELECT * FROM subscription_plans WHERE is_active=1 ORDER BY id DESC) as temp GROUP BY plan_name ORDER BY price_monthly ASC");
if($r) while($row=mysqli_fetch_assoc($r)) $plans[]=$row;

$msg=''; $msg_type='success';

// Handle: owner submits payment key after SSLCommerz payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_id'])){
    $pid = (int)$_POST['plan_id'];
    $duration = (int)($_POST['duration'] ?? 1);
    $tran_id = trim($_POST['tran_id']);
    $pay_key = trim($_POST['payment_key']);
    
    if (empty($tran_id) || empty($pay_key)) {
        $msg = 'Please fill in both the Transaction ID and the Sender Number.';
        $msg_type = 'error';
    } else {
        // Deactivate old pending rows for this building
        mysqli_query($conn, "UPDATE subscriptions SET status='expired' WHERE subscriber_type='building' AND subscriber_id='$b_id' AND status='pending'");

        $tran_esc = mysqli_real_escape_string($conn, $tran_id);
        $key_esc  = mysqli_real_escape_string($conn, $pay_key);

        mysqli_query($conn,
            "INSERT INTO subscriptions (subscriber_type, subscriber_id, plan_id, duration_months, status, tran_id, payment_key, expires_at)
             VALUES ('building', $b_id, $pid, $duration, 'pending', '$tran_esc', '$key_esc', DATE_ADD(NOW(), INTERVAL $duration MONTH))
             ON DUPLICATE KEY UPDATE
                plan_id=$pid,
                duration_months=$duration,
                tran_id='$tran_esc',
                payment_key='$key_esc',
                payment_verified_at=NULL,
                status='pending'");

        $msg = 'Your payment details have been submitted. Your account will be activated within 24 hours after admin verification.';
        $msg_type = 'success';
    }
}

// Re-fetch sub after potential update
$sub_q = mysqli_query($conn, "SELECT bs.*, sp.plan_name FROM subscriptions bs LEFT JOIN subscription_plans sp ON bs.plan_id=sp.id WHERE bs.subscriber_type='building' AND bs.subscriber_id='$b_id' LIMIT 1");
$sub = mysqli_fetch_assoc($sub_q);
$is_active = $sub && in_array($sub['status'], ['active','trial']) && strtotime($sub['expires_at']) > time();
$is_pending_verify = $sub && $sub['status'] === 'pending' && !empty($sub['payment_key']) && empty($sub['payment_verified_at']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style> body{font-family:'Inter',sans-serif;} </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased overflow-x-hidden" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php 
    $active_page = 'dashboard';
    include '../includes/owner_sidebar.php'; 
    ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8" id="main-content">
        <div class="max-w-4xl mx-auto w-full pt-6">

    <!-- Back -->
    <a href="dashboard.php" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-800 mb-8 transition">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
    </a>

    <div class="mb-8">
        <h1 class="text-3xl font-black text-slate-900">Subscription Plans</h1>
        <p class="text-slate-500 mt-1">Choose a plan that suits your building. Pay via SSLCommerz and submit your transaction key below.</p>
    </div>

    <!-- Current Status Banner -->
    <?php if($sub): ?>
    <div class="mb-8 p-5 rounded-2xl border <?=$is_active?'bg-emerald-50 border-emerald-200':'bg-amber-50 border-amber-200'?> flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl <?=$is_active?'bg-emerald-100 text-emerald-600':'bg-amber-100 text-amber-600'?> flex items-center justify-center">
            <i data-lucide="<?=$is_active?'shield-check':'alert-circle'?>" class="w-5 h-5"></i>
        </div>
        <div>
            <p class="font-bold <?=$is_active?'text-emerald-800':'text-amber-800'?>">
                Current: <?=htmlspecialchars($sub['plan_name'])?> — <?=ucfirst($sub['status'])?>
            </p>
            <p class="text-sm <?=$is_active?'text-emerald-600':'text-amber-600'?>">
                <?=$is_pending_verify
                    ? 'Your payment key is submitted and awaiting admin verification.'
                    : 'Expires: '.(!empty($sub['expires_at'])?date('M d, Y',strtotime($sub['expires_at'])):'N/A')
                ?>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <?php if($msg): ?>
    <div class="mb-6 p-4 rounded-xl border flex items-start gap-3 <?=$msg_type==='error'?'bg-red-50 border-red-200 text-red-700':'bg-emerald-50 border-emerald-200 text-emerald-700'?> text-sm font-medium">
        <i data-lucide="<?=$msg_type==='error'?'alert-circle':'check-circle'?>" class="w-5 h-5 shrink-0 mt-0.5"></i>
        <?=htmlspecialchars($msg)?>
    </div>
    <?php endif; ?>

    <!-- Plan Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-10">
    <?php foreach($plans as $p):
        $is_current = $sub && $sub['plan_id'] == $p['id'];
        $is_pop = $p['plan_name']==='Pro';
    ?>
    <label for="plan_<?=$p['id']?>" class="cursor-pointer">
        <input type="radio" name="selected_plan" id="plan_<?=$p['id']?>" value="<?=$p['id']?>" class="sr-only peer" <?=$is_current?'checked':''?>>
        <div class="h-full bg-white rounded-2xl border-2 <?=$is_pop?'border-emerald-400':'border-slate-200'?> peer-checked:border-emerald-500 peer-checked:ring-2 peer-checked:ring-emerald-300 p-5 shadow-sm hover:shadow-md transition-all relative">
            <?php if($is_pop): ?>
            <span class="absolute -top-3 right-4 bg-emerald-600 text-white text-[10px] font-black uppercase px-2 py-0.5 rounded-full tracking-widest">Popular</span>
            <?php endif; ?>
            <?php if($is_current): ?>
            <span class="absolute -top-3 left-4 bg-slate-700 text-white text-[10px] font-black uppercase px-2 py-0.5 rounded-full">Current</span>
            <?php endif; ?>
            <p class="text-xs font-black uppercase tracking-widest text-emerald-600 mb-1"><?=htmlspecialchars($p['plan_name'])?></p>
            <p class="text-2xl font-black text-slate-900 mb-3">৳<?=number_format($p['price_monthly'],0)?><span class="text-xs font-normal text-slate-400">/mo</span></p>
            <ul class="space-y-1.5 text-xs text-slate-600">
                <li class="flex items-center gap-1.5"><i data-lucide="users" class="w-3.5 h-3.5 text-slate-400"></i> <?=$p['max_residents']?> residents</li>
                <li class="flex items-center gap-1.5"><i data-lucide="video" class="w-3.5 h-3.5 text-slate-400"></i> <?=$p['max_cameras']?> cameras</li>
                <li class="flex items-center gap-1.5 <?=$p['has_cctv']?'text-emerald-700':'text-slate-300 line-through'?>"><i data-lucide="<?=$p['has_cctv']?'check':'x'?>" class="w-3.5 h-3.5"></i> CCTV Module</li>
                <li class="flex items-center gap-1.5 <?=$p['has_analytics']?'text-emerald-700':'text-slate-300 line-through'?>"><i data-lucide="<?=$p['has_analytics']?'check':'x'?>" class="w-3.5 h-3.5"></i> Analytics</li>
                <li class="flex items-center gap-1.5 <?=$p['has_ai_chatbot']?'text-emerald-700':'text-slate-300 line-through'?>"><i data-lucide="<?=$p['has_ai_chatbot']?'check':'x'?>" class="w-3.5 h-3.5"></i> AI Chatbot</li>
            </ul>
        </div>
    </label>
    <?php endforeach; ?>
    </div>

    <!-- Duration Selector -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-8">
        <h2 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
            <i data-lucide="calendar" class="w-5 h-5 text-indigo-600"></i>
            Select Subscription Duration
        </h2>
        <div class="flex flex-col sm:flex-row gap-4 sm:items-center justify-between">
            <div class="flex gap-2">
                <button type="button" onclick="setDuration(1)" id="btn_dur_1" class="px-5 py-2.5 border-2 border-indigo-500 bg-indigo-50 text-indigo-700 rounded-xl text-sm font-bold duration-btn transition-colors">1 Month</button>
                <button type="button" onclick="setDuration(6)" id="btn_dur_6" class="px-5 py-2.5 border-2 border-slate-200 bg-white text-slate-600 hover:bg-slate-50 rounded-xl text-sm font-bold duration-btn transition-colors">6 Months (Save 10%)</button>
                <button type="button" onclick="setDuration(12)" id="btn_dur_12" class="px-5 py-2.5 border-2 border-slate-200 bg-white text-slate-600 hover:bg-slate-50 rounded-xl text-sm font-bold duration-btn transition-colors">1 Year (Save 20%)</button>
            </div>
            <p id="duration_savings" class="text-sm font-black text-emerald-600 hidden bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200"></p>
        </div>
    </div>

    <!-- How to Pay -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 mb-6">
        <h2 class="font-bold text-slate-800 mb-4 flex items-center gap-2">
            <i data-lucide="credit-card" class="w-5 h-5 text-emerald-600"></i>
            How to Subscribe Manually
        </h2>
        <ol class="space-y-3 text-sm text-slate-600 list-none">
            <li class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">1</span> Send money via bKash, Nagad, or Rocket to <strong>01301085365</strong>.</li>
            <li class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">2</span> In the payment reference, include your <strong>Building ID (<?=$b_id?>)</strong>.</li>
            <li class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">3</span> Select your plan below, paste the Transaction ID, and provide the number you sent money from.</li>
            <li class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">4</span> Click <strong>Submit for Verification</strong>. Our admin team will verify and activate within 24 hours.</li>
        </ol>
    </div>

    <!-- Automated Payment Form -->
    <?php if(!$is_pending_verify): ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
        <!-- Automated SSLCommerz -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col">
            <h2 class="font-bold text-slate-800 mb-2 flex items-center gap-2">
                <i data-lucide="zap" class="w-5 h-5 text-amber-500"></i>
                Pay Automatically
            </h2>
            <p class="text-sm text-slate-500 mb-6">Fastest way. Pay securely via SSLCommerz and your subscription will activate instantly.</p>
            
            <div class="mb-4">
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Discount Coupon</label>
                <div class="flex gap-2">
                    <input type="text" id="coupon_code" name="coupon_code" class="w-full bg-slate-50 border border-slate-200 text-sm rounded-xl px-3 py-2.5 outline-none focus:ring-1 focus:ring-emerald-500 font-mono uppercase" placeholder="Enter code">
                    <button type="button" onclick="applyCoupon()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold rounded-xl text-sm transition shrink-0">Apply</button>
                </div>
                <p id="coupon_msg" class="text-xs font-medium mt-1.5 hidden"></p>
            </div>

            <div class="flex justify-between items-center mb-6 pt-4 border-t border-slate-100">
                <span class="text-slate-600 font-semibold">Total Payable</span>
                <span class="text-2xl font-black text-slate-900" id="display_amount">৳0</span>
            </div>

            <form action="../payment_integration/payment_init.php" method="POST" class="mt-auto">
                <input type="hidden" name="type" value="subscription">
                <input type="hidden" name="building_id" value="<?=$b_id?>">
                <input type="hidden" name="plan_id" id="auto_plan_id" value="1">
                <input type="hidden" name="duration" id="auto_duration" value="1">
                <input type="hidden" name="amount" id="auto_amount" value="0">
                <!-- also send coupon code to payment init just in case we want to record it -->
                <input type="hidden" name="coupon" id="auto_coupon" value="">
                <button type="submit" class="w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-sm transition flex items-center justify-center gap-2">
                    <i data-lucide="credit-card" class="w-4 h-4"></i> Pay via SSLCommerz
                </button>
            </form>
        </div>

        <!-- Manual Submission Form -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col">
            <h2 class="font-bold text-slate-800 mb-2 flex items-center gap-2">
                <i data-lucide="key" class="w-5 h-5 text-slate-400"></i>
                Submit Payment Manually
            </h2>
            <p class="text-sm text-slate-500 mb-4">If you paid via other methods or have a key.</p>
            <form method="POST" class="space-y-4">
            <input type="hidden" name="duration" id="manual_duration" value="1">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Selected Plan</label>
                <select name="plan_id" id="planSelect" class="w-full bg-slate-50 border border-slate-200 text-sm rounded-xl px-3 py-2.5 outline-none focus:ring-1 focus:ring-emerald-500">
                    <?php foreach($plans as $p): ?>
                    <option value="<?=$p['id']?>" <?=$p['price_monthly']>0?'':''?>><?=htmlspecialchars($p['plan_name'])?> — ৳<?=number_format($p['price_monthly'],0)?>/month</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Manual Transaction ID</label>
                <input type="text" name="tran_id" required placeholder="e.g. 7DF8XJ9K" class="w-full bg-slate-50 border border-slate-200 text-sm rounded-xl px-3 py-2.5 outline-none focus:ring-1 focus:ring-emerald-500 font-mono uppercase">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Sender Mobile Number</label>
                <input type="text" name="payment_key" required placeholder="e.g. 01700000000" class="w-full bg-slate-50 border border-slate-200 text-sm rounded-xl px-3 py-2.5 outline-none focus:ring-1 focus:ring-emerald-500 font-mono">
                <p class="text-xs text-slate-400 mt-1">The bKash/Nagad/Rocket number from which you sent the money.</p>
            </div>
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-sm transition flex items-center justify-center gap-2">
                <i data-lucide="send" class="w-4 h-4"></i> Submit for Verification
            </button>
        </form>
        </div>
    </div>
    <?php else: ?>
    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 text-center">
        <i data-lucide="clock" class="w-10 h-10 text-amber-500 mx-auto mb-3"></i>
        <h3 class="font-bold text-amber-800 text-lg mb-1">Verification Pending</h3>
        <p class="text-amber-700 text-sm">Your payment key has been submitted. Please wait for admin verification (usually within 24 hours).</p>
        <p class="text-xs text-amber-500 font-mono mt-2">Key submitted: <?=htmlspecialchars(substr($sub['payment_key'],0,6))?>•••</p>
    </div>
    <?php endif; ?>
</div>
</main>

<script>
    lucide.createIcons();
    
    // Store plans data for JS
    const plansData = <?=json_encode($plans)?>;
    const userId = <?=json_encode($_SESSION['user_id'])?>;
    let currentAmount = 0;
    let discountPercent = 0;
    let selectedDuration = 1;

    function recalculateAmount() {
        let baseTotal = currentAmount * selectedDuration;
        let durationDiscount = 0;
        
        if(selectedDuration === 6) {
            durationDiscount = baseTotal * 0.10; // 10% off
        } else if(selectedDuration === 12) {
            durationDiscount = baseTotal * 0.20; // 20% off
        }
        
        let savingsEl = document.getElementById('duration_savings');
        if(durationDiscount > 0) {
            savingsEl.innerText = `You save ৳${durationDiscount.toLocaleString(undefined, {maximumFractionDigits:0})}!`;
            savingsEl.classList.remove('hidden');
        } else {
            savingsEl.classList.add('hidden');
        }
        
        let afterDuration = baseTotal - durationDiscount;
        let finalAmt = afterDuration - (afterDuration * discountPercent / 100);
        finalAmt = Math.max(0, finalAmt);
        
        document.getElementById('auto_amount').value = finalAmt;
        document.getElementById('display_amount').innerText = '৳' + finalAmt.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
    }

    function setDuration(months) {
        selectedDuration = months;
        document.querySelectorAll('.duration-btn').forEach(btn => {
            btn.className = "px-5 py-2.5 border-2 border-slate-200 bg-white text-slate-600 hover:bg-slate-50 rounded-xl text-sm font-bold duration-btn transition-colors";
        });
        document.getElementById('btn_dur_' + months).className = "px-5 py-2.5 border-2 border-indigo-500 bg-indigo-50 text-indigo-700 rounded-xl text-sm font-bold duration-btn transition-colors";
        document.getElementById('auto_duration').value = months;
        document.getElementById('manual_duration').value = months;
        recalculateAmount();
    }

    // Sync radio plan cards with select dropdown and auto payment form
    function updateSelectedPlan(planId) {
        document.getElementById('planSelect').value = planId;
        document.getElementById('auto_plan_id').value = planId;
        
        for(let i=0; i<plansData.length; i++){
            if(plansData[i].id == planId) {
                currentAmount = parseFloat(plansData[i].price_monthly);
                break;
            }
        }
        recalculateAmount();
    }

    async function applyCoupon() {
        const code = document.getElementById('coupon_code').value.trim();
        const msgEl = document.getElementById('coupon_msg');
        if(!code) return;
        
        try {
            const res = await fetch('../api/validate_coupon.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({code, user_id: userId})
            });
            const data = await res.json();
            msgEl.classList.remove('hidden');
            if(data.status === 'success') {
                discountPercent = parseFloat(data.discount_percent);
                msgEl.className = 'text-xs font-medium mt-1.5 text-emerald-600';
                msgEl.innerText = `${data.message} (${discountPercent}% off)`;
                document.getElementById('auto_coupon').value = code;
                recalculateAmount();
            } else {
                discountPercent = 0;
                msgEl.className = 'text-xs font-medium mt-1.5 text-rose-600';
                msgEl.innerText = data.message;
                document.getElementById('auto_coupon').value = "";
                recalculateAmount();
            }
        } catch(e) {
            console.error(e);
        }
    }

    document.querySelectorAll('input[name="selected_plan"]').forEach(r => {
        r.addEventListener('change', () => updateSelectedPlan(r.value));
        // Initialize if checked
        if(r.checked) updateSelectedPlan(r.value);
    });
    
    // Initialize on load
    const checkedRadio = document.querySelector('input[name="selected_plan"]:checked');
    if(checkedRadio) {
        updateSelectedPlan(checkedRadio.value);
    } else if(plansData.length > 0) {
        updateSelectedPlan(plansData[0].id);
    }
</script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>
