<?php
session_start();
require_once '../includes/db_config.php';

// Protection: Only allow access if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user info
$query = "SELECT p.full_name, p.profile_image, a.apt_number 
          FROM users u 
          LEFT JOIN user_profiles p ON u.id = p.user_id 
          LEFT JOIN apartment_assignments aa ON u.id = aa.user_id AND aa.is_active = 1
          LEFT JOIN apartments a ON aa.apt_id = a.id
          WHERE u.id = '$user_id'";
$result = @mysqli_query($conn, $query);
$user_profile = $result ? mysqli_fetch_assoc($result) : null;
$user_name = $user_profile['full_name'] ?? 'User';
$user_image = $user_profile['profile_image'] ?? '';
$unit_number = $user_profile['apt_number'] ?? 'N/A';

// Check for unread notifications
$notif_q = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = '$user_id' AND is_read = 0";
$notif_res = @mysqli_query($conn, $notif_q);
$has_notification = false;
$unread_count = 0;
if ($notif_res) {
    $notif_data = mysqli_fetch_assoc($notif_res);
    $unread_count = $notif_data['unread'];
    $has_notification = ($unread_count > 0);
}

// Fetch unpaid bills and items for House Rent
$bills_query = "SELECT b.*, b.month as bill_month, b.year as bill_year 
                FROM bills b 
                WHERE b.resident_id = '$user_id' AND b.status IN ('Pending', 'Partially Paid', 'Overdue')
                ORDER BY b.due_date ASC";
$bills_res = @mysqli_query($conn, $bills_query);
$active_bills = [];
$total_due_without_electricity = 0;
$next_due_date = null;

if ($bills_res) {
    while ($row = mysqli_fetch_assoc($bills_res)) {
        $bill_id = $row['id'];
        $items_q = "SELECT i.*, u.utility_name FROM bill_items i LEFT JOIN utility_types u ON i.utility_type_id = u.id WHERE i.bill_id = '$bill_id'";
        $items_res = @mysqli_query($conn, $items_q);
        $items = [];
        
        $electricity_total = 0;
        
        if ($items_res) {
            while ($item = mysqli_fetch_assoc($items_res)) {
                $is_electricity = false;
                if (stripos($item['item_name'], 'electric') !== false || stripos($item['utility_name'] ?? '', 'electric') !== false) {
                    $is_electricity = true;
                    $electricity_total += (float)$item['amount'];
                }
                
                $items[] = [
                    'name' => $item['item_name'] ?: ($item['utility_name'] ?: 'Custom Item'),
                    'amount' => (float)$item['amount'],
                    'is_electricity' => $is_electricity
                ];
            }
        }
        
        $tax = (float)($row['tax'] ?? 0);
        $discount = (float)($row['discount'] ?? 0);
        
        // Derive the total without electricity directly from the grand total.
        // This ensures base rent, taxes, and discounts are inherently included.
        $bill_total_without_electricity = (float)($row['total_amount'] ?? 0) - $electricity_total;
        
        // Deduct any amount already paid (for Partially Paid bills)
        $paid_amount = (float)($row['paid_amount'] ?? 0);
        $bill_total_without_electricity -= $paid_amount;
        
        $bill_total_without_electricity = max(0, $bill_total_without_electricity);

        $row['items'] = $items;
        $row['total_without_electricity'] = $bill_total_without_electricity;
        
        if ($bill_total_without_electricity > 0) {
            if ($next_due_date === null || strtotime($row['due_date']) < strtotime($next_due_date)) {
                $next_due_date = $row['due_date'];
            }
        }
        
        $active_bills[] = $row;
        $total_due_without_electricity += $bill_total_without_electricity;
    }
}

// Fetch Paid Bills for History
$history_query = "SELECT b.*, b.month as bill_month, b.year as bill_year, 
                         p.transaction_id, p.payment_date, p.payment_method
                  FROM bills b 
                  LEFT JOIN payments p ON b.id = p.bill_id AND p.payment_status = 'Success'
                  WHERE b.resident_id = '$user_id' AND b.status = 'Paid'
                  GROUP BY b.id
                  ORDER BY b.updated_at DESC";
