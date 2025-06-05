<?php

namespace Tests\Feature\Traits;

use App\Models\User;
use App\Models\Profile;
use App\Models\Business;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

trait TestHelpers
{
    protected function createUser(array $attributes = []): User
    {
        $defaultAttributes = [
            'phone_number' => '1234567890'
        ];
        
        return User::factory()->create(array_merge($defaultAttributes, $attributes));
    }

    protected function createArbitrator(): User
    {
        return $this->createUser(['role' => 'arbitrator']);
    }

    protected function createVerifiedProfile(User $user): Profile
    {
        $arbitrator = User::factory()->arbitrator()->create();
        
        return Profile::create([
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
    }

    protected function createVerifiedBusiness(User $owner): Business
    {
        $arbitrator = User::factory()->arbitrator()->create();
        
        $business = Business::create([
            'name' => 'Test Business',
            'registration_number' => 'REG' . uniqid(),
            'business_type' => 'sole_proprietorship',
            'address' => 'Test Business Address',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'owner_id' => $owner->id,
            'verification_status' => 'verified',
            'registration_document_url' => 'documents/business/test.pdf',
            'verified_by' => $arbitrator->id,
            'verified_at' => now(),
            'trust_score' => 100,
        ]);

        // Attach owner's profile as a member
        $business->members()->attach($owner->profile->id, ['role' => 'owner']);

        return $business;
    }

    public function actingAs($user, $guard = null)
    {
        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;
        $this->withHeader('Authorization', 'Bearer ' . $token);
        return $this;
    }
} 