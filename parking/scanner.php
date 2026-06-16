<?php
session_start();
require_once '../includes/db_config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Terminal Scanner | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: white; }
        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 80%;
            max-width: 400px;
            height: 120px;
            border: 4px dashed rgba(16, 185, 129, 0.6);
            border-radius: 12px;
            box-shadow: 0 0 0 9999px rgba(15, 23, 42, 0.7);
            z-index: 10;
        }
        .scanning-line {
            width: 100%;
            height: 4px;
            background-color: #10b981;
            box-shadow: 0 0 10px #10b981;
            position: absolute;
            top: 0;
            left: 0;
            animation: scan 2s infinite linear;
        }
        @keyframes scan {
            0% { top: 0; }
            50% { top: 100%; }
            100% { top: 0; }
        }
    </style>
</head>
<body class="min-h-screen flex flex-col relative overflow-hidden">

    <div class="p-6 relative z-20 flex justify-between items-center bg-slate-900/80 backdrop-blur-md border-b border-slate-800">
        <div class="flex items-center gap-3">
            <a href="../index.php" class="p-2 bg-slate-800 hover:bg-slate-700 rounded-xl transition-colors">
                <i data-lucide="arrow-left" class="w-5 h-5"></i>
            </a>
            <h1 class="text-xl font-bold tracking-tight">Terminal Scanner</h1>
        </div>
        <div class="flex items-center gap-3">
            <button id="flip-btn" class="p-2 bg-slate-800 hover:bg-slate-700 rounded-xl transition-colors text-slate-300" title="Flip Camera">
                <i data-lucide="flip-horizontal" class="w-5 h-5"></i>
            </button>
            <div id="status-badge" class="px-4 py-1.5 bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-full text-xs font-bold uppercase tracking-widest flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                Initializing Engine...
            </div>
        </div>
    </div>

    <div class="flex-1 relative flex items-center justify-center">
        <!-- Video Feed -->
        <video id="webcam" autoplay playsinline class="absolute inset-0 w-full h-full object-cover"></video>
        <!-- Hidden Canvas for capturing frames -->
        <canvas id="canvas" class="hidden"></canvas>
        
        <!-- Scanner Overlay -->
        <div class="scanner-overlay overflow-hidden">
            <div class="scanning-line" id="scan-line" style="display: none;"></div>
        </div>
        
        <!-- What Tesseract Sees -->
        <canvas id="crop-canvas" class="absolute bottom-40 right-6 border-2 border-emerald-500/50 rounded-lg shadow-2xl w-40 z-20 bg-black"></canvas>
        <div class="absolute bottom-[13rem] right-6 text-[10px] font-bold text-emerald-400 uppercase tracking-widest z-20 bg-slate-900/80 px-2 py-1 rounded">Camera Vision</div>
        
        <!-- Detected Text Debugging -->
        <div id="debug-text" class="absolute bottom-32 bg-slate-900/80 px-4 py-2 rounded-lg text-emerald-400 font-mono text-sm tracking-wider opacity-0 transition-opacity z-20">
            Scanning...
        </div>

        <!-- Result/Alert Box -->
        <div id="alert-box" class="absolute bottom-12 left-1/2 -translate-x-1/2 w-[90%] max-w-md bg-slate-800/95 backdrop-blur-md border border-slate-700 rounded-2xl p-5 shadow-2xl transition-all transform translate-y-10 opacity-0 pointer-events-none z-30">
            <div class="flex items-start gap-4">
                <div id="alert-icon" class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0">
                    <i data-lucide="info" class="w-6 h-6"></i>
                </div>
                <div>
                    <h3 id="alert-title" class="text-lg font-bold mb-1">Status</h3>
                    <p id="alert-message" class="text-slate-300 text-sm leading-relaxed">Message goes here...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        const video = document.getElementById('webcam');
        const canvas = document.getElementById('canvas');
        const ctx = canvas.getContext('2d');
        const statusBadge = document.getElementById('status-badge');
        const scanLine = document.getElementById('scan-line');
        const alertBox = document.getElementById('alert-box');
        const alertIcon = document.getElementById('alert-icon');
        const alertTitle = document.getElementById('alert-title');
        const alertMessage = document.getElementById('alert-message');
        const flipBtn = document.getElementById('flip-btn');
        const debugText = document.getElementById('debug-text');
        const cropCanvas = document.getElementById('crop-canvas');
        const cropCtx = cropCanvas.getContext('2d', { willReadFrequently: true });

        let isScanning = false;
        let worker = null;
        let lastScanTime = 0;
        const cooldownMs = 3000; // Reduced cooldown to 3s for faster retries
        let isMirrored = false;
        let scanComplete = false;

        flipBtn.addEventListener('click', () => {
            isMirrored = !isMirrored;
            video.style.transform = isMirrored ? 'scaleX(-1)' : 'none';
        });

        // Initialize System
        async function initSystem() {
            setStatus('ready', 'System Ready');
            startCamera();
        }

        // Start Webcam
        async function startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'environment' } 
                });
                video.srcObject = stream;
                
                video.addEventListener('play', () => {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    scanLine.style.display = 'block';
                    requestAnimationFrame(processFrame);
                });
            } catch (err) {
                console.error(err);
                setStatus('error', 'Camera Access Denied');
            }
        }

        function setStatus(state, text) {
            statusBadge.innerHTML = `<span class="w-2 h-2 rounded-full ${state === 'ready' ? 'bg-emerald-400 animate-pulse' : (state === 'error' ? 'bg-rose-500' : 'bg-slate-400')}"></span> ${text}`;
            statusBadge.className = `px-4 py-1.5 border rounded-full text-xs font-bold uppercase tracking-widest flex items-center gap-2 ${
                state === 'ready' ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30' : 
                (state === 'error' ? 'bg-rose-500/20 text-rose-400 border-rose-500/30' : 'bg-slate-500/20 text-slate-400 border-slate-500/30')
            }`;
        }

        function showAlert(type, title, message) {
            alertTitle.textContent = title;
            alertMessage.textContent = message;
            
            let iconClass, bgClass, textClass;
            if (type === 'success') {
                iconClass = 'check-circle';
                bgClass = 'bg-emerald-500/20';
                textClass = 'text-emerald-400';
            } else if (type === 'error') {
                iconClass = 'x-circle';
                bgClass = 'bg-rose-500/20';
                textClass = 'text-rose-400';
            } else {
                iconClass = 'info';
                bgClass = 'bg-blue-500/20';
                textClass = 'text-blue-400';
            }

            alertIcon.className = `w-12 h-12 rounded-xl flex items-center justify-center shrink-0 ${bgClass} ${textClass}`;
            alertIcon.innerHTML = `<i data-lucide="${iconClass}" class="w-6 h-6"></i>`;
            lucide.createIcons();

            alertBox.classList.remove('translate-y-10', 'opacity-0', 'pointer-events-none');
            alertBox.classList.add('pointer-events-auto');
            
            // For errors, we hide after 4 seconds
            if (type !== 'success') {
                setTimeout(() => {
                    if (!scanComplete) {
                        alertBox.classList.add('translate-y-10', 'opacity-0', 'pointer-events-none');
                        alertBox.classList.remove('pointer-events-auto');
                    }
                }, 4000);
            }
        }

        // Process video frames
        async function processFrame() {
            if (isScanning || scanComplete) {
                if (!scanComplete) requestAnimationFrame(processFrame);
                return;
            }

            const now = Date.now();
            if (now - lastScanTime < cooldownMs) {
                // Cooldown active, wait
                requestAnimationFrame(processFrame);
                return;
            }

            isScanning = true;

            // Draw current video frame to canvas
            ctx.save();
            if (isMirrored) {
                ctx.translate(canvas.width, 0);
                ctx.scale(-1, 1);
            }
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            ctx.restore();
            
            // To improve OCR speed massively, we draw only the cropped box to a smaller canvas
            const boxWidth = Math.min(canvas.width * 0.8, 400);
            const boxHeight = 120;
            const x = (canvas.width - boxWidth) / 2;
            const y = (canvas.height - boxHeight) / 2;
            
            cropCanvas.width = boxWidth;
            cropCanvas.height = boxHeight;
            cropCtx.drawImage(canvas, x, y, boxWidth, boxHeight, 0, 0, boxWidth, boxHeight);

            // Pre-process: Grayscale, Contrast, and Auto-Invert for Dark Mode phones
            let imgData = cropCtx.getImageData(0, 0, boxWidth, boxHeight);
            let pixels = imgData.data;
            
            // Calculate overall brightness to detect dark mode screens
            let totalBrightness = 0;
            for (let i = 0; i < pixels.length; i += 4) {
                totalBrightness += (pixels[i] * 0.299 + pixels[i+1] * 0.587 + pixels[i+2] * 0.114);
            }
            let isDarkMode = (totalBrightness / (boxWidth * boxHeight)) < 110;

            for (let i = 0; i < pixels.length; i += 4) {
                let avg = (pixels[i] * 0.299 + pixels[i+1] * 0.587 + pixels[i+2] * 0.114);
                
                // If it's a dark screen with glowing text, invert it for Tesseract
                if (isDarkMode) avg = 255 - avg;
                
                // Boost Contrast heavily
                let c = (avg - 128) * 2.0 + 128;
                c = Math.max(0, Math.min(255, c));

                pixels[i] = c;
                pixels[i+1] = c;
                pixels[i+2] = c;
            }
            cropCtx.putImageData(imgData, 0, 0);

            try {
                // Get base64 string of cropped canvas
                const base64Image = cropCanvas.toDataURL('image/jpeg', 0.9);

                // Send to Python Backend EasyOCR
                const response = await fetch('run_easyocr.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ image: base64Image })
                });

                const result = await response.json();
                
                if (result.status === 'success' && result.text) {
                    const cleanText = result.text.replace(/[^A-Z0-9-]/gi, '').trim();

                    if (cleanText.length > 0) {
                        debugText.textContent = 'Detected: ' + cleanText;
                        debugText.classList.remove('opacity-0');
                        setTimeout(() => debugText.classList.add('opacity-0'), 2000);
                    }
                    
                    if (cleanText.length >= 4) { // Assuming plate number has at least 4 chars
                        console.log('Detected Text:', cleanText);
                        lastScanTime = Date.now(); // Trigger cooldown
                        await verifyPlate(cleanText);
                    }
                }
            } catch (err) {
                console.error("OCR Error:", err);
            }

            isScanning = false;
            requestAnimationFrame(processFrame);
        }

        async function verifyPlate(plateNumber) {
            setStatus('scanning', 'Verifying Plate...');
            
            try {
                const fd = new FormData();
                fd.append('license_plate', plateNumber);

                const response = await fetch('../api/api_scan.php', {
                    method: 'POST',
                    body: fd
                });
                
                const data = await response.json();
                
                if (data.status === 'success') {
                    scanComplete = true;
                    video.pause();
                    scanLine.style.display = 'none';
                    
                    // Show a permanent success popup with a button to return
                    showAlert('success', 'Access Granted', data.message);
                    alertMessage.innerHTML = data.message + '<br><br><a href="../index.php" class="inline-block mt-4 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-bold shadow-lg shadow-emerald-600/20 transition-colors">Return to Home</a>';
                } else {
                    showAlert('error', 'Access Denied', data.message);
                }
            } catch (err) {
                console.error(err);
                showAlert('error', 'Connection Error', 'Failed to connect to the server.');
            }
            
            setStatus('ready', 'System Ready');
        }

        // Boot up
        initSystem();

    </script>
</body>
</html>
