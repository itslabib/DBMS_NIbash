<?php
session_start();
require_once 'includes/db_config.php';

// Fetch recent 3 successful scans
$recent_scans = [];
$query = "SELECT g.id, g.full_name as guest_name, e.id as entry_id, e.entry_time, e.verification_score, a.apt_number, p.full_name as host_name
          FROM entry_logs e
          JOIN visit_requests vr ON e.visit_id = vr.id
          JOIN guests g ON vr.guest_id = g.id
          JOIN apartments a ON vr.apt_id = a.id
          JOIN users u ON vr.resident_id = u.id
          LEFT JOIN user_profiles p ON u.id = p.user_id
          ORDER BY e.entry_time DESC LIMIT 3";
$res = @mysqli_query($conn, $query);
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $recent_scans[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gate Terminal Kiosk | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api/dist/face-api.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r134/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta@latest/dist/vanta.fog.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; }
        .clock-text { font-variant-numeric: tabular-nums; }
        @keyframes pulse-slow { 0%, 100% { opacity: 1; } 50% { opacity: 0.8; } }
        .animate-scan { animation: scan-line 2.5s ease-in-out infinite; }
        @keyframes scan-line { 0% { transform: translateY(0); opacity: 0; } 10% { opacity: 1; } 90% { opacity: 1; } 100% { transform: translateY(100%); opacity: 0; } }
        @keyframes scan { 0% { top: 0; } 50% { top: 100%; } 100% { top: 0; } }
        
        /* New Aesthetics */
        
        .glass-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(16, 185, 129, 0.2);
            box-shadow: 0 10px 40px -10px rgba(16, 185, 129, 0.1);
        }
        
        .glass-btn {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(16, 185, 129, 0.2);
            position: relative;
            overflow: hidden;
            box-shadow: 0 20px 50px -12px rgba(16, 185, 129, 0.15);
            /* Added active state for touch screens to give feedback */
            transition: transform 0.1s ease, box-shadow 0.1s ease;
        }
        .glass-btn:active {
            transform: scale(0.98);
            box-shadow: inset 0 4px 10px rgba(0,0,0,0.05);
        }
        
        .icon-container {
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen flex flex-col overflow-hidden relative selection:bg-emerald-500/30">
    
    <!-- Animated Background (Vanta.js) -->
    <div id="vanta-bg" class="fixed inset-0 z-0 pointer-events-none overflow-hidden bg-slate-50"></div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof VANTA !== 'undefined') {
                VANTA.FOG({
                    el: "#vanta-bg",
                    mouseControls: false,
                    touchControls: false,
                    gyroControls: false,
                    minHeight: 200.00,
                    minWidth: 200.00,
                    highlightColor: 0x6ee7b7, // Emerald 300
                    midtoneColor: 0xa7f3d0, // Emerald 200
                    lowlightColor: 0xecfdf5, // Emerald 50
                    baseColor: 0xf8fafc, // Slate 50
                    blurFactor: 0.60,
                    speed: 1.50,
                    zoom: 1.50
                });
            }
        });
    </script>

    <!-- Header / Clock -->
    <header class="p-8 flex justify-between items-start relative z-10 w-full max-w-7xl mx-auto mt-4">
        <div class="flex items-center gap-5">
            <div class="w-16 h-16 rounded-2xl bg-white/60 border border-white flex items-center justify-center backdrop-blur-md shadow-lg">
                <i data-lucide="shield-check" class="w-8 h-8 text-emerald-600 drop-shadow-[0_0_8px_rgba(16,185,129,0.3)]"></i>
            </div>
            <div>
                <h1 class="text-3xl font-black tracking-tight text-slate-800 drop-shadow-sm">Nibash Terminal</h1>
                <p class="text-sm text-emerald-600 font-bold flex items-center gap-2 mt-1 uppercase tracking-wider">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_10px_rgba(16,185,129,0.5)]"></span> Security Active
                </p>
            </div>
        </div>
        <div class="text-right glass-panel px-8 py-4 rounded-3xl">
            <div id="clock" class="text-6xl font-black tracking-tighter clock-text text-slate-800 drop-shadow-sm">00:00</div>
            <div id="date" class="text-slate-500 font-bold mt-1 uppercase tracking-widest text-xs">Loading Date...</div>
        </div>
    </header>

    <!-- Main Buttons Area -->
    <main class="flex-1 flex items-center justify-center p-8 relative z-10 gap-16 w-full max-w-6xl mx-auto">
        <!-- Face Scan Button -->
        <button onclick="toggleBiometricModal(true)" class="glass-btn relative w-full max-w-md aspect-square rounded-[3rem] p-10 flex flex-col items-center justify-center gap-8 shadow-[0_10px_40px_-10px_rgba(16,185,129,0.1)]">
            <div class="icon-container w-44 h-44 rounded-full bg-emerald-100 flex items-center justify-center border border-white shadow-[0_0_30px_rgba(16,185,129,0.1)]">
                <i data-lucide="scan-face" class="w-20 h-20 text-emerald-600 drop-shadow-[0_0_10px_rgba(16,185,129,0.2)]"></i>
            </div>
            <div class="text-center">
                <h2 class="text-4xl font-black text-slate-800 mb-3 tracking-tight">Face Scan</h2>
                <p class="text-slate-600 font-medium text-lg tracking-wide">Tap to verify Face Pass</p>
            </div>
            <div class="absolute bottom-8 right-8 w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shadow-md border border-emerald-100">
                <i data-lucide="arrow-right" class="w-6 h-6"></i>
            </div>
        </button>

        <!-- Parking Scan Button -->
        <button onclick="toggleParkingScannerModal(true)" class="glass-btn relative w-full max-w-md aspect-square rounded-[3rem] p-10 flex flex-col items-center justify-center gap-8 shadow-[0_10px_40px_-10px_rgba(16,185,129,0.1)]">
            <div class="icon-container w-44 h-44 rounded-full bg-emerald-100 flex items-center justify-center border border-white shadow-[0_0_30px_rgba(16,185,129,0.1)]">
                <i data-lucide="car-front" class="w-20 h-20 text-emerald-600 drop-shadow-[0_0_10px_rgba(16,185,129,0.2)]"></i>
            </div>
            <div class="text-center">
                <h2 class="text-4xl font-black text-slate-800 mb-3 tracking-tight">Vehicle Entry</h2>
                <p class="text-slate-600 font-medium text-lg tracking-wide">Tap to scan License Plate</p>
            </div>
            <div class="absolute bottom-8 right-8 w-14 h-14 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shadow-md border border-emerald-100">
                <i data-lucide="arrow-right" class="w-6 h-6"></i>
            </div>
        </button>
    </main>

    <!-- Footer removed as per user request to fit perfectly on screen without scrolling -->

    <!-- MODALS -->
    <!-- Biometric Modal -->
    <div id="biometric-modal" class="hidden fixed inset-0 bg-slate-950/80 z-[100] flex items-center justify-center p-4 backdrop-blur-xl transition-opacity duration-300" aria-modal="true">
        <div id="biometric-panel" class="glass-panel rounded-3xl shadow-[0_0_50px_rgba(16,185,129,0.15)] overflow-hidden max-w-4xl w-full max-h-[95vh] transform transition-all duration-300 scale-95 opacity-0 flex flex-col border border-white/10">
            <div class="px-8 py-6 border-b border-white/10 flex justify-between items-center bg-slate-900/40 relative">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center border border-emerald-500/30 shadow-[0_0_15px_rgba(16,185,129,0.2)]">
                        <i data-lucide="cpu" class="w-6 h-6 text-emerald-400"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white tracking-tight">Biometric Terminal</h3>
                        <p class="text-xs uppercase tracking-wider text-emerald-400 font-semibold mt-1">System Active</p>
                    </div>
                </div>
                <button onclick="toggleBiometricModal(false)" class="w-12 h-12 flex items-center justify-center rounded-xl bg-white/5 hover:bg-rose-500/20 text-slate-300 hover:text-rose-400 border border-white/10 hover:border-rose-500/30 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <div class="p-6 bg-slate-900/40 flex flex-col items-center relative flex-1 overflow-y-auto min-h-0">
                <div class="relative w-full aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl border border-slate-800" id="video-container">
                    <div id="bio-status-container" class="absolute top-6 left-0 right-0 w-full text-center z-40 flex justify-center pointer-events-none">
                        <p id="bio-status" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-full bg-slate-800/90 backdrop-blur-md border border-slate-600 shadow-xl text-white text-base font-semibold pointer-events-auto">
                            <i data-lucide="loader-2" class="w-5 h-5 animate-spin text-emerald-400"></i> Initializing Hardware...
                        </p>
                    </div>
                    <video id="gate-video" class="w-full h-full object-cover transform scale-x-[-1]" autoplay muted playsinline></video>
                    <div id="scan-line" class="absolute left-0 right-0 h-1 bg-emerald-500 shadow-[0_0_20px_rgba(16,185,129,1)] opacity-0 z-20 pointer-events-none"></div>
                    <div id="scan-overlay" class="absolute inset-0 border-[4px] border-emerald-500/40 opacity-0 transition-opacity duration-200 z-10 pointer-events-none rounded-2xl">
                        <div class="absolute top-0 left-0 w-12 h-12 border-t-[4px] border-l-[4px] border-emerald-500 rounded-tl-2xl"></div>
                        <div class="absolute top-0 right-0 w-12 h-12 border-t-[4px] border-r-[4px] border-emerald-500 rounded-tr-2xl"></div>
                        <div class="absolute bottom-0 left-0 w-12 h-12 border-b-[4px] border-l-[4px] border-emerald-500 rounded-bl-2xl"></div>
                        <div class="absolute bottom-0 right-0 w-12 h-12 border-b-[4px] border-r-[4px] border-emerald-500 rounded-br-2xl"></div>
                    </div>
                    <canvas id="gate-canvas" class="absolute inset-0 z-30 pointer-events-none w-full h-full object-cover"></canvas>
                </div>
                <div id="match-result" class="w-full p-6 rounded-2xl text-center hidden opacity-0 transition-all transform translate-y-4 z-20 mt-6 border shadow-lg"></div>
            </div>
        </div>
    </div>

    <!-- Parking Scanner Modal -->
    <div id="parking-scanner-modal" class="hidden fixed inset-0 bg-slate-950/80 z-[100] flex items-center justify-center p-4 backdrop-blur-xl transition-opacity duration-300" aria-modal="true">
        <div id="parking-scanner-panel" class="glass-panel rounded-3xl shadow-[0_0_50px_rgba(59,130,246,0.15)] overflow-hidden max-w-4xl w-full max-h-[95vh] transform transition-all duration-300 scale-95 opacity-0 flex flex-col border border-white/10">
            <div class="px-8 py-6 border-b border-white/10 flex justify-between items-center bg-slate-900/40 relative">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center border border-blue-500/30 shadow-[0_0_15px_rgba(59,130,246,0.2)]">
                        <i data-lucide="car-front" class="w-6 h-6 text-blue-400"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white tracking-tight">Parking Scanner</h3>
                        <p class="text-xs uppercase tracking-wider text-blue-400 font-semibold mt-1">LPR Engine Active</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <button id="parking-flip-btn" class="w-12 h-12 flex items-center justify-center rounded-xl bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white border border-white/10 transition-colors" title="Flip Camera">
                        <i data-lucide="flip-horizontal" class="w-6 h-6"></i>
                    </button>
                    <button onclick="toggleParkingScannerModal(false)" class="w-12 h-12 flex items-center justify-center rounded-xl bg-white/5 hover:bg-rose-500/20 text-slate-300 hover:text-rose-400 border border-white/10 hover:border-rose-500/30 transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
            
            <div class="p-6 bg-slate-900/40 flex flex-col items-center relative flex-1 overflow-y-auto min-h-0">
                <div class="relative w-full aspect-video bg-black rounded-2xl overflow-hidden shadow-2xl border border-slate-800" id="parking-video-container">
                    <div id="parking-bio-status-container" class="absolute top-6 left-0 right-0 w-full text-center z-40 flex justify-center pointer-events-none">
                        <p id="parking-bio-status" class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-full bg-slate-800/90 backdrop-blur-md border border-slate-600 shadow-xl text-white text-base font-semibold pointer-events-auto">
                            <i data-lucide="loader-2" class="w-5 h-5 animate-spin text-blue-400"></i> Initializing Camera...
                        </p>
                    </div>
                    <video id="parking-webcam" class="w-full h-full object-cover" autoplay playsinline></video>
                    <!-- Scanner Overlay -->
                    <div class="absolute inset-0 flex items-center justify-center z-10 pointer-events-none">
                        <div class="w-[80%] max-w-[500px] h-[150px] border-4 border-dashed border-blue-500/60 rounded-xl relative overflow-hidden" style="box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.7);">
                            <div id="parking-scan-line" class="w-full h-1 bg-blue-500 shadow-[0_0_15px_#3b82f6] absolute top-0 left-0 hidden" style="animation: scan 2s infinite linear;"></div>
                        </div>
                    </div>
                    <canvas id="parking-canvas" class="hidden"></canvas>
                    <canvas id="parking-crop-canvas" class="hidden"></canvas>
                </div>
                <div id="parking-debug-text" class="absolute bottom-10 bg-slate-900/90 border border-slate-700 px-6 py-3 rounded-xl text-blue-400 font-mono text-lg tracking-widest opacity-0 transition-opacity z-20 pointer-events-none shadow-xl">
                    Scanning...
                </div>
                <div id="parking-match-result" class="w-full p-6 rounded-2xl text-center hidden opacity-0 transition-all transform translate-y-4 z-20 mt-6 border shadow-lg"></div>
            </div>
        </div>
    </div>

    <!-- Logic Script -->
    <script>
        lucide.createIcons();

        // Clock Logic
        function updateClock() {
            const now = new Date();
            let h = now.getHours();
            let m = now.getMinutes();
            let ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            h = h ? h : 12;
            m = m < 10 ? '0' + m : m;
            document.getElementById('clock').innerText = h + ':' + m;
            
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.getElementById('date').innerText = now.toLocaleDateString('en-US', options) + ' • ' + ampm;
        }
        setInterval(updateClock, 1000);
        updateClock();

        // --- Core Web Terminal Face Recog logic ---
        let videoStream = null;
        let isFaceRecogRunning = false;
        let labeledDescriptors = null;
        let faceMatcher = null;
        let scanInterval = null;
        let modelsLoaded = false;
        let scanAttemptCount = 0;
        let isBiometricModalOpen = false;
        let guestsLoaded = false;
        let isCameraWarmingUp = false;

        const BASE_URL = '<?= BASE_URL ?>';

        async function preloadModels() {
            if (modelsLoaded) return;
            const MODEL_URL = 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api/model/';
            try {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                    faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL),
                    faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL)
                ]);
                modelsLoaded = true;
                const dummyCanvas = document.createElement('canvas');
                dummyCanvas.width = 100; dummyCanvas.height = 100;
                await faceapi.detectAllFaces(dummyCanvas, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptors();
            } catch (err) { console.error("Error preloading models:", err); }
        }

        async function fetchGuestSignatures(isPreload = false) {
            if (guestsLoaded) return;
            try {
                const response = await fetch(BASE_URL + 'api/gate.php?action=get_guests');
                const data = await response.json();
                if (data.status !== 'success' || !data.data || data.data.length === 0) {
                    if (!isPreload) faceMatcher = null;
                    guestsLoaded = true;
                    return;
                }
                labeledDescriptors = data.data.map(guest => {
                    const descArray = Object.values(guest.face_descriptor);
                    const float32Desc = new Float32Array(descArray);
                    return new faceapi.LabeledFaceDescriptors(guest.id + ':' + guest.guest_name + ':' + guest.guest_type, [float32Desc]);
                });
                faceMatcher = new faceapi.FaceMatcher(labeledDescriptors, 0.45);
                guestsLoaded = true;
            } catch (err) {
                console.error(err);
                if (!isPreload) document.getElementById('bio-status').innerText = 'Error loading database.';
            }
        }

        async function startVideo() {
            const video = document.getElementById('gate-video');
            const bioStatus = document.getElementById('bio-status');
            try {
                bioStatus.innerHTML = '<span class="text-slate-200"><i data-lucide="camera" class="w-5 h-5 inline mr-2 text-emerald-400"></i> Connecting camera...</span>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
                videoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
                if (!isBiometricModalOpen) {
                    videoStream.getTracks().forEach(track => track.stop());
                    videoStream = null;
                    return;
                }
                video.srcObject = videoStream;
                return new Promise((resolve) => {
                    video.onloadedmetadata = () => {
                        bioStatus.innerHTML = '<span class="text-emerald-400 font-bold"><i data-lucide="scan" class="w-5 h-5 inline mr-2"></i> Scan active. Please look at camera.</span>';
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                        isCameraWarmingUp = true;
                        setTimeout(() => { isCameraWarmingUp = false; }, 2000);
                        resolve();
                    };
                });
            } catch (err) {
                console.error(err);
                bioStatus.innerHTML = '<span class="text-rose-400 font-bold"><i data-lucide="video-off" class="w-5 h-5 inline mr-2"></i> Camera access denied.</span>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }

        function stopVideo() {
            if (videoStream) {
                videoStream.getTracks().forEach(track => track.stop());
                videoStream = null;
            }
            if (scanInterval) clearInterval(scanInterval);
            isFaceRecogRunning = false;
            document.getElementById('gate-video').srcObject = null;
        }

        async function runRecognitionLoop() {
            const video = document.getElementById('gate-video');
            const canvas = document.getElementById('gate-canvas');
            const resultBox = document.getElementById('match-result');
            const overlay = document.getElementById('scan-overlay');
            const bioStatus = document.getElementById('bio-status');
            const displaySize = { width: video.videoWidth, height: video.videoHeight };
            faceapi.matchDimensions(canvas, displaySize);

            let hasRecognized = false;
            let scanStartTime = Date.now();
            let lastUnknownFaceImage = '';

            scanInterval = setInterval(async () => {
                if (!isFaceRecogRunning || hasRecognized) return;
                if (Date.now() - scanStartTime > 10000) {
                    isFaceRecogRunning = false;
                    clearInterval(scanInterval);
                    overlay.classList.remove('opacity-100');
                    overlay.classList.add('opacity-0');
                    const scanLine = document.getElementById('scan-line');
                    if (scanLine) { scanLine.classList.remove('animate-scan'); scanLine.style.opacity = '0'; }
                    const ctx = canvas.getContext('2d');
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    
                    resultBox.classList.remove('hidden');
                    void resultBox.offsetWidth;
                    resultBox.classList.remove('translate-y-4', 'opacity-0');

                    let buttonsHtml = `
                        <div class="flex gap-4 w-full mt-4">
                            <button onclick="restartScan()" class="flex-1 py-3 bg-slate-800 border border-slate-600 text-white font-bold rounded-xl text-base hover:bg-slate-700 transition-colors">Retry Scan</button>
                            <button onclick="window.location.href='${BASE_URL}owner/manual_guest.php'" class="flex-1 py-3 bg-emerald-600 border border-emerald-500 text-white font-bold rounded-xl text-base hover:bg-emerald-500 transition-colors">Manual Entry</button>
                        </div>
                    `;

                    if (lastUnknownFaceImage !== '') {
                        bioStatus.innerHTML = '<span class="text-rose-500 font-bold">Access Denied</span>';
                        fetch(BASE_URL + 'api/gate.php?action=log_unknown', {
                            method: 'POST', headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ scan_image: lastUnknownFaceImage })
                        }).catch(console.error);

                        resultBox.innerHTML = `
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 rounded-full bg-rose-500/20 text-rose-500 flex items-center justify-center mb-4 border border-rose-500/30"><i data-lucide="x" class="w-8 h-8"></i></div>
                                <div class="text-white font-black text-2xl mb-1">Face Not Recognized</div>
                                <div class="text-slate-400 text-base mb-4">Please try again or proceed to manual entry.</div>
                                ${buttonsHtml}
                            </div>
                        `;
                        resultBox.className = 'w-full p-8 rounded-2xl text-center opacity-100 transition-all z-20 mt-6 bg-slate-800/80 backdrop-blur-xl border border-rose-500/50 shadow-2xl';
                    } else {
                        bioStatus.innerHTML = '<span class="text-slate-300 font-bold">Scan Timeout</span>';
                        resultBox.innerHTML = `
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 rounded-full bg-slate-700 text-slate-300 flex items-center justify-center mb-4 border border-slate-600"><i data-lucide="clock" class="w-8 h-8"></i></div>
                                <div class="text-white font-black text-2xl mb-1">No Face Detected</div>
                                <div class="text-slate-400 text-base mb-4">Please position your face in the frame.</div>
                                ${buttonsHtml}
                            </div>
                        `;
                        resultBox.className = 'w-full p-8 rounded-2xl text-center opacity-100 transition-all z-20 mt-6 bg-slate-800/80 backdrop-blur-xl border border-slate-600 shadow-2xl';
                    }
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    return;
                }

                if (!guestsLoaded) return;
                let detections;
                try {
                    detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptors();
                } catch (e) { return; }
                if (!isFaceRecogRunning || hasRecognized || !isBiometricModalOpen) return;

                const resizedDetections = faceapi.resizeResults(detections, displaySize);
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);

                if (detections.length > 0) {
                    overlay.classList.remove('opacity-0');
                    overlay.classList.add('opacity-100');
                    if (bioStatus.innerText !== 'Analyzing Profile...') {
                        bioStatus.innerHTML = '<span class="text-emerald-400 font-bold"><i data-lucide="loader-2" class="w-5 h-5 inline animate-spin mr-2"></i> Analyzing Profile...</span>';
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    }
                    resizedDetections.forEach(detection => {
                        const box = detection.detection.box;
                        ctx.strokeStyle = 'rgba(16, 185, 129, 1)';
                        ctx.lineWidth = 4;
                        const d = 20;
                        ctx.beginPath(); ctx.moveTo(box.x, box.y + d); ctx.lineTo(box.x, box.y); ctx.lineTo(box.x + d, box.y); ctx.stroke();
                        ctx.beginPath(); ctx.moveTo(box.x + box.width - d, box.y); ctx.lineTo(box.x + box.width, box.y); ctx.lineTo(box.x + box.width, box.y + d); ctx.stroke();
                        ctx.beginPath(); ctx.moveTo(box.x, box.y + box.height - d); ctx.lineTo(box.x, box.y + box.height); ctx.lineTo(box.x + d, box.y + box.height); ctx.stroke();
                        ctx.beginPath(); ctx.moveTo(box.x + box.width - d, box.y + box.height); ctx.lineTo(box.x + box.width, box.y + box.height); ctx.lineTo(box.x + box.width, box.y + box.height - d); ctx.stroke();
                    });
                } else {
                    overlay.classList.remove('opacity-100');
                    overlay.classList.add('opacity-0');
                    if (!isCameraWarmingUp) {
                        bioStatus.innerHTML = '<span class="text-emerald-400 font-bold"><i data-lucide="scan" class="w-5 h-5 inline mr-2"></i> Scan active. Please look at camera.</span>';
                        if (typeof lucide !== 'undefined') lucide.createIcons();
                    }
                }

                const results = faceMatcher ? resizedDetections.map(d => faceMatcher.findBestMatch(d.descriptor)) : resizedDetections.map(d => ({ label: 'unknown', distance: 1.0 }));
                const matchIndex = results.findIndex(r => r.label !== 'unknown');

                const captureFace = (index) => {
                    try {
                        const detectionBox = detections[index].detection.box;
                        const tmpCanvas = document.createElement('canvas');
                        tmpCanvas.width = 300; tmpCanvas.height = 300;
                        const tmpCtx = tmpCanvas.getContext('2d');
                        const pad = 40;
                        const x = Math.max(0, detectionBox.x - pad);
                        const y = Math.max(0, detectionBox.y - pad * 1.5);
                        const w = Math.min(video.videoWidth - x, detectionBox.width + pad * 2);
                        const h = Math.min(video.videoHeight - y, detectionBox.height + pad * 2.5);
                        tmpCtx.drawImage(video, x, y, w, h, 0, 0, 300, 300);
                        return tmpCanvas.toDataURL('image/jpeg', 0.85);
                    } catch (e) { return ''; }
                };

                if (matchIndex !== -1) {
                    hasRecognized = true;
                    clearInterval(scanInterval);
                    const bestMatch = results[matchIndex];
                    const matchedFaceImage = captureFace(matchIndex);
                    
                    resultBox.classList.remove('hidden');
                    void resultBox.offsetWidth;
                    resultBox.classList.remove('translate-y-4', 'opacity-0');

                    const [gId, gName, gType] = bestMatch.label.split(':');
                    resultBox.innerHTML = `
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center shrink-0 border-2 border-emerald-500/40"><i data-lucide="check" class="w-10 h-10"></i></div>
                            <div class="text-left flex-1">
                                <div class="text-emerald-400 font-bold text-sm uppercase tracking-widest mb-1">Access Granted</div>
                                <div class="text-white text-3xl font-black">${gName}</div>
                                <div class="text-slate-400 text-lg font-medium mt-1">${gType} Profile</div>
                            </div>
                        </div>
                    `;
                    resultBox.className = 'w-full p-8 rounded-2xl opacity-100 transition-all z-20 mt-6 bg-slate-800/90 backdrop-blur-xl border-2 border-emerald-500/50 shadow-2xl';
                    bioStatus.innerHTML = '<span class="text-emerald-400 font-bold">Verification Complete</span>';

                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    overlay.classList.remove('opacity-100');
                    const scanLine = document.getElementById('scan-line');
                    if (scanLine) { scanLine.classList.remove('animate-scan'); scanLine.style.opacity = '0'; }

                    fetch(BASE_URL + 'api/gate.php?action=log_entry', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ guest_id: gId, scan_image: matchedFaceImage })
                    }).then(() => {
                        setTimeout(() => window.location.reload(), 3000); // Reload kiosk to update recent scans
                    }).catch(console.error);

                    if (typeof lucide !== 'undefined') lucide.createIcons();
                } else if (detections.length > 0) {
                    lastUnknownFaceImage = captureFace(0);
                }
            }, 200);
        }

        function restartScan() {
            clearInterval(scanInterval);
            isFaceRecogRunning = true;
            const resultBox = document.getElementById('match-result');
            if (resultBox) {
                resultBox.className = 'w-full p-6 rounded-2xl text-center hidden opacity-0 transition-all transform translate-y-4 z-20 mt-6 border shadow-lg';
                resultBox.innerHTML = '';
            }
            const overlay = document.getElementById('scan-overlay');
            if (overlay) { overlay.classList.remove('opacity-100'); overlay.classList.add('opacity-0'); }
            const scanLine = document.getElementById('scan-line');
            if (scanLine) { scanLine.style.opacity = '1'; scanLine.classList.add('animate-scan'); }
            document.getElementById('bio-status').innerHTML = '<span class="text-emerald-400 font-bold"><i data-lucide="scan" class="w-5 h-5 inline mr-2"></i> Scan active. Please look at camera.</span>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            runRecognitionLoop();
        }

        async function toggleBiometricModal(show) {
            const modal = document.getElementById('biometric-modal');
            const panel = document.getElementById('biometric-panel');
            if (show) {
                isBiometricModalOpen = true; scanAttemptCount = 0;
                preloadModels(); fetchGuestSignatures();
                
                const resultBox = document.getElementById('match-result');
                if (resultBox) { resultBox.className = 'w-full p-6 rounded-2xl text-center hidden opacity-0 transition-all transform translate-y-4 z-20 mt-6 border shadow-lg'; resultBox.innerHTML = ''; }
                const overlay = document.getElementById('scan-overlay');
                if (overlay) { overlay.classList.remove('opacity-100'); overlay.classList.add('opacity-0'); }
                const scanLine = document.getElementById('scan-line');
                if (scanLine) { scanLine.classList.remove('animate-scan'); scanLine.style.opacity = '0'; }

                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.classList.add('opacity-100'); modal.classList.remove('opacity-0');
                    panel.classList.replace('scale-95', 'scale-100'); panel.classList.replace('opacity-0', 'opacity-100');
                }, 10);

                isFaceRecogRunning = true;
                const bioStatus = document.getElementById('bio-status');
                if (!modelsLoaded) {
                    bioStatus.innerHTML = '<span class="text-slate-300 text-base font-bold flex items-center justify-center gap-2"><i data-lucide="loader-2" class="w-5 h-5 animate-spin text-emerald-400"></i> Initializing Hardware...</span>';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    await preloadModels();
                }
                if (!isBiometricModalOpen) return;
                if (!guestsLoaded) {
                    bioStatus.innerHTML = '<span class="text-slate-300 text-base font-bold flex items-center justify-center gap-2"><i data-lucide="loader-2" class="w-5 h-5 animate-spin text-emerald-400"></i> Synchronizing Database...</span>';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    await fetchGuestSignatures(false);
                }
                if (!isBiometricModalOpen) return;
                await startVideo();
                if (!isBiometricModalOpen) { stopVideo(); return; }
                if (scanLine) { scanLine.classList.add('animate-scan'); }
                runRecognitionLoop();
            } else {
                isBiometricModalOpen = false;
                modal.classList.remove('opacity-100'); modal.classList.add('opacity-0');
                panel.classList.replace('scale-100', 'scale-95'); panel.classList.replace('opacity-100', 'opacity-0');
                stopVideo();
                const scanLine = document.getElementById('scan-line');
                if (scanLine) { scanLine.classList.remove('animate-scan'); scanLine.style.opacity = '0'; }
                setTimeout(() => { modal.classList.add('hidden'); }, 300);
            }
        }

        // --- Parking Scanner Logic ---
        let parkingVideoStream = null;
        let isParkingScanning = false;
        let parkingScanComplete = false;
        let lastParkingScanTime = 0;
        const parkingCooldownMs = 3000;
        let isParkingMirrored = false;

        const parkingVideo = document.getElementById('parking-webcam');
        const parkingCanvas = document.getElementById('parking-canvas');
        const parkingCropCanvas = document.getElementById('parking-crop-canvas');
        const parkingScanLine = document.getElementById('parking-scan-line');
        const parkingDebugText = document.getElementById('parking-debug-text');
        const parkingBioStatus = document.getElementById('parking-bio-status');
        const parkingMatchResult = document.getElementById('parking-match-result');
        const parkingFlipBtn = document.getElementById('parking-flip-btn');

        if (parkingFlipBtn && parkingVideo) {
            parkingFlipBtn.addEventListener('click', () => {
                isParkingMirrored = !isParkingMirrored;
                parkingVideo.style.transform = isParkingMirrored ? 'scaleX(-1)' : 'none';
            });
        }

        async function startParkingCamera() {
            try {
                parkingBioStatus.innerHTML = '<span class="text-slate-200"><i data-lucide="camera" class="w-5 h-5 inline mr-2 text-blue-400"></i> Connecting camera...</span>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
                parkingVideoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                parkingVideo.srcObject = parkingVideoStream;
                parkingVideo.addEventListener('play', () => {
                    parkingCanvas.width = parkingVideo.videoWidth;
                    parkingCanvas.height = parkingVideo.videoHeight;
                    parkingScanLine.style.display = 'block';
                    parkingBioStatus.innerHTML = '<span class="text-blue-400 font-bold"><i data-lucide="scan" class="w-5 h-5 inline mr-2"></i> Scanning active. Please align plate.</span>';
                    if (typeof lucide !== 'undefined') lucide.createIcons();
                    requestAnimationFrame(processParkingFrame);
                }, { once: true });
            } catch (err) {
                console.error(err);
                parkingBioStatus.innerHTML = '<span class="text-rose-400 font-bold"><i data-lucide="video-off" class="w-5 h-5 inline mr-2"></i> Camera access denied.</span>';
                if (typeof lucide !== 'undefined') lucide.createIcons();
            }
        }

        function stopParkingCamera() {
            if (parkingVideoStream) { parkingVideoStream.getTracks().forEach(track => track.stop()); parkingVideoStream = null; }
            isParkingScanning = false;
            parkingVideo.srcObject = null;
            if(parkingScanLine) parkingScanLine.style.display = 'none';
        }

        async function processParkingFrame() {
            if (isParkingScanning || parkingScanComplete || !parkingVideoStream) {
                if (!parkingScanComplete && parkingVideoStream) requestAnimationFrame(processParkingFrame);
                return;
            }
            const now = Date.now();
            if (now - lastParkingScanTime < parkingCooldownMs) {
                requestAnimationFrame(processParkingFrame);
                return;
            }
            isParkingScanning = true;
            const ctx = parkingCanvas.getContext('2d');
            ctx.save();
            if (isParkingMirrored) { ctx.translate(parkingCanvas.width, 0); ctx.scale(-1, 1); }
            ctx.drawImage(parkingVideo, 0, 0, parkingCanvas.width, parkingCanvas.height);
            ctx.restore();
            
            const boxWidth = Math.min(parkingCanvas.width * 0.8, 500);
            const boxHeight = 150;
            const x = (parkingCanvas.width - boxWidth) / 2;
            const y = (parkingCanvas.height - boxHeight) / 2;
            
            const cropCtx = parkingCropCanvas.getContext('2d', { willReadFrequently: true });
            parkingCropCanvas.width = boxWidth; parkingCropCanvas.height = boxHeight;
            cropCtx.drawImage(parkingCanvas, x, y, boxWidth, boxHeight, 0, 0, boxWidth, boxHeight);



            try {
                const base64Image = parkingCropCanvas.toDataURL('image/jpeg', 0.9);
                const response = await fetch(BASE_URL + 'parking/run_easyocr.php', {
                    method: 'POST', headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ image: base64Image })
                });
                const result = await response.json();
                
                if (result.status === 'success' && result.text) {
                    const cleanText = result.text.replace(/[^A-Z0-9-]/gi, '').trim();
                    if (cleanText.length > 0) {
                        parkingDebugText.textContent = 'Detected: ' + cleanText;
                        parkingDebugText.classList.remove('opacity-0');
                        setTimeout(() => parkingDebugText.classList.add('opacity-0'), 2000);
                    }
                    if (cleanText.length >= 4) {
                        lastParkingScanTime = Date.now();
                        await verifyParkingPlate(cleanText);
                    }
                }
            } catch (err) { console.error("OCR Error:", err); }
            isParkingScanning = false;
            requestAnimationFrame(processParkingFrame);
        }

        async function verifyParkingPlate(plateNumber) {
            parkingBioStatus.innerHTML = '<span class="text-blue-400 font-bold"><i data-lucide="loader-2" class="w-5 h-5 inline animate-spin mr-2"></i> Verifying Plate...</span>';
            if (typeof lucide !== 'undefined') lucide.createIcons();
            try {
                const fd = new FormData(); fd.append('license_plate', plateNumber);
                const response = await fetch(BASE_URL + 'api/api_scan.php', { method: 'POST', body: fd });
                const data = await response.json();
                
                if (data.status === 'success') {
                    parkingScanComplete = true; parkingVideo.pause(); parkingScanLine.style.display = 'none';
                    parkingMatchResult.classList.remove('hidden'); void parkingMatchResult.offsetWidth; parkingMatchResult.classList.remove('translate-y-4', 'opacity-0');
                    
                    parkingMatchResult.innerHTML = `
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 rounded-full bg-blue-500/20 text-blue-400 flex items-center justify-center shrink-0 border-2 border-blue-500/40"><i data-lucide="check-circle" class="w-10 h-10"></i></div>
                            <div class="text-left flex-1">
                                <div class="text-blue-400 font-bold text-sm uppercase tracking-widest mb-1">Access Granted</div>
                                <div class="text-white text-2xl font-black mt-1">${data.message}</div>
                            </div>
                        </div>
                    `;
                    parkingMatchResult.className = 'w-full p-8 rounded-2xl opacity-100 transition-all z-20 mt-6 bg-slate-800/90 backdrop-blur-xl border-2 border-blue-500/50 shadow-2xl';
                    parkingBioStatus.innerHTML = '<span class="text-blue-400 font-bold">Verification Complete</span>';
                    setTimeout(() => window.location.reload(), 3000); // Reload kiosk
                } else {
                    parkingMatchResult.classList.remove('hidden'); void parkingMatchResult.offsetWidth; parkingMatchResult.classList.remove('translate-y-4', 'opacity-0');
                    parkingMatchResult.innerHTML = `
                        <div class="flex items-center gap-6">
                            <div class="w-20 h-20 rounded-full bg-rose-500/20 text-rose-500 flex items-center justify-center shrink-0 border-2 border-rose-500/40"><i data-lucide="x-circle" class="w-10 h-10"></i></div>
                            <div class="text-left flex-1">
                                <div class="text-rose-400 font-bold text-sm uppercase tracking-widest mb-1">Access Denied</div>
                                <div class="text-white text-2xl font-black mt-1">${data.message}</div>
                            </div>
                        </div>
                    `;
                    parkingMatchResult.className = 'w-full p-8 rounded-2xl opacity-100 transition-all z-20 mt-6 bg-slate-800/90 backdrop-blur-xl border-2 border-rose-500/50 shadow-2xl';
                    parkingBioStatus.innerHTML = '<span class="text-blue-400 font-bold"><i data-lucide="scan" class="w-5 h-5 inline mr-2"></i> Scan active. Please align plate.</span>';
                    setTimeout(() => {
                        if (!parkingScanComplete) {
                            parkingMatchResult.classList.add('translate-y-4', 'opacity-0');
                            setTimeout(() => parkingMatchResult.classList.add('hidden'), 300);
                        }
                    }, 4000);
                }
                if (typeof lucide !== 'undefined') lucide.createIcons();
            } catch (err) { console.error(err); }
        }

        function toggleParkingScannerModal(show) {
            const modal = document.getElementById('parking-scanner-modal');
            const panel = document.getElementById('parking-scanner-panel');
            if (show) {
                parkingScanComplete = false;
                const resultBox = document.getElementById('parking-match-result');
                if (resultBox) { resultBox.className = 'w-full p-6 rounded-2xl text-center hidden opacity-0 transition-all transform translate-y-4 z-20 mt-6 border shadow-lg'; resultBox.innerHTML = ''; }
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modal.classList.add('opacity-100'); modal.classList.remove('opacity-0');
                    panel.classList.replace('scale-95', 'scale-100'); panel.classList.replace('opacity-0', 'opacity-100');
                }, 10);
                startParkingCamera();
            } else {
                modal.classList.remove('opacity-100'); modal.classList.add('opacity-0');
                panel.classList.replace('scale-100', 'scale-95'); panel.classList.replace('opacity-100', 'opacity-0');
                stopParkingCamera();
                setTimeout(() => { modal.classList.add('hidden'); }, 300);
            }
        }
    </script>
</body>
</html>
