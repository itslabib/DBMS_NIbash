document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('nibash-chatbot-container');
    if (!container) return;

    const chatWindow = document.getElementById('nibash-chat-window');
    const toggleBtn  = document.getElementById('nibash-chat-toggle');
    const closeBtn   = document.getElementById('nibash-chat-close');
    const form       = document.getElementById('nibash-chat-form');
    const input      = document.getElementById('nibash-chat-input');
    const messagesArea = document.getElementById('nibash-chat-messages');

    // ==========================================
    // SESSION STORAGE — persist chat across pages
    // Cleared on tab close or logout (logout.php
    // should call sessionStorage.clear())
    // ==========================================
    const STORAGE_KEY = 'nibash_chat_history';

    const loadHistory = () => {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY);
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    };

    const saveHistory = (history) => {
        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history));
        } catch (e) { /* storage full – silently fail */ }
    };

    const pushHistory = (text, isUser, isHtml) => {
        const history = loadHistory();
        history.push({ text, isUser, isHtml });
        // Keep last 80 messages max to avoid storage bloat
        if (history.length > 80) history.splice(0, history.length - 80);
        saveHistory(history);
    };

    // ==========================================
    // Toggle logic
    // ==========================================
    const toggleChat = () => {
        if (chatWindow.classList.contains('hidden')) {
            chatWindow.classList.remove('hidden');
            setTimeout(() => {
                chatWindow.classList.remove('opacity-0', 'translate-y-4');
                input.focus();
            }, 10);
        } else {
            chatWindow.classList.add('opacity-0', 'translate-y-4');
            setTimeout(() => chatWindow.classList.add('hidden'), 300);
        }
    };

    toggleBtn.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    // ==========================================
    // Add message to UI
    // ==========================================
    const addMessage = (text, isUser = false, isHtml = false, persist = true) => {
        const wrapper = document.createElement('div');
        wrapper.className = `flex gap-3 text-sm ${isUser ? 'flex-row-reverse' : ''}`;

        let iconHtml = '';
        if (!isUser) {
            iconHtml = `
            <div class="w-8 h-8 rounded-full bg-slate-800 flex-shrink-0 flex items-center justify-center text-emerald-400">
                <i data-lucide="sparkles" class="w-4 h-4"></i>
            </div>`;
        }

        const msgClass = isUser
            ? 'bg-emerald-600 text-white p-3 rounded-2xl rounded-tr-sm max-w-[85%] shadow-sm leading-relaxed'
            : 'bg-slate-800 text-slate-200 p-3 rounded-2xl rounded-tl-sm max-w-[85%] shadow-sm leading-relaxed border border-slate-700/50';

        const content = isHtml ? text : text.replace(/</g, "&lt;").replace(/>/g, "&gt;");

        wrapper.innerHTML = `
            ${iconHtml}
            <div class="${msgClass}">${content}</div>
        `;

        messagesArea.appendChild(wrapper);
        messagesArea.scrollTop = messagesArea.scrollHeight;

        if (typeof lucide !== 'undefined') lucide.createIcons();

        if (persist) pushHistory(text, isUser, isHtml);

        return wrapper;
    };

    // ==========================================
    // Restore history from sessionStorage
    // ==========================================
    const history = loadHistory();
    if (history.length > 0) {
        // Hide the default greeting, show persisted history instead
        const greeting = document.getElementById('nibash-default-greeting');
        if (greeting) greeting.style.display = 'none';
        history.forEach(msg => addMessage(msg.text, msg.isUser, msg.isHtml, false));
    }

    // ==========================================
    // Loader
    // ==========================================
    const addLoader = () => {
        const wrapper = document.createElement('div');
        wrapper.id = 'nibash-chat-loader';
        wrapper.className = 'flex gap-3 text-sm';
        wrapper.innerHTML = `
            <div class="w-8 h-8 rounded-full bg-slate-800 flex-shrink-0 flex items-center justify-center text-emerald-400">
                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
            </div>
            <div class="bg-slate-800 text-slate-400 p-3 rounded-2xl rounded-tl-sm text-xs italic flex items-center border border-slate-700/50">
                Analyzing intent...
            </div>
        `;
        messagesArea.appendChild(wrapper);
        messagesArea.scrollTop = messagesArea.scrollHeight;
        if (typeof lucide !== 'undefined') lucide.createIcons();
    };

    const removeLoader = () => {
        const loader = document.getElementById('nibash-chat-loader');
        if (loader) loader.remove();
    };

    // ==========================================
    // Resolve base URL from script tag src
    // Endpoint is set by chat_widget.php as an absolute URL
    const ENDPOINT = window.NIBASH_CHATBOT_ENDPOINT || '../chatbot/intent_router.php';

    // ==========================================
    // Handle SOS — calls real email API
    // ==========================================
    const handleSOS = async (endpoint) => {
        // Try to get GPS location first for richer alert
        const getCoords = () => new Promise((resolve) => {
            if (!navigator.geolocation) return resolve(null);
            navigator.geolocation.getCurrentPosition(
                (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
                ()    => resolve(null),
                { timeout: 4000 }
            );
        });

        try {
            const coords  = await getCoords();
            const formData = new FormData();
            if (coords) {
                formData.append('lat', coords.lat);
                formData.append('lng', coords.lng);
            }

            const res    = await fetch(endpoint, { method: 'POST', body: formData });
            const result = await res.json();

            if (result.success) {
                addMessage(result.message || '🚨 SOS sent successfully!', false, false);
                // If WhatsApp URL available, open it
                if (result.whatsapp_url) {
                    setTimeout(() => window.open(result.whatsapp_url, '_blank'), 1000);
                }
            } else {
                addMessage('⚠️ ' + (result.message || 'SOS failed. Please call your contacts directly.'), false, false);
            }
        } catch (err) {
            addMessage('⚠️ SOS network error. Please call your emergency contacts directly.', false, false);
        }
    };

    // ==========================================
    // @ Suggestions Logic
    // ==========================================
    const suggestionsBox = document.getElementById('nibash-chat-suggestions');
    const QUICK_COMMANDS = [
        { cmd: "Trigger SOS", icon: "triangle-alert", color: "text-rose-500" },
        { cmd: "Pay my bill", icon: "credit-card", color: "text-emerald-500" },
        { cmd: "Open guest passes", icon: "users", color: "text-blue-500" },
        { cmd: "Active service tickets", icon: "wrench", color: "text-orange-500" },
        { cmd: "Go to community hub", icon: "message-circle", color: "text-purple-500" },
        { cmd: "My apartment details", icon: "home", color: "text-indigo-400" },
        { cmd: "Browse rentals", icon: "building", color: "text-teal-400" },
        { cmd: "Find service provider", icon: "briefcase", color: "text-amber-500" },
        { cmd: "Open CCTV", icon: "camera", color: "text-slate-400" },
        { cmd: "Sign out", icon: "log-out", color: "text-slate-500" }
    ];

    const showSuggestions = (filterText) => {
        const filtered = filterText ? QUICK_COMMANDS.filter(c => c.cmd.toLowerCase().includes(filterText.toLowerCase())) : QUICK_COMMANDS;
        
        if (filtered.length === 0) {
            suggestionsBox.classList.add('hidden');
            return;
        }

        // Keep the header, remove old items
        Array.from(suggestionsBox.children).forEach(c => {
            if (!c.classList.contains('bg-slate-900')) c.remove();
        });

        filtered.forEach(cmd => {
            const div = document.createElement('div');
            div.className = "px-3 py-2.5 text-sm text-slate-300 hover:bg-slate-700 cursor-pointer flex items-center gap-3 transition-colors border-b border-slate-700/50 last:border-0";
            div.innerHTML = `<i data-lucide="${cmd.icon}" class="w-4 h-4 ${cmd.color}"></i> <span>${cmd.cmd}</span>`;
            div.addEventListener('click', () => {
                input.value = cmd.cmd;
                suggestionsBox.classList.add('hidden');
                form.dispatchEvent(new Event('submit')); // Auto submit
            });
            suggestionsBox.appendChild(div);
        });
        
        if (typeof lucide !== 'undefined') lucide.createIcons();
        suggestionsBox.classList.remove('hidden');
    };

    input.addEventListener('input', (e) => {
        const val = e.target.value;
        const atMatch = val.match(/@(.*)$/);
        
        if (atMatch) {
            showSuggestions(atMatch[1].trim());
        } else {
            suggestionsBox.classList.add('hidden');
        }
    });

    // Hide suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !suggestionsBox.contains(e.target)) {
            suggestionsBox.classList.add('hidden');
        }
    });

    // ==========================================
    // Form submission
    // ==========================================
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;

        addMessage(text, true);
        input.value   = '';
        input.disabled = true;
        addLoader();

        try {
            const formData = new FormData();
            formData.append('query', text);

            const response = await fetch(ENDPOINT, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            removeLoader();

            if (result.success) {
                if (result.type === 'navigation') {
                    const msg = result.response_text || `Routing you to <b>${result.target_name}</b>...`;
                    addMessage(msg, false, true);
                    setTimeout(() => {
                        window.location.href = result.url;
                    }, 1500);

                } else if (result.type === 'action') {
                    addMessage(result.response_text || 'Action executed.', false, true);
                    if (result.action === 'sos' && result.sos_endpoint) {
                        handleSOS(result.sos_endpoint);
                    }

                } else if (result.type === 'data') {
                    addMessage(result.response_text, false, true);

                // NEW: If it's technically unknown but Gemini still tried to send a message, show it
                } else if (result.type === 'unknown' && result.response_text) {
                    addMessage(result.response_text, false, true);
                    
                } else {
                    addMessage("I'm sorry, I couldn't understand that command. Try asking to navigate somewhere, trigger SOS, or check your bills.", false);
                }
            } else {
                addMessage(result.error || "Sorry, I couldn't process that right now.", false);
            }
        } catch (error) {
            removeLoader();
            console.error('Chatbot error:', error);
            addMessage("Network error occurred. Please try again.", false);
        } finally {
            input.disabled = false;
            input.focus();
        }
    });
});