/**
 * Admin Core Utilities
 * Centralizes AJAX handling, notifications, and glass-switcher management.
 */

(function () {
    // --- OBSIDIAN NOTIFICATION ---
    window.showObsidianNotification = function (message, title = 'Notification', type = 'success') {
        let pill = document.querySelector('.obsidian-notification-pill');
        if (!pill) {
            pill = document.createElement('div');
            pill.className = 'obsidian-notification-pill';
            pill.innerHTML = `
                <div class="obsidian-notification-icon success">
                    <i class="bx bx-check"></i>
                </div>
                <div class="obsidian-notification-content">
                    <div class="obsidian-notification-title">NOTIFICATION</div>
                    <div class="obsidian-notification-message">Default message</div>
                </div>
            `;
            document.body.appendChild(pill);
        }

        const iconContainer = pill.querySelector('.obsidian-notification-icon');
        const titleEl = pill.querySelector('.obsidian-notification-title');
        const messageEl = pill.querySelector('.obsidian-notification-message');

        titleEl.textContent = title.toUpperCase();
        messageEl.textContent = message;

        // Set type
        iconContainer.className = `obsidian-notification-icon ${type}`;
        iconContainer.innerHTML = type === 'success' ? '<i class="bx bx-check"></i>' : '<i class="bx bx-x"></i>';

        // Show
        pill.classList.add('active');

        // Hide after 4 seconds
        setTimeout(() => {
            pill.classList.remove('active');
        }, 4000);
    };

    // --- SOFT REFRESH ---
    // Background re-fetches the current page and updates tables/scripts
    window.softRefresh = async function () {
        console.log("Starting Soft Refresh...");
        try {
            const response = await fetch(window.location.href);
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // 1. Update Table Bodies/Containers
            const selectors = [
                '.glass-table-scroll',
                '#syndicat-rec-table-body',
                '#syndicat-rep-table-body',
                '#onboarding-table-body',
                '#users-table-body'
            ];

            selectors.forEach(selector => {
                const oldEls = document.querySelectorAll(selector);
                const newEls = doc.querySelectorAll(selector);
                oldEls.forEach((oldEl, idx) => {
                    if (newEls[idx]) {
                        oldEl.innerHTML = newEls[idx].innerHTML;
                    }
                });
            });

            // 2. Update Data Scripts (for JS-rendered tables)
            const scripts = doc.querySelectorAll('script');
            scripts.forEach(s => {
                const content = s.textContent;
                if (content.includes('publicationsData') ||
                    content.includes('commentsData') ||
                    content.includes('usersData') ||
                    content.includes('reclamationsData')) {

                    // Evaluate script to update global variables
                    try {
                        // We replace const/let with var to allow re-declaration in global scope
                        const scriptContent = content
                            .replace(/const /g, 'var ')
                            .replace(/let /g, 'var ');
                        eval(scriptContent);
                        console.log("Updated data variables from script.");
                    } catch (e) {
                        console.error("Failed to re-evaluate data script:", e);
                    }
                }
            });

            // 3. Re-trigger rendering for JS tables
            if (typeof window.syndicatFilterSortRecs === 'function') window.syndicatFilterSortRecs();
            if (typeof window.syndicatFilterSortReps === 'function') window.syndicatFilterSortReps();
            if (typeof window.renderUsersTable === 'function') window.renderUsersTable();
            if (typeof window.filterSortEvents === 'function') window.filterSortEvents();
            if (typeof window.filterSortParts === 'function') window.filterSortParts();
            if (typeof window.filterSortResidences === 'function') window.filterSortResidences();
            if (typeof window.filterSortAppartements === 'function') window.filterSortAppartements();
            if (typeof window.filterSortPublications === 'function') window.filterSortPublications();
            if (typeof window.filterSortComments === 'function') window.filterSortComments();

        } catch (error) {
            console.error("Soft Refresh failed:", error);
        }
    };

    // Legacy mapping for compatibility
    window.showCoolPopup = function (title, message, type) {
        window.showObsidianNotification(message, title, type === 'error' ? 'error' : 'success');
    };

    // --- GLOBAL GLASS SWITCHER ---
    window.switchGlassFace = function (face, switcherId) {
        const switcher = switcherId ? document.getElementById(switcherId) : document.querySelector('.glass-switcher.active-main, .glass-switcher.active-details, .glass-switcher.active-edit, .glass-switcher.active-extra');
        if (!switcher) return;

        // Force modal activation if switching to a non-main face
        if (face !== 'main') {
            const targetFace = switcher.querySelector('.glass-switcher-face-' + face);
            if (targetFace) {
                const modal = targetFace.querySelector('.users-edit-modal');
                if (modal) {
                    modal.style.display = 'flex';
                    modal.classList.add('active');
                }
            }
        }

        // Remove all face classes
        switcher.classList.remove('active-main', 'active-details', 'active-edit', 'active-extra', 'active-participate');

        // Add requested face
        if (face === 'main') switcher.classList.add('active-main');
        else if (face === 'details') switcher.classList.add('active-details');
        else if (face === 'edit') switcher.classList.add('active-edit');
        else if (face === 'extra') switcher.classList.add('active-extra');
        else if (face === 'participate') switcher.classList.add('active-participate');

        // Dynamic Height adjustment (if applicable)
        setTimeout(() => {
            const faces = switcher.querySelectorAll('.glass-switcher-face');
            let targetHeight = 0;
            faces.forEach(f => {
                const isMain = f.classList.contains('glass-switcher-face-main');
                const isTarget = (face === 'main' && isMain) ||
                    (face === 'details' && f.classList.contains('glass-switcher-face-details')) ||
                    (face === 'edit' && f.classList.contains('glass-switcher-face-edit')) ||
                    (face === 'extra' && f.classList.contains('glass-switcher-face-extra')) ||
                    (face === 'participate' && f.classList.contains('glass-switcher-face-participate'));
                if (isTarget) {
                    f.style.position = 'relative'; f.style.visibility = 'visible'; f.style.display = 'block'; f.style.height = 'auto';
                    targetHeight = f.offsetHeight || f.scrollHeight;
                    f.style.position = ''; f.style.visibility = ''; f.style.display = ''; f.style.height = '';
                }
            });

            // Fallback: If targetHeight is 0 (can happen during initial render or animation), 
            // try to measure the direct child if it's the main face.
            if (targetHeight === 0 && face === 'main') {
                const mainFace = switcher.querySelector('.glass-switcher-face-main');
                if (mainFace) targetHeight = mainFace.scrollHeight || mainFace.offsetHeight;
            }

            if (targetHeight > 0) {
                switcher.style.height = (targetHeight + 20) + 'px';
            } else if (face === 'main') {
                // Absolute fallback for main table view if measurement fails
                switcher.style.height = 'auto';
                switcher.style.minHeight = '400px';
            }
        }, 150);
    };

    // --- GLOBAL MODAL HELPERS ---
    window.openModal = function (id) {
        const modal = document.getElementById(id);
        if (!modal) return;

        // 1. Show Modal
        modal.style.display = 'flex';
        // Force reflow for transitions
        void modal.offsetWidth;
        modal.classList.add('active');

        // 2. Coordinate with Switcher
        // Try to find the closest switcher or use the naming convention
        const switcher = modal.closest('.glass-switcher') ||
            document.querySelector(`[id*="${id.split('-')[0]}"][class*="glass-switcher"]`);

        if (switcher && window.switchGlassFace) {
            let face = 'main';
            if (id.includes('view') || id.includes('details')) face = 'details';
            else if (id.includes('edit') || id.includes('modify')) face = 'edit';
            else if (id.includes('add') || id.includes('new') || id.includes('create')) face = 'extra';

            window.switchGlassFace(face, switcher.id);
        }
    };

    window.closeModal = function (id) {
        const modal = document.getElementById(id);
        if (!modal) return;

        // 1. Hide Modal
        modal.classList.remove('active');
        setTimeout(() => {
            if (!modal.classList.contains('active')) {
                modal.style.display = 'none';
            }
        }, 400);

        // 2. Reset Switcher
        const switcher = modal.closest('.glass-switcher');
        if (switcher && window.switchGlassFace) {
            window.switchGlassFace('main', switcher.id);
        }
    };

    // --- GLOBAL AJAX HANDLER ---
    document.addEventListener('submit', async function (e) {
        const form = e.target.closest('form[data-ajax="true"]');
        if (!form) return;

        e.preventDefault();

        const btn = form.querySelector('button[type="submit"]');
        const originalHTML = btn ? btn.innerHTML : '';
        const isDelete = form.id && (form.id.includes('delete') || form.getAttribute('action')?.includes('delete'));

        if (btn) {
            btn.classList.add('btn-processing');
            btn.disabled = true;
        }

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await response.json();

            if (data.success) {
                // Determine action type for notification
                let actionTitle = 'Success';
                if (form.action.includes('new') || form.id.includes('add')) actionTitle = 'Created';
                else if (form.action.includes('edit')) actionTitle = 'Updated';
                else if (isDelete) actionTitle = 'Deleted';

                window.showObsidianNotification(data.message || 'Action completed successfully.', actionTitle, 'success');

                // 1. Close Modals
                const modal = form.closest('.glass-modal-overlay, .users-edit-modal') || document.querySelector('.glass-modal-overlay.active, .users-edit-modal.active');
                if (modal) {
                    if (modal.id && typeof window.closeModal === 'function' && modal.classList.contains('users-edit-modal')) {
                        window.closeModal(modal.id);
                    } else if (window.closeGlassModal) {
                        window.closeGlassModal(modal.id);
                    } else if (window.closeGlobalDeleteModal) {
                        window.closeGlobalDeleteModal();
                    } else {
                        modal.classList.remove('active');
                        setTimeout(() => { modal.style.display = 'none'; }, 400);
                    }
                }

                // 2. Reset Switchers
                const switcher = form.closest('.glass-switcher');
                if (switcher) {
                    window.switchGlassFace('main', switcher.id);
                } else {
                    // Try to find any active switcher on the page and reset it
                    document.querySelectorAll('.glass-switcher').forEach(s => {
                        if (!s.classList.contains('active-main')) window.switchGlassFace('main', s.id);
                    });
                }

                // 3. Trigger Table Refresh (if define)
                const refreshHook = form.dataset.refresh || form.getAttribute('data-refresh');
                if (refreshHook && typeof window[refreshHook] === 'function') {
                    window[refreshHook]();
                } else {
                    // Generic fallback: check for render*Table functions
                    const pageId = document.querySelector('.syndicat-page-content, .users-page-content')?.dataset?.page;
                    if (pageId === 'reclamations' && typeof window.syndicatFilterSortRecs === 'function') window.syndicatFilterSortRecs();
                    else if (pageId === 'responses' && typeof window.syndicatFilterSortReps === 'function') window.syndicatFilterSortReps();
                    // Or just reload as a safeguard if no refresh hook is provided (better UX than doing nothing)
                    // but user explicitly said NO refreshes, so we rely on the hooks.
                }

            } else {
                window.showObsidianNotification(data.message || 'Something went wrong.', 'Error', 'error');
            }
        } catch (error) {
            console.error('AJAX Error:', error);
            window.showObsidianNotification('Network error. Pulse failed.', 'Error', 'error');
        } finally {
            if (btn) {
                btn.classList.remove('btn-processing');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }
    });

})();
