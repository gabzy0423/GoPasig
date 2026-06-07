/**
 * GoPasig Fleet Ops - Announcements Management Javascript Controller
 * Handles Vanilla JS / AJAX paging, search with debouncing, status/audience filtering, and modals.
 */

// Window Configuration Setup
window.FleetAnnouncementsConfig = {
    dataUrl: '/fleet/api/announcements-data',
    detailsUrl: '/fleet/api/announcements-details',
    storeUrl: '/fleet/api/announcements-store',
    deleteUrl: '/fleet/api/announcements-delete',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

// Global Paging / State
let currentPage = 1;
let currentSearch = '';
let filterPriority = 'all';
let filterAudience = 'all';
let filterStatus = 'all';
let sortOrder = 'newest';

let isEditing = false;
let editingId = null;

// Debouncer helper
let searchTimeout = null;

async function fetchAnnouncementsData(page = 1) {
    currentPage = page;
    const searchVal = document.getElementById('search-input')?.value || '';
    const priorityVal = document.getElementById('filter-priority')?.value || 'all';
    const audienceVal = document.getElementById('filter-audience')?.value || 'all';
    const statusVal = document.getElementById('filter-status')?.value || 'all';
    const sortVal = document.querySelector('[data-sort-order]')?.getAttribute('data-sort-order') || 'newest';

    try {
        const queryParams = new URLSearchParams({
            page: page,
            search: searchVal,
            priority: priorityVal,
            audience: audienceVal,
            status: statusVal,
            sort: sortVal
        });

        const response = await fetch(`${window.FleetAnnouncementsConfig.dataUrl}?${queryParams.toString()}`);
        if (!response.ok) throw new Error('Failed to fetch announcements list');
        const data = await response.json();

        updateStatsDOM(data.announcementStats);
        updateTableDOM(data.announcements.data);
        updatePaginationDOM(data.announcements);

        document.getElementById('announcements-total-badge').innerText = `${data.announcements.total} entries`;
    } catch (error) {
        console.error('Error fetching announcements:', error);
    }
}

function updateStatsDOM(stats) {
    if (!stats) return;
    document.getElementById('stat-active').innerHTML = `<i class="ti ti-circle-check text-[14px]"></i> Active (${stats.active})`;
    document.getElementById('stat-scheduled').innerHTML = `<i class="ti ti-clock text-[14px]"></i> Scheduled (${stats.scheduled})`;
    document.getElementById('stat-expired').innerHTML = `<i class="ti ti-clock-off text-[14px]"></i> Expired (${stats.expired})`;
    document.getElementById('stat-[#A32D2D]').innerHTML = `<i class="ti ti-alert-triangle text-[14px]"></i> High Priority (${stats.high_priority})`;
}

function updateTableDOM(data) {
    const tbody = document.getElementById('announcements-table-body');
    if (!tbody) return;

    tbody.innerHTML = '';
    if (data.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="py-12 text-center bg-slate-50/50">
                    <i class="ti ti-speakerphone text-[48px] text-slate-300"></i>
                    <p class="text-[16px] font-bold text-slate-500 mt-2">No announcements found</p>
                    <p class="text-[13px] text-slate-400 mt-1">Try adjusting your filters or create a new announcement</p>
                </td>
            </tr>
        `;
        return;
    }

    const priorityClasses = {
        'High': 'bg-[#FCEBEB] text-[#A32D2D] border-[#FCEBEB]',
        'Medium': 'bg-[#E6F1FB] text-[#185FA5] border-[#E6F1FB]',
        'Low': 'bg-slate-100 text-slate-600 border-slate-100',
    };
    const statusClasses = {
        'Active': 'bg-[#EAF3DE] text-[#3B6D11] border-[#3B6D11]/10',
        'Scheduled': 'bg-[#E6F1FB] text-[#185FA5] border-[#185FA5]/10',
        'Expired': 'bg-[#F1EFE8] text-[#5F5E5A] border-[#5F5E5A]/10',
        'Draft': 'bg-slate-100 text-slate-500 border-slate-200',
    };

    data.forEach(item => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors';
        tr.setAttribute('wire:key', `announcement-row-${item.id}`);

        const priorityClass = priorityClasses[item.priority] || 'bg-slate-100 text-slate-600';
        const statusClass = statusClasses[item.status] || 'bg-slate-100 text-slate-600';
        const affectedRoute = item.affected_route || 'All Routes';
        const formattedDate = formatDateTime(item.created_at);

        tr.innerHTML = `
            <td class="py-3 px-3">
                <span class="inline-flex rounded px-2 py-0.5 text-[11px] font-semibold border ${priorityClass}">${item.priority}</span>
            </td>
            <td class="py-3 px-3 font-semibold text-[#001F44] truncate" title="${item.headline}">${item.headline}</td>
            <td class="py-3 px-3 text-slate-700">${item.audience}</td>
            <td class="py-3 px-3 text-slate-600">${affectedRoute}</td>
            <td class="py-3 px-3 text-[12px] text-slate-500 font-medium">${item.posted_by}</td>
            <td class="py-3 px-3 text-[12px] text-slate-500 font-mono">${formattedDate}</td>
            <td class="py-3 px-3">
                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold border ${statusClass}">${item.status}</span>
            </td>
            <td class="py-3 px-3 text-right">
                <div class="inline-flex items-center gap-1 text-slate-500">
                    <button onclick="viewAnnouncementAction(${item.id})" class="rounded p-1 hover:bg-slate-100 text-slate-600 transition cursor-pointer" title="View Details">
                        <i class="ti ti-eye text-[16px]"></i>
                    </button>
                    <button onclick="openEditModalAction(${item.id})" class="rounded p-1 hover:bg-slate-100 text-[#003F87] transition cursor-pointer" title="Edit">
                        <i class="ti ti-pencil text-[16px]"></i>
                    </button>
                    <button onclick="deleteAnnouncementAction(${item.id})" class="rounded p-1 hover:bg-slate-100 text-[#A32D2D] transition cursor-pointer" title="Delete">
                        <i class="ti ti-trash text-[16px]"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function updatePaginationDOM(paginator) {
    const container = document.getElementById('announcements-pagination-links');
    if (!container) return;
    container.innerHTML = '';

    if (paginator.last_page <= 1) return; // No pagination needed

    const nav = document.createElement('nav');
    nav.className = 'flex items-center justify-between border-t border-slate-100 px-4 py-3 sm:px-6';
    
    let linksHtml = `<div class="flex flex-1 justify-between sm:hidden">`;
    if (paginator.prev_page_url) {
        linksHtml += `<button onclick="goToAnnouncementsPage(${paginator.current_page - 1})" class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Previous</button>`;
    }
    if (paginator.next_page_url) {
        linksHtml += `<button onclick="goToAnnouncementsPage(${paginator.current_page + 1})" class="relative ml-3 inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Next</button>`;
    }
    linksHtml += `</div><div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">`;
    linksHtml += `<div><p class="text-sm text-slate-700">Showing <span class="font-medium">${paginator.from}</span> to <span class="font-medium">${paginator.to}</span> of <span class="font-medium">${paginator.total}</span> results</p></div>`;
    linksHtml += `<div><span class="isolate inline-flex rounded-md shadow-sm">`;

    paginator.links.forEach(link => {
        let label = link.label;
        if (label.includes('Previous')) {
            label = '<i class="ti ti-chevron-left text-[15px]"></i>';
        } else if (label.includes('Next')) {
            label = '<i class="ti ti-chevron-right text-[15px]"></i>';
        }

        let btnClass = '';
        if (link.active) {
            btnClass = 'relative z-10 inline-flex items-center bg-[#003F87] px-3.5 py-2 text-sm font-semibold text-white focus:z-20';
        } else if (!link.url) {
            btnClass = 'relative inline-flex items-center px-3.5 py-2 text-sm font-semibold text-slate-300 pointer-events-none';
        } else {
            btnClass = 'relative inline-flex items-center bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus:z-20';
        }

        if (link.url) {
            const pageNum = new URL(link.url).searchParams.get('page');
            linksHtml += `<button onclick="goToAnnouncementsPage(${pageNum})" class="${btnClass}">${label}</button>`;
        } else {
            linksHtml += `<span class="${btnClass}">${label}</span>`;
        }
    });

    linksHtml += `</span></div></div>`;
    nav.innerHTML = linksHtml;
    container.appendChild(nav);
}

function goToAnnouncementsPage(page) {
    fetchAnnouncementsData(page);
}

function formatDateTime(isoString) {
    const d = new Date(isoString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const month = months[d.getMonth()];
    const day = String(d.getDate()).padStart(2, '0');
    const year = d.getFullYear();
    
    let hours = d.getHours();
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const hourStr = String(hours).padStart(2, '0');

    return `${month} ${day}, ${year} ${hourStr}:${minutes} ${ampm}`;
}

// Modal Controllers
function openCreateModalAction() {
    isEditing = false;
    editingId = null;
    clearFormErrors();

    document.getElementById('announcement-modal-title').innerText = 'New Announcement';
    document.getElementById('headline').value = '';
    document.getElementById('body').value = '';
    document.getElementById('priority').value = 'Medium';
    document.getElementById('audience').value = 'Commuters';
    document.getElementById('affected_route').value = 'All Routes';
    document.getElementById('is_scheduled').checked = false;
    document.getElementById('scheduled_at').value = '';
    document.getElementById('expires_at').value = '';
    
    document.getElementById('schedule-time-container').classList.add('hidden');
    document.getElementById('announcement-modal').classList.remove('hidden');
}

async function openEditModalAction(id) {
    isEditing = true;
    editingId = id;
    clearFormErrors();

    try {
        const response = await fetch(`${window.FleetAnnouncementsConfig.detailsUrl}/${id}`);
        if (!response.ok) throw new Error('Details fetch issue');
        const data = await response.json();

        if (data.success) {
            const a = data.announcement;
            document.getElementById('announcement-modal-title').innerText = 'Edit Announcement';
            document.getElementById('headline').value = a.headline;
            document.getElementById('body').value = a.body;
            document.getElementById('priority').value = a.priority;
            document.getElementById('audience').value = a.audience;
            document.getElementById('affected_route').value = a.affected_route;
            document.getElementById('is_scheduled').checked = a.is_scheduled;
            document.getElementById('scheduled_at').value = a.scheduled_at;
            document.getElementById('expires_at').value = a.expires_at;

            const timeContainer = document.getElementById('schedule-time-container');
            if (a.is_scheduled) {
                timeContainer.classList.remove('hidden');
            } else {
                timeContainer.classList.add('hidden');
            }

            document.getElementById('announcement-modal').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading announcement details:', error);
    }
}

async function viewAnnouncementAction(id) {
    try {
        const response = await fetch(`${window.FleetAnnouncementsConfig.detailsUrl}/${id}`);
        if (!response.ok) throw new Error('View fetch issue');
        const data = await response.json();

        if (data.success) {
            const a = data.announcement;
            document.getElementById('view-priority').innerText = `${a.priority} Priority`;
            
            const priorityClasses = {
                'High': 'bg-[#FCEBEB] text-[#A32D2D] border-[#FCEBEB]',
                'Medium': 'bg-[#E6F1FB] text-[#185FA5] border-[#E6F1FB]',
                'Low': 'bg-slate-100 text-slate-600 border-slate-100',
            };
            document.getElementById('view-priority').className = `inline-flex rounded px-2.5 py-0.5 text-[11px] font-bold border ${priorityClasses[a.priority] || 'bg-slate-100'}`;

            document.getElementById('view-headline').innerText = a.headline;
            document.getElementById('view-body').innerText = a.body;
            document.getElementById('view-audience').innerText = a.audience;
            document.getElementById('view-affected-route').innerText = a.affected_route || 'All Routes';
            document.getElementById('view-posted-by').innerText = a.posted_by;
            document.getElementById('view-created-at').innerText = a.created_at;

            const expBlock = document.getElementById('view-expires-block');
            if (a.expires_at_formatted) {
                document.getElementById('view-expires-at').innerText = a.expires_at_formatted;
                expBlock.classList.remove('hidden');
            } else {
                expBlock.classList.add('hidden');
            }

            const schedBlock = document.getElementById('view-scheduled-block');
            if (a.is_scheduled && a.scheduled_at_formatted) {
                document.getElementById('view-scheduled-at').innerText = a.scheduled_at_formatted;
                schedBlock.classList.remove('hidden');
            } else {
                schedBlock.classList.add('hidden');
            }

            const statusClasses = {
                'Active': 'bg-[#EAF3DE] text-[#3B6D11] border-[#3B6D11]/10',
                'Scheduled': 'bg-[#E6F1FB] text-[#185FA5] border-[#185FA5]/10',
                'Expired': 'bg-[#F1EFE8] text-[#5F5E5A] border-[#5F5E5A]/10',
                'Draft': 'bg-slate-100 text-slate-500 border-slate-200',
            };
            const statusEl = document.getElementById('view-status');
            statusEl.innerText = a.status;
            statusEl.className = `inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold border ${statusClasses[a.status] || 'bg-slate-100'}`;

            // Footer edit button click binding
            document.getElementById('view-modal-btn-edit').onclick = () => {
                closeModalAction();
                openEditModalAction(a.id);
            };

            document.getElementById('view-announcement-modal').classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error viewing announcement details:', error);
    }
}

function closeModalAction() {
    document.getElementById('announcement-modal').classList.add('hidden');
    document.getElementById('view-announcement-modal').classList.add('hidden');
    isEditing = false;
    editingId = null;
    clearFormErrors();
}

// Submit store/update
async function submitAnnouncementForm(event, isDraft = false) {
    if (event) event.preventDefault();
    clearFormErrors();

    const headline = document.getElementById('headline').value.trim();
    const body = document.getElementById('body').value.trim();
    const priority = document.getElementById('priority').value;
    const audience = document.getElementById('audience').value;
    const affectedRoute = document.getElementById('affected_route').value;
    const isScheduled = document.getElementById('is_scheduled').checked;
    const scheduledAt = document.getElementById('scheduled_at').value;
    const expiresAt = document.getElementById('expires_at').value;

    let hasErrors = false;
    if (headline.length < 3) {
        showFieldError('headline', 'Headline is required (min 3 characters).');
        hasErrors = true;
    }
    if (body.length < 5) {
        showFieldError('body', 'Message body is required (min 5 characters).');
        hasErrors = true;
    }
    if (isScheduled && !scheduledAt) {
        showFieldError('scheduled_at', 'Scheduled time is required when scheduling is enabled.');
        hasErrors = true;
    }

    if (hasErrors) return;

    try {
        const payload = {
            id: editingId,
            headline: headline,
            body: body,
            priority: priority,
            audience: audience,
            affected_route: affectedRoute,
            is_scheduled: isScheduled ? 1 : 0,
            scheduled_at: isScheduled ? scheduledAt : null,
            expires_at: expiresAt || null,
            is_draft: isDraft ? 1 : 0
        };

        const response = await fetch(window.FleetAnnouncementsConfig.storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetAnnouncementsConfig.csrfToken
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        if (response.ok && data.success) {
            showAnnouncementsAlert(data.message);
            closeModalAction();
            fetchAnnouncementsData(currentPage);
        } else {
            showAnnouncementsAlert(data.message || 'Error saving announcement.', true);
        }
    } catch (error) {
        console.error('Error saving announcement:', error);
        showAnnouncementsAlert('Failed to save announcement.', true);
    }
}

// Delete Action
async function deleteAnnouncementAction(id) {
    if (!confirm('Are you sure you want to delete this announcement?')) return;

    try {
        const response = await fetch(`${window.FleetAnnouncementsConfig.deleteUrl}/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetAnnouncementsConfig.csrfToken
            }
        });
        const data = await response.json();
        if (response.ok && data.success) {
            showAnnouncementsAlert(data.message);
            fetchAnnouncementsData(currentPage);
        } else {
            showAnnouncementsAlert(data.message || 'Error deleting announcement.', true);
        }
    } catch (error) {
        console.error('Error deleting announcement:', error);
        showAnnouncementsAlert('Failed to delete announcement.', true);
    }
}

// Clear Filters Action
function resetFiltersAction() {
    document.getElementById('search-input').value = '';
    document.getElementById('filter-priority').selectedIndex = 0;
    document.getElementById('filter-audience').selectedIndex = 0;
    document.getElementById('filter-status').selectedIndex = 0;
    fetchAnnouncementsData(1);
}

// Alert Notification
function showAnnouncementsAlert(message, isError = false) {
    const alertBox = document.getElementById('announcements-alert');
    const alertMsg = document.getElementById('announcements-alert-message');
    if (alertBox && alertMsg) {
        alertMsg.innerText = message;
        if (isError) {
            alertBox.className = 'p-3 bg-red-100 border border-red-500 text-red-700 rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up';
            alertBox.querySelector('i').className = 'ti ti-circle-x text-[16px]';
        } else {
            alertBox.className = 'p-3 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up';
            alertBox.querySelector('i').className = 'ti ti-circle-check text-[16px]';
        }
        alertBox.classList.remove('hidden');
        setTimeout(() => alertBox.classList.add('hidden'), 5000);
    }
}

// Form Validation UI helper
function showFieldError(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('border-red-500', 'bg-red-50');
    
    const err = document.createElement('span');
    err.className = 'text-xs text-red-500 font-medium block mt-1 form-error';
    err.innerText = msg;
    el.parentNode.appendChild(err);
}

function clearFormErrors() {
    document.querySelectorAll('.form-error').forEach(e => e.remove());
    document.querySelectorAll('.border-red-500').forEach(e => e.classList.remove('border-red-500', 'bg-red-50'));
}

// Document ready entry
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('announcements-table-body')) {
        // Event Listeners for Filters
        const filters = ['filter-priority', 'filter-audience', 'filter-status'];
        filters.forEach(id => {
            document.getElementById(id)?.addEventListener('change', () => fetchAnnouncementsData(1));
        });

        // Search Input Keyup Debouncing
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    fetchAnnouncementsData(1);
                }, 400);
            });
        }

        // Sorting buttons
        document.querySelectorAll('.inline-flex.rounded-full.border.border-black\\/10 button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                btn.parentNode.querySelectorAll('button').forEach(b => {
                    b.className = 'rounded-full px-3 py-1 font-medium transition cursor-pointer text-slate-600 hover:text-slate-900';
                });
                btn.className = 'rounded-full px-3 py-1 font-medium transition cursor-pointer bg-white text-[#003F87] shadow-xs';
                
                const container = btn.parentNode;
                const sortActive = e.target.innerText.toLowerCase().includes('oldest') ? 'oldest' : 'newest';
                container.setAttribute('data-sort-order', sortActive);
                
                fetchAnnouncementsData(1);
            });
        });

        // Schedule Toggle Checkbox
        document.getElementById('is_scheduled')?.addEventListener('change', (e) => {
            const timeContainer = document.getElementById('schedule-time-container');
            if (e.target.checked) {
                timeContainer.classList.remove('hidden');
            } else {
                timeContainer.classList.add('hidden');
                document.getElementById('scheduled_at').value = '';
            }
        });

        // Form Submission Buttons
        document.getElementById('announcement-creation-form')?.addEventListener('submit', (e) => e.preventDefault());
        document.getElementById('btn-draft-save')?.addEventListener('click', (e) => submitAnnouncementForm(e, true));
        document.getElementById('btn-submit-save')?.addEventListener('click', (e) => submitAnnouncementForm(e, false));

        // Load initial data if injected on load
        if (window.GoPasigAnnouncementsInitialData) {
            updateStatsDOM(window.GoPasigAnnouncementsInitialData.announcementStats);
            updatePaginationDOM(window.GoPasigAnnouncementsInitialData.announcements);
        }

        // Poll for updates every 30 seconds
        setInterval(() => fetchAnnouncementsData(currentPage), 30000);
    }
});
