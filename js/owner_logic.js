// admin_logic.js
// Initialize Lucide Icons
document.addEventListener('DOMContentLoaded', () => {
    lucide.createIcons();
});

// Generic trigger for unimplemented features
function triggerAlert(featureName) {
    if (typeof showCustomPopup === 'function') {
        const isNotice = featureName.includes("Notice");
        showCustomPopup(`${featureName} is currently under construction or processed.`, isNotice ? 'success' : 'info');
    } else {
        alert(featureName + " (Custom popup not loaded)");
    }
}

// Logic for Monthly Utility Card
function generateRandomBill() {
    const min = 40;
    const max = 350;
    const randomBill = (Math.random() * (max - min) + min).toFixed(2);
    
    const toastElement = document.getElementById('bill-toast');
    toastElement.classList.remove('hidden');
    toastElement.classList.add('animate-pulse');
    toastElement.innerText = `Generated: $${randomBill} / Unit`;
    
    setTimeout(() => {
        toastElement.classList.remove('animate-pulse');
    }, 1000);
}

// Logic for Bulletin Master
function broadcastNotice() {
    const textArea = document.getElementById('notice-text');
    if (textArea.value.trim() === '') {
        triggerAlert('Empty Notice Broadcast');
        return;
    }
    
    // Simulate sending
    const originalText = textArea.value;
    textArea.value = '';
    textArea.placeholder = 'Broadcasting...';
    
    setTimeout(() => {
        textArea.placeholder = 'Draft a new notice for all residents...';
        triggerAlert('Global Notice Sent');
    }, 1500);
}
