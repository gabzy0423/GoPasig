<section id="screen-profile" class="space-y-6 hidden animate-fade-in">
    <!-- Profile Alert Messages -->
    <div id="admin-profile-success" class="hidden p-4 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-xl text-xs font-semibold flex items-center justify-between shadow-sm animate-fade-in-up">
        <div class="flex items-center gap-2">
            <i class="ti ti-circle-check text-base"></i>
            <span id="admin-profile-success-message">Profile updated successfully.</span>
        </div>
        <button type="button" onclick="document.getElementById('admin-profile-success').classList.add('hidden')" class="text-[#3B6D11] hover:opacity-80 border-none bg-transparent cursor-pointer">
            <i class="ti ti-x"></i>
        </button>
    </div>
    
    <div id="admin-profile-error" class="hidden p-4 bg-[#FCEBEB] border border-[#A32D2D] text-[#A32D2D] rounded-xl text-xs font-semibold flex items-center justify-between shadow-sm animate-fade-in-up">
        <div class="flex items-center gap-2">
            <i class="ti ti-alert-triangle text-base"></i>
            <span id="admin-profile-error-message">Failed to update profile.</span>
        </div>
        <button type="button" onclick="document.getElementById('admin-profile-error').classList.add('hidden')" class="text-[#A32D2D] hover:opacity-80 border-none bg-transparent cursor-pointer">
            <i class="ti ti-x"></i>
        </button>
    </div>

    <!-- Header Section -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Account Profile</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Account Profile</span>
        </div>
        <p class="text-[11px] text-slate-500 font-semibold mt-1">Manage your administrative account details and contact information</p>
    </div>

    <!-- Personal Information Card Component -->
    <x-profile.personal-info-card prefix="admin" title="Personal & Contact Information" />

    <!-- Security & Password Management Component -->
    <x-profile.password-card prefix="admin" title="Security & Password Management" />

    <!-- Account Information & Completion Component -->
    <x-profile.account-info-card prefix="admin" title="Account Information & Profile Completion" />

    <!-- Recent Account Activity Component -->
    <x-profile.recent-activity-card prefix="admin" title="Recent Account Activity" />
</section>
