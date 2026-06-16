<?php
session_start();
require_once '../includes/db_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php?error=unauthorized");
    exit();
}

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];

$target_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 'null';

$cur_u_query = mysqli_query($conn, "SELECT full_name, profile_image FROM user_profiles WHERE user_id = '$user_id'");
$cur_user = mysqli_fetch_assoc($cur_u_query);
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
    <title>Inbox | Nibash</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        .bg-chat-pattern {
            background-color: #f8fafc;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2310b981' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .message-bubble { position: relative; max-width: 75%; padding: 10px 16px; border-radius: 16px; margin-bottom: 8px; font-size: 0.925rem; font-weight: 500; line-height: 1.4; word-break: break-word; }
        .message-in { background: white; border: 1px solid #e2e8f0; color: #1e293b; border-bottom-left-radius: 4px; }
        .message-out { background: #10b981; color: white; border-bottom-right-radius: 4px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); }
        .message-time { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 4px; display: block; }
        .message-in .message-time { color: #94a3b8; }
        .message-out .message-time { color: rgba(255, 255, 255, 0.8); text-align: right; }
    </style>
</head>
<body class="bg-[#f2fbf6] min-h-screen text-slate-800 font-sans antialiased overflow-hidden" x-data="chatApp()" x-init="initApp(<?= $user_id ?>, <?= $target_user_id ?>)">

    <div x-data="{ sidebarOpen: false, desktopSidebarOpen: true }">
        
        <?php 
        if ($role_id == 1) {
            include '../includes/owner_sidebar.php'; 
        } else if ($role_id == 2 || $role_id == 3) {
            include '../includes/resident_sidebar.php'; 
        }
        ?>

        <main :class="desktopSidebarOpen ? 'lg:ml-[240px]' : 'lg:ml-[88px]'" class="transition-all duration-300 flex flex-col h-screen p-4 sm:p-6 lg:p-8">
            
            <div class="flex justify-center pt-2 pb-5 shrink-0">
                <a href="../index.php" class="group flex items-center gap-2.5 no-underline bg-white px-5 py-2 rounded-2xl shadow-[0_2px_10px_-2px_rgba(0,0,0,0.05)] border border-emerald-100/60 hover:shadow-[0_4px_15px_-3px_rgba(16,185,129,0.15)] hover:border-emerald-200 transition-all">
                    <span class="w-8 h-8 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center shadow-sm">
                        <i data-lucide="home" class="w-4 h-4 text-white"></i>
                    </span>
                    <span class="text-xl font-black tracking-tight text-slate-800" style="font-family: 'Inter', sans-serif; letter-spacing: -0.04em;">
                        <?= htmlspecialchars($resident_building_name) ?>
                    </span>
                </a>
            </div>

            <div class="bg-white rounded-[32px] shadow-[0_12px_40px_-12px_rgba(16,185,129,0.15)] flex-1 flex flex-col overflow-hidden border border-emerald-100/80 relative">
                
                <header class="bg-white/80 backdrop-blur-xl border-b border-emerald-50 sticky top-0 z-40 shadow-sm shrink-0">
                    <div class="px-8 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <button @click="sidebarOpen = true" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 rounded-xl transition-colors">
                                <i data-lucide="menu" class="w-5 h-5"></i>
                            </button>
                            <h2 class="text-lg font-extrabold text-slate-800 flex items-center gap-3">
                                <span class="flex h-6 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.6)]"></span>
                                <span class="tracking-tight uppercase text-xs text-slate-500 font-bold tracking-widest">Inbox</span>
                            </h2>
                        </div>
                        
                        <?php if($role_id == 4): ?>
                        <div class="flex items-center">
                            <a href="../essentials/dashboard.php" class="flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-black text-xs uppercase tracking-widest rounded-xl transition-colors border border-slate-300 shadow-sm">
                                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </header>

                <div class="flex-1 flex overflow-hidden bg-white relative">
                    
                    <!-- Left Sidebar (Conversations) -->
                    <div class="w-full md:w-[360px] lg:w-[400px] shrink-0 h-full flex flex-col border-r border-slate-200 bg-white absolute md:relative z-20 transition-transform duration-300"
                         :class="{ '-translate-x-full md:translate-x-0': activeChat }">
                        
                        <!-- Header -->
                        <div class="h-[72px] shrink-0 px-5 flex items-center justify-between border-b border-slate-100 bg-white">
                            <h1 class="text-xl font-black text-slate-800 tracking-tight">Messages</h1>
                            <div class="flex items-center gap-3">
                                <button @click="showSearch = !showSearch; if(showSearch) $nextTick(() => $refs.searchInput.focus())" class="w-10 h-10 rounded-full flex items-center justify-center bg-slate-50 text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 transition-colors">
                                    <i data-lucide="search" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Search Dropdown -->
                        <div x-show="showSearch" x-collapse class="bg-slate-50 p-4 border-b border-slate-200">
                            <div class="relative">
                                <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                <input type="text" x-ref="searchInput" x-model="searchQuery" @input.debounce.500ms="searchUsers()" placeholder="Search by email, phone, or name..." class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm font-bold outline-none focus:border-emerald-400 focus:ring-4 focus:ring-emerald-500/10 transition-all shadow-sm">
                            </div>
                            
                            <div class="mt-3 space-y-1 max-h-[300px] overflow-y-auto" x-show="searchResults.length > 0">
                                <template x-for="user in searchResults" :key="user.id">
                                    <button @click="startChat(user); showSearch = false; searchQuery = ''" class="w-full flex items-center gap-3 p-2 rounded-xl hover:bg-emerald-50 transition-colors text-left group">
                                        <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 font-bold flex items-center justify-center shrink-0 overflow-hidden">
                                            <template x-if="user.profile_image && user.profile_image !== 'default_avatar.png' && user.profile_image !== 'default_avatar.jpg'">
                                                <img :src="'../assets/uploads/' + (user.profile_image.includes('/') ? user.profile_image : 'profiles/' + user.profile_image)" class="w-full h-full object-cover">
                                            </template>
                                            <template x-if="!user.profile_image || user.profile_image === 'default_avatar.png' || user.profile_image === 'default_avatar.jpg'">
                                                <span x-text="user.full_name.charAt(0).toUpperCase()"></span>
                                            </template>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="text-sm font-bold text-slate-800 truncate group-hover:text-emerald-700" x-text="user.full_name"></h4>
                                            <p class="text-xs font-medium text-slate-500 truncate" x-text="user.email || user.phone"></p>
                                        </div>
                                    </button>
                                </template>
                            </div>
                            <div x-show="searchQuery.length >= 3 && searchResults.length === 0" class="mt-4 text-center text-xs font-bold text-slate-400 p-2">
                                No users found.
                            </div>
                        </div>

                        <!-- Conversation List -->
                        <div class="flex-1 overflow-y-auto p-3 space-y-1">
                            <template x-if="conversations.length === 0 && !loading">
                                <div class="h-full flex flex-col items-center justify-center text-center p-6 opacity-60">
                                    <i data-lucide="message-square-dashed" class="w-12 h-12 text-slate-300 mb-4"></i>
                                    <h3 class="text-sm font-black text-slate-500 mb-1">No Conversations Yet</h3>
                                    <p class="text-xs font-medium text-slate-400">Search for a user to start chatting.</p>
                                </div>
                            </template>
                            
                            <template x-for="conv in conversations" :key="conv.contact_id">
                                <button @click="openChat(conv)" 
                                        :class="{'bg-slate-50 border-emerald-100 shadow-sm': activeChat && activeChat.id == conv.contact_id, 'hover:bg-slate-50 border-transparent': !(activeChat && activeChat.id == conv.contact_id)}"
                                        class="w-full flex items-center gap-4 p-3 rounded-2xl border transition-all text-left group">
                                    
                                    <div class="relative shrink-0">
                                        <div class="w-14 h-14 rounded-full bg-slate-100 text-slate-400 font-bold flex items-center justify-center overflow-hidden border border-slate-200">
                                            <template x-if="conv.profile_image && conv.profile_image !== 'default_avatar.png' && conv.profile_image !== 'default_avatar.jpg'">
                                                <img :src="'../assets/uploads/' + (conv.profile_image.includes('/') ? conv.profile_image : 'profiles/' + conv.profile_image)" class="w-full h-full object-cover">
                                            </template>
                                            <template x-if="!conv.profile_image || conv.profile_image === 'default_avatar.png' || conv.profile_image === 'default_avatar.jpg'">
                                                <span class="text-lg" x-text="conv.full_name.charAt(0).toUpperCase()"></span>
                                            </template>
                                        </div>
                                    </div>
                                    
                                    <div class="flex-1 min-w-0 flex flex-col justify-center">
                                        <div class="flex items-center justify-between mb-1">
                                            <h4 class="text-sm font-black text-slate-900 truncate" x-text="conv.full_name"></h4>
                                            <span class="text-[10px] font-bold text-slate-400 shrink-0" x-text="formatDate(conv.last_msg_time)"></span>
                                        </div>
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm truncate" :class="conv.unread_count > 0 ? 'font-bold text-slate-800' : 'font-medium text-slate-500'" x-text="conv.last_message"></p>
                                            <template x-if="conv.unread_count > 0">
                                                <span class="w-5 h-5 rounded-full bg-emerald-500 text-white text-[10px] font-black flex items-center justify-center shrink-0 shadow-sm" x-text="conv.unread_count"></span>
                                            </template>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <!-- Right Chat Area -->
                    <div class="flex-1 h-full flex flex-col bg-chat-pattern relative transition-transform duration-300"
                         :class="{ 'translate-x-0': activeChat, 'translate-x-full md:translate-x-0 hidden md:flex': !activeChat }">
                        
                        <!-- Empty State -->
                        <div x-show="!activeChat" class="absolute inset-0 flex flex-col items-center justify-center text-center p-8 bg-slate-50 z-10">
                            <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-sm border border-slate-100 mb-6 text-emerald-300">
                                <i data-lucide="messages-square" class="w-12 h-12"></i>
                            </div>
                            <h2 class="text-2xl font-black text-slate-800 mb-2">Nibash Personal Messenger</h2>
                            <p class="text-slate-500 font-medium max-w-sm">Select a conversation from the left or search for a resident or provider to start chatting securely.</p>
                        </div>

                        <!-- Chat Header -->
                        <div x-show="activeChat" class="h-[72px] shrink-0 bg-white border-b border-slate-200 px-4 md:px-6 flex items-center justify-between z-20 shadow-sm">
                            <div class="flex items-center gap-3 md:gap-4 overflow-hidden">
                                <button @click="activeChat = null; fetchConversations()" class="md:hidden w-10 h-10 flex items-center justify-center text-slate-400 hover:bg-slate-50 hover:text-slate-600 rounded-full transition-colors shrink-0">
                                    <i data-lucide="arrow-left" class="w-5 h-5"></i>
                                </button>
                                
                                <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-emerald-100 text-emerald-600 font-bold flex items-center justify-center overflow-hidden shrink-0 border border-emerald-200">
                                    <template x-if="activeChat?.profile_image && activeChat.profile_image !== 'default_avatar.png' && activeChat.profile_image !== 'default_avatar.jpg'">
                                        <img :src="'../assets/uploads/' + (activeChat.profile_image.includes('/') ? activeChat.profile_image : 'profiles/' + activeChat.profile_image)" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!activeChat?.profile_image || activeChat.profile_image === 'default_avatar.png' || activeChat.profile_image === 'default_avatar.jpg'">
                                        <span class="text-lg" x-text="activeChat?.full_name?.charAt(0)?.toUpperCase()"></span>
                                    </template>
                                </div>
                                
                                <div class="min-w-0">
                                    <h2 class="text-sm md:text-base font-black text-slate-900 truncate" x-text="activeChat?.full_name"></h2>
                                    <p class="text-xs font-bold text-emerald-600">Online</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-2 shrink-0">
                                <!-- Jitsi Audio Call -->
                                <button @click="startCall('audio')" class="w-10 h-10 rounded-full flex items-center justify-center bg-slate-50 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 transition-colors border border-slate-200 hover:border-emerald-200" title="Audio Call">
                                    <i data-lucide="phone" class="w-4 h-4"></i>
                                </button>
                                <!-- Jitsi Video Call -->
                                <button @click="startCall('video')" class="w-10 h-10 rounded-full flex items-center justify-center bg-slate-50 text-slate-600 hover:bg-emerald-50 hover:text-emerald-600 transition-colors border border-slate-200 hover:border-emerald-200" title="Video Call">
                                    <i data-lucide="video" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Chat Messages Area -->
                        <div x-show="activeChat" class="flex-1 overflow-y-auto p-4 md:p-6" x-ref="messagesContainer">
                            <div class="space-y-1 pb-4 flex flex-col">
                                <template x-for="msg in messages" :key="msg.id">
                                    <div class="w-full flex" :class="msg.sender_id == <?= $user_id ?> ? 'justify-end' : 'justify-start'">
                                        <div class="message-bubble" :class="msg.sender_id == <?= $user_id ?> ? 'message-out' : 'message-in'">
                                            <span x-text="msg.message"></span>
                                            <span class="message-time" x-text="formatTime(msg.created_at)"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Chat Input Box -->
                        <div x-show="activeChat" class="p-3 bg-white border-t border-slate-200 shrink-0">
                            <form @submit.prevent="sendMessage" class="flex items-end gap-2 relative">
                                <textarea x-model="newMessage" x-ref="msgInput" @keydown.enter.prevent="sendMessage" placeholder="Type a message..." class="flex-1 max-h-32 min-h-[48px] h-12 bg-slate-50 border border-slate-200 rounded-[1.5rem] px-5 py-3 text-sm font-medium outline-none focus:border-emerald-400 focus:bg-white focus:ring-4 focus:ring-emerald-500/10 transition-all resize-none scrollbar-hide"></textarea>
                                
                                <button type="submit" :disabled="!newMessage.trim()" class="w-12 h-12 rounded-[1.25rem] bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:hover:bg-emerald-600 text-white flex items-center justify-center transition-colors shrink-0 shadow-sm">
                                    <i data-lucide="send" class="w-5 h-5 ml-1"></i>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Jitsi Meet iframe Container overlay -->
                        <div x-show="isCalling" x-cloak class="absolute inset-0 bg-black z-50 flex flex-col">
                            <div class="h-[60px] bg-slate-900 px-4 flex items-center justify-between text-white border-b border-slate-800">
                                <div class="flex items-center gap-3">
                                    <div class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></div>
                                    <span class="font-bold text-sm">Secure Call with <span x-text="activeChat?.full_name"></span></span>
                                </div>
                                <button @click="endCall()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg transition-colors">
                                    End Call
                                </button>
                            </div>
                            <div class="flex-1 w-full bg-[#111]" id="jitsi-container"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Jitsi External API -->
    <script src='https://8x8.vc/external_api.js'></script>
    <script>
        document.addEventListener('alpine:init', () => {
            lucide.createIcons();
            
            Alpine.data('chatApp', () => ({
                userId: null,
                targetUserId: null,
                searchQuery: '',
                searchResults: [],
                showSearch: false,
                conversations: [],
                activeChat: null,
                messages: [],
                newMessage: '',
                pollInterval: null,
                loading: true,
                
                isCalling: false,
                jitsiApi: null,

                initApp(uid, targetId) {
                    this.userId = uid;
                    this.fetchConversations();
                    
                    if (targetId) {
                        this.loadTargetUser(targetId);
                    }
                    
                    this.pollInterval = setInterval(() => {
                        this.fetchConversations(false);
                        if (this.activeChat) {
                            this.fetchMessages(this.activeChat.id, false);
                        }
                    }, 5000);
                },
                
                async loadTargetUser(id) {
                    const res = await fetch(`api.php?action=get_user_details&user_id=${id}`);
                    const user = await res.json();
                    if (user) {
                        this.startChat(user);
                    }
                },

                async searchUsers() {
                    if (this.searchQuery.length < 3) {
                        this.searchResults = [];
                        return;
                    }
                    const res = await fetch(`api.php?action=search_users&q=${encodeURIComponent(this.searchQuery)}`);
                    this.searchResults = await res.json();
                },

                async fetchConversations(showLoader = true) {
                    if(showLoader) this.loading = true;
                    const res = await fetch('api.php?action=get_conversations');
                    this.conversations = await res.json();
                    if(showLoader) this.loading = false;
                },

                startChat(user) {
                    this.activeChat = {
                        id: user.id || user.contact_id,
                        full_name: user.full_name,
                        profile_image: user.profile_image
                    };
                    this.fetchMessages(this.activeChat.id);
                },

                openChat(conv) {
                    this.activeChat = {
                        id: conv.contact_id,
                        full_name: conv.full_name,
                        profile_image: conv.profile_image
                    };
                    this.fetchMessages(conv.contact_id);
                },

                async fetchMessages(contactId, scroll = true) {
                    const res = await fetch(`api.php?action=get_messages&contact_id=${contactId}`);
                    const msgs = await res.json();
                    const isNew = msgs.length > this.messages.length;
                    this.messages = msgs;
                    
                    if (scroll && isNew) {
                        this.$nextTick(() => {
                            const container = this.$refs.messagesContainer;
                            container.scrollTop = container.scrollHeight;
                        });
                    }
                },

                async sendMessage(e) {
                    if(e) e.preventDefault();
                    if (!this.newMessage.trim() || !this.activeChat) return;
                    
                    const text = this.newMessage.trim();
                    this.newMessage = ''; 
                    
                    this.messages.push({
                        id: 'temp-' + Date.now(),
                        sender_id: this.userId,
                        message: text,
                        created_at: new Date().toISOString()
                    });
                    
                    this.$nextTick(() => {
                        const container = this.$refs.messagesContainer;
                        container.scrollTop = container.scrollHeight;
                        this.$refs.msgInput.focus();
                    });

                    const formData = new FormData();
                    formData.append('action', 'send_message');
                    formData.append('receiver_id', this.activeChat.id);
                    formData.append('message', text);

                    await fetch('api.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    this.fetchMessages(this.activeChat.id);
                    this.fetchConversations(false);
                },
                
                startCall(type) {
                    const roomName = `NibashSecure_${Math.min(this.userId, this.activeChat.id)}_${Math.max(this.userId, this.activeChat.id)}`;
                    const callMsg = `Started a ${type} call. Join room: ${roomName}`;
                    
                    this.newMessage = callMsg;
                    this.sendMessage(null);
                    
                    this.isCalling = true;
                    
                    const domain = '8x8.vc';
                    const options = {
                        roomName: `vpaas-magic-cookie-3d5f1361abef4bde8c9d2f2603f905c1/${roomName}`,
                        width: '100%',
                        height: '100%',
                        parentNode: document.querySelector('#jitsi-container'),
                        configOverwrite: { 
                            startWithAudioMuted: false, 
                            startWithVideoMuted: type === 'audio' 
                        },
                        interfaceConfigOverwrite: {
                            DISABLE_DOMINANT_SPEAKER_INDICATOR: true
                        },
                        userInfo: {
                            displayName: '<?= addslashes($cur_user['full_name'] ?? 'User') ?>'
                        }
                    };
                    
                    if (this.jitsiApi) {
                        this.jitsiApi.dispose();
                    }
                    
                    this.jitsiApi = new JitsiMeetExternalAPI(domain, options);
                    
                    this.jitsiApi.addEventListener('videoConferenceLeft', () => {
                        this.endCall();
                    });
                },
                
                endCall() {
                    if(this.jitsiApi) {
                        this.jitsiApi.dispose();
                        this.jitsiApi = null;
                    }
                    this.isCalling = false;
                },

                formatTime(datetimeStr) {
                    if(!datetimeStr) return '';
                    const d = new Date(datetimeStr);
                    return d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                },
                
                formatDate(datetimeStr) {
                    if(!datetimeStr) return '';
                    const d = new Date(datetimeStr);
                    const today = new Date();
                    if(d.toDateString() === today.toDateString()) {
                        return this.formatTime(datetimeStr);
                    }
                    return d.toLocaleDateString([], {month: 'short', day: 'numeric'});
                }
            }));
        });
    </script>
</body>
</html>
