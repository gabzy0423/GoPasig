<section id="screen-drivers-edit" class="hidden space-y-6"
         style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-tertiary:#E8E6DF;--color-border-secondary:#D6D3C9;">

    <!-- BREADCRUMB & HEADER -->
    <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
        <div class="flex items-center gap-4">
            <button onclick="switchScreen('drivers'); return false;" 
               class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 border-none" 
               title="Back to Driver Management">
                <i class="ti ti-arrow-left text-lg"></i>
            </button>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Edit Driver Details</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Fleet</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Driver Management</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span class="text-[#003F87] font-bold">Edit Driver</span>
                </div>
            </div>
        </div>
    </div>

    <!-- FORM CARD -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Edit Driver Profile</h2>
                <p class="text-xs text-slate-500">Modify the personal details, employee identification, and license details of the driver.</p>
            </div>
            <span id="df-edit-status-badge" class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 border border-slate-200">
                Off Duty
            </span>
        </div>

        <form id="edit-driver-form" onsubmit="handleDriverEditSubmit(event)" class="space-y-6" novalidate>
            @csrf
            <input type="hidden" id="df-edit-driver-id" value="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Name -->
                <div class="space-y-2">
                    <label for="df-edit-firstname" class="text-xs font-bold uppercase tracking-wider text-slate-500">First Name</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-user text-base"></i>
                        </span>
                        <input id="df-edit-firstname" name="first_name" type="text" placeholder="e.g. Juan" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <span class="text-[10px] text-rose-600 font-semibold hidden" id="df-edit-firstname-err">First name must be at least 2 characters.</span>
                </div>

                <!-- Last Name -->
                <div class="space-y-2">
                    <label for="df-edit-lastname" class="text-xs font-bold uppercase tracking-wider text-slate-500">Last Name</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-user text-base"></i>
                        </span>
                        <input id="df-edit-lastname" name="last_name" type="text" placeholder="e.g. dela Cruz" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <span class="text-[10px] text-rose-600 font-semibold hidden" id="df-edit-lastname-err">Last name must be at least 2 characters.</span>
                </div>

                <!-- Employee ID -->
                <div class="space-y-2">
                    <label for="df-edit-empid" class="text-xs font-bold uppercase tracking-wider text-slate-500">Employee ID</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-id text-base"></i>
                        </span>
                        <input id="df-edit-empid" name="emp_id" type="text" readonly
                               class="w-full rounded-lg border border-slate-200 bg-slate-100 py-2.5 pl-10 pr-4 text-xs font-mono font-semibold text-slate-500 outline-none cursor-not-allowed">
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium">Employee identification code (read-only).</p>
                </div>

                <!-- Contact Number -->
                <div class="space-y-2">
                    <label for="df-edit-contact" class="text-xs font-bold uppercase tracking-wider text-slate-500">Contact Number</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-phone text-base"></i>
                        </span>
                        <input id="df-edit-contact" name="contact_number" type="text" placeholder="e.g. 09171234567" maxlength="11" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <span class="text-[10px] text-rose-600 font-semibold hidden" id="df-edit-contact-err">Must be exactly 11 digits starting with 09.</span>
                </div>

                <!-- License Number -->
                <div class="space-y-2">
                    <label for="df-edit-license" class="text-xs font-bold uppercase tracking-wider text-slate-500">License Number</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-license text-base"></i>
                        </span>
                        <input id="df-edit-license" name="license_number" type="text" placeholder="e.g. N01-23-456789" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <span class="text-[10px] text-rose-600 font-semibold hidden" id="df-edit-license-err">Required. Format: N##-##-###### (e.g., N01-23-456789).</span>
                </div>

                <!-- License Expiry Date -->
                <div class="space-y-2">
                    <label for="df-edit-expiry" class="text-xs font-bold uppercase tracking-wider text-slate-500">License Expiry Date</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-calendar text-base"></i>
                        </span>
                        <input id="df-edit-expiry" name="license_expiry" type="date" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <span class="text-[10px] text-rose-600 font-semibold hidden" id="df-edit-expiry-err">Expiry date is required.</span>
                    <div id="df-edit-expiry-warn" class="hidden flex items-center gap-2 p-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg mt-1 text-[11px] font-semibold">
                        <i class="ti ti-alert-triangle text-amber-600 text-sm"></i>
                        <span id="df-edit-expiry-warn-text">License expiring soon!</span>
                    </div>
                </div>

                <!-- Home Address -->
                <div class="space-y-2 md:col-span-2">
                    <label for="df-edit-address" class="text-xs font-bold uppercase tracking-wider text-slate-500">Home Address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-map-pin text-base"></i>
                        </span>
                        <input id="df-edit-address" name="address" type="text" placeholder="Street, Barangay, City" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                </div>

                <!-- Status -->
                <div class="space-y-2">
                    <label for="df-edit-status" class="text-xs font-bold uppercase tracking-wider text-slate-500">Status</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-activity text-base"></i>
                        </span>
                        <select id="df-edit-status" name="status" required
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                            <option value="active">Active employment</option>
                            <option value="inactive">Inactive (Off duty)</option>
                            <option value="suspended">Suspended (disciplinary hold)</option>
                        </select>
                        <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                            <i class="ti ti-chevron-down text-sm"></i>
                        </span>
                    </div>
                    <p id="df-edit-active-trip-guard" class="hidden text-[10px] font-semibold text-amber-700">
                        Employment status is locked until the dispatched or ongoing trip is ended or cancelled.
                    </p>
                </div>

                <!-- Emergency Contact -->
                <div class="space-y-2">
                    <label for="df-edit-emergency" class="text-xs font-bold uppercase tracking-wider text-slate-500">Emergency Contact</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-urgent text-base"></i>
                        </span>
                        <input id="df-edit-emergency" name="emergency_contact" type="text" placeholder="e.g. Maria dela Cruz - 09179998888"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium">Name and contact number in case of emergencies.</p>
                </div>
            </div>

            <!-- Info chip -->
            <div class="flex items-start gap-3 rounded-xl bg-blue-50 border border-blue-100 p-4 mt-6 text-xs text-[#0C447C] leading-relaxed">
                <i class="ti ti-info-circle text-base text-blue-600 shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-bold">Active Driver Operational Sync</p>
                    <p class="mt-0.5 text-blue-900/80">Updating details will instantly sync across dispatcher monitors and update active driver login tickets.</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="pt-6 flex items-center justify-between border-t border-slate-100 mt-8">
                <button type="button" onclick="handleEditDeleteDriver()" 
                        class="text-xs font-semibold text-rose-600 hover:text-rose-800 transition duration-200 cursor-pointer underline border-none bg-none bg-transparent">
                    Delete Driver Profile
                </button>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="switchScreen('drivers'); return false;" 
                       class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer border-none">
                        Cancel
                    </button>
                    <button type="submit" id="driver-edit-submit-btn" 
                            class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>
