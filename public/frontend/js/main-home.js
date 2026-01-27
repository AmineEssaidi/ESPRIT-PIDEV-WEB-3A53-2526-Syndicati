// Main Home Frontend JavaScript - PiDev

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the main home page
    initMainHome();

    // Initialize theme toggle (for settings and main island)
    initThemeToggle();
    // Robust event delegation for header theme toggle (island)
    document.body.addEventListener('click', function(e) {
        const toggle = e.target.closest('#theme-toggle');
        if (toggle) {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            // Animate the toggle thumb for feedback only (not position)
            const thumb = toggle.querySelector('.main-home-toggle-thumb');
            if (thumb) {
                thumb.classList.add('main-home-toggle-thumb-anim');
                setTimeout(() => {
                    thumb.classList.remove('main-home-toggle-thumb-anim');
                }, 180);
            }
            e.preventDefault();
        }
    });
    // Language switcher logic (similar to theme)
    const langSwitcher = document.querySelectorAll('.main-home-language-switch, .language-switch');
    langSwitcher.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const newLang = btn.dataset.lang;
            if (newLang && window.setLanguage) {
                window.setLanguage(newLang);
            }
        });
    });

    // Initialize interactive elements
    initInteractiveElements();

    // Initialize animations
    initAnimations();

    // Initialize Dynamic Island
    initDynamicIsland();

    // Only initialize SmartTranslate if not already initialized
    if (!window._smartTranslateObserver && window.SmartTranslate) {
        let lang = localStorage.getItem('lang') || 'fr';
        window.SmartTranslate.init(lang);
    }
});

// Initialize the main home page
function initMainHome() {
    console.log('🏠 Main Home initialized');
    
    // Set up heart animation
    setupHeartAnimation();

    // Set up audio slider for video
    const video = document.getElementById('mainVideoPlayer');
    const audioSlider = document.getElementById('mainVideoAudioSlider');
    if (video && audioSlider) {
        video.muted = false;
        video.volume = audioSlider.value;
        const percentLabel = document.getElementById('mainVideoAudioPercent');
        function updatePercent() {
            if (percentLabel) {
                percentLabel.textContent = Math.round(audioSlider.value * 100) + '%';
            }
        }
        audioSlider.addEventListener('input', function() {
            video.volume = audioSlider.value;
            updatePercent();
        });
        updatePercent();
        // Always try to play video on page load
        function tryAutoplay() {
            const playPromise = video.play();
            if (playPromise !== undefined) {
                playPromise.catch(() => {
                    // If autoplay is blocked, try again after user interaction
                    document.body.addEventListener('click', function autoPlayOnce() {
                        video.play();
                        document.body.removeEventListener('click', autoPlayOnce);
                    });
                });
            }
        }
        tryAutoplay();
        // Restore play/pause toggle on click
        video.addEventListener('click', function() {
            if (video.paused) {
                video.play();
            } else {
                video.pause();
            }
        });
    }
}

// Set up heart animation
function setupHeartAnimation() {
    console.log('❤️ Heart animation initialized');
    // The heart animation is handled by CSS, this function is here for future enhancements
}

