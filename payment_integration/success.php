<?php
// payment_integration/success.php
ini_set('session.use_cookies', '0');
session_start();
require_once 'config.php';
require_once '../includes/mailer_helper.php';

// Check if POST data has been sent
$tran_id = isset($_POST['tran_id']) ? $_POST['tran_id'] : '';
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$currency = isset($_POST['currency']) ? $_POST['currency'] : '';
$bill_id = isset($_POST['value_a']) ? intval($_POST['value_a']) : null;
$resident_id = isset($_POST['value_b']) ? $_POST['value_b'] : null;
$type = isset($_POST['value_c']) ? $_POST['value_c'] : 'BILL';
$val_id = isset($_POST['val_id']) ? $_POST['val_id'] : '';

if (!$tran_id || !$bill_id) {
    die("Invalid request.");
}

// Check if payment already exists to prevent duplicate processing
$existing_payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM payments WHERE transaction_id='$tran_id' LIMIT 1"));
if ($existing_payment) {
    echo "<script>alert('Payment already processed.'); window.location.href = '" . BASE_URL . "resident/billing.php';</script>";
    exit;
}

// 1. Validate the transaction with SSLCommerz API (Important for security)
if (!empty($val_id)) {
    $val_data = array(
        'val_id' => $val_id,
        'store_id' => SSLCZ_STORE_ID,
        'store_passwd' => SSLCZ_STORE_PASSWORD,
        'format' => 'json'
    );

    $handle = curl_init();
    $url = SSLCZ_VALIDATION_URL . '?' . http_build_query($val_data);
    curl_setopt($handle, CURLOPT_URL, $url);
    curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($handle);
    curl_close($handle);

    $validationResponse = json_decode($result, true);

    if (!($validationResponse['status'] == 'VALID' || $validationResponse['status'] == 'VALIDATED')) {
        die("Payment validation failed. Status: " . ($validationResponse['status'] ?? 'Unknown'));
    }
    
    if (!($validationResponse['amount'] == $amount && $validationResponse['currency'] == $currency)) {
        die("Amount mismatch. Please contact support.");
    }
}

// 2. Transaction is successfully validated!
$payment_method = 'SSLCommerz - ' . ($_POST['card_type'] ?? 'Online');
$payment_date = date('Y-m-d H:i:s');

mysqli_begin_transaction($conn);
try {
    if ($type === 'SUBSCRIPTION') {
        $plan_id = $bill_id;
        $b_id = intval($resident_id); // value_b is building_id for subscriptions
        $duration = intval($_POST['value_d'] ?? 1);
        
        mysqli_query($conn,
            "INSERT INTO subscriptions (entity_type, entity_id, plan_id, status, tran_id, payment_key, payment_verified_at, expires_at)
             VALUES ('building', $b_id, $plan_id, 'active', '$tran_id', '$tran_id', '$payment_date', DATE_ADD(NOW(), INTERVAL $duration MONTH))
             ON DUPLICATE KEY UPDATE
                plan_id=$plan_id,
                tran_id='$tran_id',
                payment_key='$tran_id',
                status='active',
                payment_verified_at='$payment_date',
                expires_at=DATE_ADD(NOW(), INTERVAL $duration MONTH)");
                
        mysqli_commit($conn);
        echo "<script>alert('Subscription Payment Successful!'); window.location.href = '" . BASE_URL . "owner/subscribe.php';</script>";
        exit;
    } elseif ($type === 'PROVIDER_SUB') {
        $duration = $bill_id; // value_a holds duration for provider sub
        $prov_id = intval($resident_id); // value_b holds provider_id
        
        mysqli_query($conn,
            "INSERT INTO subscriptions (entity_type, entity_id, duration_months, status, tran_id, payment_key, payment_verified_at, expires_at)
             VALUES ('provider', $prov_id, $duration, 'active', '$tran_id', '$tran_id', '$payment_date', DATE_ADD(NOW(), INTERVAL $duration MONTH))
             ON DUPLICATE KEY UPDATE
                duration_months=$duration,
                tran_id='$tran_id',
                payment_key='$tran_id',
                status='active',
                payment_verified_at='$payment_date',
                expires_at=DATE_ADD(NOW(), INTERVAL $duration MONTH)");
        
        mysqli_query($conn, "UPDATE service_providers SET is_subscribed=1 WHERE id=$prov_id");
                
        mysqli_commit($conn);
        echo "<script>alert('Provider PRO Subscription Successful!'); window.location.href = '" . BASE_URL . "essentials/subscribe.php';</script>";
        exit;
    } else {
        // Fetch current bill status
        $bill_q = mysqli_query($conn, "SELECT total_amount, paid_amount, status FROM bills WHERE id='$bill_id'");
        
        if (!$bill_q || mysqli_num_rows($bill_q) === 0) {
            throw new Exception("Bill not found.");
        }
        
        $bill = mysqli_fetch_assoc($bill_q);
        $new_paid_total = floatval($bill['paid_amount']) + $amount;
        $total_due = floatval($bill['total_amount']);
        $new_status = ($new_paid_total >= ($total_due - 0.01)) ? 'Paid' : 'Partially Paid';
        
        // 3. Insert payment record with payment_status set to 'Success'
        $insert_payment = mysqli_query($conn, 
            "INSERT INTO payments (bill_id, amount_paid, payment_method, transaction_id, payment_status, payment_date) 
             VALUES ('$bill_id', '$amount', '$payment_method', '$tran_id', 'Success', '$payment_date')");
        
        if (!$insert_payment) throw new Exception("Failed to record payment: " . mysqli_error($conn));
        
        // 4. Update bill status and paid amount
        $update_bill = mysqli_query($conn, 
            "UPDATE bills SET paid_amount='$new_paid_total', status='$new_status', updated_at='$payment_date' WHERE id='$bill_id'");
        
        if (!$update_bill) throw new Exception("Failed to update bill: " . mysqli_error($conn));
        
        // 5. Commit transaction
        mysqli_commit($conn);

        // 6. Send "Paid" receipt email if bill is now fully paid
        if ($new_status === 'Paid') {
            $receipt_q = mysqli_query($conn,
                "SELECT u.email, p.full_name, b.month, b.year, b.due_date,
                        b.subtotal, b.discount, b.tax, b.total_amount, b.bill_number, a.apt_number
                 FROM bills b
                 JOIN users u ON u.id = b.resident_id
                 JOIN user_profiles p ON u.id = p.user_id
                 LEFT JOIN apartments a ON a.id = b.apt_id
                 WHERE b.id = '$bill_id'
                 LIMIT 1");

            if ($receipt_q && $receipt_row = mysqli_fetch_assoc($receipt_q)) {
                $receipt_items_q = mysqli_query($conn,
                    "SELECT item_name, quantity, unit_price, amount
                     FROM bill_items WHERE bill_id = '$bill_id'");
                $receipt_items = [];
                while ($ri = mysqli_fetch_assoc($receipt_items_q)) {
                    $receipt_items[] = $ri;
                }
                send_receipt_email(
                    $receipt_row['email'],
                    $receipt_row['full_name'],
                    array_merge($receipt_row, ['items' => $receipt_items])
                );
            }
        }

        // 7. Redirect back to the billing page with a success message
        echo "<script>alert('Payment Successful! A receipt has been sent to your email.'); window.location.href = '" . BASE_URL . "resident/billing.php';</script>";
        exit;
    }
} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Error processing payment: " . htmlspecialchars($e->getMessage()));
}
?>