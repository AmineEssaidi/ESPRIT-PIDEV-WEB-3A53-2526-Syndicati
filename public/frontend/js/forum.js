document.addEventListener('DOMContentLoaded', function () {
    console.log('Horizon Forum UI Loaded');

    // --- CACHING & STATE ---
    window.commentCache = new Map();
    let currentCategory = 'General';

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

    // List Item Click Handling (EVENT DELEGATION)
    const listWrapper = document.getElementById('forumListContainer');
    if (listWrapper) {
        listWrapper.addEventListener('click', (e) => {
            const item = e.target.closest('.forum-list-item');
            if (item) loadSplitView(item);
        });
    }

    // --- SHARED IMAGE PREVIEW LOGIC ---
    function setupImagePreview(input, previewContainer, imgElement, removeBtn) {
        if (!input || !previewContainer || !imgElement) return;

        input.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    imgElement.src = e.target.result;
                    previewContainer.classList.remove('d-none');
                };
                reader.readAsDataURL(this.files[0]);
            }
        });

        if (removeBtn) {
            removeBtn.onclick = () => {
                input.value = '';
                previewContainer.classList.add('d-none');
                imgElement.src = '';
            };
        }
    }

    // Init Post Edit Previews
    setupImagePreview(
        document.getElementById('editPostImageInput'),
        document.getElementById('newPostImagePreviewContainer'),
        document.getElementById('newPostImgPreview'),
        document.getElementById('removeNewPostImg')
    );

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
        if (d.image && d.image !== 'null' && d.image !== '') {
            heroEl.style.backgroundImage = `url(${d.image})`;
            heroEl.style.cursor = 'zoom-in';
            heroEl.onclick = () => window.openObsidianLightbox(d.image);
        } else {
            heroEl.style.backgroundImage = 'linear-gradient(135deg, #1a1a2e, #16213e)';
            heroEl.style.cursor = 'default';
            heroEl.onclick = null;
        }

        // --- AUTH CHECK: Show Edit/Delete ---
        const currentUserId = document.getElementById('currentUserId')?.value;
        const currentUserRole = document.getElementById('currentUserRole')?.value;

        // --- TOGGLE UNIFIED ACTION BAR ---
        const ownerActions = document.getElementById('splitViewOwnerActions');
        if (ownerActions) {
            // Check if current user is author or has elevated roles
            if (d.authorId == currentUserId || ['ADMIN', 'SUPERADMIN', 'OWNER', 'SYNDIC'].includes(currentUserRole)) {
                ownerActions.classList.remove('d-none');
            } else {
                ownerActions.classList.add('d-none');
            }
        }

        // --- COMMENTS ---
        // Hide comments for Announcements?
        const commentsSection = document.getElementById('commentsSection');
        if (d.categoriePub === 'Announcement') {
            commentsSection.classList.add('d-none');
        } else {
            commentsSection.classList.remove('d-none');
            const addCommentForm = document.getElementById('addCommentForm');
            if (addCommentForm) {
                addCommentForm.setAttribute('data-post-id', d.id);
            }

            // Check Cache first
            if (commentCache.has(d.id)) {
                renderComments(commentCache.get(d.id));
                // Still fetch in background to keep it fresh
                fetch(`/forum/comment/list/${d.id}`)
                    .then(r => r.json())
                    .then(comments => {
                        commentCache.set(d.id, comments);
                        renderComments(comments);
                    });
            } else {
                loadComments(d.id);
            }
        }

        // Reset Scroll to top
        const contentScroll = document.getElementById('splitViewContent');
        if (contentScroll) contentScroll.scrollTop = 0;

        // Load Reactions Status
        loadReactionStatus(d.id);
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
            // Reset Edit Previews
            document.getElementById('currentPostImageContainer').classList.add('d-none');
            document.getElementById('newPostImagePreviewContainer').classList.add('d-none');
            document.getElementById('editPostImageInput').value = '';

            const currentImg = window.currentPostData.image;
            if (currentImg && currentImg !== 'null' && currentImg !== '') {
                document.getElementById('currentPostImgPreview').src = currentImg;
                document.getElementById('currentPostImageContainer').classList.remove('d-none');
            }

            window.switchGlassFace('edit');
        };
    }

    const btnDeleteSplit = document.getElementById('btnDeleteSplit');
    if (btnDeleteSplit) {
        btnDeleteSplit.onclick = function () {
            window.confirmObsidianDelete(async function () {
                try {
                    const fd = new FormData();
                    fd.append('_token', window.currentPostData.token); // CSRF token from data-token

                    const res = await fetch(`/forum/delete/${window.currentPostId}`, {
                        method: 'POST',
                        body: fd,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await res.json();

                    if (data.success) {
                        window.showObsidianNotification('Deleted', data.message, 'success');
                        loadForumCategory(currentCategory);
                    } else {
                        window.showObsidianNotification('Error', data.message, 'error');
                    }
                } catch (err) {
                    console.error(err);
                    window.showObsidianNotification('Error', 'Failed to delete post', 'error');
                }
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
                window.showObsidianNotification('Success', data.message || 'Saved successfully', 'success');

                // PERFORMANCE FIX: Don't reload, just update UI
                if (form.id === 'createPostForm') {
                    // Refresh current category list via AJAX
                    loadForumCategory(currentCategory);
                    window.switchGlassFace('main');
                    form.reset();
                } else if (form.id === 'editPostForm') {
                    // Update current split view data
                    const activeItem = document.querySelector('.forum-list-item.active');
                    if (activeItem) {
                        // We need a way to refresh only this item or the whole list
                        loadForumCategory(currentCategory);
                    }
                    window.switchGlassFace('main');
                }

                btn.disabled = false;
                btn.innerHTML = originalText;
            } else {
                if (data.errors && typeof data.errors === 'object' && !Array.isArray(data.errors)) {
                    const validator = form.validator;
                    if (validator) {
                        validator.mapErrors(data.errors);
                        window.showObsidianNotification('Please Correct the Errors', 'Some fields require your attention.', 'error');
                    } else {
                        let msg = "Validation failed:<br>";
                        for (let key in data.errors) { msg += `- ${data.errors[key]}<br>`; }
                        window.showObsidianNotification('Please Correct the Errors', msg, 'error');
                    }
                } else {
                    window.showObsidianNotification('Error', data.message || (Array.isArray(data.errors) ? data.errors.join('<br>') : 'Validation failed'), 'error');
                }
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        } catch (err) {
            console.error(err);
            window.showObsidianNotification('Error', 'An unexpected error occurred', 'error');
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
                    window.showObsidianNotification('Commented', 'Your thought has been shared.', 'success');
                    this.reset();
                    // Delay refresh for better UX
                    setTimeout(() => loadComments(postId), 800);
                } else {
                    window.showObsidianNotification('Error', data.message || 'Error posting comment', 'error');
                }
            } catch (err) {
                console.error(err);
                window.showObsidianNotification('Connection Error', 'Failed to reach server.', 'error');
            }
        };
    }

    // --- REACTION LOGIC ---
    window.toggleReaction = async function (kind) {
        console.log('Toggling Reaction:', kind, 'for Post:', window.currentPostId);
        if (!window.currentPostId) {
            console.warn('No Post ID found for reaction');
            return;
        }

        const bar = document.getElementById('splitViewActionBar');
        const btn = bar ? bar.querySelector(`.btn-${kind.toLowerCase()}`) : null;
        if (!btn) return;

        try {
            const res = await fetch(`/forum/reaction/toggle/${window.currentPostId}/${kind}`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            console.log('Reaction Response:', data);

            if (data.success) {
                if (data.action === 'added') btn.classList.add('active');
                else btn.classList.remove('active');

                // Mutual Exclusion
                if (kind === 'Like' || kind === 'Dislike') {
                    const otherKind = kind === 'Like' ? 'Dislike' : 'Like';
                    const otherBtn = bar.querySelector(`.btn-${otherKind.toLowerCase()}`);
                    if (otherBtn) otherBtn.classList.remove('active');

                    // NEW: Mutual Exclusion with Emoji
                    const emojiBtn = bar.querySelector('.btn-emoji');
                    if (emojiBtn) emojiBtn.classList.remove('active');
                }

                if (kind === 'Bookmark') {
                    const bBtn = bar.querySelector('.btn-bookmark');
                    if (bBtn) {
                        const icon = bBtn.querySelector('i');
                        if (data.action === 'added') {
                            bBtn.classList.add('active');
                            if (icon) icon.className = 'bx bxs-bookmark fs-4';
                        } else {
                            bBtn.classList.remove('active');
                            if (icon) icon.className = 'bx bx-bookmark fs-4';
                        }
                    }
                }

                // Update Counts
                if (data.counts) updateReactionCounts(data.counts);

                // Optional: Play a tiny haptic or scale animation
                btn.style.transform = 'scale(1.2)';
                setTimeout(() => btn.style.transform = '', 200);

            } else {
                window.showObsidianNotification('Oops', data.message, 'error');
            }
        } catch (err) {
            console.error('Reaction Error:', err);
            window.showObsidianNotification('Network Error', 'Could not save your reaction.', 'error');
        }
    };

    window.toggleEmojiPicker = function (e) {
        e.stopPropagation();
        const picker = document.getElementById('emojiPicker');
        if (!picker) return;

        const isHidden = picker.classList.contains('d-none');
        if (isHidden) {
            picker.classList.remove('d-none');
            // Position near the button
            const rect = e.currentTarget.getBoundingClientRect();
            picker.style.position = 'fixed';
            picker.style.top = (rect.top - 115) + 'px';
            picker.style.left = rect.left + 'px';
            window.currentCommentingId = null; // Ensure we are NOT in comment mode
        } else {
            picker.classList.add('d-none');
        }
    };

    // Close picker when clicking outside
    document.addEventListener('click', () => {
        const picker = document.getElementById('emojiPicker');
        if (picker) picker.classList.add('d-none');
    });

    window.reactEmoji = async function (emoji) {
        console.log('Reacting with Emoji:', emoji);
        if (!window.currentPostId) return;

        try {
            const formData = new FormData();
            formData.append('emoji', emoji);
            const res = await fetch(`/forum/reaction/emoji/${window.currentPostId}`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            console.log('Emoji Response:', data);

            if (data.success) {
                const reactionsBar = document.getElementById('splitViewActionBar');
                if (!reactionsBar) return;

                const btn = reactionsBar.querySelector('.btn-emoji');
                if (data.action === 'added') {
                    btn.classList.add('active');
                    btn.innerHTML = `<span style="font-size:1.2rem;">${emoji}</span>`;

                    // NEW: Mutual Exclusion - Clear Like/Dislike
                    const likeBtn = reactionsBar.querySelector('.btn-like');
                    const dislikeBtn = reactionsBar.querySelector('.btn-dislike');
                    if (likeBtn) likeBtn.classList.remove('active');
                    if (dislikeBtn) dislikeBtn.classList.remove('active');
                } else {
                    btn.classList.remove('active');
                    btn.innerHTML = `<i class='bx bx-smile'></i>`;
                }

                // Hide Picker
                const picker = document.getElementById('emojiPicker');
                if (picker) picker.classList.add('d-none');

            } else {
                window.showObsidianNotification('Error', data.message, 'error');
            }
        } catch (err) { console.error(err); }
    };

    // NEW: Inline Reporting logic
    window.toggleReportPanel = function () {
        const panel = document.getElementById('reportPanel');
        if (!panel) return;

        if (panel.style.maxHeight === '0px' || !panel.style.maxHeight) {
            panel.style.maxHeight = '500px';
            panel.querySelector('textarea').focus();
        } else {
            panel.style.maxHeight = '0px';
        }
    };

    window.submitInlineReport = async function (e) {
        const bar = document.getElementById('splitViewActionBar');
        if (!bar || !window.currentPostId) return;

        const reasonEl = document.getElementById('reportReason');
        const reason = reasonEl.value.trim();

        if (!reason) {
            window.showObsidianNotification('Reason Required', 'Please provide a reason for the report.', 'error');
            return;
        }

        const btn = e ? e.currentTarget : null;
        const originalHtml = btn ? btn.innerHTML : 'Submit Report';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Submitting...';
        }

        try {
            const formData = new FormData();
            formData.append('reason', reason);

            const res = await fetch(`/forum/reaction/report/${window.currentPostId}`, {
                method: 'POST',
                body: formData
            });

            const data = await res.json();
            if (data.success) {
                window.showObsidianNotification('Reported', 'Thank you for your report. Our moderators will review it shortly.', 'success');
                reasonEl.value = '';
                window.toggleReportPanel(); // Close it
            } else {
                window.showObsidianNotification('Error', data.message || 'Error submitting report.', 'error');
            }
        } catch (err) {
            console.error(err);
            window.showObsidianNotification('Connection Error', 'Could not connect to the server.', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
    };

    async function loadReactionStatus(postId) {
        const reactionsBar = document.getElementById('splitViewActionBar');
        if (!reactionsBar) return;

        // Reset UI
        reactionsBar.querySelectorAll('.reaction-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.classList.contains('btn-bookmark')) {
                const icon = btn.querySelector('i');
                if (icon) icon.className = 'bx bx-bookmark fs-4';
            }
        });
        const emojiBtn = reactionsBar.querySelector('.btn-emoji');
        if (emojiBtn) emojiBtn.innerHTML = `<i class='bx bx-smile'></i>`;

        try {
            const res = await fetch(`/forum/reaction/status/${postId}`);
            const data = await res.json();

            // Highlight active buttons
            data.reactions.forEach(r => {
                const kind = r.kind.toLowerCase();
                const btn = reactionsBar.querySelector(`.btn-${kind}`);
                if (btn) {
                    btn.classList.add('active');
                    if (r.kind === 'Emoji') {
                        btn.innerHTML = `<span style="font-size:1.2rem;">${r.emoji}</span>`;
                    }
                    if (r.kind === 'Bookmark') {
                        const icon = btn.querySelector('i');
                        if (icon) icon.className = 'bx bxs-bookmark fs-4';
                    }
                }
            });

            // Update Counts
            if (data.counts) updateReactionCounts(data.counts);

        } catch (err) { console.error(err); }
    }
    window.loadReactionStatus = loadReactionStatus;

    function updateReactionCounts(counts) {
        const bar = document.getElementById('splitViewActionBar');
        if (!bar) {
            console.warn('splitViewActionBar not found for counts update');
            return;
        }

        ['Like', 'Dislike'].forEach(kind => {
            const countEl = bar.querySelector(`.btn-${kind.toLowerCase()} .count`);
            if (countEl) {
                const val = counts[kind] || 0;
                countEl.textContent = val > 0 ? val : '';
            }
        });

        // Update Publication Ratio Bar
        const ratioFill = document.querySelector('#publicationRatioBar .ratio-fill');
        const ratioText = document.getElementById('publicationRatioText');
        if (ratioFill) {
            const likes = counts.Like || 0;
            const dislikes = counts.Dislike || 0;
            const total = likes + dislikes;
            const percentage = total > 0 ? (likes / total) * 100 : 0;
            ratioFill.style.width = percentage + '%';
            if (ratioText) ratioText.textContent = Math.round(percentage) + '%';

            // Optional: Hide bar if no reactions
            const container = ratioFill.closest('.ratio-bar-container');
            if (container) container.style.opacity = total > 0 ? '1' : '0.3';
        }

        // Update Bookmark state if provided in counts/status
        const bookmarkBtn = bar.querySelector('.btn-bookmark');
        if (bookmarkBtn && counts.hasOwnProperty('isBookmarked')) {
            const icon = bookmarkBtn.querySelector('i');
            if (counts.isBookmarked) {
                bookmarkBtn.classList.add('active');
                if (icon) icon.className = 'bx bxs-bookmark fs-4';
            } else {
                bookmarkBtn.classList.remove('active');
                if (icon) icon.className = 'bx bx-bookmark fs-4';
            }
        }
    }

    window.loadComments = function (postId, containerId = 'commentsList', forceRefresh = false) {
        const list = document.getElementById(containerId);
        if (!list) {
            console.warn('Comment container not found:', containerId);
            return;
        }

        // --- CACHE CHECK ---
        if (!forceRefresh && window.commentCache && window.commentCache.has(postId)) {
            console.log('Serving comments from cache for post:', postId);
            window.renderComments(window.commentCache.get(postId), containerId);
            return;
        }

        list.innerHTML = '<div class="text-center text-white-50 p-3"><i class="bx bx-loader-alt bx-spin fs-4"></i> Syncing Discussion...</div>';

        fetch(`/forum/comment/list/${postId}`)
            .then(r => r.json())
            .then(comments => {
                if (!Array.isArray(comments)) {
                    console.error('Invalid comments data:', comments);
                    list.innerHTML = '<div class="text-danger p-3">Invalid response from server</div>';
                    return;
                }
                if (window.commentCache) window.commentCache.set(postId, comments);
                window.renderComments(comments, containerId);
            })
            .catch(err => {
                console.error('LoadComments Error:', err);
                list.innerHTML = '<div class="text-danger p-3">Failed to load comments</div>';
            });
    }

    window.renderComments = function (comments, containerId = 'commentsList') {
        const list = document.getElementById(containerId);
        if (!list) return;

        try {
            if (!comments || comments.length === 0) {
                list.innerHTML = `
                    <div class="text-center py-5 opacity-40 burst-in">
                        <div class="glass-icon-wrapper mx-auto mb-4" style="width: 80px; height: 80px; background: rgba(255,255,255,0.03); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="bx bx-message-rounded-x fs-1 text-white-50"></i>
                        </div>
                        <p class="mb-0 fw-medium letter-spacing-1">Silence is golden, but your thoughts are silver.</p>
                        <small class="text-white-50">Be the first to initiate the conversation.</small>
                    </div>
                `;
                return;
            }

            // --- BATCH RENDERING FOR EXTREME SPEED ---
            list.innerHTML = ''; // Clear initial injection
            const batchSize = 10;
            let index = 0;

            function renderBatch() {
                if (index >= comments.length) return;

                const fragment = document.createDocumentFragment();
                const tempDiv = document.createElement('div');

                const batch = comments.slice(index, index + batchSize);
                tempDiv.innerHTML = batch.map(c => {
                    if (!c || !c.author || !c.reactions || !c.counts) return '';

                    const isLiked = c.reactions.some(r => r.kind === 'Like');
                    const isDisliked = c.reactions.some(r => r.kind === 'Dislike');
                    const isMe = c.isOwner || false;
                    const emojiReaction = c.reactions.find(r => r.kind === 'Emoji');
                    const avatar = c.author.avatar || 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=200&h=200&fit=crop&crop=face';
                    const description = c.description || '<span class="text-white-50 small italic">Comment content missing</span>';

                    return `
                    <div class="comment-item p-4 mb-3 rounded-4 position-relative" data-id="${c.id}" 
                         style="background: ${isMe ? 'rgba(var(--syndicat-accent-rgb), 0.12)' : 'rgba(35,35,40,0.98)'}; 
                                border: 1px solid rgba(255,255,255,0.1); 
                                box-shadow: 0 8px 32px rgba(0,0,0,0.4); 
                                contain: content;
                                will-change: transform;">
                        
                        <div class="d-flex justify-content-between position-relative z-1 mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="forum-list-avatar border border-primary border-opacity-20" style="width: 40px; height: 40px; background: rgba(0,0,0,0.3);">
                                    <img src="${avatar}" alt="User" style="width:100%; height:100%; object-fit:cover;">
                                </div>
                                <div>
                                    <strong class="text-white d-block fs-6 fw-bold" style="letter-spacing: 0.2px;">${c.author.name}</strong>
                                    <div class="d-flex align-items-center gap-2">
                                        <small class="text-white-50 opacity-60 d-flex align-items-center gap-1" style="font-size: 0.7rem;">
                                            <i class="bx bx-time-five"></i> ${c.createdAt}
                                        </small>
                                        ${c.updatedAt ? `<span class="badge rounded-pill bg-white bg-opacity-5 text-white-50 fw-normal" style="font-size: 0.6rem; border: 1px solid rgba(255,255,255,0.05);">modified</span>` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-light opacity-90 mb-3 position-relative z-1 comment-text-content" style="white-space: pre-wrap; line-height: 1.6; font-size: 0.95rem; letter-spacing: 0.1px; min-height: 1.25rem; word-break: break-word; overflow-wrap: break-word;">${description}</div>
                        
                        ${c.image ? `
                            <div class="mb-3 position-relative z-1">
                                <div class="rounded-3 overflow-hidden border border-light border-opacity-10 shadow-lg" style="max-height: 300px; display: inline-block;">
                                    <img src="${c.image}" class="img-fluid cursor-zoom-in" style="object-fit: contain; max-height: 300px;" onclick="window.openObsidianLightbox ? window.openObsidianLightbox(this.src) : openLightbox ? openLightbox(this.src) : null">
                                </div>
                            </div>
                        ` : ''}

                        <div class="comment-actionBar d-flex align-items-center gap-2 mt-2 position-relative z-1 pt-3 border-top border-light border-opacity-5" style="display: flex !important; visibility: visible !important;">
                            <div class="d-flex align-items-center bg-white bg-opacity-5 rounded-pill p-1 border border-light border-opacity-10 shadow-sm">
                                <button class="comment-reaction-btn btn-like ${isLiked ? 'active' : ''} d-flex align-items-center gap-2 px-3 py-1 rounded-pill" style="background: transparent; border: none; transition: all 0.2s;" onclick="window.toggleCommentReaction(${c.id}, 'Like')">
                                    <i class='bx ${isLiked ? 'bxs-like text-success' : 'bx-like'} fs-5'></i> <span class="count fw-bold" style="font-size: 0.85rem; color: rgba(255,255,255,0.9);">${c.counts.Like || ''}</span>
                                </button>
                                <div style="width: 1px; height: 16px; background: rgba(255,255,255,0.15);"></div>
                                <button class="comment-reaction-btn btn-dislike ${isDisliked ? 'active' : ''} d-flex align-items-center gap-2 px-3 py-1 rounded-pill" style="background: transparent; border: none; transition: all 0.2s;" onclick="window.toggleCommentReaction(${c.id}, 'Dislike')">
                                    <i class='bx ${isDisliked ? 'bxs-dislike text-danger' : 'bx-dislike'} fs-5'></i> <span class="count fw-bold" style="font-size: 0.85rem; color: rgba(255,255,255,0.9);">${c.counts.Dislike || ''}</span>
                                </button>
                            </div>

                            <!-- Comment Ratio Bar -->
                            <div class="ratio-bar-container mx-2 d-flex align-items-center gap-2" style="width: 80px; opacity: ${(c.counts.Like + c.counts.Dislike) > 0 ? '1' : '0.3'};">
                                <div class="ratio-bar flex-grow-1" style="height: 3px; background: #f87171; border-radius: 10px; overflow: hidden; position: relative; width: 100%;">
                                    <div class="ratio-fill" style="width: ${(c.counts.Like + c.counts.Dislike) > 0 ? (c.counts.Like / (c.counts.Like + c.counts.Dislike)) * 100 : 0}%; height: 100%; background: #4ade80; transition: width 0.6s ease;"></div>
                                </div>
                                <small class="ratio-text text-white-50" style="font-size: 0.65rem; min-width: 25px;">${(c.counts.Like + c.counts.Dislike) > 0 ? Math.round((c.counts.Like / (c.counts.Like + c.counts.Dislike)) * 100) : 0}%</small>
                            </div>

                            <button class="comment-reaction-btn btn-emoji ${emojiReaction ? 'active' : ''} d-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15); transition: all 0.2s;" onclick="window.toggleCommentEmojiPicker(event, ${c.id})">
                                ${emojiReaction ? `<span style="font-size:1.1rem;">${emojiReaction.emoji}</span>` : `<i class='bx bx-smile fs-5'></i>`}
                            </button>

                            <div style="width: 1px; height: 20px; background: rgba(255,255,255,0.05); margin: 0 2px;"></div>

                            <div class="d-flex align-items-center gap-1">
                                ${c.canEdit ? `
                                <button class="btn btn-icon text-white-50 hover-text-primary rounded-circle" style="width: 36px; height: 36px;" onclick="window.toggleCommentEditPanel(${c.id})">
                                    <i class='bx bx-edit-alt fs-5'></i>
                                </button>` : ''}
                                ${c.canDelete ? `
                                <button class="btn btn-icon text-white-50 hover-text-danger rounded-circle" style="width: 36px; height: 36px;" onclick="window.deleteComment(${c.id}, '${c.deleteToken}')">
                                    <i class='bx bx-trash fs-5'></i>
                                </button>` : ''}
                            </div>

                            <button class="btn btn-icon text-white-50 hover-text-danger rounded-pill px-3 ms-auto d-flex align-items-center gap-2" style="height: 36px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);" onclick="window.toggleCommentReportPanel(${c.id})">
                                <i class='bx bx-flag fs-6'></i> <span class="small fw-bold">Report</span>
                            </button>
                        </div>

                        <div id="commentEditPanel-${c.id}" class="comment-edit-panel overflow-hidden mt-4" style="max-height: 0; transition: max-height 0.4s ease;">
                            <div class="p-4 rounded-4" style="background: rgba(var(--syndicat-accent-rgb), 0.1); border: 1px solid rgba(var(--syndicat-accent-rgb), 0.2);">
                                <h6 class="text-white mb-4 fw-bold small">Refine your message</h6>
                                <textarea class="glass-textarea edit-content-textarea mb-4 w-100 p-3 rounded-4 text-white" rows="3" style="background: rgba(0,0,0,0.5) !important; border: 1px solid rgba(255,255,255,0.1); resize: none;">${c.description}</textarea>
                                <div class="d-flex justify-content-end gap-3">
                                    <button onclick="window.toggleCommentEditPanel(${c.id})" class="btn btn-link text-white-50 text-decoration-none small">Discard</button>
                                    <button onclick="window.submitCommentEdit(${c.id})" class="btn btn-primary rounded-pill px-4 py-2 fw-bold">Save Changes</button>
                                </div>
                            </div>
                        </div>

                        <div id="commentReportPanel-${c.id}" class="comment-report-panel overflow-hidden mt-4" style="max-height: 0; transition: max-height 0.4s ease;">
                            <div class="p-4 rounded-4" style="background: rgba(220, 38, 38, 0.1); border: 1px solid rgba(220, 38, 38, 0.2);">
                                <h6 class="text-white mb-3 fw-bold small">Report Violation</h6>
                                <textarea class="glass-textarea report-reason-textarea mb-4 w-100 p-3 rounded-4 text-white" rows="2" placeholder="Reason..." style="background: rgba(0,0,0,0.5) !important; border: 1px solid rgba(255,255,255,0.1); resize: none;"></textarea>
                                <div class="d-flex justify-content-end gap-3">
                                    <button onclick="window.toggleCommentReportPanel(${c.id})" class="btn btn-link text-white-50 text-decoration-none small">Dismiss</button>
                                    <button onclick="window.submitCommentReport(${c.id})" class="btn btn-danger rounded-pill px-4 py-2 fw-bold">Submit</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    `;
                }).join('');

                while (tempDiv.firstChild) {
                    fragment.appendChild(tempDiv.firstChild);
                }
                list.appendChild(fragment);

                index += batchSize;
                if (index < comments.length) {
                    requestAnimationFrame(renderBatch);
                }
            }

            renderBatch();
        } catch (err) {
            console.error('RenderComments Error:', err);
            const list = document.getElementById(containerId);
            if (list) list.innerHTML = '<div class="text-danger p-3">Error rendering discussion</div>';
        }
    }

    // --- COMMENT REACTION HANDLERS ---
    window.toggleCommentReaction = async function (commentId, kind) {
        const item = document.querySelector(`.comment-item[data-id="${commentId}"]`);
        if (!item) return;
        const btn = item.querySelector(`.btn-${kind.toLowerCase()}`);

        try {
            const res = await fetch(`/forum/reaction/comment/toggle/${commentId}/${kind}`, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (data.success) {
                if (data.action === 'added') btn.classList.add('active');
                else btn.classList.remove('active');

                // Mutual Exclusion
                if (kind === 'Like' || kind === 'Dislike') {
                    const otherKind = kind === 'Like' ? 'Dislike' : 'Like';
                    const otherBtn = item.querySelector(`.btn-${otherKind.toLowerCase()}`);
                    if (otherBtn) {
                        otherBtn.classList.remove('active');
                        const otherIcon = otherBtn.querySelector('i');
                        if (otherIcon) otherIcon.className = `bx bx-${otherKind.toLowerCase()}`;
                    }

                    const emojiBtn = item.querySelector('.btn-emoji');
                    if (emojiBtn) {
                        emojiBtn.classList.remove('active');
                        emojiBtn.innerHTML = `<i class='bx bx-smile fs-5'></i>`;
                    }

                    const icon = btn.querySelector('i');
                    if (icon) icon.className = (data.action === 'added') ? `bx bxs-${kind.toLowerCase()}` : `bx bx-${kind.toLowerCase()}`;
                }

                if (data.counts) {
                    const likes = data.counts.Like || 0;
                    const dislikes = data.counts.Dislike || 0;
                    const total = likes + dislikes;

                    ['Like', 'Dislike'].forEach(k => {
                        const countEl = item.querySelector(`.btn-${k.toLowerCase()} .count`);
                        if (countEl) countEl.textContent = data.counts[k] > 0 ? data.counts[k] : '';
                    });

                    // Update Comment Ratio Bar
                    const ratioFill = item.querySelector('.ratio-fill');
                    const ratioText = item.querySelector('.ratio-text');
                    if (ratioFill) {
                        const percentage = total > 0 ? (likes / total) * 100 : 0;
                        ratioFill.style.width = percentage + '%';
                        if (ratioText) ratioText.textContent = Math.round(percentage) + '%';
                        ratioFill.closest('.ratio-bar-container').style.opacity = total > 0 ? '1' : '0.3';
                    }
                }
            }
        } catch (err) { console.error(err); }
    };

    window.toggleCommentEmojiPicker = function (e, commentId) {
        e.stopPropagation();
        // For comments, we can use the same picker but change where it reacts
        window.currentCommentingId = commentId; // Track which comment we are reacting to
        const picker = document.getElementById('emojiPicker');
        if (picker) {
            picker.classList.toggle('d-none');
            // Position near the button
            const rect = e.currentTarget.getBoundingClientRect();
            picker.style.position = 'fixed';
            picker.style.top = (rect.top - 105) + 'px';
            picker.style.left = rect.left + 'px';
        }
    };

    window.toggleCommentReportPanel = function (commentId) {
        // Close Edit panel if open
        const editPanel = document.getElementById(`commentEditPanel-${commentId}`);
        if (editPanel) editPanel.style.maxHeight = '0px';

        const panel = document.getElementById(`commentReportPanel-${commentId}`);
        if (!panel) return;
        panel.style.maxHeight = (panel.style.maxHeight === '0px' || !panel.style.maxHeight) ? '400px' : '0px';
    };

    window.toggleCommentEditPanel = function (commentId) {
        // Close Report panel if open
        const reportPanel = document.getElementById(`commentReportPanel-${commentId}`);
        if (reportPanel) reportPanel.style.maxHeight = '0px';

        const panel = document.getElementById(`commentEditPanel-${commentId}`);
        if (!panel) return;
        panel.style.maxHeight = (panel.style.maxHeight === '0px' || !panel.style.maxHeight) ? '400px' : '0px';
    };

    window.submitCommentEdit = async function (commentId) {
        const panel = document.getElementById(`commentEditPanel-${commentId}`);
        const textEl = panel.querySelector('textarea');
        const content = textEl.value.trim();
        if (!content) return;

        try {
            const fd = new FormData();
            fd.append('description', content);

            // Handle image edit
            const imgInput = panel.querySelector('.edit-comment-image-input');
            if (imgInput && imgInput.files && imgInput.files[0]) {
                fd.append('image_commentaire', imgInput.files[0]);
            }

            const res = await fetch(`/forum/comment/edit/${commentId}`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                window.showObsidianNotification('Updated', 'Comment saved successfully.', 'success');
                window.toggleCommentEditPanel(commentId);

                // Update in-place for instant feedback
                const item = document.querySelector(`.comment-item[data-id="${commentId}"]`);
                if (item) {
                    const descEl = item.querySelector('.text-white-50.ps-4');
                    if (descEl) descEl.textContent = content;

                    const small = item.querySelector('small.text-white-50');
                    if (small && !small.innerHTML.includes('(edited)')) {
                        small.innerHTML += ' <span class="ms-1" style="opacity:0.6;">(edited)</span>';
                    }
                }

                // Clear cache to ensure fresh load next time
                if (window.commentCache) window.commentCache.delete(window.currentPostId);

                // Refresh comments list after a short delay to ensure notification is visible
                setTimeout(() => loadComments(window.currentPostId, 'commentsList', true), 1000);
            }
        } catch (err) { console.error(err); }
    };

    window.deleteComment = function (commentId, token) {
        window.confirmObsidianDelete(async function () {
            try {
                const fd = new FormData();
                fd.append('_token', token);
                const res = await fetch(`/forum/comment/delete/${commentId}`, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    window.showObsidianNotification('Deleted', 'Comment removed.', 'success');
                    if (window.commentCache) window.commentCache.delete(window.currentPostId);
                    loadComments(window.currentPostId, 'commentsList', true);
                }
            } catch (err) { console.error(err); }
        }, 'Delete Comment?', 'Are you sure you want to permanently remove this comment?');
    };

    window.submitCommentReport = async function (commentId) {
        const panel = document.getElementById(`commentReportPanel-${commentId}`);
        const reasonEl = panel.querySelector('textarea');
        const reason = reasonEl.value.trim();
        if (!reason) return;

        try {
            const fd = new FormData();
            fd.append('reason', reason);
            const res = await fetch(`/forum/reaction/comment/report/${commentId}`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                window.showObsidianNotification('Reported', 'Thank you for your report.', 'success');
                window.toggleCommentReportPanel(commentId);
                reasonEl.value = '';
            }
        } catch (err) { console.error(err); }
    };

    // Need to update reactEmoji to handle comments too
    const originalReactEmoji = window.reactEmoji;
    window.reactEmoji = async function (emoji) {
        if (window.currentCommentingId) {
            // Reaction for comment
            const commentId = window.currentCommentingId;
            try {
                const fd = new FormData();
                fd.append('emoji', emoji);
                const res = await fetch(`/forum/reaction/comment/emoji/${commentId}`, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    const item = document.querySelector(`.comment-item[data-id="${commentId}"]`);
                    const btn = item.querySelector('.btn-emoji');
                    if (data.action === 'added') {
                        btn.classList.add('active');
                        btn.innerHTML = `<span style="font-size:1.1rem;">${emoji}</span>`;
                        // Clear Like/Dislike
                        item.querySelector('.btn-like').classList.remove('active');
                        item.querySelector('.btn-dislike').classList.remove('active');
                        item.querySelector('.btn-like i').className = 'bx bx-like';
                        item.querySelector('.btn-dislike i').className = 'bx bx-dislike';
                    } else {
                        btn.classList.remove('active');
                        btn.innerHTML = `<i class='bx bx-smile fs-5'></i>`;
                    }

                    // Update Counts & Ratio Bar if returned
                    if (data.counts) {
                        const likes = data.counts.Like || 0;
                        const dislikes = data.counts.Dislike || 0;
                        const total = likes + dislikes;

                        ['Like', 'Dislike'].forEach(k => {
                            const countEl = item.querySelector(`.btn-${k.toLowerCase()} .count`);
                            if (countEl) countEl.textContent = data.counts[k] > 0 ? data.counts[k] : '';
                        });

                        const ratioFill = item.querySelector('.ratio-fill');
                        const ratioText = item.querySelector('.ratio-text');
                        if (ratioFill) {
                            const percentage = total > 0 ? (likes / total) * 100 : 0;
                            ratioFill.style.width = percentage + '%';
                            if (ratioText) ratioText.textContent = Math.round(percentage) + '%';
                            ratioFill.closest('.ratio-bar-container').style.opacity = total > 0 ? '1' : '0.3';
                        }
                    }
                }
                document.getElementById('emojiPicker').classList.add('d-none');
                window.currentCommentingId = null;
            } catch (err) { console.error(err); }
        } else {
            // Publication reaction
            originalReactEmoji(emoji);
        }
    };

    // --- CATEGORY FILTER LOGIC ---
    window.loadForumCategory = async function (category, btn) {
        currentCategory = category;
        const container = document.getElementById('forumListContainer');
        if (!container) return;

        // UI Feedback
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';

        // Update Active Button
        document.querySelectorAll('.btn-sidebar-action').forEach(b => b.classList.remove('active'));
        if (btn) {
            btn.classList.add('active');
        } else {
            // Find button manually if called programmatically
            const targetBtn = document.getElementById(category === 'Announcement' ? 'btnFilterAnnouncement' : 'btnFilterGeneral');
            if (targetBtn) targetBtn.classList.add('active');
        }

        try {
            const response = await fetch(`/forum/ajax/list?category=${category}`);
            const html = await response.text();

            container.innerHTML = html;
            container.style.opacity = '1';
            container.style.pointerEvents = 'all';

            // NO NEED TO RE-BIND (Event Delegation handles it)

            // If we are just refreshing after a post, don't necessarily reload first item unless none selected
            const activeItem = container.querySelector('.forum-list-item.active');
            if (!activeItem) {
                const firstChild = container.querySelector('.forum-list-item');
                if (firstChild) loadSplitView(firstChild);
            }
        } catch (err) {
            console.error('Failed to load category:', err);
            container.style.opacity = '1';
            container.style.pointerEvents = 'all';
        }
    };

    window.previewCommentEditImage = function (input, id) {
        const previewContainer = document.getElementById(`newCommentPreviewContainer-${id}`);
        const previewImg = document.getElementById(`newCommentImgPreview-${id}`);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                previewImg.src = e.target.result;
                previewContainer.classList.remove('d-none');
            }
            reader.readAsDataURL(input.files[0]);
        }
    };
});
