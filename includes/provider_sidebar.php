<?php
$current_page = basename($_SERVER['PHP_SELF']);

$sidebar_user_name = "Provider";
$sidebar_user_image = "";
$is_subscribed = false;

if (isset($_SESSION['user_id']) && isset($conn)) {
    try {
        $sid = $_SESSION['user_id'];
        $s_query = "SELECT u.id, p.full_name, p.profile_image, sp.is_subscribed 
                    FROM users u 
                    LEFT JOIN user_profiles p ON u.id = p.user_id 
                    LEFT JOIN service_providers sp ON sp.user_id = u.id
                    WHERE u.id = '$sid' LIMIT 1";
        $s_result = @mysqli_query($conn, $s_query);
        if ($s_result && mysqli_num_rows($s_result) > 0) {
            $s_row = mysqli_fetch_assoc($s_result);
            $sidebar_user_name = $s_row['full_name'] ?? 'Provider';
            $sidebar_user_image = !empty($s_row['profile_image']) ? htmlspecialchars($s_row['profile_image']) : "";
            $is_subscribed = (bool)($s_row['is_subscribed'] ?? 0);
        }
    } catch (Exception $e) {
    }
}
?>
<div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 bg-slate-900/40 z-40 lg:hidden backdrop-blur-sm"
    @click="sidebarOpen = false" style="display: none;"></div>

