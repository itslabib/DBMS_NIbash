<?php
session_start();
require_once '../includes/db_config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 4){
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$q = mysqli_query($conn, "SELECT * FROM service_providers WHERE user_id='$user_id' LIMIT 1");
$provider = mysqli_fetch_assoc($q);
if(!$provider){
    die("Provider profile not found.");
}
$provider_id = $provider['id'];

// Handle Manual Payment Submission
$msg = '';
$msg_type = '';

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan_duration'])){
    $duration = (int)$_POST['plan_duration'];
    $tran_id = trim($_POST['tran_id']);
    $pay_key = trim($_POST['payment_key']);
    
    // Deactivate old pending
    mysqli_query($conn, "UPDATE subscriptions SET status='expired' WHERE subscriber_type='provider' AND subscriber_id='$provider_id' AND status='pending'");
    
    // Insert new pending
    $stmt = mysqli_prepare($conn, "INSERT INTO subscriptions (subscriber_type, subscriber_id, duration_months, status, tran_id, payment_key) VALUES ('provider', ?, ?, 'pending', ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiss", $provider_id, $duration, $tran_id, $pay_key);
    
    if(mysqli_stmt_execute($stmt)){
        $msg = "Your payment details have been submitted. Your PRO status will be activated within 24 hours after admin verification.";
        $msg_type = "success";
    } else {
        $msg = "Failed to submit payment details.";
        $msg_type = "error";
    }
}

// Check current subscription
$sq = mysqli_query($conn, "SELECT * FROM subscriptions WHERE subscriber_type='provider' AND subscriber_id='$provider_id' ORDER BY id DESC LIMIT 1");
$sub = mysqli_fetch_assoc($sq);
$is_pending = $sub && $sub['status'] === 'pending';
$is_active = $sub && $sub['status'] === 'active' && strtotime($sub['expires_at']) > time();

