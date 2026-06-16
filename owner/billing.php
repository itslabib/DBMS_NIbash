<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

// Fetch utility types for dropdowns
$utilities = [];
$utils_q = @mysqli_query($conn, "SELECT * FROM utility_types ORDER BY FIELD(utility_name, 'House rent', 'Electricity bill', 'Water bill', 'Gas bill', 'Parking rent', 'Other')");
if($utils_q) {
    while($u = mysqli_fetch_assoc($utils_q)) {
        $utilities[] = $u;
    }
}
$utilities_json = json_encode($utilities);

// ── Building Isolation Context ───────────────────────────────────────────
$user_id = $_SESSION['user_id'];
$building_id = '';
$building_q = mysqli_query($conn, "SELECT a.building_id 
                                   FROM apartment_assignments aa 
                                   JOIN apartments a ON a.id = aa.apt_id 
                                   WHERE aa.user_id = '$user_id' AND aa.is_active = 1 
                                   LIMIT 1");

if ($building_q && mysqli_num_rows($building_q) > 0) {
    $building_id = mysqli_fetch_assoc($building_q)['building_id'];
}

// Fetch active residents for bulk actions / invoice builder (Filtered by building)
$residents = [];
if (!empty($building_id)) {
    $res_q = mysqli_query($conn, "SELECT u.id as resident_id, p.full_name, a.apt_number, a.id as apt_id 
                                   FROM users u 
                                   JOIN user_profiles p ON u.id = p.user_id 
                                   JOIN apartment_assignments aa ON aa.user_id = u.id AND aa.is_active = 1
                                   JOIN apartments a ON a.id = aa.apt_id 
                                   WHERE u.role_id = 2 AND a.building_id = '$building_id'");
    while ($r = mysqli_fetch_assoc($res_q)) {
        $residents[] = $r;
    }
}
$residents_json = json_encode($residents);
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
    <title>Enterprise Billing | Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    
    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        .hover-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px -8px rgba(16, 185, 129, 0.12); border-color: #6ee7b7; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #ecfdf5; }
        ::-webkit-scrollbar-thumb { background: #a7f3d0; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6ee7b7; }
        [x-cloak] { display: none !important; }
        
        .toast-enter { transform: translateY(100%); opacity: 0; }
        .toast-enter-active { transform: translateY(0); opacity: 1; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .toast-leave { transform: translateY(0); opacity: 1; }
        .toast-leave-active { transform: translateY(100%); opacity: 0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="bg-[#f2fbf6] text-slate-800 font-sans antialiased overflow-hidden flex" x-data="billingApp()">
    
    <?php $active_page = 'billing.php'; include '../includes/owner_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex-1 flex flex-col h-screen pt-2 pb-4 px-4 sm:px-6 lg:px-8 relative">
        
        <div class="flex justify-center pt-8 pb-4 shrink-0">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-emerald-100/60 hover:shadow-[0_4px_15px_-3px_rgba(16,185,129,0.15)] hover:border-emerald-200 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>
        
        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(16,185,129,0.15)] flex-1 flex flex-col overflow-hidden border border-emerald-100/80 relative z-10">

            <header class="bg-white/80 backdrop-blur-xl border-b border-emerald-50 sticky top-0 z-40 shadow-sm flex flex-col shrink-0">   
    
                <div class="px-8 py-4 flex items-center justify-between">   
                    
                    <div class="flex items-center gap-4">
                        <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Finance Hub</span>
                        </h2>
                    </div>

                </div>

                <div class="px-8 py-3 bg-slate-50/50 border-t border-slate-100/80 flex flex-col sm:flex-row items-center gap-4">
        
                    <div class="hidden sm:block sm:flex-1"></div>

                    <div class="bg-slate-100/60 p-1.5 rounded-[1.25rem] flex items-center gap-1 border border-slate-200/60 shadow-inner w-full sm:w-auto overflow-x-auto custom-scrollbar shrink-0">
                        <button @click="currentTab = 'overview'" 
                                :class="currentTab === 'overview' ? 'bg-white text-emerald-700 shadow-sm border-slate-200' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-200/50 border-transparent'" 
                                class="px-5 py-2.5 rounded-xl font-black text-sm transition-all flex items-center gap-2 border whitespace-nowrap">
                            <i data-lucide="pie-chart" class="w-4 h-4"></i> Overview
                        </button>
                        <button @click="currentTab = 'invoices'" 
                                :class="currentTab === 'invoices' ? 'bg-white text-emerald-700 shadow-sm border-slate-200' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-200/50 border-transparent'" 
                                class="px-5 py-2.5 rounded-xl font-black text-sm transition-all flex items-center gap-2 border whitespace-nowrap">
                            <i data-lucide="receipt" class="w-4 h-4"></i> Invoices
                        </button>
                        <button @click="currentTab = 'residents'" 
                                :class="currentTab === 'residents' ? 'bg-white text-emerald-700 shadow-sm border-slate-200' : 'text-slate-500 hover:text-slate-800 hover:bg-slate-200/50 border-transparent'" 
                                class="px-5 py-2.5 rounded-xl font-black text-sm transition-all flex items-center gap-2 border whitespace-nowrap">
                            <i data-lucide="users-2" class="w-4 h-4"></i> Accounts
                        </button>
                    </div>

                    <div class="w-full sm:flex-1 flex sm:justify-end">
                        <button @click="openBuilder()" class="w-full sm:w-auto bg-slate-900 hover:bg-emerald-600 text-white px-6 py-2.5 rounded-xl font-black text-sm flex items-center justify-center gap-2 shadow-md hover:shadow-lg hover:shadow-emerald-500/30 transition-all group">
                            <i data-lucide="plus" class="w-4 h-4 group-hover:rotate-90 transition-transform"></i> New Invoice
                        </button>
                    </div>
                    
                </div>
            </header>

            <div class="flex-1 overflow-y-auto bg-slate-50/50">
                <div class="p-8 sm:p-10 max-w-[1600px] mx-auto space-y-10">
                    
                    <div x-show="currentTab === 'overview'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
                            <div class="hover-card bg-white rounded-[1.5rem] p-6 border border-slate-200 shadow-sm relative overflow-hidden group flex flex-col justify-between h-full min-h-[140px]">
                                <div class="absolute top-0 left-0 w-1 h-full bg-indigo-400"></div>
                                <div class="flex justify-between items-start mb-2">
                                    <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Outstanding Dues</p>
                                    <div class="p-2 bg-indigo-50 rounded-lg text-indigo-500 group-hover:scale-110 transition-transform"><i data-lucide="wallet" class="w-4 h-4"></i></div>
                                </div>
                                <div>
                                    <h3 class="text-3xl font-black text-slate-900 tracking-tight" x-text="formatCurrency(analytics.outstanding)"></h3>
                                    <p class="text-[11px] font-bold mt-2 flex items-center gap-1.5" :class="analytics.outstanding > analytics.last_month_outstanding ? 'text-rose-500' : 'text-emerald-500'">
                                        <i :data-lucide="analytics.outstanding > analytics.last_month_outstanding ? 'trending-up' : 'trending-down'" class="w-3.5 h-3.5"></i>
                                        <span x-text="formatCurrency(Math.abs(analytics.outstanding - analytics.last_month_outstanding)) + ' vs last month'"></span>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="hover-card bg-white rounded-[1.5rem] p-6 border border-slate-200 shadow-sm relative overflow-hidden group flex flex-col justify-between h-full min-h-[140px]">
                                <div class="absolute top-0 left-0 w-1 h-full bg-emerald-400"></div>
                                <div class="flex justify-between items-start mb-2">
                                    <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Collection Rate</p>
                                    <div class="p-2 bg-emerald-50 rounded-lg text-emerald-500 group-hover:scale-110 transition-transform"><i data-lucide="check-circle-2" class="w-4 h-4"></i></div>
                                </div>
                                <div>
                                    <h3 class="text-3xl font-black text-slate-900 tracking-tight" x-text="analytics.collection_rate + '%'"></h3>
                                    <div class="w-full bg-slate-100 rounded-full h-1.5 mt-3 overflow-hidden shadow-inner">
                                        <div class="bg-emerald-500 h-1.5 rounded-full" :style="'width: ' + analytics.collection_rate + '%'"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="hover-card bg-white rounded-[1.5rem] p-6 border border-slate-200 shadow-sm relative overflow-hidden group flex flex-col justify-between h-full min-h-[140px]">
                                <div class="absolute top-0 left-0 w-1 h-full bg-rose-400"></div>
                                <div class="flex justify-between items-start mb-2">
                                    <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Overdue Amount</p>
                                    <div class="p-2 bg-rose-50 rounded-lg text-rose-500 group-hover:scale-110 transition-transform"><i data-lucide="alert-circle" class="w-4 h-4"></i></div>
                                </div>
                                <div>
                                    <h3 class="text-3xl font-black text-rose-600 tracking-tight" x-text="formatCurrency(analytics.overdue)"></h3>
                                    <p class="text-[11px] font-bold text-rose-400/80 mt-2">Requires immediate attention</p>
                                </div>
                            </div>

                            <div class="hover-card bg-white rounded-[1.5rem] p-6 border border-slate-200 shadow-sm relative overflow-hidden group flex flex-col justify-between h-full min-h-[140px]">
                                <div class="absolute top-0 left-0 w-1 h-full bg-sky-400"></div>
                                <div class="flex justify-between items-start mb-2">
                                    <p class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Invoices Issued</p>
                                    <div class="p-2 bg-sky-50 rounded-lg text-sky-500 group-hover:scale-110 transition-transform"><i data-lucide="file-check-2" class="w-4 h-4"></i></div>
                                </div>
                                <div>
                                    <h3 class="text-3xl font-black text-slate-900 tracking-tight" x-text="analytics.bills_generated"></h3>
                                    <p class="text-[11px] font-bold text-slate-400 mt-2">Generated this month</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                            
                            <div class="lg:col-span-2 bg-white rounded-[2rem] border border-slate-200 shadow-[0_8px_30px_-6px_rgba(0,0,0,0.04)] p-8">
                                <div class="flex justify-between items-center mb-8 border-b border-slate-100 pb-4">
                                    <h4 class="text-lg font-black text-slate-900 flex items-center gap-3">
                                        <span class="p-2 bg-emerald-50 text-emerald-600 rounded-xl shadow-inner"><i data-lucide="bar-chart-3" class="w-5 h-5"></i></span>
                                        6-Month Revenue Trend
                                    </h4>
                                    <span class="text-xs font-bold text-slate-400 uppercase tracking-widest px-3 py-1 bg-slate-50 rounded-lg border border-slate-100">Live Data</span>
                                </div>
                                <div class="relative h-[320px] w-full">
                                    <canvas id="revenueChart"></canvas>
                                </div>
                            </div>

                            <div class="space-y-6 flex flex-col">
                                

                                <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6">
                                    <h4 class="text-sm font-black text-slate-900 mb-4 flex items-center gap-2">
                                        <i data-lucide="clock" class="w-4 h-4 text-rose-500"></i> Quick Links
                                    </h4>
                                    <div class="space-y-2">
                                        <button @click="currentTab = 'invoices'; statusFilter = 'Overdue'; fetchBills();" class="w-full flex items-center justify-between p-3 rounded-xl hover:bg-rose-50 border border-transparent hover:border-rose-100 transition-colors group">
                                            <span class="text-sm font-bold text-slate-600 group-hover:text-rose-700">View Overdue Invoices</span>
                                            <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-rose-500"></i>
                                        </button>
                                        <button @click="currentTab = 'invoices'; statusFilter = 'Pending'; fetchBills();" class="w-full flex items-center justify-between p-3 rounded-xl hover:bg-amber-50 border border-transparent hover:border-amber-100 transition-colors group">
                                            <span class="text-sm font-bold text-slate-600 group-hover:text-amber-700">View Pending Invoices</span>
                                            <i data-lucide="chevron-right" class="w-4 h-4 text-slate-400 group-hover:text-amber-500"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div x-show="currentTab === 'invoices'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="bg-white rounded-[2rem] border border-slate-200 shadow-[0_8px_30px_-6px_rgba(0,0,0,0.06)] overflow-hidden flex flex-col">
                            
                            <div class="p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white">
                                <div class="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto">
                                    <div class="relative w-full sm:w-72">
                                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-4 top-1/2 -translate-y-1/2"></i>
                                        <input type="text" x-model="searchQuery" @input.debounce.500ms="fetchBills()" placeholder="Search by name, invoice #..." 
                                               class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 outline-none font-bold transition-all text-slate-800">
                                    </div>
                                    <div class="relative w-full sm:w-48">
                                        <select x-model="statusFilter" @change="fetchBills()" class="w-full pl-4 pr-10 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 outline-none font-bold text-slate-700 appearance-none cursor-pointer transition-all">
                                            <option value="All">All Statuses</option>
                                            <option value="Draft">Draft</option>
                                            <option value="Pending">Pending</option>
                                            <option value="Partially Paid">Partially Paid</option>
                                            <option value="Paid">Paid</option>
                                            <option value="Overdue">Overdue</option>
                                        </select>
                                        <i data-lucide="filter" class="w-4 h-4 text-slate-400 absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                                    </div>
                                </div>
                                <div class="flex items-center justify-end gap-3 w-full md:w-auto">
                                    <button @click="fetchBills()" class="p-3 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 border border-transparent hover:border-emerald-100 rounded-xl transition-all shadow-sm" title="Refresh Data">
                                        <i data-lucide="refresh-cw" class="w-5 h-5" :class="loadingBills ? 'animate-spin text-emerald-500' : ''"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full text-left whitespace-nowrap">
                                    <thead>
                                        <tr class="bg-slate-50 text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-200">
                                            <th class="px-8 py-5">Invoice Details</th>
                                            <th class="px-6 py-5">Resident & Unit</th>
                                            <th class="px-6 py-5">Timeline</th>
                                            <th class="px-6 py-5 text-right">Financials</th>
                                            <th class="px-6 py-5 text-center">Status</th>
                                            <th class="px-8 py-5 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 text-sm font-medium bg-white">
                                        <tr x-show="loadingBills"><td colspan="6" class="px-6 py-20 text-center"><div class="flex flex-col items-center justify-center"><i data-lucide="loader-2" class="w-8 h-8 text-emerald-500 animate-spin mb-3"></i><span class="text-slate-500 font-bold">Syncing records...</span></div></td></tr>
                                        <tr x-show="!loadingBills && bills.length === 0"><td colspan="6" class="px-6 py-20 text-center"><div class="w-16 h-16 bg-slate-50 border border-slate-100 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-300"><i data-lucide="inbox" class="w-8 h-8"></i></div><span class="text-slate-500 font-bold text-lg">No invoices found.</span></td></tr>
                                        
                                        <template x-for="bill in bills" :key="bill.id">
                                            <tr class="hover:bg-emerald-50/30 transition-colors group">
                                                <td class="px-8 py-5">
                                                    <span class="font-black text-slate-900" x-text="bill.bill_number"></span>
                                                    <div class="text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-wider" x-text="bill.period"></div>
                                                </td>
                                                <td class="px-6 py-5">
                                                    <div class="font-black text-slate-800" x-text="bill.resident_name"></div>
                                                    <div class="inline-flex items-center gap-1.5 px-2 py-0.5 mt-1.5 bg-slate-50 border border-slate-200 rounded-md text-[10px] font-bold text-slate-500">
                                                        <i data-lucide="door-open" class="w-3 h-3"></i> Apt <span x-text="bill.apt_number"></span>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-5 text-slate-500 font-bold">
                                                    <div class="flex items-center gap-2"><i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i> <span x-text="bill.due_date"></span></div>
                                                </td>
                                                <td class="px-6 py-5 text-right">
                                                    <div class="font-black text-slate-900 text-base" x-text="formatCurrency(bill.total_amount)"></div>
                                                    <div x-show="bill.paid_amount > 0" class="text-[10px] font-black text-emerald-600 mt-1 uppercase tracking-wider">Paid: <span x-text="formatCurrency(bill.paid_amount)"></span></div>
                                                </td>
                                                <td class="px-6 py-5 text-center">
                                                    <span class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-widest border shadow-sm" 
                                                          :class="{
                                                              'bg-slate-50 text-slate-600 border-slate-200': bill.status === 'Draft',
                                                              'bg-amber-50 text-amber-700 border-amber-200': bill.status === 'Pending',
                                                              'bg-blue-50 text-blue-700 border-blue-200': bill.status === 'Partially Paid',
                                                              'bg-emerald-50 text-emerald-700 border-emerald-200': bill.status === 'Paid',
                                                              'bg-rose-50 text-rose-700 border-rose-200': bill.status === 'Overdue'
                                                          }" x-text="bill.status"></span>
                                                </td>
                                                <td class="px-8 py-5 text-right">
                                                    <div class="flex items-center justify-end gap-1 opacity-40 group-hover:opacity-100 transition-opacity">
                                                        <button @click="openPaymentModal(bill)" x-show="['Pending', 'Partially Paid', 'Overdue'].includes(bill.status)" class="p-2 text-emerald-600 hover:bg-emerald-100 border border-transparent hover:border-emerald-200 rounded-xl transition-all" title="Record Payment"><i data-lucide="dollar-sign" class="w-4 h-4"></i></button>
                                                        <button @click="downloadPDF(bill.id)" class="p-2 text-slate-500 hover:text-indigo-600 hover:bg-indigo-50 border border-transparent hover:border-indigo-100 rounded-xl transition-all" title="Download PDF"><i data-lucide="download" class="w-4 h-4"></i></button>
                                                        <button @click="editExisting(bill.id)" class="p-2 text-slate-500 hover:text-blue-600 hover:bg-blue-50 border border-transparent hover:border-blue-100 rounded-xl transition-all" title="Edit"><i data-lucide="edit-3" class="w-4 h-4"></i></button>
                                                        <button @click="deleteBill(bill.id)" class="p-2 text-slate-500 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-100 rounded-xl transition-all" title="Delete"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="p-6 border-t border-slate-100 flex items-center justify-between bg-white">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Page <span x-text="pagination.current" class="text-slate-800"></span> of <span x-text="pagination.total" class="text-slate-800"></span> <span class="mx-2">•</span> <span x-text="pagination.records" class="text-slate-800"></span> Records</p>
                                <div class="flex gap-2">
                                    <button @click="if(pagination.current > 1) { page = pagination.current - 1; fetchBills(); }" :disabled="pagination.current <= 1" class="w-10 h-10 flex items-center justify-center border border-slate-200 rounded-xl text-slate-500 disabled:opacity-30 disabled:bg-slate-50 hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-200 transition-colors shadow-sm"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
                                    <button @click="if(pagination.current < pagination.total) { page = pagination.current + 1; fetchBills(); }" :disabled="pagination.current >= pagination.total" class="w-10 h-10 flex items-center justify-center border border-slate-200 rounded-xl text-slate-500 disabled:opacity-30 disabled:bg-slate-50 hover:bg-emerald-50 hover:text-emerald-600 hover:border-emerald-200 transition-colors shadow-sm"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="currentTab === 'residents'" x-cloak x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                            <template x-for="r in residentsList" :key="r.resident_id">
                                <div class="hover-card bg-white rounded-[1.5rem] border border-slate-200 p-6 shadow-sm flex flex-col justify-between relative overflow-hidden group">
                                    <div class="absolute top-0 left-0 w-full h-1 bg-slate-200 group-hover:bg-emerald-400 transition-colors"></div>
                                    <div>
                                        <div class="flex justify-between items-start mb-5">
                                            <div class="w-14 h-14 rounded-full bg-slate-50 border-4 border-white shadow-sm ring-1 ring-slate-100 text-slate-400 font-black flex items-center justify-center text-xl group-hover:bg-emerald-50 group-hover:text-emerald-600 group-hover:ring-emerald-200 transition-colors">
                                                <span x-text="r.full_name.substring(0,1).toUpperCase()"></span>
                                            </div>
                                            <span class="bg-slate-50 border border-slate-200 text-slate-600 text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md shadow-sm">Apt <span x-text="r.apt_number || 'N/A'"></span></span>
                                        </div>
                                        <h3 class="font-black text-slate-900 text-lg group-hover:text-emerald-600 transition-colors" x-text="r.full_name"></h3>
                                        <p class="text-xs text-slate-400 font-bold mt-1 uppercase tracking-widest">Active Account</p>
                                    </div>
                                    <div class="mt-8 pt-5 border-t border-slate-100 flex items-center justify-between">
                                        <button @click="openBuilder(r.resident_id, r.full_name, r.apt_id)" class="w-full text-sm font-black text-slate-600 hover:text-emerald-700 bg-white border border-slate-200 hover:border-emerald-300 hover:bg-emerald-50 hover:shadow-md py-3 rounded-xl transition-all flex items-center justify-center gap-2">
                                            <i data-lucide="receipt" class="w-4 h-4"></i> Issue Bill
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div x-show="paymentModalOpen" x-cloak class="fixed inset-0 z-[150] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="paymentModalOpen = false" x-transition.opacity></div>
            <div class="bg-white rounded-[2rem] shadow-2xl border border-emerald-100 w-full max-w-md relative z-10 p-8 transform transition-all"
                 x-show="paymentModalOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-8"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-8">
                
                <button @click="paymentModalOpen = false" class="absolute top-5 right-5 w-10 h-10 flex items-center justify-center text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-xl transition-colors border border-transparent hover:border-rose-100">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
                
                <div class="flex items-center gap-4 mb-6 pb-4 border-b border-slate-100">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center border border-emerald-100 shadow-sm">
                        <i data-lucide="banknote" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black text-slate-900">Record Payment</h3>
                        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1">Invoice <span class="text-slate-800" x-text="paymentData.bill_number"></span></p>
                    </div>
                </div>
                
                <div class="bg-slate-50 p-5 rounded-2xl mb-8 flex justify-between items-center border border-slate-200 shadow-inner">
                    <div>
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Remaining Balance</p>
                        <p class="text-2xl font-black text-slate-900 tracking-tight" x-text="formatCurrency(paymentData.total - paymentData.paid)"></p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div>
                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Amount Paid (Tk)</label>
                        <input type="number" step="0.01" x-model.number="paymentForm.amount" class="w-full px-5 py-3.5 bg-white border border-slate-200 rounded-xl focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none font-black text-slate-900 transition-all shadow-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Payment Method</label>
                        <div class="relative">
                            <select x-model="paymentForm.method" class="w-full px-5 py-3.5 bg-white border border-slate-200 rounded-xl focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none font-bold text-slate-700 appearance-none cursor-pointer transition-all shadow-sm">
                                <option>Cash</option>
                                <option>Bank Transfer</option>
                                <option>Credit Card</option>
                                <option>Mobile Banking</option>
                                <option>Cheque</option>
                            </select>
                            <i data-lucide="chevron-down" class="absolute right-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none"></i>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-700 uppercase tracking-widest mb-2">Transaction ID / Notes</label>
                        <input type="text" x-model="paymentForm.notes" placeholder="Optional reference" class="w-full px-5 py-3.5 bg-white border border-slate-200 rounded-xl focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none font-bold text-slate-800 transition-all shadow-sm">
                    </div>
                    <button @click="submitPayment()" class="w-full mt-4 bg-slate-900 hover:bg-emerald-600 text-white font-black py-4 rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-emerald-500/30 flex items-center justify-center gap-2">
                        <i data-lucide="check-circle" class="w-5 h-5"></i> Confirm Payment
                    </button>
                </div>
            </div>
        </div>

        <div x-show="builderOpen" x-cloak
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6"
             @edit-bill.window="editExisting($event.detail.id)">

            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" @click="closeBuilder()" x-transition.opacity></div>

            <div class="relative w-full max-w-4xl max-h-[90vh] flex flex-col bg-white rounded-2xl shadow-xl overflow-hidden border border-slate-200"
                 x-show="builderOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-4">

                <div x-show="loadingDetails" class="absolute inset-0 bg-white/80 z-50 flex flex-col items-center justify-center text-slate-500">
                    <i data-lucide="loader-2" class="w-8 h-8 animate-spin mb-3 text-emerald-500"></i>
                    <p class="text-sm font-medium">Loading details...</p>
                </div>

                <!-- Header -->
                <div class="px-8 py-5 border-b border-slate-100 flex items-center justify-between shrink-0 bg-white relative z-20">
                    <div>
                        <h2 class="text-xl font-bold text-slate-800" x-text="editMode ? 'Edit Invoice' : 'Create Invoice'"></h2>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-md"
                              :class="form.status === 'Draft' ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-700'"
                              x-text="form.status"></span>
                        <button @click="closeBuilder()" class="text-slate-400 hover:text-slate-600 hover:bg-slate-50 p-2 rounded-lg transition-colors">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-8 space-y-10 bg-slate-50/30">
                    
                    <!-- Section 1 -->
                    <div class="space-y-5">
                        <h3 class="text-sm font-bold text-slate-800 border-b border-slate-200 pb-2">Recipient & Period</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="md:col-span-1">
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Resident</label>
                                <select x-model="form.resident_id" :disabled="editMode"
                                        class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none text-sm text-slate-800 disabled:bg-slate-50 disabled:text-slate-500 transition-shadow">
                                    <option value="">Select resident...</option>
                                    <template x-for="r in residentsList" :key="r.resident_id">
                                        <option :value="r.resident_id" x-text="r.full_name + ' (Apt ' + (r.apt_number||'N/A') + ')'"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Billing Month</label>
                                <input type="month" x-model="form.bill_month"
                                       class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none text-sm text-slate-800 transition-shadow">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Due Date</label>
                                <input type="date" x-model="form.due_date"
                                       class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none text-sm text-slate-800 transition-shadow">
                            </div>
                        </div>
                    </div>

                    <!-- Section 2 -->
                    <div class="space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                            <h3 class="text-sm font-bold text-slate-800">Charges</h3>
                            <button type="button" @click="addItem()" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1.5">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add Item
                            </button>
                        </div>
                        
                        <div class="space-y-3">
                            <div class="grid grid-cols-12 gap-4 px-1 hidden sm:grid">
                                <div class="col-span-8 text-xs font-semibold text-slate-500 uppercase tracking-wider">Category / Description</div>
                                <div class="col-span-3 text-xs font-semibold text-slate-500 uppercase tracking-wider text-right pr-4">Amount</div>
                                <div class="col-span-1"></div>
                            </div>
                            <template x-for="(item, index) in form.items" :key="item.id">
                                <div class="grid grid-cols-12 gap-4 items-center group">
                                    <div class="col-span-8">
                                        <select x-model="item.utility_type_id"
                                                class="w-full px-3 py-2 bg-white border border-slate-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none text-sm text-slate-800 transition-shadow">

                                            <template x-for="u in utilities" :key="u.id">
                                                <option :value="u.id" x-text="u.utility_name"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="col-span-3 relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs text-slate-400">Tk</span>
                                        <input type="number" step="0.01" min="0" x-model.number="item.unit_price" :placeholder="item.unit_price === 0 ? '0' : ''"
                                               class="w-full pl-8 pr-3 py-2 bg-white border border-slate-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none text-sm text-right text-slate-800 transition-shadow">
                                    </div>
                                    <div class="col-span-1 text-right">
                                        <button @click="removeItem(index)" x-show="form.items.length > 1" class="text-slate-400 hover:text-rose-500 p-1.5 rounded-md hover:bg-rose-50 transition-colors">
                                            <i data-lucide="x" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Section 3 -->
                    <div class="space-y-5">
                        <h3 class="text-sm font-bold text-slate-800 border-b border-slate-200 pb-2">Summary</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 mb-1.5">Notes to Resident (Optional)</label>
                                <textarea x-model="form.notes" rows="4" placeholder="Enter any additional notes..."
                                          class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-lg focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none text-sm text-slate-800 resize-none transition-shadow"></textarea>
                            </div>
                            
                            <div class="bg-white p-5 rounded-xl border border-slate-200 space-y-4">
                                <div class="flex justify-between text-sm">
                                    <span class="text-slate-600 font-medium">Subtotal</span>
                                    <span class="font-semibold text-slate-800" x-text="'Tk ' + formatCurrency(calculateSubtotal())"></span>
                                </div>
                                <div class="flex items-center gap-4">
                                    <div class="flex-1 flex items-center justify-between text-sm">
                                        <span class="text-slate-600 font-medium">Discount (Tk)</span>
                                        <input type="number" step="0.01" min="0" x-model.number="form.discount"
                                               class="w-24 px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-right text-sm text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-shadow">
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 border-b border-slate-100 pb-4">
                                    <div class="flex-1 flex items-center justify-between text-sm">
                                        <span class="text-slate-600 font-medium">Tax (%)</span>
                                        <input type="number" step="0.1" min="0" x-model.number="form.tax"
                                               class="w-24 px-2 py-1.5 bg-white border border-slate-300 rounded-lg text-right text-sm text-slate-800 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-shadow">
                                    </div>
                                </div>
                                <div class="flex justify-between items-center pt-2">
                                    <span class="font-bold text-slate-800 text-base">Total Payable</span>
                                    <span class="text-xl font-bold text-emerald-600 tracking-tight" x-text="'Tk ' + formatCurrency(calculateTotal())"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-8 py-5 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50 shrink-0">
                    <button @click="closeBuilder()"
                            class="px-5 py-2.5 text-sm font-semibold text-slate-600 hover:text-slate-800 hover:bg-slate-200/50 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button @click="saveInvoice('Draft')"
                            class="px-5 py-2.5 text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 rounded-lg transition-colors shadow-sm">
                        Save Draft
                    </button>
                    <button @click="saveInvoice('Pending')"
                            class="px-6 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors shadow-sm flex items-center gap-2">
                        <span x-text="editMode ? 'Update Invoice' : 'Send Invoice'"></span>
                    </button>
                </div>

            </div>
        </div>
        <div id="invoice-pdf-template" class="hidden absolute top-0 left-0 bg-white p-10 w-[800px] text-slate-800 font-sans">
            <div class="flex justify-between items-start mb-10 border-b pb-8 border-slate-200">
                <div>
                    <h1 class="text-3xl font-black text-emerald-600 tracking-tight">Nibash</h1>
                    <p class="text-slate-500 mt-1 font-medium">Property Management Services</p>
                </div>
                <div class="text-right">
                    <h2 class="text-4xl font-black text-slate-200 uppercase tracking-widest">Invoice</h2>
                    <p class="text-sm font-bold mt-2" id="pdf-bill-number"></p>
                    <p class="text-sm text-slate-500 font-medium" id="pdf-date"></p>
                </div>
            </div>
            
            <div class="flex justify-between mb-10">
                <div>
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Billed To</p>
                    <h3 class="text-lg font-bold" id="pdf-resident"></h3>
                    <p class="text-sm text-slate-600 font-medium" id="pdf-apt"></p>
                </div>
                <div class="text-right">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-1">Amount Due</p>
                    <h3 class="text-2xl font-black text-emerald-600"><span id="pdf-total"></span> Tk</h3>
                    <p class="text-sm font-bold text-rose-500 mt-1" id="pdf-due-date"></p>
                </div>
            </div>

            <table class="w-full text-left mb-10">
                <thead class="border-b-2 border-slate-900">
                    <tr>
                        <th class="py-3 text-xs font-black uppercase tracking-widest text-slate-500">Description</th>
                        <th class="py-3 text-xs font-black uppercase tracking-widest text-slate-500 text-center">Qty</th>
                        <th class="py-3 text-xs font-black uppercase tracking-widest text-slate-500 text-right">Price</th>
                        <th class="py-3 text-xs font-black uppercase tracking-widest text-slate-500 text-right">Total</th>
                    </tr>
                </thead>
                <tbody id="pdf-items" class="divide-y divide-slate-100 text-sm">
                </tbody>
            </table>

            <div class="flex justify-end mb-10">
                <div class="w-64 space-y-2">
                    <div class="flex justify-between text-sm"><span class="font-bold text-slate-500">Subtotal</span><span class="font-black"><span id="pdf-subtotal"></span> Tk</span></div>
                    <div class="flex justify-between text-sm"><span class="font-bold text-slate-500">Discount</span><span class="font-black text-rose-500"><span id="pdf-discount"></span> Tk</span></div>
                    <div class="flex justify-between text-sm"><span class="font-bold text-slate-500">Tax</span><span class="font-black"><span id="pdf-tax"></span> Tk</span></div>
                    <div class="flex justify-between text-lg pt-2 border-t-2 border-slate-900"><span class="font-black">Total</span><span class="font-black text-emerald-600"><span id="pdf-grand"></span> Tk</span></div>
                </div>
            </div>

            <div class="border-t border-slate-200 pt-6">
                <p class="text-xs text-slate-500 font-medium" id="pdf-notes"></p>
            </div>
        </div>

        <div id="processing-modal"
            class="fixed bottom-6 right-6 z-[150] hidden flex-col items-end opacity-0 transition-opacity duration-300 pointer-events-none">
            <div class="bg-white rounded-2xl p-6 shadow-2xl transform scale-95 transition-transform duration-300 border border-emerald-100 relative overflow-hidden pointer-events-auto min-w-[300px]">
                <div class="absolute top-0 left-0 w-full h-1 bg-blue-500" id="modal-top-bar"></div>
                <div class="flex items-center gap-4">
                    <div id="modal-icon-bg"
                        class="w-12 h-12 bg-blue-50 text-blue-500 border border-blue-100 rounded-full flex items-center justify-center shrink-0 shadow-sm">
                        <div id="modal-icon-wrapper"><i data-lucide="loader-2" class="w-6 h-6 animate-spin"></i></div>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-900" id="modal-title">Sending Invoice</h3>
                        <p class="text-slate-500 font-medium text-xs mt-0.5" id="modal-message">Please wait...</p>
                    </div>
                </div>
                <button type="button" onclick="closeProcessingModal()" id="modal-ok-btn"
                    class="w-full mt-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-xs rounded-xl transition-colors cursor-pointer border border-slate-200 hidden">Understood</button>
            </div>
        </div>

    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('billingApp', () => ({
                sidebarOpen: false, 
                desktopSidebarOpen: true,
                currentTab: 'overview',
                
                // KPI Data
                analytics: { outstanding: 0, last_month_outstanding: 0, collection_rate: 0, bills_generated: 0, overdue: 0 },
                revenueChart: null,
                
                // Bills Data
                bills: [],
                loadingBills: false,
                searchQuery: '',
                statusFilter: 'All',
                pagination: { current: 1, total: 1, records: 0 },
                page: 1,
                
                // Residents
                residentsList: <?php echo $residents_json; ?>,
                utilities: <?php echo $utilities_json; ?>,
                
                // Builder State
                builderOpen: false,
                editMode: false,
                loadingDetails: false,
                form: {
                    id: null, resident_id: '', apt_id: '', bill_month: '', due_date: '', 
                    status: 'Draft', notes: '', discount: '', tax: '',
                    items: []
                },

                // Payment State
                paymentModalOpen: false,
                paymentData: { id: null, bill_number: '', total: 0, paid: 0 },
                paymentForm: { amount: '', method: 'Cash', notes: '' },
                
                toasts: [],

                init() {
                    this.fetchAnalytics();
                    this.fetchBills();
                    
                    this.$watch('currentTab', value => {
                        if (value === 'overview' && !this.revenueChart) {
                            setTimeout(() => this.initChart(), 100);
                        }
                    });
                },
                
                showToast(msg, type = 'success') {
                    const id = Date.now();
                    this.toasts.push({ id, message: msg, type });
                    setTimeout(() => {
                        this.toasts = this.toasts.filter(t => t.id !== id);
                    }, 4000);
                },

                formatCurrency(val) {
                    return parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                async fetchAnalytics() {
                    try {
                        const res = await fetch('<?= BASE_URL ?>api/billing.php?action=get_analytics');
                        const json = await res.json();
                        if (json.success) {
                            this.analytics = json.data;
                            if (this.currentTab === 'overview') this.initChart();
                        }
                    } catch (e) { console.error(e); }
                },

                initChart() {
                    if (this.revenueChart) this.revenueChart.destroy();
                    const ctx = document.getElementById('revenueChart');
                    if(!ctx) return;
                    
                    this.revenueChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: this.analytics.chart.labels,
                            datasets: [{
                                label: 'Collected Revenue',
                                data: this.analytics.chart.data,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.15)',
                                tension: 0.4,
                                fill: true,
                                borderWidth: 3,
                                pointBackgroundColor: '#fff',
                                pointBorderColor: '#10b981',
                                pointBorderWidth: 2,
                                pointRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { border: { display: false }, grid: { color: '#f8fafc' }, beginAtZero: true },
                                x: { border: { display: false }, grid: { display: false } }
                            }
                        }
                    });
                },

                async fetchBills() {
                    this.loadingBills = true;
                    try {
                        const res = await fetch(`<?= BASE_URL ?>api/billing.php?action=get_bills&page=${this.page}&limit=10&search=${encodeURIComponent(this.searchQuery)}&status=${encodeURIComponent(this.statusFilter)}`);
                        const json = await res.json();
                        if (json.success) {
                            this.bills = json.data;
                            this.pagination = json.pagination;
                        }
                    } catch (e) { console.error(e); }
                    this.loadingBills = false;
                    this.$nextTick(() => lucide.createIcons());
                },

                openBuilder(resident_id = '', name = '', apt_id = '') {
                    this.editMode = false;
                    const getUtilId = (name) => { const u = this.utilities.find(x => x.utility_name === name); return u ? u.id : ''; };
                    this.form = {
                        id: null, resident_id: resident_id, apt_id: apt_id, 
                        bill_month: new Date().toISOString().slice(0, 7), 
                        due_date: new Date(new Date().setDate(15)).toISOString().slice(0, 10), 
                        status: 'Draft', notes: '', discount: '', tax: '',
                        items: [
                            { id: Date.now() + 1, utility_type_id: getUtilId('House rent'), description: '', quantity: 1, unit_price: '' }
                        ]
                    };
                    this.builderOpen = true;
                    this.$nextTick(() => lucide.createIcons());
                },

                closeBuilder() { this.builderOpen = false; },

                addItem() {
                    this.form.items.push({ id: Date.now(), utility_type_id: this.utilities[0] ? this.utilities[0].id : '', description: '', quantity: 1, unit_price: '' });
                    this.$nextTick(() => lucide.createIcons());
                },

                removeItem(idx) { if(this.form.items.length > 1) this.form.items.splice(idx, 1); },

                calculateSubtotal() {
                    return this.form.items.reduce((sum, it) => sum + ((it.quantity||0) * (it.unit_price||0)), 0);
                },
                calculateTax() {
                    const sub = this.calculateSubtotal() - (this.form.discount || 0);
                    return sub * ((this.form.tax || 0) / 100);
                },
                calculateTotal() {
                    const sub = this.calculateSubtotal() - (this.form.discount || 0);
                    return sub + this.calculateTax();
                },

                async saveInvoice(targetStatus) {
                    this.form.status = targetStatus;
                    
                    if(!this.form.apt_id && this.form.resident_id) {
                        const r = this.residentsList.find(x => x.resident_id == this.form.resident_id);
                        if(r) this.form.apt_id = r.apt_id;
                    }

                    const payload = { action: 'save_bill', ...this.form };
                    if (this.editMode) payload.id = this.form.id;

                    showProcessingModal('Sending Invoice', 'Please wait...', 'loading');

                    try {
                        const res = await fetch('<?= BASE_URL ?>api/billing.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const json = await res.json();
                        if (json.success) {
                            showProcessingModal('Sent Successfully', 'The invoice has been issued.', 'success');
                            setTimeout(() => { closeProcessingModal(); }, 2000);
                            this.closeBuilder();
                            this.fetchBills();
                            this.fetchAnalytics();
                        } else {
                            showProcessingModal('Error', json.message, 'error');
                            setTimeout(() => { closeProcessingModal(); }, 3000);
                        }
                    } catch (e) {
                        showProcessingModal('Server Error', 'Failed to send invoice.', 'error');
                        setTimeout(() => { closeProcessingModal(); }, 3000);
                    }
                },

                async editExisting(id) {
                    this.editMode = true;
                    this.builderOpen = true;
                    this.loadingDetails = true;
                    
                    try {
                        const res = await fetch(`<?= BASE_URL ?>api/billing.php?action=get_bill_details&bill_id=${id}`);
                        const json = await res.json();
                        if (json.success) {
                            const b = json.bill;
                            const monthIdx = ['January','February','March','April','May','June','July','August','September','October','November','December'].indexOf(b.month_name) + 1;
                            const monthStr = monthIdx < 10 ? '0' + monthIdx : monthIdx;
                            
                            this.form = {
                                id: b.id, resident_id: b.resident_id, apt_id: b.apt_id, 
                                bill_month: `${b.year}-${monthStr}`, due_date: b.due_date, 
                                status: b.status, notes: b.notes || '', discount: parseFloat(b.discount||0), tax: 0,
                                items: b.items.map(it => ({
                                    id: Math.random(), utility_type_id: it.utility_type_id,
                                    description: it.description, quantity: parseFloat(it.quantity||1), unit_price: parseFloat(it.unit_price||it.amount)
                                }))
                            };
                            
                            const sub = this.calculateSubtotal() - this.form.discount;
                            if (sub > 0 && b.tax > 0) this.form.tax = (parseFloat(b.tax) / sub) * 100;
                            
                        }
                    } catch (e) { this.showToast('Error loading details', 'error'); }
                    this.loadingDetails = false;
                },

                async deleteBill(id) {
                    if(!confirm('Delete this invoice permanently?')) return;
                    try {
                        const res = await fetch('<?= BASE_URL ?>api/billing.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'delete_bill', bill_id: id })
                        });
                        this.showToast('Invoice deleted');
                        this.fetchBills();
                        this.fetchAnalytics();
                    } catch(e) {}
                },

                openPaymentModal(bill) {
                    this.paymentData = { id: bill.id, bill_number: bill.bill_number, total: bill.total_amount, paid: bill.paid_amount };
                    this.paymentForm = { amount: (bill.total_amount - bill.paid_amount).toFixed(2), method: 'Cash', notes: '' };
                    this.paymentModalOpen = true;
                },

                async submitPayment() {
                    try {
                        const res = await fetch('<?= BASE_URL ?>api/billing.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'record_payment', bill_id: this.paymentData.id, amount_paid: this.paymentForm.amount, ...this.paymentForm })
                        });
                        const json = await res.json();
                        if (json.success) {
                            this.showToast(json.message);
                            this.paymentModalOpen = false;
                            this.fetchBills();
                            this.fetchAnalytics();
                        } else {
                            this.showToast(json.message, 'error');
                        }
                    } catch(e) { this.showToast('Payment failed', 'error'); }
                },

                async downloadPDF(id) {
                    this.showToast('Generating PDF...');
                    try {
                        const res = await fetch(`<?= BASE_URL ?>api/billing.php?action=get_bill_details&bill_id=${id}`);
                        const json = await res.json();
                        if (json.success) {
                            const b = json.bill;
                            
                            document.getElementById('pdf-bill-number').innerText = b.bill_number;
                            document.getElementById('pdf-date').innerText = "Issued: " + new Date().toLocaleDateString();
                            document.getElementById('pdf-resident').innerText = b.full_name;
                            document.getElementById('pdf-apt').innerText = "Apt " + (b.apt_number || 'N/A');
                            document.getElementById('pdf-due-date').innerText = "Due: " + b.due_date;
                            
                            let tbody = '';
                            let subtotal = 0;
                            b.items.forEach(it => {
                                const q = parseFloat(it.quantity||1);
                                const p = parseFloat(it.unit_price||it.amount);
                                const t = q * p;
                                subtotal += t;
                                tbody += `<tr class="border-b border-slate-100"><td class="py-3 font-medium">${it.description}</td><td class="py-3 text-center">${q}</td><td class="py-3 text-right">${this.formatCurrency(p)}</td><td class="py-3 text-right font-bold">${this.formatCurrency(t)}</td></tr>`;
                            });
                            document.getElementById('pdf-items').innerHTML = tbody;
                            
                            const discount = parseFloat(b.discount||0);
                            const tax = parseFloat(b.tax||0);
                            const grand = (subtotal - discount) + tax;

                            document.getElementById('pdf-subtotal').innerText = this.formatCurrency(subtotal);
                            document.getElementById('pdf-discount').innerText = '-' + this.formatCurrency(discount);
                            document.getElementById('pdf-tax').innerText = this.formatCurrency(tax);
                            document.getElementById('pdf-grand').innerText = this.formatCurrency(grand);
                            document.getElementById('pdf-notes').innerText = b.notes || '';
                            document.getElementById('pdf-total').innerText = this.formatCurrency(grand);

                            const element = document.getElementById('invoice-pdf-template');
                            element.classList.remove('hidden');
                            
                            var opt = {
                                margin: 0,
                                filename: `${b.bill_number}.pdf`,
                                image: { type: 'jpeg', quality: 0.98 },
                                html2canvas: { scale: 2 },
                                jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
                            };
                            
                            await html2pdf().set(opt).from(element).save();
                            element.classList.add('hidden');
                            this.showToast('PDF downloaded successfully');
                        }
                    } catch(e) {
                        this.showToast('Failed to generate PDF', 'error');
                        document.getElementById('invoice-pdf-template').classList.add('hidden');
                    }
                }
            }));
        });
        lucide.createIcons();

        function showProcessingModal(title = 'Processing', message = 'Please wait...', type = 'loading') {
            const modal = document.getElementById('processing-modal');
            document.getElementById('modal-title').textContent = title;
            document.getElementById('modal-message').textContent = message;
            
            const iconBg = document.getElementById('modal-icon-bg');
            const iconWrapper = document.getElementById('modal-icon-wrapper');
            const okBtn = document.getElementById('modal-ok-btn');
            const topBar = document.getElementById('modal-top-bar');
            
            if (type === 'success') { 
                iconBg.className = 'w-12 h-12 bg-emerald-50 text-emerald-500 border border-emerald-100 rounded-full flex items-center justify-center shrink-0 shadow-sm'; 
                iconWrapper.innerHTML = '<i data-lucide="check-circle" class="w-6 h-6"></i>'; 
                topBar.className = 'absolute top-0 left-0 w-full h-1 bg-emerald-500';
                okBtn.className = 'w-full mt-4 py-2.5 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 font-black text-xs rounded-xl transition-colors cursor-pointer hidden border border-emerald-200'; 
            }
            else if (type === 'error') { 
                iconBg.className = 'w-12 h-12 bg-rose-50 text-rose-500 border border-rose-100 rounded-full flex items-center justify-center shrink-0 shadow-sm'; 
                iconWrapper.innerHTML = '<i data-lucide="alert-circle" class="w-6 h-6"></i>'; 
                topBar.className = 'absolute top-0 left-0 w-full h-1 bg-rose-500';
                okBtn.className = 'w-full mt-4 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 font-black text-xs rounded-xl transition-colors cursor-pointer block border border-rose-200'; 
                okBtn.textContent = 'Close'; 
            }
            else { 
                iconBg.className = 'w-12 h-12 bg-blue-50 text-blue-500 border border-blue-100 rounded-full flex items-center justify-center shrink-0 shadow-sm'; 
                iconWrapper.innerHTML = '<i data-lucide="loader-2" class="w-6 h-6 animate-spin"></i>'; 
                topBar.className = 'absolute top-0 left-0 w-full h-1 bg-blue-500';
                okBtn.className = 'w-full mt-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-xs rounded-xl transition-colors cursor-pointer hidden border border-slate-200'; 
            }
            lucide.createIcons();
            modal.classList.remove('hidden'); modal.classList.add('flex');
            setTimeout(() => { modal.classList.remove('opacity-0'); modal.firstElementChild.classList.remove('scale-95'); }, 10);
        }
        function closeProcessingModal() {
            const modal = document.getElementById('processing-modal');
            modal.classList.add('opacity-0'); modal.firstElementChild.classList.add('scale-95');
            setTimeout(() => { modal.classList.remove('flex'); modal.classList.add('hidden'); }, 300);
        }
    </script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>