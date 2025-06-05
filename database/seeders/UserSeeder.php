<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@garante.ng',
            'password' => Hash::make('password'),
            'phone_number' => '08011111111',
            'role' => 'admin',
        ]);

        // Create arbitrators
        for ($i = 1; $i <= 3; $i++) {
            User::create([
                'name' => "Arbitrator {$i}",
                'email' => "arbitrator{$i}@garante.ng",
                'password' => Hash::make('password'),
                'phone_number' => "0802222222{$i}",
                'role' => 'arbitrator',
            ]);
        }

        // Create regular users
        for ($i = 1; $i <= 10; $i++) {
            User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => Hash::make('password'),
                'phone_number' => "0803333333{$i}",
                'role' => 'user',
            ]);
        }
    }
} 