// js/resident_logic.js
document.addEventListener('DOMContentLoaded', () => {
    // Initialize Lucide Icons
    lucide.createIcons();

    // Initialize Random Bill
    if(typeof calculateCurrentBill === 'function') {
        calculateCurrentBill();
    }
    
    // Set dynamic greeting and date
    setGreeting();
});

// --- UI Helpers ---
function setGreeting() {
    const hour = new Date().getHours();
    let greeting = 'Good Evening';
    if (hour < 12) greeting = 'Good Morning';
    else if (hour < 18) greeting = 'Good Afternoon';
    
    const greetingEl = document.getElementById('dynamic-greeting');
    if (greetingEl) {
        let userName = greetingEl.dataset.name || 'Resident';
        greetingEl.innerHTML = `${greeting}, <span class="text-blue-600 font-bold">${userName}</span>! 🌤️`;
    }

    const dateEl = document.getElementById('current-date');
    if (dateEl) {
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        dateEl.textContent = new Date().toLocaleDateString('en-US', options);
    }
}

// --- Bill Logic ---
function calculateCurrentBill() {
    // Fixed amounts
    const gas = 1080;
    const water = 500;
    // Random Electric between 800 and 3500
    const electric = Math.floor(Math.random() * (3500 - 800 + 1)) + 800;
    const total = gas + water + electric;

    // Update DOM (Assuming IDs are present)
    const billTotalEl = document.getElementById('bill-total');
    const billElectricEl = document.getElementById('bill-electric');
    const billGasEl = document.getElementById('bill-gas');
    const billWaterEl = document.getElementById('bill-water');

    if (billTotalEl) billTotalEl.innerText = `৳${total.toLocaleString()}`;
    if (billElectricEl) billElectricEl.innerText = `৳${electric.toLocaleString()}`;
    if (billGasEl) billGasEl.innerText = `৳${gas.toLocaleString()}`;
    if (billWaterEl) billWaterEl.innerText = `৳${water.toLocaleString()}`;
}

// --- Action Handlers ---
function triggerSOS(type) {
    // Premium Toast/Alert replacement
    const btn = document.getElementById(`sos-${type}`);
    if (btn) {
        const oldHtml = btn.innerHTML;
        btn.innerHTML = `<i data-lucide="loader-2" class="w-6 h-6 animate-spin"></i> Alerting...`;
        lucide.createIcons();
        
        setTimeout(() => {
            showCustomPopup(`EMERGENCY: ${type.toUpperCase()} alert sent to Administration and Security!`, 'error');
            btn.innerHTML = oldHtml;
            lucide.createIcons();
        }, 1500);
    } else {
        showCustomPopup(`EMERGENCY: ${type.toUpperCase()} alert triggered!`, 'error');
    }
}

function processPayment() {
    showCustomPopup("Redirecting to secure payment gateway (Mock Bkash/Nagad)...", "info");
}

function preregisterGuest(event) {
    if(event) event.preventDefault();
    const name = document.getElementById('guest-name').value;
    const date = document.getElementById('guest-date').value;
    
    if(!name || !date) {
        showCustomPopup("Please provide both name and expected date.", 'error');
        return;
    }
    
    showCustomPopup(`Success: ${name} has been pre-registered for pass access on ${date}.`, 'success');
    document.getElementById('guest-name').value = '';
    document.getElementById('guest-date').value = '';
}

function callService(serviceName) {
    showCustomPopup(`Initiating secure call routine to ${serviceName}...`, 'info');
}

// --- NEW ACTION HANDLERS (Phase 2) ---

function enrollGuest(event) {
    if(event) event.preventDefault();
    const photoInput = document.getElementById('guest-photo');
    if (!photoInput) return; // fail gracefully if form structure changed
    const photo = photoInput.files[0];
    const name = document.getElementById('guest-name').value;
    const phone = document.getElementById('guest-phone')?.value;
    const countValue = document.getElementById('guest-count')?.value;
    const totalGuests = parseInt(countValue || '0', 10);
    
    if(!photo || !name || !phone || !totalGuests || totalGuests < 1) {
        showCustomPopup("Please provide photo, name, phone number, and total guests.", 'error');
        return;
    }
    
    showCustomPopup(`Success: Biometric Profile created for ${name}. Guest can now use Face Scan at the main gate.`, 'success');
    event.target.reset();
}

function requestService(btn, providerName) {
    // Visual feedback for Request Sent
    const originalText = btn.innerHTML;
    
    // Changing button state
    btn.innerHTML = `<i data-lucide="check-circle" class="w-4 h-4 text-emerald-500"></i> <span class="text-emerald-600">Request Sent</span>`;
    btn.classList.add('border-emerald-300', 'bg-emerald-50');
    lucide.createIcons();
    
    showCustomPopup(`Service request sent to ${providerName}! They will contact you shortly via the App.`, 'success');
    
    // Optional: Revert after 5 seconds
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.classList.remove('border-emerald-300', 'bg-emerald-50');
        lucide.createIcons();
    }, 5000);
}

// --- Parking Map Logic ---
function openParkingModal(slotId) {
    const modal = document.getElementById('parking-modal');
    const slotText = document.getElementById('modal-slot-id');
    const content = document.getElementById('parking-modal-content');
    
    if(modal && slotText) {
        slotText.innerText = slotId;
        modal.classList.remove('hidden');
        
        // Slight delay for animation to kick in smoothly
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        }, 10);
    }
}

function closeParkingModal() {
    const modal = document.getElementById('parking-modal');
    const content = document.getElementById('parking-modal-content');
    
    if(modal && content) {
        modal.classList.add('opacity-0');
        content.classList.remove('scale-100');
        content.classList.add('scale-95');
        
        // Wait for transition before hiding
        setTimeout(() => {
            modal.classList.add('hidden');
        }, 300);
    }
}

function confirmReservation(event) {
    if(event) event.preventDefault();
    
    const slotId = document.getElementById('modal-slot-id').innerText;
    showCustomPopup(`Success: ${slotId} has been temporarily reserved. The security barrier will recognize the assigned vehicle.`, 'success');
    closeParkingModal();
}
