<?php
session_start();
require_once '../includes/db_config.php';
require_once '../includes/mailer_helper.php';
header('Content-Type: application/json');
mysqli_report(MYSQLI_REPORT_ERROR);

// Verify authentication and authorization
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Automatically update Overdue status (good to do on any billing request)
mysqli_query($conn, "UPDATE bills SET status = 'Overdue' WHERE due_date < CURDATE() AND status IN ('Pending', 'Partially Paid')");

// Determine the action (can come from GET, POST, or raw JSON body)
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$raw_data = json_decode(file_get_contents('php://input'), true);
if (!$action && $raw_data && isset($raw_data['action'])) {
    $action = $raw_data['action'];
}

if (!$action) {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
    exit();
}

$user_id = $_SESSION['user_id'];

// ── Building Isolation Context ───────────────────────────────────────────
$building_id = '';
$building_q = mysqli_query($conn, "SELECT a.building_id 
                                   FROM apartment_assignments aa 
                                   JOIN apartments a ON a.id = aa.apt_id 
                                   WHERE aa.user_id = '$user_id' AND aa.is_active = 1 
                                   LIMIT 1");

if ($building_q && mysqli_num_rows($building_q) > 0) {
    $building_id = mysqli_fetch_assoc($building_q)['building_id'];
}

if (empty($building_id)) {
    echo json_encode(['success' => false, 'message' => 'Your account is not assigned to any building. Contact Admin.']);
    exit();
}
$safe_building_id = mysqli_real_escape_string($conn, $building_id);

try {
    switch ($action) {
        
        // ---------------------------------------------------------
        // GET ANALYTICS
        // ---------------------------------------------------------
        case 'get_analytics':
            $current_month = date('F');
            $current_year = date('Y');

            $outstanding_q = mysqli_query($conn, "SELECT SUM(total_amount - paid_amount) as total FROM bills b JOIN apartments a ON b.apt_id = a.id WHERE b.status IN ('Pending', 'Partially Paid', 'Overdue') AND a.building_id = '$safe_building_id'");
            $outstanding = mysqli_fetch_assoc($outstanding_q)['total'] ?? 0;

            $last_month_name = date('F', strtotime('-1 month'));
            $last_month_year = date('Y', strtotime('-1 month'));
            $lm_outstanding_q = mysqli_query($conn, "SELECT SUM(total_amount - paid_amount) as total FROM bills b JOIN apartments a ON b.apt_id = a.id WHERE b.status IN ('Pending', 'Partially Paid', 'Overdue') AND (b.month = '$last_month_name' AND b.year = '$last_month_year') AND a.building_id = '$safe_building_id'");
            $lm_outstanding = mysqli_fetch_assoc($lm_outstanding_q)['total'] ?? 0;

            $cm_total_billed_q = mysqli_query($conn, "SELECT SUM(total_amount) as total FROM bills b JOIN apartments a ON b.apt_id = a.id WHERE b.month = '$current_month' AND b.year = '$current_year' AND b.status != 'Draft' AND a.building_id = '$safe_building_id'");
            $cm_total_billed = mysqli_fetch_assoc($cm_total_billed_q)['total'] ?? 0;

            $cm_total_collected_q = mysqli_query($conn, "SELECT SUM(paid_amount) as total FROM bills b JOIN apartments a ON b.apt_id = a.id WHERE b.month = '$current_month' AND b.year = '$current_year' AND b.status != 'Draft' AND a.building_id = '$safe_building_id'");
            $cm_total_collected = mysqli_fetch_assoc($cm_total_collected_q)['total'] ?? 0;

            $collection_rate = ($cm_total_billed > 0) ? round(($cm_total_collected / $cm_total_billed) * 100, 1) : 0;

            $bills_generated_q = mysqli_query($conn, "SELECT COUNT(*) as count FROM bills b JOIN apartments a ON b.apt_id = a.id WHERE b.month = '$current_month' AND b.year = '$current_year' AND a.building_id = '$safe_building_id'");
            $bills_generated = mysqli_fetch_assoc($bills_generated_q)['count'] ?? 0;

            $overdue_q = mysqli_query($conn, "SELECT SUM(total_amount - paid_amount) as total FROM bills b JOIN apartments a ON b.apt_id = a.id WHERE b.status = 'Overdue' AND a.building_id = '$safe_building_id'");
            $overdue = mysqli_fetch_assoc($overdue_q)['total'] ?? 0;

            $chart_data = [];
            $chart_labels = [];
            for ($i = 5; $i >= 0; $i--) {
                $m_name = date('F', strtotime("-$i month"));
                $y_num = date('Y', strtotime("-$i month"));
                $m_collected_q = mysqli_query($conn, "SELECT SUM(p.amount_paid) as total FROM payments p JOIN bills b ON p.bill_id = b.id JOIN apartments a ON b.apt_id = a.id WHERE b.month = '$m_name' AND b.year = '$y_num' AND a.building_id = '$safe_building_id'");
                $chart_labels[] = date('M', strtotime("-$i month"));
                $chart_data[] = mysqli_fetch_assoc($m_collected_q)['total'] ?? 0;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'outstanding' => (float)$outstanding,
                    'last_month_outstanding' => (float)$lm_outstanding,
                    'collection_rate' => $collection_rate,
                    'bills_generated' => (int)$bills_generated,
                    'overdue' => (float)$overdue,
                    'chart' => ['labels' => $chart_labels, 'data' => $chart_data]
                ]
            ]);
            break;

        // ---------------------------------------------------------
        // GET BILLS LIST
        // ---------------------------------------------------------
        case 'get_bills':
            $search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
            $status = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : 'All';
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = ($page - 1) * $limit;

            $where_clauses = ["a.building_id = '$safe_building_id'"];
            if (!empty($search)) {
                $where_clauses[] = "(u.id LIKE '%$search%' OR p.full_name LIKE '%$search%' OR b.bill_number LIKE '%$search%' OR a.apt_number LIKE '%$search%')";
            }
            if ($status !== 'All') {
                $where_clauses[] = "b.status = '$status'";
            }
            $where_sql = implode(' AND ', $where_clauses);

            $count_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM bills b JOIN users u ON b.resident_id = u.id JOIN user_profiles p ON u.id = p.user_id JOIN apartments a ON b.apt_id = a.id WHERE $where_sql");
            $total_rows = mysqli_fetch_assoc($count_q)['total'];
            $total_pages = ceil($total_rows / $limit);

            $query = "SELECT b.*, p.full_name, a.apt_number FROM bills b JOIN users u ON b.resident_id = u.id JOIN user_profiles p ON u.id = p.user_id JOIN apartments a ON b.apt_id = a.id WHERE $where_sql ORDER BY b.id DESC LIMIT $limit OFFSET $offset";
            $bills_q = mysqli_query($conn, $query);
            $bills = [];
            while ($row = mysqli_fetch_assoc($bills_q)) {
                $bills[] = [
                    'id' => $row['id'],
                    'bill_number' => $row['bill_number'] ?: 'INV-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
                    'resident_name' => $row['full_name'],
                    'apt_number' => $row['apt_number'] ?: 'N/A',
                    'period' => $row['month'] . ' ' . $row['year'],
                    'total_amount' => (float)$row['total_amount'],
                    'paid_amount' => (float)$row['paid_amount'],
                    'status' => $row['status'],
                    'due_date' => $row['due_date']
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $bills,
                'pagination' => ['current_page' => $page, 'total_pages' => $total_pages, 'total_records' => $total_rows]
            ]);
            break;

        // ---------------------------------------------------------
        // GET BILL DETAILS
        // ---------------------------------------------------------
        case 'get_bill_details':
            $bill_id = mysqli_real_escape_string($conn, $_GET['bill_id'] ?? '');
            if (!$bill_id) throw new Exception("Bill ID required");

            $bill_q = mysqli_query($conn, "SELECT b.*, p.full_name, a.apt_number FROM bills b JOIN user_profiles p ON b.resident_id = p.user_id LEFT JOIN apartments a ON b.apt_id = a.id WHERE b.id = '$bill_id'");
            if ($bill = mysqli_fetch_assoc($bill_q)) {
                $items_q = mysqli_query($conn, "SELECT * FROM bill_items WHERE bill_id = '$bill_id'");
                $items = [];
                while ($item = mysqli_fetch_assoc($items_q)) {
                    $items[] = [
                        'utility_type_id' => $item['utility_type_id'],
                        'description' => $item['item_name'],
                        'quantity' => (float)$item['quantity'],
                        'unit_price' => (float)$item['unit_price'],
                        'amount' => (float)$item['amount']
                    ];
                }
                echo json_encode([
                    'success' => true,
                    'bill' => array_merge($bill, [
                        'bill_number' => $bill['bill_number'] ?: 'INV-' . str_pad($bill['id'], 5, '0', STR_PAD_LEFT),
                        'month_name' => $bill['month'],
                        'items' => $items
                    ])
                ]);
            } else {
                throw new Exception("Bill not found");
            }
            break;

        // ---------------------------------------------------------
        // CREATE / UPDATE BILL
        // ---------------------------------------------------------
        case 'save_bill':
            $data = $raw_data ?: $_POST;
            $bill_id = !empty($data['id']) ? mysqli_real_escape_string($conn, $data['id']) : null;
            $resident_id = mysqli_real_escape_string($conn, $data['resident_id']);
            $apt_id = !empty($data['apt_id']) ? "'" . mysqli_real_escape_string($conn, $data['apt_id']) . "'" : "NULL";
            
            $bill_month_val = $data['bill_month']; 
            list($year, $month_num) = explode('-', $bill_month_val);
            $month_name = date("F", mktime(0, 0, 0, $month_num, 10));

            $due_date = mysqli_real_escape_string($conn, $data['due_date']);
            $status = isset($data['status']) && $data['status'] === 'Draft' ? 'Draft' : 'Pending';
            $notes = isset($data['notes']) ? mysqli_real_escape_string($conn, $data['notes']) : '';
            $discount = isset($data['discount']) ? floatval($data['discount']) : 0;
            $tax_percent = isset($data['tax']) ? floatval($data['tax']) : 0;

            $items = $data['items'] ?? [];
            $subtotal = 0;
            foreach ($items as $item) {
                $qty = floatval($item['quantity'] ?? 1);
                $rate = floatval($item['unit_price'] ?? $item['amount'] ?? 0);
                $subtotal += ($qty * $rate);
            }
            $tax_amount = ($subtotal - $discount) * ($tax_percent / 100);
            $total_amount = ($subtotal - $discount) + $tax_amount;

            try {
                mysqli_begin_transaction($conn);
                
                $creator = $_SESSION['user_id'];
                $is_new = false;

                if ($bill_id) {
                    $curr_q = mysqli_query($conn, "SELECT status, bill_number FROM bills WHERE id='$bill_id'");
                    $curr = mysqli_fetch_assoc($curr_q);
                    
                    $new_status = isset($data['status']) ? mysqli_real_escape_string($conn, $data['status']) : $curr['status'];
                    
                    mysqli_query($conn, "UPDATE bills SET apt_id=$apt_id, resident_id='$resident_id', month='$month_name', year='$year', due_date='$due_date', subtotal='$subtotal', discount='$discount', tax='$tax_amount', notes='$notes', status='$new_status' WHERE id='$bill_id'");
                    mysqli_query($conn, "DELETE FROM bill_items WHERE bill_id = '$bill_id'");
                    
                    if ($curr['status'] === 'Draft' && $new_status === 'Pending') {
                        $msg = "A new invoice ({$curr['bill_number']}) for $month_name $year totaling $" . number_format($total_amount, 2) . " has been issued. Due on " . date('M d, Y', strtotime($due_date)) . ".";
                        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link, is_read) VALUES ('$resident_id', 'New Invoice Issued', '$msg', 'resident/billing.php', 0)");
                    }
                } else {
                    $is_new = true;
                    $issue_date = date('Y-m-d');
                    mysqli_query($conn, "INSERT INTO bills (apt_id, resident_id, month, year, issue_date, due_date, subtotal, discount, tax, status, notes, created_by) VALUES ($apt_id, '$resident_id', '$month_name', '$year', '$issue_date', '$due_date', '$subtotal', '$discount', '$tax_amount', '$status', '$notes', '$creator')");
                    $bill_id = mysqli_insert_id($conn);
                    $bill_number = 'INV-' . str_pad($bill_id, 5, '0', STR_PAD_LEFT);
                    mysqli_query($conn, "UPDATE bills SET bill_number = '$bill_number' WHERE id = '$bill_id'");

                    if ($status === 'Pending') {
                        $msg = "A new invoice ($bill_number) for $month_name $year totaling $" . number_format($total_amount, 2) . " has been issued. Due on " . date('M d, Y', strtotime($due_date)) . ".";
                        mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link, is_read) VALUES ('$resident_id', 'New Invoice Issued', '$msg', 'resident/billing.php', 0)");
                    }
                }

                foreach ($items as $item) {
                    $utility_type_id = !empty($item['utility_type_id']) ? "'" . mysqli_real_escape_string($conn, $item['utility_type_id']) . "'" : "NULL";
                    $qty = floatval($item['quantity'] ?? 1);
                    $rate = floatval($item['unit_price'] ?? $item['amount'] ?? 0);
                    $amount = $qty * $rate;
                    $desc = mysqli_real_escape_string($conn, $item['description'] ?? '');
                    
                    if ($utility_type_id !== "NULL" && empty($desc)) {
                        $util_q = mysqli_query($conn, "SELECT utility_name FROM utility_types WHERE id=$utility_type_id");
                        if($util_q && $ur = mysqli_fetch_assoc($util_q)) $desc = $ur['utility_name'];
                    }
                    if (empty($desc)) $desc = "Custom Item";
                    
                    mysqli_query($conn, "INSERT INTO bill_items (bill_id, utility_type_id, item_name, quantity, unit_price, amount) VALUES ('$bill_id', $utility_type_id, '$desc', '$qty', '$rate', '$amount')");
                }

                mysqli_commit($conn);

                // ── Send invoice email to resident when status is Pending ──────────
                $should_email = ($status === 'Pending') || (!$is_new && isset($curr) && $curr['status'] === 'Draft' && $new_status === 'Pending');
                if ($should_email) {
                    // Fetch resident email + full bill data for the email
                    $email_apt_id = intval($data['apt_id'] ?? 0);
                    $email_q = mysqli_query($conn, "SELECT u.email, p.full_name, a.apt_number FROM users u JOIN user_profiles p ON u.id = p.user_id LEFT JOIN apartments a ON a.id = '$email_apt_id' WHERE u.id = '$resident_id' LIMIT 1");
                    if ($email_q && $email_row = mysqli_fetch_assoc($email_q)) {
                        $items_for_email_q = mysqli_query($conn, "SELECT item_name, quantity, unit_price, amount FROM bill_items WHERE bill_id = '$bill_id'");
                        $items_for_email = [];
                        while ($eitem = mysqli_fetch_assoc($items_for_email_q)) { $items_for_email[] = $eitem; }
                        $actual_bill_number = $is_new 
                            ? ($bill_number ?? 'INV-' . str_pad($bill_id, 5, '0', STR_PAD_LEFT))
                            : ($curr['bill_number'] ?? 'INV-' . str_pad($bill_id, 5, '0', STR_PAD_LEFT));
                        send_invoice_email(
                            $email_row['email'],
                            $email_row['full_name'],
                            [
                                'bill_number'  => $actual_bill_number,
                                'full_name'    => $email_row['full_name'],
                                'apt_number'   => $email_row['apt_number'] ?? 'N/A',
                                'month'        => $month_name,
                                'year'         => $year,
                                'due_date'     => $due_date,
                                'items'        => $items_for_email,
                                'subtotal'     => $subtotal,
                                'discount'     => $discount,
                                'tax'          => $tax_amount,
                                'total_amount' => $total_amount,
                            ]
                        );
                    }
                }

                echo json_encode(['success' => true, 'message' => $is_new ? "Invoice created successfully." : "Invoice updated successfully."]);
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
            }


            break;

        // ---------------------------------------------------------
        // RECORD PAYMENT
        // ---------------------------------------------------------
        case 'record_payment':
            $data = $raw_data ?: $_POST;
            if (empty($data['bill_id']) || empty($data['amount_paid'])) throw new Exception("Missing required fields");

            $bill_id = (int)$data['bill_id'];
            $amount_paid = floatval($data['amount_paid']);
            $payment_method = mysqli_real_escape_string($conn, $data['payment_method'] ?? 'Cash');
            $transaction_id = mysqli_real_escape_string($conn, $data['transaction_id'] ?? '');
            $notes = mysqli_real_escape_string($conn, $data['notes'] ?? '');

            mysqli_begin_transaction($conn);
            $bill_q = mysqli_query($conn, "SELECT total_amount, paid_amount, status, resident_id, bill_number FROM bills WHERE id='$bill_id'");
            $bill = mysqli_fetch_assoc($bill_q);
            if (!$bill) throw new Exception("Bill not found.");

            $new_paid_total = $bill['paid_amount'] + $amount_paid;
            $new_status = 'Partially Paid';
            if ($new_paid_total >= ($bill['total_amount'] - 0.01)) {
                $new_status = 'Paid';
            }

            mysqli_query($conn, "INSERT INTO payments (bill_id, amount_paid, payment_method, transaction_id, notes) VALUES ('$bill_id', '$amount_paid', '$payment_method', '$transaction_id', '$notes')");
            mysqli_query($conn, "UPDATE bills SET paid_amount = '$new_paid_total', status = '$new_status' WHERE id = '$bill_id'");
            
            $msg = "A payment of Tk " . number_format($amount_paid, 2) . " has been received for Invoice {$bill['bill_number']}. Status: $new_status.";
            mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, link, is_read) VALUES ('{$bill['resident_id']}', 'Payment Received', '$msg', 'resident/billing.php', 0)");

            mysqli_commit($conn);

            // ── Send Paid receipt email when bill is fully settled ───────────────
            if ($new_status === 'Paid') {
                $receipt_q = mysqli_query($conn, "SELECT u.email, p.full_name, b.month, b.year, b.due_date, b.subtotal, b.discount, b.tax, b.total_amount, b.bill_number, a.apt_number FROM users u JOIN user_profiles p ON u.id = p.user_id JOIN bills b ON b.id = '$bill_id' LEFT JOIN apartments a ON a.id = b.apt_id WHERE u.id = '{$bill['resident_id']}' LIMIT 1");
                if ($receipt_q && $receipt_row = mysqli_fetch_assoc($receipt_q)) {
                    $receipt_items_q = mysqli_query($conn, "SELECT item_name, quantity, unit_price, amount FROM bill_items WHERE bill_id = '$bill_id'");
                    $receipt_items = [];
                    while ($ri = mysqli_fetch_assoc($receipt_items_q)) { $receipt_items[] = $ri; }
                    send_receipt_email(
                        $receipt_row['email'],
                        $receipt_row['full_name'],
                        array_merge($receipt_row, ['items' => $receipt_items])
                    );
                }
            }

            echo json_encode(['success' => true, 'message' => "Payment of Tk " . number_format($amount_paid, 2) . " recorded successfully."]);
            break;

        // ---------------------------------------------------------
        // DELETE BILL
        // ---------------------------------------------------------
        case 'delete_bill':
            $data = $raw_data ?: $_POST;
            $bill_id = mysqli_real_escape_string($conn, $data['bill_id'] ?? '');
            if (!$bill_id) throw new Exception("Bill ID required");

            mysqli_query($conn, "DELETE FROM bill_items WHERE bill_id = '$bill_id'");
            mysqli_query($conn, "DELETE FROM bills WHERE id = '$bill_id'");
            echo json_encode(['success' => true, 'message' => 'Invoice deleted successfully']);
            break;

        default:
            throw new Exception("Unknown action: " . $action);
    }
} catch (Exception $e) {
    if (isset($conn) && mysqli_ping($conn)) mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
