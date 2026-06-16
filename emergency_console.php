<?php
session_start();
require_once 'includes/db_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id']; // 1 = Owner, 2 = Resident

// Fetch user profile info
$user_name = $role_id == 1 ? "Owner" : "Resident";
$profile_id = 0;
try {
    $query = "SELECT id, full_name FROM user_profiles WHERE user_id = '$user_id'";
    $result = @mysqli_query($conn, $query);
    if($result && mysqli_num_rows($result) > 0) {
        $user_profile = mysqli_fetch_assoc($result);
        $user_name = $user_profile['full_name'];
        $profile_id = $user_profile['id'];
    }
} catch (Exception $e) {}

// Handle Adding SOS Contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sos'])) {
    $title = mysqli_real_escape_string($conn, trim($_POST['title']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone_number']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));

    $insert_query = "INSERT INTO emergency_contacts (user_profile_id, title, phone_number, email, contact_type) 
                     VALUES ('$profile_id', '$title', '$phone', '$email', 'Personal')";
    
    if (mysqli_query($conn, $insert_query)) {
        $_SESSION['success_msg'] = "SOS Contact added successfully!";
    } else {
        $_SESSION['error_msg'] = "Failed to add SOS Contact.";
    }
    header("Location: emergency_console.php");
    exit();
}

// Handle Deleting SOS Contact
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_sos'])) {
    $contact_id = intval($_POST['contact_id']);
    $delete_query = "DELETE FROM emergency_contacts WHERE id = '$contact_id' AND user_profile_id = '$profile_id'";
    
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['success_msg'] = "SOS Contact removed.";
    } else {
        $_SESSION['error_msg'] = "Failed to remove SOS Contact.";
    }
    header("Location: emergency_console.php");
    exit();
}

