import './globals/theme.js'; /* By Sheaf.dev */ 

import './bootstrap';
import focus from '@alpinejs/focus';

// Livewire v3 standard: register plugins on the bundled Alpine instance
document.addEventListener('livewire:init', () => {
    window.Alpine.plugin(focus);
});

// Fallback only for non-Livewire pages using dynamic imports
document.addEventListener('DOMContentLoaded', () => {
    if (!window.Livewire && !document.querySelector('[wire\\:id], [wire\\:initial-data], [wire\\:snapshot], [wire\\:effects]')) {
        import('alpinejs').then(({ default: Alpine }) => {
            window.Alpine = Alpine;
            Alpine.plugin(focus);
            Alpine.start();
        });
    }
});
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';

// now you can register
// components using Alpine.data(...) and
// plugins using Alpine.plugin(...) 


 
Livewire.start()