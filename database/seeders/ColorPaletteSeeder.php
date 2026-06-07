<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ColorPalette;

class ColorPaletteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Default color palette (galing sa hardcoded values sa controllers)
        $colors = [
            ['hex_color' => '#003F87', 'name' => 'color_1', 'order' => 1, 'usage' => 'analytics'],
            ['hex_color' => '#3B6D11', 'name' => 'color_2', 'order' => 2, 'usage' => 'analytics'],
            ['hex_color' => '#854F0B', 'name' => 'color_3', 'order' => 3, 'usage' => 'analytics'],
            ['hex_color' => '#6B21A8', 'name' => 'color_4', 'order' => 4, 'usage' => 'analytics'],
            ['hex_color' => '#0F6E56', 'name' => 'color_5', 'order' => 5, 'usage' => 'analytics'],
            ['hex_color' => '#DC2626', 'name' => 'color_6', 'order' => 6, 'usage' => 'analytics'],
            ['hex_color' => '#0891B2', 'name' => 'color_7', 'order' => 7, 'usage' => 'analytics'],
            ['hex_color' => '#D97706', 'name' => 'color_8', 'order' => 8, 'usage' => 'analytics'],
        ];

        foreach ($colors as $color) {
            ColorPalette::updateOrCreate(
                ['name' => $color['name']],
                $color
            );
        }

        // Route-specific colors (from FleetController)
        $routeColors = [
            ['hex_color' => '#378ADD', 'name' => 'route_1', 'order' => 1, 'usage' => 'routes', 'description' => 'Route 1 color'],
            ['hex_color' => '#639922', 'name' => 'route_2', 'order' => 2, 'usage' => 'routes', 'description' => 'Route 2 color'],
            ['hex_color' => '#BA7517', 'name' => 'route_3', 'order' => 3, 'usage' => 'routes', 'description' => 'Route 3 color'],
            ['hex_color' => '#E24B4A', 'name' => 'route_4', 'order' => 4, 'usage' => 'routes', 'description' => 'Route 4 color'],
        ];

        foreach ($routeColors as $color) {
            ColorPalette::updateOrCreate(
                ['name' => $color['name']],
                $color
            );
        }
    }
}

