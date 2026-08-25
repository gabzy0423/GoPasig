@extends('layouts.fleet')

@section('title', 'GoPasig Fleet Ops - Maintenance')

@section('content')
<div class="flex h-screen w-screen overflow-hidden bg-white">
    <x-fleet.sidebar />

    <div class="flex flex-1 flex-col min-w-0 bg-white">
        <x-fleet.topbar />

        <main class="flex-grow overflow-y-auto bg-slate-50/50 p-6 relative">
            <div class="mx-auto w-full max-w-[1366px]">
                @include('fleet.maintenance.content', ['fleetFragment' => false])
            </div>
        </main>
    </div>
</div>
@endsection