$plans = [];
$rp = mysqli_query($conn, "SELECT * FROM provider_subscription_plans ORDER BY duration_months ASC");
if($rp) while($row = mysqli_fetch_assoc($rp)){
    $plans[] = [
        'id' => $row['id'],
        'duration_months' => $row['duration_months'],
        'name' => $row['plan_name'],
        'price' => $row['price'],
        'save' => $row['save_amount']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade to PRO | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style> 
        body{font-family:'Inter',sans-serif;} 
        h1,h2,h3,.outfit{font-family:'Outfit',sans-serif;}
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 min-h-screen overflow-x-hidden selection:bg-indigo-100 selection:text-indigo-900" x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php include '../includes/provider_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 min-h-screen flex flex-col pt-4 px-4 pb-12 lg:px-8 lg:pt-6 lg:pb-16 max-w-[1200px] mx-auto">
        
        <header class="flex items-center gap-4 mb-8">
            <a href="dashboard.php" class="w-10 h-10 flex items-center justify-center bg-white border border-slate-200 text-slate-500 rounded-xl hover:bg-slate-50 transition-colors shadow-sm shrink-0">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Upgrade to PRO</h1>
                <p class="text-slate-500 text-sm mt-1">Boost your profile visibility and get 3x more job requests.</p>
            </div>
        </header>

        <?php if($sub): ?>
        <div class="mb-8 p-5 rounded-2xl border <?=$is_active?'bg-emerald-50 border-emerald-200':'bg-amber-50 border-amber-200'?> flex items-center gap-4 shadow-sm">
            <div class="w-12 h-12 rounded-xl <?=$is_active?'bg-emerald-100 text-emerald-600':'bg-amber-100 text-amber-600'?> flex items-center justify-center shrink-0">
                <i data-lucide="<?=$is_active?'crown':'clock'?>" class="w-6 h-6"></i>
            </div>
            <div>
                <p class="font-bold text-lg <?=$is_active?'text-emerald-800':'text-amber-800'?>">
                    Status: <?=ucfirst($sub['status'])?>
                </p>
                <p class="text-sm <?=$is_active?'text-emerald-600':'text-amber-600'?>">
                    <?=$is_pending
                        ? 'Your payment key is submitted and awaiting admin verification.'
                        : 'Expires: '.(!empty($sub['expires_at'])?date('M d, Y',strtotime($sub['expires_at'])):'N/A')
                    ?>
                </p>
            </div>
        </div>
        <?php endif; ?>

        <?php if($msg): ?>
        <div class="mb-8 p-5 rounded-2xl border flex items-start gap-3 <?=$msg_type==='error'?'bg-red-50 border-red-200 text-red-700':'bg-emerald-50 border-emerald-200 text-emerald-700'?> text-sm font-medium shadow-sm">
            <i data-lucide="<?=$msg_type==='error'?'alert-circle':'check-circle'?>" class="w-5 h-5 shrink-0 mt-0.5"></i>
            <?=htmlspecialchars($msg)?>
        </div>
        <?php endif; ?>

        <!-- Plan Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
        <?php foreach($plans as $p):
            $is_pop = $p['duration_months'] === 6;
        ?>
        <label for="plan_<?=$p['id']?>" class="cursor-pointer">
            <input type="radio" name="selected_plan" id="plan_<?=$p['id']?>" value="<?=$p['id']?>" class="sr-only peer" <?=$is_pop?'checked':''?>>
            <div class="h-full bg-white rounded-[1.5rem] border-2 <?=$is_pop?'border-indigo-400':'border-slate-200'?> peer-checked:border-indigo-600 peer-checked:ring-4 peer-checked:ring-indigo-600/10 p-6 shadow-sm hover:shadow-md transition-all relative overflow-hidden group">
                
                <?php if($is_pop): ?>
                <div class="absolute top-0 right-0 w-24 h-24 bg-indigo-50 rounded-bl-full -mr-4 -mt-4 transition-transform group-hover:scale-110"></div>
                <span class="absolute top-4 right-4 bg-indigo-600 text-white text-[10px] font-black uppercase px-2 py-0.5 rounded-full tracking-widest z-10 shadow-sm">Best Value</span>
                <?php endif; ?>
                
                <div class="relative z-10">
                    <p class="text-xs font-black uppercase tracking-widest text-indigo-600 mb-2"><?=htmlspecialchars($p['name'])?></p>
                    <p class="text-4xl font-black text-slate-900 mb-2">৳<?=number_format($p['price'],0)?></p>
                    <?php if($p['save'] > 0): ?>
                    <p class="text-sm font-bold text-emerald-500 mb-4 bg-emerald-50 inline-block px-2 py-0.5 rounded">Save ৳<?=number_format($p['save'],0)?></p>
                    <?php else: ?>
                    <p class="text-sm font-bold text-slate-400 mb-4 h-6"></p>
                    <?php endif; ?>
                    
                    <ul class="space-y-2 text-sm text-slate-600 font-medium border-t border-slate-100 pt-4">
                        <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-indigo-600"></i> Top-tier visibility</li>
                        <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-indigo-600"></i> 4KM service radius</li>
                        <li class="flex items-center gap-2"><i data-lucide="check" class="w-4 h-4 text-indigo-600"></i> Trust Badge on profile</li>
                    </ul>
                </div>
            </div>
        </label>
        <?php endforeach; ?>
        </div>

        <!-- How to Pay Manual -->
        <div class="bg-indigo-50 rounded-2xl border border-indigo-100 p-6 mb-8 text-indigo-900">
            <h2 class="font-bold text-indigo-900 mb-4 flex items-center gap-2">
                <i data-lucide="info" class="w-5 h-5 text-indigo-600"></i>
                Manual Payment Instructions
            </h2>
            <ol class="space-y-3 text-sm font-medium list-none">
                <li class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-indigo-200 text-indigo-800 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">1</span> Send money via bKash, Nagad, or Rocket to <strong>01301085365</strong>.</li>
                <li class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-indigo-200 text-indigo-800 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">2</span> Provide your Provider ID (<?=$provider_id?>) in the payment reference.</li>
                <li class="flex items-start gap-3"><span class="w-6 h-6 rounded-full bg-indigo-200 text-indigo-800 text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">3</span> Submit the Transaction ID and Sender Number below.</li>
            </ol>
        </div>

        <?php if(!$is_pending): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
            
            <!-- Automated SSLCommerz -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] p-6 md:p-8 flex flex-col">
                <h2 class="text-xl font-bold text-slate-800 mb-2 flex items-center gap-2">
                    <i data-lucide="zap" class="w-6 h-6 text-amber-500"></i>
                    Pay Automatically
                </h2>
                <p class="text-sm text-slate-500 mb-8 font-medium">Get activated instantly using secure SSLCommerz checkout.</p>
                
                <div class="mb-6">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Discount Coupon</label>
                    <div class="flex gap-2">
                        <input type="text" id="coupon_code" class="w-full bg-slate-50 border border-slate-200 text-sm rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 font-mono uppercase" placeholder="Enter code">
                        <button type="button" onclick="applyCoupon()" class="px-5 py-3 bg-slate-800 hover:bg-slate-900 text-white font-bold rounded-xl text-sm transition shrink-0 shadow-md">Apply</button>
                    </div>
                    <p id="coupon_msg" class="text-xs font-medium mt-2 hidden"></p>
                </div>

                <div class="flex justify-between items-center mb-8 pt-6 border-t border-slate-100">
                    <span class="text-slate-500 font-bold">Total Payable</span>
                    <span class="text-3xl font-black text-slate-900" id="display_amount">৳0</span>
                </div>

                <form action="../payment_integration/payment_init.php" method="POST" class="mt-auto">
                    <input type="hidden" name="type" value="provider_subscription">
                    <input type="hidden" name="provider_id" value="<?=$provider_id?>">
                    <input type="hidden" name="duration" id="auto_duration" value="6">
                    <input type="hidden" name="amount" id="auto_amount" value="0">
                    <input type="hidden" name="coupon" id="auto_coupon" value="">
                    <button type="submit" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-xl text-sm transition shadow-lg shadow-indigo-600/20 hover:shadow-indigo-600/40 hover:-translate-y-0.5 flex items-center justify-center gap-2">
                        <i data-lucide="credit-card" class="w-5 h-5"></i> Pay via SSLCommerz
                    </button>
                </form>
            </div>

            <!-- Manual Submission -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] p-6 md:p-8 flex flex-col">
                <h2 class="text-xl font-bold text-slate-800 mb-2 flex items-center gap-2">
                    <i data-lucide="key" class="w-6 h-6 text-slate-400"></i>
                    Manual Submission
                </h2>
                <p class="text-sm text-slate-500 mb-8 font-medium">Verify your manual payment within 24 hours.</p>
                <form method="POST" class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Selected Plan</label>
                        <select name="plan_duration" id="planSelect" class="w-full bg-slate-50 border border-slate-200 font-medium text-sm rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500">
                            <?php foreach($plans as $p): ?>
                            <option value="<?=$p['duration_months']?>"><?=htmlspecialchars($p['name'])?> — ৳<?=number_format($p['price'],0)?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Transaction ID</label>
                        <input type="text" name="tran_id" required placeholder="e.g. 7DF8XJ9K" class="w-full bg-slate-50 border border-slate-200 text-sm rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 font-mono uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Sender Mobile Number</label>
                        <input type="text" name="payment_key" required placeholder="e.g. 01700000000" class="w-full bg-slate-50 border border-slate-200 text-sm rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="w-full py-4 bg-slate-900 hover:bg-black text-white font-black rounded-xl text-sm transition shadow-lg flex items-center justify-center gap-2">
                            <i data-lucide="send" class="w-5 h-5"></i> Submit for Verification
                        </button>
                    </div>
                </form>
            </div>

        </div>
        <?php endif; ?>

    </main>

