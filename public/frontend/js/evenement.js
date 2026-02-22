/* ==========================================================================
   EVENEMENT MODULE JAVASCRIPT
   "Horizon" Sick UI Interactivity
   ========================================================================== */

// Global promise error handling to catch "Uncaught (in promise)"
window.addEventListener('unhandledrejection', function (event) {
    console.error('Unhandled promise rejection:', event.reason);
});

document.addEventListener('DOMContentLoaded', function () {
    // --- ADVANCED MAP LOGIC (LEAFLET) ---
    let eventMaps = {};

    function initLeafletMap(containerId, latInputId, lngInputId, initialLat, initialLng, interactive = true) {
        console.log('initLeafletMap:', containerId);

        if (typeof L === 'undefined') {
            alert('CRITICAL: Leaflet library (L) is not loaded! The map will not work. Please check your internet connection.');
            return;
        }

        if (eventMaps[containerId]) {
            eventMaps[containerId].remove();
        }

        try {
            const lat = initialLat ? parseFloat(initialLat) : 36.8065;
            const lng = initialLng ? parseFloat(initialLng) : 10.1815;

            // Basic Init
            const map = L.map(containerId).setView([lat, lng], 13);
            eventMaps[containerId] = map;

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap travelers'
            }).addTo(map);

            let marker;
            if (initialLat && initialLng) {
                marker = L.marker([lat, lng], { draggable: interactive }).addTo(map);
            }

            if (interactive) {
                map.on('click', function (e) {
                    if (marker) marker.setLatLng(e.latlng);
                    else marker = L.marker(e.latlng, { draggable: true }).addTo(map);

                    if (latInputId) document.getElementById(latInputId).value = e.latlng.lat;
                    if (lngInputId) document.getElementById(lngInputId).value = e.latlng.lng;
                });
            }

            // Force refresh
            setTimeout(() => map.invalidateSize(), 800);
        } catch (e) {
            console.error('Leaflet failing:', e);
            const container = document.getElementById(containerId);
            if (container) container.innerHTML = '<p style="color:red; padding:10px;">Map Init failed: ' + e.message + '</p>';
        }
    }

    // --- CREATE MODAL ---
    const createModal = document.getElementById('createEventModal');
    const btnOpenCreate = document.getElementById('btnOpenCreate');
    const btnOpenCreateTop = document.getElementById('btnOpenCreateTop');
    const btnEmptyCreate = document.getElementById('btnEmptyCreate');
    const btnHostEventDashboard = document.getElementById('btnHostEventDashboard');

    function lockScroll() {
        document.body.style.overflow = 'hidden';
        const scrollPill = document.querySelector('.scroll-down-pill-wrapper');
        if (scrollPill) scrollPill.classList.add('hide');
    }

    function unlockScroll() {
        document.body.style.overflow = '';
        const scrollPill = document.querySelector('.scroll-down-pill-wrapper');
        if (scrollPill) scrollPill.classList.remove('hide');
    }

    function openCreateModal() {
        if (!createModal) return;
        lockScroll();
        createModal.style.display = 'flex';
        setTimeout(() => {
            createModal.classList.add('show');
            initLeafletMap('createEventMap', 'createLat', 'createLng', null, null, true);
        }, 10);
    }

    window.closeCreateModal = function () {
        if (!createModal) return;
        createModal.classList.remove('show');
        unlockScroll();
        setTimeout(() => createModal.style.display = 'none', 300);
    }

    if (btnOpenCreate) btnOpenCreate.onclick = openCreateModal;
    if (btnOpenCreateTop) btnOpenCreateTop.onclick = openCreateModal;
    if (btnEmptyCreate) btnEmptyCreate.onclick = openCreateModal;
    if (btnHostEventDashboard) btnHostEventDashboard.onclick = openCreateModal;

    // --- DETAILS MODAL ---
    const detailsModal = document.getElementById('eventDetailsModal');
    const viewDetailsBtns = document.querySelectorAll('.view-event-details');

    window.closeDetailsModal = function () {
        if (!detailsModal) return;
        detailsModal.classList.remove('show');
        unlockScroll();
        setTimeout(() => detailsModal.style.display = 'none', 300);
    }

    if (viewDetailsBtns.length > 0) {
        viewDetailsBtns.forEach(btn => {
            btn.onclick = function () {
                const data = btn.dataset;

                // Inject Data
                document.getElementById('modalEventTitle').textContent = data.title;
                document.getElementById('modalEventDescription').textContent = data.description;
                document.getElementById('modalEventDate').textContent = data.date;
                document.getElementById('modalEventLieu').textContent = data.lieu;
                document.getElementById('modalEventType').textContent = data.type;
                const typeDisplay = document.getElementById('modalEventTypeDisplay');
                if (typeDisplay) typeDisplay.textContent = data.type;
                document.getElementById('modalEventAvailability').textContent = `${data.restants} / ${data.places} Places`;

                // Map Display
                const detailsMapContainer = document.getElementById('detailsEventMap');
                if (data.lat && data.lng && data.lat !== "" && data.lng !== "") {
                    detailsMapContainer.style.display = 'block';
                    initLeafletMap('detailsEventMap', null, null, data.lat, data.lng, false);
                } else {
                    detailsMapContainer.style.display = 'none';
                }

                // Banner
                const banner = document.getElementById('modalEventBanner');
                if (data.image && data.image !== "") {
                    banner.innerHTML = `<img src="${data.image}" alt="${data.title}" onerror="this.src='/img/default-event.jpg'; console.error('Image load failed:', this.src);"><div class="hero-overlay"></div>`;
                } else {
                    banner.innerHTML = `<div style="width: 100%; height: 100%; background: var(--event-gradient);"></div><div class="hero-overlay"></div>`;
                }

                // Admin Actions Visibility (Creator or Moderator roles)
                const currentUserId = (document.getElementById('currentUserId')?.value || "").trim();
                const currentUserRole = (document.getElementById('currentUserRole')?.value || "").trim().toUpperCase();
                const adminActions = document.getElementById('modalAdminActions');
                const moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];

                const authorId = (data.authorId || "").trim();
                const isAuthor = currentUserId !== "" && authorId !== "" && String(currentUserId) === String(authorId);
                const isModerator = currentUserRole !== "" && moderatorRoles.includes(currentUserRole);

                if (adminActions) {
                    if (isAuthor || isModerator) {
                        adminActions.classList.remove('d-none');
                        adminActions.style.display = 'block';
                    } else {
                        adminActions.classList.add('d-none');
                        adminActions.style.display = 'none';
                    }
                }

                // Delete Form Setup
                const deleteForm = document.getElementById('deleteEventForm');
                const deleteToken = document.getElementById('deleteEventToken');
                if (deleteForm) deleteForm.action = data.deletePath;
                if (deleteToken) deleteToken.value = data.token;

                // Edit Data storage for later
                window.currentEventData = data;

                // Participation Button & Already Participated Label
                const btnOpenParticipation = document.getElementById('btnOpenParticipation');

                if (btnOpenParticipation) {
                    btnOpenParticipation.style.display = 'block';
                    if (data.hasParticipated === 'true') {
                        btnOpenParticipation.innerHTML = '<i class="bx bxs-check-circle"></i> Already Joined';
                        btnOpenParticipation.style.opacity = '0.7';
                        btnOpenParticipation.style.pointerEvents = 'none';
                    } else {
                        btnOpenParticipation.innerHTML = 'Participate';
                        btnOpenParticipation.style.opacity = '1';
                        btnOpenParticipation.style.pointerEvents = 'auto';
                    }
                }

                // Show Modal
                detailsModal.style.display = 'flex';
                lockScroll();
                setTimeout(() => detailsModal.classList.add('show'), 10);
            };
        });
    }

    // --- EDIT MODAL ---
    const editModal = document.getElementById('editEventModal');
    const btnOpenEdit = document.getElementById('btnOpenEdit');
    const editForm = document.getElementById('editEventForm');

    window.closeEditModal = function () {
        if (!editModal) return;
        editModal.classList.remove('show');
        unlockScroll();
        setTimeout(() => editModal.style.display = 'none', 300);
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

            // Map Edit
            const editLatInput = document.getElementById('editLat');
            const editLngInput = document.getElementById('editLng');
            if (editLatInput) editLatInput.value = data.lat || "";
            if (editLngInput) editLngInput.value = data.lng || "";

            if (data.dateRaw) {
                document.getElementById('editDate').value = data.dateRaw;
            }

            if (data.statut) {
                const editStatut = document.getElementById('editStatut');
                if (editStatut) {
                    editStatut.value = data.statut;
                    const pillContainer = editStatut.closest('.main-home-form-group');
                    if (pillContainer) {
                        const pills = pillContainer.querySelectorAll('.status-pill');
                        pills.forEach(p => {
                            if (p.dataset.value === data.statut) p.classList.add('active');
                            else p.classList.remove('active');
                        });
                    }
                }
            }

            editForm.action = data.editPath;

            // Transition: Close details, open edit
            detailsModal.classList.remove('show');
            setTimeout(() => {
                detailsModal.style.display = 'none';
                editModal.style.display = 'flex';
                setTimeout(() => {
                    editModal.classList.add('show');
                    initLeafletMap('editEventMap', 'editLat', 'editLng', data.lat, data.lng, true);
                }, 10);
            }, 300);
        };
    }

    // --- DELETE CONFIRMATION ---
    const deleteConfirmModal = document.getElementById('deleteConfirmModal');
    const btnOpenDeleteConfirm = document.getElementById('btnOpenDeleteConfirm');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const deleteEventForm = document.getElementById('deleteEventForm');

    window.closeDeleteConfirm = function () {
        if (!deleteConfirmModal) return;
        deleteConfirmModal.classList.remove('show');
        if (!detailsModal.classList.contains('show') && !editModal.classList.contains('show')) {
            unlockScroll();
        }
        setTimeout(() => deleteConfirmModal.style.display = 'none', 300);
    }

    if (btnOpenDeleteConfirm) {
        btnOpenDeleteConfirm.onclick = function () {
            if (!deleteConfirmModal) return;
            deleteConfirmModal.style.display = 'flex';
            lockScroll();
            setTimeout(() => deleteConfirmModal.classList.add('show'), 10);
        };
    }

    if (confirmDeleteBtn && deleteEventForm) {
        confirmDeleteBtn.onclick = function () {
            deleteEventForm.submit();
        };
    }

    // --- PARTICIPATION MODAL ---
    const participationModal = document.getElementById('participationModal');
    const btnOpenParticipation = document.getElementById('btnOpenParticipation');
    const participationForm = document.getElementById('participationForm');

    window.closeParticipationModal = function () {
        if (!participationModal) return;
        participationModal.classList.remove('show');
        if (!detailsModal.classList.contains('show')) {
            unlockScroll();
        }
        setTimeout(() => participationModal.style.display = 'none', 300);
    }

    if (btnOpenParticipation) {
        btnOpenParticipation.onclick = function () {
            const data = window.currentEventData;
            if (!data) return;

            const currentUserIdInput = document.getElementById('currentUserId');
            const currentUserId = currentUserIdInput ? currentUserIdInput.value : "";

            if (!currentUserId || currentUserId === "") {
                window.location.href = '/sign-in';
                return;
            }

            document.getElementById('participationEventTitle').textContent = data.title;
            participationForm.action = `/participation/new/${data.id}`;

            participationModal.style.display = 'flex';
            lockScroll();
            setTimeout(() => participationModal.classList.add('show'), 10);
        };
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

        const monthName = new Intl.DateTimeFormat('en-US', { month: 'long' }).format(displayDate);
        calendarMonthDisplay.textContent = `${monthName} ${year}`;

        const labels = calendarGrid.querySelectorAll('.calendar-day-label');
        calendarGrid.innerHTML = '';
        labels.forEach(label => calendarGrid.appendChild(label));

        let firstDay = new Date(year, month, 1).getDay();
        firstDay = firstDay === 0 ? 6 : firstDay - 1;

        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        for (let i = firstDay; i > 0; i--) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'calendar-day empty';
            dayDiv.textContent = daysInPrevMonth - i + 1;
            calendarGrid.appendChild(dayDiv);
        }

        for (let i = 1; i <= daysInMonth; i++) {
            const dayDiv = document.createElement('div');
            dayDiv.className = 'calendar-day';
            dayDiv.textContent = i;

            if (i === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                dayDiv.classList.add('active');
            }

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

        const totalSlots = 42;
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

        const banner = document.getElementById('previewBanner');
        const type = document.getElementById('previewType');
        const title = document.getElementById('previewTitle');
        const date = document.getElementById('previewDate');
        const viewBtn = document.getElementById('previewViewBtn');

        if (event.image) banner.style.backgroundImage = `url(${event.image})`;
        else banner.style.background = 'var(--event-gradient)';

        type.textContent = event.type;
        title.textContent = event.title;
        date.textContent = event.date;

        viewBtn.onclick = () => {
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

        const rect = element.getBoundingClientRect();
        previewPopover.style.top = `${rect.top - 12}px`;
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

    renderCalendar();

    window.onclick = function (event) {
        if (event.target == createModal) closeCreateModal();
        if (event.target == detailsModal) closeDetailsModal();
        if (event.target == editModal) closeEditModal();
        if (event.target == deleteConfirmModal) closeDeleteConfirm();
        if (event.target == participationModal) closeParticipationModal();

        if (!event.target.closest('.calendar-event-preview') && !event.target.closest('.calendar-day')) {
            hideEventPreview();
        }
    }

    const createEventForm = document.getElementById('createEventForm');
    if (createEventForm) {
        createEventForm.onsubmit = async function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalHTML = btn.innerHTML;

            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Organizing...';
            btn.disabled = true;

            const formData = new FormData(this);
            try {
                const response = await fetch(this.action || '/evenement/new', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }

                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    console.error('Non-JSON response received:', await response.text());
                    throw new TypeError("Ouch! The server didn't return JSON. Check the console for the full response.");
                }
                const data = await response.json();
                if (data.success) {
                    showCoolPopup('Event Live!', 'Your event has been successfully organized.', 'success');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    const errorMsg = data.message || (data.errors ? data.errors.join('<br>') : 'Validation failed.');
                    showCoolPopup('Check your event', errorMsg, 'error');
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                showCoolPopup('Network Error', 'The server responded with an error or is unreachable.', 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        };
    }

    const editEventForm = document.getElementById('editEventForm');
    if (editEventForm) {
        editEventForm.onsubmit = async function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalHTML = btn.innerHTML;

            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Saving...';
            btn.disabled = true;

            const formData = new FormData(this);
            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) {
                    throw new Error(`Server returned ${response.status}: ${response.statusText}`);
                }

                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    console.error('Non-JSON response received:', await response.text());
                    throw new TypeError("Ouch! The server didn't return JSON. Check the console for the full response.");
                }
                const data = await response.json();
                if (data.success) {
                    showCoolPopup('Updated!', 'Your event details have been saved.', 'success');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    const errorMsg = data.message || (data.errors ? data.errors.join('<br>') : 'Validation failed.');
                    showCoolPopup('Check your edit', errorMsg, 'error');
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                }
            } catch (error) {
                console.error('Fetch Error:', error);
                showCoolPopup('Network Error', 'The server responded with an error or is unreachable.', 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }
        };
    }
});
