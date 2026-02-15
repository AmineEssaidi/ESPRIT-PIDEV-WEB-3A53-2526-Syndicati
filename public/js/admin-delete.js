/**
 * Admin Unified Delete Handler
 * Intercepts clicks on .users-btn-delete (or triggers via window.confirmDelete)
 * Shows the Obsidian Red global delete modal.
 */

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('globalDeleteModal');
    const confirmBtn = document.getElementById('globalConfirmDeleteBtn');
    let pendingAction = null; // Can be a callback function or a form element

    // Helper to open modal
    window.openGlobalDeleteModal = function () {
        if (modal) {
            modal.style.display = 'flex';
            // Force reflow for transition
            void modal.offsetWidth;
            modal.classList.add('active');
        }
    };

    // Helper to close modal
    window.closeGlobalDeleteModal = function () {
        if (modal) {
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
                pendingAction = null;
            }, 300);
        }
    };

    // Helper to update modal title and message
    function updateModalContent(title, message) {
        const titleEl = modal.querySelector('.global-delete-title');
        const textEl = modal.querySelector('.global-delete-text');

        if (titleEl) titleEl.textContent = title;
        if (textEl) {
            textEl.innerHTML = message || 'This action cannot be undone. <br>Are you sure you want to permanently delete this?';
        }
    }

    // Public API for manual JS triggers (like in forum.js)
    // Updated to accept optional title and message
    window.confirmDelete = function (actionCallback, title = 'Delete Item?', message = null) {
        pendingAction = actionCallback;
        updateModalContent(title, message);
        window.openGlobalDeleteModal();
    };

    // Confirm Button Click
    if (confirmBtn) {
        confirmBtn.addEventListener('click', function () {
            if (typeof pendingAction === 'function') {
                // Execute callback (e.g., specific JS logic)
                pendingAction();
                window.closeGlobalDeleteModal();
            } else if (pendingAction && pendingAction.tagName === 'FORM') {
                // Submit intercepted form (use requestSubmit to trigger AJAX handlers)
                if (typeof pendingAction.requestSubmit === 'function') {
                    pendingAction.requestSubmit();
                } else {
                    // Fallback for older browsers
                    pendingAction.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                }
                window.closeGlobalDeleteModal();
            } else if (pendingAction && typeof pendingAction === 'string') {
                // Navigate to URL
                window.location.href = pendingAction;
            }
        });
    }

    // Auto-Intercept .users-btn-delete inside FORMS
    // We delegate to document body to handle dynamically added elements (like valid ajax replacements)
    document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.users-btn-delete');
        if (btn) {
            // Check if it's inside a form
            const form = btn.closest('form');
            if (form) {
                e.preventDefault(); // Stop immediate submit
                e.stopPropagation(); // Stop event bubbling

                pendingAction = form; // Store the form to submit later

                // Extract custom text overrides
                const customTitle = btn.getAttribute('data-delete-title') || 'Delete Item?';
                const customMsg = btn.getAttribute('data-delete-message') || null;

                updateModalContent(customTitle, customMsg);
                window.openGlobalDeleteModal();
            }
        }
    });

    // Close on backdrop click
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                window.closeGlobalDeleteModal();
            }
        });
    }
});
