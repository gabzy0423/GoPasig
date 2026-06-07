<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Alex Admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
            ],
            [
                'name' => 'Danielle Dispatcher',
                'email' => 'dispatcher@example.com',
                'role' => 'dispatcher',
            ],
            [
                'name' => 'David Driver',
                'email' => 'driver@example.com',
                'role' => 'driver',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('password123'),
                    'role' => $user['role'],
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
