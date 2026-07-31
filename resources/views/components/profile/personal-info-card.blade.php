@props([
    'prefix' => 'admin',
    'title'  => 'Personal & Contact Information',
])

<div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="ti ti-id text-lg text-[#003F87]"></i>
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-800">{{ $title }}</h2>
        </div>
        <span id="{{ $prefix }}-profile-loading" class="hidden text-xs font-semibold text-slate-400 flex items-center gap-1.5">
            <i class="ti ti-loader animate-spin text-sm text-[#003F87]"></i> Loading profile...
        </span>
    </div>

    <div class="p-6">
        <form id="{{ $prefix }}-profile-form" onsubmit="handleStaffProfileSubmit(event, '{{ $prefix }}')" class="space-y-6">
            <!-- Avatar Preview & User Meta Section -->
            <div class="flex flex-col sm:flex-row sm:items-center gap-4 pb-6 border-b border-slate-100">
                <div id="{{ $prefix }}-profile-avatar-preview" class="h-16 w-16 rounded-full bg-[#003F87]/10 flex items-center justify-center font-black text-[#003F87] text-xl shrink-0 border-2 border-[#003F87]/20 shadow-sm overflow-hidden">
                    --
                </div>
                <div class="space-y-1">
                    <h3 id="{{ $prefix }}-profile-display-name" class="text-base font-bold text-slate-900">User Profile</h3>
                    <p class="text-xs font-medium text-slate-500 flex items-center gap-2">
                        <span id="{{ $prefix }}-profile-role" disabled readonly class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold uppercase text-[10px]">User</span>
                        <span class="text-slate-300">•</span>
                        <span id="{{ $prefix }}-profile-id-display" class="font-mono text-[11px] font-bold text-slate-500">ID: --</span>
                    </p>

                    <!-- Profile Photo Actions -->
                    <div class="pt-1 flex items-center gap-2">
                        <input type="file" id="{{ $prefix }}-profile-photo-input" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden" onchange="handleStaffPhotoUpload(event, '{{ $prefix }}')">
                        <button type="button" id="{{ $prefix }}-profile-photo-upload-btn" onclick="document.getElementById('{{ $prefix }}-profile-photo-input').click()"
                                class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-1.5 text-xs font-bold transition flex items-center gap-1.5 border-none cursor-pointer">
                            <i class="ti ti-camera text-sm"></i>
                            <span>Upload Photo</span>
                        </button>
                        <button type="button" id="{{ $prefix }}-profile-photo-remove-btn" onclick="handleStaffPhotoRemove('{{ $prefix }}')"
                                class="hidden rounded-lg bg-red-50 hover:bg-red-100 text-red-700 px-3 py-1.5 text-xs font-bold transition flex items-center gap-1.5 border-none cursor-pointer">
                            <i class="ti ti-trash text-sm"></i>
                            <span>Remove</span>
                        </button>
                    </div>
                    <span id="{{ $prefix }}-profile-photo-error" class="hidden text-[11px] font-semibold text-red-600 block pt-1"></span>
                </div>
            </div>

            <!-- Form Fields Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Full Name -->
                <div class="space-y-1">
                    <label for="{{ $prefix }}-profile-name" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1">
                        Full Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="{{ $prefix }}-profile-name" required placeholder="Enter full name"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    <span id="{{ $prefix }}-profile-name-error" class="hidden text-[11px] font-semibold text-red-600"></span>
                </div>

                <!-- Email Address -->
                <div class="space-y-1">
                    <label for="{{ $prefix }}-profile-email" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1">
                        Email Address <span class="text-red-500">*</span>
                    </label>
                    <input type="email" id="{{ $prefix }}-profile-email" required placeholder="name@gopasig.gov.ph"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    <span id="{{ $prefix }}-profile-email-error" class="hidden text-[11px] font-semibold text-red-600"></span>
                </div>

                <!-- Contact Number -->
                <div class="space-y-1">
                    <label for="{{ $prefix }}-profile-contact-number" class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Contact Number
                    </label>
                    <input type="text" id="{{ $prefix }}-profile-contact-number" placeholder="e.g. 0917 123 4567"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    <span id="{{ $prefix }}-profile-contact-number-error" class="hidden text-[11px] font-semibold text-red-600"></span>
                </div>

                <!-- Emergency Contact -->
                <div class="space-y-1">
                    <label for="{{ $prefix }}-profile-emergency-contact" class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Emergency Contact
                    </label>
                    <input type="text" id="{{ $prefix }}-profile-emergency-contact" placeholder="Name - Relationship - Phone"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    <span id="{{ $prefix }}-profile-emergency-contact-error" class="hidden text-[11px] font-semibold text-red-600"></span>
                </div>

                <!-- Complete Address -->
                <div class="space-y-1 md:col-span-2">
                    <label for="{{ $prefix }}-profile-address" class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        Complete Address
                    </label>
                    <textarea id="{{ $prefix }}-profile-address" rows="2" placeholder="Enter complete office or residential address..."
                              class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]"></textarea>
                    <span id="{{ $prefix }}-profile-address-error" class="hidden text-[11px] font-semibold text-red-600"></span>
                </div>
            </div>

            <!-- Form Action Buttons -->
            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100">
                <button type="button" id="{{ $prefix }}-profile-reset" onclick="resetStaffProfileForm('{{ $prefix }}')"
                        class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2.5 text-xs font-bold transition border-none cursor-pointer">
                    Reset Changes
                </button>
                <button type="submit" id="{{ $prefix }}-profile-save"
                        class="rounded-lg bg-[#003F87] hover:bg-[#002D62] text-white px-5 py-2.5 text-xs font-extrabold uppercase tracking-wider transition border-none cursor-pointer flex items-center gap-2 shadow-sm">
                    <i class="ti ti-check text-sm"></i>
                    <span id="{{ $prefix }}-profile-save-text">Save Changes</span>
                </button>
            </div>
        </form>
    </div>
</div>
