<?php
session_start();
require_once 'includes/db_config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role_id'] == 1) {
        header("Location: " . BASE_URL . "owner/dashboard.php");    } elseif ($_SESSION['role_id'] == 4) {
        header("Location: " . BASE_URL . "essentials/dashboard.php");    } else {
        header("Location: " . BASE_URL . "resident/dashboard.php");
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Access Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="font-sans antialiased overflow-hidden bg-slate-50">

    <div class="fixed inset-0 z-0 transition-transform duration-[10s] ease-linear scale-105" id="background-wrapper">
        <div class="absolute inset-0 bg-slate-50 transition-colors duration-500"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-emerald-50/50 to-teal-50/50 dark:from-emerald-900/20 dark:to-teal-900/20 transition-colors duration-500"></div>
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
            <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-emerald-100/40 dark:bg-emerald-900/30 blur-3xl transition-colors duration-500"></div>
            <div class="absolute top-[60%] -right-[10%] w-[50%] h-[50%] rounded-full bg-teal-100/40 dark:bg-teal-900/30 blur-3xl transition-colors duration-500"></div>
        </div>
    </div>

    <div id="layout-wrapper" class="relative z-10 w-full max-w-7xl mx-auto h-screen flex flex-col md:flex-row items-center justify-center md:justify-between px-6 md:px-12">
        
        <div id="text-content" class="w-full max-w-lg mb-8 md:mb-0 z-20 md:absolute md:left-6 lg:left-12 transition-transform duration-1000 ease-[cubic-bezier(0.68,-0.55,0.265,1.55)]">
            
            <div class="flex items-center justify-center md:justify-start gap-3 mb-6">
                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center border border-emerald-100 shadow-md">
                    <i data-lucide="home" class="w-6 h-6 text-emerald-500"></i>
                </div>
                <span class="text-2xl font-extrabold text-slate-800 tracking-widest">Nibash</span>
            </div>
            
            <h1 class="text-4xl md:text-5xl lg:text-7xl font-extrabold text-slate-900 tracking-tight mb-6 leading-tight text-center md:text-left">
                Centralized Residential <br class="hidden md:block" />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-teal-400"> Platform</span>
            </h1>
            
            <p class="text-base md:text-lg lg:text-xl font-light text-slate-600 leading-relaxed max-w-md mx-auto md:mx-0 mb-8 text-center md:text-left">
                Experience seamless building operations and secure management.
            </p>

            <ul class="space-y-4 hidden md:block">
                <li class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i>
                    </div>
                    <span class="text-slate-600 font-medium">Smart Access Control</span>
                </li>
                <li class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i>
                    </div>
                    <span class="text-slate-600 font-medium">Centralized Management</span>
                </li>
                <li class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i>
                    </div>
                    <span class="text-slate-600 font-medium">Secure Communication</span>
                </li>
            </ul>
        </div>

        <div id="form-container" class="w-full max-w-md z-20 md:absolute md:right-6 lg:right-12 transition-transform duration-1000 ease-[cubic-bezier(0.68,-0.55,0.265,1.55)]">

            <div class="bg-white border border-slate-200 p-8 sm:p-10 rounded-[2.5rem] shadow-xl relative overflow-hidden group/glass">
                
                <a href="index.php" class="absolute top-6 left-6 w-10 h-10 flex flex-col items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 transition-colors border border-slate-200 text-slate-400 hover:text-slate-600 z-20 group peer" aria-label="Go Back">
                    <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform duration-300"></i>
                </a>
                
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-100/50 rounded-full blur-[40px] pointer-events-none transition-transform duration-700 group-hover/glass:scale-125"></div>
                <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-teal-100/50 rounded-full blur-[40px] pointer-events-none transition-transform duration-700 group-hover/glass:scale-125"></div>

                <div class="text-center mb-8 relative z-10">
                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-2">Access Portal</h2>
                    <p class="text-slate-500 text-sm font-medium">Verify your identity to continue</p>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <?php 
                        $msg = "An error occurred.";
                        if ($_GET['error'] == 'invalid_credentials') $msg = "Invalid username or password.";
                        else if ($_GET['error'] == 'invalid_role') $msg = "Residents cannot log in as Owners.";
                        else if ($_GET['error'] == 'invalid_role_admin') $msg = "Owners cannot log in as residents.";
                        else if ($_GET['error'] == 'unauthorized') $msg = "Please log in to access this page.";
                        else if ($_GET['error'] == 'empty_fields') $msg = "Please fill in all fields.";
                        else if ($_GET['error'] == 'account_pending') $msg = "Your account is under checking and will be active after the admin manually confirms it. It may take 5-10 hours.";
                        else if ($_GET['error'] == 'account_suspended') $msg = "Your account has been suspended.";
                    ?>
                    <div class="bg-red-50 border border-red-200 p-4 mb-6 rounded-2xl flex shadow-sm relative z-10">
                        <div class="flex-shrink-0 bg-red-100 rounded-full p-1.5 border border-red-200">
                            <i data-lucide="alert-triangle" class="h-4 w-4 text-red-600"></i>
                        </div>
                        <div class="ml-3 flex items-center">
                            <p class="text-sm text-red-700 font-bold tracking-wide"><?= htmlspecialchars($msg) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['msg']) && $_GET['type'] == 'info'): ?>
                    <div class="bg-blue-50 border border-blue-200 p-4 mb-6 rounded-2xl flex shadow-sm relative z-10">
                        <div class="flex-shrink-0 bg-blue-100 rounded-full p-1.5 border border-blue-200">
                            <i data-lucide="info" class="h-4 w-4 text-blue-600"></i>
                        </div>
                        <div class="ml-3 flex items-center">
                            <p class="text-sm text-blue-700 font-bold tracking-wide"><?= htmlspecialchars($_GET['msg']) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['success']) && $_GET['success'] == 'password_reset'): ?>
                    <div class="bg-emerald-50 border border-emerald-200 p-4 mb-6 rounded-2xl flex shadow-sm relative z-10">
                        <div class="flex-shrink-0 bg-emerald-100 rounded-full p-1.5 border border-emerald-200">
                            <i data-lucide="check-circle" class="h-4 w-4 text-emerald-600"></i>
                        </div>
                        <div class="ml-3 flex items-center">
                            <p class="text-sm text-emerald-700 font-bold tracking-wide">Password reset successfully. Please log in.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="login_process.php" method="POST" class="space-y-6 relative z-10">
                    
                    <div class="flex p-1.5 bg-slate-100 rounded-2xl mb-8 relative border border-slate-200 shadow-inner">
                        <div class="absolute inset-y-1.5 left-1.5 w-[calc(50%-6px)] bg-white border border-slate-200 rounded-xl shadow-sm transition-transform duration-500 ease-out" id="role-slider-bg"></div>
                        
                        <button type="button" onclick="switchRole('resident')" class="relative w-1/2 py-3 text-sm font-bold rounded-xl text-emerald-600 transition-colors z-10 flex items-center justify-center gap-2" id="btn-tab-resident">
                            <i data-lucide="users" class="w-4 h-4"></i> Resident
                        </button>
                        
                        <button type="button" onclick="switchRole('admin')" class="relative w-1/2 py-3 text-sm font-medium rounded-xl text-slate-500 hover:text-slate-700 transition-colors z-10 flex items-center justify-center gap-2" id="btn-tab-owner">
                            <i data-lucide="briefcase" class="w-4 h-4"></i> Owner
                        </button>
                    </div>

                    <input type="hidden" id="authRole" name="authRole" value="resident">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars(isset($_GET['redirect']) ? $_GET['redirect'] : '') ?>">

                    <div class="space-y-4">
                        <div>
                            <label for="username" class="block text-[13px] font-bold text-slate-700 mb-2 ml-1 tracking-wide uppercase">Username or Email</label>
                            <div class="relative group/input">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors text-slate-400 group-focus-within/input:text-emerald-500">
                                    <i data-lucide="user" class="w-5 h-5"></i>
                                </div>
                                <input type="text" id="username" name="username" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 hover:bg-slate-100 focus:bg-white border border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-slate-900 placeholder-slate-400 transition-all font-semibold sm:text-sm outline-none" placeholder="e.g. johndoe123 or john@email.com" required>
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-[13px] font-bold text-slate-700 mb-2 ml-1 tracking-wide uppercase flex justify-between">
                                <span>Password</span>
                                <a href="forgot_password.php" class="text-emerald-500 hover:text-emerald-600 transition-colors font-medium lowercase capitalize-first">Forgot?</a>
                            </label>
                            <div class="relative group/input">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors text-slate-400 group-focus-within/input:text-emerald-500">
                                    <i data-lucide="lock" class="w-5 h-5"></i>
                                </div>
                                <input type="password" id="password" name="password" class="block w-full pl-11 pr-12 py-3.5 bg-slate-50 hover:bg-slate-100 focus:bg-white border border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-slate-900 placeholder-slate-400 transition-all font-semibold sm:text-sm outline-none" placeholder="••••••••" required>
                                <button type="button" onclick="const p = document.getElementById('password'); const i = this.querySelector('i'); if (p.type === 'password') { p.type = 'text'; i.setAttribute('data-lucide', 'eye-off'); } else { p.type = 'password'; i.setAttribute('data-lucide', 'eye'); } lucide.createIcons();" class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-emerald-500 transition-colors focus:outline-none">
                                    <i data-lucide="eye" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full flex justify-center items-center gap-2 mt-8 py-4 px-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl shadow-lg shadow-emerald-500/30 transition-all transform hover:-translate-y-0.5 border border-emerald-500 outline-none">
                        Sign In Securely
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                    
                    <div class="mt-8 relative h-12 flex items-center justify-center">
                        <div id="note-resident" class="absolute inset-0 flex items-center justify-center transition-all duration-500">
                            <div class="flex items-center gap-2">
                                <i data-lucide="info" class="w-4 h-4 text-slate-400"></i>
                                <p class="text-xs text-slate-500 font-medium">Account creation locked. Contact Manager.</p>
                            </div>
                        </div>

                        <div id="note-owner" class="absolute inset-0 flex items-center justify-center gap-2 transition-all duration-500 opacity-0 translate-y-4 pointer-events-none">
                            <span class="text-sm text-slate-600 font-medium">New building admin?</span>
                            <a href="<?php echo BASE_URL; ?>owner/register.php" class="inline-flex items-center text-emerald-600 hover:text-emerald-700 font-bold text-sm tracking-wide transition-colors border-b border-emerald-600/30 hover:border-emerald-600">
                                Create Account
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
    lucide.createIcons();

    function switchRole(role) {
        const wrapper = document.getElementById('layout-wrapper');
        const textContent = document.getElementById('text-content');
        const formContainer = document.getElementById('form-container');
        
        const roleSliderBg = document.getElementById('role-slider-bg');
        const btnResident = document.getElementById('btn-tab-resident');
        const btnOwner = document.getElementById('btn-tab-owner');
        const roleInput = document.getElementById('authRole');
        
        const noteResident = document.getElementById('note-resident');
        const noteOwner = document.getElementById('note-owner');
        
        roleInput.value = role;

        if (role === 'admin') {
            if (window.innerWidth >= 768) {
                const moveDistForm = wrapper.clientWidth - formContainer.clientWidth;
                const moveDistText = wrapper.clientWidth - textContent.clientWidth;
                
                formContainer.style.transform = `translateX(-${moveDistForm}px)`;
                textContent.style.transform = `translateX(${moveDistText}px)`;
            }

            roleSliderBg.style.transform = 'translateX(100%)';
            btnOwner.classList.replace('text-slate-500', 'text-emerald-600');
            btnOwner.classList.replace('font-medium', 'font-bold');
            btnResident.classList.replace('text-emerald-600', 'text-slate-500');
            btnResident.classList.replace('font-bold', 'font-medium');

            noteResident.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none');
            noteOwner.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none');

        } else {
            if (window.innerWidth >= 768) {
                formContainer.style.transform = `translateX(0)`;
                textContent.style.transform = `translateX(0)`;
            }
            
            roleSliderBg.style.transform = 'translateX(0)';
            btnResident.classList.replace('text-slate-500', 'text-emerald-600');
            btnResident.classList.replace('font-medium', 'font-bold');
            btnOwner.classList.replace('text-emerald-600', 'text-slate-500');
            btnOwner.classList.replace('font-bold', 'font-medium');

            noteOwner.classList.add('opacity-0', 'translate-y-4', 'pointer-events-none');
            noteResident.classList.remove('opacity-0', 'translate-y-4', 'pointer-events-none');
        }
    }

    window.addEventListener('resize', () => {
        const textContent = document.getElementById('text-content');
        const formContainer = document.getElementById('form-container');
        if (window.innerWidth < 768) {
            textContent.style.transform = '';
            formContainer.style.transform = '';
        } else {
            switchRole(document.getElementById('authRole').value);
        }
    });

    document.addEventListener('mousemove', (e) => {
        const bg = document.getElementById('background-wrapper');
        const x = (window.innerWidth / 2 - e.pageX) / 100;
        const y = (window.innerHeight / 2 - e.pageY) / 100;
        bg.style.transform = `translate(${x}px, ${y}px) scale(1.02)`;
    });
</script>

    <?php include 'chatbot/chat_widget.php'; ?>
</body>
</html>