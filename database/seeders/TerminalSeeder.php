<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Terminal;

class TerminalSeeder extends Seeder
{
    /**
     * Seed the operational terminals for GoPasig.
     *
     * These three terminal names were previously hardcoded as PHP string
     * literals across multiple controllers and Livewire components.
     * They are now loaded dynamically via Terminal::getDefaultName().
     */
    public function run(): void
    {
        $terminals = [
            [
                'name'        => 'SPED Terminal (Caruncho Ave.)',
                'lat'         => 14.5593,
                'lng'         => 121.0805,
                'description' => 'Main SPED Terminal — system-wide default origin terminal.',
                'is_default'  => true,
            ],
            [
                'name'        => 'Pasig Terminal',
                'lat'         => 14.5838,
                'lng'         => 121.0620,
                'description' => 'Secondary Pasig terminal used as default route origin label.',
                'is_default'  => false,
            ],
            [
                'name'        => 'New Terminus',
                'lat'         => null,
                'lng'         => null,
                'description' => 'Placeholder destination label for newly created route stops.',
                'is_default'  => false,
            ],
        ];

        foreach ($terminals as $data) {
            Terminal::updateOrCreate(
                ['name' => $data['name']],
                $data
            );
        }
    }
}
