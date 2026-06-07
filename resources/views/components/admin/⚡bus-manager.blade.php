<?php

use Livewire\Component;

new class extends Component
{
    public $buses = [];
    public $filter = 'all';
    public $search = '';

    // Form properties
    public $newPlate = '';
    public $newRoute = 'None - Unassigned';
    public $newDriver = '';
    public $newStatus = 'Active';

    public $showModal = false;

    public function mount()
    {
        $this->buses = [
            ['plate' => 'B-108 (PAS-439)', 'route' => 'San Joaquin - City Hall', 'driver' => 'Cardo Dalisay', 'status' => 'Active', 'gps' => 'Just Now'],
            ['plate' => 'B-215 (PAS-204)', 'route' => 'Rosario - Mega Market', 'driver' => 'Juan Dela Cruz', 'status' => 'Active', 'gps' => '2 mins ago'],
            ['plate' => 'B-309 (PAS-881)', 'route' => 'Buting - Mega Market', 'driver' => 'Ricardo Dalisay', 'status' => 'Maintenance', 'gps' => '10 mins ago'],
            ['plate' => 'B-430 (PAS-661)', 'route' => 'None - Unassigned', 'driver' => 'None', 'status' => 'Breakdown', 'gps' => '1 hr ago'],
        ];
    }

    public function filterBuses($status)
    {
        $this->filter = $status;
    }

    public function openAddBusModal()
    {
        $this->showModal = true;
    }

    public function closeAddBusModal()
    {
        $this->showModal = false;
        $this->reset(['newPlate', 'newRoute', 'newDriver', 'newStatus']);
    }

    public function registerBus()
    {
        $this->validate([
            'newPlate' => 'required|string',
            'newDriver' => 'required|string',
        ]);

        array_unshift($this->buses, [
            'plate' => $this->newPlate,
            'route' => $this->newRoute,
            'driver' => $this->newDriver,
            'status' => $this->newStatus,
            'gps' => 'Just Now',
        ]);

        $this->closeAddBusModal();
        $this->dispatch('busAdded', ['plate' => $this->newPlate]);
    }

    public function getFilteredBuses()
    {
        return collect($this->buses)
            ->filter(function ($bus) {
                if ($this->filter !== 'all' && $bus['status'] !== $this->filter) {
                    return false;
                }
                if ($this->search !== '') {
                    return str_contains(strtolower($bus['plate']), strtolower($this->search)) ||
                           str_contains(strtolower($bus['driver']), strtolower($this->search)) ||
                           str_contains(strtolower($bus['route']), strtolower($this->search));
                }
                return true;
            })->all();
    }
};
?>

