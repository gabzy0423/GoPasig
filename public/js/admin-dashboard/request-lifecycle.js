(function () {
    const nativeFetch = window.fetch.bind(window);
    const nativeSetTimeout = window.setTimeout.bind(window);
    const nativeClearTimeout = window.clearTimeout.bind(window);
    const nativeSetInterval = window.setInterval.bind(window);
    const nativeClearInterval = window.clearInterval.bind(window);
    const activeControllers = new Set();
    const activeTimers = new Set();
    let isLoggingOut = false;

    function isAdminApiRequest(input) {
        const rawUrl = typeof input === 'string' ? input : input?.url;
        if (!rawUrl) return false;
        try {
            const path = new URL(rawUrl, window.location.origin).pathname;
            return path === '/admin/api' || path.startsWith('/admin/api/');
        } catch (_) { return false; }
    }

    function abortError() {
        return new DOMException('Admin logout is in progress', 'AbortError');
    }

    window.fetch = function (input, init) {
        if (!isAdminApiRequest(input)) return nativeFetch(input, init);
        if (isLoggingOut) return Promise.reject(abortError());

        const controller = new AbortController();
        activeControllers.add(controller);
        const requestInit = { ...(init || {}) };
        requestInit.signal = requestInit.signal && typeof AbortSignal.any === 'function'
            ? AbortSignal.any([requestInit.signal, controller.signal])
            : controller.signal;

        return nativeFetch(input, requestInit).finally(() => activeControllers.delete(controller));
    };

    window.setTimeout = function (callback, delay, ...args) {
        const id = nativeSetTimeout(() => {
            activeTimers.delete(id);
            callback(...args);
        }, delay);
        activeTimers.add(id);
        return id;
    };

    window.clearTimeout = function (id) {
        activeTimers.delete(id);
        nativeClearTimeout(id);
    };

    window.setInterval = function (callback, delay, ...args) {
        const id = nativeSetInterval(callback, delay, ...args);
        activeTimers.add(id);
        return id;
    };

    window.clearInterval = function (id) {
        activeTimers.delete(id);
        nativeClearInterval(id);
    };

    function beginLogout() {
        if (isLoggingOut) return;
        isLoggingOut = true;
        activeTimers.forEach((id) => {
            nativeClearTimeout(id);
            nativeClearInterval(id);
        });
        activeTimers.clear();
        activeControllers.forEach((controller) => controller.abort());
        activeControllers.clear();
    }

    function isAdminLogoutForm(form) {
        const action = new URL(form.action, window.location.origin).pathname;
        return form.method.toUpperCase() === 'POST' && action === '/logout';
    }

    document.addEventListener('submit', (event) => {
        if (event.target instanceof HTMLFormElement && isAdminLogoutForm(event.target)) beginLogout();
    }, true);

    window.GoPasigAdminRequestLifecycle = {
        beginLogout,
        isLoggingOut: () => isLoggingOut,
        activeRequestCount: () => activeControllers.size,
    };
}());