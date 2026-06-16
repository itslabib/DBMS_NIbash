<?php
// payment_integration/payment_init.php
session_start();
require_once 'config.php';

// Verify authentication
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

// Ensure database connection from config.php (via db_config.php) is available as $conn

// 1. Receive bill information
// Typically, the "Pay Now" button in resident/billing.php will submit a form or fetch request here
$type = $_POST['type'] ?? 'bill';
$resident_id = $_SESSION['user_id'];

if ($type === 'subscription') {
    $plan_id = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : null;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
    $b_id = isset($_POST['building_id']) ? intval($_POST['building_id']) : null;
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : 1;
    if (!$plan_id || !$b_id || $amount <= 0) die("Invalid subscription info.");
} elseif ($type === 'provider_subscription') {
    $provider_id = isset($_POST['provider_id']) ? intval($_POST['provider_id']) : null;
    $duration = isset($_POST['duration']) ? intval($_POST['duration']) : null;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
    if (!$provider_id || !$duration || $amount <= 0) die("Invalid provider subscription info.");
} else {
    $bill_id = isset($_POST['bill_id']) ? intval($_POST['bill_id']) : null;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0.0;
    if (!$bill_id || $amount <= 0) die("Invalid bill information.");
}

// 2. Fetch resident profile information from the database
$user_q = mysqli_query($conn, "SELECT up.full_name, up.phone, u.email, up.address FROM user_profiles up JOIN users u ON u.id = up.user_id WHERE up.user_id = '$resident_id'");
$user_profile = mysqli_fetch_assoc($user_q);

$cus_name = $user_profile['full_name'] ?? 'Resident';
$cus_email = $user_profile['email'] ?? 'test@test.com';
$cus_phone = $user_profile['phone'] ?? '01700000000';
$cus_address = $user_profile['address'] ?? 'Dhaka';

// 3. Generate a unique transaction ID
$transaction_id = "SSLCZ_" . uniqid(); 

// 4. Save initial transaction record into the database (optional but recommended for tracking)
// INSERT INTO payments (bill_id, amount_paid, payment_method, transaction_id, status) VALUES ('$bill_id', '$amount', 'SSLCommerz', '$transaction_id', 'Pending')
// Ensure your payments table has a status column, or you can track it in a separate transaction_logs table

// 5. Prepare transaction data for SSLCommerz
$post_data = array();
$post_data['store_id'] = SSLCZ_STORE_ID;
$post_data['store_passwd'] = SSLCZ_STORE_PASSWORD;
$post_data['total_amount'] = $amount;
$post_data['currency'] = "BDT";
$post_data['tran_id'] = $transaction_id;
$post_data['success_url'] = BASE_URL . "payment_integration/success.php";
$post_data['fail_url'] = BASE_URL . "payment_integration/fail.php";
$post_data['cancel_url'] = BASE_URL . "payment_integration/cancel.php";
// ipn_url receives backend notification, must be publicly accessible on live servers
$post_data['ipn_url'] = BASE_URL . "payment_integration/ipn.php";

// Customer Information
$post_data['cus_name'] = $cus_name;
$post_data['cus_email'] = $cus_email;
$post_data['cus_add1'] = $cus_address;
$post_data['cus_add2'] = "";
$post_data['cus_city'] = "Dhaka";
$post_data['cus_state'] = "Dhaka";
$post_data['cus_postcode'] = "1000";
$post_data['cus_country'] = "Bangladesh";
$post_data['cus_phone'] = $cus_phone;
$post_data['cus_fax'] = "";

// Essential parameters to prevent sandbox 500 errors
$post_data['emi_option'] = 0; // Disable EMI to avoid get_emi internal server error
$post_data['product_category'] = "Service";
$post_data['product_profile'] = "general";

// Shipment Information
$post_data['shipping_method'] = "NO";
$post_data['num_of_item'] = "1";
if ($type === 'subscription') {
    $post_data['product_name'] = "Subscription Plan #$plan_id";
    $post_data['value_a'] = $plan_id;
    $post_data['value_b'] = $b_id;
    $post_data['value_c'] = "SUBSCRIPTION";
    $post_data['value_d'] = $duration;
} elseif ($type === 'provider_subscription') {
    $post_data['product_name'] = "PRO Provider $duration Months";
    $post_data['value_a'] = $duration;
    $post_data['value_b'] = $provider_id;
    $post_data['value_c'] = "PROVIDER_SUB";
} else {
    $post_data['product_name'] = "Bill Payment #$bill_id";
    $post_data['value_a'] = $bill_id;
    $post_data['value_b'] = $resident_id;
    $post_data['value_c'] = "BILL";
}

// 6. Initiate cURL to SSLCommerz Sandbox
$handle = curl_init();
curl_setopt($handle, CURLOPT_URL, SSLCZ_SUBMIT_URL);
curl_setopt($handle, CURLOPT_TIMEOUT, 30);
curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($handle, CURLOPT_POST, 1 );
curl_setopt($handle, CURLOPT_POSTFIELDS, $post_data);
curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE); // Disable in sandbox if issues arise

# PARSE THE JSON RESPONSE
$content = curl_exec($handle);
$code = curl_getinfo($handle, CURLINFO_HTTP_CODE);

if($code == 200 && !( curl_errno($handle))) {
    curl_close($handle);
    $sslcommerzResponse = $content;
} else {
    curl_close($handle);
    echo "FAILED TO CONNECT WITH SSLCOMMERZ API";
    exit;
}

$sslcz = json_decode($sslcommerzResponse, true );

if(isset($sslcz['GatewayPageURL']) && $sslcz['GatewayPageURL'] != "") {
    // Redirect to the SSLCommerz gateway
    echo "<meta http-equiv='refresh' content='0;url=".$sslcz['GatewayPageURL']."'>";
    exit;
} else {
    echo "JSON Data parsing error!";
}
?>