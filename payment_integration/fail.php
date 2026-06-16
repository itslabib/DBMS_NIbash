<?php
// payment_integration/fail.php
ini_set('session.use_cookies', '0');
session_start();
require_once 'config.php';

$tran_id = isset($_POST['tran_id']) ? $_POST['tran_id'] : '';
$bill_id = isset($_POST['value_a']) ? intval($_POST['value_a']) : null;

// Handle failed transactions
// Database placeholder:
// UPDATE payments SET status = 'Failed' WHERE transaction_id = '$tran_id';

// Add placeholder comments for logging failure and showing an error message.
// You might want to log this into a transaction_logs table here.

echo "<script>alert('Payment Failed.'); window.location.href = '" . BASE_URL . "resident/billing.php';</script>";
exit;
?>