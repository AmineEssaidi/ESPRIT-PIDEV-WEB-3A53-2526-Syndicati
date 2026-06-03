/**
 * Address Bar Rewrite Script
 * Changes the visual appearance of the address bar to show www.syndicati.tn
 * ONLY for external LAN connections.
 */

(function () {
    'use strict';

    const currentHost = window.location.hostname;
    const customDomain = 'www.syndicati.tn';
    let lastAppliedUrl = null;

    // EXCLUSION RULE: Don't change for localhost development
    if (currentHost === 'localhost' || currentHost === '127.0.0.1' || currentHost === '::1') {
        return;
    }

    // Function to rewrite the address bar visually
    function rewriteAddressBar() {
        try {
            // Reconstruct the URL using the custom domain
            // NOTE: We keep the current protocol (http/https)
            const fakeUrl = window.location.protocol + '//' + customDomain + window.location.pathname + window.location.search + window.location.hash;
            if (fakeUrl === lastAppliedUrl) return;

            // Purely visual change - does not trigger navigation
            window.history.replaceState(null, '', fakeUrl);
            lastAppliedUrl = fakeUrl;

            // Update title if not already updated
            if (!document.title.includes(customDomain)) {
                // document.title = document.title + ' | ' + customDomain;
            }
        } catch (error) {
            // Silent fail to not disturb the user
        }
    }

    // Initialize
    rewriteAddressBar();

    // Re-apply on state changes (back/forward/hash navigation)
    window.addEventListener('popstate', rewriteAddressBar);
    window.addEventListener('hashchange', rewriteAddressBar);
    window.addEventListener('pageshow', rewriteAddressBar);

    // Re-apply when history is mutated by in-app navigation code.
    const originalPushState = window.history.pushState;
    const originalReplaceState = window.history.replaceState;

    window.history.pushState = function () {
        const result = originalPushState.apply(this, arguments);
        rewriteAddressBar();
        return result;
    };

    window.history.replaceState = function () {
        const result = originalReplaceState.apply(this, arguments);
        lastAppliedUrl = null;
        rewriteAddressBar();
        return result;
    };

})();
