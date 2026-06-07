<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\Stop;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    /**
     * Store a newly created route in the database.
     */
    public function store(Request $request)
    {
        $count = Route::count() + 1;

        $route = Route::create([
            'name' => $request->name ?? ('Route ' . $count),
            'description' => $request->description,
            'color' => $request->color ?? '#003F87',
            'polyline_coordinates' => $request->polyline_coordinates ?? [],
            'status' => 'Active'
        ]);


        return response()->json([
            'success' => true,
            'message' => 'Route successfully created!',
            'route' => $route
        ], 201);
    }

    /**
     * Update the specified route details or status in the database.
     */
    public function update(Request $request, Route $route)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|nullable|string',
            'status' => 'sometimes|required|in:Active,Suspended',
        ]);

        $route->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Route successfully updated!',
            'route' => $route
        ]);
    }

    /**
     * Remove the specified route from the database.
     */
    public function destroy(Route $route)
    {
        $route->delete();

        return response()->json([
            'success' => true,
            'message' => 'Route successfully deleted!'
        ]);
    }
}
