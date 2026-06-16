<?php
session_start();
require_once 'includes/db_config.php';

$error = '';
$email = isset($_GET['email']) ? htmlspecialchars(trim($_GET['email'])) : '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $post_email = trim($_POST['email']);
    $otp = trim($_POST['otp']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Check for empty fields
    if (empty($otp) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } 
    // 2. Check if passwords match
    elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } 
    // 3. SECURE PASSWORD VALIDATION (Min 6 chars, uppercase, lowercase, number)
    elseif (strlen($new_password) < 6 || !preg_match('/[a-z]/', $new_password) || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $error = "Password must be at least 6 characters and contain an uppercase letter, a lowercase letter, and a number.";
    } 
    // 4. Proceed if all validation passes
    else {
        // Fetch user based on email
        $stmt = mysqli_prepare($conn, "SELECT id, verification_code FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $post_email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            // Check if OTP matches and isn't empty
            if (!empty($row['verification_code']) && $row['verification_code'] === $otp) {
                
                // Code is correct, update password and clear verification code
                $update_stmt = mysqli_prepare($conn, "UPDATE users SET password = ?, verification_code = NULL WHERE id = ?");
                mysqli_stmt_bind_param($update_stmt, "si", $new_password, $row['id']);
                
                if (mysqli_stmt_execute($update_stmt)) {
                    // Redirect back to login with success parameter
                    header("Location: login.php?success=password_reset");
                    exit();
                } else {
                    $error = "A database error occurred. Please try again.";
                }
            } else {
                $error = "Invalid or expired verification code.";
            }
        } else {
            $error = "Invalid request.";
        }
    }
    $email = $post_email; // Retain email on error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password | Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="font-sans antialiased overflow-hidden bg-slate-50">

    <div class="fixed inset-0 z-0 transition-transform duration-[10s] ease-linear scale-105" id="background-wrapper">
        <div class="absolute inset-0 bg-white"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-emerald-50/50 to-teal-50/50"></div>
    </div>

    <div class="relative z-10 w-full max-w-7xl mx-auto h-screen flex flex-col items-center justify-center px-6">
        <div class="w-full max-w-md z-20">
            <div class="bg-white border border-slate-200 p-8 sm:p-10 rounded-[2.5rem] shadow-xl relative overflow-hidden">
                
                <div class="text-center mb-8 relative z-10">
                    <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-emerald-100">
                        <i data-lucide="lock" class="w-8 h-8 text-emerald-500"></i>
                    </div>
                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-2">New Password</h2>
                    <p class="text-slate-500 text-sm font-medium">Enter your 6-digit code and a secure new password.</p>
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

                <form action="reset_password.php" method="POST" class="space-y-5 relative z-10">
                    <input type="hidden" name="email" value="<?= $email ?>">

                    <div>
                        <label for="otp" class="block text-[13px] font-bold text-slate-700 mb-2 ml-1 tracking-wide uppercase">6-Digit Code</label>
                        <div class="relative group/input">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="key-round" class="w-5 h-5"></i>
                            </div>
                            <input type="text" id="otp" name="otp" maxlength="6" pattern="\d{6}" class="block w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 text-slate-900 placeholder-slate-400 font-bold tracking-[0.5em] text-center outline-none" placeholder="••••••" required autocomplete="off">
                        </div>
                    </div>

                    <div>
                        <label for="new_password" class="block text-[13px] font-bold text-slate-700 mb-2 ml-1 tracking-wide uppercase">New Password</label>
                        <div class="relative group/input">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="shield" class="w-5 h-5"></i>
                            </div>
                            <input type="password" id="new_password" name="new_password" 
                                pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{6,}" 
                                title="Must contain at least 6 characters, including uppercase, lowercase, and numbers"
                                class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 text-slate-900 placeholder-slate-400 font-semibold outline-none" placeholder="••••••••" required>
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1.5 ml-1 font-medium">Requires at least 6 chars, 1 uppercase, 1 lowercase, 1 number.</p>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-[13px] font-bold text-slate-700 mb-2 ml-1 tracking-wide uppercase">Confirm Password</label>
                        <div class="relative group/input">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="shield-check" class="w-5 h-5"></i>
                            </div>
                            <input type="password" id="confirm_password" name="confirm_password" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 text-slate-900 placeholder-slate-400 font-semibold outline-none" placeholder="••••••••" required>
                        </div>
                    </div>

                    <button type="submit" class="w-full flex justify-center items-center gap-2 mt-8 py-4 px-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl shadow-lg shadow-emerald-500/30 transition-all transform hover:-translate-y-0.5 border border-emerald-500 outline-none">
                        Save New Password
                        <i data-lucide="check" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script>lucide.createIcons();</script>

    <?php include 'chatbot/chat_widget.php'; ?>
</body>
</html>