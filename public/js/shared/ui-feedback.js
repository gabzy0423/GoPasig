(function () {
    const state = {
        toastRoot: null,
        modalRoot: null,
        loadingRoot: null,
        activeActions: new Set(),
        activeFetches: new Set(),
    };

    function ensureToastRoot() {
        if (state.toastRoot) return state.toastRoot;
        const root = document.createElement('div');
        root.id = 'gopasig-toast-root';
        root.className = 'fixed right-4 bottom-4 z-[9999] flex w-[min(360px,calc(100vw-2rem))] flex-col gap-2 pointer-events-none';
        document.body.appendChild(root);
        state.toastRoot = root;
        return root;
    }

    function ensureModalRoot() {
        if (state.modalRoot) return state.modalRoot;
        const root = document.createElement('div');
        root.id = 'gopasig-modal-root';
        document.body.appendChild(root);
        state.modalRoot = root;
        return root;
    }

    function showLoadingOverlay(message = 'Processing...', detail = 'Please wait.') {
        if (state.loadingRoot) {
            const messageEl = state.loadingRoot.querySelector('[data-loading-message]');
            const detailEl = state.loadingRoot.querySelector('[data-loading-detail]');
            if (messageEl) messageEl.textContent = message;
            if (detailEl) detailEl.textContent = detail;
            return state.loadingRoot;
        }

        const overlay = document.createElement('div');
        overlay.id = 'gopasig-loading-overlay';
        overlay.className = 'fixed inset-0 z-[10050] flex items-center justify-center bg-slate-950/50 px-4 backdrop-blur-sm opacity-0 transition-opacity duration-150';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.setAttribute('aria-busy', 'true');

        const panel = document.createElement('div');
        panel.className = 'w-full max-w-sm rounded-2xl border border-white/20 bg-white/95 p-6 text-center shadow-2xl dark:border-slate-700 dark:bg-slate-900/95';

        const spinner = document.createElement('div');
        spinner.className = 'mx-auto h-10 w-10 animate-spin rounded-full border-4 border-[#003F87]/20 border-t-[#003F87]';

        const heading = document.createElement('p');
        heading.className = 'mt-4 text-sm font-extrabold text-slate-950 dark:text-white';
        heading.dataset.loadingMessage = 'true';
        heading.textContent = message;

        const body = document.createElement('p');
        body.className = 'mt-1 text-xs font-semibold text-slate-500 dark:text-slate-300';
        body.dataset.loadingDetail = 'true';
        body.textContent = detail;

        panel.append(spinner, heading, body);
        overlay.appendChild(panel);
        document.body.appendChild(overlay);
        state.loadingRoot = overlay;

        requestAnimationFrame(() => overlay.classList.remove('opacity-0'));
        return overlay;
    }

    function hideLoadingOverlay() {
        const overlay = state.loadingRoot;
        if (!overlay) return;
        overlay.classList.add('opacity-0');
        window.setTimeout(() => overlay.remove(), 180);
        state.loadingRoot = null;
    }

    function normalizeVariant(variant) {
        if (['success', 'warning', 'error', 'info'].includes(variant)) return variant;
        return 'info';
    }

    function variantClasses(variant) {
        return {
            success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
            warning: 'border-amber-200 bg-amber-50 text-amber-900',
            error: 'border-rose-200 bg-rose-50 text-rose-900',
            info: 'border-sky-200 bg-sky-50 text-sky-900',
        }[normalizeVariant(variant)];
    }

    function variantIcon(variant) {
        return {
            success: 'ti ti-circle-check text-emerald-600',
            warning: 'ti ti-alert-triangle text-amber-600',
            error: 'ti ti-alert-circle text-rose-600',
            info: 'ti ti-info-circle text-sky-600',
        }[normalizeVariant(variant)];
    }

    function toast(message, variant = 'info', options = {}) {
        const root = ensureToastRoot();
        const normalizedVariant = normalizeVariant(variant);
        const item = document.createElement('div');
        item.className = `pointer-events-auto flex items-start gap-2.5 rounded-lg border px-4 py-3 text-sm font-semibold shadow-lg ${variantClasses(normalizedVariant)}`;
        item.setAttribute('role', normalizedVariant === 'error' ? 'alert' : 'status');

        const icon = document.createElement('i');
        icon.className = `${variantIcon(normalizedVariant)} mt-0.5 shrink-0 text-base`;
        icon.setAttribute('aria-hidden', 'true');

        const body = document.createElement('span');
        body.className = 'min-w-0 flex-1 leading-5';
        body.textContent = String(message || '');

        item.append(icon, body);
        root.appendChild(item);
        window.setTimeout(() => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(-4px)';
            item.style.transition = 'opacity 160ms ease, transform 160ms ease';
            window.setTimeout(() => item.remove(), 180);
        }, options.duration || 3600);
    }

    function dialog({ title = 'GoPasig', message = '', variant = 'info', confirmText = 'OK', cancelText = null, input = false, defaultValue = '' }) {
        const root = ensureModalRoot();
        root.innerHTML = '';

        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 z-[10000] flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-sm';

            const panel = document.createElement('div');
            panel.className = 'w-full max-w-md rounded-xl border border-slate-200 bg-white p-5 shadow-2xl';
            panel.setAttribute('role', 'dialog');
            panel.setAttribute('aria-modal', 'true');

            const heading = document.createElement('h2');
            heading.className = 'text-base font-extrabold text-slate-900';
            heading.textContent = title;

            const body = document.createElement('p');
            body.className = 'mt-2 text-sm leading-6 text-slate-600';
            body.textContent = String(message || '');

            let field = null;
            if (input) {
                field = document.createElement('input');
                field.className = 'mt-4 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-800 outline-none focus:border-[#003F87] focus:ring-2 focus:ring-[#003F87]/15';
                field.value = defaultValue || '';
            }

            const actions = document.createElement('div');
            actions.className = 'mt-5 flex justify-end gap-2';

            const close = (value) => {
                root.innerHTML = '';
                resolve(value);
            };

            if (cancelText) {
                const cancel = document.createElement('button');
                cancel.type = 'button';
                cancel.className = 'rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-50';
                cancel.textContent = cancelText;
                cancel.addEventListener('click', () => close(input ? null : false));
                actions.appendChild(cancel);
            }

            const confirm = document.createElement('button');
            confirm.type = 'button';
            confirm.className = 'rounded-lg bg-[#003F87] px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-[#002e63]';
            if (variant === 'error') confirm.className = 'rounded-lg bg-rose-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-rose-700';
            if (variant === 'warning') confirm.className = 'rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-amber-700';
            confirm.textContent = confirmText;
            confirm.addEventListener('click', () => close(input ? field.value : true));
            actions.appendChild(confirm);

            panel.append(heading, body);
            if (field) panel.appendChild(field);
            panel.appendChild(actions);
            overlay.appendChild(panel);
            root.appendChild(overlay);

            confirm.focus();
            if (field) {
                field.focus();
                field.select();
            }
        });
    }

    function confirmDialog(message, options = {}) {
        return dialog({
            title: options.title || 'Confirm Action',
            message,
            variant: options.variant || 'warning',
            confirmText: options.confirmText || 'Confirm',
            cancelText: options.cancelText || 'Cancel',
        });
    }

    function promptDialog(message, defaultValue = '', options = {}) {
        return dialog({
            title: options.title || 'Input Required',
            message,
            variant: options.variant || 'info',
            confirmText: options.confirmText || 'Continue',
            cancelText: options.cancelText || 'Cancel',
            input: true,
            defaultValue,
        });
    }

    function setButtonLoading(button, loading, label = null) {
        if (!button) return;
        if (loading) {
            if (!button.dataset.originalHtml) button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = `<span class="inline-flex items-center gap-2"><span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-t-transparent"></span><span>${label || 'Working...'}</span></span>`;
        } else {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
                delete button.dataset.originalHtml;
            }
        }
    }

    async function guardAction(key, callback) {
        if (state.activeActions.has(key)) return null;
        state.activeActions.add(key);
        try {
            return await callback();
        } finally {
            state.activeActions.delete(key);
        }
    }

    function debounce(callback, delay = 350) {
        let timer = null;
        return function debounced(...args) {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => callback.apply(this, args), delay);
        };
    }

    function unsafeMethod(init) {
        return String(init?.method || 'GET').toUpperCase();
    }

    function requestUrl(input) {
        return typeof input === 'string' ? input : input?.url;
    }

    function requestBodyKey(init) {
        if (!init || init.body == null) return '';
        if (typeof init.body === 'string') return init.body;
        if (init.body instanceof URLSearchParams) return init.body.toString();
        return Object.prototype.toString.call(init.body);
    }

    function duplicateResponse() {
        return new Response(JSON.stringify({
            success: false,
            message: 'This request is already in progress.',
        }), {
            status: 409,
            headers: { 'Content-Type': 'application/json' },
        });
    }

    function installFetchDuplicateGuard() {
        if (window.GoPasigFetchDuplicateGuardInstalled || typeof window.fetch !== 'function') return;
        window.GoPasigFetchDuplicateGuardInstalled = true;
        const nativeFetch = window.fetch.bind(window);

        window.fetch = function guardedFetch(input, init = {}) {
            const method = unsafeMethod(init);
            if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
                return nativeFetch(input, init);
            }

            const url = requestUrl(input);
            const key = `${method}:${url}:${requestBodyKey(init)}`;
            if (state.activeFetches.has(key)) {
                toast('This request is already in progress.', 'warning');
                return Promise.resolve(duplicateResponse());
            }

            state.activeFetches.add(key);
            return nativeFetch(input, init).finally(() => state.activeFetches.delete(key));
        };
    }

    function findSubmitButton(form) {
        return form.querySelector('[type="submit"], button:not([type]), input[type="submit"]');
    }

    function installFormSubmitGuard() {
        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (String(form.method || 'GET').toUpperCase() === 'GET') return;

            if (form.dataset.gopasigSubmitting === 'true') {
                event.preventDefault();
                toast('This action is already in progress.', 'warning');
                return;
            }

            form.dataset.gopasigSubmitting = 'true';
            setButtonLoading(findSubmitButton(form), true);

            window.setTimeout(() => {
                if (!document.body.contains(form)) return;
                form.dataset.gopasigSubmitting = 'false';
                setButtonLoading(findSubmitButton(form), false);
            }, 12000);
        }, true);
    }

    window.GoPasigUI = {
        toast,
        alert: (message, variant = 'info') => toast(message, variant),
        success: (message, options = {}) => toast(message, 'success', options),
        info: (message, options = {}) => toast(message, 'info', options),
        warning: (message, options = {}) => toast(message, 'warning', options),
        error: (message, options = {}) => toast(message, 'error', options),
        confirm: confirmDialog,
        prompt: promptDialog,
        showLoadingOverlay,
        hideLoadingOverlay,
        setButtonLoading,
        guardAction,
        debounce,
    };

    installFetchDuplicateGuard();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', installFormSubmitGuard, { once: true });
    } else {
        installFormSubmitGuard();
    }
}());

