<?php
session_start();
require_once 'includes/db_config.php';
$hasColumn = static function ($conn, $table, $column) {
    $table = mysqli_real_escape_string($conn, $table);
    $column = mysqli_real_escape_string($conn, $column);
    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $check && mysqli_num_rows($check) > 0;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased">
    <nav class="fixed top-0 inset-x-0 z-50 bg-white/70 backdrop-blur-xl border-b border-emerald-100/50 py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                    <i data-lucide="home" class="w-5 h-5 text-white"></i>
                </div>
                <span class="text-xl font-bold tracking-tight">Nibash</span>
            </a>
            <a href="index.php" class="text-sm font-medium text-slate-600 hover:text-emerald-600">Back to Home</a>
        </div>
    </nav>

    <section class="pt-32 pb-20 px-4">
        <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-8 md:p-12">
            <div class="text-center mb-12">
                <h1 class="text-3xl font-bold text-slate-900 mb-4">Contact Developer</h1>
                <p class="text-slate-600">Get in touch with the creator of Nibash for any inquiries or support.</p>
            </div>

            <div class="space-y-6 max-w-xl mx-auto">
                <div class="flex items-center gap-4 p-4 rounded-xl border border-slate-100 bg-slate-50">
                    <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                        <i data-lucide="user" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 font-medium">Name</p>
                        <p class="text-lg font-semibold text-slate-900">Ahnaf Tajwar Suchak</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-4 rounded-xl border border-slate-100 bg-slate-50">
                    <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 font-medium">Team</p>
                        <p class="text-lg font-semibold text-slate-900">Zero-NF</p>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-4 rounded-xl border border-slate-100 bg-slate-50">
                    <div class="w-12 h-12 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                        <i data-lucide="mail" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 font-medium">Email</p>
                        <a href="mailto:ahanftajwarsuchak@gmail.com" class="text-lg font-semibold text-slate-900 hover:text-emerald-600 transition-colors">ahanftajwarsuchak@gmail.com</a>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-4 rounded-xl border border-slate-100 bg-slate-50">
                    <div class="w-12 h-12 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center shrink-0">
                        <i data-lucide="phone" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <p class="text-sm text-slate-500 font-medium">Phone</p>
                        <a href="tel:01301085365" class="text-lg font-semibold text-slate-900 hover:text-emerald-600 transition-colors">01301085365</a>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-4 mt-8 pt-4">
                    <a href="https://github.com/atsuchak" target="_blank" class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-slate-900 text-white rounded-xl font-semibold hover:bg-slate-800 transition-colors">
                        <i data-lucide="github" class="w-5 h-5"></i> GitHub
                    </a>
                    <a href="https://linkedin.com/in/atsuchak" target="_blank" class="flex-1 flex items-center justify-center gap-2 px-6 py-3 bg-[#0a66c2] text-white rounded-xl font-semibold hover:bg-[#004182] transition-colors">
                        <i data-lucide="linkedin" class="w-5 h-5"></i> LinkedIn
                    </a>
                </div>
            </div>
        </div>
    </section>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>