<script>
    lucide.createIcons();
    
    const plansData = <?=json_encode($plans)?>;
    const userId = <?=json_encode($_SESSION['user_id'])?>;
    let currentAmount = 0;
    let discountPercent = 0;

    function recalculateAmount() {
        let finalAmt = currentAmount - (currentAmount * discountPercent / 100);
        finalAmt = Math.max(0, finalAmt);
        document.getElementById('auto_amount').value = finalAmt;
        document.getElementById('display_amount').innerText = '৳' + finalAmt.toLocaleString(undefined, {minimumFractionDigits:0, maximumFractionDigits:2});
    }

    function updateSelectedPlan(planId) {
        // find duration for the select box
        let dur = 1;
        for(let i=0; i<plansData.length; i++){
            if(plansData[i].id == planId) {
                dur = plansData[i].duration_months;
                currentAmount = parseFloat(plansData[i].price);
                break;
            }
        }
        document.getElementById('planSelect').value = dur;
        document.getElementById('auto_duration').value = dur;
        
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
                msgEl.className = 'text-xs font-medium mt-2 text-emerald-600';
                msgEl.innerText = `${data.message} (${discountPercent}% off)`;
                document.getElementById('auto_coupon').value = code;
                recalculateAmount();
            } else {
                discountPercent = 0;
                msgEl.className = 'text-xs font-medium mt-2 text-rose-600';
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
        if(r.checked) updateSelectedPlan(r.value);
    });
</script>
</body>
</html>