<div>
    <!-- Top Action Bar -->
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 shrink-0">
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
            <button wire:click="filterBuses('all')" class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $filter === 'all' ? 'bg-[#003F87] text-white' : 'bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100' }} cursor-pointer">All Buses</button>
            <button wire:click="filterBuses('Active')" class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $filter === 'Active' ? 'bg-[#003F87] text-white' : 'bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100' }} cursor-pointer">Active</button>
            <button wire:click="filterBuses('Maintenance')" class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $filter === 'Maintenance' ? 'bg-[#003F87] text-white' : 'bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100' }} cursor-pointer">Maintenance</button>
            <button wire:click="filterBuses('Breakdown')" class="rounded-lg px-3 py-1.5 text-xs font-bold transition {{ $filter === 'Breakdown' ? 'bg-[#003F87] text-white' : 'bg-slate-50 border border-slate-200 text-slate-600 hover:bg-slate-100' }} cursor-pointer">Breakdown</button>
        </div>
        
        <!-- Search & Add Button -->
        <div class="flex items-center gap-3">
            <div class="relative w-64">
                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                    <i class="ti ti-search text-sm"></i>
                </span>
                <input wire:model.live="search" type="text" placeholder="Search bus plate..." 
                       class="w-full rounded-lg border border-slate-200 bg-white py-1.5 pl-9 pr-4 text-xs font-semibold text-slate-900 outline-none transition-all placeholder-slate-400 focus:border-[#003F87] focus:ring-1 focus:ring-[#003F87]">
            </div>
            <button wire:click="openAddBusModal" class="flex items-center gap-2 rounded-lg bg-[#003F87] px-4 py-1.8 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer">
                <i class="ti ti-plus text-sm"></i>
                Add Bus
            </button>
        </div>
    </div>

    <!-- Bus Management Table Grid container -->
    <div class="rounded-xl border border-[#E0E0E0] bg-white overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.06)] mt-6">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-widest text-slate-500">
                    <th class="px-5 py-3 font-bold">Bus Plate</th>
                    <th class="px-5 py-3 font-bold">Route Assigned</th>
                    <th class="px-5 py-3 font-bold">Assigned Driver</th>
                    <th class="px-5 py-3 font-bold">Status</th>
                    <th class="px-5 py-3 font-bold">Last GPS Update</th>
                    <th class="px-5 py-3 font-bold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="text-xs font-medium text-slate-700 divide-y divide-slate-100">
                @forelse($this->getFilteredBuses() as $bus)
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="px-5 py-3.5 font-bold text-[#003F87]">{{ $bus['plate'] }}</td>
                        <td class="px-5 py-3.5">{{ $bus['route'] }}</td>
                        <td class="px-5 py-3.5">{{ $bus['driver'] }}</td>
                        <td class="px-5 py-3.5">
                            @if($bus['status'] === 'Active')
                                <span class="inline-flex rounded-full bg-[#E8F4E0] px-2.5 py-0.5 text-[9px] font-bold text-[#639922] uppercase tracking-wider">Active</span>
                            @elseif($bus['status'] === 'Maintenance')
                                <span class="inline-flex rounded-full bg-[#FEF7ED] px-2.5 py-0.5 text-[9px] font-bold text-[#BA7517] uppercase tracking-wider">Maintenance</span>
                            @else
                                <span class="inline-flex rounded-full bg-[#FDF2F2] px-2.5 py-0.5 text-[9px] font-bold text-[#E24B4A] uppercase tracking-wider">Breakdown</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-slate-500">{{ $bus['gps'] }}</td>
                        <td class="px-5 py-3.5 text-right font-extrabold text-[#003F87] space-x-3">
                            <button class="hover:underline cursor-pointer">Edit</button>
                            <button class="hover:underline cursor-pointer text-slate-400">View</button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-slate-400 font-semibold uppercase tracking-wider">No buses found matching criteria.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- ADD BUS MODAL -->
    @if($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm p-4 animate-fade-in-up">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl border border-slate-100">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
                    <span class="text-sm font-extrabold uppercase tracking-widest text-[#003F87]">Add Bus Registration</span>
                    <button wire:click="closeAddBusModal" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i class="ti ti-x text-lg"></i></button>
                </div>
                
                <form wire:submit.prevent="registerBus" class="mt-4 space-y-4">
                    <div class="space-y-1">
                        <label for="new-bus-plate" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Bus Plate No</label>
                        <input wire:model="newPlate" id="new-bus-plate" type="text" placeholder="e.g. B-508 (PAS-442)" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                    </div>
                    
                    <div class="space-y-1">
                        <label for="new-bus-route" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Route Assignment</label>
                        <select wire:model="newRoute" id="new-bus-route" required
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                            <option value="None - Unassigned">None - Unassigned</option>
                            <option value="San Joaquin - City Hall">Line 1 - San Joaquin - City Hall</option>
                            <option value="Buting - Mega Market">Line 2 - Buting - Mega Market</option>
                            <option value="Rosario - Mega Market">Line 3 - Rosario - Mega Market</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label for="new-bus-driver" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Driver Assignment</label>
                        <input wire:model="newDriver" id="new-bus-driver" type="text" placeholder="e.g. Juan dela Cruz" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                    </div>

                    <div class="space-y-1">
                        <label for="new-bus-status" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Status</label>
                        <select wire:model="newStatus" id="new-bus-status" required
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                            <option value="Active">Active</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Breakdown">Breakdown</option>
                        </select>
                    </div>

                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 shrink-0">
                        <button type="button" wire:click="closeAddBusModal" class="rounded-lg bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200 transition cursor-pointer">Cancel</button>
                        <button type="submit" class="rounded-lg bg-[#003F87] px-5 py-2 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer">Register Bus</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>