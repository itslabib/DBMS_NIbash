<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] ?? null) != 1) {
    header('Location: ' . BASE_URL . 'index.php?error=unauthorized');
    exit();
}
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
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCTV Surveillance | Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">

    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        .hover-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .hover-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px -8px rgba(16, 185, 129, 0.12);
            border-color: #6ee7b7;
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #ecfdf5;
        }

        ::-webkit-scrollbar-thumb {
            background: #a7f3d0;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #6ee7b7;
        }

        .horizontal-scroll::-webkit-scrollbar {
            height: 6px;
        }

        .horizontal-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .horizontal-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        .canvas-shadow {
            box-shadow: 0 40px 100px -20px rgba(0, 0, 0, 0.1), 0 20px 40px -20px rgba(0, 0, 0, 0.05);
        }

        .scan-line {
            position: absolute;
            inset: 0 auto auto 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(180deg, transparent 0%, rgba(16, 185, 129, 0.1) 48%, transparent 100%);
            animation: scanMove 4s linear infinite;
            pointer-events: none;
        }

        @keyframes scanMove {
            0% {
                transform: translateY(-100%);
            }

            100% {
                transform: translateY(100%);
            }
        }

        @keyframes swapIn {
            0% {
                opacity: 0;
                transform: scale(0.96) translateY(12px);
            }

            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes swapOut {
            0% {
                opacity: 1;
                transform: scale(1);
            }

            100% {
                opacity: 0.72;
                transform: scale(0.985);
            }
        }

        .animate-swapIn {
            animation: swapIn 360ms ease-out both;
        }

        .animate-swapOut {
            animation: swapOut 260ms ease-out both;
        }
    </style>
</head>

<body class="bg-[#F0FAF4] text-slate-800 font-sans antialiased overflow-hidden flex"
    x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">

    <?php $active_page = 'cctv_surveillance.php';
    include '../includes/owner_sidebar.php'; ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[256px]' : 'lg:ml-[100px]'"
        class="flex-1 transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] flex flex-col h-screen pt-2 pb-4 px-4 sm:px-6 lg:px-8 relative">

        <div class="flex justify-center pt-8 pb-4 shrink-0">
            <a href="<?php echo BASE_URL; ?>index.php"
                class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-emerald-100/60 transition-all">
                <span class="w-8 h-8 rounded-xl bg-emerald-500 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800"
                    style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>

        <div
            class="bg-white rounded-[2.5rem] canvas-shadow flex-1 flex flex-col overflow-hidden border border-emerald-500/10 relative z-10">

            <header class="bg-white/90 backdrop-blur-xl border-b border-slate-100 sticky top-0 z-20 shrink-0">
                <div class="px-8 py-5 flex items-center gap-3">
                    <button @click="sidebarOpen = true"
                        class="lg:hidden w-8 h-8 flex items-center justify-center text-slate-500 hover:bg-slate-100 rounded-xl transition-colors mr-2">
                        <i data-lucide="menu" class="w-5 h-5"></i>
                    </button>
                    <span class="flex h-5 w-1.5 rounded-full bg-emerald-500 shadow-sm"></span>
                    <h2 class="tracking-widest uppercase text-[11px] text-slate-500 font-bold">CCTV Surveillance</h2>
                </div>
            </header>

            <div class="flex-1 overflow-y-auto bg-slate-50/30 p-6 sm:p-8">
                <div class="max-w-[1600px] mx-auto">

                    <div class="flex flex-col xl:flex-row items-start xl:items-center justify-between gap-4 mb-8">
                        <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
                            <div
                                class="bg-white border border-slate-200 rounded-xl px-4 py-2.5 flex items-center gap-2 shadow-sm hidden sm:flex">
                                <i data-lucide="clock" class="w-4 h-4 text-sky-500"></i>
                                <span id="liveClock" class="text-sm font-bold text-slate-700">--</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto">
                            <button id="refreshButton"
                                class="flex-1 xl:flex-none px-4 py-2.5 flex items-center justify-center gap-2 bg-white text-slate-600 font-bold text-sm hover:bg-emerald-50 hover:text-emerald-700 border border-slate-200 hover:border-emerald-200 rounded-xl transition-all shadow-sm">
                                <i data-lucide="refresh-cw" class="w-4 h-4"></i> <span
                                    class="hidden sm:inline">Refresh</span>
                            </button>
                            <a href="cctv_broadcast.php" target="_blank"
                                class="flex-1 xl:flex-none bg-emerald-100 hover:bg-emerald-200 text-emerald-800 px-5 py-2.5 rounded-xl font-bold text-sm flex items-center justify-center gap-2 shadow-sm transition-all border border-emerald-200">
                                <i data-lucide="smartphone" class="w-4 h-4"></i> Mobile Broadcast
                            </a>
                            <button id="addCameraButton"
                                class="flex-1 xl:flex-none bg-slate-900 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl font-bold text-sm flex items-center justify-center gap-2 shadow-md hover:shadow-lg transition-all">
                                <i data-lucide="plus" class="w-4 h-4"></i> Add Camera
                            </button>
                        </div>
                    </div>

                    <div id="wallStatusBar"
                        class="mb-6 bg-white border border-slate-200 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-3 w-3">
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span>
                            </span>
                            <span id="wallSummary" class="text-sm font-bold text-slate-700">Loading CCTV
                                layout...</span>
                        </div>
                        <div
                            class="flex items-center gap-2 text-xs font-bold text-slate-500 uppercase tracking-widest bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100">
                            <i data-lucide="wifi" class="w-3.5 h-3.5 text-emerald-500"></i> Live Connection Active
                        </div>
                    </div>

                    <div id="contentState" class="mb-8"></div>

                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 pb-6">

                        <div
                            class="xl:col-span-2 bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6 sm:p-8 flex flex-col">
                            <div
                                class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-50">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="p-2 bg-emerald-50 rounded-xl text-emerald-600 border border-emerald-100">
                                        <i data-lucide="image" class="w-5 h-5"></i></div>
                                    <h2 class="text-xl font-black text-slate-900 tracking-tight">Recent Captures</h2>
                                </div>
                                <div class="flex items-center gap-2 w-full sm:w-auto">
                                    <button id="viewAllCapturesBtn" onclick="openViewAllModal()"
                                        class="flex-1 sm:flex-none flex items-center justify-center gap-2 text-xs font-bold text-slate-500 hover:text-emerald-600 bg-slate-50 hover:bg-emerald-50 px-3 py-2 rounded-lg border border-slate-200 hover:border-emerald-200 transition-colors">
                                        <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i> View All
                                    </button>
                                    <button id="manualCapturesRefresh"
                                        class="flex-1 sm:flex-none flex items-center justify-center gap-2 text-xs font-bold text-slate-500 hover:text-emerald-600 bg-slate-50 hover:bg-emerald-50 px-3 py-2 rounded-lg border border-slate-200 hover:border-emerald-200 transition-colors">
                                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Reload
                                    </button>
                                </div>
                            </div>
                            <div id="capturesMount" class="horizontal-scroll flex gap-4 overflow-x-auto pb-4"></div>
                        </div>

                        <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6 sm:p-8 flex flex-col">
                            <div class="flex items-center justify-between mb-6 pb-4 border-b border-slate-50">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 bg-rose-50 rounded-xl text-rose-600 border border-rose-100"><i
                                            data-lucide="alert-triangle" class="w-5 h-5"></i></div>
                                    <h2 class="text-xl font-black text-slate-900 tracking-tight">System Alerts</h2>
                                </div>
                                <span id="floatingAlertCount"
                                    class="bg-rose-100 text-rose-700 font-black px-2.5 py-1 rounded-md text-xs shadow-sm">0</span>
                                <span id="alertCount" class="hidden"></span>
                            </div>
                            <div id="alertsMount"
                                class="space-y-3 flex-1 overflow-y-auto pr-2 custom-scrollbar max-h-[400px]"></div>
                        </div>

                    </div>

                    <!-- Undefined Persons Section -->
                    <div class="bg-white rounded-[2rem] border border-slate-200 shadow-sm p-6 sm:p-8 flex flex-col mb-6">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-4 border-b border-slate-50">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-amber-50 rounded-xl text-amber-600 border border-amber-100">
                                    <i data-lucide="user-x" class="w-5 h-5"></i>
                                </div>
                                <h2 class="text-xl font-black text-slate-900 tracking-tight">Undefined Persons</h2>
                            </div>
                        </div>
                        <div id="unknownCapturesMount" class="horizontal-scroll flex gap-4 overflow-x-auto pb-4"></div>
                    </div>

                </div>
            </div>
        </div>

        <div id="captureHistoryModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" id="closeCaptureBg"></div>
            <div
                class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 w-full max-w-2xl relative z-10 overflow-hidden flex flex-col max-h-[90vh]">
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-500 mb-1">Detection
                            History</p>
                        <h3 id="captureHistoryTitle" class="text-2xl font-black text-slate-900 tracking-tight">Recent
                            Detections</h3>
                    </div>
                    <button id="closeCaptureHistoryModal"
                        class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-rose-50 hover:text-rose-500 hover:border-rose-200 border border-slate-200 transition-all">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
                <div class="p-8 overflow-y-auto bg-slate-50/50 flex-1">
                    <div id="captureHistoryMeta" class="mb-6"></div>
                    <div id="captureHistoryList" class="space-y-3"></div>
                </div>
            </div>
        </div>
        </div>

        <div id="viewAllModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" id="closeViewAllBg"
                onclick="closeViewAllModalWindow()"></div>
            <div
                class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-100 w-full max-w-[80vw] h-[80vh] relative z-10 overflow-hidden flex flex-col">
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-emerald-50 rounded-xl text-emerald-600 border border-emerald-100"><i
                                data-lucide="layout-grid" class="w-5 h-5"></i></div>
                        <h3 class="text-2xl font-black text-slate-900 tracking-tight">All Recent Captures</h3>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="handleDeleteAllCaptures()"
                            class="flex items-center gap-2 px-4 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 hover:text-rose-700 text-xs font-bold rounded-xl border border-rose-100 hover:border-rose-200 transition-colors">
                            <i data-lucide="trash-2" class="w-4 h-4"></i> Delete All
                        </button>
                        <button id="closeViewAllModal" onclick="closeViewAllModalWindow()"
                            class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-rose-50 hover:text-rose-500 hover:border-rose-200 border border-slate-200 transition-all">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
                <div class="p-8 overflow-y-auto bg-slate-50/50 flex-1 custom-scrollbar">
                    <div id="viewAllMount" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-6">
                    </div>
                </div>
            </div>
        </div>

        <div id="addCameraModal" class="fixed inset-0 z-[100] hidden items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm" id="closeAddCameraBg"></div>
            <div
                class="bg-white rounded-[2.5rem] shadow-2xl border border-emerald-100 w-full max-w-xl relative z-10 overflow-hidden flex flex-col">
                <div class="px-8 py-6 border-b border-slate-100 flex items-center justify-between bg-white">
                    <div class="flex items-center gap-4">
                        <div
                            class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shadow-inner border border-emerald-100">
                            <i data-lucide="video" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl font-black text-slate-900 tracking-tight">Add Camera</h3>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-1">Register new
                                device</p>
                        </div>
                    </div>
                    <button id="closeAddCameraModal"
                        class="w-10 h-10 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-rose-50 hover:text-rose-500 hover:border-rose-200 border border-slate-200 transition-all">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>

                <div class="p-8 bg-slate-50/50">
                    <form id="addCameraForm" class="space-y-5">
                        <div id="formError"
                            class="hidden rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-600 flex items-center gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4"></i> <span id="formErrorText"></span>
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">Camera
                                Name <span class="text-emerald-500">*</span></label>
                            <input type="text" id="cameraName" placeholder="e.g., Main Entrance" required
                                class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-sm">
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">Camera
                                Type</label>
                            <select id="cameraType"
                                class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-sm appearance-none cursor-pointer"
                                onchange="document.getElementById('ipAddressContainer').style.display = this.value === 'builtin' ? 'none' : 'block'">
                                <option value="ip">External IP Camera (e.g. IP Webcam App)</option>
                                <option value="builtin">Built-in Mobile Device Camera (Broadcast)</option>
                            </select>
                        </div>

                        <div id="ipAddressContainer">
                            <label class="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">IP
                                Address / Stream URL</label>
                            <input type="text" id="ipAddress" placeholder="e.g., 192.168.1.100:8080"
                                class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-sm font-mono tracking-wide">
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">Location
                                Description</label>
                            <input type="text" id="locationDescription" placeholder="e.g., Building Hallway"
                                class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-sm">
                        </div>

                        <div>
                            <label
                                class="block text-[11px] font-black text-slate-500 uppercase tracking-widest mb-2">Status</label>
                            <select id="cameraStatus"
                                class="w-full rounded-xl border-2 border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-900 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 outline-none transition-all shadow-sm appearance-none cursor-pointer">
                                <option value="active">Active (Monitoring)</option>
                                <option value="inactive">Inactive (Offline)</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-200 mt-6">
                            <button type="button" id="cancelAddCameraBtn"
                                class="rounded-xl border-2 border-slate-200 bg-white px-6 py-3 text-sm font-black text-slate-600 transition-all hover:bg-slate-50 shadow-sm">
                                Cancel
                            </button>
                            <button type="submit" id="submitAddCameraBtn"
                                class="flex items-center gap-2 rounded-xl bg-slate-900 hover:bg-emerald-600 px-6 py-3 text-sm font-black text-white transition-all shadow-md hover:shadow-lg disabled:opacity-50 group">
                                <i data-lucide="check-circle"
                                    class="w-4 h-4 group-hover:scale-110 transition-transform"></i> Add Camera
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </main>

    <script>
        // Utility to initialize Lucide icons dynamically
        function refreshIcons() {
            setTimeout(() => { if (window.lucide) lucide.createIcons(); }, 10);
        }

        const CCTV_API_URL = '<?= BASE_URL ?>api/cctv.php';

        const state = {
            buildingId: '', buildings: [], devices: [], captures: [], alerts: [],
            unreadCount: 0, loading: true, error: null, lastRefreshedAt: null,
            lastSwap: null, currentBuildingLabel: ''
        };

        const liveClock = document.getElementById('liveClock');
        const wallSummary = document.getElementById('wallSummary');
        const wallState = document.getElementById('contentState');
        const capturesMount = document.getElementById('capturesMount');
        const alertsMount = document.getElementById('alertsMount');
        const alertCount = document.getElementById('alertCount');
        const floatingAlertCount = document.getElementById('floatingAlertCount');
        const refreshButton = document.getElementById('refreshButton');
        const captureHistoryModal = document.getElementById('captureHistoryModal');
        const captureHistoryTitle = document.getElementById('captureHistoryTitle');
        const captureHistoryMeta = document.getElementById('captureHistoryMeta');
        const captureHistoryList = document.getElementById('captureHistoryList');
        const closeCaptureHistoryModal = document.getElementById('closeCaptureHistoryModal');
        const closeCaptureBg = document.getElementById('closeCaptureBg');

        const addCameraButton = document.getElementById('addCameraButton');
        const addCameraModal = document.getElementById('addCameraModal');
        const addCameraForm = document.getElementById('addCameraForm');
        const closeAddCameraModal = document.getElementById('closeAddCameraModal');
        const cancelAddCameraBtn = document.getElementById('cancelAddCameraBtn');
        const submitAddCameraBtn = document.getElementById('submitAddCameraBtn');
        const formError = document.getElementById('formError');
        const formErrorText = document.getElementById('formErrorText');
        const cameraNameInput = document.getElementById('cameraName');
        const ipAddressInput = document.getElementById('ipAddress');
        const locationDescriptionInput = document.getElementById('locationDescription');
        const cameraStatusSelect = document.getElementById('cameraStatus');
        const closeAddCameraBg = document.getElementById('closeAddCameraBg');

        function escapeHtml(value) {
            return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function formatTimeAgo(input) {
            if (!input) return 'Just now';
            const timestamp = new Date(input.replace(' ', 'T'));
            if (Number.isNaN(timestamp.getTime())) return 'Just now';
            const diffMs = Date.now() - timestamp.getTime();
            const diffMinutes = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);
            if (diffMinutes < 1) return 'Just now';
            if (diffMinutes < 60) return `${diffMinutes} min${diffMinutes === 1 ? '' : 's'} ago`;
            if (diffHours < 24) return `${diffHours} hr${diffHours === 1 ? '' : 's'} ago`;
            return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
        }

        function formatDateTime(input) {
            if (!input) return '--';
            const timestamp = new Date(input.replace(' ', 'T'));
            if (Number.isNaN(timestamp.getTime())) return input;
            return timestamp.toLocaleString('en-US', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
        }

        function getDetectionMeta(type) {
            const map = {
                face: { label: 'Face', color: '#047857', bg: '#ecfdf5', border: '#a7f3d0' },
                unknown: { label: 'Unknown', color: '#b45309', bg: '#fffbeb', border: '#fde68a' },
                motion: { label: 'Motion', color: '#0369a1', bg: '#f0f9ff', border: '#bae6fd' },
                intruder: { label: 'Intruder', color: '#be123c', bg: '#fff1f2', border: '#fecdd3' }
            };
            return map[type] || map.motion;
        }

        function getAlertMeta(type) {
            const map = {
                unknown_face: { icon: 'x-circle', color: '#be123c', bg: '#fff1f2', border: '#fecdd3' },
                motion_detected: { icon: 'alert-triangle', color: '#b45309', bg: '#fffbeb', border: '#fde68a' },
                intruder_alert: { icon: 'activity', color: '#be123c', bg: '#fff1f2', border: '#fecdd3' }
            };
            return map[type] || map.unknown_face;
        }

        function formatClock() {
            const now = new Date();
            if (liveClock) liveClock.textContent = now.toLocaleString('en-US', { weekday: 'short', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
        }

        function renderSkeleton() {
            wallState.innerHTML = `
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="bg-white border border-slate-200 overflow-hidden rounded-[2rem] p-6 shadow-sm">
                        <div class="animate-pulse bg-slate-200 mb-4 h-6 w-40 rounded-full"></div>
                        <div class="animate-pulse bg-slate-200 h-[22rem] rounded-[1.5rem]"></div>
                    </div>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div class="bg-white border border-slate-200 overflow-hidden rounded-[1.5rem] p-5"><div class="animate-pulse bg-slate-200 mb-3 h-4 w-32 rounded-full"></div><div class="animate-pulse bg-slate-200 h-36 rounded-xl"></div></div>
                        <div class="bg-white border border-slate-200 overflow-hidden rounded-[1.5rem] p-5"><div class="animate-pulse bg-slate-200 mb-3 h-4 w-32 rounded-full"></div><div class="animate-pulse bg-slate-200 h-36 rounded-xl"></div></div>
                    </div>
                </div>
            `;
            capturesMount.innerHTML = Array.from({ length: 4 }).map(() => `
                <div class="bg-white border border-slate-200 w-[260px] shrink-0 rounded-[1.5rem] p-4 shadow-sm">
                    <div class="animate-pulse bg-slate-200 h-36 rounded-xl"></div>
                    <div class="mt-4 space-y-2">
                        <div class="animate-pulse bg-slate-200 h-4 w-24 rounded-full"></div>
                        <div class="animate-pulse bg-slate-200 h-3 w-32 rounded-full"></div>
                    </div>
                </div>
            `).join('');
            alertsMount.innerHTML = Array.from({ length: 3 }).map(() => `
                <div class="rounded-2xl border border-slate-100 bg-slate-50 p-4">
                    <div class="animate-pulse bg-slate-200 h-4 w-40 rounded-full"></div>
                    <div class="animate-pulse bg-slate-200 mt-3 h-3 w-full rounded-full"></div>
                </div>
            `).join('');
            refreshIcons();
        }

        function renderError(message) {
            wallState.innerHTML = `
                <div class="bg-white border border-slate-200 rounded-[2rem] p-10 text-center shadow-sm">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-50 text-rose-500 border border-rose-100 mb-4">
                        <i data-lucide="alert-circle" class="w-8 h-8"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-900">Unable to load feeds</h3>
                    <p class="mx-auto mt-2 text-sm font-medium text-slate-500">${escapeHtml(message || 'Service unavailable. Please retry.')}</p>
                    <button id="retryButton" class="mt-6 inline-flex items-center gap-2 rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white transition hover:bg-emerald-600 shadow-md">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i> Retry Connection
                    </button>
                </div>
            `;
            document.getElementById('retryButton').addEventListener('click', () => loadDashboard(state.buildingId));
            wallSummary.textContent = 'Feed refresh failed.';
            refreshIcons();
        }

        function renderNoDevices() {
            wallState.innerHTML = `
                <div class="bg-white border border-slate-200 rounded-[2rem] p-16 text-center shadow-sm">
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-[1.5rem] bg-emerald-50 text-emerald-500 border-2 border-dashed border-emerald-200 mb-6">
                        <i data-lucide="video-off" class="w-10 h-10"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-900">No Cameras Registered</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm font-medium text-slate-500">This building currently has no cameras. Click 'Add Camera' to populate the video wall.</p>
                </div>
            `;
            refreshIcons();
        }

        function streamBadge(status) {
            if (status === 'active') return '<span class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-emerald-700"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Active</span>';
            if (status === 'maintenance') return '<span class="inline-flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-amber-700"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Maint</span>';
            return '<span class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1 text-[10px] font-black uppercase tracking-widest text-rose-700"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>Offline</span>';
        }

        function renderVideoCard(camera, mode) {
            const isHero = mode === 'hero';
            const swapClass = state.lastSwap && state.lastSwap.toId === camera.id && isHero
                ? 'animate-swapIn' : state.lastSwap && state.lastSwap.fromId === camera.id && !isHero
                    ? 'animate-swapOut opacity-80' : '';

            const shell = isHero ? 'lg:col-span-2' : '';
            const lastCapture = camera.last_capture || {};

            let streamUrl = '';
            let isPolling = false;
            let noStream = false;

            if (camera.ip_address === 'builtin') {
                isPolling = true;
                streamUrl = `../assets/cctv/streams/${camera.building_id}/cam_${camera.id}.jpg`;
            } else if (camera.ip_address) {
                streamUrl = camera.ip_address;
                // Auto-append http:// if missing
                if (!streamUrl.startsWith('http://') && !streamUrl.startsWith('https://') && !streamUrl.startsWith('rtsp://')) {
                    streamUrl = 'http://' + streamUrl;
                }
                // Convert /video to /shot.jpg to prevent lag
                if (streamUrl.includes(':8080') && !streamUrl.includes('/video') && !streamUrl.includes('/shot.jpg')) {
                    streamUrl = streamUrl.replace(/\/$/, '') + '/shot.jpg';
                    isPolling = true;
                } else if (streamUrl.includes('/video')) {
                    streamUrl = streamUrl.replace('/video', '/shot.jpg');
                    isPolling = true;
                } else if (streamUrl.includes('/shot.jpg')) {
                    isPolling = true;
                }
            } else {
                noStream = true;
            }

            const previewImage = lastCapture.image_url && !isPolling
                ? `<img src="${escapeHtml(lastCapture.image_url)}" class="absolute inset-0 h-full w-full object-cover">`
                : (streamUrl ? `<img src="${escapeHtml(streamUrl)}" class="${isPolling ? 'stream-img' : ''} absolute inset-0 h-full w-full object-cover" ${isPolling ? `data-src="${escapeHtml(streamUrl)}"` : ''} onerror="this.style.display='none'">` : '');

            const showPlaceholder = !lastCapture.image_url && noStream ? '<div class="absolute inset-0 bg-slate-100 flex items-center justify-center"><span class="text-slate-400 text-xs font-bold uppercase tracking-widest">No Stream</span></div>' : '';

            return `
                <article class="${shell} ${swapClass} bg-white rounded-[2rem] border border-slate-200 p-5 shadow-sm hover:shadow-lg transition-all group relative overflow-hidden cursor-pointer" data-camera-id="${camera.id}">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="space-y-1">
                            <div class="inline-flex items-center gap-1.5 text-sm font-black text-slate-900">
                                <i data-lucide="video" class="w-4 h-4 text-emerald-500"></i> ${escapeHtml(camera.camera_name)}
                            </div>
                            <div class="flex items-center gap-1.5 text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                                <i data-lucide="map-pin" class="w-3 h-3"></i> ${escapeHtml(camera.location_description || 'No location')}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            ${streamBadge(camera.status)}
                            <button type="button" class="delete-camera-btn flex h-7 w-7 items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-rose-600 hover:border-rose-200 hover:bg-rose-50 transition-colors" data-camera-id="${camera.id}" data-camera-name="${escapeHtml(camera.camera_name)}" title="Delete camera">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>
                    </div>

                    <div class="relative overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 ${isHero ? 'h-[20rem] sm:h-[26rem]' : 'h-[12rem]'}">
                        ${previewImage}
                        ${showPlaceholder}
                        <div class="scan-line"></div>
                        <div class="absolute bottom-3 left-3 bg-black/60 backdrop-blur-md text-white text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-md flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full ${camera.status === 'active' ? 'bg-red-500 animate-pulse' : 'bg-slate-400'}"></span> ${camera.status === 'active' ? 'Live' : 'REC'}
                        </div>
                    </div>
                </article>
            `;
        }

        function renderVideoWall() {
            const cameras = state.devices.slice();
            if (!cameras.length) {
                wallSummary.textContent = `No cameras found for ${state.currentBuildingLabel}`;
                renderNoDevices();
                return;
            }

            const heroCamera = cameras[0];
            const sideCameras = cameras.slice(1, 5);

            wallSummary.textContent = `${cameras.length} feeds connected to ${state.currentBuildingLabel}`;

            wallState.innerHTML = `
                <div class="grid gap-6 grid-cols-1 lg:grid-cols-3">
                    ${renderVideoCard(heroCamera, 'hero')}
                    <div class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 lg:content-start">
                        ${sideCameras.map((camera) => renderVideoCard(camera, 'support')).join('')}
                    </div>
                </div>
            `;

            refreshIcons();

            wallState.querySelectorAll('[data-camera-id]').forEach((element) => {
                element.addEventListener('click', (e) => {
                    if (e.target.closest('.delete-camera-btn')) return;
                    const cameraId = element.dataset.cameraId;
                    if (!cameraId || state.devices[0].id == cameraId) return;

                    const selectedIndex = state.devices.findIndex((c) => String(c.id) === String(cameraId));
                    if (selectedIndex <= 0) return;

                    const previousHeroId = state.devices[0].id;
                    const selectedCamera = state.devices.splice(selectedIndex, 1)[0];
                    state.devices.unshift(selectedCamera);
                    state.lastSwap = { fromId: previousHeroId, toId: selectedCamera.id };
                    renderVideoWall();
                    setTimeout(() => { state.lastSwap = null; renderVideoWall(); }, 380);
                });
            });

            wallState.querySelectorAll('.delete-camera-btn').forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    handleDeleteCamera(btn.dataset.cameraId, btn.dataset.cameraName);
                });
            });
        }

        function openViewAllModal() {
            document.getElementById('viewAllModal').classList.remove('hidden');
            document.getElementById('viewAllModal').classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeViewAllModalWindow() {
            document.getElementById('viewAllModal').classList.add('hidden');
            document.getElementById('viewAllModal').classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function renderCaptures() {
            if (alertCount) alertCount.textContent = state.unreadCount;
            if (floatingAlertCount) floatingAlertCount.textContent = state.unreadCount;

            if (!state.captures.length) {
                capturesMount.innerHTML = `<div class="w-full bg-slate-50 border border-slate-100 rounded-2xl p-8 text-center text-slate-500 font-bold text-sm">No recent captures available.</div>`;
                document.getElementById('unknownCapturesMount').innerHTML = `<div class="w-full bg-slate-50 border border-slate-100 rounded-2xl p-8 text-center text-slate-500 font-bold text-sm">No undefined persons detected.</div>`;
                document.getElementById('viewAllMount').innerHTML = '';
                refreshIcons();
                return;
            }

            const generateCard = (capture, isSmall = false) => {
                const detect = getDetectionMeta(capture.detection_type);
                const isResident = Boolean(capture.resident_name);
                const residentLine = isResident
                    ? `<span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-emerald-700"><i data-lucide="user-check" class="w-3.5 h-3.5"></i> ${escapeHtml(capture.resident_name)}</span>`
                    : `<span class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-wider text-amber-600"><i data-lucide="help-circle" class="w-3.5 h-3.5"></i> Unknown</span>`;

                const imageMarkup = capture.image_url
                    ? `<img src="${escapeHtml(capture.image_url)}" class="absolute inset-0 h-full w-full object-cover">`
                    : '<div class="absolute inset-0 bg-slate-100 flex items-center justify-center text-slate-300"><i data-lucide="camera-off" class="w-8 h-8"></i></div>';

                return `
                    <article class="group hover-card bg-white border border-slate-200 rounded-[1.5rem] p-4 ${isSmall ? 'w-[260px] shrink-0' : 'w-full'} cursor-pointer" data-capture-id="${capture.id}">
                        <div class="relative h-36 overflow-hidden rounded-xl bg-slate-50 border border-slate-100 mb-4">
                            ${imageMarkup}
                            <div class="absolute top-2 right-2 px-2 py-1 rounded text-[9px] font-black uppercase tracking-widest shadow-sm" style="color:${detect.color}; background:${detect.bg}; border:1px solid ${detect.border};">
                                ${detect.label}
                            </div>
                        </div>
                        <div class="space-y-2 relative">
                            <h3 class="text-sm font-black text-slate-900 truncate pr-8">${escapeHtml(capture.camera_name)}</h3>
                            <div class="flex items-center justify-between">
                                ${residentLine}
                                <span class="text-[10px] font-bold text-slate-400">${formatTimeAgo(capture.captured_at)}</span>
                            </div>
                            <button class="delete-capture-btn absolute -top-4 right-0 w-8 h-8 rounded-full bg-rose-50 border border-rose-100/50 text-rose-500 shadow-sm opacity-0 group-hover:opacity-100 transition-all hover:bg-rose-500 hover:text-white flex items-center justify-center" data-delete-id="${capture.id}" onclick="event.stopPropagation(); handleDeleteCapture(${capture.id});">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </article>
                `;
            };

            const recentCaptures = state.captures.filter(c => c.detection_type !== 'unknown');
            const unknownCaptures = state.captures.filter(c => c.detection_type === 'unknown');

            capturesMount.innerHTML = recentCaptures.slice(0, 12).map(c => generateCard(c, true)).join('') || `<div class="w-full bg-slate-50 border border-slate-100 rounded-2xl p-8 text-center text-slate-500 font-bold text-sm">No recent identified captures.</div>`;
            document.getElementById('unknownCapturesMount').innerHTML = unknownCaptures.slice(0, 12).map(c => generateCard(c, true)).join('') || `<div class="w-full bg-slate-50 border border-slate-100 rounded-2xl p-8 text-center text-slate-500 font-bold text-sm">No undefined persons detected.</div>`;
            
            document.getElementById('viewAllMount').innerHTML = state.captures.map(c => generateCard(c, false)).join('');

            refreshIcons();

            document.querySelectorAll('[data-capture-id]').forEach((element) => {
                element.addEventListener('click', () => showCaptureHistory(element.dataset.captureId));
            });
        }


        function renderAlerts() {
            if (!state.alerts.length) {
                alertsMount.innerHTML = `<div class="bg-slate-50 border border-slate-100 rounded-2xl p-6 text-center text-slate-500 font-bold text-sm">No recent security alerts.</div>`;
                refreshIcons();
                return;
            }

            alertsMount.innerHTML = state.alerts.map((alertItem) => {
                const meta = getAlertMeta(alertItem.alert_type);
                return `
                    <article class="bg-white border border-slate-200 rounded-2xl p-4 flex gap-4 items-start relative overflow-hidden group hover:border-slate-300 transition-colors">
                        <div class="absolute top-0 left-0 w-1 h-full" style="background:${meta.color}"></div>
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border" style="color:${meta.color}; background:${meta.bg}; border-color:${meta.border};">
                            <i data-lucide="${meta.icon}" class="w-5 h-5"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-black text-slate-900 truncate">${escapeHtml(alertItem.camera_name || alertItem.alert_type.replaceAll('_', ' '))}</h4>
                            <p class="text-xs font-medium text-slate-500 mt-1 line-clamp-2">${escapeHtml(alertItem.message)}</p>
                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-[10px] font-bold text-slate-400">${formatTimeAgo(alertItem.created_at)}</span>
                                <span class="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded" style="color:${meta.color}; background:${meta.bg};">${escapeHtml(alertItem.alert_type.replace('_', ' '))}</span>
                            </div>
                        </div>
                    </article>
                `;
            }).join('');
            refreshIcons();
        }

        function openCaptureHistoryModal() {
            captureHistoryModal.classList.remove('hidden');
            captureHistoryModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        }

        function closeCaptureHistoryModalWindow() {
            captureHistoryModal.classList.add('hidden');
            captureHistoryModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        function renderCaptureHistory(capture, history) {
            captureHistoryTitle.textContent = capture.resident_name ? capture.resident_name : 'Unknown Identifier';

            captureHistoryMeta.innerHTML = `
                ${capture.image_url ? `
                <div class="w-full h-64 bg-slate-900 rounded-2xl mb-4 overflow-hidden relative shadow-inner">
                    <img src="${escapeHtml(capture.image_url)}" class="absolute inset-0 w-full h-full object-contain">
                </div>
                ` : ''}
                <div class="bg-slate-50 border border-slate-200 rounded-2xl p-5 mb-2">
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-xs font-black uppercase tracking-widest text-slate-500">Capture Source</span>
                        <span class="bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase tracking-widest px-2.5 py-1 rounded-md">${history.length} Matches</span>
                    </div>
                    <div class="text-sm font-bold text-slate-800 flex items-center gap-2"><i data-lucide="video" class="w-4 h-4 text-emerald-500"></i> ${escapeHtml(capture.camera_name)}</div>
                    <div class="text-xs font-medium text-slate-500 mt-1">${escapeHtml(capture.location_description || 'No location set')}</div>
                </div>
            `;

            captureHistoryList.innerHTML = history.map((item) => `
                <div class="bg-white border border-slate-200 hover:border-emerald-200 transition-colors rounded-2xl p-4 flex items-center gap-4">
                    <div class="w-10 h-10 bg-slate-50 rounded-xl flex items-center justify-center border border-slate-100 text-slate-400">
                        <i data-lucide="clock" class="w-4 h-4"></i>
                    </div>
                    <div class="flex-1">
                        <div class="font-black text-sm text-slate-900">${escapeHtml(item.camera_name || 'Camera')}</div>
                        <div class="text-xs font-bold text-slate-500 mt-0.5">${escapeHtml(formatDateTime(item.captured_at))}</div>
                    </div>
                    <div class="bg-slate-100 text-slate-600 text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded border border-slate-200">
                        ${escapeHtml(item.detection_type || 'face')}
                    </div>
                </div>
            `).join('');
            refreshIcons();
        }

        async function showCaptureHistory(captureId) {
            const selected = state.captures.find(c => String(c.id) === String(captureId));
            if (!selected) return;

            captureHistoryTitle.textContent = 'Loading...';
            captureHistoryMeta.innerHTML = '';
            captureHistoryList.innerHTML = '<div class="text-center p-6"><i data-lucide="loader-2" class="w-6 h-6 animate-spin mx-auto text-emerald-500"></i></div>';
            openCaptureHistoryModal();
            refreshIcons();

            try {
                const response = await fetch(`${CCTV_API_URL}?action=get_capture_history&building_id=${encodeURIComponent(state.buildingId)}&capture_id=${encodeURIComponent(captureId)}`, { credentials: 'same-origin' });
                const payload = await response.json();
                if (!response.ok || payload.status !== 'success') throw new Error(payload.message);
                renderCaptureHistory(payload.capture || selected, payload.history || []);
            } catch (error) {
                captureHistoryTitle.textContent = 'History Error';
                captureHistoryList.innerHTML = `<div class="text-sm font-bold text-rose-500 text-center p-4">${escapeHtml(error.message)}</div>`;
            }
        }

        async function loadDashboard(buildingId = '') {
            try {
                state.loading = true; renderSkeleton();

                // If a building ID was passed directly or exists in state, use it
                const targetBuilding = buildingId || state.buildingId || '';

                const response = await fetch(`${CCTV_API_URL}?action=get_dashboard&building_id=${encodeURIComponent(targetBuilding)}`);
                const payload = await response.json();
                if (!response.ok || payload.status !== 'success') throw new Error(payload.message);

                Object.assign(state, payload, {
                    buildingId: payload.building_id || targetBuilding,
                    currentBuildingLabel: payload.building_label || payload.building_id,
                    loading: false
                });

                // Building state is now synced, render wall without dropdown logic
                renderVideoWall();
                renderCaptures();
                renderAlerts();
            } catch (error) {
                state.loading = false;
                renderError(error.message);
            }
        }

        async function loadCapturesAndAlerts(buildingId = '') {
            try {
                const response = await fetch(`${CCTV_API_URL}?action=get_dashboard&building_id=${encodeURIComponent(buildingId || state.buildingId || '')}`);
                const payload = await response.json();
                if (response.ok && payload.status === 'success') {
                    state.captures = payload.captures || [];
                    state.alerts = payload.alerts || [];
                    state.unreadCount = payload.unread_count || 0;
                    renderCaptures(); renderAlerts();
                }
            } catch (e) { }
        }

        // Camera Modal
        function openAddCameraModal() {
            addCameraForm.reset();
            formError.classList.add('hidden');
            submitAddCameraBtn.disabled = false;
            addCameraModal.classList.remove('hidden');
            addCameraModal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
            setTimeout(() => cameraNameInput.focus(), 100);
        }

        function closeAddCameraModalWindow() {
            addCameraModal.classList.add('hidden');
            addCameraModal.classList.remove('flex');
            document.body.classList.remove('overflow-hidden');
        }

        async function handleAddCamera(e) {
            e.preventDefault();
            formError.classList.add('hidden');
            submitAddCameraBtn.disabled = true;

            const type = document.getElementById('cameraType').value;
            const data = {
                camera_name: cameraNameInput.value.trim(),
                ip_address: type === 'builtin' ? 'builtin' : ipAddressInput.value.trim(),
                location_description: locationDescriptionInput.value.trim(),
                status: cameraStatusSelect.value
            };

            if (!data.camera_name || (type === 'ip' && !data.ip_address)) {
                formErrorText.textContent = 'Name and IP are required.';
                formError.classList.remove('hidden');
                submitAddCameraBtn.disabled = false;
                refreshIcons(); return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'add_device');
                formData.append('building_id', state.buildingId);
                Object.keys(data).forEach(k => formData.append(k, data[k]));

                const response = await fetch(CCTV_API_URL, { method: 'POST', body: formData });
                const payload = await response.json();
                if (!response.ok || payload.status !== 'success') throw new Error(payload.message);

                closeAddCameraModalWindow();
                loadDashboard(state.buildingId);
            } catch (error) {
                formErrorText.textContent = error.message || 'Failed to add camera';
                formError.classList.remove('hidden');
                submitAddCameraBtn.disabled = false;
                refreshIcons();
            }
        }

        async function handleDeleteCamera(id, name) {
            if (!confirm(`Delete camera "${name}" permanently?`)) return;
            try {
                const formData = new FormData();
                formData.append('action', 'delete_device');
                formData.append('building_id', state.buildingId);
                formData.append('device_id', id);

                const response = await fetch(CCTV_API_URL, { method: 'POST', body: formData });
                const payload = await response.json();
                if (!response.ok || payload.status !== 'success') throw new Error(payload.message);
                loadDashboard(state.buildingId);
            } catch (error) { alert(error.message); }
        }

        async function handleDeleteCapture(id) {
            if (!confirm(`Delete this capture permanently?`)) return;
            try {
                const formData = new FormData();
                formData.append('action', 'delete_capture');
                formData.append('building_id', state.buildingId);
                formData.append('capture_id', id);

                const response = await fetch(CCTV_API_URL, { method: 'POST', body: formData });
                const payload = await response.json();
                if (!response.ok || payload.status !== 'success') throw new Error(payload.message);
                loadDashboard(state.buildingId);
            } catch (error) { alert(error.message); }
        }

        async function handleDeleteAllCaptures() {
            if (!confirm(`Delete all recent captures permanently? This cannot be undone.`)) return;
            try {
                const formData = new FormData();
                formData.append('action', 'delete_all_captures');
                formData.append('building_id', state.buildingId);

                const response = await fetch(CCTV_API_URL, { method: 'POST', body: formData });
                const payload = await response.json();
                if (!response.ok || payload.status !== 'success') throw new Error(payload.message);

                closeViewAllModalWindow();
                loadDashboard(state.buildingId);
            } catch (error) { alert(error.message); }
        }

        // Listeners
        refreshButton.addEventListener('click', () => loadDashboard(state.buildingId));
        document.getElementById('manualCapturesRefresh').addEventListener('click', () => loadCapturesAndAlerts(state.buildingId));
        closeCaptureHistoryModal.addEventListener('click', closeCaptureHistoryModalWindow);
        closeCaptureBg.addEventListener('click', closeCaptureHistoryModalWindow);

        addCameraButton.addEventListener('click', openAddCameraModal);
        closeAddCameraModal.addEventListener('click', closeAddCameraModalWindow);
        closeAddCameraBg.addEventListener('click', closeAddCameraModalWindow);
        cancelAddCameraBtn.addEventListener('click', closeAddCameraModalWindow);
        addCameraForm.addEventListener('submit', handleAddCamera);

        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCaptureHistoryModalWindow();
                closeAddCameraModalWindow();
            }
        });

        formatClock();
        setInterval(formatClock, 1000);

        // Fast polling for JPEG streams (eliminates MJPEG browser lag)
        setInterval(() => {
            if (document.hidden) return;
            document.querySelectorAll('.stream-img').forEach(img => {
                if (img.dataset.src && !img.dataset.refreshing) {
                    img.dataset.refreshing = 'true';
                    const temp = new Image();
                    temp.onload = () => { img.src = temp.src; img.dataset.refreshing = ''; };
                    temp.onerror = () => { img.dataset.refreshing = ''; };
                    const separator = img.dataset.src.includes('?') ? '&' : '?';
                    temp.src = img.dataset.src + separator + 't=' + new Date().getTime();
                }
            });
        }, 250);

        // Auto-poll for new captures (or updated images of the same person)
        setInterval(() => {
            if (!document.hidden && !state.loading) {
                loadCapturesAndAlerts(state.buildingId);
            }
        }, 5000);

        loadDashboard();
    </script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>

</html>