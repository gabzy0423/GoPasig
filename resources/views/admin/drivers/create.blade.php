<section id="screen-drivers-create" class="hidden space-y-6"
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
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Register Municipal Driver</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Fleet</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Driver Management</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span class="text-[#003F87] font-bold">Register Driver</span>
                </div>
            </div>
        </div>
    </div>

    <!-- FORM CARD -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
        <div class="mb-6">
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Driver Profile & Credentials</h2>
            <p class="text-xs text-slate-500">Provide the personal details, employee identification, and license details to register a new driver ticket.</p>
        </div>

        <form id="create-driver-form" onsubmit="handleDriverCreateSubmit(event)" class="space-y-6" novalidate>
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Name -->
                <div class="space-y-2">
                    <label for="df-firstname" class="text-xs font-bold uppercase tracking-wider text-slate-500">First Name</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-user text-base"></i>
                        </span>
                        <input id="df-firstname" name="first_name" type="text" placeholder="e.g. Juan" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <span class="text-[10px] text-rose-600 font-semibold hidden" id="df-firstname-err">First name must be at least 2 characters.</span>
                </div>

                <!-- Last Name -->
                <div class="space-y-2">
                    <label for="df-lastname" class="text-xs font-bold uppercase tracking-wider text-slate-500">Last Name</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-user text-base"></i>
                        </span>
                        <input id="df-lastname" name="last_name" type="text" placeholder="e.g. dela Cruz" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <span class="text-[10px] text-rose-600 font-semibold hidden" id="df-lastname-err">Last name must be at least 2 characters.</span>
                </div>

                <!-- Employee ID -->
                <div class="space-y-2">
                    <label for="df-empid" class="text-xs font-bold uppercase tracking-wider text-slate-500">Employee ID</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-id text-base"></i>
                        </span>
                        <input id="df-empid" name="emp_id" type="text" readonly
                               class="w-full rounded-lg border border-slate-200 bg-slate-100 py-2.5 pl-10 pr-4 text-xs font-mono font-semibold text-slate-500 outline-none cursor-not-allowed">
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium">Auto-generated unique sequential code.</p>
                </div>

                <!-- Contact Number -->
                <div class="space-y-2">
                    <label for="df-contact" class="text-xs font-bold uppercase tracking-wider text-slate-500">Contact Number</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-phone text-base"></i>
                        </span>
                        <input id="df-contact" name="contact_number" type="text" placeholder="e.g. 09171234567" maxlength="11" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <span class="text-[10px] text-rose-600 font-semibold hidden" id="df-contact-err">Must be exactly 11 digits starting with 09.</span>
                </div>

                <!-- License Number -->
                <div class="space-y-2">
                    <label for="df-license" class="text-xs font-bold uppercase tracking-wider text-slate-500">License Number</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-license text-base"></i>
                        </span>
                        <input id="df-license" name="license_number" type="text" placeholder="e.g. N01-23-456789" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <span class="text-[10px] text-rose-600 font-semibold hidden" id="df-license-err">Required. Format: N##-##-###### (e.g., N01-23-456789).</span>
                </div>

                <!-- License Expiry Date -->
                <div class="space-y-2">
                    <label for="df-expiry" class="text-xs font-bold uppercase tracking-wider text-slate-500">License Expiry Date</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-calendar text-base"></i>
                        </span>
                        <input id="df-expiry" name="license_expiry" type="date" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <span class="text-[10px] text-rose-600 font-semibold hidden" id="df-expiry-err">Expiry date is required.</span>
                    <div id="df-expiry-warn" class="hidden flex items-center gap-2 p-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg mt-1 text-[11px] font-semibold">
                        <i class="ti ti-alert-triangle text-amber-600 text-sm"></i>
                        <span id="df-expiry-warn-text">License expiring soon!</span>
                    </div>
                </div>

                <!-- Home Address -->
                <div class="space-y-2 md:col-span-2">
                    <label for="df-address" class="text-xs font-bold uppercase tracking-wider text-slate-500">Home Address</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-map-pin text-base"></i>
                        </span>
                        <input id="df-address" name="address" type="text" placeholder="Street, Barangay, City" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                </div>

                <!-- Status -->
                <div class="space-y-2">
                    <label for="df-status" class="text-xs font-bold uppercase tracking-wider text-slate-500">Status</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-activity text-base"></i>
                        </span>
                        <select id="df-status" name="status" required
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                            <option value="active">Active (On Duty / Standby)</option>
                            <option value="inactive" selected>Inactive (Off duty)</option>
                            <option value="suspended">Suspended (disciplinary hold)</option>
                        </select>
                        <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                            <i class="ti ti-chevron-down text-sm"></i>
                        </span>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="space-y-2">
                    <label for="df-emergency" class="text-xs font-bold uppercase tracking-wider text-slate-500">Emergency Contact</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-urgent text-base"></i>
                        </span>
                        <input id="df-emergency" name="emergency_contact" type="text" placeholder="e.g. Maria dela Cruz - 09179998888"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium">Name and contact number in case of emergencies.</p>
                </div>
            </div>

            <!-- Info chip -->
            <div class="flex items-start gap-3 rounded-xl bg-blue-50 border border-blue-100 p-4 mt-6 text-xs text-[#0C447C] leading-relaxed">
                <i class="ti ti-info-circle text-base text-blue-600 shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-bold">Automated Credential Delivery</p>
                    <p class="mt-0.5 text-blue-900/80">The registered driver will receive an automated notification with login instructions once their driver ticket profile is successfully generated.</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                <button type="button" onclick="switchScreen('drivers'); return false;" 
                   class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer border-none">
                    Cancel
                </button>
                <button type="submit" id="driver-submit-btn" 
                        class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none">
                    Register Driver
                </button>
            </div>
        </form>
    </div>

</section>

<!-- INLINE SCRIPT: License Expiry Warning Logic with Dynamic Threshold -->
<script>
    // Inject license warning threshold from PHP controller
    const driverLicenseWarningDays = {{ $licenseWarningDays }};

    document.addEventListener('DOMContentLoaded', () => {
        const expiryInput = document.getElementById('df-expiry');
        const warnDiv = document.getElementById('df-expiry-warn');
        const warnText = document.getElementById('df-expiry-warn-text');

        // Listen for date change events
        expiryInput.addEventListener('change', () => {
            checkLicenseExpiry();
        });

        // Also check on page load if form is visible
        const checkLicenseExpiry = () => {
            const expiryValue = expiryInput.value;
            if (!expiryValue) {
                warnDiv.classList.add('hidden');
                return;
            }

            const selectedDate = new Date(expiryValue);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Calculate difference in days
            const timeDiff = selectedDate - today;
            const daysDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));

            // Show warning if expiring within threshold days or already expired
            if (daysDiff <= driverLicenseWarningDays && daysDiff > 0) {
                warnDiv.classList.remove('hidden');
                warnText.textContent = `License expiring in ${daysDiff} day(s)!`;
            } else if (daysDiff <= 0) {
                warnDiv.classList.remove('hidden');
                warnText.textContent = 'License has already expired!';
            } else {
                warnDiv.classList.add('hidden');
            }
        };
    });
</script>
