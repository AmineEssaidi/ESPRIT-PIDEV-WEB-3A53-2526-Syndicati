/**
 * activity-tracker.js
 * Captures user interactions (clicks, etc.) and sends them to the server-side log.
 */
(function () {
    'use strict';

    const LOG_ENDPOINT = '/api/log/event';

    let lastLogTime = 0;
    const DEBOUNCE_MS = 150;

    function sendLog(eventType, entityType, entityId = null, metadata = {}) {
        const now = Date.now();
        if (now - lastLogTime < DEBOUNCE_MS) return;
        lastLogTime = now;

        fetch(LOG_ENDPOINT, {
            method: 'POST',
            keepalive: true, // Crucial for logging on navigation/unload
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                event_type: eventType,
                entity_type: entityType,
                entity_id: entityId,
                metadata: {
                    ...metadata,
                    timestamp: new Date().toISOString(),
                    path: window.location.pathname,
                    title: document.title
                }
            })
        }).catch(err => console.error('[ActivityTracker] Log failed:', err));
    }

    // Capture Clicks
    document.addEventListener('click', (e) => {
        const target = e.target.closest('a, button, [data-log], input[type="submit"]');
        if (!target) return;

        // SKIP logging if it's a submit button for a form that has data-ajax="true"
        // because evenement.js or other scripts will already handle the feedback/logging
        if (target.type === 'submit' && target.form && target.form.dataset.ajax === 'true') {
            return;
        }

        const info = {
            tag: target.tagName,
            id: target.id || null,
            classes: target.className || null,
            text: (target.innerText || target.value || '').substring(0, 50).trim(),
            dataLog: target.getAttribute('data-log') || null
        };

        sendLog('UI_CLICK', 'UI_ELEMENT', null, info);
    }, true);

    // Initial page load (already handled by server-side Kernel listener, 
    // but we can add secondary client-side context here if needed)
    console.log('[ActivityTracker] Initialized');

})();
