// Dynamic Database-Driven Fleet Maintenance CRUD Controller

// Helper: Retrieve CSRF Token from Config or Meta tag
function getCsrfToken() {
    if (window.GoPasigConfig && window.GoPasigConfig.csrfToken) {
        return window.GoPasigConfig.csrfToken;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Format date for display (e.g., "May 26, 2026, 09:30 AM")
function formatMaintenanceDate(dateString) {
    if (!dateString) return '—';
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true };
    return date.toLocaleDateString('en-US', options);
}

// Parse technician and notes from the combined description field
function parseDescription(descText) {
    const result = { technician: 'Unassigned', notes: 'No notes provided' };
    if (!descText) return result;

    const parts = descText.split(' | ');
    parts.forEach(part => {
        if (part.startsWith('Technician: ')) {
            result.technician = part.replace('Technician: ', '');
        } else if (part.startsWith('Notes: ')) {
            result.notes = part.replace('Notes: ', '');
        } else {
            // Fallback for simple description values
            result.notes = descText;
        }
    });
    return result;
}

// Open inline form and populate bus dropdown dynamically
function openScheduleMaintenanceModal() {
    const listContainer = document.getElementById('maintenance-list-container');
    const formContainer = document.getElementById('maintenance-form-container');
    const busSelect = document.getElementById('maintenance-bus-id');
    const form = document.getElementById('schedule-maintenance-form');
    
    if (form) form.reset();

    // Set default date-time to current local time
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const dateInput = document.getElementById('maintenance-date');
    if (dateInput) {
        dateInput.value = now.toISOString().slice(0, 16);
    }

    // Populate registered buses dynamically from global fleetData
    if (busSelect) {
        busSelect.innerHTML = '<option value="">Select a Bus...</option>';
        if (typeof fleetData !== 'undefined' && fleetData.length > 0) {
            fleetData.forEach(bus => {
                const opt = document.createElement('option');
                opt.value = bus.id;
                opt.textContent = `${bus.plate} (${bus.driver || 'No Assigned Driver'})`;
                busSelect.appendChild(opt);
            });
        }
    }

    if (listContainer) listContainer.classList.add('hidden');
    if (formContainer) formContainer.classList.remove('hidden');

    // Update breadcrumb
    const breadcrumbCurrent = document.getElementById('maintenance-breadcrumb-current');
    if (breadcrumbCurrent) {
        breadcrumbCurrent.textContent = 'Schedule Session';
    }
}

// Close inline form and return to logs list
function closeScheduleMaintenanceModal() {
    const listContainer = document.getElementById('maintenance-list-container');
    const formContainer = document.getElementById('maintenance-form-container');
    
    if (listContainer) listContainer.classList.remove('hidden');
    if (formContainer) formContainer.classList.add('hidden');

    // Reset breadcrumb
    const breadcrumbCurrent = document.getElementById('maintenance-breadcrumb-current');
    if (breadcrumbCurrent) {
        breadcrumbCurrent.textContent = 'Maintenance Logs';
    }
}

// Open inspection modal and load record details
function openInspectionModal(recordId) {
    const listContainer = document.getElementById('maintenance-list-container');
    const inspectionContainer = document.getElementById('maintenance-inspection-container');
    const form = document.getElementById('inspection-form');
    
    if (form) {
        form.reset();
        form.dataset.recordId = recordId;
    }

    // Fetch record details to populate the form
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
    fetch(`${baseUrl}/${recordId}`)
        .then(res => res.json())
        .then(record => {
            const busLabel = record.bus ? record.bus.plate_number : `Bus #${record.bus_id}`;
            const parsedDesc = parseDescription(record.description);
            
            document.getElementById('inspection-bus-label').textContent = busLabel;
            document.getElementById('inspection-record-status').textContent = record.status.toUpperCase();
            document.getElementById('inspection-technician-label').textContent = parsedDesc.technician;
            document.getElementById('inspection-date-label').textContent = formatMaintenanceDate(record.scheduled_at);
        })
        .catch(err => console.error('Failed to load record details:', err));

    if (listContainer) listContainer.classList.add('hidden');
    if (inspectionContainer) inspectionContainer.classList.remove('hidden');
}

// Close inspection modal and return to logs list
function closeInspectionModal() {
    const listContainer = document.getElementById('maintenance-list-container');
    const inspectionContainer = document.getElementById('maintenance-inspection-container');
    
    if (listContainer) listContainer.classList.remove('hidden');
    if (inspectionContainer) inspectionContainer.classList.add('hidden');
}

// Submit inspection form
async function handleInspectionSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const recordId = form.dataset.recordId;
    const inspectionPassed = form.querySelector('input[name="inspection_passed"]:checked').value === 'true';
    const inspectedBy = document.getElementById('inspection-by').value.trim();
    const inspectionNotes = document.getElementById('inspection-notes').value.trim();

    if (!recordId || !inspectedBy) {
        alert('Please fill in all required fields.');
        return;
    }

    const payload = {
        inspection_passed: inspectionPassed,
        inspected_by: inspectedBy,
        inspection_notes: inspectionNotes || ''
    };

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
        
        const response = await fetch(`${baseUrl}/${recordId}/perform-inspection`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert(data.message);
            closeInspectionModal();
            
            // Reload logs
            await fetchMaintenanceLogs();
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
        } else {
            alert(data.message || 'Failed to submit inspection.');
            console.error('Inspection failed:', data);
        }
    } catch (error) {
        alert('Server connection error. Failed to submit inspection.');
        console.error('AJAX inspection error:', error);
    }
}

