<?php
session_start();
require_once '../includes/db_config.php';
date_default_timezone_set('Asia/Dhaka');

// Require Composer autoloader
require_once '../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Basic auth check
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$user_id = $_SESSION['user_id'];
$bill_id = isset($_GET['bill_id']) ? intval($_GET['bill_id']) : 0;

if ($bill_id <= 0) {
    die("Invalid Bill ID.");
}

// Fetch the bill and payment info
$bill_query = "SELECT b.*, p.transaction_id, p.payment_date 
               FROM bills b
               LEFT JOIN payments p ON b.id = p.bill_id AND p.payment_status = 'Success'
               WHERE b.id = $bill_id AND b.resident_id = $user_id AND b.status = 'Paid'";
$bill_res = mysqli_query($conn, $bill_query);
$bill = mysqli_fetch_assoc($bill_res);

if (!$bill) {
    die("Receipt not found or not paid yet.");
}

// Fetch user info for the receipt
$user_q = "SELECT p.full_name, a.apt_number 
           FROM users u 
           LEFT JOIN user_profiles p ON u.id = p.user_id 
           LEFT JOIN apartment_assignments aa ON u.id = aa.user_id AND aa.is_active = 1
           LEFT JOIN apartments a ON aa.apt_id = a.id
           WHERE u.id = $user_id";
$user_res = mysqli_query($conn, $user_q);
$user_profile = mysqli_fetch_assoc($user_res);

// Fetch line items
$items_q = "SELECT i.*, u.utility_name 
            FROM bill_items i 
            LEFT JOIN utility_types u ON i.utility_type_id = u.id 
            WHERE i.bill_id = $bill_id";
$items_res = mysqli_query($conn, $items_q);
$items = [];
while ($row = mysqli_fetch_assoc($items_res)) {
    $items[] = $row;
}

// Read the HTML template
$template_path = '../pdf_templates/resident_bill_receipt.html';
if (!file_exists($template_path)) {
    die("PDF Template not found.");
}
$html = file_get_contents($template_path);

// Prepare replacement data
$trx_id = $bill['transaction_id'] ?? ('TRX-' . strtoupper(uniqid()));
$payment_date = $bill['payment_date'] ? date('M j, Y h:i A', strtotime($bill['payment_date'])) : date('M j, Y h:i A');
$resident_name = $user_profile['full_name'] ?? 'N/A';
$apt_number = $user_profile['apt_number'] ?? 'N/A';
$billing_month = $bill['month'] . ' ' . $bill['year'];
$current_year = date('Y');
$total_amount_paid = 'Tk. ' . number_format($bill['total_amount'], 2);

// Replace basic placeholders
$html = str_replace('{{TRANSACTION_ID}}', $trx_id, $html);
$html = str_replace('{{DATE_OF_PAYMENT}}', $payment_date, $html);
$html = str_replace('{{RESIDENT_NAME}}', $resident_name, $html);
$html = str_replace('{{APT_NUMBER}}', $apt_number, $html);
$html = str_replace('{{BILLING_MONTH}}', $billing_month, $html);
$html = str_replace('{{CURRENT_YEAR}}', $current_year, $html);

// Build dynamic items rows
$items_html = '';
foreach ($items as $item) {
    if ((float)$item['amount'] <= 0) {
        continue;
    }
    $name = $item['item_name'] ?: ($item['utility_name'] ?: 'Custom Item');
    $amount = 'Tk. ' . number_format($item['amount'], 2);
    $items_html .= '<tr>
                        <td>' . htmlspecialchars($name) . '</td>
                        <td class="amount">' . htmlspecialchars($amount) . '</td>
                    </tr>';
}

$discount = (float)($bill['discount'] ?? 0);
$tax = (float)($bill['tax'] ?? 0);

$items_html .= '<tr>
                    <td>Discount</td>
                    <td class="amount text-rose">-Tk. ' . number_format($discount, 2) . '</td>
                </tr>';
$items_html .= '<tr>
                    <td>VAT / Tax</td>
                    <td class="amount text-emerald">+Tk. ' . number_format($tax, 2) . '</td>
                </tr>';

// Replace the placeholder rows with our dynamic items
$start_tag = '<!-- Replace these rows dynamically from your PHP backend -->';
$end_tag = '<!-- Dynamic rows end -->';
$start_pos = strpos($html, $start_tag);
$end_pos = strpos($html, $end_tag);

if ($start_pos !== false && $end_pos !== false) {
    $end_pos += strlen($end_tag);
    $html = substr_replace($html, $items_html, $start_pos, $end_pos - $start_pos);
}

$html = str_replace('{{TOTAL_AMOUNT_PAID}}', $total_amount_paid, $html);

// Initialize Dompdf
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output the generated PDF to Browser
$filename = 'Receipt_' . $bill['month'] . '_' . $bill['year'] . '.pdf';
$dompdf->stream($filename, ["Attachment" => true]);
