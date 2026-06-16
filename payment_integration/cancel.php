<?php
// payment_integration/cancel.php
ini_set('session.use_cookies', '0');
session_start();
require_once 'config.php';

$tran_id = isset($_POST['tran_id']) ? $_POST['tran_id'] : '';
$bill_id = isset($_POST['value_a']) ? intval($_POST['value_a']) : null;

// Handle user-cancelled transactions
// Database placeholder:
// UPDATE payments SET status = 'Cancelled' WHERE transaction_id = '$tran_id';

// Add placeholder comments for redirecting back to billing with a cancellation message
echo "<script>alert('Payment was cancelled by the user.'); window.location.href = '" . BASE_URL . "resident/billing.php';</script>";
exit;
?>