// Show notification
function showNotification(title, message) {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'notification';
    notification.innerHTML = `
        <div class="notification-content">
            <h4>${title}</h4>
            <p>${message}</p>
        </div>
    `;
    
    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 60px;
        right: 20px;
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 12px;
        padding: 1rem;
        max-width: 300px;
        z-index: 1000;
        animation: slideIn 0.3s ease-out;
    `;
    
    // Add to page
    document.body.appendChild(notification);
            const pill = document.getElementById('mainHomeAudioPill');
            if (pill) {
                pill.style.display = 'flex';
            }
}

// Initialize theme toggle
function initThemeToggle() {
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update toggle icon
            const icon = themeToggle.querySelector('i');
            if (icon) {
                icon.textContent = newTheme === 'light' ? '🌙' : '☀️';
            }
        });
        
        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        const icon = themeToggle.querySelector('i');
        if (icon) {
            icon.textContent = savedTheme === 'light' ? '🌙' : '☀️';
        }
    }
}

// Initialize interactive elements
function initInteractiveElements() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            // Implement search functionality here
            console.log('🔍 Searching for:', query);
        });
    }
    
    // Sidebar navigation
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Remove active class from all links
            navLinks.forEach(l => l.classList.remove('active'));
            
            // Add active class to clicked link
            link.classList.add('active');
            
            // Handle navigation
            const route = link.dataset.route;
            if (route) {
                console.log('🧭 Navigating to:', route);
                // Implement navigation logic here
            }
        });
    });
}

// Initialize animations
function initAnimations() {
    // Add entrance animations
    const elements = document.querySelectorAll('.welcome-card, .heart-section');
    
    elements.forEach((element, index) => {
        element.style.opacity = '0';
        element.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            element.style.transition = 'all 0.6s ease-out';
            element.style.opacity = '1';
            element.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
        
        .notification-content h4 {
            margin: 0 0 0.5rem 0;
            font-size: 1rem;
            font-weight: var(--font-weight-semibold);
            color: var(--text);
        }
        
        .notification-content p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--muted);
        }
    `;
    document.head.appendChild(style);
}