$active_page = 'emergency_console.php';
?>
<?php
$resident_building_name = 'Nibash';
try {
    $uid_for_b = $_SESSION['user_id'] ?? 0;
    if ($uid_for_b) {
        $bq = @mysqli_query($conn, "SELECT b.building_name, b.building_number FROM apartment_assignments aa JOIN apartments a ON aa.apt_id = a.id JOIN buildings b ON a.building_id = b.id WHERE aa.user_id = '$uid_for_b' AND aa.is_active=1 LIMIT 1");
        if ($bq && mysqli_num_rows($bq) > 0) {
            $brow = mysqli_fetch_assoc($bq);
            $resident_building_name = !empty($brow['building_name']) ? $brow['building_name'] : $brow['building_number'];
        } else {
            $mq = @mysqli_query($conn, "SELECT b.building_name, b.building_number FROM building_managers bm JOIN buildings b ON bm.building_id = b.id WHERE bm.user_id = '$uid_for_b' LIMIT 1");
            if ($mq && mysqli_num_rows($mq) > 0) {
                $mrow = mysqli_fetch_assoc($mq);
                $resident_building_name = !empty($mrow['building_name']) ? $mrow['building_name'] : $mrow['building_number'];
            }
        }
    }
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Console | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="<?php echo BASE_URL; ?>js/tailwind-config.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .hover-card { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .hover-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -8px rgba(225, 29, 72, 0.15); border-color: #fda4af; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #fff1f2; }
        ::-webkit-scrollbar-thumb { background: #fecdd3; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #f43f5e; }
        
        .cat-tab { transition: all 0.2s ease; }
        .cat-tab.active { background-color: #e11d48; color: white; border-color: #e11d48; box-shadow: 0 4px 10px -2px rgba(225, 29, 72, 0.4); }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-x-hidden" x-data="emergencyConsole">

    <?php 
    if ($role_id == 1) {
        include 'includes/owner_sidebar.php'; 
    } else {
        include 'includes/resident_sidebar.php'; 
    }
    ?>

    <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col min-h-screen p-4 sm:p-6 lg:p-8">
        
        <div class="flex justify-center pt-2 pb-5">
            <a href="<?php echo BASE_URL; ?>index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-emerald-100/60 hover:shadow-[0_4px_15px_-3px_rgba(16,185,129,0.15)] hover:border-emerald-200 transition-all">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-sm">
                    <i data-lucide="home" class="w-4 h-4 text-white"></i>
                </span>
                <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                    <?= htmlspecialchars($resident_building_name) ?>
                </span>
            </a>
        </div>

        <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(225,29,72,0.15)] flex-1 flex flex-col overflow-hidden border border-rose-100/80 relative">
            
            <header class="bg-white/80 backdrop-blur-xl border-b border-rose-50 sticky top-0 z-40 shadow-sm">
                <div class="px-8 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-rose-50 hover:text-rose-600 rounded-xl transition-colors">
                            <i data-lucide="menu" class="w-5 h-5"></i>
                        </button>
                        <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                            <span class="flex h-6 w-2 rounded-full bg-rose-500 shadow-[0_0_8px_rgba(225,29,72,0.6)] animate-pulse"></span>
                            <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">S.O.S Console</span>
                        </h2>
                    </div>
                </div>
            </header>

            <div class="p-8 sm:p-12 flex-1 overflow-y-auto max-w-[1600px] mx-auto w-full bg-slate-50/50 space-y-10">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end gap-6 pb-6 border-b border-slate-200">
                    <div class="space-y-3">
                        <h1 class="text-3xl md:text-4xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                            Emergency Console
                        </h1>
                        <p class="text-slate-500 font-medium flex items-center gap-2 text-sm sm:text-base">
                            <span class="p-1.5 bg-rose-100 border border-rose-200 rounded-lg"><i data-lucide="siren" class="w-4 h-4 text-rose-700"></i></span>
                            Manage your personal SOS contacts and locate nearby emergency services.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <div class="lg:col-span-1 h-fit" x-data="{ showSosForm: false }">
                        <button @click="showSosForm = !showSosForm" class="w-full bg-white rounded-[1.5rem] border border-slate-200 p-5 shadow-sm flex items-center justify-between group hover:border-rose-300 transition-colors focus:outline-none">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-rose-50 rounded-xl flex items-center justify-center text-rose-500 group-hover:scale-110 transition-transform shadow-sm">
                                    <i data-lucide="user-plus" class="w-5 h-5"></i>
                                </div>
                                <span class="text-base font-black text-slate-900">Add SOS Contact</span>
                            </div>
                            <div class="w-8 h-8 rounded-lg bg-slate-50 flex items-center justify-center group-hover:bg-rose-50 transition-colors">
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 group-hover:text-rose-500 transition-transform duration-300" :class="showSosForm ? 'rotate-180' : ''"></i>
                            </div>
                        </button>

                        <div x-show="showSosForm" x-transition.opacity.duration.300ms style="display: none;" class="bg-white rounded-[2rem] border border-slate-200 p-8 shadow-sm mt-4">
                            <h3 class="text-xl font-black text-slate-900 mb-2">New Contact</h3>
                            <p class="text-xs font-medium text-slate-500 mb-6">These contacts will receive immediate alerts when you trigger SOS from the dashboard.</p>
                            
                            <form method="POST" action="" class="space-y-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Name / Relation</label>
                                    <input type="text" name="title" required placeholder="e.g. Brother, Wife" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-rose-400 focus:ring-4 focus:ring-rose-500/10 outline-none transition-all text-sm font-medium">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Phone Number</label>
                                    <input type="tel" name="phone_number" required placeholder="01XXXXXXXXX" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-rose-400 focus:ring-4 focus:ring-rose-500/10 outline-none transition-all text-sm font-medium">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Email Address</label>
                                    <input type="email" name="email" required placeholder="contact@example.com" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-rose-400 focus:ring-4 focus:ring-rose-500/10 outline-none transition-all text-sm font-medium">
                                </div>
                                <button type="submit" name="add_sos" class="w-full py-3.5 mt-2 bg-slate-900 hover:bg-rose-600 text-white font-black text-sm rounded-xl transition-all shadow-md flex items-center justify-center gap-2 group">
                                    <i data-lucide="plus" class="w-4 h-4"></i> Save Contact
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-[2rem] border border-slate-200 p-8 shadow-sm min-h-full">
                            <h3 class="text-xl font-black text-slate-900 mb-6 flex items-center gap-2">
                                <i data-lucide="users" class="w-5 h-5 text-indigo-500"></i> My Active SOS Contacts
                            </h3>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <?php
                                $sos_q = mysqli_query($conn, "SELECT * FROM emergency_contacts WHERE user_profile_id = '$profile_id' AND contact_type = 'Personal'");
                                if(mysqli_num_rows($sos_q) > 0):
                                    while($contact = mysqli_fetch_assoc($sos_q)):
                                ?>
                                    <div class="p-5 border border-slate-200 rounded-[1.5rem] bg-slate-50/50 flex flex-col relative group">
                                        <div class="flex justify-between items-start mb-3">
                                            <h4 class="font-black text-slate-800 text-lg"><?= htmlspecialchars($contact['title']) ?></h4>
                                            <form method="POST" action="" onsubmit="return confirm('Remove this SOS contact?');">
                                                <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>">
                                                <button type="submit" name="delete_sos" class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-400 hover:text-rose-600 hover:border-rose-200 hover:bg-rose-50 transition-colors">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="space-y-2 mt-auto">
                                            <p class="text-sm font-medium text-slate-600 flex items-center gap-2">
                                                <i data-lucide="phone" class="w-4 h-4 text-emerald-500"></i> <?= htmlspecialchars($contact['phone_number']) ?>
                                            </p>
                                            <p class="text-sm font-medium text-slate-600 flex items-center gap-2">
                                                <i data-lucide="mail" class="w-4 h-4 text-sky-500"></i> <?= htmlspecialchars($contact['email']) ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                    <div class="col-span-full py-12 text-center bg-rose-50/30 rounded-[1.5rem] border-2 border-dashed border-rose-200">
                                        <i data-lucide="user-x" class="w-10 h-10 text-rose-300 mx-auto mb-3"></i>
                                        <h4 class="text-base font-black text-slate-900">No SOS Contacts Configured</h4>
                                        <p class="text-slate-500 font-medium text-sm mt-1">Add a contact to enable the dashboard SOS button.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4 py-4">
                    <div class="h-px bg-slate-200 flex-1"></div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Map Interface</span>
                    <div class="h-px bg-slate-200 flex-1"></div>
                </div>

                <div x-show="!locationGranted && !loading" class="bg-white p-10 rounded-[2rem] border-2 border-dashed border-rose-200 text-center shadow-sm max-w-2xl mx-auto">
                    <div class="w-20 h-20 bg-rose-50 rounded-full flex items-center justify-center mx-auto mb-6 text-rose-500 border border-rose-100 shadow-sm relative">
                        <span class="absolute inset-0 rounded-full bg-rose-400 animate-ping opacity-20"></span>
                        <i data-lucide="map-pin" class="w-10 h-10 relative z-10"></i>
                    </div>
                    <h3 class="text-2xl font-black text-slate-900 mb-3">Location Required</h3>
                    <p class="text-slate-500 font-medium mb-8 px-4">To find the nearest hospitals, police stations, fire departments, and ATMs, we need access to your device's current location.</p>
                    <button @click="requestLocation()" class="px-8 py-3.5 bg-rose-600 hover:bg-rose-700 text-white font-black text-sm rounded-xl transition-all shadow-md hover:shadow-lg hover:shadow-rose-500/40 flex items-center justify-center gap-3 mx-auto group">
                        <i data-lucide="navigation" class="w-5 h-5 group-hover:translate-x-1 transition-transform"></i> Find Nearby Services
                    </button>
                    <p x-show="locationError" class="text-rose-600 font-bold text-sm mt-4 bg-rose-50 py-2 px-4 rounded-lg inline-block" x-text="locationError"></p>
                </div>

                <div x-show="loading" class="flex flex-col items-center justify-center py-20">
                    <i data-lucide="loader-2" class="w-12 h-12 text-rose-500 animate-spin mb-4"></i>
                    <p class="text-slate-500 font-bold animate-pulse" x-text="loadingMsg">Scanning area for services...</p>
                </div>

                <div x-show="locationGranted && !loading" style="display: none;" class="space-y-8">
                    <div class="flex flex-wrap items-center gap-3 w-full">
                        <template x-for="cat in categories" :key="cat.id">
                            <button @click="setCategory(cat.id)" 
                                    :class="activeCategory === cat.id ? 'active' : 'bg-white text-slate-600 hover:bg-slate-50'"
                                    class="cat-tab px-6 py-3 rounded-xl border border-slate-200 text-xs font-black uppercase tracking-widest flex items-center gap-2.5">
                                <i :data-lucide="cat.icon" class="w-4 h-4"></i>
                                <span x-text="cat.name"></span>
                            </button>
                        </template>
                        <div class="flex-1 min-w-[200px] ml-auto">
                            <div class="relative">
                                <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                <input type="text" x-model="searchQuery" placeholder="Search facilities..." class="w-full pl-9 pr-4 py-3 bg-white border border-slate-200 rounded-xl focus:border-rose-400 focus:ring-4 focus:ring-rose-500/10 outline-none transition-all text-sm font-medium">
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-4 w-4/5 mx-auto">
                        <template x-for="place in filteredPlaces" :key="place.id">
                            <div @click="showDetails(place)" class="hover-card cursor-pointer bg-white rounded-[1.5rem] border border-slate-200 overflow-hidden relative group shadow-sm flex flex-col md:flex-row items-center p-5 gap-6">
                                
                                <div class="absolute left-0 top-0 w-full h-1 md:w-2 md:h-full" :class="getCategoryColor(place.category)"></div>
                                
                                <div class="flex-shrink-0 relative">
                                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center border shadow-sm" :class="getIconBgColor(place.category)">
                                        <i :data-lucide="getCategoryIcon(place.category)" class="w-8 h-8" :class="getIconTextColor(place.category)"></i>
                                    </div>
                                </div>
                                
                                <div class="flex-1 text-center md:text-left">
                                    <h3 class="text-xl font-black text-slate-900 mb-2 group-hover:text-emerald-600 transition-colors line-clamp-1" x-text="place.name"></h3>
                                    <p class="text-sm font-medium text-slate-500 line-clamp-1">
                                        <i data-lucide="map-pin" class="w-3.5 h-3.5 inline text-slate-400 mr-1"></i>
                                        <span x-text="place.address || 'Address not available in local registry'"></span>
                                    </p>
                                </div>
                                
                                <div class="flex flex-wrap md:flex-nowrap items-center justify-center md:justify-end gap-3 w-full md:w-auto mt-4 md:mt-0">
                                    <span class="text-[10px] font-black uppercase tracking-widest px-3 py-2.5 rounded-xl border bg-slate-50 border-slate-200 text-slate-500 flex items-center gap-1 w-full md:w-auto justify-center">
                                        <i data-lucide="map" class="w-3 h-3"></i> <span x-text="place.distance + ' km'"></span>
                                    </span>
                                    <a :href="place.phone ? 'tel:' + place.phone : '#'" 
                                       @click.stop="if(!place.phone) { alert('Phone number not publicly listed for this facility.'); return false; }"
                                       :class="place.phone ? 'bg-slate-900 text-white hover:bg-slate-800' : 'bg-slate-100 text-slate-400 cursor-not-allowed'"
                                       class="flex-1 md:flex-none px-4 py-3 font-bold text-xs rounded-xl transition-colors flex items-center justify-center gap-2 shadow-sm whitespace-nowrap">
                                        <i data-lucide="phone-call" class="w-4 h-4"></i> Call
                                    </a>
                                    
                                    <a :href="'https://www.google.com/maps/dir/?api=1&destination=' + place.lat + ',' + place.lon" target="_blank" @click.stop=""
                                       class="flex-1 md:flex-none px-4 py-3 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:border-emerald-200 border border-emerald-100 font-bold text-xs rounded-xl transition-colors flex items-center justify-center gap-2 shadow-sm whitespace-nowrap">
                                        <i data-lucide="navigation" class="w-4 h-4"></i> Navigate
                                    </a>
                                </div>
                            </div>
                        </template>

                        <div x-show="filteredPlaces.length === 0 && !loading" class="col-span-full py-16 text-center bg-white rounded-[1.5rem] border-2 border-dashed border-slate-200">
                            <i data-lucide="search-x" class="w-12 h-12 text-slate-300 mx-auto mb-3"></i>
                            <h4 class="text-lg font-black text-slate-900">No facilities found</h4>
                            <p class="text-slate-500 font-medium text-sm mt-1">Try expanding your search or selecting a different category.</p>
                        </div>
                    </div>
                </div>

                <div x-show="selectedPlace" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm p-4" style="display: none;" x-transition>
                    <div @click.outside="selectedPlace = null" class="bg-white rounded-[2rem] p-6 md:p-8 max-w-md w-full shadow-2xl relative border border-slate-100">
                        <button @click="selectedPlace = null" class="absolute top-5 right-5 text-slate-400 hover:text-rose-500 hover:bg-rose-50 p-2 rounded-xl transition-colors">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                        <div class="mb-5 pr-8">
                            <div class="flex items-center gap-3 mb-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center border shadow-sm" :class="getIconBgColor(selectedPlace?.category)">
                                    <i :data-lucide="getCategoryIcon(selectedPlace?.category)" class="w-5 h-5" :class="getIconTextColor(selectedPlace?.category)"></i>
                                </div>
                                <span class="text-[10px] font-black uppercase tracking-widest px-2.5 py-1.5 rounded-lg border bg-slate-50 border-slate-200 text-slate-500 flex items-center gap-1 object-cover">
                                    <i data-lucide="map" class="w-3 h-3"></i> <span x-text="selectedPlace?.distance + ' km'"></span>
                                </span>
                            </div>
                            <h3 class="text-2xl font-black text-slate-900 leading-tight" x-text="selectedPlace?.name"></h3>
                            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mt-2" x-text="selectedPlace?.category"></p>
                        </div>
                        <div class="space-y-4 mb-8 p-5 bg-slate-50 rounded-2xl border border-slate-100">
                            <p class="flex gap-3 items-start text-sm font-medium text-slate-600">
                                <span class="p-1.5 bg-white rounded-lg shadow-sm border border-slate-100"><i data-lucide="map-pin" class="w-4 h-4 text-slate-400"></i></span> 
                                <span class="mt-1 leading-relaxed" x-text="selectedPlace?.address || 'Address not available in local registry'"></span>
                            </p>
                            <p class="flex gap-3 items-center text-sm font-medium text-slate-600">
                                <span class="p-1.5 bg-white rounded-lg shadow-sm border border-slate-100"><i data-lucide="phone" class="w-4 h-4 text-slate-400"></i></span> 
                                <span class="mt-0.5" x-text="selectedPlace?.phone || 'Phone number not publicly listed'"></span>
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <a :href="selectedPlace?.phone ? 'tel:' + selectedPlace.phone : '#'" 
                               @click="if(!selectedPlace?.phone) { alert('Phone number not listed.'); return false; }"
                               :class="selectedPlace?.phone ? 'bg-slate-900 text-white hover:bg-slate-800' : 'bg-slate-100 text-slate-400 cursor-not-allowed'"
                               class="px-4 py-3.5 font-bold text-sm rounded-xl text-center shadow-sm flex justify-center items-center gap-2 transition-colors">
                               <i data-lucide="phone-call" class="w-4 h-4"></i> Call
                            </a>
                            <a :href="'https://www.google.com/maps/dir/?api=1&destination=' + selectedPlace?.lat + ',' + selectedPlace?.lon" target="_blank"
                               class="px-4 py-3.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-sm rounded-xl border border-emerald-100 text-center shadow-sm flex justify-center items-center gap-2 transition-colors">
                               <i data-lucide="navigation" class="w-4 h-4"></i> Navigate
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo BASE_URL; ?>js/toast.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('emergencyConsole', () => ({
                sidebarOpen: false, 
                desktopSidebarOpen: true,

                locationGranted: false,
                loading: false,
                loadingMsg: '',
                locationError: '',
                userLat: null,
                userLon: null,
                
                activeCategory: 'all', 
                searchQuery: '',
                allPlaces: [],
                selectedPlace: null,
                
                categories: [
                    { id: 'all', name: 'All Facilities', icon: 'layout-grid' },
                    { id: 'hospital', name: 'Hospitals', icon: 'cross' },
                    { id: 'police', name: 'Police Stations', icon: 'shield-alert' },
                    { id: 'fire', name: 'Fire Stations', icon: 'flame' },
                    { id: 'pharmacy', name: 'Pharmacies', icon: 'pill' },
                    { id: 'atm', name: 'ATMs', icon: 'credit-card' }
                ],

                init() {
                    localStorage.removeItem('nibash_emergency_data_v4');
                },

                showDetails(place) {
                    this.selectedPlace = place;
                    this.$nextTick(() => { lucide.createIcons(); });
                },

                get filteredPlaces() {
                    let filtered = this.allPlaces;
                    
                    if (this.activeCategory !== 'all') {
                        filtered = filtered.filter(p => p.category === this.activeCategory);
                    }
                    
                    if (this.searchQuery) {
                        const q = this.searchQuery.toLowerCase();
                        filtered = filtered.filter(p => 
                            p.name.toLowerCase().includes(q) || 
                            (p.address && p.address.toLowerCase().includes(q))
                        );
                    }
                    return filtered.sort((a, b) => a.distance - b.distance); 
                },

                setCategory(id) {
                    this.activeCategory = id;
                    this.$nextTick(() => { lucide.createIcons(); });
                },

                requestLocation() {
                    this.locationError = '';
                    if (!navigator.geolocation) {
                        this.locationError = "Geolocation is not supported by your browser.";
                        return;
                    }
                    
                    this.loading = true;
                    this.loadingMsg = 'Acquiring GPS coordinates...';
                    
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            this.userLat = position.coords.latitude;
                            this.userLon = position.coords.longitude;
                            this.locationGranted = true;
                            this.fetchFacilities();
                        },
                        (error) => {
                            this.loading = false;
                            this.locationError = "Please allow location access in your browser settings. GPS is required.";
                        },
                        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                    );
                },

                async fetchFacilities() {
                    this.loadingMsg = 'Scanning 5km radius for all services...';
                    
                    const query = `
                        [out:json][timeout:25];
                        (
                        node["amenity"="hospital"](around:5000,${this.userLat},${this.userLon});
                        way["amenity"="hospital"](around:5000,${this.userLat},${this.userLon});
                        node["amenity"="clinic"](around:5000,${this.userLat},${this.userLon});
                        way["amenity"="clinic"](around:5000,${this.userLat},${this.userLon});
                        node["amenity"="police"](around:5000,${this.userLat},${this.userLon});
                        way["amenity"="police"](around:5000,${this.userLat},${this.userLon});
                        node["amenity"="fire_station"](around:5000,${this.userLat},${this.userLon});
                        way["amenity"="fire_station"](around:5000,${this.userLat},${this.userLon});
                        node["amenity"="pharmacy"](around:5000,${this.userLat},${this.userLon});
                        node["amenity"="atm"](around:5000,${this.userLat},${this.userLon});
                        way["amenity"="atm"](around:5000,${this.userLat},${this.userLon});
                        );
                        out center;
                    `;

                    try {
                        const response = await fetch('https://overpass-api.de/api/interpreter', {
                            method: 'POST',
                            body: 'data=' + encodeURIComponent(query),
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                        });
                        
                        const data = await response.json();
                        console.log("[Nibash Debug] Total places found:", data.elements.length);
                        
                        this.allPlaces = data.elements
                            .filter(el => el.tags) 
                            .map(el => {
                                let cat = 'hospital';
                                if(el.tags.amenity === 'police') cat = 'police';
                                else if(el.tags.amenity === 'fire_station') cat = 'fire';
                                else if(el.tags.amenity === 'pharmacy') cat = 'pharmacy';
                                else if(el.tags.amenity === 'atm') cat = 'atm';

                                let fallbackName = 'Unnamed ' + (cat === 'hospital' ? 'Hospital/Clinic' : cat === 'police' ? 'Police Station' : cat === 'fire' ? 'Fire Station' : cat === 'pharmacy' ? 'Pharmacy' : 'ATM');

                                let addrStr = [];
                                if(el.tags['addr:street']) addrStr.push(el.tags['addr:street']);
                                if(el.tags['addr:city']) addrStr.push(el.tags['addr:city']);
                                
                                const placeLat = el.lat || (el.center ? el.center.lat : 0);
                                const placeLon = el.lon || (el.center ? el.center.lon : 0);

                                return {
                                    id: el.id,
                                    name: el.tags.name || fallbackName,
                                    category: cat,
                                    lat: placeLat,
                                    lon: placeLon,
                                    phone: el.tags.phone || el.tags['contact:phone'] || '',
                                    address: addrStr.length > 0 ? addrStr.join(', ') : 'Address not listed',
                                    distance: this.calculateDistance(this.userLat, this.userLon, placeLat, placeLon)
                                };
                            }).filter(p => p.lat !== 0);

                    } catch (err) {
                        console.error('API Error:', err);
                        this.locationError = "Failed to fetch nearby services. Please try again later.";
                        this.locationGranted = false;
                    } finally {
                        this.loading = false;
                        this.$nextTick(() => { lucide.createIcons(); });
                    }
                },

                calculateDistance(lat1, lon1, lat2, lon2) {
                    const R = 6371; 
                    const dLat = this.deg2rad(lat2 - lat1);
                    const dLon = this.deg2rad(lon2 - lon1); 
                    const a = 
                        Math.sin(dLat/2) * Math.sin(dLat/2) +
                        Math.cos(this.deg2rad(lat1)) * Math.cos(this.deg2rad(lat2)) * Math.sin(dLon/2) * Math.sin(dLon/2); 
                    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
                    return (R * c).toFixed(1); 
                },

                deg2rad(deg) { return deg * (Math.PI/180); },

                getCategoryColor(category) {
                    const colors = { 'all': 'bg-rose-500', 'hospital': 'bg-blue-400', 'police': 'bg-slate-800', 'fire': 'bg-orange-500', 'pharmacy': 'bg-emerald-400', 'atm': 'bg-teal-500' };
                    return colors[category] || 'bg-slate-400';
                },
                getIconBgColor(category) {
                    const colors = { 'all': 'bg-rose-50 border-rose-100', 'hospital': 'bg-blue-50 border-blue-100', 'police': 'bg-slate-100 border-slate-200', 'fire': 'bg-orange-50 border-orange-100', 'pharmacy': 'bg-emerald-50 border-emerald-100', 'atm': 'bg-teal-50 border-teal-100' };
                    return colors[category] || 'bg-slate-50 border-slate-100';
                },
                getIconTextColor(category) {
                    const colors = { 'all': 'text-rose-600', 'hospital': 'text-blue-600', 'police': 'text-slate-700', 'fire': 'text-orange-600', 'pharmacy': 'text-emerald-600', 'atm': 'text-teal-600' };
                    return colors[category] || 'text-slate-500';
                },
                getCategoryIcon(category) {
                    const icons = { 'all': 'layout-grid', 'hospital': 'cross', 'police': 'shield-alert', 'fire': 'flame', 'pharmacy': 'pill', 'atm': 'credit-card' };
                    return icons[category] || 'map-pin';
                }
            }));
        });
        
        document.addEventListener("DOMContentLoaded", function() {
            <?php if (isset($_SESSION['success_msg'])): ?>
                showCustomPopup("<?= addslashes($_SESSION['success_msg']) ?>", 'success');
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_msg'])): ?>
                showCustomPopup("<?= addslashes($_SESSION['error_msg']) ?>", 'error');
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>
        });

        lucide.createIcons();
    </script>

    <?php include 'chatbot/chat_widget.php'; ?>
</body>
</html>