// Submit new maintenance ticket via AJAX Fetch
async function handleMaintenanceSubmit(event) {
    event.preventDefault();

    const busId = document.getElementById('maintenance-bus-id').value;
    const type = document.getElementById('maintenance-type').value;
    const technician = document.getElementById('maintenance-technician').value.trim();
    const notes = document.getElementById('maintenance-desc').value.trim();
    const scheduledAt = document.getElementById('maintenance-date').value;

    if (!busId || !scheduledAt) {
        alert('Please select a bus unit and scheduled date/time.');
        return;
    }

    // Combine technician and notes into a single description field to respect DB schema
    const combinedDescription = `Technician: ${technician} | Notes: ${notes || 'Regular maintenance check'}`;

    const payload = {
        bus_id: parseInt(busId),
        type: type,
        description: combinedDescription,
        scheduled_at: scheduledAt,
        status: 'scheduled'
    };

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
        
        const response = await fetch(baseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert(data.message);
            closeScheduleMaintenanceModal();
            
            // Reload logs and refresh the global fleet tables/maps
            await fetchMaintenanceLogs();
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
        } else {
            alert(data.message || 'Validation error. Please verify input data.');
            console.error('Maintenance schedule failed:', data.errors || data);
        }
    } catch (error) {
        alert('Server connection error. Failed to schedule maintenance.');
        console.error('AJAX maintenance submit error:', error);
    }
}

// Fetch all maintenance tickets from the database and render the timeline
async function fetchMaintenanceLogs() {
    const container = document.getElementById('maintenance-logs-container');
    if (!container) return;

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
        const response = await fetch(baseUrl);
        const records = await response.json();

        container.innerHTML = ''; // Clear container

        if (records.length === 0) {
            container.innerHTML = `
                <div class="py-12 text-center text-slate-400 font-semibold text-xs border border-dashed border-slate-200 rounded-xl bg-slate-50/50">
                    <i class="ti ti-tool text-2xl text-slate-300 block mb-2"></i>
                    No maintenance records found in the database.
                </div>
            `;
            return;
        }

        records.forEach(record => {
            const parsedDesc = parseDescription(record.description);
            const busLabel = record.bus ? record.bus.plate_number : `Bus #${record.bus_id}`;
            const formattedDate = formatMaintenanceDate(record.scheduled_at);

            // Set bullet colors and badges based on type and status
            let bulletColorClass = 'bg-[#BA7517]'; // preventive / amber
            let badgeBgClass = 'bg-[#FEF7ED]';
            let badgeTextClass = 'text-[#BA7517]';
            
            if (record.type.includes('Corrective')) {
                bulletColorClass = 'bg-[#E24B4A]'; // corrective / red
                badgeBgClass = 'bg-[#FDF2F2]';
                badgeTextClass = 'text-[#E24B4A]';
            }

            let statusBadgeClass = 'bg-[#FEF7ED] text-[#BA7517] border border-orange-100';
            if (record.status === 'completed') {
                statusBadgeClass = 'bg-[#E8F4E0] text-[#639922] border border-emerald-100';
                bulletColorClass = 'bg-[#639922]'; // completed / green
            } else if (record.status === 'cancelled') {
                statusBadgeClass = 'bg-slate-100 text-slate-500 border border-slate-200';
                bulletColorClass = 'bg-slate-400';
            }

            const itemDiv = document.createElement('div');
            itemDiv.className = 'relative pl-6 border-l border-slate-200 pb-4';

            // Action buttons logic based on inspection state
            let actionHtml = '';
            if (record.status !== 'completed' && record.status !== 'cancelled') {
                // Show inspection status if available
                let inspectionStatusHtml = '';
                if (record.inspection_passed === true) {
                    inspectionStatusHtml = '<span class="text-[10px] font-bold text-[#639922]"><i class="ti ti-check-circle"></i> Inspection PASSED</span>';
                } else if (record.inspection_passed === false) {
                    inspectionStatusHtml = '<span class="text-[10px] font-bold text-[#E24B4A]"><i class="ti ti-circle-x"></i> Inspection FAILED</span>';
                } else if (record.status === 'in_progress') {
                    inspectionStatusHtml = '<span class="text-[10px] font-bold text-[#BA7517]"><i class="ti ti-alert-circle"></i> Awaiting Inspection</span>';
                }

                let completeButton = '';
                if (record.status === 'in_progress' && record.inspection_passed !== true) {
                    // Show Inspect button if not yet inspected or failed
                    completeButton = `
                        <button onclick="openInspectionModal(${record.id})" class="text-[10px] font-extrabold text-[#003F87] bg-[#E3F0FF] hover:bg-[#d0e5ff] px-2 py-1 rounded transition-all cursor-pointer">
                            <i class="ti ti-checklist"></i> Inspect
                        </button>
                    `;
                } else if (record.status === 'in_progress' && record.inspection_passed === true) {
                    // Show Complete button only if inspection passed
                    completeButton = `
                        <button onclick="completeMaintenanceTask(${record.id})" class="text-[10px] font-extrabold text-[#639922] bg-[#E8F4E0] hover:bg-[#d8edd0] px-2 py-1 rounded transition-all cursor-pointer">
                            <i class="ti ti-check"></i> Complete Service
                        </button>
                    `;
                }

                actionHtml = `
                    <div class="mt-3.5 flex items-center justify-between border-t border-slate-100 pt-2 gap-3 shrink-0">
                        <div>${inspectionStatusHtml}</div>
                        <div class="flex gap-2">
                            ${completeButton}
                            <button onclick="deleteMaintenanceRecord(${record.id})" class="text-[10px] font-extrabold text-[#E24B4A] hover:underline cursor-pointer">
                                <i class="ti ti-trash"></i> Cancel / Delete
                            </button>
                        </div>
                    </div>
                `;
            } else {
                actionHtml = `
                    <div class="mt-3.5 flex items-center justify-end border-t border-slate-100 pt-2 gap-3 shrink-0">
                        <button onclick="deleteMaintenanceRecord(${record.id})" class="text-[10px] font-semibold text-slate-400 hover:text-slate-600 cursor-pointer">
                            <i class="ti ti-trash"></i> Delete Log
                        </button>
                    </div>
                `;
            }

            itemDiv.innerHTML = `
                <!-- Bullet marker -->
                <span class="absolute left-[-5px] top-1.5 h-2.5 w-2.5 rounded-full ${bulletColorClass} border border-white"></span>
                <div class="rounded-lg border border-[#E0E0E0] bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] hover:border-slate-300 transition-all duration-200">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-[#003F87]">${busLabel}</span>
                        <div class="flex gap-2">
                            <span class="inline-flex rounded-full ${badgeBgClass} ${badgeTextClass} px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider">${record.type}</span>
                            <span class="inline-flex rounded-full ${statusBadgeClass} px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider">${record.status}</span>
                        </div>
                    </div>
                    <div class="mt-2.5 grid grid-cols-2 gap-y-2 text-[11px] text-slate-500 font-semibold">
                        <div>Date: <span class="text-slate-800 font-bold">${formattedDate}</span></div>
                        <div>Technician: <span class="text-slate-800 font-bold">${parsedDesc.technician}</span></div>
                        <div class="col-span-2">Tasks / Details: <span class="text-slate-700 font-medium block mt-0.5 p-1.5 rounded bg-slate-50 border border-slate-100">${parsedDesc.notes}</span></div>
                    </div>
                    ${actionHtml}
                </div>
            `;

            container.appendChild(itemDiv);
        });
    } catch (error) {
        console.error('Failed to load maintenance records:', error);
        container.innerHTML = `
            <div class="py-12 text-center text-rose-500 font-semibold text-xs border border-dashed border-rose-200 rounded-xl bg-rose-50/50">
                <i class="ti ti-alert-triangle text-2xl block mb-2 animate-bounce"></i>
                Error loading maintenance logs from system server.
            </div>
        `;
    }
}

