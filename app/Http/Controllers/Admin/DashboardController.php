<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Trip;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard');
    }

    public function getFleetData()
    {
        $routes = Route::with('stops')->get();
        $buses = Bus::all();
        $trips = Trip::with(['bus', 'driver', 'route'])->latest()->take(5)->get();

        return response()->json(compact('routes', 'buses', 'trips'));
    }
}
