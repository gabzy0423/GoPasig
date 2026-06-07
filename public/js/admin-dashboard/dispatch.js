// Listener for dynamic database-driven Livewire dispatch success event
window.addEventListener('dispatchSuccessful', () => {
    if (typeof loadDatabaseFleetData === 'function') {
        loadDatabaseFleetData();
    }
    alert('Bus successfully dispatched on route!');
    switchScreen('overview');
});
