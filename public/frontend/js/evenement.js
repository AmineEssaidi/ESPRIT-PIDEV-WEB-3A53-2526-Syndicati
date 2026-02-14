/* ==========================================================================
   EVENEMENT MODULE JAVASCRIPT
   "Horizon" Sick UI Interactivity
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
    // --- CREATE MODAL ---
    const createModal = document.getElementById('createEventModal');
    const editModal = document.getElementById('editEventModal');
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const detailsModal = document.getElementById('eventDetailsModal');
    const btnOpenEdit = document.getElementById('btnOpenEdit');
    const btnOpenDeleteConfirm = document.getElementById('btnOpenDeleteConfirm');
    // --- GLASS SWITCHER UTILITY ---
    window.switchGlassCard = function (containerId, faceName) {
        const container = document.getElementById(containerId);
        if (!container) return;

        // Remove all possible active classes
        container.classList.remove('active-extra', 'active-details', 'active-participate');

        if (faceName !== 'main') {
            container.classList.add(`active-${faceName}`);
            // Trigger Flatpickr/Custom Select init for the newly shown face
            if (typeof initializeCustomFormElements === 'function') {
                setTimeout(initializeCustomFormElements, 100);
            }
        }
    };

    const btnHostEventDashboard = document.getElementById('btnHostEventDashboard');
    if (btnHostEventDashboard) {
        btnHostEventDashboard.onclick = () => switchGlassCard('evenementDashboardSwitcher', 'extra');
    }

    const scrollToDashboardAndHost = () => {
        const switcher = document.getElementById('evenementDashboardSwitcher');
        if (switcher) {
            switcher.scrollIntoView({ behavior: 'smooth', block: 'center' });
            switchGlassCard('evenementDashboardSwitcher', 'extra');
        }
    };

    const btnOpenCreate = document.getElementById('btnOpenCreate');
    const btnOpenCreateTop = document.getElementById('btnOpenCreateTop');
    const btnEmptyCreate = document.getElementById('btnEmptyCreate');

    if (btnOpenCreate) btnOpenCreate.onclick = scrollToDashboardAndHost;
    if (btnOpenCreateTop) btnOpenCreateTop.onclick = scrollToDashboardAndHost;
    if (btnEmptyCreate) btnEmptyCreate.onclick = scrollToDashboardAndHost;

    // --- GLOBAL ACTIONS FOR CARDS ---
    window.openEditModalFromCard = function (btn) {
        const data = btn.dataset;
        if (!data) return;

        // Pre-fill Edit Form
        const editTitre = document.getElementById('editTitre');
        const editDesc = document.getElementById('editDesc');
        const editLieu = document.getElementById('editLieu');
        const editPlaces = document.getElementById('editPlaces');
        const editRestants = document.getElementById('editRestants');
        const editType = document.getElementById('editType');
        const editDate = document.getElementById('editDate');

        if (editTitre) editTitre.value = data.title || "";
        if (editDesc) editDesc.value = data.description || "";
        if (editLieu) editLieu.value = data.lieu || "";
        if (editPlaces) editPlaces.value = data.places || "";
        if (editRestants) editRestants.value = data.restants || "";
        if (editType) {
            editType.value = data.type || "";
            // Trigger custom dropdown update if it exists
            const trigger = editType.closest('.custom-select-wrapper')?.querySelector('.selected-text');
            if (trigger) trigger.textContent = editType.options[editType.selectedIndex]?.text || data.type;
        }

        if (editDate && data.dateRaw) {
            editDate.value = data.dateRaw;
        }

        if (data.statut) {
            const editStatut = document.getElementById('editStatut');
            if (editStatut) {
                editStatut.value = data.statut;
                const pillContainer = editStatut.closest('.glass-form-group');
                if (pillContainer) {
                    pillContainer.querySelectorAll('.status-pill').forEach(p => {
                        if (p.dataset.value === data.statut) p.classList.add('active');
                        else p.classList.remove('active');
                    });
                }
            }
        }

        const editForm = document.getElementById('editEventForm');
        if (editForm) editForm.action = data.editPath;

        if (typeof openGlassModal === 'function') {
            openGlassModal('editEventModal');
        }
    };

    window.openDeleteConfirmModal = function (id, deletePath, token) {
        const deleteEventForm = document.getElementById('deleteEventForm');
        const deleteToken = document.getElementById('deleteEventToken');
        if (deleteEventForm) deleteEventForm.action = deletePath;
        if (deleteToken) deleteToken.value = token;

        if (typeof openGlassModal === 'function') {
            openGlassModal('deleteConfirmModal');
        }
    };

    // if (btnOpenCreate) btnOpenCreate.onclick = () => openGlassModal('createEventModal');
    // if (btnOpenCreateTop) btnOpenCreateTop.onclick = () => openGlassModal('createEventModal');
    // if (btnEmptyCreate) btnEmptyCreate.onclick = () => openGlassModal('createEventModal');

    // Modals for details and participation have been replaced by card switchers.
    // Edit and Delete modals are still used but triggered via global window functions.

    const editForm = document.getElementById('editEventForm');

    window.closeEditModal = function () {
        closeGlassModal('editEventModal');
    }

    if (btnOpenEdit) {
        btnOpenEdit.onclick = function () {
            const data = window.currentEventData;
            if (!data) return;

            // Pre-fill Edit Form
            document.getElementById('editTitre').value = data.title;
            document.getElementById('editDesc').value = data.description;
            document.getElementById('editLieu').value = data.lieu;
            document.getElementById('editPlaces').value = data.places;
            document.getElementById('editRestants').value = data.restants;
            document.getElementById('editType').value = data.type;

            if (data.dateRaw) {
                document.getElementById('editDate').value = data.dateRaw;
            }

            if (data.statut) {
                const editStatut = document.getElementById('editStatut');
                if (editStatut) {
                    editStatut.value = data.statut;
                    const pillContainer = editStatut.closest('.glass-form-group');
                    if (pillContainer) {
                        pillContainer.querySelectorAll('.status-pill').forEach(p => {
                            if (p.dataset.value === data.statut) p.classList.add('active');
                            else p.classList.remove('active');
                        });
                    }
                }
            }

            editForm.action = data.editPath;

            // Transition: Close details, open edit
            if (typeof closeGlassModal === 'function' && typeof openGlassModal === 'function') {
                closeGlassModal('eventDetailsModal');
                setTimeout(() => {
                    openGlassModal('editEventModal');
                }, 300);
            } else {
                detailsModal.classList.remove('show');
                setTimeout(() => {
                    detailsModal.style.display = 'none';
                    editModal.style.display = 'flex';
                    setTimeout(() => editModal.classList.add('show'), 10);
                }, 300);
            }
        };
    }

    // --- DELETE CONFIRMATION ---
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteEventForm = document.getElementById('deleteEventForm');

    window.closeDeleteConfirm = function () {
        if (!deleteConfirmModal) return;
        deleteConfirmModal.classList.remove('show');
        // If we opened this from Details, we don't necessarily want to unlockScroll 
        // if Details is still open, but usually we cover Details with this.
        // Let's just restore scroll if nothing else is open.
        if (!detailsModal.classList.contains('show') && !editModal.classList.contains('show')) {
            unlockScroll();
        }
        setTimeout(() => deleteConfirmModal.style.display = 'none', 300);
    }

    if (btnOpenDeleteConfirm) {
        btnOpenDeleteConfirm.onclick = function () {
            if (typeof openGlassModal === 'function') {
                openGlassModal('deleteConfirmModal');
            } else {
                if (!deleteConfirmModal) return;
                deleteConfirmModal.style.display = 'flex';
                lockScroll();
                setTimeout(() => deleteConfirmModal.classList.add('show'), 10);
            }
        };
    }

    if (confirmDeleteBtn && deleteEventForm) {
        confirmDeleteBtn.onclick = async function () {
            this.disabled = true;
            this.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Deleting...';

            try {
                const formData = new FormData(deleteEventForm);
                const response = await fetch(deleteEventForm.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await response.json();
                if (data.success) {
                    showCoolPopup('Event Removed', data.message, 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showCoolPopup('Error', data.message || 'Could not delete event.', 'error');
                    this.disabled = false;
                    this.innerHTML = 'Yes, Delete it';
                }
            } catch (err) {
                console.error('Delete Error:', err);
                showCoolPopup('Network Error', 'Check your connection.', 'error');
                this.disabled = false;
                this.innerHTML = 'Yes, Delete it';
            }
        };
    }


    // --- PARTICIPATION AJAX ---
    // Delegation handles both standalone forms and in-card forms
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-ajax="true"]');
        if (form && form.action.includes('/participation/new')) {
            e.preventDefault();
            handleAjaxParticipation(form);
        }
    });

    async function handleAjaxParticipation(form) {
        const btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        const originalHTML = btn.innerHTML;

        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Processing...';
        btn.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await response.json();
            if (data.success) {
                showCoolPopup('Spot Secured!', data.message, 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showCoolPopup('Error', data.message || 'Submission failed.', 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        } catch (err) {
            console.error('Participation Error:', err);
            showCoolPopup('Error', 'An unexpected error occurred.', 'error');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    }

    // --- CALENDAR LOGIC ---
    const calendarMonthDisplay = document.getElementById('calendarMonthDisplay');
    const calendarGrid = document.getElementById('calendarGrid');
    const prevMonthBtn = document.getElementById('prevMonth');
    const nextMonthBtn = document.getElementById('nextMonth');

    let displayDate = new Date();
    const today = new Date();

    function renderCalendar() {
        if (!calendarGrid || !calendarMonthDisplay) return;

        const year = displayDate.getFullYear();
        const month = displayDate.getMonth();

        // Update Month Display
        const monthName = new Intl.DateTimeFormat('en-US', { month: 'long' }).format(displayDate);
        calendarMonthDisplay.textContent = `${monthName} ${year}`;

        // Clear existing days (keep labels)
        const labels = calendarGrid.querySelectorAll('.calendar-day-label');
        calendarGrid.innerHTML = '';
        labels.forEach(label => calendarGrid.appendChild(label));

        // Get first day of month (0 = Sunday, 1 = Monday...)
        let firstDay = new Date(year, month, 1).getDay();
        // Adjust to Mo-Su (where Mo=0, Su=6)
        firstDay = firstDay === 0 ? 6 : firstDay - 1;

        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        // 1. Render days from previous month (empty slots)
        for (let i = firstDay; i > 0; i--) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'calendar-day empty';
            dayDiv.textContent = daysInPrevMonth - i + 1;
            calendarGrid.appendChild(dayDiv);
        }

        // 2. Render current month days
        for (let i = 1; i <= daysInMonth; i++) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'calendar-day';
            dayDiv.textContent = i;

            // Check if it's today
            if (i === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                dayDiv.classList.add('active');
            }

            // Check if there's an event on this day
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
            if (window.calendarEvents && window.calendarEvents[dateStr]) {
                const event = window.calendarEvents[dateStr];
                dayDiv.classList.add('has-event');
                dayDiv.onclick = (e) => {
                    e.stopPropagation();
                    showEventPreview(dayDiv, event);
                };
            }

            calendarGrid.appendChild(dayDiv);
        }

        // 3. Fill remaining slots to make a full grid (optional for aesthetic)
        const totalSlots = 42; // 6 rows of 7
        const currentSlots = calendarGrid.querySelectorAll('.calendar-day').length;
        for (let i = 1; i <= (totalSlots - currentSlots); i++) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'calendar-day empty';
            dayDiv.textContent = i;
            calendarGrid.appendChild(dayDiv);
        }
    }

    const previewPopover = document.getElementById('calendarEventPreview');
    function showEventPreview(element, event) {
        if (!previewPopover) return;

        // Populate Preview
        const banner = document.getElementById('previewBanner');
        const type = document.getElementById('previewType');
        const title = document.getElementById('previewTitle');
        const date = document.getElementById('previewDate');
        const viewBtn = document.getElementById('previewViewBtn');

        if (event.image) {
            banner.style.backgroundImage = `url(${event.image})`;
        } else {
            banner.style.background = 'var(--event-gradient)';
        }

        type.textContent = event.type;
        title.textContent = event.title;
        date.textContent = event.date;

        // View Button Hook
        viewBtn.onclick = () => {
            // Find the card's view details button and trigger it
            // Or use a more direct way since we have the data
            // Let's find the card in the DOM by title or ID if possible
            const cards = document.querySelectorAll('.event-card');
            let targetBtn = null;
            cards.forEach(card => {
                const cardTitle = card.querySelector('.event-card-title')?.textContent;
                if (cardTitle === event.title) {
                    targetBtn = card.querySelector('.view-event-details');
                }
            });

            if (targetBtn) {
                targetBtn.click();
                hideEventPreview();
            }
        };

        // Position Preview
        const rect = element.getBoundingClientRect();
        previewPopover.style.top = `${rect.top - 12}px`; /* Added slight offset */
        previewPopover.style.left = `${rect.left + rect.width / 2}px`;

        previewPopover.classList.add('show');
    }

    function hideEventPreview() {
        if (previewPopover) previewPopover.classList.remove('show');
    }

    if (prevMonthBtn) {
        prevMonthBtn.onclick = () => {
            displayDate.setMonth(displayDate.getMonth() - 1);
            hideEventPreview();
            renderCalendar();
        };
    }

    if (nextMonthBtn) {
        nextMonthBtn.onclick = () => {
            displayDate.setMonth(displayDate.getMonth() + 1);
            hideEventPreview();
            renderCalendar();
        };
    }

    // Initial render
    renderCalendar();

    // --- CUSTOM PILL SELECTORS (Status) ---
    function initStatusPills() {
        const selectors = document.querySelectorAll('.custom-status-selector');
        selectors.forEach(selector => {
            selector.onclick = function (e) {
                const pill = e.target.closest('.status-pill');
                if (!pill) return;

                const value = pill.dataset.value;
                const container = pill.closest('.glass-form-group');
                const select = container ? container.querySelector('select') : null;

                if (select) {
                    select.value = value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                }

                // Update UI
                selector.querySelectorAll('.status-pill').forEach(p => p.classList.remove('active'));
                pill.classList.add('active');
            };
        });
    }

    initStatusPills();

    // Outer Click to Close
    window.onclick = function (event) {
        if (event.target == createModal) closeGlassModal('createEventModal');
        if (event.target == editModal) closeEditModal();
        if (event.target == deleteConfirmModal) closeDeleteConfirm();

        // Hide preview when clicking elsewhere
        const previewPopover = document.getElementById('calendarEventPreview');
        if (previewPopover && !event.target.closest('.calendar-event-preview') && !event.target.closest('.calendar-day')) {
            hideEventPreview();
        }
    }

    // --- AJAX EVENT SUBMISSIONS (Unified Delegation) ---
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-ajax="true"]');
        if (!form) return;

        // Skip participation (handled separately or let's unify)
        if (form.action.includes('/participation/new')) {
            e.preventDefault();
            handleAjaxParticipation(form);
            return;
        }

        // Handle Event Create/Edit
        // Matches: #createEventFormDashboard OR /evenement/new OR /evenement/{id}/edit
        if (form.id === 'createEventFormDashboard' || form.action.includes('/evenement/new') || (form.action.includes('/evenement/') && form.action.includes('/edit'))) {
            e.preventDefault();
            handleAjaxEventAction(form);
        }
    });

    async function handleAjaxEventAction(form) {
        const btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        const originalHTML = btn.innerHTML;
        const isEdit = form.action.includes('/edit');

        btn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status"></span> ${isEdit ? 'Saving...' : 'Organizing...'}`;
        btn.disabled = true;

        const formData = new FormData(form);
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await response.json();
            if (data.success) {
                const title = isEdit ? 'Updated!' : 'Event Live!';
                const message = isEdit ? 'Your event details have been saved.' : 'Your event has been successfully organized.';
                showCoolPopup(title, message, 'success');

                if (!isEdit) {
                    // CREATE: Reset form and close
                    form.reset();
                    // Reset Flatpickr
                    const dateInput = form.querySelector('.flatpickr-input');
                    if (dateInput && dateInput._flatpickr) dateInput._flatpickr.clear();

                    // Reset custom selects/pills
                    // (Optional: reset dynamic UI)

                    // Close switcher
                    if (typeof switchGlassCard === 'function') {
                        switchGlassCard('evenementDashboardSwitcher', 'main');
                    }
                } else {
                    // EDIT: Close modal/switcher
                    if (typeof closeGlassModal === 'function') {
                        closeGlassModal('editEventModal');
                    }
                    // If using in-card switcher
                    const cardSwitcher = form.closest('.glass-switcher');
                    if (cardSwitcher) {
                        const switcherId = cardSwitcher.id;
                        if (typeof switchGlassCard === 'function') {
                            switchGlassCard(switcherId, 'details');
                        }
                    }
                }

                // Ideally we would update the event list here, but without full SPA logic, 
                // we'll rely on the user refreshing later or implemented a forced reload if absolutely needed.
                // User asked to "remain in the same page", so we DO NOT reload.

            } else {
                if (data.errors && typeof data.errors === 'object' && !Array.isArray(data.errors)) {
                    if (form.validator) {
                        form.validator.mapErrors(data.errors);
                        showCoolPopup('Please Correct the Errors', 'Some fields require your attention.', 'error');
                    } else {
                        let msg = "Validation failed:<br>";
                        for (let key in data.errors) { msg += `- ${data.errors[key]}<br>`; }
                        showCoolPopup('Please Correct the Errors', msg, 'error');
                    }
                } else {
                    const errorMsg = data.message || (Array.isArray(data.errors) ? data.errors.join('<br>') : 'Validation failed.');
                    showCoolPopup('Check your form', errorMsg, 'error');
                }
            }
        } catch (error) {
            console.error('Event Action Error:', error);
            showCoolPopup('Network Error', 'Please try again later.', 'error');
        } finally {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    }
});

