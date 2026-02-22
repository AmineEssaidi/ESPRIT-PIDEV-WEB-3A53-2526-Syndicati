// --- POPUP UTILITIES ---
window.showCoolPopup = function (title, message, type = 'success') {
    const p = document.getElementById('coolPopup');
    if (!p) { alert(title + ': ' + message); return; }
    document.getElementById('coolPopupTitle').textContent = title;
    document.getElementById('coolPopupMessage').innerHTML = message;
    document.getElementById('coolPopupIcon').innerHTML = type === 'success' ? '✅' : (type === 'error' ? '❌' : 'ℹ️');
    p.style.display = 'flex';
};

window.handleCommentAction = async function (pubId, commentId, action, type = null) {
    let url = `/forum/action/comment/${action}/${commentId}`;
    if (type) url += `/${type}`;
    console.log('Horizon: Comment Action', action, commentId);

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json();

        if (data.success) {
            const commentEl = document.querySelector(`.comment-item[data-id="${commentId}"]`);
            if (commentEl) {
                const btnLike = commentEl.querySelector('.btn-comment-like');
                const btnDislike = commentEl.querySelector('.btn-comment-dislike');
                const btnSignal = commentEl.querySelector('.btn-comment-signal');

                if (action === 'react') {
                    if (type === 'like') {
                        btnLike?.classList.toggle('active');
                        btnDislike?.classList.remove('active');
                    } else if (type === 'dislike') {
                        btnDislike?.classList.toggle('active');
                        btnLike?.classList.remove('active');
                    }
                } else if (action === 'report') {
                    btnSignal?.classList.toggle('active');
                }

                if (data.counts) {
                    const cl = commentEl.querySelector('.count-comment-like');
                    const cd = commentEl.querySelector('.count-comment-dislike');
                    const cr = commentEl.querySelector('.count-comment-report');
                    if (cl && data.counts.likes !== undefined) cl.textContent = data.counts.likes;
                    if (cd && data.counts.dislikes !== undefined) cd.textContent = data.counts.dislikes;
                    if (cr && data.counts.reports !== undefined) cr.textContent = data.counts.reports;
                }
            }
        }
    } catch (err) {
        console.error('Comment AJAX Error:', err);
    }
};

// --- GLOBAL FUNCTIONS ---

window.handleForumFilter = async function (btn, type, url) {
    if (!btn || !url) return;
    console.log('Horizon: [FILTER]', type, url);

    const filterContainer = document.getElementById('forumFilters');
    const listContainer = document.getElementById('forumListContainer');
    if (!filterContainer || !listContainer) return;

    // Visual feedback
    filterContainer.querySelectorAll('.btn-sidebar-action').forEach(l => l.classList.remove('primary'));
    btn.classList.add('primary');
    listContainer.style.opacity = '0.5';
    listContainer.style.pointerEvents = 'none';

    try {
        const ajaxUrl = `${url}${url.includes('?') ? '&' : '?'}ajax_filter=1`;
        const res = await fetch(ajaxUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        });

        if (res.ok) {
            const data = await res.json();
            if (data.success) {
                listContainer.innerHTML = data.html;
                window.history.pushState({ type: type }, '', url);
                const firstItem = listContainer.querySelector('.forum-list-item');
                if (firstItem && typeof window.loadSplitView === 'function') window.loadSplitView(firstItem);

                // Success! Return early.
                listContainer.style.opacity = '1';
                listContainer.style.pointerEvents = 'auto';
                return;
            }
        }
    } catch (err) {
        console.error('Horizon: AJAX Filter failed, falling back to redirect:', err);
    }

    // FINAL FALLBACK: If AJAX failed or returned error, just refresh the page to that URL
    window.location.href = url;
};

