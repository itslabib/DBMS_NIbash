<script type="text/javascript">
    function toggleDarkMode() {
        let isDark = document.documentElement.classList.contains('dark');
        if (isDark) {
            document.documentElement.classList.remove('dark');
            document.cookie = 'theme=light; path=/; domain=' + location.hostname + '; max-age=31536000';
        } else {
            document.documentElement.classList.add('dark');
            document.cookie = 'theme=dark; path=/; domain=' + location.hostname + '; max-age=31536000';
        }
    }
</script>
<style>
/* Formal Dark Mode Styles for Nibash */
html.dark {
    color-scheme: dark;
    background-color: #0f172a !important; /* Base for entire viewport */
}
html.dark body, html.dark main, 
html.dark .bg-slate-50, html.dark .bg-slate-50\/30, html.dark .bg-slate-50\/50, html.dark .bg-slate-50\/70, 
html.dark .bg-slate-100, html.dark .bg-slate-100\/50, html.dark .bg-slate-100\/60, html.dark .bg-slate-100\/80,
html.dark .bg-\[\#FAFAFA\], html.dark .bg-\[\#f2fbf6\], html.dark .bg-\[\#F0FAF4\], html.dark .bg-\[\#fafcfa\] {
    background-color: #0f172a !important; /* Deep Navy */
    background-image: none !important;
    color: #f8fafc !important;
}

/* Sidebar & Headers */
html.dark aside, html.dark header {
    background-color: #1e293b !important;
    border-color: #334155 !important;
}
/* Translucent headers for blur effects */
html.dark .bg-white\/95 {
    background-color: rgba(30, 41, 59, 0.95) !important;
    border-color: #334155 !important;
}
html.dark .bg-white\/80 {
    background-color: rgba(30, 41, 59, 0.8) !important;
    border-color: #334155 !important;
}
html.dark .bg-white\/70, html.dark #navbar {
    background-color: rgba(30, 41, 59, 0.7) !important;
    border-color: rgba(16, 185, 129, 0.2) !important;
}
html.dark .nav-scrolled {
    background-color: rgba(15, 23, 42, 0.90) !important;
    border-bottom: 1px solid #1e293b !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5) !important;
}

/* Cards & Modals */
html.dark .bg-white {
    background-color: #1e293b !important;
    border-color: #334155 !important;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.4) !important;
}

/* Text Colors */
html.dark .text-slate-900, html.dark .text-slate-800 { color: #f8fafc !important; }
html.dark .text-slate-700, html.dark .text-slate-600 { color: #cbd5e1 !important; }
html.dark .text-slate-500 { color: #94a3b8 !important; }
html.dark .text-slate-400 { color: #64748b !important; }

/* Borders */
html.dark .border-slate-200, html.dark .border-slate-100, html.dark .border-slate-50, html.dark .border-emerald-50, html.dark .border-teal-50, html.dark .border-indigo-50, html.dark .border-rose-50, html.dark .border-sky-50, html.dark .border-white {
    border-color: #334155 !important;
}

/* Base Light Backgrounds (Tables, Sub-areas) */
html.dark .bg-slate-50 { background-color: #0f172a !important; }

/* Table specific styling */
html.dark table thead tr { background-color: #0f172a !important; }
html.dark table tbody tr { border-color: #334155 !important; }
html.dark table tbody tr:hover, html.dark table tbody tr.hover\:bg-slate-50\/70:hover, html.dark .hover\:bg-slate-50\/70:hover, html.dark .hover\:bg-slate-200\/50:hover { background-color: #1e293b !important; }

/* Gradients */
html.dark .from-white\/50 {
    --tw-gradient-from: rgba(15, 23, 42, 0.5) !important;
    --tw-gradient-to: rgba(15, 23, 42, 0) !important;
    --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important;
}

/* Accents - Formalized (Emerald/Teal/Indigo) */
/* Replaces light theme background tinted colors with darker ones that maintain brand identity but formal */
html.dark .bg-emerald-50, html.dark .bg-teal-50, html.dark .hover\:bg-emerald-50:hover, html.dark .hover\:bg-teal-50:hover, html.dark .bg-emerald-100 {
    background-color: rgba(16, 185, 129, 0.1) !important;
    border-color: rgba(16, 185, 129, 0.2) !important;
}
html.dark .text-emerald-700, html.dark .text-emerald-600, html.dark .text-teal-700, html.dark .text-teal-600 {
    color: #34d399 !important;
}

html.dark .bg-indigo-50, html.dark .hover\:bg-indigo-50:hover, html.dark .bg-indigo-50\/30 {
    background-color: rgba(99, 102, 241, 0.1) !important;
    border-color: rgba(99, 102, 241, 0.2) !important;
}
html.dark .text-indigo-700, html.dark .text-indigo-600 { color: #818cf8 !important; }

html.dark .bg-amber-50, html.dark .bg-yellow-50, html.dark .bg-yellow-100, html.dark .bg-amber-100, html.dark .hover\:bg-amber-50:hover {
    background-color: rgba(245, 158, 11, 0.1) !important;
    border-color: rgba(245, 158, 11, 0.2) !important;
}
html.dark .text-amber-700, html.dark .text-amber-600, html.dark .text-yellow-700, html.dark .text-yellow-600 { color: #fbbf24 !important; }

html.dark .bg-rose-50, html.dark .hover\:bg-rose-50:hover {
    background-color: rgba(244, 63, 94, 0.1) !important;
    border-color: rgba(244, 63, 94, 0.2) !important;
}
html.dark .text-rose-700, html.dark .text-rose-600 { color: #fb7185 !important; }

html.dark .bg-sky-50, html.dark .bg-cyan-50, html.dark .bg-cyan-100, html.dark .bg-blue-100, html.dark .hover\:bg-sky-50:hover {
    background-color: rgba(14, 165, 233, 0.1) !important;
    border-color: rgba(14, 165, 233, 0.2) !important;
}
html.dark .text-sky-700, html.dark .text-sky-600, html.dark .text-cyan-700, html.dark .text-cyan-600, html.dark .text-blue-700, html.dark .text-blue-600 { color: #38bdf8 !important; }

html.dark .bg-purple-50, html.dark .bg-purple-100 {
    background-color: rgba(168, 85, 247, 0.1) !important;
    border-color: rgba(168, 85, 247, 0.2) !important;
}
html.dark .text-purple-700, html.dark .text-purple-600 { color: #c084fc !important; }

html.dark .border-emerald-100, html.dark .border-teal-100, html.dark .border-indigo-100, html.dark .border-amber-100, html.dark .border-rose-100, html.dark .border-sky-100 {
    border-color: #475569 !important;
}

/* Links & Buttons */
html.dark .hover\:text-blue-600:hover, html.dark .hover\:text-emerald-600:hover, html.dark .hover\:text-teal-600:hover { color: #34d399 !important; }
html.dark .bg-slate-900, html.dark .text-white { color: #ffffff !important; }

/* Input fields */
html.dark input, html.dark select, html.dark textarea {
    background-color: #0f172a !important;
    border-color: #334155 !important;
    color: #f8fafc !important;
}
</style>
