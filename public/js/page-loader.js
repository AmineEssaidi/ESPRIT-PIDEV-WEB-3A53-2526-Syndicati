/**
 * Page Loader Script
 * Shows an animated loading screen when navigating between pages
 */

(function () {
    'use strict';

    const loader = document.getElementById('pageLoader');
    let sessionHeartbeatTimer = null;

    if (!loader) return;
    let cinematicTimers = [];

    function clearCinematicTimers() {
        cinematicTimers.forEach(clearTimeout);
        cinematicTimers = [];
    }

    function ensureCinematicShell() {
        let content = loader.querySelector('.loader-content');
        if (!content) {
            content = document.createElement('div');
            content.className = 'loader-content';
            loader.appendChild(content);
        }
        if (content.querySelector('.loader-cinematic-title')) return content;
        content.innerHTML = `
            <div class="loader-cinematic-orbit" aria-hidden="true"></div>
            <div class="loader-cinematic-title">Syndicati</div>
            <div class="loader-cinematic-subtitle">Preparing workspace...</div>
            <div class="loader-cinematic-track"><div class="loader-cinematic-fill"></div></div>
        `;
        return content;
    }

    // Function to show the loader immediately
    function showLoader(options = {}) {
        clearCinematicTimers();
        if (options.cinematic) {
            loader.classList.add('cinematic');
            const content = ensureCinematicShell();
            const subtitle = content.querySelector('.loader-cinematic-subtitle');
            const fill = content.querySelector('.loader-cinematic-fill');
            const steps = options.steps || ['Recovering session...', 'Syncing profile...', 'Warming workspace...', 'Ready.'];
            steps.forEach((step, index) => {
                cinematicTimers.push(setTimeout(() => {
                    if (subtitle) subtitle.textContent = step;
                    if (fill) fill.style.width = `${Math.min(96, 22 + ((index + 1) / steps.length) * 74)}%`;
                }, index * (options.stepDelay || 420)));
            });
        }
        loader.classList.remove('fade-out');
        loader.classList.add('active');
        // Force browser to paint immediately
        loader.offsetHeight;
    }

    // Function to hide the loader
    function hideLoader() {
        clearCinematicTimers();
        loader.classList.add('fade-out');
        setTimeout(() => {
            loader.classList.remove('active', 'fade-out');
        }, 300);
    }

    // Show the loader for normal internal link clicks without delaying or hijacking navigation.
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a');

        if (!link) return;

        const href = link.getAttribute('href');

        // Skip if no href
        if (!href) return;

        // Force loader for cross-section links (Dashboard, Main Home, etc.)
        const forceLoader = link.hasAttribute('data-page-loader');

        if (!forceLoader) {
            // Skip external links, anchors, javascript:, mailto:, tel:, etc.
            if (href.startsWith('#') ||
                href.startsWith('javascript:') ||
                href.startsWith('mailto:') ||
                href.startsWith('tel:') ||
                (href.startsWith('http://') && !href.includes(window.location.host)) ||
                (href.startsWith('https://') && !href.includes(window.location.host))) {
                return;
            }
        } else {
            // data-page-loader: only skip anchors and empty
            if (href.startsWith('#') || href === '') return;
        }

        // Skip links that open in new tab
        if (link.target === '_blank') return;

        // Skip links with download attribute
        if (link.hasAttribute('download')) return;

        // Skip if modifier keys are pressed (for opening in new tab)
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;

        // Skip dropdown items that don't navigate
        if (link.classList.contains('dropdown-toggle')) return;

        showLoader({
            cinematic: true,
            steps: ['Loading destination...', 'Almost there...'],
            stepDelay: 160
        });
    }, true);

    window.HorizonCinematic = {
        show: (options = {}) => showLoader(Object.assign({ cinematic: true }, options)),
        hide: hideLoader,
        navigate: function (url, options = {}) {
            showLoader(Object.assign({ cinematic: true }, options));
            const delay = options.delay ?? 0;
            setTimeout(() => { window.location.href = url; }, delay);
        },
        recover: async function (options = {}) {
            showLoader(Object.assign({
                cinematic: true,
                steps: ['Recovering session...', 'Checking identity...', 'Syncing live data...', 'Opening workspace...']
            }, options));
            try {
                await fetch('/api/session/status', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });
            } catch (e) {}
            if (options.redirect) {
                setTimeout(() => { window.location.href = options.redirect; }, options.delay || 650);
            } else {
                setTimeout(hideLoader, options.delay || 500);
            }
        }
    };

    // Handle form submissions
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || form.nodeName !== 'FORM') return;

        // Skip forms that open in new tab
        if (form.target === '_blank') return;

        // Skip AJAX forms
        if (form.hasAttribute('data-ajax')) return;

        // Skip loader for sign-in (shows TOTP popup without full-page reload feel)
        if (form.hasAttribute('data-no-page-loader')) return;
        if (form.id === 'signin-form') return;
        if (form.classList && form.classList.contains('main-home-auth-form')) return;
        try {
            const action = (form.getAttribute('action') || '').toLowerCase();
            if (action.indexOf('sign-in') !== -1) return;
        } catch (err) { }

        showLoader({
            cinematic: true,
            steps: ['Submitting securely...', 'Keeping session alive...', 'Refreshing data...'],
            stepDelay: 180
        });
    }, true);

    // Handle browser back/forward buttons
    window.addEventListener('pageshow', function (e) {
        // Hide loader when page is shown from cache
        if (e.persisted) {
            hideLoader();
        }
    });

    // Hide loader when page fully loads (for initial page load)
    window.addEventListener('load', function () {
        hideLoader();
    });

    if (document.body && document.body.dataset.userInfo === 'true') {
        const pingSession = () => {
            if (document.hidden || navigator.onLine === false) return;
            fetch('/api/session/status', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            }).catch(() => {});
        };

        window.setTimeout(() => {
            pingSession();
        }, 1200);
        sessionHeartbeatTimer = window.setInterval(pingSession, 240000);
        window.addEventListener('beforeunload', () => {
            if (sessionHeartbeatTimer) {
                window.clearInterval(sessionHeartbeatTimer);
                sessionHeartbeatTimer = null;
            }
        }, { once: true });
    }

    // Also hide on DOMContentLoaded as backup
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            // Small delay to ensure smooth transition
            setTimeout(hideLoader, 100);
        });
    } else {
        setTimeout(hideLoader, 100);
    }

    // Ultimate Safety Timeout: Force hide loader after 5 seconds no matter what
    setTimeout(hideLoader, 5000);

})();
