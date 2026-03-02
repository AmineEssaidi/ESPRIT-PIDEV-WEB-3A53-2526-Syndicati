/* ==========================================================================
   EVENEMENT MODULE JAVASCRIPT
   "Horizon" Sick UI Interactivity
   ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
    // --- BUTTONS ---
    const btnOpenEdit = document.getElementById('btnOpenEdit');
    const btnOpenDeleteConfirm = document.getElementById('btnOpenDeleteConfirm');
    // --- ADVANCED MAP & WEATHER LOGIC ---
    let activeMaps = {};

    async function geocodeLocation(location) {
        if (!location) return null;
        console.log(`Geocoding: ${location}`);
        try {
            const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(location)}&limit=1`;
            const response = await fetch(url, {
                headers: {
                    'Accept-Language': 'en',
                    'User-Agent': 'HorizonCommunityApp/1.0 (amineessaidi)'
                }
            });
            const data = await response.json();
            if (data && data.length > 0) {
                console.log('Geocoding Success:', data[0]);
                return {
                    lat: parseFloat(data[0].lat),
                    lng: parseFloat(data[0].lon),
                    displayName: data[0].display_name
                };
            } else {
                console.warn('Geocoding: No results found.');
            }
        } catch (err) {
            console.error('Geocoding error:', err);
        }
        return null;
    }

    async function updateWeatherForEvent(eventId, lat, lng, date) {
        const weatherCont = document.getElementById(`weather_${eventId}`);
        if (!weatherCont) return;

        try {
            const res = await fetch(`/evenement/weather/preview?lat=${lat}&lng=${lng}&date=${date}`);
            const data = await res.json();
            if (data.success) {
                const w = data.data;
                document.getElementById(`weatherCondition_${eventId}`).textContent = w.condition;
                document.getElementById(`weatherTempMax_${eventId}`).textContent = Math.round(w.temp_max) + '°C';
                document.getElementById(`weatherTempMin_${eventId}`).textContent = Math.round(w.temp_min) + '°C';
                document.getElementById(`weatherWind_${eventId}`).textContent = `Wind: ${Math.round(w.wind)} km/h`;
                document.getElementById(`weatherIcon_${eventId}`).innerHTML = `<i class='bx ${w.icon}'></i>`;
                weatherCont.style.display = 'block';
            }
        } catch (err) {
            console.error('Weather error:', err);
        }
    }

    window.switchGlassCard = async function (containerId, faceName) {
        const container = document.getElementById(containerId);
        if (!container) return;

        // Remove all possible active classes
        container.classList.remove('active-extra', 'active-details', 'active-participate', 'active-edit');

        if (faceName !== 'main') {
            container.classList.add(`active-${faceName}`);

            // --- Logic for Details Face (Map & Weather) ---
            if (faceName === 'details') {
                const eventId = container.dataset.eventId;
                const location = container.dataset.lieu;
                const date = container.dataset.date;

                const mapDiv = document.getElementById(`map_${eventId}`);
                const weatherCont = document.getElementById(`weather_${eventId}`);
                const loader = document.getElementById(`loading_info_${eventId}`);

                if (loader) loader.style.display = 'block';
                if (mapDiv) mapDiv.style.display = 'none';
                if (weatherCont) weatherCont.style.display = 'none';

                if (location) {
                    const coords = await geocodeLocation(location);
                    if (coords) {
                        if (loader) loader.style.display = 'none';
                        if (mapDiv) mapDiv.style.display = 'block';

                        // Wait for transition or use timeout for Leaflet to work correctly with displays
                        setTimeout(() => {
                            if (activeMaps[eventId]) {
                                activeMaps[eventId].remove();
                            }

                            const map = L.map(mapDiv).setView([coords.lat, coords.lng], 15);
                            activeMaps[eventId] = map;

                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; OpenStreetMap'
                            }).addTo(map);

                            // Custom Marker
                            const customIcon = L.divIcon({
                                className: 'custom-map-marker',
                                html: '<i class="bx bxs-map-pin"></i>',
                                iconSize: [32, 32],
                                iconAnchor: [16, 32],
                                popupAnchor: [0, -32]
                            });

                            const marker = L.marker([coords.lat, coords.lng], { icon: customIcon }).addTo(map);

                            // Rich Popup
                            const googleMapsUrl = `https://www.google.com/maps/search/?api=1&query=${coords.lat},${coords.lng}`;
                            const eventTitle = container.querySelector('.event-card-title')?.textContent || 'Event Location';

                            marker.bindPopup(`
                                <div class="marker-popup-content">
                                    <h6>${eventTitle}</h6>
                                    <p style="font-size: 0.7rem; color: rgba(255,255,255,0.5); font-style: italic; margin-top: -3px; margin-bottom: 8px;">Resolved: ${coords.displayName}</p>
                                    <a href="${googleMapsUrl}" target="_blank" class="btn-navigate">
                                        <i class='bx bx-navigation me-1'></i> Navigate with Google
                                    </a>
                                </div>
                            `).openPopup();

                            setTimeout(() => map.invalidateSize(), 150);
                        }, 400);

                        // Fetch Weather
                        updateWeatherForEvent(eventId, coords.lat, coords.lng, date);
                    } else {
                        if (loader) {
                            loader.innerHTML = `<i class='bx bx-error-circle' style="font-size: 1.5rem; color: #ff4d4d;"></i><div style="font-size: 0.8rem; color: rgba(255,255,255,0.4); margin-top: 0.5rem;">Could not map location: ${location}</div>`;
                        }
                    }
                } else {
                    if (loader) loader.style.display = 'none';
                }
            }

            // Trigger Flatpickr/Custom Select init for the newly shown face
            setTimeout(initializeCustomFormElements, 100);
        }
    };

    window.initializeCustomFormElements = function () {
        // --- CUSTOM SELECTS ---
        const selects = document.querySelectorAll('.custom-select-wrapper select:not(.custom-initialized)');
        selects.forEach(select => {
            if (select.parentElement.querySelector('.custom-dropdown-trigger')) return;

            const wrapper = select.parentElement;
            const options = Array.from(select.options);
            const selectedOption = select.options[select.selectedIndex] || options[0];

            const trigger = document.createElement('div');
            trigger.className = 'custom-dropdown-trigger';
            trigger.innerHTML = `<span class="selected-text">${selectedOption ? selectedOption.text : 'Select...'}</span><span class="arrow"><i class='bx bx-chevron-down'></i></span>`;

            const menu = document.createElement('div');
            menu.className = 'custom-dropdown-menu';

            options.forEach(opt => {
                const optDiv = document.createElement('div');
                optDiv.className = 'custom-dropdown-option';
                if (opt.value === select.value) optDiv.classList.add('selected');
                optDiv.textContent = opt.text;
                optDiv.dataset.value = opt.value;

                optDiv.onclick = (e) => {
                    e.stopPropagation();
                    select.value = opt.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    trigger.querySelector('.selected-text').textContent = opt.text;
                    menu.querySelectorAll('.custom-dropdown-option').forEach(d => d.classList.remove('selected'));
                    optDiv.classList.add('selected');
                    trigger.classList.remove('active');
                    menu.classList.remove('active');
                };
                menu.appendChild(optDiv);
            });

            trigger.onclick = (e) => {
                e.stopPropagation();
                const isActive = trigger.classList.contains('active');
                // Close others
                document.querySelectorAll('.custom-dropdown-trigger.active').forEach(t => {
                    if (t !== trigger) {
                        t.classList.remove('active');
                        t.nextElementSibling?.classList.remove('active');
                    }
                });
                trigger.classList.toggle('active');
                menu.classList.toggle('active');
            };

            wrapper.appendChild(trigger);
            wrapper.appendChild(menu);
            select.classList.add('custom-initialized');
        });

        // --- FLATPICKR ---
        if (typeof flatpickr === 'function') {
            flatpickr(".flatpickr-input:not(.flatpickr-initialized)", {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                time_24hr: true,
                theme: "dark",
                disableMobile: "true",
                onReady: function (selectedDates, dateStr, instance) {
                    instance.element.classList.add('flatpickr-initialized');
                }
            });
        }
    };

    // Global click to close dropdowns
    document.addEventListener('click', () => {
        document.querySelectorAll('.custom-dropdown-trigger.active').forEach(t => {
            t.classList.remove('active');
            t.nextElementSibling?.classList.remove('active');
        });
    });

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

        // Removed legacy openEditModalFromCard that used modals
    };

    window.openDeleteConfirmModal = function (id, deletePath, token) {
        window.confirmObsidianDelete(async function () {
            try {
                const fd = new FormData();
                fd.append('_token', token);
                const res = await fetch(deletePath, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await res.json();
                if (data.success) {
                    window.pushNotif('Event Removed', data.message, 'SUCCESS');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    window.pushNotif('Error', data.message || 'Could not delete event.', 'ERROR');
                }
            } catch (err) {
                console.error('Delete Error:', err);
                window.showObsidianNotification('Network Error', 'Check your connection.', 'error');
            }
        }, 'Delete Event?', 'Are you sure you want to permanently remove this event?');
    };

    // if (btnOpenCreate) btnOpenCreate.onclick = () => openGlassModal('createEventModal');
    // if (btnOpenCreateTop) btnOpenCreateTop.onclick = () => openGlassModal('createEventModal');
    // if (btnEmptyCreate) btnEmptyCreate.onclick = () => openGlassModal('createEventModal');

    // Modals for details and participation have been replaced by card switchers.
    // Edit and Delete modals are still used but triggered via global window functions.

    const editForm = document.getElementById('editEventForm');

    window.closeEditModal = function () {
        // Switch back to details face if available, or just main
        const dashboardSwitcher = document.getElementById('evenementDashboardSwitcher');
        if (dashboardSwitcher) switchGlassCard('evenementDashboardSwitcher', 'main');
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

            // Transition using switchers instead of modals
            const cardSwitcher = btnOpenEdit.closest('.glass-switcher');
            if (cardSwitcher) {
                switchGlassCard(cardSwitcher.id, 'edit');
            }
        };
    }


    let isProcessingParticipation = false;

    async function handleAjaxParticipation(form) {
        if (isProcessingParticipation) return;

        const btn = form.querySelector('button[type="submit"]');
        if (!btn) return;

        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Processing...';
        btn.disabled = true;
        isProcessingParticipation = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await response.json();

            if (data.success) {
                window.pushNotif('Spot Secured!', data.message, 'SUCCESS');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                if (data.errors && form.validator) {
                    form.validator.mapErrors(data.errors);
                    const errorMsg = "Please correct the errors in the form.";
                    if (window.showCoolPopup) window.showCoolPopup('Oops!', errorMsg, 'error');
                    else window.pushNotif('Error', errorMsg, 'ERROR');
                } else {
                    const errorMsg = data.message || 'Submission failed.';
                    if (window.showCoolPopup) window.showCoolPopup('Oops!', errorMsg, 'error');
                    else window.pushNotif('Error', errorMsg, 'ERROR');
                }
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                isProcessingParticipation = false;
            }
        } catch (err) {
            console.error('Participation Error:', err);
            window.showObsidianNotification('Error', 'An unexpected error occurred.', 'error');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
            isProcessingParticipation = false;
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
    initializeCustomFormElements();

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
        const createModal = document.getElementById('createEventModal');
        const editModal = document.getElementById('editEventModal');
        const deleteConfirmModal = document.getElementById('obsidianDeleteModal'); // Fixed: use existing global modal

        if (event.target == createModal) switchGlassCard('evenementDashboardSwitcher', 'main');
        if (event.target == editModal) closeEditModal();
        if (event.target == deleteConfirmModal) window.closeObsidianDeleteModal();

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

                if (window.showCoolPopup) window.showCoolPopup(title, message, 'success');
                else window.pushNotif(title, message, 'SUCCESS');

                if (!isEdit) {
                    form.reset();
                    const dateInput = form.querySelector('.flatpickr-input');
                    if (dateInput && dateInput._flatpickr) dateInput._flatpickr.clear();
                    if (typeof switchGlassCard === 'function') switchGlassCard('evenementDashboardSwitcher', 'main');
                } else {
                    const cardSwitcher = form.closest('.glass-switcher');
                    if (cardSwitcher && typeof switchGlassCard === 'function') switchGlassCard(cardSwitcher.id, 'details');
                }
            } else {
                if (data.errors && form.validator) {
                    form.validator.mapErrors(data.errors);
                    const errorMsg = "Please check your form for errors.";
                    if (window.showCoolPopup) window.showCoolPopup('Check your form', errorMsg, 'error');
                    else window.pushNotif('Check your form', errorMsg, 'ERROR');
                } else {
                    const errorMsg = data.message || 'Validation failed.';
                    if (window.showCoolPopup) window.showCoolPopup('Oops!', errorMsg, 'error');
                    else window.pushNotif('Check your form', errorMsg, 'ERROR');
                }
            }
        } catch (error) {
            console.error('Event Action Error:', error);
            window.pushNotif('Network Error', 'Please try again later.', 'ERROR');
        } finally {
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    }
});