<aside
    :class="{'translate-x-0 w-[240px]': sidebarOpen, '-translate-x-full lg:translate-x-0': !sidebarOpen, 'lg:w-[240px]': desktopSidebarOpen, 'lg:w-[88px]': !desktopSidebarOpen}"
    class="fixed top-4 bottom-4 left-4 z-50 bg-white/95 backdrop-blur-2xl border border-slate-200 rounded-[2rem] transition-all duration-300 ease-[cubic-bezier(0.4,0,0.2,1)] flex flex-col overflow-hidden shadow-[0_8px_30px_rgb(0,0,0,0.08)]">

    <div class="px-5 pt-5 pb-2 items-center flex"
        :class="desktopSidebarOpen || sidebarOpen ? 'justify-end' : 'justify-center'">
        <button @click="desktopSidebarOpen = !desktopSidebarOpen"
            class="hidden lg:flex w-8 h-8 items-center justify-center rounded-xl hover:bg-indigo-50 text-slate-400 hover:text-indigo-600 transition-colors shrink-0 border border-transparent hover:border-indigo-100">
            <i data-lucide="chevrons-left" x-show="desktopSidebarOpen" class="w-4 h-4"></i>
            <i data-lucide="chevrons-right" x-show="!desktopSidebarOpen" class="w-4 h-4" style="display: none;"></i>
        </button>
        <button x-show="sidebarOpen" @click="sidebarOpen = false"
            class="lg:hidden w-8 h-8 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-indigo-50 hover:text-indigo-600 transition-colors shrink-0">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>

    <div class="px-4 pb-4 flex flex-col items-center mb-2 group">
        <a href="<?php echo BASE_URL; ?>essentials/dashboard.php"
            class="relative group-hover:scale-105 transition-transform shrink-0 w-16 h-16"
            :class="desktopSidebarOpen || sidebarOpen ? 'w-16 h-16' : 'w-10 h-10'">
            <?php
            $provider_sidebar_image_web = "";
            if (!empty($sidebar_user_image) && $sidebar_user_image !== 'default_avatar.png') {
                $pi = $sidebar_user_image;
                if (strpos($pi, '/') === false) {
                    $pi = 'assets/uploads/profiles/' . $pi;
                }
                $osi_clean = ltrim($pi, '/');
                $osi_abs = $_SERVER['DOCUMENT_ROOT'] . '/Nibash/' . $osi_clean;
                if (file_exists($osi_abs)) {
                    $provider_sidebar_image_web = BASE_URL . $osi_clean;
                }
            }
            ?>
            <?php if (!empty($provider_sidebar_image_web)): ?>
                <img src="<?php echo $provider_sidebar_image_web; ?>"
                    class="w-full h-full rounded-full border-4 border-white shadow-sm ring-1 ring-indigo-50 group-hover:ring-indigo-200 transition-all object-cover bg-white">
            <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($sidebar_user_name); ?>&background=eef2ff&color=4f46e5&rounded=true&bold=true"
                    class="w-full h-full rounded-full border-4 border-white shadow-sm ring-1 ring-indigo-50 group-hover:ring-indigo-200 transition-all object-cover bg-white">
            <?php endif; ?>
            <span
                class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-indigo-500 border-[3px] border-white rounded-full shadow-sm"
                title="Status: Online"></span>
        </a>
        <div x-show="desktopSidebarOpen || sidebarOpen" class="mt-3 text-center flex flex-col items-center">
            <a href="<?php echo BASE_URL; ?>essentials/dashboard.php"
                class="text-[14px] font-black text-slate-900 hover:text-indigo-600 transition-colors line-clamp-1 pb-0.5 tracking-tight"><?php echo htmlspecialchars($sidebar_user_name); ?></a>
            <?php if ($is_subscribed): ?>
                <span class="text-[9px] text-amber-600 bg-amber-50 px-2 py-0.5 rounded uppercase tracking-widest font-extrabold border border-amber-200 mt-1 shadow-sm flex items-center gap-1">
                    <i data-lucide="crown" class="w-3 h-3 fill-amber-500"></i> PRO Provider
                </span>
            <?php else: ?>
                <span class="text-[9px] text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded uppercase tracking-widest font-extrabold border border-indigo-100/50 mt-1">Verified Provider</span>
            <?php endif; ?>
        </div>
    </div>

    <nav class="flex-1 px-4 py-2 space-y-1.5 overflow-y-auto scrollbar-hide w-full flex flex-col items-center">

        <div class="w-full" x-show="desktopSidebarOpen || sidebarOpen">
            <p class="px-3 text-[10px] font-extrabold uppercase tracking-widest text-slate-400 mb-2 mt-4">Hub</p>
        </div>
        <?php
        $nav_hub = [
            ['url' => BASE_URL . 'essentials/dashboard.php', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
            ['url' => '#schedule', 'icon' => 'calendar-days', 'label' => 'Schedule'],
            ['url' => '#earnings', 'icon' => 'wallet', 'label' => 'Earnings'],
            ['url' => '#reviews', 'icon' => 'star', 'label' => 'Reviews'],
        ];

        foreach ($nav_hub as $item) {
            $is_active = false;
            // Handle anchors and direct paths
            if (strpos($item['url'], '#') === 0) {
                // Not active unless clicked, let's keep it simple
                $is_active = false; 
            } else {
                $item_path = parse_url($item['url'], PHP_URL_PATH);
                $is_active = ($_SERVER['PHP_SELF'] == $item_path);
            }
            
            $active_classes = $is_active
                ? 'bg-indigo-50 text-indigo-700 font-black shadow-sm border border-indigo-100 relative'
                : 'text-slate-500 font-bold hover:bg-slate-50 hover:text-indigo-600 border border-transparent';
            $icon_active = $is_active ? 'text-indigo-600 drop-shadow-sm' : 'text-slate-400 group-hover:text-indigo-500';

            echo '<a href="' . $item['url'] . '" class="flex items-center rounded-xl transition-all duration-300 group ' . $active_classes . '" :class="desktopSidebarOpen || sidebarOpen ? \'w-full h-11 px-3 gap-3\' : \'w-12 h-12 justify-center px-0\'" title="' . $item['label'] . '">';
            if ($is_active) {
                echo '<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1.5 h-5 bg-indigo-500 rounded-r-full shadow-[0_0_8px_rgba(99,102,241,0.8)]"></span>';
            }
            echo '<i data-lucide="' . $item['icon'] . '" class="w-4 h-4 shrink-0 transition-colors ' . $icon_active . '"></i>';
            echo '<span x-show="desktopSidebarOpen || sidebarOpen" class="whitespace-nowrap z-10 text-xs">' . $item['label'] . '</span>';
            echo '</a>';
        }
        ?>

        <div class="w-full shrink-0" x-show="desktopSidebarOpen || sidebarOpen">
            <p class="px-3 text-[10px] font-extrabold uppercase tracking-widest text-slate-400 mb-2 mt-5">Messages</p>
        </div>
        <div class="w-full flex justify-center py-2 shrink-0" x-show="!desktopSidebarOpen && !sidebarOpen">
            <div class="w-6 h-px bg-slate-100 shrink-0"></div>
        </div>

        <a href="<?= BASE_URL ?>messages/index.php"
            class="flex items-center rounded-xl transition-all duration-300 group <?php echo (strpos($_SERVER['PHP_SELF'], '/messages/') !== false) ? 'bg-indigo-50 text-indigo-700 font-black shadow-sm border border-indigo-100 relative' : 'text-slate-500 font-bold hover:bg-slate-50 hover:text-indigo-600 border border-transparent'; ?>"
            :class="desktopSidebarOpen || sidebarOpen ? 'w-full h-11 px-3 gap-3' : 'w-12 h-12 justify-center px-0'"
            title="Inbox">
            <?php if (strpos($_SERVER['PHP_SELF'], '/messages/') !== false): ?>
                <span class="absolute left-0 top-1/2 -translate-y-1/2 w-1.5 h-5 bg-indigo-500 rounded-r-full shadow-[0_0_8px_rgba(99,102,241,0.8)]"></span>
            <?php endif; ?>
            <i data-lucide="message-square"
                class="w-4 h-4 shrink-0 transition-colors <?php echo (strpos($_SERVER['PHP_SELF'], '/messages/') !== false) ? 'text-indigo-600 drop-shadow-sm' : 'text-slate-400 group-hover:text-indigo-500'; ?>"></i>
            <span x-show="desktopSidebarOpen || sidebarOpen" class="whitespace-nowrap z-10 text-xs">Inbox</span>
        </a>

        <?php if (!$is_subscribed): ?>
        <!-- Upsell Banner -->
        <div x-show="desktopSidebarOpen || sidebarOpen" class="mt-8 mb-4 mx-2">
            <div class="bg-gradient-to-br from-indigo-50 to-purple-50 rounded-2xl p-4 border border-indigo-100/50 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 w-16 h-16 bg-gradient-to-br from-amber-200 to-amber-400 rounded-full opacity-20 group-hover:scale-150 transition-transform duration-500"></div>
                <h4 class="text-xs font-black text-indigo-900 mb-1 flex items-center gap-1.5 relative z-10"><i data-lucide="crown" class="w-3.5 h-3.5 text-amber-500"></i> Get Pro</h4>
                <p class="text-[10px] text-indigo-700/80 mb-3 leading-tight font-medium relative z-10">Stand out at the top of the provider directory!</p>
                <a href="<?=BASE_URL?>essentials/subscribe.php" class="block text-center w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-black uppercase tracking-wider rounded-xl shadow-md transition-colors relative z-10">Subscribe Now</a>
            </div>
        </div>
        <div class="w-full flex justify-center py-4 shrink-0" x-show="!desktopSidebarOpen && !sidebarOpen">
            <a href="<?=BASE_URL?>essentials/subscribe.php" class="relative z-10 w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center border border-amber-200 hover:bg-amber-100 transition-colors" title="Get Pro">
                <i data-lucide="crown" class="w-4 h-4 text-amber-500"></i>
            </a>
        </div>
        <?php else: ?>
        <!-- Pro Active Banner -->
        <div x-show="desktopSidebarOpen || sidebarOpen" class="mt-8 mb-4 mx-2">
            <div class="bg-gradient-to-br from-amber-50 to-yellow-50 rounded-2xl p-4 border border-amber-200 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 w-16 h-16 bg-gradient-to-br from-amber-200 to-yellow-400 rounded-full opacity-40 group-hover:scale-150 transition-transform duration-500"></div>
                <h4 class="text-xs font-black text-amber-900 mb-1 flex items-center gap-1.5 relative z-10"><i data-lucide="check-circle" class="w-3.5 h-3.5 text-amber-600"></i> Pro Active</h4>
                <p class="text-[10px] text-amber-700/80 mb-3 leading-tight font-medium relative z-10">Your profile is highlighted at the top.</p>
                <a href="<?=BASE_URL?>essentials/subscribe.php" class="block text-center w-full py-1.5 bg-white/60 hover:bg-white text-amber-700 border border-amber-200 text-[10px] font-black uppercase tracking-wider rounded-xl transition-colors relative z-10">Manage Pro</a>
            </div>
        </div>
        <?php endif; ?>
    </nav>

    <div class="p-4 mt-auto w-full flex flex-col items-center shrink-0 border-t border-indigo-50 bg-slate-50/50">
        <a href="<?php echo BASE_URL; ?>logout.php"
            class="flex items-center rounded-xl text-slate-500 font-bold hover:bg-rose-50 hover:text-rose-600 transition-all duration-300 group border border-transparent hover:border-rose-100"
            :class="desktopSidebarOpen || sidebarOpen ? 'w-full h-11 px-3 gap-3' : 'w-12 h-12 justify-center px-0'"
            title="Sign Out">
            <i data-lucide="log-out"
                class="w-4 h-4 shrink-0 transition-colors text-slate-400 group-hover:text-rose-500"></i>
            <span x-show="desktopSidebarOpen || sidebarOpen" class="whitespace-nowrap text-xs">Sign Out</span>
        </a>
    </div>
</aside>

<div class="hidden w-[240px] lg:w-[240px] lg:w-[88px] lg:ml-[240px] lg:ml-[88px] w-full h-11 px-3 gap-3 w-12 h-12 justify-center px-0 translate-x-0 -translate-x-full lg:translate-x-0"></div>
<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>
