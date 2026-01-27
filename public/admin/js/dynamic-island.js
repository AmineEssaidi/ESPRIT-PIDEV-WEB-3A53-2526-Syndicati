// Dynamic Island Notification Logic for Admin (copied from frontend)


function initDynamicIsland() {
    const dynamicIsland = document.getElementById('dynamicIsland');
    if (!dynamicIsland) return;
    
    let isExpanded = false;
    let isShowingNotification = false;
    let isShowingNotificationsList = false;
    let expandTimeout;
    let notificationTimeout;
    let lang = localStorage.getItem('lang') || 'fr';
    let translations = window.translations || {};
    let notificationsList = [
        { title: translations[lang]["Bienvenue !"] || "Bienvenue !", message: translations[lang]["Bienvenue sur notre plateforme incroyable"] || "Bienvenue sur notre plateforme incroyable", icon: "👋", time: "il y a 2 min" },
        { title: translations[lang]["Nouveau message"] || "Nouveau message", message: translations[lang]["Vous avez un nouveau message de Sarah"] || "Vous avez un nouveau message de Sarah", icon: "💬", time: "il y a 5 min" },
        { title: translations[lang]["Mise à jour système"] || "Mise à jour système", message: translations[lang]["Maintenance système prévue pour ce soir"] || "Maintenance système prévue pour ce soir", icon: "⚙️", time: "il y a 1 heure" },
        { title: translations[lang]["Paiement reçu"] || "Paiement reçu", message: translations[lang]["Paiement de 299 $ traité"] || "Paiement de 299 $ traité", icon: "💰", time: "il y a 2 heures" },
        { title: translations[lang]["Nouvelle fonctionnalité"] || "Nouvelle fonctionnalité", message: translations[lang]["Découvrez les nouvelles fonctionnalités du tableau de bord"] || "Découvrez les nouvelles fonctionnalités du tableau de bord", icon: "✨", time: "il y a 3 heures" },
        { title: translations[lang]["Alerte sécurité"] || "Alerte sécurité", message: translations[lang]["Nouvelle connexion détectée depuis Chrome"] || "Nouvelle connexion détectée depuis Chrome", icon: "🔒", time: "il y a 4 heures" },
        { title: translations[lang]["Rappel de réunion"] || "Rappel de réunion", message: translations[lang]["Réunion d'équipe dans 30 minutes"] || "Réunion d'équipe dans 30 minutes", icon: "📅", time: "il y a 5 heures" },
        { title: translations[lang]["Fichier téléchargé"] || "Fichier téléchargé", message: translations[lang]["Document.pdf a été téléchargé"] || "Document.pdf a été téléchargé", icon: "📄", time: "il y a 6 heures" }
    ];
    let clickCount = 0;
    // (rest of the function is identical to frontend, see previous context)
    // ... (copy full function body from frontend)

    // Initialize badge count and dynamic content
    const badge = dynamicIsland.querySelector('.main-home-notification-badge');
    if (badge) {
        badge.textContent = notificationsList.length;
    }
    const dynamicText = dynamicIsland.querySelector('.main-home-dynamic-text');
    const dynamicSubtext = dynamicIsland.querySelector('.main-home-dynamic-subtext');
    if (dynamicText && dynamicSubtext) {
        dynamicText.textContent = notificationsList.length;
        dynamicSubtext.textContent = notificationsList.length === 1 ? 'notification' : 'notifications';
    }
    // Close button handler for expanded island
    const closeBtn = document.querySelector('.main-home-dynamic-close-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            collapseIsland();
        });
    }
    dynamicIsland.addEventListener('click', function() {
        if (isShowingNotification) {
            hideNotificationPopup();
        } else if (isShowingNotificationsList) {
            hideNotificationsList();
        } else if (notificationsList.length > 0) {
            const existingOverlay = document.getElementById('notificationsListOverlay');
            if (existingOverlay) {
                hideNotificationsList();
            } else {
                showNotificationsList();
            }
        } else if (isExpanded) {
            collapseIsland();
        } else {
            expandIsland();
        }
    });
    dynamicIsland.addEventListener('mouseenter', function() {
        if (!isExpanded && !isShowingNotification && !isShowingNotificationsList && notificationsList.length === 0) {
            clearTimeout(expandTimeout);
            expandTimeout = setTimeout(() => {
                expandIsland();
            }, 500);
        }
    });
    dynamicIsland.addEventListener('mouseleave', function() {
        clearTimeout(expandTimeout);
        if (isExpanded && !isShowingNotification && !isShowingNotificationsList && notificationsList.length === 0) {
            setTimeout(() => {
                collapseIsland();
            }, 1000);
        }
    });
    function expandIsland() {
        if (isExpanded || isShowingNotification || isShowingNotificationsList) return;
        isExpanded = true;
        dynamicIsland.classList.add('expanded');
        const dynamicContent = dynamicIsland.querySelector('.main-home-dynamic-content');
        if (dynamicContent) {
            dynamicContent.style.display = 'block';
        }
        const dynamicText = dynamicIsland.querySelector('.main-home-dynamic-text');
        const dynamicSubtext = dynamicIsland.querySelector('.main-home-dynamic-subtext');
        if (dynamicText && dynamicSubtext) {
            dynamicText.textContent = 'Notifications';
            dynamicSubtext.textContent = `${notificationsList.length} nouveaux messages`;
        }
    }
    function collapseIsland() {
        if (!isExpanded || isShowingNotification || isShowingNotificationsList) return;
        isExpanded = false;
        dynamicIsland.classList.remove('expanded');
        const dynamicContent = dynamicIsland.querySelector('.main-home-dynamic-content');
        if (dynamicContent) {
            dynamicContent.style.display = 'none';
        }
        if (closeBtn) {
            closeBtn.classList.remove('show');
        }
    }
    function showNotificationsList() {
        if (isShowingNotificationsList || notificationsList.length === 0) return;
        const existingOverlay = document.getElementById('notificationsListOverlay');
        if (existingOverlay) {
            existingOverlay.remove();
        }
        if (!isExpanded) {
            expandIsland();
            setTimeout(() => {
                showBigPopup();
            }, 400);
            return;
        }
        showBigPopup();
    }
    function showBigPopup() {
        const currentRect = dynamicIsland.getBoundingClientRect();
        const notificationsListElement = document.createElement('div');
        notificationsListElement.className = 'main-home-dynamic-island notifications-list';
        notificationsListElement.id = 'notificationsListOverlay';
        notificationsListElement.style.position = 'fixed';
        notificationsListElement.style.top = `${currentRect.top}px`;
        notificationsListElement.style.left = `${currentRect.left}px`;
        notificationsListElement.style.right = 'auto';
        notificationsListElement.style.transform = 'none';
        notificationsListElement.style.zIndex = '1003';
        notificationsListElement.style.width = `${currentRect.width}px`;
        notificationsListElement.style.height = '200px';
        notificationsListElement.style.opacity = '0';
        notificationsListElement.style.transition = 'opacity 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94)';
        notificationsListElement.style.isolation = 'isolate';
        notificationsListElement.innerHTML = `
            <div class="main-home-notifications-container" style="display: flex; flex-direction: column; height: 100%;">
                <div class="main-home-notifications-header" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                    <span style="color: white; font-size: 0.8rem; font-weight: 600;">Notifications</span>
                    <button class="main-home-close-notifications-btn" style="background: none; border: none; color: rgba(255, 255, 255, 0.7); cursor: pointer; font-size: 1rem; padding: 4px; border-radius: 4px; transition: all 0.2s ease;" onmouseover="this.style.background='rgba(255, 255, 255, 0.1)'" onmouseout="this.style.background='none'">✕</button>
                </div>
                <div class="main-home-notifications-scroll" style="flex: 1; overflow-y: auto; overflow-x: hidden; scrollbar-width: none; -ms-overflow-style: none; padding: 8px 12px;">
                    <!-- Notifications will be dynamically added here -->
                </div>
            </div>
        `;
        document.body.appendChild(notificationsListElement);
        const closeButton = notificationsListElement.querySelector('.main-home-close-notifications-btn');
        if (closeButton) {
            closeButton.addEventListener('click', function(e) {
                e.stopPropagation();
                hideNotificationsList();
            });
        }
        const scrollContainer = notificationsListElement.querySelector('.main-home-notifications-scroll');
        if (scrollContainer) {
            scrollContainer.innerHTML = '';
            notificationsList.forEach((notification, index) => {
                const notificationItem = document.createElement('div');
                notificationItem.className = 'main-home-notification-item';
                notificationItem.innerHTML = `
                    <div class="main-home-notification-item-icon">${notification.icon}</div>
                    <div class="main-home-notification-item-text">
                        <p class="main-home-notification-item-title">${notification.title}</p>
                        <p class="main-home-notification-item-message">${notification.message}</p>
                    </div>
                    <div class="main-home-notification-item-time">${notification.time}</div>
                `;
                scrollContainer.appendChild(notificationItem);
            });
        }
        isShowingNotificationsList = true;
        if (closeBtn) {
            closeBtn.classList.remove('show');
        }
        requestAnimationFrame(() => {
            notificationsListElement.style.opacity = '1';
        });
    }
    function hideNotificationsList() {
        if (!isShowingNotificationsList) return;
        const overlayElement = document.getElementById('notificationsListOverlay');
        if (overlayElement) {
            overlayElement.style.opacity = '0';
            setTimeout(() => {
                if (overlayElement.parentNode) {
                    overlayElement.remove();
                }
            }, 400);
        }
        isShowingNotificationsList = false;
        if (closeBtn) {
            closeBtn.classList.add('show');
        }
    }
    function renderNotificationsList() {
        const scrollContainer = dynamicIsland.querySelector('.main-home-notifications-scroll');
        if (!scrollContainer) return;
        scrollContainer.innerHTML = '';
        notificationsList.forEach((notification, index) => {
            const notificationItem = document.createElement('div');
            notificationItem.className = 'main-home-notification-item';
            notificationItem.innerHTML = `
                <div class="main-home-notification-item-icon">${notification.icon}</div>
                <div class="main-home-notification-item-text">
                    <p class="main-home-notification-item-title">${notification.title}</p>
                    <p class="main-home-notification-item-message">${notification.message}</p>
                </div>
                <div class="main-home-notification-item-time">${notification.time}</div>
            `;
            scrollContainer.appendChild(notificationItem);
        });
    }
    function showNotificationPopup(title, message, icon = '📱') {
        if (isShowingNotification) return;
        isShowingNotification = true;
        isExpanded = false;
        dynamicIsland.classList.remove('expanded');
        dynamicIsland.classList.add('notification-popup');
        const dynamicContent = dynamicIsland.querySelector('.main-home-dynamic-content');
        if (dynamicContent) {
            dynamicContent.style.display = 'none';
        }
        const popupContent = dynamicIsland.querySelector('.main-home-notification-popup-content');
        const popupIcon = dynamicIsland.querySelector('.main-home-notification-popup-icon');
        const popupTitle = dynamicIsland.querySelector('.main-home-notification-popup-title');
        const popupMessage = dynamicIsland.querySelector('.main-home-notification-popup-message');
        if (popupContent && popupIcon && popupTitle && popupMessage) {
            popupContent.style.display = 'flex';
            popupIcon.textContent = icon;
            if (notificationsList.length === 1) {
                popupTitle.textContent = title;
                popupMessage.textContent = message;
            } else {
                popupTitle.textContent = `${notificationsList.length} Notifications`;
                popupMessage.textContent = 'Cliquez à nouveau pour tout voir';
            }
        }
        if (notificationsList.length === 1) {
            notificationTimeout = setTimeout(() => {
                hideNotificationPopup();
            }, 4000);
        }
    }
    function hideNotificationPopup() {
        if (!isShowingNotification) return;
        isShowingNotification = false;
        clearTimeout(notificationTimeout);
        dynamicIsland.classList.remove('notification-popup');
        const popupContent = dynamicIsland.querySelector('.main-home-notification-popup-content');
        if (popupContent) {
            popupContent.style.display = 'none';
        }
        const dynamicContent = dynamicIsland.querySelector('.main-home-dynamic-content');
        if (dynamicContent) {
            dynamicContent.style.display = 'block';
        }
    }
    // Simulate incoming notifications (optional, not used in admin)
    // Expose functions globally for external use
    window.DynamicIsland = {
        showNotification: showNotificationPopup,
        hideNotification: hideNotificationPopup,
        expand: expandIsland,
        collapse: collapseIsland
    };
    console.log('🏝️ Dynamic Island initialized (admin)');
}

document.addEventListener('DOMContentLoaded', function() {
    initDynamicIsland();
});
