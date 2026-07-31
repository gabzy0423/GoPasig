@props([
    'prefix' => 'admin',
    'title' => 'Security & Password Management',
])

<div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mt-6">
    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="ti ti-lock text-lg text-[#003F87]"></i>
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-800">{{ $title }}</h2>
        </div>
    </div>

    <div class="p-6 space-y-6">
        <!-- Password Security Alerts -->
        <div id="{{ $prefix }}-profile-password-success" class="hidden p-4 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-xl text-xs font-semibold flex items-center justify-between shadow-sm animate-fade-in-up">
            <div class="flex items-center gap-2">
                <i class="ti ti-circle-check text-base"></i>
                <span id="{{ $prefix }}-profile-password-success-message">Password updated successfully.</span>
            </div>
            <button type="button" onclick="document.getElementById('{{ $prefix }}-profile-password-success').classList.add('hidden')" class="text-[#3B6D11] hover:opacity-80 border-none bg-transparent cursor-pointer">
                <i class="ti ti-x"></i>
            </button>
        </div>

        <div id="{{ $prefix }}-profile-password-error" class="hidden p-4 bg-[#FCEBEB] border border-[#A32D2D] text-[#A32D2D] rounded-xl text-xs font-semibold flex items-center justify-between shadow-sm animate-fade-in-up">
            <div class="flex items-center gap-2">
                <i class="ti ti-alert-triangle text-base"></i>
                <span id="{{ $prefix }}-profile-password-error-message">Failed to update password.</span>
            </div>
            <button type="button" onclick="document.getElementById('{{ $prefix }}-profile-password-error').classList.add('hidden')" class="text-[#A32D2D] hover:opacity-80 border-none bg-transparent cursor-pointer">
                <i class="ti ti-x"></i>
            </button>
        </div>

        <form id="{{ $prefix }}-password-form" onsubmit="handleStaffPasswordSubmit(event, '{{ $prefix }}')" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Current Password -->
                <div class="space-y-1">
                    <label for="{{ $prefix }}-profile-current-password" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1">
                        Current Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="{{ $prefix }}-profile-current-password" required placeholder="Enter current password" autocomplete="current-password"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 pl-3.5 pr-10 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                        <button type="button" onclick="toggleStaffPasswordVisibility('{{ $prefix }}-profile-current-password', '{{ $prefix }}-current-password-eye-icon')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0.5 focus:outline-none"
                                aria-label="Toggle Current Password Visibility">
                            <i id="{{ $prefix }}-current-password-eye-icon" class="ti ti-eye text-base"></i>
                        </button>
                    </div>
                    <span id="{{ $prefix }}-profile-current-password-error" class="hidden text-[11px] font-semibold text-red-600 block pt-0.5"></span>
                </div>

                <!-- New Password -->
                <div class="space-y-1">
                    <label for="{{ $prefix }}-profile-new-password" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1">
                        New Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="{{ $prefix }}-profile-new-password" required placeholder="Min. 8 characters" autocomplete="new-password"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 pl-3.5 pr-10 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                        <button type="button" onclick="toggleStaffPasswordVisibility('{{ $prefix }}-profile-new-password', '{{ $prefix }}-new-password-eye-icon')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0.5 focus:outline-none"
                                aria-label="Toggle New Password Visibility">
                            <i id="{{ $prefix }}-new-password-eye-icon" class="ti ti-eye text-base"></i>
                        </button>
                    </div>
                    <span id="{{ $prefix }}-profile-new-password-error" class="hidden text-[11px] font-semibold text-red-600 block pt-0.5"></span>
                </div>

                <!-- Confirm New Password -->
                <div class="space-y-1">
                    <label for="{{ $prefix }}-profile-new-password-confirmation" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1">
                        Confirm New Password <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="password" id="{{ $prefix }}-profile-new-password-confirmation" required placeholder="Re-enter new password" autocomplete="new-password"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 pl-3.5 pr-10 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                        <button type="button" onclick="toggleStaffPasswordVisibility('{{ $prefix }}-profile-new-password-confirmation', '{{ $prefix }}-confirm-password-eye-icon')"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0.5 focus:outline-none"
                                aria-label="Toggle Password Confirmation Visibility">
                            <i id="{{ $prefix }}-confirm-password-eye-icon" class="ti ti-eye text-base"></i>
                        </button>
                    </div>
                    <span id="{{ $prefix }}-profile-new-password-confirmation-error" class="hidden text-[11px] font-semibold text-red-600 block pt-0.5"></span>
                </div>
            </div>

            <!-- Password Action Buttons -->
            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" id="{{ $prefix }}-profile-password-reset" onclick="resetStaffPasswordForm('{{ $prefix }}')"
                        class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 text-xs font-bold transition border-none cursor-pointer">
                    Reset
                </button>
                <button type="submit" id="{{ $prefix }}-profile-password-save"
                        class="rounded-lg bg-[#003F87] hover:bg-[#002D62] text-white px-5 py-2.5 text-xs font-extrabold uppercase tracking-wider transition border-none cursor-pointer flex items-center gap-2 shadow-sm">
                    <i class="ti ti-key text-sm"></i>
                    <span id="{{ $prefix }}-profile-password-save-text">Update Password</span>
                </button>
            </div>
        </form>
    </div>
</div>
