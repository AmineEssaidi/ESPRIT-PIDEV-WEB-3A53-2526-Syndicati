/**
 * Address Bar Rewrite Script
 * Changes the visual appearance of the address bar to show www.syndicati.tn
 * ONLY for external LAN connections.
 */

(function () {
    'use strict';

    const currentHost = window.location.hostname;
    const customDomain = 'www.syndicati.tn';

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

            // Purely visual change - does not trigger navigation
            window.history.replaceState(null, '', fakeUrl);

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

    // Re-apply on state changes (back/forward)
    window.addEventListener('popstate', rewriteAddressBar);

    // Periodic check to ensure it stays (some SPA-like behaviors might overwrite it)
    setInterval(rewriteAddressBar, 2000);

})();