window.switchGlassFace = function (face, isAnnouncement = false) {
    const glassSwitcher = document.getElementById('forumGlassSwitcher');
    if (!glassSwitcher) return;

    glassSwitcher.classList.remove('active-extra', 'active-edit');
    if (face === 'extra') {
        glassSwitcher.classList.add('active-extra');
        const categorySelect = document.getElementById('createCategorySelect');
        const categoryGroup = document.getElementById('createCategoryGroup');
        if (categorySelect && categoryGroup) {
            if (isAnnouncement) {
                categorySelect.value = 'Announcement';
                categoryGroup.style.display = 'none';
            } else {
                categoryGroup.style.display = 'block';
                if (categorySelect.value === 'Announcement') categorySelect.selectedIndex = 0;
            }
        }
    }
    else if (face === 'edit') glassSwitcher.classList.add('active-edit');
};

window.loadSplitView = function (item) {
    if (!item) return;
    try {
        window.switchGlassFace('main');
        document.querySelectorAll('.forum-list-item').forEach(i => i.classList.remove('active'));
        item.classList.add('active');

        const d = item.dataset;
        window.currentPostId = d.id;
        window.currentPostData = { ...d };

        if (document.getElementById('splitViewTitle')) document.getElementById('splitViewTitle').textContent = d.title || '';
        if (document.getElementById('splitViewDate')) document.getElementById('splitViewDate').textContent = d.date || '';
        if (document.getElementById('splitViewAuthor')) document.getElementById('splitViewAuthor').textContent = d.user || '';

        const categoryEl = document.getElementById('splitViewCategory');
        if (categoryEl && d.categoriePub) {
            const categoryClass = 'cat-' + d.categoriePub.toLowerCase().replace(/\s+/g, '-');
            categoryEl.className = 'forum-list-category ' + categoryClass;
            categoryEl.textContent = d.categoriePub;
        }

        const descEl = document.getElementById('splitViewDescription');
        if (descEl) descEl.innerHTML = (d.description || '').replace(/\n/g, '<br>');

        const avatarEl = document.getElementById('splitViewAvatar');
        if (avatarEl) {
            if (d.avatar) {
                avatarEl.innerHTML = `<img src="${d.avatar}" alt="${d.user || ''}" style="width:100%;height:100%;object-fit:cover;">`;
            } else {
                avatarEl.innerHTML = d.userInitial || (d.user ? d.user.charAt(0).toUpperCase() : '?');
            }
        }

        const heroEl = document.getElementById('splitViewHero');
        if (heroEl) heroEl.style.backgroundImage = d.image ? `url(${d.image})` : 'linear-gradient(135deg, #1a1a2e, #16213e)';

        const ownerActionsEl = document.getElementById('ownerActions');
        const splitViewActionsEl = document.getElementById('splitViewActions');
        const currentUserId = document.getElementById('currentUserId')?.value;
        const currentUserRole = document.getElementById('currentUserRole')?.value;

        const activeUserId = currentUserId ? String(currentUserId).trim() : null;
        const authorId = d.authorId ? String(d.authorId).trim() : null;

        const isAuthor = activeUserId && authorId && activeUserId === authorId;
        const isAdmin = currentUserRole && ['ADMIN', 'SUPERADMIN', 'OWNER', 'SYNDIC'].includes(currentUserRole);

        if (splitViewActionsEl) {
            if (activeUserId) splitViewActionsEl.classList.remove('d-none');
            else splitViewActionsEl.classList.add('d-none');
        }

        if (ownerActionsEl) {
            if (isAuthor || isAdmin) ownerActionsEl.classList.remove('d-none');
            else ownerActionsEl.classList.add('d-none');
        }

        const commentsSection = document.getElementById('commentsSection');
        if (commentsSection) {
            const isAnnonce = d.categoriePub && (d.categoriePub.toLowerCase() === 'announcement' || d.categoriePub.toLowerCase() === 'annonce');
            if (isAnnonce) {
                commentsSection.classList.add('d-none');
            } else {
                commentsSection.classList.remove('d-none');
                const addCommentForm = document.getElementById('addCommentForm');
                if (addCommentForm) {
                    addCommentForm.action = `/forum/comment/add/${d.id}`;
                    addCommentForm.setAttribute('data-post-id', d.id);
                }
                if (typeof window.loadComments === 'function') window.loadComments(d.id);
            }
        }

        const btnLike = document.getElementById('btnLikeSplit');
        const btnDislike = document.getElementById('btnDislikeSplit');
        const btnBookmark = document.getElementById('btnBookmarkSplit');
        const btnSignal = document.getElementById('btnSignalSplit');

        [btnLike, btnDislike, btnBookmark, btnSignal].forEach(btn => btn?.classList.remove('active'));

        if (d.userReaction === 'like') btnLike?.classList.add('active');
        if (d.userReaction === 'dislike') btnDislike?.classList.add('active');
        if (d.userBookmark === 'true' || d.userBookmark === '1') btnBookmark?.classList.add('active');
        if (d.userReport === 'true' || d.userReport === '1') btnSignal?.classList.add('active');

        const countLikeEl = document.getElementById('countLike');
        const countDislikeEl = document.getElementById('countDislike');
        const countReportEl = document.getElementById('countReport');

        if (countLikeEl) countLikeEl.textContent = d.countLike || 0;
        if (countDislikeEl) countDislikeEl.textContent = d.countDislike || 0;
        if (countReportEl) countReportEl.textContent = d.countReport || 0;
    } catch (err) {
        console.error('Horizon: loadSplitView error', err);
    }
};

