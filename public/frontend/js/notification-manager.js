/**
 * NotificationManager.js
 * Handles real-time notifications and UI updates for the "Obsidian Hub" navbar.
 */
window.NotificationManager = window.NotificationManager || class NotificationManager {
    constructor() {
        if (window.SyndicatiNotificationManagerLoaded) return;
        window.SyndicatiNotificationManagerLoaded = true;

        this.bell = document.getElementById('notifBell');
        this.hub = document.getElementById('notifHub');
        this.scrollContainer = document.getElementById('notifScroll');
        this.badge = document.getElementById('notifBadge');
        this.markAllBtn = document.getElementById('notifMarkAllRead');

        this.pollInterval = 30000; // 30 seconds
        this.isFetching = false;
        this.lastCount = -1;
        this.lastId = -1;

        if (this.bell && this.scrollContainer) {
            this.init();
        }
    }

    init() {
        this.fetchNotifications();
        this.startPolling();

        window.addEventListener('push-notification', (e) => {
            this.handleLocalPush(e.detail);
        });

        if (this.markAllBtn) {
            this.markAllBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.markAllRead();
            });
        }

        // Bridge legacy calls (message, title, type)
        window.showObsidianNotification = (message, title = 'Notification', type = 'SUCCESS') => {
            window.pushNotif(title, message, type?.toUpperCase() === 'SUCCESS' ? 'SUCCESS' : 'ERROR');
        };

        this.injectStyles();
    }

    async fetchNotifications() {
        if (document.hidden) return;
        if (this.isFetching) return;
        this.isFetching = true;

        try {
            const response = await fetch('/api/notifications');
            if (response.ok) {
                const data = await response.json();
                this.renderNotifications(data.notifications, data.unread_count);

                // Check for new notifications to toast
                if (data.notifications.length > 0) {
                    const latest = data.notifications[0];
                    if (this.lastId !== -1 && latest.id > this.lastId && !latest.is_read) {
                        this.showToast(latest.title || 'Notification', latest.content || '', latest.type || 'INFO');
                        this.pulseBell();
                    }
                    this.lastId = latest.id;
                }

                if (this.lastCount !== -1 && data.unread_count > this.lastCount) {
                    this.pulseBell();
                }
                this.lastCount = data.unread_count;
            }
        } catch (error) {
            console.error('[NotificationManager] Fetch failed:', error);
        } finally {
            this.isFetching = false;
        }
    }

    startPolling() {
        setInterval(() => this.fetchNotifications(), this.pollInterval);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) this.fetchNotifications();
        });
    }

    renderNotifications(notifications, unreadCount) {
        // Update Badge
        if (this.badge) {
            this.badge.textContent = unreadCount;
            this.badge.style.display = unreadCount > 0 ? 'flex' : 'none';
        }

        // Render List
        this.scrollContainer.innerHTML = '';
        if (notifications.length === 0) {
            this.scrollContainer.innerHTML = `
                <div class="text-center p-5 opacity-40">
                    <i class="bx bx-bell-off" style="font-size: 2.5rem; display: block; margin-bottom: 10px;"></i>
                    <p style="font-size: 0.85rem;">Aucune notification pour le moment</p>
                </div>`;
            return;
        }

        let lastGroup = null;
        notifications.forEach(notif => {
            const group = this.getDateGroupName(notif.created_at);
            if (group !== lastGroup) {
                const header = document.createElement('div');
                header.className = 'notif-group-label';
                header.textContent = group;
                this.scrollContainer.appendChild(header);
                lastGroup = group;
            }

            const item = document.createElement('div');
            item.className = `notif-item ${notif.is_read ? '' : 'unread'}`;

            item.innerHTML = `
                <div class="notif-item-icon">${this.getIconForType(notif.type)}</div>
                <div class="notif-item-content">
                    <p class="notif-item-title">${notif.title || 'Notification'}</p>
                    <p class="notif-item-text">${notif.content || ''}</p>
                    <p class="notif-item-time">${this.formatTime(notif.created_at)}</p>
                </div>
            `;

            item.onclick = (e) => {
                e.stopPropagation();
                this.markAsRead(notif.id, item);
            };
            this.scrollContainer.appendChild(item);
        });
    }

    async markAsRead(id, element) {
        try {
            const response = await fetch(`/api/notifications/${id}/read`, { method: 'POST' });
            if (response.ok) {
                element.classList.remove('unread');
                // Optional: instant decrement for snappy feel
                const current = parseInt(this.badge.textContent || 0) - 1;
                this.badge.textContent = Math.max(0, current);
                if (current <= 0) this.badge.style.display = 'none';

                this.fetchNotifications(); // Refresh list/count
            }
        } catch (error) {
            console.error('[NotificationManager] Mark read failed:', error);
        }
    }

    async markAllRead() {
        try {
            const response = await fetch('/api/notifications/read-all', { method: 'POST' });
            if (response.ok) {
                this.fetchNotifications();
                window.pushNotif('Génial!', 'Toutes les notifications ont été marquées comme lues', 'SUCCESS');
            }
        } catch (error) {
            console.error('[NotificationManager] Mark all read failed:', error);
        }
    }

    pulseBell() {
        if (!this.bell) return;
        this.bell.classList.add('bell-pulse');
        setTimeout(() => this.bell.classList.remove('bell-pulse'), 1500);
    }

    handleLocalPush(detail) {
        this.showToast(detail.title, detail.content, detail.type);
        this.pulseBell();
        this.fetchNotifications();
    }

    showToast(title, content, type) {
        const container = document.getElementById('notif-toast-container') || this.createToastContainer();
        const toast = document.createElement('div');
        toast.className = `notif-toast ${type.toLowerCase()}`;

        const icon = type === 'SUCCESS' ? 'bx-check-circle' : (type === 'ERROR' ? 'bx-error-circle' : 'bx-bell');

        toast.innerHTML = `
            <div class="notif-toast-icon"><i class="bx ${icon}"></i></div>
            <div class="notif-toast-body">
                <div class="notif-toast-title">${title}</div>
                <div class="notif-toast-text">${content}</div>
            </div>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 500);
        }, 4000);
    }

    createToastContainer() {
        const container = document.createElement('div');
        container.id = 'notif-toast-container';
        document.body.appendChild(container);
        return container;
    }

    getIconForType(type) {
        const icons = {
            'MESSAGE_NEW': '<i class="bx bx-message-square-dots"></i>',
            'TASK_ASSIGNED': '<i class="bx bx-task"></i>',
            'SUCCESS': '<i class="bx bx-check-circle"></i>',
            'ERROR': '<i class="bx bx-error-alt"></i>',
            'FRIEND_REQUEST': '<i class="bx bx-user-plus"></i>',
            'DELETE': '<i class="bx bx-trash"></i>',
            'RELATIONSHIP_ACCEPTED': '<i class="bx bx-user-check"></i>',
            'EVENT': '<i class="bx bx-calendar"></i>'
        };
        return icons[type] || '<i class="bx bx-bell"></i>';
    }

    getDateGroupName(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(today.getDate() - 1);

        if (date.toDateString() === today.toDateString()) return 'Aujourd\'hui';
        if (date.toDateString() === yesterday.toDateString()) return 'Hier';
        return date.toLocaleDateString([], { month: 'long', day: 'numeric', year: 'numeric' });
    }

    formatTime(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);

        if (diff < 60) return "À l'instant";
        if (diff < 3600) return `${Math.floor(diff / 60)}m`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    injectStyles() {
        if (document.getElementById('notif-manager-extra-styles')) return;
        const style = document.createElement('style');
        style.id = 'notif-manager-extra-styles';
        style.innerHTML = `
            @keyframes bell-pulse {
                0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(var(--main-home-accent-rgb, 107, 70, 193), 0.4); }
                50% { transform: scale(1.1); box-shadow: 0 0 20px 10px rgba(var(--main-home-accent-rgb, 107, 70, 193), 0); }
                100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(var(--main-home-accent-rgb, 107, 70, 193), 0); }
            }
            .bell-pulse {
                animation: bell-pulse 0.5s ease-in-out 3;
            }
            
            #notif-toast-container {
                position: fixed;
                top: 85px;
                right: 20px;
                z-index: 1000000;
                display: flex;
                flex-direction: column;
                gap: 10px;
                pointer-events: none;
            }

            .notif-toast {
                background: rgba(13, 13, 20, 0.95);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 16px;
                padding: 12px 20px;
                width: 320px;
                display: flex;
                align-items: center;
                gap: 15px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                animation: toast-slide-in 0.4s cubic-bezier(0.16, 1, 0.3, 1);
                pointer-events: auto;
            }

            .notif-toast.success { border-left: 4px solid #4ade80; }
            .notif-toast.error { border-left: 4px solid #ff4b5c; }

            .notif-toast-icon { font-size: 1.5rem; color: #fff; }
            .notif-toast-title { font-weight: 700; color: #fff; font-size: 0.95rem; }
            .notif-toast-text { color: rgba(255,255,255,0.6); font-size: 0.85rem; }

            @keyframes toast-slide-in {
                from { transform: translateX(50px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            .notif-toast.fade-out {
                transform: translateX(50px);
                opacity: 0;
                transition: all 0.5s ease;
            }
        `;
        document.head.appendChild(style);
    }
};

// Global scope helper
window.pushNotif = function (title, content, type = 'SUCCESS') {
    window.dispatchEvent(new CustomEvent('push-notification', {
        detail: { title, content, type }
    }));
};

// Initialize — works whether the script is deferred (DOMContentLoaded not yet fired)
// or dynamically injected after the DOM is already ready.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.notificationManager = new window.NotificationManager();
    });
} else {
    // DOM already ready (script was injected dynamically)
    window.notificationManager = new window.NotificationManager();
}
