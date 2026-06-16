<div id="nibash-chatbot-container" class="fixed bottom-6 right-6 z-[9999] flex flex-col items-end" style="font-family: inherit;">
    <!-- Chat Window -->
    <div id="nibash-chat-window" class="hidden w-80 md:w-96 bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden mb-4 transform transition-all opacity-0 translate-y-4">
        <!-- Header -->
        <div class="bg-slate-800 p-4 border-b border-slate-700 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400">
                    <i data-lucide="bot" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="text-white font-semibold text-sm">Nibash AI</h3>
                    <p class="text-slate-400 text-xs">Always here to help</p>
                </div>
            </div>
            <button id="nibash-chat-close" class="text-slate-400 hover:text-white transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>

        <!-- Messages Area -->
        <div id="nibash-chat-messages" class="h-80 overflow-y-auto p-4 space-y-4 bg-slate-900 scrollbar-thin scrollbar-thumb-slate-700">
            <!-- Initial Greeting (shown only when no history exists) -->
            <div id="nibash-default-greeting" class="flex gap-3 text-sm">
                <div class="w-8 h-8 rounded-full bg-slate-800 flex-shrink-0 flex items-center justify-center text-emerald-400">
                    <i data-lucide="sparkles" class="w-4 h-4"></i>
                </div>
                <div class="bg-slate-800 text-slate-200 p-3 rounded-2xl rounded-tl-sm max-w-[85%] leading-relaxed">
                    Hello! I'm your Nibash AI assistant. Ask me to navigate pages (e.g. "go to billing") or check your data (e.g. "What is my due bill?"). How can I help?
                </div>
            </div>
        </div>

        <!-- @ Suggestions Popup -->
        <div id="nibash-chat-suggestions" class="absolute bottom-[4.5rem] left-0 w-full bg-slate-800 border-t border-slate-700 shadow-2xl rounded-t-xl overflow-hidden hidden flex-col z-50 max-h-48 overflow-y-auto">
            <div class="px-3 py-2 text-xs font-bold text-slate-400 bg-slate-900 border-b border-slate-700">Quick Commands</div>
            <!-- Suggestions injected here via JS -->
        </div>

        <!-- Input Area -->
        <div class="p-3 bg-slate-800 border-t border-slate-700 relative z-10">
            <form id="nibash-chat-form" class="flex gap-2">
                <input type="text" id="nibash-chat-input" placeholder="Type a command or use @ for shortcuts..." class="flex-1 bg-slate-900 text-white placeholder-slate-400 text-sm rounded-xl px-4 py-2 border border-slate-700 focus:outline-none focus:border-emerald-500 transition-colors" autocomplete="off" required>
                <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl p-2 transition-colors flex items-center justify-center w-10 shrink-0 shadow">
                    <i data-lucide="send" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- Toggle Button -->
    <button id="nibash-chat-toggle" class="w-14 h-14 bg-emerald-500 hover:bg-emerald-600 text-white rounded-full shadow-lg flex items-center justify-center transition-transform hover:scale-105 hover:shadow-emerald-500/25">
        <i data-lucide="message-square" class="w-6 h-6"></i>
    </button>
</div>

<!-- Load Lucide Icons if not already loaded -->
<script>
    if (typeof lucide === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://unpkg.com/lucide@latest';
        script.onload = () => lucide.createIcons();
        document.head.appendChild(script);
    } else {
        lucide.createIcons();
    }
</script>

<?php
    // Inject the absolute URL of the intent router so the JS never has to guess
    $base_url = defined('BASE_URL') ? rtrim(BASE_URL, '/') : 'http://localhost/Nibash';
?>
<!-- Hardcoded chatbot endpoint — no path-guessing needed -->
<script>
    window.NIBASH_CHATBOT_ENDPOINT = '<?= $base_url ?>/chatbot/intent_router.php';
</script>
<script src="<?= $base_url ?>/chatbot/chat_handler.js?v=<?= time() ?>"></script>
