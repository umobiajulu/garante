<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Traits\TestHelpers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BusinessTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_verified_user_can_create_business()
    {
        $user = $this->createUser();
        $this->createVerifiedProfile($user);
        $this->actingAs($user);

        $registrationDoc = UploadedFile::fake()->create('registration.pdf', 100);

        $response = $this->postJson('/api/businesses', [
            'name' => 'Test Business',
            'registration_number' => 'REG123456',
            'business_type' => 'sole_proprietorship',
            'address' => 'Test Business Address',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'registration_document' => $registrationDoc
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'business' => [
                        'id',
                        'name',
                        'registration_number',
                        'business_type',
                        'address',
                        'state',
                        'city',
                        'owner_id',
                        'verification_status'
                    ]
                ]);
    }

    public function test_business_verification_process()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $this->actingAs($owner);

        // Create business
        $business = $this->createVerifiedBusiness($owner);

        $response = $this->getJson("/api/businesses/{$business->id}");

        $response->assertOk()
                ->assertJsonStructure([
                    'message',
                    'business' => [
                        'id',
                        'name',
                        'registration_number',
                        'business_type',
                        'verification_status',
                        'verified_at',
                        'verified_by'
                    ]
                ]);
    }

    public function test_business_member_management()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $this->createVerifiedProfile($owner);
        $member = $this->createUser(['email' => 'member@example.com']);
        $this->createVerifiedProfile($member);
        
        $business = $this->createVerifiedBusiness($owner);
        $this->actingAs($owner);

        // Add member
        $response = $this->postJson("/api/businesses/{$business->id}/members", [
            'user_id' => $member->id,
            'role' => 'staff'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'member' => [
                        'id',
                        'name',
                        'email',
                        'role'
                    ]
                ]);

        // List members
        $response = $this->getJson("/api/businesses/{$business->id}/members");

        $response->assertOk()
                ->assertJsonStructure([
                    'message',
                    'members' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'role'
                        ]
                    ]
                ]);
    }

    public function test_member_can_leave_business()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $this->createVerifiedProfile($owner);
        $member = $this->createUser(['email' => 'member@example.com']);
        $this->createVerifiedProfile($member);
        
        $business = $this->createVerifiedBusiness($owner);
        
        // Add member
        $business->members()->attach($member->profile->id, ['role' => 'staff']);
        
        $this->actingAs($member);

        // Member leaves
        $response = $this->postJson("/api/businesses/{$business->id}/leave");

        $response->assertOk()
                ->assertJsonStructure(['message']);
    }

    public function test_owner_cannot_leave_business()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);
        $this->actingAs($owner);

        $response = $this->postJson("/api/businesses/{$business->id}/leave");

        $response->assertStatus(422)
                ->assertJsonStructure(['message']);
    }
} 