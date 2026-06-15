<section id="screen-settings" class="space-y-6 hidden animate-fade-in">
    <!-- Settings Alert Box -->
    <div id="settings-alert-success" class="hidden p-4 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-xl text-xs font-semibold flex items-center justify-between shadow-sm animate-fade-in-up">
        <div class="flex items-center gap-2">
            <i class="ti ti-circle-check text-base"></i>
            <span></span>
        </div>
        <button onclick="document.getElementById('settings-alert-success').classList.add('hidden')" class="text-[#3B6D11] hover:opacity-80"><i class="ti ti-x"></i></button>
    </div>
    
    <div id="settings-alert-error" class="hidden p-4 bg-[#FCEBEB] border border-[#A32D2D] text-[#A32D2D] rounded-xl text-xs font-semibold flex items-center justify-between shadow-sm animate-fade-in-up">
        <div class="flex items-center gap-2">
            <i class="ti ti-alert-triangle text-base"></i>
            <span></span>
        </div>
        <button onclick="document.getElementById('settings-alert-error').classList.add('hidden')" class="text-[#A32D2D] hover:opacity-80"><i class="ti ti-x"></i></button>
    </div>

    <!-- Header Section -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-slate-900">System & Simulation Configuration</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span class="text-slate-600 font-bold">System Settings</span>
                </div>
                <p class="text-[11px] text-slate-505 font-semibold mt-1">Manage global constants, thresholds, and simulation parameters</p>
            </div>
        </div>
    </div>

    <!-- Tabs Container -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="border-b border-slate-100 bg-slate-50/50 px-5 py-3 flex items-center gap-4 shrink-0">
            <button onclick="switchSettingsTab('system')" id="tab-btn-system" class="px-4 py-2 text-xs font-extrabold uppercase tracking-wider transition bg-[#003F87] text-white rounded-lg shadow-sm">
                General System Settings
            </button>
            <button onclick="switchSettingsTab('simulation')" id="tab-btn-simulation" class="px-4 py-2 text-xs font-extrabold uppercase tracking-wider transition text-slate-500 hover:text-slate-800 rounded-lg">
                Simulation Defaults
            </button>
        </div>

        <div class="p-6">
            <!-- General System Settings Panel -->
            <div id="panel-system" class="space-y-4">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10px] font-extrabold uppercase tracking-widest text-slate-400">
                                <th class="pb-3 font-bold w-1/4">Key</th>
                                <th class="pb-3 font-bold w-5/12">Description</th>
                                <th class="pb-3 font-bold w-1/4">Value</th>
                                <th class="pb-3 font-bold text-right w-1/12">Action</th>
                            </tr>
                        </thead>
                        <tbody id="system-settings-tbody" class="text-xs font-semibold text-slate-700 divide-y divide-slate-100/50">
                            <tr>
                                <td colspan="4" class="py-12 text-center text-slate-400 font-semibold">Loading system settings...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Simulation Defaults Panel -->
            <div id="panel-simulation" class="space-y-4 hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10px] font-extrabold uppercase tracking-widest text-slate-400">
                                <th class="pb-3 font-bold w-1/4">Key</th>
                                <th class="pb-3 font-bold w-5/12">Description</th>
                                <th class="pb-3 font-bold w-1/4">Value</th>
                                <th class="pb-3 font-bold text-right w-1/12">Action</th>
                            </tr>
                        </thead>
                        <tbody id="simulation-settings-tbody" class="text-xs font-semibold text-slate-700 divide-y divide-slate-100/50">
                            <tr>
                                <td colspan="4" class="py-12 text-center text-slate-400 font-semibold">Loading simulation settings...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
