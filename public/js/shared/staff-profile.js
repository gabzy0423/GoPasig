/**
 * Shared Staff Profile JavaScript Module
 * Serves both Admin and Dispatcher Account Profile SPAs without code duplication.
 */
(function () {
    let lastLoadedProfiles = {};

    function getApiEndpoint(prefix, action) {
        const base = (prefix === 'dispatcher' || prefix === 'fleet') ? '/fleet/api/profile' : '/admin/api/profile';
        if (action === 'photo') return `${base}/photo`;
        if (action === 'password') return `${base}/password`;
        return base;
    }

    function getCsrfToken() {
        return (window.GoPasigConfig && window.GoPasigConfig.csrfToken)
            ? window.GoPasigConfig.csrfToken
            : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    /**
     * Fetch complete staff profile data
     */
    function loadStaffProfileData(prefix = 'admin', customEndpoint = null) {
        const endpoint = customEndpoint || getApiEndpoint(prefix, 'show');
        const loadingEl = document.getElementById(`${prefix}-profile-loading`);

        if (loadingEl) loadingEl.classList.remove('hidden');
        clearStaffProfileErrors(prefix);

        fetch(endpoint, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success && data.user) {
                const user = data.user;
                lastLoadedProfiles[prefix] = JSON.parse(JSON.stringify(user));

                populateStaffProfileFields(user, prefix);
                updateStaffProfileIdentity(user);

                if (data.account_information) {
                    populateAccountInformation(data.account_information, prefix);
                }
                if (data.profile_completion) {
                    renderProfileCompletion(data.profile_completion, prefix);
                }
                if (data.recent_activity) {
                    renderRecentActivity(data.recent_activity, prefix);
                }
            } else {
                showStaffProfileError('Unable to load profile data.', prefix);
            }
        })
        .catch(err => {
            console.error(`Error loading ${prefix} profile:`, err);
            showStaffProfileError('Failed to fetch account profile data.', prefix);
        })
        .finally(() => {
            if (loadingEl) loadingEl.classList.add('hidden');
        });
    }

    /**
     * Populate form fields with user model data and staff profile details
     */
    function populateStaffProfileFields(user, prefix = 'admin') {
        const nameInput = document.getElementById(`${prefix}-profile-name`);
        const emailInput = document.getElementById(`${prefix}-profile-email`);
        const roleInput = document.getElementById(`${prefix}-profile-role`);
        const idDisplay = document.getElementById(`${prefix}-profile-id-display`);
        const displayName = document.getElementById(`${prefix}-profile-display-name`);
        const avatarPreview = document.getElementById(`${prefix}-profile-avatar-preview`);
        const removePhotoBtn = document.getElementById(`${prefix}-profile-photo-remove-btn`);

        if (nameInput) nameInput.value = user.name || '';
        if (emailInput) emailInput.value = user.email || '';
        if (roleInput) roleInput.textContent = user.role ? (user.role.charAt(0).toUpperCase() + user.role.slice(1)) : 'User';
        if (idDisplay) idDisplay.textContent = user.id ? `ID: #${user.id}` : 'ID: --';
        if (displayName) displayName.textContent = user.name || 'User Profile';

        const profile = user.staff_profile || {};
        const contactInput = document.getElementById(`${prefix}-profile-contact-number`);
        const addressInput = document.getElementById(`${prefix}-profile-address`);
        const emergencyInput = document.getElementById(`${prefix}-profile-emergency-contact`);

        if (contactInput) contactInput.value = profile.contact_number || '';
        if (addressInput) addressInput.value = profile.address || '';
        if (emergencyInput) emergencyInput.value = profile.emergency_contact || '';

        // Handle Avatar Preview
        const photoUrl = user.profile_photo_url || profile.profile_photo_url;
        if (avatarPreview) {
            if (photoUrl) {
                avatarPreview.innerHTML = `<img src="${photoUrl}" alt="${escapeHtml(user.name)}" class="h-full w-full object-cover">`;
                if (removePhotoBtn) removePhotoBtn.classList.remove('hidden');
            } else {
                const initials = getStaffInitials(user.name || '');
                avatarPreview.innerHTML = `<span>${escapeHtml(initials)}</span>`;
                if (removePhotoBtn) removePhotoBtn.classList.add('hidden');
            }
        }
    }

    /**
     * Update Topbar identity elements
     */
    function updateStaffProfileIdentity(user) {
        const topbarName = document.getElementById('topbar-user-name');
        const topbarRole = document.getElementById('topbar-user-role');
        const avatarContainer = document.getElementById('topbar-avatar-container');

        if (topbarName) topbarName.textContent = user.name || '';
        if (topbarRole) topbarRole.textContent = user.role ? (user.role.charAt(0).toUpperCase() + user.role.slice(1)) : '';

        const photoUrl = user.profile_photo_url || (user.staff_profile ? user.staff_profile.profile_photo_url : null);

        if (avatarContainer) {
            if (photoUrl) {
                avatarContainer.innerHTML = `<img id="topbar-avatar-img" src="${photoUrl}" alt="${escapeHtml(user.name)}" class="h-full w-full object-cover">`;
            } else {
                const initials = getStaffInitials(user.name || '');
                avatarContainer.innerHTML = `<span id="topbar-avatar-initials">${escapeHtml(initials)}</span>`;
            }
        }
    }

    function getStaffInitials(name) {
        if (!name || typeof name !== 'string') return 'SP';
        const parts = name.trim().split(/\s+/).filter(Boolean);
        if (parts.length === 0) return 'SP';
        if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    /**
     * Reset personal profile form back to last loaded values
     */
    function resetStaffProfileForm(prefix = 'admin') {
        if (lastLoadedProfiles[prefix]) {
            populateStaffProfileFields(lastLoadedProfiles[prefix], prefix);
        }
        clearStaffProfileErrors(prefix);
    }

    /**
     * Submit profile text fields update
     */
    function handleStaffProfileSubmit(event, prefix = 'admin') {
        event.preventDefault();

        clearStaffProfileErrors(prefix);

        const nameInput = document.getElementById(`${prefix}-profile-name`);
        const emailInput = document.getElementById(`${prefix}-profile-email`);
        const contactInput = document.getElementById(`${prefix}-profile-contact-number`);
        const addressInput = document.getElementById(`${prefix}-profile-address`);
        const emergencyInput = document.getElementById(`${prefix}-profile-emergency-contact`);

        const saveBtn = document.getElementById(`${prefix}-profile-save`);
        const saveText = document.getElementById(`${prefix}-profile-save-text`);

        const name = nameInput ? nameInput.value.trim() : '';
        const email = emailInput ? emailInput.value.trim() : '';
        const contact_number = contactInput ? contactInput.value.trim() : '';
        const address = addressInput ? addressInput.value.trim() : '';
        const emergency_contact = emergencyInput ? emergencyInput.value.trim() : '';

        let hasError = false;

        if (!name) {
            showStaffFieldError(`${prefix}-profile-name-error`, 'Full name is required.');
            hasError = true;
        }

        if (!email) {
            showStaffFieldError(`${prefix}-profile-email-error`, 'Email address is required.');
            hasError = true;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showStaffFieldError(`${prefix}-profile-email-error`, 'Please enter a valid email address.');
            hasError = true;
        }

        if (hasError) return;

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.add('opacity-75', 'cursor-not-allowed');
        }
        if (saveText) {
            saveText.textContent = 'Saving...';
        }

        const endpoint = getApiEndpoint(prefix, 'update');

        fetch(endpoint, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                name,
                email,
                contact_number,
                address,
                emergency_contact
            })
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) {
                if (response.status === 422 && data.errors) {
                    if (data.errors.name) showStaffFieldError(`${prefix}-profile-name-error`, data.errors.name[0]);
                    if (data.errors.email) showStaffFieldError(`${prefix}-profile-email-error`, data.errors.email[0]);
                    if (data.errors.contact_number) showStaffFieldError(`${prefix}-profile-contact-number-error`, data.errors.contact_number[0]);
                    if (data.errors.address) showStaffFieldError(`${prefix}-profile-address-error`, data.errors.address[0]);
                    if (data.errors.emergency_contact) showStaffFieldError(`${prefix}-profile-emergency-contact-error`, data.errors.emergency_contact[0]);

                    throw new Error(data.message || 'Validation failed.');
                }
                throw new Error(data.message || 'Failed to update profile.');
            }
            return data;
        })
        .then(data => {
            if (data && data.success && data.user) {
                const user = data.user;
                lastLoadedProfiles[prefix] = JSON.parse(JSON.stringify(user));

                populateStaffProfileFields(user, prefix);
                updateStaffProfileIdentity(user);

                if (data.account_information) populateAccountInformation(data.account_information, prefix);
                if (data.profile_completion) renderProfileCompletion(data.profile_completion, prefix);
                if (data.recent_activity) renderRecentActivity(data.recent_activity, prefix);

                showStaffProfileSuccess(data.message || 'Profile updated successfully.', prefix);
            }
        })
        .catch(err => {
            console.error(`Error updating ${prefix} profile:`, err);
            showStaffProfileError(err.message || 'An error occurred while saving your profile.', prefix);
        })
        .finally(() => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
            if (saveText) {
                saveText.textContent = 'Save Changes';
            }
        });
    }

    /**
     * Upload photo handler
     */
    function handleStaffPhotoUpload(event, prefix = 'admin') {
        const fileInput = event.target;
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) return;

        const file = fileInput.files[0];
        const errorEl = document.getElementById(`${prefix}-profile-photo-error`);
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }

        const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            showStaffFieldError(`${prefix}-profile-photo-error`, 'Invalid image format. Only JPEG, PNG, JPG, and WEBP files are allowed.');
            fileInput.value = '';
            return;
        }

        if (file.size > 2 * 1024 * 1024) {
            showStaffFieldError(`${prefix}-profile-photo-error`, 'File size exceeds 2MB limit.');
            fileInput.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('photo', file);

        const endpoint = getApiEndpoint(prefix, 'photo');

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) {
                if (response.status === 422 && data.errors && data.errors.photo) {
                    throw new Error(data.errors.photo[0]);
                }
                throw new Error(data.message || 'Failed to upload photo.');
            }
            return data;
        })
        .then(data => {
            if (data && data.success && data.user) {
                const user = data.user;
                lastLoadedProfiles[prefix] = JSON.parse(JSON.stringify(user));

                populateStaffProfileFields(user, prefix);
                updateStaffProfileIdentity(user);

                if (data.account_information) populateAccountInformation(data.account_information, prefix);
                if (data.profile_completion) renderProfileCompletion(data.profile_completion, prefix);
                if (data.recent_activity) renderRecentActivity(data.recent_activity, prefix);

                showStaffProfileSuccess(data.message || 'Profile photo uploaded successfully.', prefix);
            }
        })
        .catch(err => {
            console.error(`Error uploading ${prefix} photo:`, err);
            showStaffFieldError(`${prefix}-profile-photo-error`, err.message || 'Failed to upload photo.');
        })
        .finally(() => {
            fileInput.value = '';
        });
    }

    /**
     * Remove photo handler
     */
    function handleStaffPhotoRemove(prefix = 'admin') {
        const errorEl = document.getElementById(`${prefix}-profile-photo-error`);
        if (errorEl) {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        }

        const endpoint = getApiEndpoint(prefix, 'photo');

        fetch(endpoint, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Failed to remove photo.');
            }
            return data;
        })
        .then(data => {
            if (data && data.success && data.user) {
                const user = data.user;
                lastLoadedProfiles[prefix] = JSON.parse(JSON.stringify(user));

                populateStaffProfileFields(user, prefix);
                updateStaffProfileIdentity(user);

                if (data.account_information) populateAccountInformation(data.account_information, prefix);
                if (data.profile_completion) renderProfileCompletion(data.profile_completion, prefix);
                if (data.recent_activity) renderRecentActivity(data.recent_activity, prefix);

                showStaffProfileSuccess(data.message || 'Profile photo removed successfully.', prefix);
            }
        })
        .catch(err => {
            console.error(`Error removing ${prefix} photo:`, err);
            showStaffFieldError(`${prefix}-profile-photo-error`, err.message || 'Failed to remove photo.');
        });
    }

    /**
     * Password Visibility Toggle (Eye Icon)
     */
    function toggleStaffPasswordVisibility(fieldId, iconId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(iconId);
        if (!field) return;

        if (field.type === 'password') {
            field.type = 'text';
            if (icon) {
                icon.classList.remove('ti-eye');
                icon.classList.add('ti-eye-off');
            }
        } else {
            field.type = 'password';
            if (icon) {
                icon.classList.remove('ti-eye-off');
                icon.classList.add('ti-eye');
            }
        }
    }

    /**
     * Clear Password Form Error Messages and Alerts
     */
    function clearStaffPasswordErrors(prefix = 'admin') {
        const passwordErrorFieldIds = [
            `${prefix}-profile-current-password-error`,
            `${prefix}-profile-new-password-error`,
            `${prefix}-profile-new-password-confirmation-error`
        ];

        passwordErrorFieldIds.forEach(id => {
            const errEl = document.getElementById(id);
            if (errEl) {
                errEl.textContent = '';
                errEl.classList.add('hidden');
            }
        });

        const errorAlert = document.getElementById(`${prefix}-profile-password-error`);
        const successAlert = document.getElementById(`${prefix}-profile-password-success`);

        if (errorAlert) errorAlert.classList.add('hidden');
        if (successAlert) successAlert.classList.add('hidden');
    }

    function showStaffPasswordSuccess(msg, prefix = 'admin') {
        const alertEl = document.getElementById(`${prefix}-profile-password-success`);
        const msgEl = document.getElementById(`${prefix}-profile-password-success-message`);
        if (alertEl && msgEl) {
            msgEl.textContent = msg;
            alertEl.classList.remove('hidden');
        }
    }

    function showStaffPasswordError(msg, prefix = 'admin') {
        const alertEl = document.getElementById(`${prefix}-profile-password-error`);
        const msgEl = document.getElementById(`${prefix}-profile-password-error-message`);
        if (alertEl && msgEl) {
            msgEl.textContent = msg;
            alertEl.classList.remove('hidden');
        }
    }

    /**
     * Reset Password Form Inputs Only
     */
    function resetStaffPasswordForm(prefix = 'admin') {
        const currentInput = document.getElementById(`${prefix}-profile-current-password`);
        const newInput = document.getElementById(`${prefix}-profile-new-password`);
        const confirmInput = document.getElementById(`${prefix}-profile-new-password-confirmation`);

        if (currentInput) currentInput.value = '';
        if (newInput) newInput.value = '';
        if (confirmInput) confirmInput.value = '';

        [`${prefix}-profile-current-password`, `${prefix}-profile-new-password`, `${prefix}-profile-new-password-confirmation`].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.type = 'password';
        });

        const icons = [
            { id: `${prefix}-current-password-eye-icon` },
            { id: `${prefix}-new-password-eye-icon` },
            { id: `${prefix}-confirm-password-eye-icon` }
        ];

        icons.forEach(item => {
            const iconEl = document.getElementById(item.id);
            if (iconEl) {
                iconEl.classList.remove('ti-eye-off');
                iconEl.classList.add('ti-eye');
            }
        });

        clearStaffPasswordErrors(prefix);
    }

    /**
     * Submit Password Update
     */
    function handleStaffPasswordSubmit(event, prefix = 'admin') {
        event.preventDefault();

        clearStaffPasswordErrors(prefix);

        const currentInput = document.getElementById(`${prefix}-profile-current-password`);
        const newInput = document.getElementById(`${prefix}-profile-new-password`);
        const confirmInput = document.getElementById(`${prefix}-profile-new-password-confirmation`);

        const saveBtn = document.getElementById(`${prefix}-profile-password-save`);
        const saveText = document.getElementById(`${prefix}-profile-password-save-text`);

        const current_password = currentInput ? currentInput.value : '';
        const new_password = newInput ? newInput.value : '';
        const new_password_confirmation = confirmInput ? confirmInput.value : '';

        let hasError = false;

        if (!current_password) {
            showStaffFieldError(`${prefix}-profile-current-password-error`, 'Current password is required.');
            hasError = true;
        }

        if (!new_password) {
            showStaffFieldError(`${prefix}-profile-new-password-error`, 'New password is required.');
            hasError = true;
        } else if (new_password.length < 8) {
            showStaffFieldError(`${prefix}-profile-new-password-error`, 'The new password must be at least 8 characters.');
            hasError = true;
        } else if (new_password === current_password) {
            showStaffFieldError(`${prefix}-profile-new-password-error`, 'The new password must be different from your current password.');
            hasError = true;
        }

        if (!new_password_confirmation) {
            showStaffFieldError(`${prefix}-profile-new-password-confirmation-error`, 'Please confirm your new password.');
            hasError = true;
        } else if (new_password && new_password !== new_password_confirmation) {
            showStaffFieldError(`${prefix}-profile-new-password-confirmation-error`, 'New password confirmation does not match.');
            hasError = true;
        }

        if (hasError) return;

        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.classList.add('opacity-75', 'cursor-not-allowed');
        }
        if (saveText) {
            saveText.textContent = 'Updating...';
        }

        const endpoint = getApiEndpoint(prefix, 'password');

        fetch(endpoint, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                current_password,
                new_password,
                new_password_confirmation
            })
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) {
                if (response.status === 422 && data.errors) {
                    if (data.errors.current_password) showStaffFieldError(`${prefix}-profile-current-password-error`, data.errors.current_password[0]);
                    if (data.errors.new_password) showStaffFieldError(`${prefix}-profile-new-password-error`, data.errors.new_password[0]);
                    if (data.errors.new_password_confirmation) showStaffFieldError(`${prefix}-profile-new-password-confirmation-error`, data.errors.new_password_confirmation[0]);

                    throw new Error(data.message || 'Validation failed.');
                }
                throw new Error(data.message || 'Failed to update password.');
            }
            return data;
        })
        .then(data => {
            if (data && data.success) {
                resetStaffPasswordForm(prefix);
                showStaffPasswordSuccess(data.message || 'Password updated successfully.', prefix);
                loadStaffProfileData(prefix);
            }
        })
        .catch(err => {
            console.error(`Error updating ${prefix} password:`, err);
            if (!document.getElementById(`${prefix}-profile-current-password-error`)?.textContent &&
                !document.getElementById(`${prefix}-profile-new-password-error`)?.textContent &&
                !document.getElementById(`${prefix}-profile-new-password-confirmation-error`)?.textContent) {
                showStaffPasswordError(err.message || 'Failed to update password.', prefix);
            }
        })
        .finally(() => {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
            if (saveText) {
                saveText.textContent = 'Update Password';
            }
        });
    }

    /**
     * Populate Account Information metadata grid
     */
    function populateAccountInformation(info, prefix = 'admin') {
        if (!info) return;

        const idEl = document.getElementById(`${prefix}-account-info-user-id`);
        const roleEl = document.getElementById(`${prefix}-account-info-role`);
        const createdEl = document.getElementById(`${prefix}-account-info-created-at`);
        const verifiedEl = document.getElementById(`${prefix}-account-info-email-verified`);
        const profileUpdateEl = document.getElementById(`${prefix}-account-info-last-profile-update`);
        const passwordChangeEl = document.getElementById(`${prefix}-account-info-last-password-change`);

        if (idEl) idEl.textContent = info.user_id ? `#${info.user_id}` : '--';
        if (roleEl) roleEl.textContent = info.role_display || (prefix === 'dispatcher' || prefix === 'fleet' ? 'Fleet Operations Manager' : 'Administrator');
        if (createdEl) createdEl.textContent = formatDateString(info.created_at);
        if (verifiedEl) verifiedEl.textContent = info.email_verified_at ? 'Verified (' + formatDateString(info.email_verified_at) + ')' : 'Not Verified';
        if (profileUpdateEl) profileUpdateEl.textContent = formatDateString(info.last_profile_update);
        if (passwordChangeEl) passwordChangeEl.textContent = info.last_password_change ? formatDateString(info.last_password_change) : 'Never';
    }

    /**
     * Render Profile Completion progress bar and missing field chips
     */
    function renderProfileCompletion(completion, prefix = 'admin') {
        if (!completion) return;

        const countEl = document.getElementById(`${prefix}-profile-completion-count`);
        const badgeEl = document.getElementById(`${prefix}-profile-completion-badge`);
        const barEl = document.getElementById(`${prefix}-profile-completion-bar`);
        const missingContainer = document.getElementById(`${prefix}-profile-completion-missing-container`);
        const missingChips = document.getElementById(`${prefix}-profile-completion-missing-chips`);

        const pct = completion.percentage !== undefined ? completion.percentage : 0;
        const completed = completion.completed !== undefined ? completion.completed : 0;
        const total = completion.total !== undefined ? completion.total : 6;

        if (countEl) countEl.textContent = `${completed} / ${total} fields completed`;
        if (badgeEl) badgeEl.textContent = `${pct}%`;
        if (barEl) barEl.style.width = `${pct}%`;

        if (missingChips && missingContainer) {
            missingChips.innerHTML = '';
            const missing = completion.missing || [];
            if (missing.length > 0) {
                missingContainer.classList.remove('hidden');
                missing.forEach(fieldName => {
                    const chip = document.createElement('span');
                    chip.className = 'px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200';
                    chip.textContent = fieldName;
                    missingChips.appendChild(chip);
                });
            } else {
                missingContainer.classList.add('hidden');
            }
        }
    }

    /**
     * Render Recent Activity timeline list
     */
    function renderRecentActivity(activities, prefix = 'admin') {
        const loadingEl = document.getElementById(`${prefix}-recent-activity-loading`);
        const emptyEl = document.getElementById(`${prefix}-recent-activity-empty`);
        const listEl = document.getElementById(`${prefix}-recent-activity-list`);

        if (loadingEl) loadingEl.classList.add('hidden');
        if (!listEl) return;

        listEl.innerHTML = '';

        if (!activities || !Array.isArray(activities) || activities.length === 0) {
            if (emptyEl) emptyEl.classList.remove('hidden');
            return;
        }

        if (emptyEl) emptyEl.classList.add('hidden');

        activities.forEach(act => {
            const item = document.createElement('div');
            item.className = 'flex items-start gap-3 p-3 bg-slate-50/70 border border-slate-100 rounded-xl transition hover:bg-slate-50';

            let badgeStyle = 'bg-slate-100 text-slate-700 border-slate-200';
            let iconName = 'ti-activity';

            const typeLower = (act.type || '').toLowerCase();
            if (typeLower === 'security') {
                badgeStyle = 'bg-rose-50 text-rose-700 border-rose-200';
                iconName = 'ti-shield-lock';
            } else if (typeLower === 'profile') {
                badgeStyle = 'bg-blue-50 text-blue-700 border-blue-200';
                iconName = 'ti-user';
            }

            item.innerHTML = `
                <div class="p-2 rounded-lg ${badgeStyle} shrink-0 border mt-0.5">
                    <i class="ti ${iconName} text-base"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-extrabold text-slate-800 truncate">${escapeHtml(act.description || 'Activity logged')}</span>
                        <span class="text-[10px] font-semibold text-slate-400 shrink-0">${formatDateString(act.created_at)}</span>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mt-0.5 block">${escapeHtml(act.type || 'General')}</span>
                </div>
            `;
            listEl.appendChild(item);
        });
    }

    function clearStaffProfileErrors(prefix = 'admin') {
        const errorFieldIds = [
            `${prefix}-profile-name-error`,
            `${prefix}-profile-email-error`,
            `${prefix}-profile-contact-number-error`,
            `${prefix}-profile-address-error`,
            `${prefix}-profile-emergency-contact-error`,
            `${prefix}-profile-photo-error`
        ];

        errorFieldIds.forEach(id => {
            const errEl = document.getElementById(id);
            if (errEl) {
                errEl.textContent = '';
                errEl.classList.add('hidden');
            }
        });

        const errorAlert = document.getElementById(`${prefix}-profile-error`);
        const successAlert = document.getElementById(`${prefix}-profile-success`);

        if (errorAlert) errorAlert.classList.add('hidden');
        if (successAlert) successAlert.classList.add('hidden');
    }

    function showStaffFieldError(fieldId, msg) {
        const errEl = document.getElementById(fieldId);
        if (errEl) {
            errEl.textContent = msg;
            errEl.classList.remove('hidden');
        }
    }

    function showStaffProfileSuccess(msg, prefix = 'admin') {
        const alertEl = document.getElementById(`${prefix}-profile-success`);
        const msgEl = document.getElementById(`${prefix}-profile-success-message`);
        if (alertEl && msgEl) {
            msgEl.textContent = msg;
            alertEl.classList.remove('hidden');
        }
    }

    function showStaffProfileError(msg, prefix = 'admin') {
        const alertEl = document.getElementById(`${prefix}-profile-error`);
        const msgEl = document.getElementById(`${prefix}-profile-error-message`);
        if (alertEl && msgEl) {
            msgEl.textContent = msg;
            alertEl.classList.remove('hidden');
        }
    }

    function formatDateString(isoStr) {
        if (!isoStr) return '--';
        try {
            const d = new Date(isoStr);
            if (isNaN(d.getTime())) return isoStr;
            return d.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        } catch (e) {
            return isoStr;
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Expose unified helpers on window
    window.loadStaffProfileData = loadStaffProfileData;
    window.handleStaffProfileSubmit = handleStaffProfileSubmit;
    window.handleStaffPhotoUpload = handleStaffPhotoUpload;
    window.handleStaffPhotoRemove = handleStaffPhotoRemove;
    window.resetStaffProfileForm = resetStaffProfileForm;
    window.toggleStaffPasswordVisibility = toggleStaffPasswordVisibility;
    window.handleStaffPasswordSubmit = handleStaffPasswordSubmit;
    window.resetStaffPasswordForm = resetStaffPasswordForm;
    window.updateStaffProfileIdentity = updateStaffProfileIdentity;
    window.getStaffInitials = getStaffInitials;

    // Backward-compatibility / Role specific aliases
    window.loadAdminProfileData = () => loadStaffProfileData('admin', '/admin/api/profile');
    window.handleAdminProfileSubmit = (e) => handleStaffProfileSubmit(e, 'admin');
    window.handleAdminPhotoUpload = (e) => handleStaffPhotoUpload(e, 'admin');
    window.handleAdminPhotoRemove = () => handleStaffPhotoRemove('admin');
    window.resetAdminProfileForm = () => resetStaffProfileForm('admin');
    window.updateAdminProfileIdentity = updateStaffProfileIdentity;
    window.getAdminInitials = getStaffInitials;
    window.togglePasswordVisibility = toggleStaffPasswordVisibility;
    window.handleAdminPasswordSubmit = (e) => handleStaffPasswordSubmit(e, 'admin');
    window.resetAdminPasswordForm = () => resetStaffPasswordForm('admin');

    window.loadDispatcherProfileData = () => loadStaffProfileData('dispatcher', '/fleet/api/profile');
    window.handleDispatcherProfileSubmit = (e) => handleStaffProfileSubmit(e, 'dispatcher');
    window.handleDispatcherPhotoUpload = (e) => handleStaffPhotoUpload(e, 'dispatcher');
    window.handleDispatcherPhotoRemove = () => handleStaffPhotoRemove('dispatcher');
    window.resetDispatcherProfileForm = () => resetStaffProfileForm('dispatcher');
    window.handleDispatcherPasswordSubmit = (e) => handleStaffPasswordSubmit(e, 'dispatcher');
    window.resetDispatcherPasswordForm = () => resetStaffPasswordForm('dispatcher');
})();
