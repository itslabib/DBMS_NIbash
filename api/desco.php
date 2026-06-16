<?php
session_start();
require_once '../includes/db_config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$raw_data = json_decode(file_get_contents('php://input'), true);
$action = $_POST['action'] ?? $raw_data['action'] ?? '';
$account = $_POST['account'] ?? $raw_data['account'] ?? '';

if (empty($action) || empty($account)) {
    echo json_encode(['success' => false, 'message' => 'Missing action or account number']);
    exit();
}

if (!preg_match('/^\d+$/', $account)) {
    echo json_encode(['success' => false, 'message' => 'Invalid account number format. Please enter numbers only.']);
    exit();
}

$cli_path = 'C:\Users\sucha\AppData\Roaming\Python\Python314\Scripts\desco-cli.exe';

try {
    if ($action === 'get_balance') {
        $cmd = 'cmd.exe /c "set PYTHONIOENCODING=utf-8 && ' . escapeshellarg($cli_path) . ' get-balance -a ' . $account . '" 2>&1';
        $output = shell_exec($cmd);
        
        // Return output directly
        echo json_encode(['success' => true, 'output' => trim($output)]);
        
    } elseif ($action === 'get_customer_info') {
        $cmd = 'cmd.exe /c "set PYTHONIOENCODING=utf-8 && ' . escapeshellarg($cli_path) . ' get-customer-info -a ' . $account . '" 2>&1';
        $output = shell_exec($cmd);
        echo json_encode(['success' => true, 'output' => trim($output)]);
    } elseif ($action === 'get_recharge_history') {
        $cmd = 'cmd.exe /c "set PYTHONIOENCODING=utf-8 && ' . escapeshellarg($cli_path) . ' get-recharge-history -a ' . $account . '" 2>&1';
        $output = shell_exec($cmd);
        echo json_encode(['success' => true, 'output' => trim($output)]);
    } elseif ($action === 'get_monthly_consumption') {
        $cmd = 'cmd.exe /c "set PYTHONIOENCODING=utf-8 && ' . escapeshellarg($cli_path) . ' get-monthly-consumption -a ' . $account . '" 2>&1';
        $output = shell_exec($cmd);
        echo json_encode(['success' => true, 'output' => trim($output)]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Failed to execute DESCO CLI: ' . $e->getMessage()]);
}
