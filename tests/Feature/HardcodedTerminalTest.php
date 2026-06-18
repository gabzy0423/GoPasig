<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\Stop;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HardcodedTerminalTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function seedDefaultTerminal(): Terminal
    {
        return Terminal::create([
            'name'        => 'SPED Terminal (Caruncho Ave.)',
            'lat'         => 14.5593,
            'lng'         => 121.0805,
            'description' => 'Main default terminal',
            'is_default'  => true,
        ]);
    }

    private function makeRoute(): Route
    {
        return Route::create([
            'id'                   => 1,
            'name'                 => 'Route A',
            'description'          => 'SPED to City Hall',
            'polyline_coordinates' => [[14.5593, 121.0805]],
            'status'               => 'Active',
        ]);
    }

    // -------------------------------------------------------------------------
    // Terminal model helpers
    // -------------------------------------------------------------------------

    public function test_get_default_name_returns_is_default_terminal(): void
    {
        Terminal::create(['name' => 'Other Terminal', 'is_default' => false]);
        $default = $this->seedDefaultTerminal();

        $this->assertEquals($default->name, Terminal::getDefaultName());
    }

    public function test_get_default_name_returns_fallback_when_table_empty(): void
    {
        // Table exists but is empty
        $this->assertEquals('My Fallback', Terminal::getDefaultName('My Fallback'));
    }

    public function test_find_by_name_returns_exact_match(): void
    {
        Terminal::create(['name' => 'New Terminus', 'is_default' => false]);

        $this->assertEquals('New Terminus', Terminal::findByName('New Terminus', 'Fallback'));
    }

    public function test_find_by_name_returns_partial_match(): void
    {
        Terminal::create(['name' => 'New Terminus', 'is_default' => false]);

        $this->assertEquals('New Terminus', Terminal::findByName('Terminus', 'Fallback'));
    }

    public function test_find_by_name_returns_fallback_when_not_found(): void
    {
        $this->assertEquals('Fallback', Terminal::findByName('Ghost Terminal', 'Fallback'));
    }

    // -------------------------------------------------------------------------
    // StopController: no longer falls back to hardcoded 'Pasig Terminal'
    // -------------------------------------------------------------------------

    public function test_stop_store_uses_db_terminal_for_origin_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $route      = $this->makeRoute();

        // No DefaultRouteSetting row → falls through to SystemSetting → falls through to Terminal
        $this->seedDefaultTerminal();

        // POST with no 'name' → should use the default terminal from DB as origin label
        $response = $this->actingAs($admin)
            ->postJson('/admin/api/stops', ['route_id' => $route->id]);

        $response->assertStatus(201);

        // The created stop's name should be the DB terminal name, not a hardcoded PHP string
        $this->assertDatabaseHas('stops', [
            'route_id' => $route->id,
            'name'     => 'SPED Terminal (Caruncho Ave.)',
        ]);
    }

    // -------------------------------------------------------------------------
    // Terminal::getDefaultName() is safe when terminals table is empty
    // -------------------------------------------------------------------------

    public function test_get_default_returns_null_when_table_empty(): void
    {
        $this->assertNull(Terminal::getDefault());
    }
}
