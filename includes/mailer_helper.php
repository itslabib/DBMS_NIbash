<?php
/**
 * mailer_helper.php — Nibash centralised email + PDF utility.
 *
 * Public API:
 *   send_invoice_email($toEmail, $toName, $bill)  — Pending invoice
 *   send_receipt_email($toEmail, $toName, $bill)  — Paid receipt
 *
 * $bill keys: bill_number, full_name, apt_number, month, year,
 *             due_date, items[], subtotal, discount, tax, total_amount
 */

use PHPMailer\PHPMailer\PHPMailer;
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/../vendor/autoload.php';

// ── SMTP config ───────────────────────────────────────────────────────────────
define('NIBASH_SMTP_HOST', 'smtp.gmail.com');
define('NIBASH_SMTP_USER', 'suchak9931@gmail.com');
define('NIBASH_SMTP_PASS', 'ubaz ayum yhyy hyis');
define('NIBASH_SMTP_PORT', 587);
define('NIBASH_FROM_NAME', 'Nibash Billing');

// ── Internal: build PDF HTML from template design ─────────────────────────────
function _nibash_pdf_html(array $bill, string $status): string
{
    $isPaid      = ($status === 'Paid');
    $accentColor = $isPaid ? '#059669' : '#d97706';
    $statusLabel = $isPaid ? 'PAID'    : 'PENDING';
    $statusColor = $isPaid ? '#059669' : '#d97706';
    $docTitle    = $isPaid ? 'Payment Receipt' : 'Monthly Invoice';
    $idLabel     = $isPaid ? 'TRX-ID'          : 'INV-ID';
    $totalLabel  = $isPaid ? 'Total Amount Paid' : 'Total Amount Due';
    $stamp       = $isPaid
        ? '<div class="stamp">PAID IN FULL</div>'
        : '';

    // Filter out zero-amount items
    $items = array_filter($bill['items'] ?? [], function ($i) {
        return floatval($i['amount'] ?? 0) > 0;
    });

    $itemRows = '';
    foreach ($items as $item) {
        $name   = htmlspecialchars($item['item_name'] ?? $item['description'] ?? 'Item');
        $amount = number_format(floatval($item['amount']), 2);
        $itemRows .= "<tr><td>{$name}</td><td class=\"amount\">&#2547; {$amount}</td></tr>";
    }

    $subtotal    = number_format(floatval($bill['subtotal']     ?? 0), 2);
    $discount    = number_format(floatval($bill['discount']     ?? 0), 2);
    $tax         = number_format(floatval($bill['tax']          ?? 0), 2);
    $total       = number_format(floatval($bill['total_amount'] ?? 0), 2);
    $billNumber  = htmlspecialchars($bill['bill_number']  ?? '');
    $name        = htmlspecialchars($bill['full_name']    ?? '');
    $apt         = htmlspecialchars($bill['apt_number']   ?? 'N/A');
    $period      = htmlspecialchars(($bill['month'] ?? '') . ' ' . ($bill['year'] ?? ''));
    $due         = htmlspecialchars($bill['due_date']     ?? '');
    $year        = date('Y');

    // Summary rows — only show discount/tax if non-zero
    $summaryRows = "<tr><td class=\"label\">Subtotal</td><td class=\"value\">&#2547; {$subtotal}</td></tr>";
    if (floatval($bill['discount'] ?? 0) > 0) {
        $summaryRows .= "<tr><td class=\"label\" style=\"color:#e11d48;\">Discount</td><td class=\"value\" style=\"color:#e11d48;\">- &#2547; {$discount}</td></tr>";
    }
    if (floatval($bill['tax'] ?? 0) > 0) {
        $summaryRows .= "<tr><td class=\"label\">Tax / VAT</td><td class=\"value\">&#2547; {$tax}</td></tr>";
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{$docTitle}</title>
<style>
body{font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;color:#1e293b;background:#fff;margin:0;padding:40px 50px;font-size:13px;line-height:1.5;}
.accent-bar{height:6px;background-color:{$accentColor};width:100%;margin-bottom:40px;}
.header-table{width:100%;margin-bottom:50px;border-collapse:collapse;}
.header-left{width:50%;vertical-align:middle;}
.header-right{width:50%;text-align:right;vertical-align:top;}
.logo-text{font-size:32px;font-weight:800;color:#0f172a;margin:0;letter-spacing:-1px;line-height:1;}
.logo-text span{color:#059669;}
.logo-subtext{font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:2px;margin-top:5px;}
.doc-title{font-size:24px;font-weight:300;color:#0f172a;margin:0 0 10px;text-transform:uppercase;letter-spacing:4px;}
.doc-meta{font-size:12px;color:#64748b;margin:3px 0;}
.doc-meta strong{color:#334155;font-weight:600;}
.info-table{width:100%;margin-bottom:50px;border-collapse:collapse;}
.info-cell{width:50%;vertical-align:top;}
.info-box{background-color:#f8fafc;border-left:4px solid #cbd5e1;padding:15px 20px;}
.info-box.highlight{background-color:#ecfdf5;border-left-color:{$accentColor};}
.info-label{font-size:10px;text-transform:uppercase;color:#64748b;letter-spacing:1px;margin:0 0 5px;font-weight:bold;}
.info-value{font-size:15px;color:#0f172a;margin:0;font-weight:bold;}
.info-subvalue{font-size:13px;color:#475569;margin:4px 0 0;}
.items-table{width:100%;border-collapse:collapse;margin-bottom:40px;}
.items-table th{background-color:#f1f5f9;padding:12px 15px;text-align:left;font-size:10px;text-transform:uppercase;color:#475569;letter-spacing:1.5px;border-top:1px solid #e2e8f0;border-bottom:2px solid #cbd5e1;}
.items-table td{padding:16px 15px;border-bottom:1px solid #e2e8f0;color:#334155;font-size:14px;}
.items-table td.amount,.items-table th.amount{text-align:right;}
.items-table td.amount{font-family:'Courier New',Courier,monospace;font-weight:600;font-size:14px;color:#0f172a;}
.summary-container{width:100%;border-collapse:collapse;}
.summary-spacer{width:50%;}
.summary-content{width:50%;vertical-align:top;}
.summary-table{width:100%;border-collapse:collapse;}
.summary-table td{padding:8px 15px;font-size:13px;}
.summary-table td.label{text-align:left;color:#64748b;font-weight:bold;}
.summary-table td.value{text-align:right;font-family:'Courier New',Courier,monospace;font-weight:600;color:#0f172a;}
.summary-table tr.total-row td{background-color:#f8fafc;color:#0f172a;font-size:16px;font-weight:bold;padding:15px;border-top:2px solid {$accentColor};border-bottom:1px solid #e2e8f0;}
.summary-table tr.total-row td.value{color:{$accentColor};font-size:18px;}
.footer{margin-top:80px;text-align:center;font-size:11px;color:#94a3b8;border-top:1px dashed #cbd5e1;padding-top:30px;}
.stamp{position:absolute;top:250px;right:50px;border:3px solid #10b981;color:#10b981;font-size:22px;font-weight:bold;padding:10px 20px;text-transform:uppercase;letter-spacing:4px;transform:rotate(-15deg);opacity:0.15;border-radius:8px;}
</style>
</head>
<body>

<div class="accent-bar"></div>
{$stamp}

<table class="header-table">
  <tr>
    <td class="header-left">
      <h1 class="logo-text">Ni<span>bash</span></h1>
      <p class="logo-subtext">Property Management Systems</p>
    </td>
    <td class="header-right">
      <h2 class="doc-title">{$docTitle}</h2>
      <p class="doc-meta"><strong>{$idLabel}:</strong> {$billNumber}</p>
      <p class="doc-meta"><strong>Due Date:</strong> {$due}</p>
    </td>
  </tr>
</table>

<table class="info-table">
  <tr>
    <td class="info-cell" style="padding-right:15px;">
      <div class="info-box">
        <p class="info-label">Billed To</p>
        <p class="info-value">{$name}</p>
        <p class="info-subvalue">Apt / Flat: {$apt}</p>
      </div>
    </td>
    <td class="info-cell" style="padding-left:15px;">
      <div class="info-box highlight">
        <p class="info-label">Billing Cycle</p>
        <p class="info-value">{$period}</p>
        <p class="info-subvalue">Status: <span style="color:{$statusColor};font-weight:bold;">{$statusLabel}</span></p>
      </div>
    </td>
  </tr>
</table>

<table class="items-table">
  <thead>
    <tr>
      <th>Charge Description</th>
      <th class="amount">Amount</th>
    </tr>
  </thead>
  <tbody>
    {$itemRows}
  </tbody>
</table>

<table class="summary-container">
  <tr>
    <td class="summary-spacer"></td>
    <td class="summary-content">
      <table class="summary-table">
        {$summaryRows}
        <tr class="total-row">
          <td class="label">{$totalLabel}</td>
          <td class="value">&#2547; {$total}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<div class="footer">
  <p>Thank you for your prompt payment. This document is computer-generated and requires no physical signature.</p>
  <p>&copy; {$year} Smart Nibash Systems. All rights reserved.</p>
</div>

</body>
</html>
HTML;
}

// ── Internal: generate PDF bytes via Dompdf ───────────────────────────────────
function _nibash_generate_pdf(array $bill, string $status): string
{
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', false);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml(_nibash_pdf_html($bill, $status));
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

// ── Internal: configure PHPMailer ─────────────────────────────────────────────
function _nibash_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = NIBASH_SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = NIBASH_SMTP_USER;
    $mail->Password   = NIBASH_SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = NIBASH_SMTP_PORT;
    $mail->setFrom(NIBASH_SMTP_USER, NIBASH_FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}

// ── Internal: simple plain-text email body ────────────────────────────────────
function _nibash_email_body(array $bill, string $status): string
{
    $name   = $bill['full_name'] ?? 'Resident';
    $isPaid = ($status === 'Paid');

    if ($isPaid) {
        return "Hello {$name},\n\nYour payment has been confirmed. Please check the attachment below for your receipt.\n\nThank you,\nNibash";
    }

    return "Hello {$name},\n\nYour invoice is ready. Please check the attachment below.\n\nTo pay your bill, visit the billing page on your Nibash dashboard.\n\nThank you,\nNibash";
}

// ═══════════════════════════════════════════════════════════════════════════════
// PUBLIC FUNCTIONS
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Send a Pending invoice email with PDF attachment to a resident.
 */
function send_invoice_email(string $toEmail, string $toName, array $bill): bool
{
    try {
        $pdf      = _nibash_generate_pdf($bill, 'Pending');
        $filename = 'Invoice-' . ($bill['bill_number'] ?? 'Nibash') . '.pdf';

        $mail = _nibash_mailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = "New Invoice {$bill['bill_number']} — Nibash";
        $mail->Body    = _nibash_email_body($bill, 'Pending');
        $mail->addStringAttachment($pdf, $filename, PHPMailer::ENCODING_BASE64, 'application/pdf');
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('[Nibash Mailer] Invoice email failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send a Paid receipt email with PDF attachment to a resident.
 */
function send_receipt_email(string $toEmail, string $toName, array $bill): bool
{
    try {
        $pdf      = _nibash_generate_pdf($bill, 'Paid');
        $filename = 'Receipt-' . ($bill['bill_number'] ?? 'Nibash') . '.pdf';

        $mail = _nibash_mailer();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = "Payment Confirmed — {$bill['bill_number']} (PAID) — Nibash";
        $mail->Body    = _nibash_email_body($bill, 'Paid');
        $mail->addStringAttachment($pdf, $filename, PHPMailer::ENCODING_BASE64, 'application/pdf');
        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('[Nibash Mailer] Receipt email failed: ' . $e->getMessage());
        return false;
    }
}