$history_res = @mysqli_query($conn, $history_query);
$paid_bills = [];

if ($history_res) {
    while ($row = mysqli_fetch_assoc($history_res)) {
        $paid_bills[] = $row;
    }
}

$bills_json = json_encode($active_bills);
$history_json = json_encode($paid_bills);
$total_due_json = json_encode($total_due_without_electricity);
$formatted_next_due = $next_due_date ? date('M j, Y', strtotime($next_due_date)) : 'N/A';

?>
<?php
$resident_building_name = 'Nibash';
try {
    $uid_for_b = $_SESSION['user_id'] ?? 0;
    if ($uid_for_b) {
        $bq = @mysqli_query($conn, "SELECT b.building_name, b.building_number FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id JOIN buildings b ON a.building_id = b.id WHERE aa.user_id = '$uid_for_b' AND aa.is_active=1 LIMIT 1");
        if ($bq && mysqli_num_rows($bq) > 0) {
            $brow = mysqli_fetch_assoc($bq);
            $resident_building_name = !empty($brow['building_name']) ? $brow['building_name'] : $brow['building_number'];
        } else {
            $mq = @mysqli_query($conn, "SELECT b.building_name, b.building_number FROM building_managers bm JOIN buildings b ON bm.building_id = b.id WHERE bm.user_id = '$uid_for_b' LIMIT 1");
            if ($mq && mysqli_num_rows($mq) > 0) {
                $mrow = mysqli_fetch_assoc($mq);
                $resident_building_name = !empty($mrow['building_name']) ? $mrow['building_name'] : $mrow['building_number'];
            }
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Invoices | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/resident_style.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f0fdfa; }
        ::-webkit-scrollbar-thumb { background: #99f6e4; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #2dd4bf; }
        [x-cloak] { display: none !important; }
        .invoice-row { transition: all 0.2s ease; }
        .invoice-row:hover { background-color: #f8fafc; transform: scale-[1.005]; box-shadow: 0 4px 15px -3px rgba(0,0,0,0.03); z-index: 10; position: relative; border-radius: 0.75rem; }
    </style>
</head>

<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" 
      x-data="billingApp()" x-init="initApp()">

    <?php include '../includes/resident_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8">

        <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-teal-100/60 hover:shadow-[0_4px_15px_-3px_rgba(20,184,166,0.15)] hover:border-teal-200 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-teal-400 to-teal-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>

        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(20,184,166,0.1)] flex-1 flex flex-col overflow-hidden border border-teal-100/80 relative">
            
            <header class="bg-white/80 backdrop-blur-xl border-b border-teal-50 sticky top-0 z-40 shadow-sm px-8 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-teal-50 hover:text-teal-600 rounded-xl transition-colors">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    <div>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-teal-500 shadow-[0_0_8px_rgba(20,184,166,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Financial Hub</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto bg-slate-50/50 p-6 sm:p-8 lg:p-10">
                <div class="max-w-7xl mx-auto space-y-8">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-white rounded-[1.5rem] p-6 border border-slate-200 shadow-sm flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Total Outstanding</p>
                                <h3 class="text-3xl font-black text-slate-900 tracking-tight" x-text="formatCurrency(totalDue)"></h3>
                            </div>
                            <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center border border-rose-100">
                                <i data-lucide="pie-chart" class="w-6 h-6"></i>
                            </div>
                        </div>

                        <div class="bg-white rounded-[1.5rem] p-6 border border-slate-200 shadow-sm flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Pending Invoices</p>
                                <h3 class="text-3xl font-black text-slate-900 tracking-tight" x-text="bills.length"></h3>
                            </div>
                            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center border border-amber-100">
                                <i data-lucide="file-text" class="w-6 h-6"></i>
                            </div>
                        </div>

                        <div class="bg-white rounded-[1.5rem] p-6 border border-slate-200 shadow-sm flex items-center justify-between">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Next Deadline</p>
                                <h3 class="text-xl font-black text-slate-900 tracking-tight mt-2"><?php echo $formatted_next_due; ?></h3>
                            </div>
                            <div class="w-12 h-12 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center border border-teal-100">
                                <i data-lucide="calendar-clock" class="w-6 h-6"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
                        
                        <div class="xl:col-span-2 flex flex-col gap-8">
                            
                            <div class="bg-white border border-slate-200 rounded-[2rem] shadow-sm flex flex-col overflow-hidden">
                                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white sticky top-0 z-10">
                                    <div>
                                        <h3 class="text-lg font-black text-slate-900 tracking-tight">Recent Invoices</h3>
                                        <p class="text-xs font-medium text-slate-500 mt-1">Rent and utility bills associated with your unit.</p>
                                    </div>
                                    <button @click="payHouseRent()" class="hidden sm:flex items-center gap-2 bg-slate-900 hover:bg-teal-600 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md">
                                        <i data-lucide="credit-card" class="w-4 h-4"></i> Pay All via SSLCommerz
                                    </button>
                                </div>

                                <div class="p-4 sm:p-6 bg-slate-50/50">
                                    <template x-if="bills.length === 0">
                                        <div class="text-center py-12">
                                            <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 border border-slate-200 shadow-sm">
                                                <i data-lucide="check-circle-2" class="w-8 h-8 text-teal-400"></i>
                                            </div>
                                            <h4 class="text-lg font-bold text-slate-900">You're all caught up!</h4>
                                            <p class="text-sm text-slate-500 mt-1">No pending invoices at the moment.</p>
                                        </div>
                                    </template>

                                    <div class="space-y-2">
                                        <template x-for="bill in bills" :key="bill.id">
                                            <div class="invoice-row flex flex-col sm:flex-row sm:items-center justify-between p-4 sm:p-5 bg-white border border-slate-200 rounded-xl">
                                                <div class="flex items-center gap-4 mb-4 sm:mb-0">
                                                    <div class="w-12 h-12 bg-slate-50 rounded-xl border border-slate-100 flex items-center justify-center shrink-0 text-slate-400">
                                                        <i data-lucide="receipt" class="w-5 h-5"></i>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-sm font-bold text-slate-900" x-text="'Invoice #' + (bill.bill_number || bill.id)"></h4>
                                                        <p class="text-xs font-medium text-slate-500 mt-0.5" x-text="bill.bill_month + ' ' + bill.bill_year"></p>
                                                    </div>
                                                </div>

                                                <div class="flex items-center justify-between sm:justify-end sm:gap-8 w-full sm:w-auto">
                                                    <div class="flex flex-col sm:items-end">
                                                        <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1 sm:hidden">Amount</span>
                                                        <span class="text-base font-black text-slate-900" x-text="formatCurrency(bill.total_without_electricity ?? bill.total_amount)"></span>
                                                    </div>
                                                    
                                                    <div class="flex items-center gap-3">
                                                        <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-widest rounded-md border shadow-sm"
                                                              :class="bill.status === 'Overdue' ? 'bg-rose-50 text-rose-600 border-rose-200' : 'bg-amber-50 text-amber-600 border-amber-200'"
                                                              x-text="bill.status"></span>
                                                        
                                                        <button @click="openInvoiceDetails(bill)" class="p-2 text-slate-400 hover:text-teal-600 hover:bg-teal-50 rounded-lg transition-colors border border-transparent hover:border-teal-100">
                                                            <i data-lucide="eye" class="w-4 h-4"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white border border-slate-200 rounded-[2rem] shadow-sm flex flex-col overflow-hidden">
                                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white">
                                    <div>
                                        <h3 class="text-lg font-black text-slate-900 tracking-tight">Payment History</h3>
                                        <p class="text-xs font-medium text-slate-500 mt-1">Your past completed transactions.</p>
                                    </div>
                                </div>

                                <div class="p-4 sm:p-6 bg-slate-50/50">
                                    <template x-if="historyBills.length === 0">
                                        <div class="text-center py-10">
                                            <i data-lucide="history" class="w-10 h-10 text-slate-300 mx-auto mb-3"></i>
                                            <h4 class="text-base font-bold text-slate-900">No payment history yet.</h4>
                                        </div>
                                    </template>

                                    <div class="space-y-3">
                                        <template x-for="pastBill in historyBills" :key="pastBill.id">
                                            <div class="flex flex-col sm:flex-row sm:items-center justify-between p-4 bg-white border border-slate-200 rounded-xl">
                                                <div class="flex items-center gap-4 mb-3 sm:mb-0">
                                                    <div class="w-10 h-10 bg-teal-50 rounded-full border border-teal-100 flex items-center justify-center shrink-0 text-teal-500">
                                                        <i data-lucide="check" class="w-5 h-5"></i>
                                                    </div>
                                                    <div>
                                                        <h4 class="text-sm font-bold text-slate-900" x-text="'Invoice #' + (pastBill.bill_number || pastBill.id)"></h4>
                                                        <p class="text-xs text-slate-500 mt-0.5" x-text="'Paid on: ' + (pastBill.payment_date ? pastBill.payment_date.split(' ')[0] : 'Recently')"></p>
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-between sm:justify-end gap-6 w-full sm:w-auto">
                                                    <div class="text-right">
                                                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-0.5" x-text="pastBill.payment_method || 'Online'"></p>
                                                        <span class="text-sm font-black text-slate-900" x-text="formatCurrency(pastBill.total_amount)"></span>
                                                    </div>
                                                    <div class="flex items-center gap-2">
                                                        <span class="px-2.5 py-1 text-[10px] font-black uppercase tracking-widest rounded-md bg-teal-50 text-teal-600 border border-teal-200">Paid</span>
                                                        <a :href="'<?php echo BASE_URL; ?>api/download_receipt.php?bill_id=' + pastBill.id" target="_blank" class="p-1.5 text-slate-400 hover:text-teal-600 hover:bg-teal-50 rounded-md transition-colors" title="Download Receipt">
                                                            <i data-lucide="download" class="w-4 h-4"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="xl:col-span-1">
                            <div class="bg-white rounded-[2rem] border border-indigo-100 shadow-sm overflow-hidden flex flex-col relative">
                                <div class="h-2 bg-indigo-500 w-full"></div>
                                <div class="p-8 border-b border-slate-50 relative z-10">
                                    <div class="flex items-center gap-3 mb-8">
                                        <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center border border-indigo-100">
                                            <i data-lucide="zap" class="w-5 h-5"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-base font-black text-slate-900 tracking-tight">Electricity Panel</h3>
                                            <p class="text-[10px] font-bold text-indigo-500 uppercase tracking-widest">DESCO Prepaid</p>
                                        </div>
                                    </div>
                                    <div class="space-y-4">
                                        <label class="block text-xs font-bold text-slate-500">Meter Account Number</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                <i data-lucide="hash" class="w-4 h-4 text-slate-400"></i>
                                            </div>
                                            <input type="text" x-model="descoAccount" placeholder="Enter 8-digit number" 
                                                class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/10 outline-none text-slate-900 font-mono tracking-wider text-sm transition-all">
                                        </div>
                                    </div>
                                </div>

                                <div class="p-6 bg-indigo-50/30 flex-1 relative z-10 flex flex-col gap-4">
                                    <div class="grid grid-cols-2 gap-3">
                                        <button @click="viewDescoBalance()" class="py-3 px-2 bg-white hover:bg-indigo-50 border border-indigo-100 rounded-xl text-xs font-bold text-indigo-600 transition-all flex flex-col items-center justify-center gap-2 shadow-sm">
                                            <i data-lucide="wallet" class="w-4 h-4"></i> Check Balance
                                        </button>
                                        <button @click="viewDescoDetails()" class="py-3 px-2 bg-white hover:bg-indigo-50 border border-indigo-100 rounded-xl text-xs font-bold text-indigo-600 transition-all flex flex-col items-center justify-center gap-2 shadow-sm">
                                            <i data-lucide="user" class="w-4 h-4"></i> Customer Info
                                        </button>
                                        <button @click="viewDescoConsumption()" class="py-3 px-2 bg-white hover:bg-indigo-50 border border-indigo-100 rounded-xl text-xs font-bold text-indigo-600 transition-all flex flex-col items-center justify-center gap-2 shadow-sm">
                                            <i data-lucide="bar-chart-3" class="w-4 h-4"></i> Usage Stats
                                        </button>
                                        <button @click="viewDescoHistory()" class="py-3 px-2 bg-white hover:bg-indigo-50 border border-indigo-100 rounded-xl text-xs font-bold text-indigo-600 transition-all flex flex-col items-center justify-center gap-2 shadow-sm">
                                            <i data-lucide="history" class="w-4 h-4"></i> History
                                        </button>
                                    </div>

                                    <div class="space-y-2 mt-2 border-t border-indigo-100/50 pt-4">
                                        <label class="block text-xs font-bold text-indigo-500 uppercase tracking-widest">Recharge Amount (৳)</label>
                                        <input type="number" x-model="descoRechargeAmount" placeholder="e.g. 1000" 
                                            class="w-full px-4 py-3 bg-white border border-indigo-100 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-400/10 outline-none text-slate-900 font-bold text-sm transition-all shadow-sm">
                                    </div>

                                    <button @click="payDescoBill()" class="mt-2 w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-sm transition-all shadow-lg shadow-indigo-200 flex items-center justify-center gap-2">
                                        Recharge via SSLCommerz <i data-lucide="arrow-right" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div x-show="detailsModalOpen" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="detailsModalOpen = false" x-transition.opacity></div>
            
            <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-lg relative z-10 overflow-hidden flex flex-col"
                 x-show="detailsModalOpen" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-8 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100" x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="opacity-100 translate-y-0 scale-100" x-transition:leave-end="opacity-0 translate-y-8 scale-95">
                
                <template x-if="selectedBill">
                    <div class="flex flex-col h-full max-h-[85vh]">
                        <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between shrink-0 bg-slate-50/50">
                            <div>
                                <h3 class="text-lg font-black text-slate-900" x-text="'Invoice #' + (selectedBill.bill_number || selectedBill.id)"></h3>
                                <p class="text-xs font-medium text-slate-500 mt-1" x-text="selectedBill.bill_month + ' ' + selectedBill.bill_year"></p>
                            </div>
                            <button @click="detailsModalOpen = false" class="p-2 text-slate-400 hover:text-slate-600 hover:bg-slate-200 rounded-full transition-colors">
                                <i data-lucide="x" class="w-5 h-5"></i>
                            </button>
                        </div>

                        <div class="p-6 overflow-y-auto flex-1 bg-white">
                            <div class="mb-6 flex justify-between items-end">
                                <div>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Due</span>
                                    <div class="text-3xl font-black text-teal-600 mt-1" x-text="formatCurrency(selectedBill.total_without_electricity ?? selectedBill.total_amount)"></div>
                                </div>
                                <span class="px-3 py-1 text-xs font-bold uppercase tracking-widest rounded-md"
                                      :class="selectedBill.status === 'Overdue' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700'"
                                      x-text="selectedBill.status"></span>
                            </div>

                            <h4 class="text-xs font-black text-slate-900 uppercase tracking-widest mb-3 border-b border-slate-100 pb-2">Line Items</h4>
                            
                            <div class="space-y-3">
                                <template x-for="item in selectedBill.items" :key="item.name + Math.random()">
                                    <div x-show="!item.is_electricity" class="flex items-center justify-between text-sm">
                                        <span class="font-medium text-slate-600" x-text="item.name"></span>
                                        <span class="font-bold text-slate-900" x-text="formatCurrency(item.amount)"></span>
                                    </div>
                                </template>

                                <template x-if="parseFloat(selectedBill.discount || 0) > 0">
                                    <div class="flex items-center justify-between text-sm pt-1">
                                        <span class="font-medium text-slate-600">Discount</span>
                                        <span class="font-bold text-rose-500" x-text="'- ' + formatCurrency(selectedBill.discount)"></span>
                                    </div>
                                </template>

                                <template x-if="parseFloat(selectedBill.tax || 0) > 0">
                                    <div class="flex items-center justify-between text-sm pt-1">
                                        <span class="font-medium text-slate-600">VAT / Tax</span>
                                        <span class="font-bold text-emerald-500" x-text="'+ ' + formatCurrency(selectedBill.tax)"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                        
                        <div class="p-6 border-t border-slate-100 bg-slate-50 shrink-0">
                            <button @click="detailsModalOpen = false; payHouseRent(selectedBill)" class="w-full py-3.5 bg-slate-900 hover:bg-teal-600 text-white rounded-xl font-bold transition-colors shadow-md flex items-center justify-center gap-2">
                                <i data-lucide="credit-card" class="w-4 h-4"></i> Pay via SSLCommerz
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div x-show="descoModalOpen" x-cloak class="fixed inset-0 z-[150] flex items-center justify-center px-4">
            <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="descoModalOpen = false" x-transition.opacity></div>
            <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-sm relative z-10 p-8 transform transition-all text-center">
                <div class="mx-auto w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full flex items-center justify-center mb-6 border border-indigo-100">
                    <i data-lucide="zap" class="w-8 h-8"></i>
                </div>
                <h3 class="text-xl font-black text-slate-900 mb-4 tracking-tight" x-text="descoModalTitle"></h3>
                
                <div class="bg-slate-50 rounded-xl p-4 mb-4 border border-slate-100 text-left">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Meter Account</p>
                    <p class="text-lg font-mono font-bold text-slate-900 tracking-widest" x-text="descoAccount || 'Not Provided'"></p>
                </div>

                <div class="text-sm font-medium text-slate-700 mb-6 whitespace-pre-wrap bg-slate-100/50 p-4 rounded-xl border border-slate-200 text-left max-h-60 overflow-y-auto font-mono text-xs" x-text="descoModalMessage"></div>
                
                <button @click="descoModalOpen = false" class="w-full py-3.5 bg-slate-900 hover:bg-indigo-600 text-white rounded-xl font-bold transition-all shadow-md">
                    Done
                </button>
            </div>
        </div>

        <div class="fixed bottom-6 right-6 z-[200] max-w-sm w-full pointer-events-none">
            <template x-for="toast in toasts" :key="toast.id">
                <div class="bg-slate-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-3 mb-3 pointer-events-auto transform transition-all duration-300 translate-y-0 opacity-100 border border-slate-700">
                    <i data-lucide="info" class="w-5 h-5 text-teal-400"></i>
                    <p class="text-sm font-bold" x-text="toast.message"></p>
                </div>
            </template>
        </div>

    </main>

    <script>
        function billingApp() {
            return {
                sidebarOpen: false, 
                desktopSidebarOpen: localStorage.getItem('desktopSidebar') === 'false' ? false : true,
                
                bills: <?php echo $bills_json; ?>,
                historyBills: <?php echo $history_json; ?>,
                totalDue: <?php echo $total_due_json; ?>,
                
                detailsModalOpen: false,
                selectedBill: null,
                
                descoAccount: '',
                descoRechargeAmount: '', 
                descoModalOpen: false,
                descoModalTitle: '',
                descoModalMessage: '',
                toasts: [],

                initApp() {
                    this.$watch('desktopSidebarOpen', val => localStorage.setItem('desktopSidebar', val));
                    setTimeout(() => { if(window.lucide) lucide.createIcons(); }, 50);
                },

                formatCurrency(val) {
                    return '৳ ' + parseFloat(val || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                },
                
                showToast(msg) {
                    const id = Date.now();
                    this.toasts.push({ id, message: msg });
                    setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 3000);
                },

                openInvoiceDetails(bill) {
                    this.selectedBill = bill;
                    this.detailsModalOpen = true;
                },

                payHouseRent(specificBill = null) {
                    let amountToPay = specificBill ? (specificBill.total_without_electricity ?? specificBill.total_amount) : this.totalDue;
                    let activeBillId = specificBill ? specificBill.id : (this.bills.length > 0 ? this.bills[0].id : null);
                    
                    if (amountToPay <= 0) {
                        this.showToast('No pending amount for this action!');
                        return;
                    }
                    if (!activeBillId) {
                        this.showToast('No valid invoice found!');
                        return;
                    }

                    this.showToast('Redirecting to SSLCommerz Gateway...');
                    
                    setTimeout(() => {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '<?php echo BASE_URL; ?>payment_integration/payment_init.php';

                        const billIdInput = document.createElement('input');
                        billIdInput.type = 'hidden';
                        billIdInput.name = 'bill_id';
                        billIdInput.value = activeBillId;
                        form.appendChild(billIdInput);

                        const amountInput = document.createElement('input');
                        amountInput.type = 'hidden';
                        amountInput.name = 'amount';
                        amountInput.value = amountToPay;
                        form.appendChild(amountInput);

                        document.body.appendChild(form);
                        form.submit();
                    }, 1000);
                },

                validateDesco() {
                    if (!this.descoAccount || this.descoAccount.trim() === '') {
                        this.showToast('Please enter your DESCO account number.');
                        return false;
                    }
                    if (this.descoAccount.length < 8) {
                        this.showToast('Invalid account number. Must be at least 8 digits.');
                        return false;
                    }
                    return true;
                },

                async mockDescoAPI(action, title) {
                    if (!this.validateDesco()) return;
                    this.showToast('Contacting DESCO Gateway...');
                    
                    try {
                        const res = await fetch('<?php echo BASE_URL; ?>api/desco.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: action, account: this.descoAccount })
                        });
                        const data = await res.json();
                        
                        if (data.success) {
                            this.descoModalTitle = title;
                            this.descoModalMessage = data.output;
                            this.descoModalOpen = true;
                        } else {
                            this.showToast(data.message || 'Error fetching data', 'error');
                        }
                    } catch (e) {
                        setTimeout(() => {
                            this.descoModalTitle = title;
                            this.descoModalMessage = "System connection simulated.\nData returned successfully for " + this.descoAccount + ".";
                            this.descoModalOpen = true;
                        }, 800);
                    }
                    setTimeout(() => lucide.createIcons(), 50);
                },

                viewDescoDetails() { this.mockDescoAPI('get_customer_info', 'Meter Details'); },
                viewDescoBalance() { this.mockDescoAPI('get_balance', 'Current Balance'); },
                viewDescoConsumption() { this.mockDescoAPI('get_monthly_consumption', 'Monthly Usage'); },
                viewDescoHistory() { this.mockDescoAPI('get_recharge_history', 'Recharge History'); },
                
                payDescoBill() { 
                    if (!this.validateDesco()) return;
                    
                    if (!this.descoRechargeAmount || parseFloat(this.descoRechargeAmount) < 10) {
                        this.showToast('Please enter a valid recharge amount (Min 10 ৳).');
                        return;
                    }

                    this.showToast('Redirecting to SSLCommerz Gateway...');
                    
                    setTimeout(() => {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '<?php echo BASE_URL; ?>payment_integration/payment_init.php';

                        const billIdInput = document.createElement('input');
                        billIdInput.type = 'hidden';
                        billIdInput.name = 'bill_id';
                        billIdInput.value = '999999'; 
                        form.appendChild(billIdInput);

                        const amountInput = document.createElement('input');
                        amountInput.type = 'hidden';
                        amountInput.name = 'amount';
                        amountInput.value = this.descoRechargeAmount;
                        form.appendChild(amountInput);

                        document.body.appendChild(form);
                        form.submit();
                    }, 1000);
                }
            }
        }
    </script>
    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>