<script type="text/javascript">
    function googleTranslateElementInit() {
        new google.translate.TranslateElement({
            pageLanguage: 'en', 
            includedLanguages: 'bn,en', 
            autoDisplay: false
        }, 'google_translate_element');
    }

    function toggleLanguage() {
        let currentCookie = document.cookie.match(/(?:^|;)\s*googtrans=([^;]*)/);
        let currentLang = currentCookie ? decodeURIComponent(currentCookie[1]) : '';
        
        if (currentLang === '/en/bn') {
            document.cookie = 'googtrans=/en/en; path=/';
            document.cookie = 'googtrans=/en/en; path=/; domain=' + location.hostname;
        } else {
            document.cookie = 'googtrans=/en/bn; path=/';
            document.cookie = 'googtrans=/en/bn; path=/; domain=' + location.hostname;
        }
        window.location.reload();
    }
</script>
<script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
<style>
    /* Hide the Google Translate UI elements */
    .goog-te-banner-frame.skiptranslate, .goog-te-banner-frame { display: none !important; }
    body { top: 0px !important; position: static !important; }
    #google_translate_element { display: none !important; }
    .goog-tooltip { display: none !important; }
    .goog-tooltip:hover { display: none !important; }
    .goog-text-highlight { background-color: transparent !important; border: none !important; box-shadow: none !important; }
    /* Hide the banner container completely */
    body > .skiptranslate { display: none !important; }
</style>
<div id="google_translate_element"></div>
