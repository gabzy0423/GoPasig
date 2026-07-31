<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // 1. Temporarily expand enum to allow both old and new role values
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'dispatcher', 'fleet_manager', 'driver') NOT NULL");

            // 2. Update existing 'dispatcher' user records to 'fleet_manager'
            DB::table('users')->where('role', 'dispatcher')->update(['role' => 'fleet_manager']);

            // 3. Constrain enum to finalized role values
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'fleet_manager', 'driver') NOT NULL");
        } else {
            DB::statement('PRAGMA ignore_check_constraints = ON;');
            DB::table('users')->where('role', 'dispatcher')->update(['role' => 'fleet_manager']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'dispatcher', 'fleet_manager', 'driver') NOT NULL");
            DB::table('users')->where('role', 'fleet_manager')->update(['role' => 'dispatcher']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'dispatcher', 'driver') NOT NULL");
        } else {
            DB::table('users')->where('role', 'fleet_manager')->update(['role' => 'dispatcher']);
        }
    }
};
