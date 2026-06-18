window.GoPasigSettingsConfig = {
    fetchUrl: "/admin/api/settings",
    saveUrl: "/admin/api/settings",
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

let activeSettingsTab = 'system';

function switchSettingsTab(tabName) {
    activeSettingsTab = tabName;
    sessionStorage.setItem('active_settings_tab', tabName);
    const btnSystem = document.getElementById('tab-btn-system');
    const btnSimulation = document.getElementById('tab-btn-simulation');
    const panelSystem = document.getElementById('panel-system');
    const panelSimulation = document.getElementById('panel-simulation');

    if (!btnSystem || !btnSimulation || !panelSystem || !panelSimulation) return;

    if (tabName === 'system') {
        btnSystem.className = "px-4 py-2 text-xs font-extrabold uppercase tracking-wider transition bg-[#003F87] text-white rounded-lg shadow-sm";
        btnSimulation.className = "px-4 py-2 text-xs font-extrabold uppercase tracking-wider transition text-slate-500 hover:text-slate-800 rounded-lg";
        panelSystem.classList.remove('hidden');
        panelSimulation.classList.add('hidden');
    } else {
        btnSimulation.className = "px-4 py-2 text-xs font-extrabold uppercase tracking-wider transition bg-[#003F87] text-white rounded-lg shadow-sm";
        btnSystem.className = "px-4 py-2 text-xs font-extrabold uppercase tracking-wider transition text-slate-500 hover:text-slate-800 rounded-lg";
        panelSimulation.classList.remove('hidden');
        panelSystem.classList.add('hidden');
    }
}

async function initSettingsDashboard() {
    try {
        const response = await fetch(window.GoPasigSettingsConfig.fetchUrl);
        if (!response.ok) throw new Error('Failed to load settings.');
        const data = await response.json();

        renderSettingsTable('system', data.system_settings);
        renderSettingsTable('simulation', data.simulation_defaults);

        // Restore active tab
        const savedTab = sessionStorage.getItem('active_settings_tab') || 'system';
        switchSettingsTab(savedTab);
    } catch (error) {
        console.error('Error loading settings:', error);
        showSettingsNotification('Failed to load settings from server.', true);
    }
}

function renderSettingsTable(type, settings) {
    const tbody = document.getElementById(`${type}-settings-tbody`);
    if (!tbody) return;

    tbody.innerHTML = '';
    if (!settings || settings.length === 0) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-12 text-center text-slate-400 font-semibold">No ${type} settings found in the database.</td></tr>`;
        return;
    }

    settings.forEach(setting => {
        const tr = document.createElement('tr');
        tr.className = "hover:bg-slate-50/50 transition";
        
        tr.innerHTML = `
            <td class="py-3 font-mono text-[11px] text-[#003F87]">${setting.key}</td>
            <td class="py-3 text-slate-500 font-medium pr-4 leading-relaxed">${setting.description || 'No description provided.'}</td>
            <td class="py-3 pr-4">
                <input type="text" id="input-${type}-${setting.key}" value="${setting.value || ''}" 
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-1.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white font-mono">
            </td>
            <td class="py-3 text-right">
                <button onclick="saveSettingAction('${type}', '${setting.key}')" 
                        class="rounded bg-[#003F87] hover:bg-[#002D62] text-white px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-wider transition cursor-pointer">
                    Save
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

async function saveSettingAction(type, key) {
    const inputElement = document.getElementById(`input-${type}-${key}`);
    if (!inputElement) return;

    const value = inputElement.value;

    try {
        const response = await fetch(window.GoPasigSettingsConfig.saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.GoPasigSettingsConfig.csrfToken
            },
            body: JSON.stringify({ type, key, value })
        });

        const data = await response.json();
        if (response.ok && data.success) {
            showSettingsNotification(data.message);
            
            if (key === 'default_demand_threshold') {
                window.location.hash = 'settings';
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        } else {
            showSettingsNotification(data.message || 'Failed to save setting.', true);
        }
    } catch (error) {
        console.error('Error saving setting:', error);
        showSettingsNotification('Failed to save setting due to a network/server error.', true);
    }
}

function showSettingsNotification(message, isError = false) {
    const alertSuccess = document.getElementById('settings-alert-success');
    const alertError = document.getElementById('settings-alert-error');
    if (!alertSuccess || !alertError) return;

    if (isError) {
        alertError.querySelector('span').innerText = message;
        alertError.classList.remove('hidden');
        alertSuccess.classList.add('hidden');
        setTimeout(() => alertError.classList.add('hidden'), 5000);
    } else {
        alertSuccess.querySelector('span').innerText = message;
        alertSuccess.classList.remove('hidden');
        alertError.classList.add('hidden');
        setTimeout(() => alertSuccess.classList.add('hidden'), 5000);
    }
}

function openAddSettingModal() {
    const modal = document.getElementById('add-setting-modal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function closeAddSettingModal() {
    const modal = document.getElementById('add-setting-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
    const form = document.getElementById('add-setting-form');
    if (form) {
        form.reset();
    }
}

async function handleAddSettingSubmit(event) {
    event.preventDefault();

    const type = document.querySelector('input[name="new_setting_type"]:checked').value;
    const key = document.getElementById('new_setting_key').value;
    const value = document.getElementById('new_setting_value').value;
    const description = document.getElementById('new_setting_description').value;

    try {
        const response = await fetch(window.GoPasigSettingsConfig.saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.GoPasigSettingsConfig.csrfToken
            },
            body: JSON.stringify({ type, key, value, description })
        });

        const data = await response.json();
        if (response.ok && data.success) {
            showSettingsNotification(data.message);
            closeAddSettingModal();
            initSettingsDashboard();
        } else {
            showSettingsNotification(data.message || 'Failed to create setting.', true);
        }
    } catch (error) {
        console.error('Error creating setting:', error);
        showSettingsNotification('Failed to create setting due to a network/server error.', true);
    }
}
