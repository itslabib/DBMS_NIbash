<?php
session_start();
require_once 'includes/db_config.php';
?>
<!DOCTYPE html>
<html class="scroll-smooth" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Zero-NF - Nibash</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Monaco', 'Consolas', "Liberation Mono", "Courier New", 'monospace'],
                    },
                    colors: {
                        emerald: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        },
                        slate: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#94a3b8',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                        }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        .perspective-container {
            /* Apply perspective individually to each card wrapper so they all twist symmetrically around their own center */
            perspective: 1200px;
        }
        
        .transform-3d {
            transform-style: preserve-3d;
        }
        
        .tilt-card {
            /* Reduced twist as requested */
            transform: rotateY(8deg);
            transform-origin: center;
            transition: transform 0.6s cubic-bezier(0.23, 1, 0.32, 1), box-shadow 0.6s cubic-bezier(0.23, 1, 0.32, 1);
            box-shadow: -10px 10px 20px rgba(0, 0, 0, 0.06);
        }
        
        /* Mobile: disable tilt since side-by-side isn't visible the same way */
        @media (max-width: 1024px) {
            .tilt-card {
                transform: rotateY(0deg);
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            }
        }
        
        /* Hover Effect: Straightens out the card and lifts it slightly towards the user */
        .group:hover .tilt-card {
            transform: rotateY(0deg) translateZ(30px);
            box-shadow: 0 25px 50px -12px rgba(16, 185, 129, 0.25);
            z-index: 20;
        }

        .gradient-scrim {
            /* Smooth left-to-right gradient, getting very dark on the right for text readability */
            background: linear-gradient(to right, 
                transparent 0%, 
                rgba(15, 23, 42, 0.2) 30%, 
                rgba(15, 23, 42, 0.8) 60%, 
                rgba(15, 23, 42, 0.95) 100%
            );
        }

        /* Subtle image scale on hover for extra dynamic feel */
        .tilt-image {
            transition: transform 0.8s cubic-bezier(0.23, 1, 0.32, 1);
            --img-scale: 1;
            transform: scale(var(--img-scale));
        }
        .group:hover .tilt-image {
            transform: scale(calc(var(--img-scale) + 0.05));
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased overflow-hidden selection:bg-emerald-100 selection:text-emerald-900 h-screen flex flex-col">

    <!-- Navbar (Reduced padding for 100vh fit) -->
    <nav class="shrink-0 z-50 bg-white/70 backdrop-blur-xl border-b border-emerald-100/50 py-3">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-md shadow-emerald-500/20 transition-transform hover:scale-105">
                    <i data-lucide="building" class="w-4 h-4 text-white"></i>
                </div>
                <span class="text-lg font-bold tracking-tight text-slate-900">Nibash</span>
            </a>
            <a href="index.php" class="text-sm font-medium text-slate-600 hover:text-emerald-600 transition-colors">Back to Home</a>
        </div>
    </nav>

    <?php
    $team_members = [
        [
            "name" => "Ahnaf Tajwar Suchak",
            "id" => "0112420437",
            "role" => "Member 1",
            "contribution" => "Backend Architecture, API Integration",
            "image" => "assets/team_images/suchak_img.jpeg",
            "img_style" => "object-position: center;"
        ],
        [
            "name" => "Md Labib Ahsan",
            "id" => "0112410176",
            "role" => "Member 2",
            "contribution" => "Database Management, Deployment",
            "image" => "assets/team_images/labib_img.jpeg",
            "img_style" => "object-position: center;"
        ],
        [
            "name" => "Md Rohan",
            "id" => "0112320296",
            "role" => "Member 3",
            "contribution" => "Frontend Development, System Testing",
            "image" => "assets/team_images/rohan_img.jpeg",
            "img_style" => "object-position: center;"
        ],
        [
            "name" => "Kashfia Shams Chowdhury Badhan",
            "id" => "0112330510",
            "role" => "Member 4",
            "contribution" => "UI/UX Design, Content Writing",
            "image" => "assets/team_images/badhan_img.jpeg",
            "img_style" => "object-position: 50% 90%; --img-scale: 1.35; transform-origin: bottom;"
        ]
    ];
    ?>

    <!-- Main Content (Flex-1 to take remaining space, reduced padding) -->
    <section class="flex-1 relative z-10 flex flex-col justify-center py-4 px-4">
        <div class="max-w-[95rem] mx-auto w-full">
            
            <div class="text-center mb-8 max-w-2xl mx-auto">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-semibold uppercase tracking-wider mb-2">
                    <i data-lucide="users" class="w-3.5 h-3.5"></i> Development Team
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-slate-900 tracking-tight mb-2">
                    Team Zero-NF
                </h1>
                <p class="text-sm md:text-base text-slate-600">
                    Meet the development team behind the Nibash platform.
                </p>
            </div>

            <!-- Grid Container with increased gap and width -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 lg:gap-10 max-w-[95rem] mx-auto">
                
                <?php foreach($team_members as $member): ?>
                
                <!-- Perspective is applied here to ensure symmetrical twisting for all cards -->
                <div class="perspective-container w-full">
                    <!-- Slightly increased height -->
                    <div class="group relative cursor-pointer h-[400px] lg:h-[450px] xl:h-[490px] w-full transform-3d">
                        
                        <div class="tilt-card absolute inset-0 rounded-2xl overflow-hidden bg-slate-900">
                            
                            <!-- Full Background Portrait Image -->
                            <img src="<?= $member['image'] ?>" alt="<?= $member['name'] ?>" class="tilt-image absolute inset-0 w-full h-full object-cover" style="<?= $member['img_style'] ?>">
                            
                            <!-- Left-to-Right Dark Gradient Overlay (Scrim) -->
                            <div class="gradient-scrim absolute inset-0 z-10"></div>

                            <!-- Info Text over the darker right side -->
                            <div class="absolute inset-y-0 right-0 w-[90%] p-5 flex flex-col justify-end text-right z-20">
                                
                                <!-- Role Badge -->
                                <div class="bg-emerald-500 text-white px-2.5 py-1 rounded text-[0.65rem] uppercase tracking-widest font-bold shadow-sm mb-2 w-max self-end border border-emerald-400">
                                    <?= $member['role'] ?>
                                </div>
                                
                                <!-- Name -->
                                <h3 class="text-[1.1rem] lg:text-xl xl:text-2xl font-bold text-white mb-1 drop-shadow-lg leading-tight tracking-tight">
                                    <?= $member['name'] ?>
                                </h3>
                                
                                <!-- ID -->
                                <div class="text-sm font-mono text-slate-300 mb-2 opacity-90 drop-shadow-md flex items-center justify-end">
                                    <?= $member['id'] ?>
                                </div>
                                
                                <!-- Contributions (No Title) -->
                                <p class="text-[0.85rem] text-slate-200 leading-snug drop-shadow-md border-r-2 border-emerald-500 pr-3">
                                    <?= $member['contribution'] ?>
                                </p>

                            </div>

                            <!-- Accent border element that glows on hover -->
                            <div class="absolute inset-0 rounded-2xl border-2 border-transparent group-hover:border-emerald-500/30 transition-colors duration-500 z-30 pointer-events-none"></div>

                        </div>
                    </div>
                </div>

                <?php endforeach; ?>

            </div>

        </div>
    </section>

    <!-- Footer (Shrink-0 to keep it at the bottom without overlapping) -->
    <footer class="shrink-0 bg-white border-t border-slate-200 py-4 relative z-10">
        <div class="max-w-[90rem] mx-auto px-4 sm:px-6 lg:px-8 text-center text-[0.8rem] text-slate-500">
            &copy; 2026 Nibash Systems. Developed by <span class="font-bold text-emerald-600">Team Zero-NF</span>.
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
