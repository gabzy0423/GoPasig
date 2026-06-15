<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DriverUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drivers = Driver::whereNull('user_id')->get();

        foreach ($drivers as $driver) {
            // Lowercase and remove spaces/special chars from firstname for formatting email
            $firstNameClean = Str::slug($driver->first_name, '');
            $email = $firstNameClean . '@gopasig.com';

            // Ensure email uniqueness (in case duplicate first names exist)
            $baseEmail = $email;
            $counter = 1;
            while (User::where('email', $email)->exists()) {
                $email = $firstNameClean . $counter . '@gopasig.com';
                $counter++;
            }

            // Create user
            $user = User::create([
                'name' => trim($driver->first_name . ' ' . $driver->last_name),
                'email' => $email,
                'role' => 'driver',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]);

            // Link user to driver
            $driver->update([
                'user_id' => $user->id,
            ]);
        }
    }
}
