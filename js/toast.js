// js/toast.js

function showCustomPopup(message, type = 'info') {
    // Determine icon and colors based on type
    let iconName = 'info';
    let iconColor = 'text-blue-400';
    let borderColor = 'border-slate-700';
    let shadowColor = 'shadow-[0_10px_40px_-10px_rgba(0,0,0,0.5)]';

    if (type === 'error' || type === 'error_msg') {
        iconName = 'alert-triangle';
        iconColor = 'text-red-400';
        borderColor = 'border-red-500/50';
        shadowColor = 'shadow-[0_10px_40px_-10px_rgba(239,68,68,0.3)]';
    } else if (type === 'success') {
        iconName = 'check-circle';
        iconColor = 'text-emerald-400';
        borderColor = 'border-emerald-500/50';
        shadowColor = 'shadow-[0_10px_40px_-10px_rgba(16,185,129,0.3)]';
    } else {
        // info / general
        iconName = 'info';
        iconColor = 'text-emerald-400'; // user wanted a light green touch broadly
        borderColor = 'border-emerald-500/30';
        shadowColor = 'shadow-[0_10px_40px_-10px_rgba(16,185,129,0.2)]';
    }

    const toastId = 'toast-' + Math.random().toString(36).substr(2, 9);

    const toast = document.createElement('div');
    toast.id = toastId;
    // Styling: Black (slate-900), sliding from right (translate-x-full to translate-x-0)
    toast.className = `fixed top-6 right-6 bg-slate-900 text-white px-5 py-4 rounded-xl ${shadowColor} border-l-4 ${borderColor} flex items-center gap-3 z-[9999] transform transition-all duration-300 translate-x-32 opacity-0 select-none min-w-[300px] max-w-sm`;
    
    // Capitalize type for title
    let typeTitle = type.charAt(0).toUpperCase() + type.slice(1);
    if(type === 'error_msg') typeTitle = 'Error';

    // HTML Structure
    toast.innerHTML = `
        <div class="flex-shrink-0 ${iconColor}">
            <i data-lucide="${iconName}" class="w-6 h-6"></i>
        </div>
        <div class="flex-1">
            <p class="font-bold text-sm text-slate-100">${typeTitle}</p>
            <p class="text-xs text-slate-400 mt-0.5 leading-relaxed">${message}</p>
        </div>
        <button onclick="dismissCustomPopup('${toastId}')" class="flex-shrink-0 text-slate-500 hover:text-white transition-colors cursor-pointer p-1">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    `;
    
    document.body.appendChild(toast);
    
    // Attempt to render the lucide icons inside the toast
    if (typeof lucide !== 'undefined') {
        lucide.createIcons({
            root: toast
        });
    }

    // Animate In: Slide from right -> left
    requestAnimationFrame(() => {
        setTimeout(() => {
            toast.classList.remove('translate-x-32', 'opacity-0');
            toast.classList.add('translate-x-0', 'opacity-100');
        }, 50); // slight delay to ensure DOM is updated
    });
    
    // Auto dismiss after 2s + 0.5s animation buffer
    const dismissTimer = setTimeout(() => {
        dismissCustomPopup(toastId);
    }, 2500);

    // Store timer on the element so we can clear it if manually closed
    toast.dataset.timer = dismissTimer;
}

function dismissCustomPopup(toastId) {
    const toast = document.getElementById(toastId);
    if (toast) {
        if(toast.dataset.timer) {
            clearTimeout(toast.dataset.timer);
        }
        // Animate Out: Slide right
        toast.classList.remove('translate-x-0', 'opacity-100');
        toast.classList.add('translate-x-32', 'opacity-0');
        
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300); // match duration-300
    }
}

// Map the old triggerAlert if it exists, to not break existing calls immediately.
// We will replace them in code, but this is a fallback.
window.triggerAlert = function(msg) {
    showCustomPopup(msg, 'info');
};
