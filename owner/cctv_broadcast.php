<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role_id'] ?? null) != 1) {
    header('Location: ' . BASE_URL . 'index.php?error=unauthorized');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCTV Mobile Broadcast | Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/owner_style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-900 text-white font-sans antialiased h-screen flex flex-col">
    
    <header class="bg-black/40 backdrop-blur-md border-b border-white/10 p-4 flex items-center justify-between z-10 shrink-0">
        <div class="flex items-center gap-3">
            <a href="cctv_surveillance.php" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/5 text-white hover:bg-white/10 transition">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <div>
                <h1 class="text-lg font-black tracking-tight text-white">Camera Broadcast</h1>
                <p class="text-[10px] font-bold text-emerald-400 uppercase tracking-widest">Live Relay</p>
            </div>
        </div>
        <div id="statusIndicator" class="flex items-center gap-2 bg-slate-800/50 border border-slate-700 px-3 py-1.5 rounded-lg">
            <span class="w-2 h-2 rounded-full bg-slate-500" id="statusDot"></span>
            <span class="text-xs font-bold text-slate-300" id="statusText">Ready</span>
        </div>
    </header>

    <div class="flex-1 relative flex flex-col items-center justify-center overflow-hidden bg-black p-4">
        <!-- Video Element -->
        <video id="videoElement" class="absolute inset-0 w-full h-full object-cover opacity-50" autoplay playsinline muted></video>
        
        <!-- Controls Overlay -->
        <div class="relative z-10 w-full max-w-md bg-slate-900/80 backdrop-blur-xl border border-white/10 rounded-[2rem] p-6 shadow-2xl">
            <div id="setupSection">
                <div class="mb-6">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center border border-emerald-500/30">
                            <i data-lucide="video" class="w-4 h-4"></i>
                        </div>
                        <h2 class="text-lg font-black text-white">Select Camera</h2>
                    </div>
                    <p class="text-xs font-medium text-slate-400 mb-4">Choose which camera slot to broadcast to. Only "Built-in" cameras are listed.</p>
                    
                    <select id="cameraSelect" class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-bold text-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none appearance-none">
                        <option value="">Loading cameras...</option>
                    </select>
                </div>

                <button id="startBtn" disabled class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-black py-4 rounded-xl flex items-center justify-center gap-2 transition disabled:opacity-50 disabled:cursor-not-allowed">
                    <i data-lucide="play" class="w-5 h-5"></i> Start Broadcast
                </button>
            </div>

            <div id="activeSection" class="hidden text-center">
                <div class="w-20 h-20 mx-auto rounded-full bg-rose-500/20 flex items-center justify-center border border-rose-500/30 mb-4 relative">
                    <div class="absolute inset-0 rounded-full border-2 border-rose-500 animate-ping opacity-20"></div>
                    <i data-lucide="radio" class="w-8 h-8 text-rose-500 animate-pulse"></i>
                </div>
                <h2 class="text-xl font-black text-white mb-1">Broadcasting Live</h2>
                <p class="text-xs font-bold text-emerald-400 uppercase tracking-widest mb-6">Transmitting to Dashboard</p>

                <div class="grid grid-cols-2 gap-4 mb-6 text-left">
                    <div class="bg-slate-800/50 border border-slate-700 p-3 rounded-xl">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">FPS</p>
                        <p class="text-lg font-black text-white" id="fpsDisplay">--</p>
                    </div>
                    <div class="bg-slate-800/50 border border-slate-700 p-3 rounded-xl">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Frames Sent</p>
                        <p class="text-lg font-black text-white" id="framesDisplay">0</p>
                    </div>
                </div>

                <button id="stopBtn" class="w-full bg-slate-800 border border-slate-700 hover:bg-rose-500/20 hover:border-rose-500/50 hover:text-rose-400 text-white font-black py-4 rounded-xl flex items-center justify-center gap-2 transition">
                    <i data-lucide="square" class="w-5 h-5"></i> Stop Broadcast
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden canvas for capturing frames -->
    <canvas id="captureCanvas" class="hidden"></canvas>

    <script>
        lucide.createIcons();

        const videoElement = document.getElementById('videoElement');
        const canvas = document.getElementById('captureCanvas');
        const ctx = canvas.getContext('2d', { alpha: false });
        
        const cameraSelect = document.getElementById('cameraSelect');
        const startBtn = document.getElementById('startBtn');
        const stopBtn = document.getElementById('stopBtn');
        const setupSection = document.getElementById('setupSection');
        const activeSection = document.getElementById('activeSection');
        
        const statusDot = document.getElementById('statusDot');
        const statusText = document.getElementById('statusText');
        const fpsDisplay = document.getElementById('fpsDisplay');
        const framesDisplay = document.getElementById('framesDisplay');

        let broadcastInterval = null;
        let isBroadcasting = false;
        let buildingId = '';
        let stream = null;
        
        let framesSent = 0;
        let lastFrameTime = Date.now();
        let fpsCounter = 0;

        // Fetch available built-in cameras
        async function fetchCameras() {
            try {
                const response = await fetch('../api/cctv.php?action=get_dashboard');
                const data = await response.json();
                
                if (data.status === 'success') {
                    buildingId = data.building_id;
                    const builtinCameras = (data.devices || []).filter(c => c.ip_address === 'builtin');
                    
                    if (builtinCameras.length > 0) {
                        cameraSelect.innerHTML = builtinCameras.map(c => 
                            `<option value="${c.id}">${c.camera_name} (${c.location_description || 'No location'})</option>`
                        ).join('');
                        startBtn.disabled = false;
                    } else {
                        cameraSelect.innerHTML = '<option value="">No builtin cameras found. Add one in dashboard.</option>';
                    }
                }
            } catch (err) {
                console.error(err);
                cameraSelect.innerHTML = '<option value="">Error loading cameras</option>';
            }
        }

        async function initCamera() {
            try {
                // Request back camera
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'environment',
                        width: { ideal: 640 },
                        height: { ideal: 480 }
                    }, 
                    audio: false 
                });
                videoElement.srcObject = stream;
                videoElement.play();
                
                statusDot.className = 'w-2 h-2 rounded-full bg-emerald-500';
                statusText.textContent = 'Camera Active';
            } catch (err) {
                console.error(err);
                statusDot.className = 'w-2 h-2 rounded-full bg-rose-500';
                statusText.textContent = 'Camera Access Denied';
                alert('Could not access camera. Please allow camera permissions.');
            }
        }

        async function sendFrame(cameraId) {
            if (!isBroadcasting) return;

            // Draw to canvas
            canvas.width = videoElement.videoWidth;
            canvas.height = videoElement.videoHeight;
            ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);
            
            // Get JPEG base64
            const jpegData = canvas.toDataURL('image/jpeg', 0.5); // 50% quality for speed

            const formData = new FormData();
            formData.append('camera_id', cameraId);
            formData.append('building_id', buildingId);
            formData.append('image', jpegData);

            try {
                await fetch('../api/cctv_relay.php', {
                    method: 'POST',
                    body: formData
                });
                
                framesSent++;
                framesDisplay.textContent = framesSent;
                
                // Calculate actual FPS
                fpsCounter++;
                const now = Date.now();
                if (now - lastFrameTime >= 1000) {
                    fpsDisplay.textContent = fpsCounter;
                    fpsCounter = 0;
                    lastFrameTime = now;
                }
            } catch (err) {
                console.error('Frame drop', err);
            }
        }

        startBtn.addEventListener('click', () => {
            const selectedCamera = cameraSelect.value;
            if (!selectedCamera) return;

            isBroadcasting = true;
            setupSection.classList.add('hidden');
            activeSection.classList.remove('hidden');
            videoElement.classList.remove('opacity-50');
            videoElement.classList.add('opacity-100');
            
            statusDot.className = 'w-2 h-2 rounded-full bg-rose-500 animate-pulse';
            statusText.textContent = 'Broadcasting...';
            statusText.classList.add('text-rose-400');

            framesSent = 0;
            fpsCounter = 0;
            lastFrameTime = Date.now();

            // Send 10 frames per second
            broadcastInterval = setInterval(() => sendFrame(selectedCamera), 100);
        });

        stopBtn.addEventListener('click', () => {
            isBroadcasting = false;
            clearInterval(broadcastInterval);
            
            setupSection.classList.remove('hidden');
            activeSection.classList.add('hidden');
            videoElement.classList.add('opacity-50');
            videoElement.classList.remove('opacity-100');
            
            statusDot.className = 'w-2 h-2 rounded-full bg-emerald-500';
            statusText.textContent = 'Camera Active';
            statusText.classList.remove('text-rose-400');
        });

        // Initialize
        fetchCameras();
        initCamera();
    </script>

    <?php include '../chatbot/chat_widget.php'; ?>
</body>
</html>
