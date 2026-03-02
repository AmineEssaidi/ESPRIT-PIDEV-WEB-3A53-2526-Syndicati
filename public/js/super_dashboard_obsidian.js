/**
 * Obsidian Black Glass UI - Super Dashboard Logic
 * Handles interactive elements, toggles, and live updates.
 */

document.addEventListener('DOMContentLoaded', () => {
    initPageMatrix();
    initHeartbeat();
    initActivityStream();
});

/**
 * Initialize Page Management Matrix
 */
function initPageMatrix() {
    const toggles = document.querySelectorAll('.page-toggle-input');

    toggles.forEach(toggle => {
        toggle.addEventListener('change', async (e) => {
            const pageId = e.target.dataset.pageId;
            const isOnline = e.target.checked;
            const card = e.target.closest('.page-glass-card');

            // Add loading state
            card.style.opacity = '0.7';
            card.style.pointerEvents = 'none';

            try {
                // In a real application, you would make an AJAX call here
                // console.log(`Toggling ${pageId} to ${isOnline ? 'online' : 'offline'}`);

                // For demonstration purposes, we'll simulate a 500ms API call
                await new Promise(resolve => setTimeout(resolve, 500));

                // Show notification (using the global obsidian notification if available)
                if (window.showObsidianNotification) {
                    window.showObsidianNotification(
                        'System Update',
                        `${pageId.replace('_', ' ').toUpperCase()} is now ${isOnline ? 'ONLINE' : 'OFFLINE'}.`,
                        'success'
                    );
                } else {
                    console.info(`${pageId} is now ${isOnline ? 'online' : 'offline'}`);
                }
            } catch (error) {
                console.error('Failed to update page status:', error);
                // Revert toggle on error
                e.target.checked = !isOnline;
            } finally {
                card.style.opacity = '1';
                card.style.pointerEvents = 'all';
            }
        });
    });
}

/**
 * Initialize Heartbeat Dynamic Elements
 */
function initHeartbeat() {
    // Live Clock
    const clockElement = document.getElementById('obsidian-clock');
    if (clockElement) {
        setInterval(() => {
            const now = new Date();
            clockElement.textContent = now.toLocaleTimeString();
        }, 1000);
    }

    // Randomize some stats for "live" feel (optional/demo)
    const liveStats = document.querySelectorAll('.live-stat-pulse');
    liveStats.forEach(stat => {
        setInterval(() => {
            if (Math.random() > 0.8) {
                stat.style.color = 'var(--obsidian-accent-secondary)';
                setTimeout(() => stat.style.color = '', 300);
            }
        }, 3000);
    });
}

/**
 * Initialize Activity Stream interactions
 */
function initActivityStream() {
    // Add simple hover effects or click-to-detail logic if needed
    const activities = document.querySelectorAll('.activity-item');
    activities.forEach(item => {
        item.addEventListener('click', () => {
            // Logic for showing details
            console.log('Activity clicked:', item.querySelector('.activity-user').textContent);
        });
    });
}
