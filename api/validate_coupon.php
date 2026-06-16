<?php
require_once '../includes/db_config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$code = mysqli_real_escape_string($conn, trim($data['code'] ?? ''));
$target = (int)($data['user_id'] ?? 0);

if (empty($code)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty coupon code']);
    exit;
}

$q = mysqli_query($conn, "SELECT * FROM coupons WHERE code = '$code' AND is_active = 1");
if (mysqli_num_rows($q) > 0) {
    $coupon = mysqli_fetch_assoc($q);
    
    // Check expiry
    if (!empty($coupon['valid_until']) && strtotime($coupon['valid_until']) < time()) {
        echo json_encode(['status' => 'error', 'message' => 'Coupon has expired']);
        exit;
    }
    // Check max uses
    if (!empty($coupon['max_uses']) && $coupon['used_count'] >= $coupon['max_uses']) {
        echo json_encode(['status' => 'error', 'message' => 'Coupon usage limit reached']);
        exit;
    }
    // Check target user
    if (!empty($coupon['target_user_id']) && $coupon['target_user_id'] != $target) {
        echo json_encode(['status' => 'error', 'message' => 'Coupon not valid for this user']);
        exit;
    }
    
    echo json_encode([
        'status' => 'success',
        'discount_percent' => (int)$coupon['discount_percent'],
        'message' => 'Coupon applied successfully!'
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or inactive coupon code']);
}
