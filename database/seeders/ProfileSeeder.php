<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProfileSeeder extends Seeder
{
    public function run(): void
    {
        $arbitrator = User::where('role', 'arbitrator')->first();
        
        // Create verified profiles for half of the regular users
        User::where('role', 'user')->take(5)->each(function ($user) use ($arbitrator) {
            Profile::create([
                'user_id' => $user->id,
                'nin' => '12345678901',
                'bvn' => '12345678901',
                'nin_verified' => true,
                'bvn_verified' => true,
                'nin_phone' => $user->phone_number,
                'bvn_phone' => $user->phone_number,
                'nin_dob' => '1990-01-01',
                'bvn_dob' => '1990-01-01',
                'address' => 'Test Address',
                'state' => 'Lagos',
                'city' => 'Lagos',
                'profession' => 'Business Person',
                'verification_status' => 'verified',
                'id_document_url' => 'documents/id/test.pdf',
                'address_document_url' => 'documents/address/test.pdf',
                'verified_by' => $arbitrator->id,
                'verified_at' => now(),
            ]);
        });

        // Create pending profiles for remaining users
        User::where('role', 'user')->skip(5)->take(5)->each(function ($user) {
            Profile::create([
                'user_id' => $user->id,
                'nin' => '12345678901',
                'bvn' => '12345678901',
                'address' => 'Test Address',
                'state' => 'Lagos',
                'city' => 'Lagos',
                'profession' => 'Business Person',
                'id_document_url' => 'documents/id/test.pdf',
                'address_document_url' => 'documents/address/test.pdf',
            ]);
        });

        // Create verified profiles for arbitrators
        User::where('role', 'arbitrator')->each(function ($user) use ($arbitrator) {
            Profile::create([
                'user_id' => $user->id,
                'nin' => '12345678901',
                'bvn' => '12345678901',
                'nin_verified' => true,
                'bvn_verified' => true,
                'nin_phone' => $user->phone_number,
                'bvn_phone' => $user->phone_number,
                'nin_dob' => '1990-01-01',
                'bvn_dob' => '1990-01-01',
                'address' => 'Test Address',
                'state' => 'Lagos',
                'city' => 'Lagos',
                'profession' => 'Arbitrator',
                'verification_status' => 'verified',
                'id_document_url' => 'documents/id/test.pdf',
                'address_document_url' => 'documents/address/test.pdf',
                'verified_by' => $arbitrator->id,
                'verified_at' => now(),
            ]);
        });
    }
} 