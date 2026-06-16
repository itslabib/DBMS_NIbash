<?php
session_start();
require_once 'includes/db_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php'; // Path assumes this file is in root

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = "Please enter your registered email address.";
    } else {
        // Check if email exists in the system
        $stmt = mysqli_prepare($conn, "SELECT id, username FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($result)) {
            $user_id = $row['id'];
            $username = $row['username'];
            
            // Generate a random 6-digit OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));

            // Update verification code in DB
            $update_stmt = mysqli_prepare($conn, "UPDATE users SET verification_code = ? WHERE id = ?");
            mysqli_stmt_bind_param($update_stmt, "si", $otp, $user_id);
            mysqli_stmt_execute($update_stmt);

            // Send email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'suchak9931@gmail.com'; 
                $mail->Password   = 'ubaz ayum yhyy hyis';   
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('suchak9931@gmail.com', 'Nibash System');
                $mail->addAddress($email, $username);

                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request | Nibash';
                $mail->Body    = "Hello {$username},<br><br>We received a request to reset your password. Your 6-digit verification code is: <b>{$otp}</b><br><br>If you did not request this, please ignore this email.";

                $mail->send();
                
                // Redirect to the reset form
                header("Location: reset_password.php?email=" . urlencode($email));
                exit();
            } catch (Exception $e) {
                $error = "Failed to send email. Please try again later.";
            }
        } else {
            $error = "No account found with that email address.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Nibash</title>
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
                
                <a href="login.php" class="absolute top-6 left-6 w-10 h-10 flex flex-col items-center justify-center rounded-full bg-slate-50 hover:bg-slate-100 transition-colors border border-slate-200 text-slate-400 hover:text-slate-600 z-20">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i>
                </a>
                
                <div class="text-center mb-8 relative z-10 mt-4">
                    <div class="w-16 h-16 bg-emerald-50 rounded-full flex items-center justify-center mx-auto mb-4 border border-emerald-100">
                        <i data-lucide="key" class="w-8 h-8 text-emerald-500"></i>
                    </div>
                    <h2 class="text-3xl font-extrabold text-slate-900 tracking-tight mb-2">Forgot Password</h2>
                    <p class="text-slate-500 text-sm font-medium">Enter your email to receive a reset code.</p>
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

                <form action="forgot_password.php" method="POST" class="space-y-6 relative z-10">
                    <div>
                        <label for="email" class="block text-[13px] font-bold text-slate-700 mb-2 ml-1 tracking-wide uppercase">Email Address</label>
                        <div class="relative group/input">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="mail" class="w-5 h-5"></i>
                            </div>
                            <input type="email" id="email" name="email" class="block w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-2xl focus:ring-2 focus:ring-emerald-500 focus:bg-white text-slate-900 placeholder-slate-400 transition-all font-semibold outline-none" placeholder="e.g. john@email.com" required>
                        </div>
                    </div>

                    <button type="submit" class="w-full flex justify-center items-center gap-2 mt-8 py-4 px-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-xl shadow-lg shadow-emerald-500/30 transition-all transform hover:-translate-y-0.5 border border-emerald-500 outline-none">
                        Send Reset Code
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <script>lucide.createIcons();</script>

    <?php include 'chatbot/chat_widget.php'; ?>
</body>
</html>