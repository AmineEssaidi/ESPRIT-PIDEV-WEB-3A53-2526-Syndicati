// Global settings loader for theme, accent, and language
(function () {
    try {
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('logout') === 'success') {
            localStorage.removeItem('theme');
            localStorage.removeItem('accent-gradient');
            localStorage.removeItem('accent-color');
            localStorage.removeItem('lang');
            localStorage.removeItem('animated-accents');
            // Remove the param to avoid repeated clearing
            var url = new URL(window.location);
            url.searchParams.delete('logout');
            window.history.replaceState({}, '', url);
        }

        // SYNC & SANITIZE SERVER SETTINGS
        // blocked: #6c5ce7 (Blue), #5a4fcf (Purple-Blue), 108, 92, 231 (RGB Blue)
        var serverSettings = window.serverSettings || {};
        if (Array.isArray(serverSettings)) serverSettings = {};

        // 1. MUTATE THE SOURCE: valid overrides only
        if (serverSettings['accent-color'] === '#6c5ce7' || serverSettings['accent-color'] === '#5a4fcf' || serverSettings['accent-color'] === '108, 92, 231') {
            serverSettings['accent-color'] = 'transparent';
        }
        if (serverSettings['accent-gradient'] && (serverSettings['accent-gradient'].includes('#6c5ce7') || serverSettings['accent-gradient'].includes('#5a4fcf'))) {
            serverSettings['accent-gradient'] = 'linear-gradient(135deg, transparent, transparent, transparent)';
        }
        // Update window object so other scripts see clean data
        window.serverSettings = serverSettings;

        if (Object.keys(serverSettings).length > 0) {
            if (serverSettings.theme) localStorage.setItem('theme', serverSettings.theme);
            if (serverSettings['accent-gradient']) localStorage.setItem('accent-gradient', serverSettings['accent-gradient']);
            if (serverSettings['accent-color']) localStorage.setItem('accent-color', serverSettings['accent-color']);
            if (serverSettings.lang) localStorage.setItem('lang', serverSettings.lang);
            if (serverSettings['animated-accents'] !== undefined) localStorage.setItem('animated-accents', serverSettings['animated-accents']);
        }

        // FORCE WIPE: Use a version flag to forcefully clear old user preferences.
        // This ensures EVERYONE gets the new neutral defaults.
        var CONFIG_VERSION = 'v5_premium_glass';
        var storedVersion = localStorage.getItem('config_version');

        if (storedVersion !== CONFIG_VERSION) {
            // Wipe all theme-related color settings
            localStorage.removeItem('accent-color');
            localStorage.removeItem('accent-gradient');
            localStorage.removeItem('main-home-accent-rgb');
            // Update version so we don't wipe again
            localStorage.setItem('config_version', CONFIG_VERSION);
            console.log('🧹 System Colors Reset to Neutral Defaults (v2)');
        }

        var html = document.documentElement;
        var theme = localStorage.getItem('theme') || 'dark';
        var accentGradient = localStorage.getItem('accent-gradient') || 'linear-gradient(135deg, rgba(245, 245, 245, 0.1), rgba(245, 245, 245, 0.05))';
        var accentColor = localStorage.getItem('accent-color') || '#F5F5F5';
        var lang = localStorage.getItem('lang') || 'fr';
        var animatedAccents = localStorage.getItem('animated-accents') !== 'false';
        function hexToRgb(hex) {
            if (hex === 'transparent') return '245, 245, 245';
            var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            if (!m) return '245, 245, 245';
            return [parseInt(m[1], 16), parseInt(m[2], 16), parseInt(m[3], 16)].join(', ');
        }
        html.setAttribute('data-theme', theme);
        html.style.setProperty('--main-home-accent-gradient', accentGradient);
        html.style.setProperty('--accent-gradient', accentGradient);
        html.style.setProperty('--main-home-accent-color', accentColor);
        html.style.setProperty('--accent', accentColor);
        html.style.setProperty('--primary', accentColor);
        html.style.setProperty('--main-home-accent-rgb', hexToRgb(accentColor));
        html.style.setProperty('--accent-glow', 'rgba(' + hexToRgb(accentColor) + ', 0.5)');
        // 4. GRADIENT BORDER (animated border on footer, navbar, cards)
        var gradientBorder = accentGradient || 'linear-gradient(135deg, transparent, transparent)';

        // SANITIZE: If gradientBorder contains blue, replace it
        if (gradientBorder.includes('#6c5ce7') || gradientBorder.includes('#5a4fcf') || gradientBorder.includes('#1e293b') || gradientBorder.includes('#334155')) {
            gradientBorder = 'linear-gradient(135deg, transparent, transparent)';
        }

        html.style.setProperty('--main-home-accent-gradient-border', gradientBorder);
        html.style.setProperty('--primary-gradient', accentGradient);
        html.setAttribute('lang', lang);
        if (animatedAccents) {
            html.classList.add('accents-animated');
        } else {
            html.classList.remove('accents-animated');
        }

        window.setLanguage = function (newLang) {
            localStorage.setItem('lang', newLang);
            // Set cookie so LocaleSubscriber picks it up
            document.cookie = "frontend_lang=" + newLang + "; path=/; max-age=31536000";
            document.documentElement.setAttribute('lang', newLang);

            // Reload page to let Symfony native translation take over
            window.location.reload();
        };

        // --- Premium Glass Modal Helpers ---
        window.openGlassModal = function (modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            modal.style.display = 'flex';
            // Force reflow for transition
            modal.offsetHeight;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        };

        window.closeGlassModal = function (modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
                // Only restore overflow if no other glass modals are active
                if (!document.querySelector('.glass-modal-overlay.active')) {
                    document.body.style.overflow = '';
                }
            }, 400); // Pulse duration matching glass-modal.css
        };

        // Global Close Listeners
        document.addEventListener('click', function (e) {
            if (e.target.classList.contains('glass-modal-close') || e.target.classList.contains('glass-modal-cancel')) {
                const modalId = e.target.dataset.modal || e.target.closest('.glass-modal-overlay')?.id;
                if (modalId) closeGlassModal(modalId);
            }
            // Backdrop click
            if (e.target.classList.contains('glass-modal-overlay')) {
                closeGlassModal(e.target.id);
            }
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.glass-modal-overlay.active');
                if (activeModal) closeGlassModal(activeModal.id);
            }
        });
    } catch (e) { }
})();