// Utility functions
function formatDate(date) {
    return date.toLocaleDateString('en-US', {
        weekday: 'short',
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

function generateRandomValue(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

// Dynamic Island functionality
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
            e.stopPropagation(); // Prevent triggering the main click handler
            collapseIsland();
        });
    }
    
    // Click handler for Dynamic Island
    dynamicIsland.addEventListener('click', function() {
        if (isShowingNotification) {
            hideNotificationPopup();
        } else if (isShowingNotificationsList) {
            hideNotificationsList();
        } else if (notificationsList.length > 0) {
            // Check if there's already a notifications list overlay
            const existingOverlay = document.getElementById('notificationsListOverlay');
            if (existingOverlay) {
                // If overlay exists, just hide it
                hideNotificationsList();
            } else {
                // Always show the big popup when there are notifications
                showNotificationsList();
            }
        } else if (isExpanded) {
            collapseIsland();
        } else {
            expandIsland();
        }
    });
    
    // Hover handlers
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
    
    // Expand Dynamic Island
    function expandIsland() {
        if (isExpanded || isShowingNotification || isShowingNotificationsList) return;
        
        isExpanded = true;
        dynamicIsland.classList.add('expanded');
        
        // Show the dynamic content
        const dynamicContent = dynamicIsland.querySelector('.main-home-dynamic-content');
        if (dynamicContent) {
            dynamicContent.style.display = 'block';
        }
        
        // Update content
        const dynamicText = dynamicIsland.querySelector('.main-home-dynamic-text');
        const dynamicSubtext = dynamicIsland.querySelector('.main-home-dynamic-subtext');
        
        if (dynamicText && dynamicSubtext) {
            dynamicText.textContent = 'Notifications';
            dynamicSubtext.textContent = `${notificationsList.length} nouveaux messages`;
        }
    }
    
    // Collapse Dynamic Island
    function collapseIsland() {
        if (!isExpanded || isShowingNotification || isShowingNotificationsList) return;
        
        isExpanded = false;
        dynamicIsland.classList.remove('expanded');
        
        // Hide the dynamic content
        const dynamicContent = dynamicIsland.querySelector('.main-home-dynamic-content');
        if (dynamicContent) {
            dynamicContent.style.display = 'none';
        }
        
        // Hide the animated close button
        if (closeBtn) {
            closeBtn.classList.remove('show');
        }
    }
    
    // Show notifications list
    function showNotificationsList() {
        if (isShowingNotificationsList || notificationsList.length === 0) return;
        
        // Check if there's already an overlay and remove it first
        const existingOverlay = document.getElementById('notificationsListOverlay');
        if (existingOverlay) {
            existingOverlay.remove();
        }
        
        // First expand the island if it's not already expanded
        if (!isExpanded) {
            expandIsland();
            // Wait for expansion animation to complete before showing big popup
            setTimeout(() => {
                showBigPopup();
            }, 400); // Match the expansion animation duration
            return; // Exit early to prevent immediate execution
        }
        
        // If already expanded, show big popup immediately
        showBigPopup();
    }
    
    // Helper function to show the big popup
    function showBigPopup() {
        // Get the current position of the expanded Dynamic Island
        const currentRect = dynamicIsland.getBoundingClientRect();
    
        // Create a new element for the notifications list that appears on top
        const notificationsListElement = document.createElement('div');
        notificationsListElement.className = 'main-home-dynamic-island notifications-list';
        notificationsListElement.id = 'notificationsListOverlay';
        
        // Position it in the EXACT same location as the expanded island
        notificationsListElement.style.position = 'fixed';
        notificationsListElement.style.top = `${currentRect.top}px`;
        notificationsListElement.style.left = `${currentRect.left}px`;
        notificationsListElement.style.right = 'auto';
        notificationsListElement.style.transform = 'none';
        notificationsListElement.style.zIndex = '1003'; // Higher than the expanded island
        notificationsListElement.style.width = `${currentRect.width}px`; // Same width as expanded island
        notificationsListElement.style.height = '200px'; // Start with full height immediately
        notificationsListElement.style.opacity = '0'; // Start invisible
        notificationsListElement.style.transition = 'opacity 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94)'; // Only animate opacity
        notificationsListElement.style.isolation = 'isolate'; // Create new stacking context
        
        // Add the notifications container with close button
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
        
        // Add to the page
        document.body.appendChild(notificationsListElement);
        
        // Add click handler for close button
        const closeButton = notificationsListElement.querySelector('.main-home-close-notifications-btn');
        if (closeButton) {
            closeButton.addEventListener('click', function(e) {
                e.stopPropagation();
                hideNotificationsList();
            });
        }
        
        // Render the notifications
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
        
        // Set the state
        isShowingNotificationsList = true;
        
        // Hide the animated close button when showing big popup
        if (closeBtn) {
            closeBtn.classList.remove('show');
        }
        
        // Trigger the smooth transition
        requestAnimationFrame(() => {
            notificationsListElement.style.opacity = '1';
        });
    }
    
    // Hide notifications list
    function hideNotificationsList() {
        if (!isShowingNotificationsList) return;
        
        const overlayElement = document.getElementById('notificationsListOverlay');
        if (overlayElement) {
            // Smooth transition out
            overlayElement.style.opacity = '0';
            
            // Remove element after transition completes
            setTimeout(() => {
                if (overlayElement.parentNode) {
                    overlayElement.remove();
                }
            }, 400); // Match the transition duration
        }
        
        isShowingNotificationsList = false;
        
        // Show the animated close button
        if (closeBtn) {
            closeBtn.classList.add('show');
        }
        
        // The expanded island remains visible underneath
    }
    
    // Render notifications list
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
    
    // Show notification popup
    function showNotificationPopup(title, message, icon = '📱') {
        if (isShowingNotification) return;
        
        isShowingNotification = true;
        isExpanded = false;
        
        // Remove expanded class and add notification popup class
        dynamicIsland.classList.remove('expanded');
        dynamicIsland.classList.add('notification-popup');
        
        // Hide the dynamic content (the "3 notifications" text)
        const dynamicContent = dynamicIsland.querySelector('.main-home-dynamic-content');
        if (dynamicContent) {
            dynamicContent.style.display = 'none';
        }
        
        // Update popup content
        const popupContent = dynamicIsland.querySelector('.main-home-notification-popup-content');
        const popupIcon = dynamicIsland.querySelector('.main-home-notification-popup-icon');
        const popupTitle = dynamicIsland.querySelector('.main-home-notification-popup-title');
        const popupMessage = dynamicIsland.querySelector('.main-home-notification-popup-message');
        
        if (popupContent && popupIcon && popupTitle && popupMessage) {
            popupContent.style.display = 'flex';
            popupIcon.textContent = icon;
            
            // Show different content based on notification count
            if (notificationsList.length === 1) {
                popupTitle.textContent = title;
                popupMessage.textContent = message;
            } else {
                popupTitle.textContent = `${notificationsList.length} Notifications`;
                popupMessage.textContent = 'Cliquez à nouveau pour tout voir';
            }
        }
        
        // Auto-hide after 4 seconds only if there's only one notification
        // If there are multiple notifications, keep it open for second click
        if (notificationsList.length === 1) {
            notificationTimeout = setTimeout(() => {
                hideNotificationPopup();
            }, 4000);
        }
    }
    
    // Hide notification popup
    function hideNotificationPopup() {
        if (!isShowingNotification) return;
        
        isShowingNotification = false;
        clearTimeout(notificationTimeout);
        
        dynamicIsland.classList.remove('notification-popup');
        
        // Hide the popup content
        const popupContent = dynamicIsland.querySelector('.main-home-notification-popup-content');
        if (popupContent) {
            popupContent.style.display = 'none';
        }
        
        // Restore the dynamic content (the "3 notifications" text) for future hover/click interactions
        const dynamicContent = dynamicIsland.querySelector('.main-home-dynamic-content');
        if (dynamicContent) {
            dynamicContent.style.display = 'block';
        }
    }
    
    // Simulate incoming notifications
    function simulateNotification() {
        const notifications = [
            { title: 'New Message', message: 'You have a new message from John', icon: '💬' },
            { title: 'System Update', message: 'Your system has been updated', icon: '⚡' },
            { title: 'Reminder', message: 'Don\'t forget your meeting at 3 PM', icon: '⏰' },
            { title: 'Welcome!', message: 'Welcome to our platform!', icon: '🎉' },
            { title: 'Security Alert', message: 'New login detected', icon: '🔒' },
            { title: 'Payment Received', message: 'Payment of $299 has been received', icon: '💰' },
            { title: 'File Uploaded', message: 'Document.pdf has been uploaded successfully', icon: '📄' },
            { title: 'Team Invite', message: 'You\'ve been invited to join Team Alpha', icon: '👥' },
            { title: 'Backup Complete', message: 'Your data backup is now complete', icon: '💾' },
            { title: 'New Follow', message: 'Sarah started following you', icon: '👤' },
            { title: 'Event Reminder', message: 'Conference call starts in 15 minutes', icon: '📞' },
            { title: 'Storage Warning', message: 'You\'re using 85% of your storage', icon: '⚠️' },
            { title: 'App Update', message: 'New version available for download', icon: '📱' },
            { title: 'Weather Alert', message: 'Heavy rain expected this afternoon', icon: '🌧️' },
            { title: 'Birthday Wish', message: 'Happy Birthday! 🎂', icon: '🎈' }
        ];
        
        const randomNotification = notifications[Math.floor(Math.random() * notifications.length)];
        
        // Add timestamp
        const now = new Date();
        const timeString = now.toLocaleTimeString('en-US', { 
            hour: '2-digit', 
            minute: '2-digit',
            hour12: true 
        });
        
        // Add to notifications list
        notificationsList.unshift({
            ...randomNotification,
            time: timeString,
            id: Date.now() + Math.random()
        });
        
        // Keep only last 10 notifications
        if (notificationsList.length > 10) {
            notificationsList = notificationsList.slice(0, 10);
        }
        
        // Don't show notification popup from test button - only update badge count
        // The big popup should only appear when clicking the actual Dynamic Island
    }
    
    
    // Expose functions globally for external use
    window.DynamicIsland = {
        showNotification: showNotificationPopup,
        hideNotification: hideNotificationPopup,
        expand: expandIsland,
        collapse: collapseIsland
    };
    
    console.log('🏝️ Dynamic Island initialized');
}

// Export functions for external use
window.MainHome = {
    showNotification,
    formatDate,
    generateRandomValue
};
