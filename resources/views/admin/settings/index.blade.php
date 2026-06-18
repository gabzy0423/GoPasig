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
            <div>
                <button onclick="openAddSettingModal()" class="rounded-lg bg-[#003F87] hover:bg-[#002D62] text-white px-4 py-2 text-xs font-extrabold uppercase tracking-wider transition cursor-pointer flex items-center gap-1.5 shadow-sm border-none">
                    <i class="ti ti-plus text-sm"></i> Add New Setting
                </button>
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

    <!-- Add New Setting Modal -->
    <div id="add-setting-modal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/40 backdrop-blur-sm animate-fade-in">
        <div class="bg-white rounded-2xl border border-slate-200 shadow-xl p-6 w-full max-w-md mx-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
                    <i class="ti ti-plus text-base text-[#003F87]"></i> Add Setting
                </h3>
                <button onclick="closeAddSettingModal()" class="text-slate-400 hover:text-slate-600 transition border-none bg-transparent cursor-pointer">
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>
            
            <form id="add-setting-form" onsubmit="handleAddSettingSubmit(event)" class="space-y-4">
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase tracking-wider text-slate-505">Setting Type</label>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 cursor-pointer">
                            <input type="radio" name="new_setting_type" value="system" checked class="text-[#003F87] focus:ring-[#003F87]"> General System Setting
                        </label>
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 cursor-pointer">
                            <input type="radio" name="new_setting_type" value="simulation" class="text-[#003F87] focus:ring-[#003F87]"> Simulation Default
                        </label>
                    </div>
                </div>

                <div class="space-y-1">
                    <label for="new_setting_key" class="text-[10px] font-bold uppercase tracking-wider text-slate-505 flex flex-col">Setting Key</label>
                    <input type="text" id="new_setting_key" required placeholder="e.g. driver_initial_performance_score"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white font-mono">
                </div>

                <div class="space-y-1">
                    <label for="new_setting_value" class="text-[10px] font-bold uppercase tracking-wider text-slate-555 flex flex-col">Setting Value</label>
                    <input type="text" id="new_setting_value" required placeholder="e.g. 80"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white font-mono">
                </div>

                <div class="space-y-1">
                    <label for="new_setting_description" class="text-[10px] font-bold uppercase tracking-wider text-slate-505 flex flex-col">Description</label>
                    <textarea id="new_setting_description" rows="2" placeholder="Explain what this configuration controls..."
                              class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white"></textarea>
                </div>

                <div class="pt-4 flex items-center justify-end gap-2 border-t border-slate-100">
                    <button type="button" onclick="closeAddSettingModal()" class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 text-xs font-bold transition border-none cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-lg bg-[#003F87] hover:bg-[#002D62] text-white px-5 py-2 text-xs font-extrabold uppercase tracking-wider transition border-none cursor-pointer">
                        Add Setting
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
