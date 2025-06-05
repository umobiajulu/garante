<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;

class BusinessSeeder extends Seeder
{
    public function run(): void
    {
        $arbitrator = User::where('role', 'arbitrator')->first();
        $verifiedProfiles = Profile::where('verification_status', 'verified')->get();

        // Create one business for each verified profile
        foreach ($verifiedProfiles as $profile) {
            $business = Business::create([
                'name' => "Business of {$profile->user->name}",
                'registration_number' => 'REG' . str_pad($profile->id, 6, '0', STR_PAD_LEFT),
                'business_type' => 'sole_proprietorship',
                'address' => 'Test Business Address',
                'state' => 'Lagos',
                'city' => 'Lagos',
                'owner_id' => $profile->user_id,
                'verification_status' => 'verified',
                'registration_document_url' => 'documents/business/test.pdf',
                'verified_by' => $arbitrator->id,
                'verified_at' => now(),
                'trust_score' => 100,
            ]);

            // Add owner as member
            $business->members()->attach($profile->id, ['role' => 'owner']);

            // Add some staff members (2 random verified profiles)
            $staffProfiles = $verifiedProfiles->where('id', '!=', $profile->id)->random(2);
            foreach ($staffProfiles as $staffProfile) {
                $business->members()->attach($staffProfile->id, ['role' => 'staff']);
            }
        }
    }
} 