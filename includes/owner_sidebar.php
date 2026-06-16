<?php
$current_page = basename($_SERVER['PHP_SELF']);

$sidebar_user_name = "Owner";
$sidebar_user_image = "";
if (isset($_SESSION['user_id']) && isset($conn)) {
    try {
        $sid = $_SESSION['user_id'];
        $s_query = "SELECT p.full_name, p.profile_image FROM users u LEFT JOIN user_profiles p ON u.id = p.user_id WHERE u.id = '$sid'";
        $s_result = @mysqli_query($conn, $s_query);
        if ($s_result && mysqli_num_rows($s_result) > 0) {
            $s_row = mysqli_fetch_assoc($s_result);
            $sidebar_user_name = $s_row['full_name'] ?? 'Owner';
            $sidebar_user_image = !empty($s_row['profile_image']) ? htmlspecialchars($s_row['profile_image']) : "";
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
            class="hidden lg:flex w-8 h-8 items-center justify-center rounded-xl hover:bg-emerald-50 text-slate-400 hover:text-emerald-600 transition-colors shrink-0 border border-transparent hover:border-emerald-100">
            <i data-lucide="chevrons-left" x-show="desktopSidebarOpen" class="w-4 h-4"></i>
            <i data-lucide="chevrons-right" x-show="!desktopSidebarOpen" class="w-4 h-4" style="display: none;"></i>
        </button>
        <button x-show="sidebarOpen" @click="sidebarOpen = false"
            class="lg:hidden w-8 h-8 flex items-center justify-center rounded-xl bg-slate-50 text-slate-400 hover:bg-emerald-50 hover:text-emerald-600 transition-colors shrink-0">
            <i data-lucide="x" class="w-4 h-4"></i>
        </button>
    </div>

    <div class="px-4 pb-4 flex flex-col items-center mb-2 group">
        <a href="<?php echo BASE_URL; ?>owner/profile.php"
            class="relative group-hover:scale-105 transition-transform shrink-0 w-16 h-16"
            :class="desktopSidebarOpen || sidebarOpen ? 'w-16 h-16' : 'w-10 h-10'">
            <?php
            $owner_sidebar_image_web = '';
            if (!empty($sidebar_user_image)) {
                $osi_clean = ltrim($sidebar_user_image, '/');
                if (strpos($osi_clean, 'assets/uploads/profiles/') !== 0) {
                    $osi_clean = 'assets/uploads/profiles/' . $osi_clean;
                }
                $osi_abs = $_SERVER['DOCUMENT_ROOT'] . '/Nibash/' . $osi_clean;
                if (file_exists($osi_abs) || file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $osi_clean)) {
                    $owner_sidebar_image_web = BASE_URL . $osi_clean;
                }
            }
            ?>
            <?php if (!empty($owner_sidebar_image_web)): ?>
                <img src="<?php echo $owner_sidebar_image_web; ?>"
                    class="w-full h-full rounded-[1.25rem] border-4 border-white shadow-sm ring-1 ring-emerald-50 group-hover:ring-emerald-200 transition-all object-cover bg-white">
            <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($sidebar_user_name); ?>&background=ecfdf5&color=047857&rounded=true&bold=true"
                    class="w-full h-full rounded-[1.25rem] border-4 border-white shadow-sm ring-1 ring-emerald-50 group-hover:ring-emerald-200 transition-all object-cover bg-white">
            <?php endif; ?>
            <span
                class="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 bg-emerald-500 border-[3px] border-white rounded-full shadow-sm"
                title="Status: Online"></span>
        </a>
        <div x-show="desktopSidebarOpen || sidebarOpen" class="mt-3 text-center flex flex-col items-center">
            <a href="<?php echo BASE_URL; ?>owner/profile.php"
                class="text-[14px] font-black text-slate-900 hover:text-emerald-600 transition-colors line-clamp-1 pb-0.5 tracking-tight"><?php echo htmlspecialchars($sidebar_user_name); ?></a>
            <span class="text-[9px] text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded uppercase tracking-widest font-extrabold border border-emerald-100/50 mt-1">Property Owner</span>
        </div>
    </div>

    <nav class="flex-1 px-4 py-2 space-y-1.5 overflow-y-auto scrollbar-hide w-full flex flex-col items-center">

        <div class="w-full" x-show="desktopSidebarOpen || sidebarOpen">
            <p class="px-3 text-[10px] font-extrabold uppercase tracking-widest text-slate-400 mb-2 mt-4">Main Menu</p>
        </div>
        <?php
        $nav_management = [
            ['url' => BASE_URL . 'owner/dashboard.php', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
            ['url' => BASE_URL . 'owner/residents.php', 'icon' => 'users', 'label' => 'Residents'],
            ['url' => BASE_URL . 'rentals/manage.php', 'icon' => 'building', 'label' => 'Rental Post'],
            ['url' => BASE_URL . 'parking/index.php', 'icon' => 'car', 'label' => 'Parking', 'aliases' => ['parking/index.php']],
        ];

        foreach ($nav_management as $item) {
            $item_path = parse_url($item['url'], PHP_URL_PATH);
            $is_active = ($_SERVER['PHP_SELF'] == $item_path);
            if (!$is_active && isset($item['aliases'])) {
                foreach ($item['aliases'] as $alias) {
                    if (strpos($_SERVER['PHP_SELF'], $alias) !== false) {
                        $is_active = true;
                        break;
                    }
                }
            }
            $active_classes = $is_active
                ? 'bg-emerald-50 text-emerald-700 font-black shadow-sm border border-emerald-100 relative'
                : 'text-slate-500 font-bold hover:bg-slate-50 hover:text-emerald-600 border border-transparent';
            $icon_active = $is_active ? 'text-emerald-600 drop-shadow-sm' : 'text-slate-400 group-hover:text-emerald-500';

            echo '<a href="' . $item['url'] . '" class="flex items-center rounded-xl transition-all duration-300 group ' . $active_classes . '" :class="desktopSidebarOpen || sidebarOpen ? \'w-full h-11 px-3 gap-3\' : \'w-12 h-12 justify-center px-0\'" title="' . $item['label'] . '">';
            if ($is_active) {
                echo '<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1.5 h-5 bg-emerald-500 rounded-r-full shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>';
            }
            echo '<i data-lucide="' . $item['icon'] . '" class="w-4 h-4 shrink-0 transition-colors ' . $icon_active . '"></i>';
            echo '<span x-show="desktopSidebarOpen || sidebarOpen" class="whitespace-nowrap z-10 text-xs">' . $item['label'] . '</span>';
            echo '</a>';
        }
        ?>

        <div class="w-full shrink-0" x-show="desktopSidebarOpen || sidebarOpen">
            <p class="px-3 text-[10px] font-extrabold uppercase tracking-widest text-slate-400 mb-2 mt-5">Daily Activity</p>
        </div>
        <div class="w-full flex justify-center py-2 shrink-0" x-show="!desktopSidebarOpen && !sidebarOpen">
            <div class="w-6 h-px bg-slate-100 shrink-0"></div>
        </div>

        <?php
        $nav_operations = [
            ['url' => BASE_URL . 'owner/billing.php', 'icon' => 'receipt', 'label' => 'Bills & Payments'],
            ['url' => BASE_URL . 'owner/guest_entries.php', 'icon' => 'clipboard-check', 'label' => 'Entries'],
            ['url' => BASE_URL . 'owner/cctv_surveillance.php', 'icon' => 'video', 'label' => 'Cameras'],
        ];

        foreach ($nav_operations as $item) {
            $item_path = parse_url($item['url'], PHP_URL_PATH);
            $is_active = ($_SERVER['PHP_SELF'] == $item_path);
            $active_classes = $is_active
                ? 'bg-emerald-50 text-emerald-700 font-black shadow-sm border border-emerald-100 relative'
                : 'text-slate-500 font-bold hover:bg-slate-50 hover:text-emerald-600 border border-transparent';
            $icon_active = $is_active ? 'text-emerald-600 drop-shadow-sm' : 'text-slate-400 group-hover:text-emerald-500';

            echo '<a href="' . $item['url'] . '" class="flex items-center rounded-xl transition-all duration-300 group ' . $active_classes . '" :class="desktopSidebarOpen || sidebarOpen ? \'w-full h-11 px-3 gap-3\' : \'w-12 h-12 justify-center px-0\'" title="' . $item['label'] . '">';
            if ($is_active) {
                echo '<span class="absolute left-0 top-1/2 -translate-y-1/2 w-1.5 h-5 bg-emerald-500 rounded-r-full shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>';
            }
            echo '<i data-lucide="' . $item['icon'] . '" class="w-4 h-4 shrink-0 transition-colors ' . $icon_active . '"></i>';
            echo '<span x-show="desktopSidebarOpen || sidebarOpen" class="whitespace-nowrap z-10 text-xs">' . $item['label'] . '</span>';
            echo '</a>';
        }
        ?>

        <div class="w-full shrink-0" x-show="desktopSidebarOpen || sidebarOpen">
            <p class="px-3 text-[10px] font-extrabold uppercase tracking-widest text-slate-400 mb-2 mt-5">Community & Help</p>
        </div>
        <div class="w-full flex justify-center py-2 shrink-0" x-show="!desktopSidebarOpen && !sidebarOpen">
            <div class="w-6 h-px bg-slate-100 shrink-0"></div>
        </div>

        <a href="<?= BASE_URL ?>community_hub.php"
            class="flex items-center rounded-xl transition-all duration-300 group <?php echo ($current_page == 'community_hub.php') ? 'bg-emerald-50 text-emerald-700 font-black shadow-sm border border-emerald-100 relative' : 'text-slate-500 font-bold hover:bg-slate-50 hover:text-emerald-600 border border-transparent'; ?>"
            :class="desktopSidebarOpen || sidebarOpen ? 'w-full h-11 px-3 gap-3' : 'w-12 h-12 justify-center px-0'"
            title="Notice Board">
            <?php if ($current_page == 'community_hub.php'): ?>
                <span class="absolute left-0 top-1/2 -translate-y-1/2 w-1.5 h-5 bg-emerald-500 rounded-r-full shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>
            <?php endif; ?>
            <i data-lucide="clipboard-list"
                class="w-4 h-4 shrink-0 transition-colors <?php echo ($current_page == 'community_hub.php') ? 'text-emerald-600 drop-shadow-sm' : 'text-slate-400 group-hover:text-emerald-500'; ?>"></i>
            <span x-show="desktopSidebarOpen || sidebarOpen"
                class="whitespace-nowrap z-10 text-xs">Notice Board</span>
        </a>

        <a href="<?= BASE_URL ?>essentials/index.php"
            class="flex items-center rounded-xl transition-all duration-300 group <?php echo (strpos($_SERVER['PHP_SELF'], '/essentials/') !== false) ? 'bg-emerald-50 text-emerald-700 font-black shadow-sm border border-emerald-100 relative' : 'text-slate-500 font-bold hover:bg-slate-50 hover:text-emerald-600 border border-transparent'; ?>"
            :class="desktopSidebarOpen || sidebarOpen ? 'w-full h-11 px-3 gap-3' : 'w-12 h-12 justify-center px-0'"
            title="Local Services">
            <?php if (strpos($_SERVER['PHP_SELF'], '/essentials/') !== false): ?>
                <span class="absolute left-0 top-1/2 -translate-y-1/2 w-1.5 h-5 bg-emerald-500 rounded-r-full shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>
            <?php endif; ?>
            <i data-lucide="briefcase"
                class="w-4 h-4 shrink-0 transition-colors <?php echo (strpos($_SERVER['PHP_SELF'], '/essentials/') !== false) ? 'text-emerald-600 drop-shadow-sm' : 'text-slate-400 group-hover:text-emerald-500'; ?>"></i>
            <span x-show="desktopSidebarOpen || sidebarOpen"
                class="whitespace-nowrap z-10 text-xs">Local Services</span>
        </a>

        <div class="w-full flex justify-center py-2 shrink-0" x-show="!desktopSidebarOpen && !sidebarOpen">
            <div class="w-6 h-px bg-slate-100 shrink-0"></div>
        </div>

        <a href="<?php echo BASE_URL; ?>emergency_console.php" onclick="showCustomPopup('Emergency Console Connecting...', 'error')"
            class="flex items-center rounded-xl transition-all duration-300 group text-slate-500 font-bold hover:bg-rose-50 hover:text-rose-600 border border-transparent mt-2"
            :class="desktopSidebarOpen || sidebarOpen ? 'w-full h-11 px-3 gap-3' : 'w-12 h-12 justify-center px-0'"
            title="Emergency">
            <i data-lucide="siren"
                class="w-4 h-4 shrink-0 transition-colors text-slate-400 group-hover:text-rose-500 group-hover:animate-pulse"></i>
            <span x-show="desktopSidebarOpen || sidebarOpen"
                class="whitespace-nowrap z-10 text-xs">Nearby Places</span>
        </a>

    </nav>

    <div class="p-4 mt-auto w-full flex flex-col items-center shrink-0 border-t border-emerald-50 bg-slate-50/50">
        
        <a href="<?php echo BASE_URL; ?>logout.php"
            class="flex items-center rounded-xl text-slate-500 font-bold hover:bg-rose-50 hover:text-rose-600 transition-all duration-300 group border border-transparent hover:border-rose-100"
            :class="desktopSidebarOpen || sidebarOpen ? 'w-full h-11 px-3 gap-3' : 'w-12 h-12 justify-center px-0'"
            title="Sign Out">
            <i data-lucide="log-out"
                class="w-4 h-4 shrink-0 transition-colors text-slate-400 group-hover:text-rose-500"></i>
            <span x-show="desktopSidebarOpen || sidebarOpen" class="whitespace-nowrap text-xs">Sign Out</span>
        </a>

        <div x-show="desktopSidebarOpen || sidebarOpen" class="mt-4 pt-3 border-t border-slate-200/60 w-full flex flex-col items-center justify-center">
            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 mb-1">Powered By</span>
            <div class="flex items-center gap-1.5 opacity-80 hover:opacity-100 transition-opacity">
                <span class="w-5 h-5 rounded-md bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-3 h-3 text-white"></i>
                </span>
                <span class="text-sm font-black tracking-tight text-slate-700" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    Ni<span class="text-emerald-600">bash</span>
                </span>
            </div>
        </div>
    </div>
</aside>

<div
    class="hidden w-[240px] lg:w-[240px] lg:w-[88px] lg:ml-[240px] lg:ml-[88px] w-full h-11 px-3 gap-3 w-12 h-12 justify-center px-0 translate-x-0 -translate-x-full lg:translate-x-0">
</div>

<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>