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

        // SYNC SERVER SETTINGS
        var serverSettings = window.serverSettings || {};
        if (Array.isArray(serverSettings)) serverSettings = {};
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
        var CONFIG_VERSION = 'v6_syndicati_emerald';
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
        var accentGradient = localStorage.getItem('accent-gradient') || 'linear-gradient(135deg, #04130f 0%, #0a4f35 24%, #10b981 48%, #25f2a3 68%, #d6fff1 82%, #063b2d 100%)';
        var accentColor = localStorage.getItem('accent-color') || '#10b981';
        var lang = localStorage.getItem('lang') || 'fr';
        var animatedAccents = localStorage.getItem('animated-accents') !== 'false';
        var reduceMotion = localStorage.getItem('access-reduce-motion') === 'true';
        var highContrast = localStorage.getItem('access-high-contrast') === 'true';
        var dyslexiaFont = localStorage.getItem('access-dyslexia-font') === 'true';
        var comfortableTargets = localStorage.getItem('access-comfortable-targets') === 'true';
        var colorblindSafe = localStorage.getItem('access-colorblind-safe') !== 'false';
        var voiceInput = localStorage.getItem('access-voice-input') !== 'false';
        var agentCaptions = localStorage.getItem('access-agent-captions') !== 'false';
        var uiScale = parseFloat(localStorage.getItem('access-ui-scale') || '1');
        if (!Number.isFinite(uiScale)) uiScale = 1;
        uiScale = Math.min(1.25, Math.max(0.9, uiScale));
        function hexToRgb(hex) {
            if (hex === 'transparent') return '245, 245, 245';
            var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            if (!m) return '22, 163, 74';
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
        html.style.setProperty('--access-ui-scale', uiScale);
        var gradientBorder = accentGradient || 'linear-gradient(135deg, transparent, transparent)';
        html.style.setProperty('--main-home-accent-gradient-border', gradientBorder);
        html.style.setProperty('--primary-gradient', accentGradient);
        html.setAttribute('lang', lang);
        if (animatedAccents) {
            html.classList.add('accents-animated');
        } else {
            html.classList.remove('accents-animated');
        }
        html.classList.toggle('access-reduce-motion', reduceMotion);
        html.classList.toggle('access-high-contrast', highContrast);
        html.classList.toggle('access-dyslexia-font', dyslexiaFont);
        html.classList.toggle('access-comfortable-targets', comfortableTargets);
        html.classList.toggle('access-colorblind-safe', colorblindSafe);
        html.classList.toggle('access-agent-captions', agentCaptions);

        ensureAccessibilityStyles();
        document.addEventListener('DOMContentLoaded', function () {
            improveIconButtonLabels();
            if (reduceMotion || highContrast) pauseDecorativeVideos();
            if (voiceInput) enableFormVoiceInput();
            if (agentCaptions) enableAgentCaptions();
        });

        window.setLanguage = function (newLang) {
            localStorage.setItem('lang', newLang);
            // Set cookie so LocaleSubscriber picks it up
            document.cookie = "frontend_lang=" + newLang + "; path=/; max-age=31536000";
            document.documentElement.setAttribute('lang', newLang);

            // Reload page to let Symfony native translation take over
            window.location.reload();
        };

        function ensureAccessibilityStyles() {
            if (document.getElementById('syndicati-accessibility-styles')) return;
            var style = document.createElement('style');
            style.id = 'syndicati-accessibility-styles';
            style.textContent = `
                :root { font-size: calc(16px * var(--access-ui-scale, 1)); }
                .main-home-btn,
                .main-home-btn-primary,
                .main-home-btn-auth-primary,
                .main-home-btn-auth-submit,
                .glass-btn-save,
                .btn-primary-gradient,
                .onboarding-btn-next {
                    background:
                        linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0)),
                        rgba(var(--main-home-accent-rgb), 0.78) !important;
                    border: 1px solid rgba(255,255,255,0.20) !important;
                    box-shadow:
                        0 12px 30px rgba(var(--main-home-accent-rgb), 0.20),
                        inset 0 1px 0 rgba(255,255,255,0.18) !important;
                }
                .main-home-btn::before,
                .main-home-btn-auth-primary::before,
                .main-home-btn-auth-submit::before {
                    display: none !important;
                    opacity: 0 !important;
                }
                .main-home-section-title,
                .main-home-content-title,
                .main-home-cta-title,
                .main-home-value-title,
                .main-home-auth-title,
                .settings-page .main-home-hero-title,
                .settings-page .main-home-hero-subtitle,
                .settings-section-title {
                    background: none !important;
                    -webkit-background-clip: border-box !important;
                    background-clip: border-box !important;
                    -webkit-text-fill-color: currentColor !important;
                    color: #f8fffb !important;
                    text-shadow: 0 2px 18px rgba(0,0,0,0.42) !important;
                }
                html.access-reduce-motion *,
                html.access-reduce-motion *::before,
                html.access-reduce-motion *::after {
                    animation-duration: 0.001ms !important;
                    animation-iteration-count: 1 !important;
                    scroll-behavior: auto !important;
                    transition-duration: 0.001ms !important;
                }
                html.access-high-contrast {
                    --main-home-text-primary: #ffffff;
                    --main-home-text-secondary: rgba(255,255,255,0.92);
                }
                html.access-high-contrast .global-video-element {
                    filter: grayscale(1) brightness(0.18) contrast(1.1) !important;
                }
                html.access-high-contrast .global-video-overlay {
                    background: rgba(0,0,0,0.84) !important;
                    backdrop-filter: none !important;
                }
                html.access-high-contrast .main-home-content-box,
                html.access-high-contrast .forum-main-shell,
                html.access-high-contrast .settings-section {
                    background: rgba(0,0,0,0.90) !important;
                    border-color: rgba(255,255,255,0.34) !important;
                    box-shadow: none !important;
                    text-shadow: none !important;
                }
                html.access-high-contrast h1,
                html.access-high-contrast h2,
                html.access-high-contrast h3,
                html.access-high-contrast h4,
                html.access-high-contrast p,
                html.access-high-contrast span,
                html.access-high-contrast label,
                html.access-high-contrast strong,
                html.access-high-contrast small {
                    text-shadow: none !important;
                }
                html.access-dyslexia-font body,
                html.access-dyslexia-font input,
                html.access-dyslexia-font textarea,
                html.access-dyslexia-font button,
                html.access-dyslexia-font select {
                    font-family: Verdana, Tahoma, Arial, sans-serif !important;
                    letter-spacing: 0.02em;
                    word-spacing: 0.08em;
                    line-height: 1.65;
                }
                html.access-comfortable-targets button,
                html.access-comfortable-targets a,
                html.access-comfortable-targets input,
                html.access-comfortable-targets select,
                html.access-comfortable-targets textarea {
                    min-height: 44px;
                }
                html.access-comfortable-targets .main-home-search-icon-btn,
                html.access-comfortable-targets .main-home-notification-btn,
                html.access-comfortable-targets .scroll-down-pill {
                    min-width: 48px;
                    min-height: 48px;
                }
                :where(a, button, input, textarea, select, [tabindex]):focus-visible {
                    outline: 3px solid rgba(var(--main-home-accent-rgb), 0.95) !important;
                    outline-offset: 4px !important;
                    box-shadow: 0 0 0 6px rgba(var(--main-home-accent-rgb), 0.18) !important;
                }
                html.access-colorblind-safe .badge::before,
                html.access-colorblind-safe [class*="status"]::before,
                html.access-colorblind-safe [class*="pill"]::before {
                    margin-right: 0.35rem;
                    font-weight: 900;
                }
                html.access-colorblind-safe .text-success::before,
                html.access-colorblind-safe .badge-success::before,
                html.access-colorblind-safe [class*="success"]::before { content: "✓"; }
                html.access-colorblind-safe .text-danger::before,
                html.access-colorblind-safe .badge-danger::before,
                html.access-colorblind-safe [class*="danger"]::before,
                html.access-colorblind-safe [class*="error"]::before { content: "!"; }
                html.access-colorblind-safe .text-warning::before,
                html.access-colorblind-safe .badge-warning::before,
                html.access-colorblind-safe [class*="warning"]::before,
                html.access-colorblind-safe [class*="pending"]::before { content: "•"; }
                .access-voice-btn {
                    width: 38px;
                    height: 38px;
                    border-radius: 999px;
                    border: 1px solid rgba(255,255,255,0.18);
                    color: #fff;
                    background: rgba(0,0,0,0.34);
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    margin-left: 8px;
                    cursor: pointer;
                }
                .access-voice-btn.is-listening {
                    background: rgba(var(--main-home-accent-rgb), 0.72);
                    box-shadow: 0 0 20px rgba(var(--main-home-accent-rgb), 0.35);
                }
                .access-caption-log {
                    position: fixed;
                    left: 50%;
                    bottom: 26px;
                    transform: translateX(-50%);
                    z-index: 99999;
                    max-width: min(720px, calc(100vw - 32px));
                    padding: 14px 18px;
                    border-radius: 18px;
                    border: 1px solid rgba(255,255,255,0.18);
                    background: rgba(0,0,0,0.76);
                    color: #fff;
                    font-weight: 700;
                    line-height: 1.5;
                    box-shadow: 0 18px 54px rgba(0,0,0,0.35);
                    backdrop-filter: blur(18px);
                }
                .access-caption-log:empty { display: none; }
            `;
            document.head.appendChild(style);
        }

        function pauseDecorativeVideos() {
            document.querySelectorAll('.global-video-element, video[autoplay]').forEach(function (video) {
                try {
                    video.pause();
                    video.removeAttribute('autoplay');
                } catch (e) {
                    // Ignore browsers that block scripted video controls.
                }
            });
        }

        function improveIconButtonLabels() {
            document.querySelectorAll('button, a').forEach(function (el) {
                if (el.getAttribute('aria-label')) return;
                var text = (el.textContent || '').trim();
                if (text.length > 0) return;
                var title = el.getAttribute('title');
                if (title) {
                    el.setAttribute('aria-label', title);
                    return;
                }
                var icon = el.querySelector('i[class*="bx-"]');
                if (!icon) return;
                var iconClass = Array.from(icon.classList).find(function (cls) { return cls.indexOf('bx-') === 0; });
                if (iconClass) {
                    el.setAttribute('aria-label', iconClass.replace(/^bxs?-/, '').replace(/-/g, ' '));
                }
            });
        }

        function enableFormVoiceInput() {
            var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!Recognition) return;
            document.querySelectorAll('textarea, input[type="text"], input[type="search"], input[type="email"]').forEach(function (field) {
                if (field.dataset.voiceEnhanced || field.readOnly || field.disabled) return;
                field.dataset.voiceEnhanced = 'true';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'access-voice-btn';
                btn.innerHTML = '<i class="bx bx-microphone"></i>';
                btn.setAttribute('aria-label', 'Dictate into ' + (field.placeholder || field.name || 'field'));
                btn.title = 'Voice input';
                field.insertAdjacentElement('afterend', btn);
                var recognition = new Recognition();
                recognition.lang = (document.documentElement.lang || 'en').startsWith('fr') ? 'fr-FR' : ((document.documentElement.lang || 'en').startsWith('ar') ? 'ar-SA' : 'en-US');
                recognition.interimResults = true;
                recognition.maxAlternatives = 1;
                recognition.onstart = function () { btn.classList.add('is-listening'); };
                recognition.onend = function () { btn.classList.remove('is-listening'); };
                recognition.onresult = function (event) {
                    var transcript = Array.from(event.results).map(function (result) {
                        return result && result[0] ? result[0].transcript : '';
                    }).join(' ').replace(/\s+/g, ' ').trim();
                    if (!transcript) return;
                    field.value = field.tagName === 'TEXTAREA' && field.value ? field.value.replace(/\s*$/, ' ') + transcript : transcript;
                    field.dispatchEvent(new Event('input', { bubbles: true }));
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    field.focus();
                };
                btn.addEventListener('click', function () {
                    try { recognition.start(); } catch (e) { recognition.stop(); }
                });
            });
        }

        function enableAgentCaptions() {
            if (window.__syndicatiCaptionsReady) return;
            window.__syndicatiCaptionsReady = true;
            var caption = document.createElement('div');
            caption.className = 'access-caption-log';
            caption.setAttribute('role', 'status');
            caption.setAttribute('aria-live', 'polite');
            document.body.appendChild(caption);
            window.SyndicatiCaption = function (text) {
                if (!text) return;
                caption.textContent = text;
                window.clearTimeout(caption._hideTimer);
                caption._hideTimer = window.setTimeout(function () { caption.textContent = ''; }, 9000);
            };
            if ('speechSynthesis' in window) {
                var originalSpeak = window.speechSynthesis.speak.bind(window.speechSynthesis);
                window.speechSynthesis.speak = function (utterance) {
                    if (utterance && utterance.text) window.SyndicatiCaption(utterance.text);
                    return originalSpeak(utterance);
                };
            }
        }

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
