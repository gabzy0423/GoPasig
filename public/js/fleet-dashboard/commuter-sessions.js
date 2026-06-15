/**
 * GoPasig Fleet Ops - Commuter Sessions Javascript Controller
 * Handles dynamic fetching, search, expiration states, and pagination.
 */

let sessionsCurrentPage = 1;

async function fetchCommuterSessions(page = 1) {
    sessionsCurrentPage = page;
    const search = document.getElementById('sessions-search-input')?.value || '';

    try {
        const queryParams = new URLSearchParams({
            page: page,
            search: search
        });

        const response = await fetch(`/fleet/api/commuter-sessions?${queryParams.toString()}`);
        if (!response.ok) throw new Error('Failed to fetch commuter sessions');
        const data = await response.json();

        renderSessionsTableDOM(data.data);
        renderSessionsPaginationDOM(data);
        
        // Update count badge
        const badge = document.getElementById('sessions-total-badge');
        if (badge) {
            badge.innerText = `${data.total} entries`;
        }
    } catch (error) {
        console.error('Error loading commuter sessions data:', error);
    }
}

function renderSessionsTableDOM(sessions) {
    const tbody = document.getElementById('sessions-table-body');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (!sessions || sessions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="5" class="py-12 text-center bg-slate-50/50">
                    <i class="ti ti-key-off text-[36px] text-slate-300"></i>
                    <p class="text-xs font-bold text-slate-500 mt-2">No active sessions found</p>
                </td>
            </tr>
        `;
        return;
    }

    sessions.forEach(session => {
        const isActive = session.is_active;
        const statusBadge = isActive 
            ? 'bg-[#EAF3DE] text-[#3B6D11] border-[#EAF3DE]' 
            : 'bg-slate-100 text-slate-400 border-slate-200';
        const statusText = isActive ? 'Active' : 'Expired';

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors';
        tr.innerHTML = `
            <td class="py-3 px-3">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-xs text-slate-600 truncate max-w-[280px]" title="${session.session_token}">${session.session_token}</span>
                    <button onclick="copyToClipboard('${session.session_token}')" class="text-slate-400 hover:text-[#003F87] transition cursor-pointer p-0.5" title="Copy Token">
                        <i class="ti ti-copy text-[14px]"></i>
                    </button>
                </div>
            </td>
            <td class="py-3 px-3 text-slate-700 font-mono">${session.ip_address || '—'}</td>
            <td class="py-3 px-3 text-[12px] text-slate-500 font-mono">${formatTimestamp(session.created_at)}</td>
            <td class="py-3 px-3 text-[12px] text-slate-500 font-mono">${formatTimestamp(session.expires_at)}</td>
            <td class="py-3 px-3">
                <span class="inline-flex rounded px-2 py-0.5 text-[11px] font-semibold border ${statusBadge}">${statusText}</span>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function renderSessionsPaginationDOM(meta) {
    const container = document.getElementById('sessions-pagination');
    if (!container) return;

    if (meta.last_page <= 1) {
        container.innerHTML = '';
        return;
    }

    let linksHtml = `<div class="flex items-center gap-1.5">`;
    if (meta.current_page > 1) {
        linksHtml += `<button onclick="fetchCommuterSessions(${meta.current_page - 1})" class="px-3 py-1.5 rounded border border-black/10 bg-white hover:bg-slate-50 cursor-pointer font-medium">Prev</button>`;
    }
    
    for (let i = 1; i <= meta.last_page; i++) {
        const isCurrent = i === meta.current_page;
        const btnClass = isCurrent 
            ? 'bg-[#003F87] text-white border-[#003F87]' 
            : 'bg-white text-slate-600 hover:bg-slate-50 border-black/10';
        linksHtml += `<button onclick="fetchCommuterSessions(${i})" class="px-3 py-1.5 rounded border font-semibold cursor-pointer ${btnClass}">${i}</button>`;
    }

    if (meta.current_page < meta.last_page) {
        linksHtml += `<button onclick="fetchCommuterSessions(${meta.current_page + 1})" class="px-3 py-1.5 rounded border border-black/10 bg-white hover:bg-slate-50 cursor-pointer font-medium">Next</button>`;
    }
    linksHtml += `</div>`;

    container.innerHTML = `
        <div>Showing ${meta.from || 0} to ${meta.to || 0} of ${meta.total} entries</div>
        ${linksHtml}
    `;
}

function resetSessionsFiltersAction() {
    const search = document.getElementById('sessions-search-input');
    if (search) search.value = '';
    fetchCommuterSessions(1);
}

// Setup polling and input events
document.addEventListener('DOMContentLoaded', () => {
    const search = document.getElementById('sessions-search-input');

    let debounceTimeout = null;
    search?.addEventListener('input', () => {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(() => fetchCommuterSessions(1), 350);
    });

    // Register active polling loop check
    setInterval(() => {
        const sessionsScreen = document.getElementById('screen-commuter-sessions');
        if (sessionsScreen && !sessionsScreen.classList.contains('hidden')) {
            fetchCommuterSessions(sessionsCurrentPage);
        }
    }, 15000); // Poll every 15s when tab active
});
