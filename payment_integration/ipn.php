<?php
// payment_integration/ipn.php
require_once 'config.php';

// Handle SSLCommerz server notifications (IPN)
// IPN happens in the background, out of user session.

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tran_id'])) {
    $tran_id = $_POST['tran_id'];
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = $_POST['currency'] ?? '';
    $bill_id = intval($_POST['value_a'] ?? 0);
    $type = $_POST['value_c'] ?? 'BILL';
    $resident_id = $_POST['value_b'] ?? '';
    $status = $_POST['status'] ?? '';
    $val_id = $_POST['val_id'] ?? '';
    $card_type = $_POST['card_type'] ?? 'Online';

    // Check if payment already exists to prevent duplicate processing
    $existing_payment = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM payments WHERE transaction_id='$tran_id' LIMIT 1"));
    if ($existing_payment) {
        http_response_code(200);
        exit; // Already processed
    }

    if ($status == 'VALID' || $status == 'VALIDATED') {
        // Validate against validation URL again to be 100% sure
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

            if ($validationResponse['status'] == 'VALID' || $validationResponse['status'] == 'VALIDATED') {
                if ($validationResponse['amount'] == $amount && $validationResponse['currency'] == $currency) {
                    // Verify payment and update the database
                    $payment_method = 'SSLCommerz - ' . $card_type;
                    $payment_date = date('Y-m-d H:i:s');
                    
                    mysqli_begin_transaction($conn);
                    try {
                        if ($type === 'SUBSCRIPTION') {
                            $plan_id = $bill_id;
                            $b_id = intval($resident_id); // value_b is building_id
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
                        } elseif ($type === 'PROVIDER_SUB') {
                            $duration = $bill_id; // value_a
                            $prov_id = intval($resident_id); // value_b
                            
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
                        } else {
                            // Fetch bill details
                            $bill_q = mysqli_query($conn, "SELECT total_amount, paid_amount FROM bills WHERE id='$bill_id'");
                            if ($bill_q && mysqli_num_rows($bill_q) > 0) {
                                $bill = mysqli_fetch_assoc($bill_q);
                                $new_paid_total = floatval($bill['paid_amount']) + $amount;
                                $total_due = floatval($bill['total_amount']);
                                $new_status = ($new_paid_total >= ($total_due - 0.01)) ? 'Paid' : 'Partially Paid';
                                
                                // Insert payment record
                                mysqli_query($conn, "INSERT INTO payments (bill_id, amount_paid, payment_method, transaction_id, payment_status, payment_date, notes) 
                                                     VALUES ('$bill_id', '$amount', '$payment_method', '$tran_id', 'Success', '$payment_date', 'Online Payment via SSLCommerz (IPN)')");
                                
                                // Update bill status
                                mysqli_query($conn, "UPDATE bills SET paid_amount='$new_paid_total', status='$new_status', updated_at='$payment_date' WHERE id='$bill_id'");
                                
                                mysqli_commit($conn);

                                // Send "Paid" receipt email (safety net — success.php handles normal flow)
                                if ($new_status === 'Paid') {
                                    require_once '../includes/mailer_helper.php';
                                    $ipn_receipt_q = mysqli_query($conn,
                                        "SELECT u.email, p.full_name, b.month, b.year, b.due_date,
                                                b.subtotal, b.discount, b.tax, b.total_amount, b.bill_number, a.apt_number
                                         FROM bills b
                                         JOIN users u ON u.id = b.resident_id
                                         JOIN user_profiles p ON u.id = p.user_id
                                         LEFT JOIN apartments a ON a.id = b.apt_id
                                         WHERE b.id = '$bill_id' LIMIT 1");
                                    if ($ipn_receipt_q && $ipn_row = mysqli_fetch_assoc($ipn_receipt_q)) {
                                        $ipn_items_q = mysqli_query($conn,
                                            "SELECT item_name, quantity, unit_price, amount FROM bill_items WHERE bill_id = '$bill_id'");
                                        $ipn_items = [];
                                        while ($ri = mysqli_fetch_assoc($ipn_items_q)) { $ipn_items[] = $ri; }
                                        send_receipt_email(
                                            $ipn_row['email'],
                                            $ipn_row['full_name'],
                                            array_merge($ipn_row, ['items' => $ipn_items])
                                        );
                                    }
                                }
                            }
                        }
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                    }
                    
                    http_response_code(200);
                    exit;
                }
            }
        }
    } else {
        // Update payment status as Failed/Cancelled if tracked
        // Optionally record failed transaction for audit purposes
    }
}

http_response_code(400);
?>