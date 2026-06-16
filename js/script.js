// js/script.js

// Initialize Lucide icons on DOM Load
document.addEventListener("DOMContentLoaded", () => {
    lucide.createIcons();
});

// Smooth scroll implementation handled by Tailwind's "scroll-smooth" class on HTML
// Navbar scroll effect
window.addEventListener('scroll', () => {
    const navbar = document.getElementById('navbar');
    if (window.scrollY > 10) {
        navbar.classList.add('glass');
        navbar.classList.replace('py-4', 'py-2');
    } else {
        navbar.classList.remove('glass');
        navbar.classList.replace('py-2', 'py-4');
    }
});

// Toggle Modal Functionality
function toggleModal(modalID) {
    const modal = document.getElementById(modalID);
    const backdrop = document.getElementById('login-backdrop');
    const panel = document.getElementById('login-panel');
    
    if (modal.classList.contains('hidden')) {
        // Open modal
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Allow CSS transition to process
        setTimeout(() => {
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            panel.classList.remove('opacity-0', 'scale-95');
            panel.classList.add('opacity-100', 'scale-100');
        }, 10);
    } else {
        // Close modal
        backdrop.classList.remove('opacity-100');
        backdrop.classList.add('opacity-0');
        panel.classList.remove('opacity-100', 'scale-100');
        panel.classList.add('opacity-0', 'scale-95');
        
        // Wait for transition to finish
        setTimeout(() => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }, 300);
    }
}

// Role Toggle Logic
function setRole(role) {
    document.getElementById('authRole').value = role;
    const indicator = document.getElementById('role-indicator');
    const btnResident = document.getElementById('btn-resident');
    const btnAdmin = document.getElementById('btn-admin');

    if(role === 'admin') {
        indicator.style.transform = 'translateX(100%)';
        btnAdmin.classList.replace('text-gray-500', 'text-navy');
        btnAdmin.classList.replace('font-medium', 'font-semibold');
        btnResident.classList.replace('text-navy', 'text-gray-500');
        btnResident.classList.replace('font-semibold', 'font-medium');
        
        // Show owner register button, hide resident note
        if (document.getElementById('role-note-owner')) {
            document.getElementById('role-note-owner').classList.remove('hidden');
            document.getElementById('role-note-resident').classList.add('hidden');
            document.getElementById('role-note-owner').style.display = 'flex';
            document.getElementById('role-note-resident').style.display = 'none';
        }
    } else {
        indicator.style.transform = 'translateX(0)';
        btnResident.classList.replace('text-gray-500', 'text-navy');
        btnResident.classList.replace('font-medium', 'font-semibold');
        btnAdmin.classList.replace('text-navy', 'text-gray-500');
        btnAdmin.classList.replace('font-semibold', 'font-medium');
        
        // Show resident note, hide owner register button
        if (document.getElementById('role-note-resident')) {
            document.getElementById('role-note-resident').classList.remove('hidden');
            document.getElementById('role-note-owner').classList.add('hidden');
            document.getElementById('role-note-resident').style.display = 'flex';
            document.getElementById('role-note-owner').style.display = 'none';
        }
    }
}

// Random Billing Demo Logic
function generateRandomBill() {
    const billBtn = document.getElementById('demo-bill-btn');
    const amountSpan = document.getElementById('demo-bill-amount');
    
    billBtn.disabled = true;
    amountSpan.innerText = 'Calculating...';
    amountSpan.classList.add('animate-pulse');

    setTimeout(() => {
        // Random electric bill between $45 and $120
        const randomElectric = (Math.random() * (120 - 45) + 45).toFixed(2);
        amountSpan.innerText = '$' + randomElectric;
        amountSpan.classList.remove('animate-pulse');
        billBtn.disabled = false;
        
        // Generate a fun toast notification simulation
        console.log(`Generated Electric Bill: $${randomElectric}. Fixed Water & Gas added.`);
    }, 800);
}


