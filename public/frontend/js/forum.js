document.addEventListener('DOMContentLoaded', function () {
    console.log('Horizon Forum UI Loaded');

    // --- GLOBAL VARIABLES ---
    const glassSwitcher = document.getElementById('forumGlassSwitcher');
    const createPostForm = document.getElementById('createPostForm');
    const editPostForm = document.getElementById('editPostForm');

    // --- GLASS SWITCHER HELPER ---
    window.switchGlassFace = function (face, isAnnouncement = false) {
        if (!glassSwitcher) {
            console.error('Glass Switcher not found!');
            return;
        }

        // Reset Active States
        glassSwitcher.classList.remove('active-extra', 'active-edit');

        // Logic for Faces
        if (face === 'extra') {
            glassSwitcher.classList.add('active-extra');

            // Handle Announcement Logic if opening Create Form
            const categorySelect = document.getElementById('createCategorySelect');
            const categoryGroup = document.getElementById('createCategoryGroup');

            if (categorySelect && categoryGroup) {
                if (isAnnouncement) {
                    categorySelect.value = 'Announcement';
                    categoryGroup.style.display = 'none'; // Hide selection for forced announcement
                } else {
                    categoryGroup.style.display = 'block';
                    // If it was stuck on announcement, reset it
                    if (categorySelect.value === 'Announcement') {
                        categorySelect.selectedIndex = 0;
                    }
                }
            }
        }
        else if (face === 'edit') {
            glassSwitcher.classList.add('active-edit');
        }
        else {
            // Main Face (default)
            // No class needed
        }
    };

    // --- SPLIT VIEW LOGIC ---
    // Load the first post automatically if available
    const firstItem = document.querySelector('.forum-list-item');
    if (firstItem) {
        loadSplitView(firstItem);
    }

    // List Item Click Handling
    document.querySelectorAll('.forum-list-item').forEach(item => {
        item.addEventListener('click', () => loadSplitView(item));
    });

    function loadSplitView(item) {
        // Switch to Main View immediately
        window.switchGlassFace('main');

        // Active State in Sidebar
        document.querySelectorAll('.forum-list-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');

        // Extract Data
        const d = item.dataset;
        window.currentPostId = d.id;
        window.currentPostData = { ...d };

        // Populate Main View
        document.getElementById('splitViewTitle').textContent = d.title;
        document.getElementById('splitViewDate').textContent = d.date;
        document.getElementById('splitViewAuthor').textContent = d.user;

        // Category Pill with proper styling
        const categoryEl = document.getElementById('splitViewCategory');
        const categoryClass = 'cat-' + d.categoriePub.toLowerCase().replace(/\s+/g, '-');
        categoryEl.className = 'forum-list-category ' + categoryClass;
        categoryEl.textContent = d.categoriePub;

        const descEl = document.getElementById('splitViewDescription');
        descEl.innerHTML = d.description.replace(/\n/g, '<br>');

        // Author Avatar
        const avatarEl = document.getElementById('splitViewAvatar');
        if (d.avatar) {
            avatarEl.innerHTML = `<img src="${d.avatar}" alt="${d.user}" style="width:100%;height:100%;object-fit:cover;">`;
        } else {
            avatarEl.innerHTML = d.userInitial || d.user.charAt(0).toUpperCase();
        }

        // Hero Background
        const heroEl = document.getElementById('splitViewHero');
        if (d.image) {
            heroEl.style.backgroundImage = `url(${d.image})`;
        } else {
            heroEl.style.backgroundImage = 'linear-gradient(135deg, #1a1a2e, #16213e)';
        }

        // --- AUTH CHECK: Show Edit/Delete ---
        const actionsEl = document.getElementById('splitViewActions');
        const currentUserId = document.getElementById('currentUserId')?.value;
        const currentUserRole = document.getElementById('currentUserRole')?.value;

        const isAuthor = currentUserId && d.authorId && String(currentUserId) === String(d.authorId);
        const isAdmin = currentUserRole && ['ADMIN', 'SUPERADMIN', 'OWNER', 'SYNDIC'].includes(currentUserRole);

        if (isAuthor || isAdmin) {
            actionsEl.classList.remove('d-none');
        } else {
            actionsEl.classList.add('d-none');
        }

        // --- COMMENTS ---
        // Hide comments for Announcements?
        const commentsSection = document.getElementById('commentsSection');
        if (d.categoriePub === 'Announcement') {
            commentsSection.classList.add('d-none');
        } else {
            commentsSection.classList.remove('d-none');
            // Update Comment Form ID
            const addCommentForm = document.getElementById('addCommentForm');
            if (addCommentForm) {
                addCommentForm.setAttribute('data-post-id', d.id);
                // addCommentForm.dataset.postId = d.id;
            }
            loadComments(d.id);
        }
    }

    // --- BUTTON HOOKS ---
    const btnEditSplit = document.getElementById('btnEditSplit');
    if (btnEditSplit) {
        btnEditSplit.onclick = function () {
            // Populate Edit Form
            document.getElementById('editPostTitle').value = window.currentPostData.title;
            document.getElementById('editPostDescription').value = window.currentPostData.description;
            document.getElementById('editPostCategory').value = window.currentPostData.categoriePub;
            document.getElementById('editPostForm').action = `/forum/edit/${window.currentPostId}`;

            // Switch
            window.switchGlassFace('edit');
        };
    }

    const btnDeleteSplit = document.getElementById('btnDeleteSplit');
    if (btnDeleteSplit) {
        btnDeleteSplit.onclick = function () {
            // Use Global Obsidian Red Popup
            window.confirmDelete(function () {
                window.location.href = `/forum/delete/${window.currentPostId}`;
            });
        };
    }
    // btnConfirmDeleteAction is no longer needed as global modal handles it

    // --- AJAX FORM SUBMISSIONS ---
    // Universal Handler function
    async function handleAjaxForm(e, form) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Processing...';

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action || window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();

            if (data.success) {
                window.showCoolPopup('Success', data.message || 'Saved successfully', 'success');
                setTimeout(() => window.location.reload(), 1000); // Reload to secure state
            } else {
                if (data.errors && typeof data.errors === 'object' && !Array.isArray(data.errors)) {
                    const validator = form.validator; // Validator attached in template
                    if (validator) {
                        validator.mapErrors(data.errors);
                        window.showCoolPopup('Please Correct the Errors', 'Some fields require your attention.', 'error');
                    } else {
                        let msg = "Validation failed:<br>";
                        for (let key in data.errors) { msg += `- ${data.errors[key]}<br>`; }
                        window.showCoolPopup('Please Correct the Errors', msg, 'error');
                    }
                } else {
                    window.showCoolPopup('Error', data.message || (Array.isArray(data.errors) ? data.errors.join('<br>') : 'Validation failed'), 'error');
                }
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (err) {
            console.error(err);
            window.showCoolPopup('Error', 'An unexpected error occurred', 'error');
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    }

    if (createPostForm) createPostForm.onsubmit = (e) => handleAjaxForm(e, createPostForm);
    if (editPostForm) editPostForm.onsubmit = (e) => handleAjaxForm(e, editPostForm);

    // --- COMMENT LOGIC ---
    // Add Comment (AJAX)
    const addCommentForm = document.getElementById('addCommentForm');
    if (addCommentForm) {
        addCommentForm.onsubmit = async function (e) {
            e.preventDefault();
            const postId = this.getAttribute('data-post-id');
            const fd = new FormData(this);

            try {
                const res = await fetch(`/forum/comment/add/${postId}`, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    this.reset();
                    loadComments(postId);
                } else {
                    alert('Error posting comment');
                }
            } catch (err) { console.error(err); }
        };
    }

    function loadComments(postId) {
        const list = document.getElementById('commentsList');
        list.innerHTML = '<div class="text-center text-white-50 p-3">Loading comments...</div>';

        fetch(`/forum/comment/list/${postId}`)
            .then(r => r.json())
            .then(comments => {
                if (comments.length === 0) {
                    list.innerHTML = '<div class="text-center text-white-50 p-3">No comments yet. Be the first!</div>';
                    return;
                }

                list.innerHTML = comments.map(c => `
                    <div class="comment-item p-3 mb-3 rounded-4" style="background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05);">
                        <div class="d-flex justify-content-between">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <div class="forum-list-avatar" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    ${c.author.avatar ? `<img src="${c.author.avatar}" alt="User">` : c.author.name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <strong class="text-white d-block" style="line-height:1.2;">${c.author.name}</strong>
                                    <small class="text-white-50" style="font-size: 0.75rem;">${c.createdAt}</small>
                                </div>
                            </div>
                            <!-- Actions could go here -->
                        </div>
                        <div class="text-white-50 ps-5">${c.description}</div>
                        ${c.image ? `<div class="ps-5"><img src="${c.image}" class="mt-2 rounded-3" style="max-height:200px;"></div>` : ''}
                    </div>
                `).join('');
            })
            .catch(() => {
                list.innerHTML = '<div class="text-danger p-3">Failed to load comments</div>';
            });
    }

});
