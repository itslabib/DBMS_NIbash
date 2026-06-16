<?php
session_start();
require_once 'includes/db_config.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role_id'] == 1) {
        header("Location: " . BASE_URL . "owner/dashboard.php");
    } elseif ($_SESSION['role_id'] == 4) {
        header("Location: " . BASE_URL . "essentials/dashboard.php");
    } else {
        header("Location: " . BASE_URL . "resident/dashboard.php");
    }
    exit;
}

$error = '';
$success = '';
// Grab the email from the URL if it was passed from the registration redirect
$email = isset($_GET['email']) ? htmlspecialchars(trim($_GET['email'])) : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $post_email = trim($_POST['email']);
    $otp = trim($_POST['otp']);

    if (empty($post_email) || empty($otp)) {
        $error = "Please provide both your email and the verification code.";
    } else {
        // Find the user by email
        $stmt = mysqli_prepare($conn, "SELECT id, email, username, role_id, password, is_verified, verification_code, status FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $post_email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            if ($row['is_verified'] == 1) {
                $error = "This account is already verified. Please go to the login page.";
            } elseif ($row['verification_code'] === $otp) {
                
                // Code matches! Update the database to verify them
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "i", $row['id']);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    
                    if (($row['role_id'] == 4 || $row['role_id'] == 1) && $row['status'] === 'inactive') {
                        // Provider or Owner is verified but needs admin approval
                        $success_msg = "Account verified! Your account is under checking and will be active after the admin manually confirms it. It may take 5-10 hours.";
                        if ($row['role_id'] == 4) {
                            header("Location: " . BASE_URL . "essentials/login.php?msg=" . urlencode($success_msg) . "&type=info");
                        } else {
                            header("Location: " . BASE_URL . "login.php?msg=" . urlencode($success_msg) . "&type=info");
                        }
                        exit();
                    } else {
                        // Immediately log the user in to save them a step
                        $_SESSION['user_id'] = $row['id'];
                        $_SESSION['email'] = $row['email'];
                        $_SESSION['username'] = $row['username'];
                        $_SESSION['role_id'] = $row['role_id'];

                        // Redirect to their respective dashboard
                        if ($row['role_id'] == 1) {
                            header("Location: " . BASE_URL . "owner/dashboard.php");
                        } elseif ($row['role_id'] == 4) {
                            header("Location: " . BASE_URL . "essentials/dashboard.php");
                        } else {
                            header("Location: " . BASE_URL . "resident/dashboard.php");
                        }
                        exit();
                    }
                } else {
                    $error = "A database error occurred. Please try again.";
                }
            } else {
                $error = "Invalid verification code. Please check your email and try again.";
            }
        } else {
            $error = "No account found with that email address.";
        }
    }
    // Keep the email in the input field if there was an error
    $email = $post_email; 
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Account | Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="font-sans antialiased overflow-hidden bg-slate-50">

    <div class="fixed inset-0 z-0 transition-transform duration-[10s] ease-linear scale-105" id="background-wrapper">
        <div class="absolute inset-0 bg-white"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-emerald-50/50 to-teal-50/50"></div>
        <div class="absolute top-0 left-0 w-full h-full overflow-hidden pointer-events-none">
            <div class="absolute -top-[10%] -left-[10%] w-[40%] h-[40%] rounded-full bg-emerald-100/40 blur-3xl"></div>
            <div class="absolute top-[60%] -right-[10%] w-[50%] h-[50%] rounded-full bg-teal-100/40 blur-3xl"></div>
        </div>
    </div>

    <div class="relative z-10 w-full max-w-7xl mx-auto h-screen flex flex-col items-center justify-center px-6">
        
        <div class="w-full max-w-md z-20">
            <div class="bg-white border border-slate-200 p-8 sm:p-10 rounded-[2.5rem] shadow-xl relative overflow-hidden group/glass">
                
                <a href="login.php" class="absolute top-6 left-6 w-10 h-10 flex flex-col items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 transition-colors border border-slate-200 text-slate-400 hover:text-slate-600 z-20 group peer" aria-label="Go to Login">
                    <i data-lucide="arrow-left" class="w-4 h-4 group-hover:-translate-x-0.5 transition-transform duration-300"></i>
                </a>
                
                <div class="absolute -top-24 -right-24 w-48 h-48 bg-emerald-100/50 rounded-full blur-[40px] pointer-events-none transition-transform duration-700 group-hover/glass:scale-125"></div>
                <div class="absolute -bottom-24 -left-24 w-48 h-48 bg-teal-100/50 rounded-full blur-[40px] pointer-events-none transition-transform duration-700 group-hover/glass:scale-125"></div>

                <div class="text-center mb-8 relative z-10 mt-4">
                    <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-emerald-100">
                        <i data-lucide="shield-check" class="w-8 h-8 text-emerald-500"></i>
                    </div>
                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-2">Verify Account</h2>
                    <p class="text-slate-500 text-sm font-medium">Enter the 6-digit code sent to your email.</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 p-4 mb-6 rounded-2xl flex shadow-sm relative z-10">
                        <div class="flex-shrink-0 bg-red-100 rounded-full p-1.5 border border-red-200">
                            <i data-lucide="alert-triangle" class="h-4 w-4 text-red-600"></i>
                        </div>
                        <div class="ml-3 flex items-center">
                            <p class="text-sm text-red-700 font-bold tracking-wide"><?= htmlspecialchars($error) ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form action="verify.php" method="POST" class="space-y-6 relative z-10">
                    
                    <div class="space-y-4">
                        <div>
                            <label for="email" class="block text-[13px] font-bold text-slate-700 mb-2 ml-1 tracking-wide uppercase">Email Address</label>
                            <div class="relative group/input">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors text-slate-400 group-focus-within/input:text-emerald-500">
                                    <i data-lucide="mail" class="w-5 h-5"></i>
                                </div>
                                <input type="email" id="email" name="email" value="<?= $email ?>" <?= !empty($email) ? 'readonly' : '' ?> class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 hover:bg-slate-100 focus:bg-white border border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-slate-900 placeholder-slate-400 transition-all font-semibold sm:text-sm outline-none <?= !empty($email) ? 'opacity-80 cursor-not-allowed' : '' ?>" placeholder="e.g. john@email.com" required>
                            </div>
                        </div>

                        <div>
                            <label for="otp" class="block text-[13px] font-bold text-slate-700 mb-2 ml-1 tracking-wide uppercase">6-Digit Code</label>
                            <div class="relative group/input">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors text-slate-400 group-focus-within/input:text-emerald-500">
                                    <i data-lucide="key-round" class="w-5 h-5"></i>
                                </div>
                                <input type="text" id="otp" name="otp" maxlength="6" pattern="\d{6}" title="Please enter exactly 6 digits" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 hover:bg-slate-100 focus:bg-white border border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:border-transparent text-slate-900 placeholder-slate-400 transition-all font-bold tracking-[0.5em] text-center sm:text-lg outline-none" placeholder="••••••" required autocomplete="off">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="w-full flex justify-center items-center gap-2 mt-8 py-4 px-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl shadow-lg shadow-emerald-500/30 transition-all transform hover:-translate-y-0.5 border border-emerald-500 outline-none">
                        Verify & Login
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                    
                </form>
            </div>
        </div>
    </div>

<script>
    lucide.createIcons();

    // Subtle background animation matching the login page
    document.addEventListener('mousemove', (e) => {
        const bg = document.getElementById('background-wrapper');
        const x = (window.innerWidth / 2 - e.pageX) / 100;
        const y = (window.innerHeight / 2 - e.pageY) / 100;
        bg.style.transform = `translate(${x}px, ${y}px) scale(1.02)`;
    });
</script>
</body>
</html>