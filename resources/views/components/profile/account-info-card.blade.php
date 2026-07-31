@props([
    'prefix' => 'admin',
    'title'  => 'Account Information & Profile Completion',
])

<div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mt-6">
    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="ti ti-shield-check text-lg text-[#003F87]"></i>
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-800">{{ $title }}</h2>
        </div>
    </div>

    <div class="p-6 space-y-6">
        <!-- Profile Completion Progress Bar & Missing Chips -->
        <div class="space-y-3 p-4 bg-slate-50 border border-slate-100 rounded-xl">
            <div class="flex items-center justify-between">
                <span class="text-xs font-extrabold uppercase tracking-wider text-slate-700 flex items-center gap-1.5">
                    <i class="ti ti-chart-bar text-sm text-[#003F87]"></i> Profile Completion
                </span>
                <div class="flex items-center gap-2">
                    <span id="{{ $prefix }}-profile-completion-count" class="text-xs font-semibold text-slate-500">0 / 6 fields completed</span>
                    <span id="{{ $prefix }}-profile-completion-badge" class="px-2 py-0.5 rounded-full text-xs font-black bg-[#003F87]/10 text-[#003F87]">0%</span>
                </div>
            </div>
            <div class="w-full bg-slate-200 h-2.5 rounded-full overflow-hidden">
                <div id="{{ $prefix }}-profile-completion-bar" class="bg-[#003F87] h-full transition-all duration-500 rounded-full" style="width: 0%"></div>
            </div>
            <div id="{{ $prefix }}-profile-completion-missing-container" class="space-y-1.5 pt-1 hidden">
                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Missing Fields:</span>
                <div id="{{ $prefix }}-profile-completion-missing-chips" class="flex flex-wrap gap-1.5"></div>
            </div>
        </div>

        <!-- Read-Only Account Metadata Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <div class="p-3 bg-white border border-slate-200/80 rounded-xl space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">User Reference ID</span>
                <p id="{{ $prefix }}-account-info-user-id" class="text-xs font-mono font-bold text-slate-800">--</p>
            </div>
            <div class="p-3 bg-white border border-slate-200/80 rounded-xl space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">System Role</span>
                <p id="{{ $prefix }}-account-info-role" class="text-xs font-bold text-[#003F87]">User</p>
            </div>
            <div class="p-3 bg-white border border-slate-200/80 rounded-xl space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Account Created</span>
                <p id="{{ $prefix }}-account-info-created-at" class="text-xs font-semibold text-slate-700">--</p>
            </div>
            <div class="p-3 bg-white border border-slate-200/80 rounded-xl space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Email Verification</span>
                <p id="{{ $prefix }}-account-info-email-verified" class="text-xs font-semibold text-slate-700">Verified</p>
            </div>
            <div class="p-3 bg-white border border-slate-200/80 rounded-xl space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Last Profile Update</span>
                <p id="{{ $prefix }}-account-info-last-profile-update" class="text-xs font-semibold text-slate-700">--</p>
            </div>
            <div class="p-3 bg-white border border-slate-200/80 rounded-xl space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Last Password Changed</span>
                <p id="{{ $prefix }}-account-info-last-password-change" class="text-xs font-semibold text-slate-700">Never</p>
            </div>
        </div>
    </div>
</div>
