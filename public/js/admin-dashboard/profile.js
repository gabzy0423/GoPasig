/**
 * GoPasig Admin Account Profile Management (Staff Profile SPA Logic)
 */

(function () {
    let lastLoadedAdminProfile = null;

    /**
     * Compute initials deterministically:
     * - First letter of first word + first letter of last word if 2+ words.
     * - First letter of single word if 1 word.
     * - Uppercase, max 2 characters.
     */
    function getAdminInitials(name) {
        if (!name || typeof name !== 'string' || !name.trim()) {
            return 'A';
        }
        const parts = name.trim().split(/\s+/).filter(Boolean);
        if (parts.length === 1) {
            return parts[0].charAt(0).toUpperCase();
        }
        return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
    }

    /**
     * Synchronize topbar identity display elements with fresh user profile.
     */
    function updateAdminProfileIdentity(user) {
        if (!user || !user.name) return;

        const topbarName = document.getElementById('topbar-admin-name');
        const topbarAvatar = document.getElementById('topbar-admin-avatar');

        if (topbarName) {
            topbarName.textContent = user.name;
        }

        if (topbarAvatar) {
            const photoUrl = user.profile_photo_url || (user.staff_profile ? user.staff_profile.profile_photo_url : null);
            if (photoUrl) {
                topbarAvatar.innerHTML = `<img src="${photoUrl}" alt="${user.name}" class="h-full w-full object-cover rounded-full">`;
            } else {
                topbarAvatar.textContent = getAdminInitials(user.name);
            }
        }
    }

    /**
     * Load current admin profile via GET /admin/api/profile
     */
    function loadAdminProfileData() {
        const loadingEl = document.getElementById('admin-profile-loading');
        const successAlert = document.getElementById('admin-profile-success');
        const errorAlert = document.getElementById('admin-profile-error');

        if (loadingEl) loadingEl.classList.remove('hidden');
        if (successAlert) successAlert.classList.add('hidden');
        if (errorAlert) errorAlert.classList.add('hidden');

        clearAdminProfileErrors();

        const endpoint = (window.GoPasigConfig && window.GoPasigConfig.profileUrl)
            ? window.GoPasigConfig.profileUrl
            : '/admin/api/profile';

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
                lastLoadedAdminProfile = JSON.parse(JSON.stringify(user));

                populateAdminProfileFields(user);
                updateAdminProfileIdentity(user);

                if (data.account_information) {
                    populateAccountInformation(data.account_information);
                }
                if (data.profile_completion) {
                    renderProfileCompletion(data.profile_completion);
                }
                if (data.recent_activity) {
                    renderRecentActivity(data.recent_activity);
                }
            } else {
                showAdminProfileError('Unable to load profile data.');
            }
        })
        .catch(err => {
            console.error('Error loading admin profile:', err);
            showAdminProfileError('Failed to fetch account profile data.');
        })
        .finally(() => {
            if (loadingEl) loadingEl.classList.add('hidden');
        });
    }

    /**
     * Populate form fields with user model data and staff profile details.
     */
    function populateAdminProfileFields(user) {
        const nameInput = document.getElementById('admin-profile-name');
        const emailInput = document.getElementById('admin-profile-email');
        const roleInput = document.getElementById('admin-profile-role');
        const idDisplay = document.getElementById('admin-profile-id-display');
        const displayName = document.getElementById('admin-profile-display-name');
        const avatarPreview = document.getElementById('admin-profile-avatar-preview');
        const removePhotoBtn = document.getElementById('admin-profile-photo-remove-btn');

        // Supplemental Staff Fields
        const contactInput = document.getElementById('admin-profile-contact-number');
        const addressInput = document.getElementById('admin-profile-address');
        const emergencyInput = document.getElementById('admin-profile-emergency-contact');

        if (nameInput) nameInput.value = user.name || '';
        if (emailInput) emailInput.value = user.email || '';
        if (roleInput) roleInput.value = 'Administrator';
        if (idDisplay) idDisplay.value = user.id ? `#${user.id}` : '--';
        if (displayName) displayName.textContent = user.name || 'Administrator';

        const photoUrl = user.profile_photo_url || (user.staff_profile ? user.staff_profile.profile_photo_url : null);
        if (avatarPreview) {
            if (photoUrl) {
                avatarPreview.innerHTML = `<img src="${photoUrl}" alt="${user.name}" class="h-full w-full object-cover rounded-full">`;
            } else {
                avatarPreview.textContent = getAdminInitials(user.name);
            }
        }

        if (removePhotoBtn) {
            if (photoUrl) {
                removePhotoBtn.classList.remove('hidden');
            } else {
                removePhotoBtn.classList.add('hidden');
            }
        }

        const staff = user.staff_profile || {};
        if (contactInput) contactInput.value = staff.contact_number || '';
        if (addressInput) addressInput.value = staff.address || '';
        if (emergencyInput) emergencyInput.value = staff.emergency_contact || '';
    }

    /**
     * Submit profile changes via PUT /admin/api/profile
     */
    function handleAdminProfileSubmit(event) {
        event.preventDefault();

        clearAdminProfileErrors();

        const nameInput = document.getElementById('admin-profile-name');
        const emailInput = document.getElementById('admin-profile-email');
        const contactInput = document.getElementById('admin-profile-contact-number');
        const addressInput = document.getElementById('admin-profile-address');
        const emergencyInput = document.getElementById('admin-profile-emergency-contact');

        const saveBtn = document.getElementById('admin-profile-save');
        const saveText = document.getElementById('admin-profile-save-text');

        const name = nameInput ? nameInput.value.trim() : '';
        const email = emailInput ? emailInput.value.trim() : '';
        const contact_number = contactInput ? contactInput.value.trim() : '';
        const address = addressInput ? addressInput.value.trim() : '';
        const emergency_contact = emergencyInput ? emergencyInput.value.trim() : '';

        if (!name) {
            showAdminFieldError('admin-profile-name-error', 'Full Name is required.');
            return;
        }

        if (!email) {
            showAdminFieldError('admin-profile-email-error', 'Email Address is required.');
            return;
        }

        const realSaveBtn = document.getElementById('admin-profile-save');
        if (realSaveBtn) {
            realSaveBtn.disabled = true;
            realSaveBtn.classList.add('opacity-75', 'cursor-not-allowed');
        }
        if (saveText) {
            saveText.textContent = 'Saving...';
        }

        const endpoint = (window.GoPasigConfig && window.GoPasigConfig.profileUpdateUrl)
            ? window.GoPasigConfig.profileUpdateUrl
            : '/admin/api/profile';

        const csrfToken = (window.GoPasigConfig && window.GoPasigConfig.csrfToken)
            ? window.GoPasigConfig.csrfToken
            : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(endpoint, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
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
                    if (data.errors.name) showAdminFieldError('admin-profile-name-error', data.errors.name[0]);
                    if (data.errors.email) showAdminFieldError('admin-profile-email-error', data.errors.email[0]);
                    if (data.errors.contact_number) showAdminFieldError('admin-profile-contact-number-error', data.errors.contact_number[0]);
                    if (data.errors.address) showAdminFieldError('admin-profile-address-error', data.errors.address[0]);
                    if (data.errors.emergency_contact) showAdminFieldError('admin-profile-emergency-contact-error', data.errors.emergency_contact[0]);

                    throw new Error(data.message || 'Validation failed.');
                }
                throw new Error(data.message || 'Failed to update profile.');
            }
            return data;
        })
        .then(data => {
            if (data && data.success && data.user) {
                const user = data.user;
                lastLoadedAdminProfile = JSON.parse(JSON.stringify(user));

                populateAdminProfileFields(user);
                updateAdminProfileIdentity(user);

                showAdminProfileSuccess(data.message || 'Profile updated successfully.');
            }
        })
        .catch(err => {
            console.error('Error updating admin profile:', err);
            showAdminProfileError(err.message || 'An error occurred while saving your profile.');
        })
        .finally(() => {
            if (realSaveBtn) {
                realSaveBtn.disabled = false;
                realSaveBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
            if (saveText) {
                saveText.textContent = 'Save Changes';
            }
        });
    }

    /**
     * Upload profile photo via POST /admin/api/profile/photo
     */
    function handleAdminPhotoUpload(event) {
        const fileInput = event.target;
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            return;
        }

        const file = fileInput.files[0];
        const uploadBtn = document.getElementById('admin-profile-photo-upload-btn');
        const uploadText = document.getElementById('admin-profile-photo-upload-text');

        clearAdminProfileErrors();

        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!allowedTypes.includes(file.type.toLowerCase())) {
            showAdminPhotoError('The profile photo field must be a file of type: jpeg, jpg, png, webp.');
            fileInput.value = '';
            return;
        }

        if (file.size > 2048 * 1024) {
            showAdminPhotoError('The profile photo field must not be greater than 2048 kilobytes.');
            fileInput.value = '';
            return;
        }

        if (uploadBtn) {
            uploadBtn.disabled = true;
            uploadBtn.classList.add('opacity-75', 'cursor-not-allowed');
        }
        if (uploadText) {
            uploadText.textContent = 'Uploading...';
        }

        const formData = new FormData();
        formData.append('photo', file);

        const endpoint = (window.GoPasigConfig && window.GoPasigConfig.profilePhotoUrl)
            ? window.GoPasigConfig.profilePhotoUrl
            : '/admin/api/profile/photo';

        const csrfToken = (window.GoPasigConfig && window.GoPasigConfig.csrfToken)
            ? window.GoPasigConfig.csrfToken
            : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(endpoint, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(async response => {
            const data = await response.json();
            if (!response.ok) {
                if (response.status === 422 && data.errors) {
                    const firstErrKey = Object.keys(data.errors)[0];
                    const firstMsg = data.errors[firstErrKey] ? data.errors[firstErrKey][0] : 'Validation failed.';
                    showAdminPhotoError(firstMsg);
                    throw new Error(firstMsg);
                }
                throw new Error(data.message || 'Failed to upload photo.');
            }
            return data;
        })
        .then(data => {
            if (data && data.success && data.user) {
                const user = data.user;
                lastLoadedAdminProfile = JSON.parse(JSON.stringify(user));

                populateAdminProfileFields(user);
                updateAdminProfileIdentity(user);
                showAdminProfileSuccess(data.message || 'Profile photo uploaded successfully.');
            }
        })
        .catch(err => {
            console.error('Error uploading profile photo:', err);
            if (!document.getElementById('admin-profile-photo-error')?.textContent) {
                showAdminProfileError(err.message || 'Failed to upload profile photo.');
            }
        })
        .finally(() => {
            if (uploadBtn) {
                uploadBtn.disabled = false;
                uploadBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
            if (uploadText) {
                uploadText.textContent = 'Upload Photo';
            }
            fileInput.value = '';
        });
    }

    /**
     * Remove profile photo via DELETE /admin/api/profile/photo
     */
    function handleAdminPhotoRemove() {
        const removeBtn = document.getElementById('admin-profile-photo-remove-btn');
        const removeText = document.getElementById('admin-profile-photo-remove-text');

        clearAdminProfileErrors();

        if (removeBtn) {
            removeBtn.disabled = true;
            removeBtn.classList.add('opacity-75', 'cursor-not-allowed');
        }
        if (removeText) {
            removeText.textContent = 'Removing...';
        }

        const endpoint = (window.GoPasigConfig && window.GoPasigConfig.profilePhotoUrl)
            ? window.GoPasigConfig.profilePhotoUrl
            : '/admin/api/profile/photo';

        const csrfToken = (window.GoPasigConfig && window.GoPasigConfig.csrfToken)
            ? window.GoPasigConfig.csrfToken
            : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(endpoint, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
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
                lastLoadedAdminProfile = JSON.parse(JSON.stringify(user));

                populateAdminProfileFields(user);
                updateAdminProfileIdentity(user);
                showAdminProfileSuccess(data.message || 'Profile photo removed successfully.');
            }
        })
        .catch(err => {
            console.error('Error removing profile photo:', err);
            showAdminProfileError(err.message || 'Failed to remove profile photo.');
        })
        .finally(() => {
            if (removeBtn) {
                removeBtn.disabled = false;
                removeBtn.classList.remove('opacity-75', 'cursor-not-allowed');
            }
            if (removeText) {
                removeText.textContent = 'Remove Photo';
            }
        });
    }

    /**
     * Restore form inputs to baseline loaded/saved values.
     */
    function resetAdminProfileForm() {
        clearAdminProfileErrors();
        if (lastLoadedAdminProfile) {
            populateAdminProfileFields(lastLoadedAdminProfile);
        } else {
            loadAdminProfileData();
        }
    }

    function showAdminFieldError(elementId, message) {
        const errorEl = document.getElementById(elementId);
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        }
    }

    function showAdminPhotoError(msg) {
        const photoErrorEl = document.getElementById('admin-profile-photo-error');
        if (photoErrorEl) {
            photoErrorEl.textContent = msg;
            photoErrorEl.classList.remove('hidden');
        } else {
            showAdminProfileError(msg);
        }
    }

    function clearAdminProfileErrors() {
        const errorFieldIds = [
            'admin-profile-name-error',
            'admin-profile-email-error',
            'admin-profile-contact-number-error',
            'admin-profile-address-error',
            'admin-profile-emergency-contact-error',
            'admin-profile-photo-error'
        ];

        errorFieldIds.forEach(id => {
            const errEl = document.getElementById(id);
            if (errEl) {
                errEl.textContent = '';
                errEl.classList.add('hidden');
            }
        });

        const errorAlert = document.getElementById('admin-profile-error');
        const successAlert = document.getElementById('admin-profile-success');

        if (errorAlert) errorAlert.classList.add('hidden');
        if (successAlert) successAlert.classList.add('hidden');
    }

    function showAdminProfileSuccess(msg) {
        const alertEl = document.getElementById('admin-profile-success');
        const msgEl = document.getElementById('admin-profile-success-message');
        if (alertEl && msgEl) {
            msgEl.textContent = msg;
            alertEl.classList.remove('hidden');
        }
    }

    function showAdminProfileError(msg) {
        const alertEl = document.getElementById('admin-profile-error');
        const msgEl = document.getElementById('admin-profile-error-message');
        if (alertEl && msgEl) {
            msgEl.textContent = msg;
            alertEl.classList.remove('hidden');
        }
    }

    /**
     * Password Visibility Toggle (Eye Icon)
     */
    function togglePasswordVisibility(fieldId, iconId) {
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
    function clearAdminPasswordErrors() {
        const passwordErrorFieldIds = [
            'admin-profile-current-password-error',
            'admin-profile-new-password-error',
            'admin-profile-new-password-confirmation-error'
        ];

        passwordErrorFieldIds.forEach(id => {
            const errEl = document.getElementById(id);
            if (errEl) {
                errEl.textContent = '';
                errEl.classList.add('hidden');
            }
        });

        const errorAlert = document.getElementById('admin-profile-password-error');
        const successAlert = document.getElementById('admin-profile-password-success');

        if (errorAlert) errorAlert.classList.add('hidden');
        if (successAlert) successAlert.classList.add('hidden');
    }

    function showAdminPasswordSuccess(msg) {
        const alertEl = document.getElementById('admin-profile-password-success');
        const msgEl = document.getElementById('admin-profile-password-success-message');
        if (alertEl && msgEl) {
            msgEl.textContent = msg;
            alertEl.classList.remove('hidden');
        }
    }

    function showAdminPasswordError(msg) {
        const alertEl = document.getElementById('admin-profile-password-error');
        const msgEl = document.getElementById('admin-profile-password-error-message');
        if (alertEl && msgEl) {
            msgEl.textContent = msg;
            alertEl.classList.remove('hidden');
        }
    }

    /**
     * Reset Password Form Inputs Only
     */
    function resetAdminPasswordForm() {
        const currentInput = document.getElementById('admin-profile-current-password');
        const newInput = document.getElementById('admin-profile-new-password');
        const confirmInput = document.getElementById('admin-profile-new-password-confirmation');

        if (currentInput) currentInput.value = '';
        if (newInput) newInput.value = '';
        if (confirmInput) confirmInput.value = '';

        // Reset inputs back to password type if toggled
        ['admin-profile-current-password', 'admin-profile-new-password', 'admin-profile-new-password-confirmation'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.type = 'password';
        });

        const icons = [
            { id: 'current-password-eye-icon' },
            { id: 'new-password-eye-icon' },
            { id: 'confirm-password-eye-icon' }
        ];

        icons.forEach(item => {
            const iconEl = document.getElementById(item.id);
            if (iconEl) {
                iconEl.classList.remove('ti-eye-off');
                iconEl.classList.add('ti-eye');
            }
        });

        clearAdminPasswordErrors();
    }

    /**
     * Submit Password Update via PUT /admin/api/profile/password
     */
    function handleAdminPasswordSubmit(event) {
        event.preventDefault();

        clearAdminPasswordErrors();

        const currentInput = document.getElementById('admin-profile-current-password');
        const newInput = document.getElementById('admin-profile-new-password');
        const confirmInput = document.getElementById('admin-profile-new-password-confirmation');

        const saveBtn = document.getElementById('admin-profile-password-save');
        const saveText = document.getElementById('admin-profile-password-save-text');

        const current_password = currentInput ? currentInput.value : '';
        const new_password = newInput ? newInput.value : '';
        const new_password_confirmation = confirmInput ? confirmInput.value : '';

        let hasError = false;

        if (!current_password) {
            showAdminFieldError('admin-profile-current-password-error', 'Current password is required.');
            hasError = true;
        }

        if (!new_password) {
            showAdminFieldError('admin-profile-new-password-error', 'New password is required.');
            hasError = true;
        } else if (new_password.length < 8) {
            showAdminFieldError('admin-profile-new-password-error', 'The new password must be at least 8 characters.');
            hasError = true;
        } else if (new_password === current_password) {
            showAdminFieldError('admin-profile-new-password-error', 'The new password must be different from your current password.');
            hasError = true;
        }

        if (!new_password_confirmation) {
            showAdminFieldError('admin-profile-new-password-confirmation-error', 'Please confirm your new password.');
            hasError = true;
        } else if (new_password && new_password !== new_password_confirmation) {
            showAdminFieldError('admin-profile-new-password-confirmation-error', 'New password confirmation does not match.');
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

        const endpoint = (window.GoPasigConfig && window.GoPasigConfig.passwordUpdateUrl)
            ? window.GoPasigConfig.passwordUpdateUrl
            : '/admin/api/profile/password';

        const csrfToken = (window.GoPasigConfig && window.GoPasigConfig.csrfToken)
            ? window.GoPasigConfig.csrfToken
            : document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        fetch(endpoint, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
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
                    if (data.errors.current_password) showAdminFieldError('admin-profile-current-password-error', data.errors.current_password[0]);
                    if (data.errors.new_password) showAdminFieldError('admin-profile-new-password-error', data.errors.new_password[0]);
                    if (data.errors.new_password_confirmation) showAdminFieldError('admin-profile-new-password-confirmation-error', data.errors.new_password_confirmation[0]);

                    throw new Error(data.message || 'Validation failed.');
                }
                throw new Error(data.message || 'Failed to update password.');
            }
            return data;
        })
        .then(data => {
            if (data && data.success) {
                resetAdminPasswordForm();
                showAdminPasswordSuccess(data.message || 'Password updated successfully.');
                loadAdminProfileData();
            }
        })
        .catch(err => {
            console.error('Error updating admin password:', err);
            if (!document.getElementById('admin-profile-current-password-error')?.textContent &&
                !document.getElementById('admin-profile-new-password-error')?.textContent &&
                !document.getElementById('admin-profile-new-password-confirmation-error')?.textContent) {
                showAdminPasswordError(err.message || 'Failed to update password.');
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
    function populateAccountInformation(info) {
        if (!info) return;

        const idEl = document.getElementById('account-info-user-id');
        const roleEl = document.getElementById('account-info-role');
        const createdEl = document.getElementById('account-info-created-at');
        const verifiedEl = document.getElementById('account-info-email-verified');
        const profileUpdateEl = document.getElementById('account-info-last-profile-update');
        const passwordChangeEl = document.getElementById('account-info-last-password-change');

        if (idEl) idEl.textContent = info.user_id ? `#${info.user_id}` : '--';
        if (roleEl) roleEl.textContent = info.role_display || 'Administrator';
        if (createdEl) createdEl.textContent = formatDateString(info.created_at);
        if (verifiedEl) verifiedEl.textContent = info.email_verified_at ? 'Verified (' + formatDateString(info.email_verified_at) + ')' : 'Not Verified';
        if (profileUpdateEl) profileUpdateEl.textContent = formatDateString(info.last_profile_update);
        if (passwordChangeEl) passwordChangeEl.textContent = info.last_password_change ? formatDateString(info.last_password_change) : 'Never';
    }

    /**
     * Render Profile Completion progress bar and missing field chips
     */
    function renderProfileCompletion(completion) {
        if (!completion) return;

        const countEl = document.getElementById('profile-completion-count');
        const badgeEl = document.getElementById('profile-completion-badge');
        const barEl = document.getElementById('profile-completion-bar');
        const missingContainer = document.getElementById('profile-completion-missing-container');
        const missingChips = document.getElementById('profile-completion-missing-chips');

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
    function renderRecentActivity(activities) {
        const loadingEl = document.getElementById('recent-activity-loading');
        const emptyEl = document.getElementById('recent-activity-empty');
        const listEl = document.getElementById('recent-activity-list');

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

    // Expose helpers globally
    window.loadAdminProfileData = loadAdminProfileData;
    window.handleAdminProfileSubmit = handleAdminProfileSubmit;
    window.handleAdminPhotoUpload = handleAdminPhotoUpload;
    window.handleAdminPhotoRemove = handleAdminPhotoRemove;
    window.resetAdminProfileForm = resetAdminProfileForm;
    window.updateAdminProfileIdentity = updateAdminProfileIdentity;
    window.getAdminInitials = getAdminInitials;
    window.togglePasswordVisibility = togglePasswordVisibility;
    window.handleAdminPasswordSubmit = handleAdminPasswordSubmit;
    window.resetAdminPasswordForm = resetAdminPasswordForm;
    window.populateAccountInformation = populateAccountInformation;
    window.renderProfileCompletion = renderProfileCompletion;
    window.renderRecentActivity = renderRecentActivity;
})();
