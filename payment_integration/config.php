<?php
// payment_integration/config.php

// Define Base URL, replace with your exact domain/folder setup
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/Nibash/');
}

// Sandbox Store ID and Store Password
define('SSLCZ_STORE_ID', 'nibas6a0721d0b929b'); // REPLACE with your Sandbox Store ID
define('SSLCZ_STORE_PASSWORD', 'nibas6a0721d0b929b@ssl'); // REPLACE with your Sandbox Store Password

// SSLCommerz URLs
define('SSLCZ_IS_SANDBOX', true); // Change to false for live mode
define('SSLCZ_SUBMIT_URL', SSLCZ_IS_SANDBOX ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' : 'https://securepay.sslcommerz.com/gwprocess/v4/api.php');
define('SSLCZ_VALIDATION_URL', SSLCZ_IS_SANDBOX ? 'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php' : 'https://securepay.sslcommerz.com/validator/api/validationserverAPI.php');

// Database connection inclusion
// Include your main db_config.php to reuse the existing connection
require_once dirname(__DIR__) . '/includes/db_config.php';
?>