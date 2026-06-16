<?php
session_start();
require_once '../includes/db_config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role_id'] == 4) {
        header("Location: " . BASE_URL . "essentials/dashboard.php");
        exit;
    } else {
        header("Location: " . BASE_URL . "index.php");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_id = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_id) || empty($password)) {
        header("Location: " . BASE_URL . "essentials/login.php?error=empty_fields");
        exit();
    }

    $stmt = mysqli_prepare($conn, "SELECT id, username, email, password, role_id, is_verified, status FROM users WHERE (username = ? OR email = ?) AND role_id = 4");
    mysqli_stmt_bind_param($stmt, "ss", $login_id, $login_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        // Support both hashed passwords and plaintext (for legacy)
        if (password_verify($password, $row['password']) || $password === $row['password']) {

            if (isset($row['status']) && $row['status'] === 'suspended') {
                header("Location: " . BASE_URL . "essentials/login.php?error=account_suspended");
                exit();
            }

            if ($row['is_verified'] == 0) {
                header("Location: " . BASE_URL . "verify.php?email=" . urlencode($row['email']));
                exit();
            }

            $_SESSION['user_id'] = $row['id'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role_id'] = $row['role_id'];
            $_SESSION['building_id'] = null; // Providers might not be tied to a single building in session

            if (!empty($_POST['redirect'])) {
                header("Location: " . BASE_URL . ltrim($_POST['redirect'], '/'));
                exit();
            } else {
                header("Location: " . BASE_URL . "essentials/dashboard.php");
                exit();
            }
        } else {
            header("Location: " . BASE_URL . "essentials/login.php?error=invalid_credentials");
            exit();
        }
    } else {
        header("Location: " . BASE_URL . "essentials/login.php?error=invalid_credentials");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Login | Nibash Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="font-sans antialiased overflow-hidden bg-slate-50">

    <div class="fixed inset-0 z-0 transition-transform duration-[10s] ease-linear scale-105" id="background-wrapper">
        <div class="absolute inset-0 bg-slate-50 transition-colors duration-500"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-emerald-50/50 to-teal-50/50 transition-colors duration-500"></div>
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
            <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-emerald-100/40 blur-3xl transition-colors duration-500"></div>
            <div class="absolute top-[60%] -right-[10%] w-[50%] h-[50%] rounded-full bg-teal-100/40 blur-3xl transition-colors duration-500"></div>
        </div>
    </div>

    <div class="relative z-10 w-full max-w-7xl mx-auto h-screen flex flex-col md:flex-row items-center justify-center md:justify-between px-6 md:px-12">
        
        <div class="w-full max-w-lg mb-8 md:mb-0 z-20 md:absolute md:left-6 lg:left-12">
            
            <div class="flex items-center justify-center md:justify-start gap-3 mb-6">
                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center border border-emerald-100 shadow-md">
                    <i data-lucide="briefcase" class="w-6 h-6 text-emerald-500"></i>
                </div>
                <span class="text-2xl font-extrabold text-slate-800 tracking-widest">Nibash Provider</span>
            </div>
            
            <h1 class="text-4xl md:text-5xl lg:text-7xl font-extrabold text-slate-900 tracking-tight mb-6 leading-tight text-center md:text-left">
                Grow Your <br class="hidden md:block" />
                <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-teal-400"> Service Business</span>
            </h1>
            
            <p class="text-base md:text-lg lg:text-xl font-light text-slate-600 leading-relaxed max-w-md mx-auto md:mx-0 mb-8 text-center md:text-left">
                Manage requests, view schedules, and expand your coverage area.
            </p>

            <ul class="space-y-4 hidden md:block">
                <li class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i>
                    </div>
                    <span class="text-slate-600 font-medium">Reach more clients</span>
                </li>
                <li class="flex items-center gap-3">
                    <div class="flex-shrink-0 w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center">
                        <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-600"></i>
                    </div>
                    <span class="text-slate-600 font-medium">Manage your schedule</span>
                </li>
            </ul>
        </div>

        <div class="w-full max-w-md z-20 md:absolute md:right-6 lg:right-12">

            <div class="bg-white border border-slate-200 p-8 sm:p-10 rounded-[2.5rem] shadow-xl relative overflow-hidden group/glass">
                
                <a href="../index.php" class="absolute top-6 left-6 w-10 h-10 flex flex-col items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 transition-colors border border-slate-200 text-slate-400 hover:text-slate-600 z-20 group peer" aria-label="Go Back">
                    <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform duration-300"></i>
                </a>

                <div class="text-center mb-8 relative z-10 mt-6">
                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-2">Provider Login</h2>
                    <p class="text-slate-500 text-sm font-medium">Welcome back, partner!</p>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <?php 
                        $msg = "An error occurred.";
                        if ($_GET['error'] == 'invalid_credentials') $msg = "Invalid username or password.";
                        else if ($_GET['error'] == 'account_suspended') $msg = "Your account is suspended.";
                        else if ($_GET['error'] == 'empty_fields') $msg = "Please fill in all fields.";
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

                <form action="" method="POST" class="space-y-6 relative z-10">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars(isset($_GET['redirect']) ? $_GET['redirect'] : '') ?>">

                    <div class="space-y-4">
                        <div>
                            <label for="username" class="block text-[13px] font-bold text-slate-700 mb-2 ml-1 tracking-wide uppercase">Username or Email</label>
                            <div class="relative group/input">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors text-slate-400 group-focus-within/input:text-emerald-500">
                                    <i data-lucide="user" class="w-5 h-5"></i>
                                </div>
                                <input type="text" id="username" name="username" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 hover:bg-slate-100 focus:bg-white border border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-slate-900 placeholder-slate-400 transition-all font-semibold sm:text-sm outline-none" placeholder="e.g. johndoe or john@email.com" required>
                            </div>
                        </div>

                        <div>
                            <label for="password" class="block text-[13px] font-bold text-slate-700 mb-2 ml-1 tracking-wide uppercase flex justify-between">
                                <span>Password</span>
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
                        Login to Dashboard
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                    
                    <div class="mt-6 text-center">
                        <span class="text-sm text-slate-600 font-medium">Want to offer your services?</span>
                        <a href="register.php" class="inline-flex items-center text-emerald-600 hover:text-emerald-700 font-bold text-sm tracking-wide transition-colors border-b border-emerald-600/30 hover:border-emerald-600 ml-1">
                            Register Now
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
    lucide.createIcons();
    document.addEventListener('mousemove', (e) => {
        const bg = document.getElementById('background-wrapper');
        const x = (window.innerWidth / 2 - e.pageX) / 100;
        const y = (window.innerHeight / 2 - e.pageY) / 100;
        bg.style.transform = `translate(${x}px, ${y}px) scale(1.02)`;
    });
</script>
</body>
</html>