window.loadComments = function (postId) {
    const list = document.getElementById('commentsList');
    if (!list) return;
    list.innerHTML = '<div class="text-center text-white-50 p-3">Loading comments...</div>';
    fetch(`/forum/comment/list/${postId}`)
        .then(r => r.json())
        .then(comments => {
            if (comments.length === 0) {
                list.innerHTML = '<div class="text-center text-white-50 p-3">No comments yet.</div>';
                return;
            }
            list.innerHTML = comments.map(c => `
                <div class="comment-item p-3 mb-3 rounded-4" data-id="${c.id}" style="background: rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05);">
                    <div class="d-flex justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <div class="forum-list-avatar" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                ${c.author.avatar ? `<img src="${c.author.avatar}" alt="User">` : c.author.name.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <strong class="text-white d-block" style="line-height:1.2;">${c.author.name}</strong>
                                <small class="text-white-50" style="font-size: 0.75rem;">${c.createdAt}</small>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <button class="btn-comment-action btn-comment-like ${c.userInteraction.reaction === 'like' ? 'active' : ''}" 
                                        onclick="handleCommentAction(0, ${c.id}, 'react', 'like')">
                                    <i class='bx bx-like'></i> <span class="count-comment-like">${c.counts.likes}</span>
                                </button>
                                <button class="btn-comment-action btn-comment-dislike ${c.userInteraction.reaction === 'dislike' ? 'active' : ''}" 
                                        onclick="handleCommentAction(0, ${c.id}, 'react', 'dislike')">
                                    <i class='bx bx-dislike'></i> <span class="count-comment-dislike">${c.counts.dislikes}</span>
                                </button>
                            </div>
                            <button class="btn-comment-action btn-comment-signal ${c.userInteraction.isReported ? 'active' : ''}" 
                                    onclick="handleCommentAction(0, ${c.id}, 'report')">
                                <i class='bx bx-flag'></i> <span class="count-comment-report d-none">${c.counts.reports}</span>
                            </button>
                            ${c.canEdit ? `
                                <button class="btn-comment-action btn-comment-edit text-info" title="Edit Comment"
                                        onclick="handleCommentEditToggle(${postId}, ${c.id})">
                                    <i class='bx bx-edit-alt'></i>
                                </button>
                            ` : ''}
                            ${c.canDelete ? `
                                <button class="btn-comment-action btn-comment-delete text-danger" title="Delete Comment"
                                        onclick="handleCommentDelete(${c.id}, '${c.deleteToken}', ${postId})">
                                    <i class='bx bx-trash'></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>
                    <div id="comment-text-${c.id}" class="text-white-50 ps-5">${c.description}</div>
                    <div id="comment-edit-zone-${c.id}" class="ps-5 mt-2 d-none">
                        <textarea id="comment-textarea-${c.id}" class="glass-textarea w-100 mb-2" rows="3" style="background: rgba(0,0,0,0.2) !important;">${c.description}</textarea>
                        
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <label for="edit-image-${c.id}" class="btn btn-sm btn-outline-light" style="font-size: 0.8rem;">
                                <i class='bx bx-image-add'></i> ${c.image ? 'Change Photo' : 'Add Photo'}
                            </label>
                            <input type="file" id="edit-image-${c.id}" class="d-none edit-comment-image" accept="image/*" onchange="handleCommentImagePreview(this, ${c.id})">
                            <div id="edit-image-preview-container-${c.id}" class="${c.image ? '' : 'd-none'}">
                                <img id="edit-image-preview-${c.id}" src="${c.image || ''}" style="height: 40px; width: 40px; border-radius: 6px; object-fit: cover; border: 1px solid rgba(255,255,255,0.1);">
                                <button class="btn btn-sm btn-link text-danger p-0 ms-1" onclick="handleCommentImageClear(${c.id})"><i class='bx bx-x'></i></button>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <input type="hidden" id="edit-token-${c.id}" value="${c.canEdit ? c.editToken || '' : ''}">
                            <button class="btn btn-sm btn-outline-light" onclick="handleCommentEditToggle(${postId}, ${c.id})">Cancel</button>
                            <button class="btn btn-sm btn-primary" onclick="handleCommentSave(${postId}, ${c.id})">Save</button>
                        </div>
                    </div>
                    ${c.image ? `
                        <div class="ps-5 mt-2">
                            <img src="${c.image}" class="rounded-3" style="max-height: 200px; cursor: pointer; border: 1px solid rgba(255,255,255,0.1);" 
                                 onclick="window.openLightbox('${c.image}')" loading="lazy">
                        </div>
                    ` : ''}
                </div>
            `).join('');
        }).catch(() => {
            list.innerHTML = '<div class="text-danger p-3">Failed to load comments</div>';
        });
};

window.handleCommentDelete = async function (commentId, token, postId) {
    if (!confirm('Are you sure you want to delete this comment?')) return;
    const formData = new FormData();
    formData.append('_token', token);
    try {
        const res = await fetch(`/forum/comment/delete/${commentId}`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            window.loadComments(postId);
        } else {
            window.showCoolPopup('Error', data.message || 'Deletion failed', 'error');
        }
    } catch (err) {
        console.error(err);
    }
};

window.handleCommentEditToggle = function (postId, commentId) {
    const text = document.getElementById(`comment-text-${commentId}`);
    const editZone = document.getElementById(`comment-edit-zone-${commentId}`);
    if (text && editZone) {
        text.classList.toggle('d-none');
        editZone.classList.toggle('d-none');
        if (!editZone.classList.contains('d-none')) {
            document.getElementById(`comment-textarea-${commentId}`).focus();
        }
    }
};

window.handleCommentImagePreview = function (input, commentId) {
    const container = document.getElementById(`edit-image-preview-container-${commentId}`);
    const img = document.getElementById(`edit-image-preview-${commentId}`);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            img.src = e.target.result;
            container.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    }
};

window.handleCommentImageClear = function (commentId) {
    const input = document.getElementById(`edit-image-${commentId}`);
    const container = document.getElementById(`edit-image-preview-container-${commentId}`);
    const img = document.getElementById(`edit-image-preview-${commentId}`);
    input.value = '';
    img.src = '';
    container.classList.add('d-none');
};

window.handleCommentSave = async function (postId, commentId) {
    const textarea = document.getElementById(`comment-textarea-${commentId}`);
    const fileInput = document.getElementById(`edit-image-${commentId}`);
    const token = document.getElementById(`edit-token-${commentId}`).value;
    const newText = textarea.value.trim();

    if (!newText) return;

    const formData = new FormData();
    formData.append('description', newText);
    formData.append('_token', token);
    if (fileInput && fileInput.files.length > 0) {
        formData.append('image_commentaire', fileInput.files[0]);
    }

    try {
        const res = await fetch(`/forum/comment/edit/${commentId}`, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            window.loadComments(postId, true);
        } else {
            alert(data.message || 'Update failed');
        }
    } catch (err) {
        console.error('Comment Update Error:', err);
    }
};

document.addEventListener('DOMContentLoaded', function () {
    console.log('Horizon Forum UI Loaded');

    // Lightbox Logic for Forum
    const lightbox = document.getElementById('reclamation-lightbox');
    const lightboxImg = document.getElementById('lightbox-image');
    const lightboxClose = document.getElementById('lightbox-close');

    window.openLightbox = function (src) {
        if (!lightbox || !lightboxImg) return;
        lightboxImg.src = src;
        lightbox.style.display = 'flex';
        lightbox.offsetHeight; // Force reflow
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    };

    window.closeLightbox = function () {
        if (!lightbox) return;
        lightbox.classList.remove('active');
        setTimeout(() => {
            lightbox.style.display = 'none';
            if (lightboxImg) lightboxImg.src = '';
            document.body.style.overflow = '';
        }, 300);
    };

    if (lightboxClose) lightboxClose.addEventListener('click', window.closeLightbox);
    if (lightbox) {
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) window.closeLightbox();
        });
    }

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox && lightbox.classList.contains('active')) {
            window.closeLightbox();
        }
    });

    const createPostForm = document.getElementById('createPostForm');
    const editPostForm = document.getElementById('editPostForm');

    function showToast(message, iconClass) {
        const toast = document.getElementById('forumToast');
        const toastMsg = document.getElementById('toastMessage');
        const toastIcon = document.getElementById('toastIcon');
        if (toast && toastMsg && toastIcon) {
            toastMsg.textContent = message;
            toastIcon.className = iconClass;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
    }

    window.handleForumAction = async function (action, type = null) {
        if (!window.currentPostId) return;
        let url = `/forum/action/${action}/${window.currentPostId}`;
        if (type) url += `/${type}`;
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, method: 'POST' });
            const data = await response.json();
            if (data.success) {
                updateSocialButtonsUI(action, type, data.counts);
                showToast(data.message, 'bx bx-check-circle');
            }
        } catch (err) {
            console.error('AJAX Error:', err);
        }
    };

    function updateSocialButtonsUI(action, type, serverCounts = null) {
        const btns = {
            'like': document.getElementById('btnLikeSplit'),
            'dislike': document.getElementById('btnDislikeSplit'),
            'bookmark': document.getElementById('btnBookmarkSplit'),
            'signal': document.getElementById('btnSignalSplit')
        };

        if (action === 'react') {
            if (type === 'like') {
                btns.like?.classList.toggle('active');
                btns.dislike?.classList.remove('active');
            } else if (type === 'dislike') {
                btns.dislike?.classList.toggle('active');
                btns.like?.classList.remove('active');
            }
        } else if (action === 'bookmark') btns.bookmark?.classList.toggle('active');
        else if (action === 'report') btns.signal?.classList.toggle('active');

        const sidebarItem = document.querySelector(`.forum-list-item[data-id="${window.currentPostId}"]`);
        if (!sidebarItem) return;

        const d = sidebarItem.dataset;
        if (serverCounts) {
            if (serverCounts.likes !== undefined) d.countLike = serverCounts.likes;
            if (serverCounts.dislikes !== undefined) d.countDislike = serverCounts.dislikes;
            if (serverCounts.reports !== undefined) d.countReport = serverCounts.reports;

            const cl = document.getElementById('countLike');
            const cd = document.getElementById('countDislike');
            const cr = document.getElementById('countReport');
            if (cl) cl.textContent = d.countLike;
            if (cd) cd.textContent = d.countDislike;
            if (cr) cr.textContent = d.countReport;
        }
    }

    // --- INITIALIZATION ---
    const forumListContainer = document.getElementById('forumListContainer');
    if (forumListContainer) {
        console.log('Horizon: List listener attached');
        forumListContainer.addEventListener('click', (e) => {
            const item = e.target.closest('.forum-list-item');
            if (item) window.loadSplitView(item);
        });
    }


    const btnLike = document.getElementById('btnLikeSplit');
    if (btnLike) btnLike.onclick = () => window.handleForumAction('react', 'like');

    const btnDislike = document.getElementById('btnDislikeSplit');
    if (btnDislike) btnDislike.onclick = () => window.handleForumAction('react', 'dislike');

    const btnBookmark = document.getElementById('btnBookmarkSplit');
    if (btnBookmark) btnBookmark.onclick = () => window.handleForumAction('bookmark');

    const btnSignal = document.getElementById('btnSignalSplit');
    if (btnSignal) btnSignal.onclick = () => window.handleForumAction('report');

    const btnEditSplit = document.getElementById('btnEditSplit');
    if (btnEditSplit) {
        btnEditSplit.onclick = function () {
            if (!window.currentPostData) return;
            document.getElementById('editPostTitle').value = window.currentPostData.title || '';
            document.getElementById('editPostDescription').value = window.currentPostData.description || '';
            document.getElementById('editPostCategory').value = window.currentPostData.categoriePub || '';
            document.getElementById('editPostForm').action = `/forum/edit/${window.currentPostId}`;
            window.switchGlassFace('edit');
        };
    }

    const btnDeleteSplit = document.getElementById('btnDeleteSplit');
    if (btnDeleteSplit) {
        btnDeleteSplit.onclick = async function () {
            if (!window.currentPostId) return;
            if (confirm('Are you sure you want to delete this post?')) {
                const token = window.currentPostData?.token;
                const formData = new FormData();
                if (token) formData.append('_token', token);

                try {
                    const response = await fetch(`/forum/delete/${window.currentPostId}`, {
                        method: 'POST',
                        body: formData,
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await response.json();
                    if (data.success) {
                        window.showCoolPopup('Success', data.message || 'Post deleted', 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        window.showCoolPopup('Error', data.message || 'Deletion failed', 'error');
                    }
                } catch (err) {
                    console.error(err);
                    window.showCoolPopup('Error', 'An unexpected error occurred', 'error');
                }
            }
        };
    }

    if (createPostForm) createPostForm.onsubmit = (e) => handleAjaxForm(e, createPostForm);
    if (editPostForm) editPostForm.onsubmit = (e) => handleAjaxForm(e, editPostForm);
    const addCommentForm = document.getElementById('addCommentForm');
    if (addCommentForm) addCommentForm.onsubmit = (e) => handleAjaxForm(e, addCommentForm);

    const firstItem = document.querySelector('.forum-list-item');
    if (firstItem) window.loadSplitView(firstItem);

    async function handleAjaxForm(e, form) {
        e.preventDefault();
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Processing...';

        try {
            const formData = new FormData(form);
            const response = await fetch(form.action || window.location.href, {
                method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await response.json();
            if (data.success) {
                if (form.id === 'addCommentForm') {
                    form.reset();
                    // Reset image preview if it exists
                    const preview = form.querySelector('.image-preview-container');
                    if (preview) { preview.innerHTML = ''; preview.classList.add('d-none'); }

                    const postId = form.getAttribute('data-post-id');
                    if (postId) window.loadComments(postId);

                    btn.disabled = false;
                    btn.innerHTML = originalText;
                } else {
                    window.showCoolPopup('Success', data.message || 'Saved successfully', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                }
            } else {
                window.showCoolPopup('Error', data.message || 'Operation failed', 'error');
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
});
