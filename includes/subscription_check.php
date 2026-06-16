<?php
// includes/subscription_check.php
// Include at the top of any owner page AFTER session_start() and db_config.php.
// Provides: $subscription (array), $sub_is_active (bool), nibash_can($feature) helper.

if (!isset($conn)) require_once __DIR__ . '/db_config.php';

$b_id = $_SESSION['building_id'] ?? 0;

// Fetch subscription + plan
$sub_res = mysqli_query($conn,
    "SELECT os.*, sp.plan_name, sp.max_residents, sp.max_cameras,
            sp.has_cctv, sp.has_analytics, sp.has_ai_chatbot, sp.price_monthly
     FROM subscriptions os
     JOIN subscription_plans sp ON os.plan_id = sp.id
     WHERE os.subscriber_type='building' AND os.subscriber_id = $b_id LIMIT 1");

if (!$sub_res || mysqli_num_rows($sub_res) === 0) {
    if ($b_id > 0) {
        // Auto-create free trial for new owners
        mysqli_query($conn,
            "INSERT IGNORE INTO subscriptions (subscriber_type, subscriber_id, plan_id, status, expires_at)
             VALUES ('building', $b_id, 1, 'trial', DATE_ADD(NOW(), INTERVAL 14 DAY))");
        if (mysqli_affected_rows($conn) > 0) {
            header('Location: ' . $_SERVER['PHP_SELF']); exit;
        }
    }
}

$subscription = ($sub_res && mysqli_num_rows($sub_res) > 0) ? mysqli_fetch_assoc($sub_res) : null;
if (!$subscription) {
    $subscription = [
        'status' => 'expired',
        'expires_at' => 'yesterday',
        'plan_name' => 'None'
    ];
}

$sub_expires  = strtotime($subscription['expires_at'] ?? 'yesterday');

// Determine active state — pending payment key = still in trial, not blocked
$sub_is_active = in_array($subscription['status'], ['active', 'trial']) && $sub_expires > time();

// Auto-expire if past date
if (!$sub_is_active && $subscription['status'] !== 'suspended') {
    if ($b_id > 0) {
        mysqli_query($conn, "UPDATE subscriptions SET status='expired' WHERE subscriber_type='building' AND subscriber_id=$b_id");
    }
    $subscription['status'] = 'expired';
}

// Feature gate helper
function nibash_can(string $feature, array $sub = []): bool {
    global $subscription;
    $data = empty($sub) ? $subscription : $sub;
    return !empty($data[$feature]);
}

// Days remaining helper
$sub_days_left = $sub_is_active ? max(0, (int)(($sub_expires - time()) / 86400)) : 0;
$sub_is_trial  = $subscription['status'] === 'trial';
$sub_expires_soon = $sub_is_active && $sub_days_left <= 7;

// If subscription is hard expired/suspended, redirect to subscribe page
// (only wall if not already on the subscribe page itself)
if (!$sub_is_active && !in_array(basename($_SERVER['PHP_SELF']), ['subscribe.php', 'logout.php'])) {
    ?><!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Subscription Required | Nibash</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>body{font-family:'Inter',sans-serif;}</style>
    </head>
    <body class="bg-slate-50 min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl p-10 max-w-md w-full text-center">
            <div class="w-16 h-16 bg-red-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
                <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M4.93 4.93l14.14 14.14M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z"></path></svg>
            </div>
            <h2 class="text-2xl font-black text-slate-900 mb-2">Subscription Expired</h2>
            <p class="text-slate-500 text-sm mb-6">Your <strong><?=htmlspecialchars($subscription['plan_name'])?></strong> subscription has <?=$subscription['status']==='suspended'?'been suspended':'expired'?>. Please renew to continue using Nibash.</p>
            <a href="subscribe.php" class="inline-flex items-center justify-center gap-2 w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-sm transition">
                Renew Subscription →
            </a>
            <a href="../login.php?logout=1" class="block mt-3 text-sm text-slate-400 hover:text-slate-600">Logout</a>
        </div>
    </body></html>
    <?php exit;
}
?>
