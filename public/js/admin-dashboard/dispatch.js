function refreshDispatchRuntimeState() {
    window.dispatchEvent(new CustomEvent('request-dispatch-runtime-refresh'));
    return Promise.resolve();
}

// Listener for dynamic database-driven Livewire dispatch success event
window.addEventListener('dispatchSuccessful', () => {
    GoPasigUI.alert('Bus successfully dispatched on route!');

    refreshDispatchRuntimeState().catch((error) => {
        console.error('Background dispatch runtime refresh failed:', error);
    });
});
