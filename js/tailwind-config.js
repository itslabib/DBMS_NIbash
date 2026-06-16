// js/tailwind-config.js
tailwind.config = {
    darkMode: 'class',
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'sans-serif'],
            },
            colors: {
                navy: {
                    DEFAULT: '#1e293b',
                    dark: '#0f172a',
                    light: '#334155'
                },
                emerald: {
                    DEFAULT: '#10b981',
                    dark: '#047857',
                    light: '#34d399'
                },
                slatewhite: '#f8fafc'
            },
            animation: {
                'fade-in': 'fadeIn 0.5s ease-out forwards',
                'slide-up': 'slideUp 0.6s ease-out forwards',
                'scan-line': 'scanLine 2.5s ease-in-out infinite',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                slideUp: {
                    '0%': { transform: 'translateY(20px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                scanLine: {
                    '0%': { transform: 'translateY(0)', opacity: '0' },
                    '5%': { opacity: '1' },
                    '50%': { transform: 'translateY(240px)' },
                    '95%': { opacity: '1' },
                    '100%': { transform: 'translateY(0)', opacity: '0' },
                }
            }
        }
    }
}
