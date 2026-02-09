
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('createPostModal');
    const closeBtn = document.querySelector('.forum-modal-close');
    const form = document.getElementById('createPostForm');
    const publicationsGrid = document.querySelector('.forum-publications-grid');
    const emptyState = document.querySelector('.forum-empty-state');

    const btns = document.querySelectorAll('.btn-hero-create, .btn-discussion-create');

    if (btns.length > 0) {
        btns.forEach(btn => {
            btn.onclick = function (e) {
                e.preventDefault();
                modal.style.display = "flex";

                // Handle Announcement Mode
                const isAnnouncement = btn.classList.contains('btn-hero-create');
                const categorySelect = form.querySelector('select[name$="[categorie_pub]"]'); // Matches publication[categorie_pub]
                const categoryRow = categorySelect ? categorySelect.closest('.main-home-form-group') : null;
                const modalTitle = modal.querySelector('.forum-modal-header h2');

                if (isAnnouncement) {
                    if (modalTitle) modalTitle.textContent = "New Announcement";
                    if (categorySelect) {
                        categorySelect.value = 'Announcement';
                    }
                    if (categoryRow) categoryRow.style.display = 'none';
                } else {
                    if (modalTitle) modalTitle.textContent = "Create New Post";
                    if (categorySelect) {
                        // If previously set to Announcement (which is restricted), reset it
                        if (categorySelect.value === 'Announcement') {
                            // Select the next available option (e.g., Suggestion)
                            // Assuming Announcement is the first option now
                            if (categorySelect.options.length > 1) {
                                categorySelect.selectedIndex = 1;
                            } else {
                                categorySelect.selectedIndex = 0;
                            }
                        }
                    }
                    if (categoryRow) categoryRow.style.display = 'block';
                }

                // Add a class for animation
                setTimeout(() => {
                    modal.classList.add('show');
                }, 10);
            }
        });
    }

    if (closeBtn) {
        closeBtn.onclick = function () {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = "none";
            }, 300);
        }
    }

    // Post Details Modal Logic
    const detailsModal = document.getElementById('postDetailsModal');
    const detailsClose = document.querySelector('.details-close-btn');
    const readMoreBtns = document.querySelectorAll('.btn-read-more');

    if (readMoreBtns.length > 0) {
        readMoreBtns.forEach(btn => {
            btn.onclick = function () {
                const data = btn.dataset;

                // ID and Author Logic
                const postId = this.getAttribute('data-id');
                const authorId = this.getAttribute('data-author-id');
                const currentUserId = document.getElementById('currentUserId')?.value;
                const currentUserRole = document.getElementById('currentUserRole')?.value;
                const authorActions = document.getElementById('authorActions');

                const moderatorRoles = ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'];
                const isModerator = currentUserRole && moderatorRoles.includes(currentUserRole);

                window.currentPostId = postId; // Store globally for actions
                window.currentAuthorId = authorId; // Store globally for actions

                if (authorActions) {
                    const isAuthor = currentUserId && authorId && String(currentUserId) === String(authorId);
                    const isModerator = currentUserRole && ['OWNER', 'ADMIN', 'SUPERADMIN', 'SYNDIC'].includes(currentUserRole);

                    if (isAuthor || isModerator) {
                        authorActions.classList.remove('d-none');
                        authorActions.style.setProperty('display', 'flex', 'important');
                    } else {
                        authorActions.classList.add('d-none');
                        authorActions.style.setProperty('display', 'none', 'important');
                    }
                }

                // Title and Meta
                document.getElementById('modalPostTitle').textContent = data.title;
                document.getElementById('modalPostDescription').textContent = data.description;
                document.getElementById('modalPostCategory').textContent = data.categoriePub;
                document.getElementById('modalPostCategory').className = 'forum-category-badge ' + data.catClass;
                document.getElementById('modalPostDate').textContent = data.date;
                document.getElementById('modalPostUserName').textContent = data.user;

                const avatarContainer = document.getElementById('modalPostUserAvatar');
                if (data.avatar && data.avatar !== "" && !data.avatar.includes('undefined')) {
                    avatarContainer.innerHTML = `<img src="${data.avatar}" alt="${data.user}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">`;
                } else {
                    avatarContainer.textContent = data.userInitial;
                }

                // Store current data for pre-filling edit modal
                window.currentPostData = {
                    title: data.title,
                    description: data.description,
                    category: data.categoriePub
                };

                const imgContainer = document.getElementById('modalPostImage');
                const modalContent = document.getElementById('detailsModalContent');

                if (data.image && data.image !== "" && !data.image.includes('undefined')) {
                    imgContainer.style.backgroundImage = `url(${data.image})`;
                    imgContainer.style.display = 'block';
                    if (modalContent) modalContent.classList.remove('no-image');
                } else {
                    imgContainer.style.backgroundImage = 'none';
                    if (modalContent) modalContent.classList.add('no-image');
                }

                if (detailsModal) {
                    detailsModal.style.display = 'flex';
                    setTimeout(() => {
                        detailsModal.classList.add('show');
                        document.body.classList.add('modal-open');
                    }, 10);
                }

                // Handle Comments Section
                const commentsSection = document.getElementById('commentsSection');
                const addCommentForm = document.getElementById('addCommentForm');

                if (data.categoriePub === 'Announcement') {
                    if (commentsSection) commentsSection.classList.add('d-none');
                } else {
                    if (commentsSection) commentsSection.classList.remove('d-none');
                    loadComments(postId);

                    // Setup form action
                    if (addCommentForm) {
                        addCommentForm.dataset.postId = postId;
                    }
                }
            };
        });
    }

    function loadComments(postId) {
        const list = document.getElementById('commentsList');
        if (!list) return;

        list.innerHTML = '<div class="text-center text-white-50"><i class="bx bx-loader-alt bx-spin fs-3"></i></div>';

        fetch(`/forum/comment/list/${postId}`)
            .then(res => res.json())
            .then(comments => {
                if (comments.length === 0) {
                    list.innerHTML = '<div class="text-center text-white-50 p-4">No comments yet. Be the first to share your thoughts!</div>';
                    return;
                }

                list.innerHTML = comments.map(c => {
                    let avatarHtml = '';
                    if (c.author.isAnonymous) {
                        avatarHtml = `<div class="details-author-avatar"><i class='bx bx-user-secret'></i></div>`;
                    } else if (c.author.avatar) {
                        avatarHtml = `
                            <div class="details-author-avatar">
                                <img src="${c.author.avatar}" 
                                     alt="${c.author.name}" 
                                     style="width: 100%; height: 100%; object-fit: cover; border-radius: 12px;">
                            </div>`;
                    } else {
                        avatarHtml = `<div class="details-author-avatar">${c.author.name.charAt(0).toUpperCase()}</div>`;
                    }

                    let actionsHtml = '';
                    if (c.canEdit || c.canDelete) {
                        actionsHtml = `<div style="display: flex; flex-direction: row; gap: 8px; margin-left: auto; align-items: center; flex-wrap: nowrap;">`;
                        if (c.canEdit) {
                            actionsHtml += `
                                <button class="action-btn edit-comment-btn" data-id="${c.id}" data-text="${c.description.replace(/"/g, '&quot;')}" title="Edit" style="width: 32px; height: 32px; font-size: 1rem; background: rgba(108, 92, 231, 0.15); color: #6c5ce7; border: 1px solid rgba(108, 92, 231, 0.25);">
                                    <i class='bx bx-edit-alt'></i>
                                </button>`;
                        }
                        if (c.canDelete) {
                            actionsHtml += `
                                <button class="action-btn delete-comment-btn" data-id="${c.id}" title="Delete" style="width: 32px; height: 32px; font-size: 1rem; background: rgba(255, 77, 77, 0.15); color: #ff4d4d; border: 1px solid rgba(255, 77, 77, 0.25);">
                                    <i class='bx bx-trash'></i>
                                </button>`;
                        }
                        actionsHtml += `</div>`;
                    }

                    return `
                        <div class="comment-item" id="comment-item-${c.id}">
                            <div class="comment-header">
                                ${avatarHtml}
                                <div class="d-flex flex-column">
                                    <span class="comment-author-name">${c.author.name}</span>
                                    <span class="comment-date">
                                        ${c.createdAt}
                                        ${c.updatedAt ? `<small style="font-style:italic; margin-left:5px; opacity:0.7;">(Edited ${c.updatedAt})</small>` : ''}
                                    </span>
                                </div>
                                ${actionsHtml}
                            </div>
                            <div class="comment-body" id="comment-body-${c.id}">
                                ${c.description}
                                ${c.image ? `
                                    <div class="comment-image-wrapper mt-3" style="max-width: 500px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.08); overflow: hidden; background: rgba(0,0,0,0.2);">
                                        <img src="${c.image}" alt="Comment image" class="img-fluid w-100" style="max-height: 400px; object-fit: contain; cursor: pointer; transition: transform 0.3s ease;" onclick="window.open('${c.image}', '_blank')">
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                }).join('');

                // Attach delete handlers
                document.querySelectorAll('.delete-comment-btn').forEach(btn => {
                    btn.onclick = function () {
                        if (confirm('Are you sure you want to delete this comment?')) {
                            fetch(`/forum/comment/delete/${this.dataset.id}`, { method: 'POST' })
                                .then(res => res.json())
                                .then(res => {
                                    if (res.success) {
                                        showToast('Comment deleted');
                                        loadComments(postId);
                                    }
                                });
                        }
                    }
                });

                // Attach inline edit handlers
                document.querySelectorAll('.edit-comment-btn').forEach(btn => {
                    btn.onclick = function () {
                        enterInlineEdit(this.dataset.id, this.dataset.text);
                    }
                });
            })
            .catch(err => {
                list.innerHTML = '<div class="text-center text-danger">Failed to load comments.</div>';
            });
    }

    window.enterInlineEdit = function (id, originalText) {
        const bodyDiv = document.getElementById(`comment-body-${id}`);
        if (!bodyDiv) return;

        // Save original HTML in case of cancel
        bodyDiv.dataset.originalHtml = bodyDiv.innerHTML;

        bodyDiv.innerHTML = `
            <div class="inline-edit-container mt-2">
                <textarea class="comment-input-area inline-edit-textarea" rows="3" style="width:100%;">${originalText}</textarea>
                <div class="d-flex justify-content-end gap-2 mt-2">
                    <button class="btn-cancel-delete" style="padding: 4px 12px; font-size: 0.85rem;" onclick="cancelInlineEdit(${id})">Cancel</button>
                    <button class="main-home-btn-auth-submit" style="width: auto; margin:0; padding: 4px 16px; font-size: 0.85rem;" onclick="saveInlineComment(${id})">Save</button>
                </div>
                <div class="text-white-50 mt-1" style="font-size: 0.75rem;">Tip: Press Enter to save, Shift+Enter for new line</div>
            </div>
        `;

        const textarea = bodyDiv.querySelector('textarea');
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);

        // Enter key to save
        textarea.onkeydown = function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                saveInlineComment(id);
            }
            if (e.key === 'Escape') {
                cancelInlineEdit(id);
            }
        };
    };

    window.cancelInlineEdit = function (id) {
        const bodyDiv = document.getElementById(`comment-body-${id}`);
        if (bodyDiv && bodyDiv.dataset.originalHtml) {
            bodyDiv.innerHTML = bodyDiv.dataset.originalHtml;
        }
    };

    window.saveInlineComment = function (id) {
        const bodyDiv = document.getElementById(`comment-body-${id}`);
        const textarea = bodyDiv.querySelector('textarea');
        const newText = textarea.value.trim();

        if (newText === "") return;

        const saveBtn = bodyDiv.querySelector('.main-home-btn-auth-submit');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i>';

        const formData = new FormData();
        formData.append('description', newText);

        fetch(`/forum/comment/edit/${id}`, {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Comment updated');
                    loadComments(window.currentPostId);
                } else {
                    showToast(data.message || 'Error updating', 'error');
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save';
                }
            })
            .catch(err => {
                showToast('Network error', 'error');
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save';
            });
    };

    window.closeDetailsModal = function () {
        if (!detailsModal) return;
        detailsModal.classList.remove('show');
        document.body.classList.remove('modal-open');

        // Reset comment form and image preview
        const addForm = document.getElementById('addCommentForm');
        if (addForm) addForm.reset();

        const imgInput = document.getElementById('commentImageInput');
        const imgPreviewContainer = document.getElementById('commentImagePreviewContainer');
        const imgPreview = document.getElementById('commentImagePreview');
        if (imgInput) imgInput.value = '';
        if (imgPreviewContainer) imgPreviewContainer.classList.add('d-none');
        if (imgPreview) imgPreview.style.backgroundImage = 'none';

        setTimeout(() => {
            detailsModal.style.display = "none";
        }, 300);
    };

    if (detailsClose) {
        detailsClose.onclick = closeDetailsModal;
    }

    // --- Detail Modal Actions (Edit/Delete) ---
    const btnDelete = document.getElementById('btnDeletePost');
    const btnEdit = document.getElementById('btnEditPost');
    const editModal = document.getElementById('editPostModal');

    // --- Custom Modals & Notifications ---
    const confirmModal = document.getElementById('confirmDeleteModal');
    const btnConfirmDelete = document.getElementById('btnConfirmDelete');
    const forumToast = document.getElementById('forumToast');

    window.openConfirmModal = function () {
        confirmModal.style.display = 'flex';
        setTimeout(() => {
            confirmModal.classList.add('show');
        }, 10);
    };

    window.closeConfirmModal = function () {
        confirmModal.classList.remove('show');
        setTimeout(() => {
            confirmModal.style.display = 'none';
        }, 300);
    };

    window.showToast = function (message, type = 'success') {
        const toastMsg = document.getElementById('toastMessage');
        const toastIcon = document.getElementById('toastIcon');

        toastMsg.textContent = message;
        forumToast.className = `forum-toast show ${type}`;

        if (type === 'success') {
            toastIcon.className = 'bx bx-check-circle';
        } else {
            toastIcon.className = 'bx bx-error-circle';
        }

        setTimeout(() => {
            forumToast.classList.remove('show');
        }, 4000);
    };

    if (btnDelete) {
        btnDelete.onclick = function () {
            openConfirmModal();
        };
    }

    if (btnConfirmDelete) {
        btnConfirmDelete.onclick = function () {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/forum/delete/${window.currentPostId}`;
            document.body.appendChild(form);
            form.submit();
        };
    }

    if (btnEdit) {
        btnEdit.onclick = function () {
            // Pre-fill fields
            document.getElementById('editPostTitle').value = window.currentPostData.title;
            document.getElementById('editPostDescription').value = window.currentPostData.description;
            document.getElementById('editPostCategory').value = window.currentPostData.category;
            document.getElementById('editPostForm').action = `/forum/edit/${window.currentPostId}`;

            // Hide details modal, show edit modal
            detailsModal.classList.remove('show');
            setTimeout(() => {
                detailsModal.style.display = 'none';
                editModal.style.display = 'flex';
                setTimeout(() => {
                    editModal.classList.add('show');
                }, 10);
            }, 300);
        };
    }


    window.closeEditModal = function () {
        editModal.classList.remove('show');
        setTimeout(() => {
            editModal.style.display = 'none';
            // Return to details modal
            detailsModal.style.display = 'flex';
            setTimeout(() => {
                detailsModal.classList.add('show');
            }, 10);
        }, 300);
    };

    // --- Comment Editing Logic ---
    const commentEditModal = document.getElementById('editCommentModal');
    const commentEditArea = document.getElementById('commentEditArea');
    const editCommentForm = document.getElementById('editCommentForm');

    window.openEditCommentModal = function (id, text) {
        window.currentCommentId = id;
        commentEditArea.value = text;
        commentEditModal.style.display = 'flex';
        setTimeout(() => {
            commentEditModal.classList.add('show');
        }, 10);
    };

    window.closeEditCommentModal = function () {
        commentEditModal.classList.remove('show');
        setTimeout(() => {
            commentEditModal.style.display = 'none';
        }, 300);
    };

    if (editCommentForm) {
        editCommentForm.onsubmit = function (e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Saving...';

            const formData = new FormData(this);
            fetch(`/forum/comment/edit/${window.currentCommentId}`, {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save Changes';
                    if (data.success) {
                        closeEditCommentModal();
                        showToast('Comment updated');
                        loadComments(window.currentPostId);
                    } else {
                        alert(data.message || 'Error updating comment');
                    }
                });
        };
    }

    // Close Modals on Outer Click
    window.onclick = function (event) {
        if (event.target == modal) {
            modal.classList.remove('show');
            setTimeout(() => {
                modal.style.display = "none";
            }, 300);
        }
        if (event.target == detailsModal) {
            closeDetailsModal();
        }
        if (event.target == editModal) {
            closeEditModal();
        }
        if (event.target == commentEditModal) {
            closeEditCommentModal();
        }
    }
    // Check for auto-open post (e.g. after comment submission)
    const urlParams = new URLSearchParams(window.location.search);
    const openPostId = urlParams.get('open_post');
    if (openPostId) {
        const btn = document.querySelector(`.btn-read-more[data-id="${openPostId}"]`);
        if (btn) {
            btn.click();
            // Clean URL
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    }

    // Check if any error container actually has text
    const errorContainers = document.querySelectorAll('#createPostModal .text-danger');
    const hasErrors = Array.from(errorContainers).some(el => el.textContent.trim().length > 0);

    if (hasErrors) {
        if (modal) {
            modal.style.display = "flex";
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }
    }

    // AJAX Comment Submission
    const addCommentForm = document.getElementById('addCommentForm');
    if (addCommentForm) {
        addCommentForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const postId = this.dataset.postId;
            if (!postId) return;

            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Posting...';

            const formData = new FormData(this);

            fetch(`/forum/comment/add/${postId}`, {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;

                    if (data.success) {
                        this.reset();
                        if (commentImgPreviewContainer) {
                            commentImgPreviewContainer.classList.add('d-none');
                            commentImgPreview.style.backgroundImage = 'none';
                        }
                        loadComments(postId); // "Secretly" refresh
                        // Optional: Scroll to comments
                        document.getElementById('commentsSection').scrollIntoView({ behavior: 'smooth' });
                    } else {
                        alert(data.message || 'Error posting comment');
                    }
                })
                .catch(err => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                    alert('An error occurred. Please try again.');
                    console.error(err);
                });
        });
    }

    // --- Comment Image Upload Preview Logic ---
    const commentImgInput = document.getElementById('commentImageInput');
    const commentImgPreview = document.getElementById('commentImagePreview');
    const commentImgPreviewContainer = document.getElementById('commentImagePreviewContainer');
    const removeCommentImg = document.getElementById('removeCommentImg');

    if (commentImgInput) {
        commentImgInput.addEventListener('change', function () {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    commentImgPreview.style.backgroundImage = `url(${e.target.result})`;
                    commentImgPreviewContainer.classList.remove('d-none');
                }
                reader.readAsDataURL(file);
            }
        });
    }

    if (removeCommentImg) {
        removeCommentImg.addEventListener('click', function () {
            commentImgInput.value = '';
            commentImgPreviewContainer.classList.add('d-none');
            commentImgPreview.style.backgroundImage = 'none';
        });
    }

    // --- AJAX FORUM POST CREATION ---
    const createPostForm = document.getElementById('createPostForm');
    if (createPostForm) {
        createPostForm.onsubmit = async function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalHTML = btn.innerHTML;

            // Set Loading State
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Posting...';
            btn.disabled = true;
            btn.style.opacity = '0.8';

            const formData = new FormData(this);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await response.json();

                if (data.success) {
                    showCoolPopup('Shared!', 'Your post has been published to the community.', 'success');
                    setTimeout(() => {
                        window.location.reload(); // Refresh to show new post
                    }, 2000);
                } else {
                    const errorMsg = data.message || (data.errors ? data.errors.join('<br>') : 'Validation failed. Please check your input.');
                    showCoolPopup('Check your post', errorMsg, 'error');
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    btn.style.opacity = '1';
                }
            } catch (error) {
                console.error('Forum Post Error:', error);
                showCoolPopup('Network Error', 'Please check your connection and try again.', 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        };
    }

    // --- AJAX FORUM POST EDITING ---
    const editPostForm = document.getElementById('editPostForm');
    if (editPostForm) {
        editPostForm.onsubmit = async function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalHTML = btn.innerHTML;

            // Set Loading State
            btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Saving...';
            btn.disabled = true;
            btn.style.opacity = '0.8';

            const formData = new FormData(this);
            const actionUrl = this.getAttribute('action');

            try {
                const response = await fetch(actionUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                const data = await response.json();

                if (data.success) {
                    showCoolPopup('Updated!', 'Your changes have been saved successfully.', 'success');
                    setTimeout(() => {
                        window.location.reload(); // Refresh to show changes
                    }, 2000);
                } else {
                    const errorMsg = data.message || (data.errors ? data.errors.join('<br>') : 'Validation failed. Please check your input.');
                    showCoolPopup('Check your edit', errorMsg, 'error');
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                    btn.style.opacity = '1';
                }
            } catch (error) {
                console.error('Forum Edit Error:', error);
                showCoolPopup('Network Error', 'Please check your connection and try again.', 'error');
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                btn.style.opacity = '1';
            }
        };
    }
});