// Complete Maintenance Task
async function completeMaintenanceTask(id) {
    if (!confirm('Are you sure you want to mark this maintenance task as COMPLETED?\nThis will restore the bus operational status back to ACTIVE.')) {
        return;
    }

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
        
        const response = await fetch(`${baseUrl}/${id}/complete`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert(data.message);
            // Refresh local logs and global fleet state
            await fetchMaintenanceLogs();
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
        } else {
            alert(data.message || 'Failed to update maintenance task status.');
        }
    } catch (error) {
        alert('Server connection error. Failed to complete maintenance task.');
        console.error('AJAX complete error:', error);
    }
}

// Delete Maintenance Record
async function deleteMaintenanceRecord(id) {
    if (!confirm('Are you sure you want to delete this maintenance record?\nIf the task is not completed, this will unlock the bus back to active.')) {
        return;
    }

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
        
        const response = await fetch(`${baseUrl}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        })

        const data = await response.json();

        if (response.ok && data.success) {
            alert(data.message);
            // Refresh local logs and global fleet state
            await fetchMaintenanceLogs();
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
        } else {
            alert(data.message || 'Failed to delete maintenance record.');
        }
    } catch (error) {
        alert('Server connection error. Failed to delete maintenance record.');
        console.error('AJAX delete error:', error);
    }
}

// Auto-run on document loading and view switcher bindings
document.addEventListener('DOMContentLoaded', () => {
    // Initial fetch of logs on dashboard load
    fetchMaintenanceLogs();

    // Intercept navigation switcher triggers to reload maintenance logs whenever switching to maintenance screen
    const originalSwitchScreen = window.switchScreen;
    if (typeof originalSwitchScreen === 'function') {
        window.switchScreen = function(screenId) {
            originalSwitchScreen(screenId);
            if (screenId === 'maintenance') {
                fetchMaintenanceLogs();
            }
        };
    }
});
