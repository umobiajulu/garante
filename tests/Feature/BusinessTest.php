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
        $this->actingAs($owner, 'api');

        // Send invitation
        $response = $this->postJson("/api/businesses/{$business->id}/invitations", [
            'user_id' => $member->id,
            'role' => 'staff'
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'invitation' => [
                        'id',
                        'business_id',
                        'profile_id',
                        'role',
                        'status',
                        'expires_at'
                    ]
                ]);

        // Switch to member user and accept invitation
        $this->actingAs($member, 'api');
        $invitation = $business->invitations()->where('profile_id', $member->profile->id)->first();
        
        $response = $this->postJson("/api/invitations/{$invitation->id}/accept");
        
        $response->assertOk()
                ->assertJsonStructure([
                    'message'
                ]);

        // Switch back to owner and list members
        $this->actingAs($owner, 'api');
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

    public function test_verified_profile_cannot_join_multiple_businesses()
    {
        // Create first business and owner
        $firstOwner = $this->createUser(['email' => 'owner1@example.com']);
        $firstOwnerProfile = $this->createVerifiedProfile($firstOwner);
        $firstBusiness = $this->createVerifiedBusiness($firstOwner);

        // Create second business and owner
        $secondOwner = $this->createUser(['email' => 'owner2@example.com']);
        $secondOwnerProfile = $this->createVerifiedProfile($secondOwner);
        $secondBusiness = $this->createVerifiedBusiness($secondOwner);

        // Create a verified profile to test with
        $user = $this->createUser(['email' => 'staff@example.com']);
        $profile = $this->createVerifiedProfile($user);

        // Add profile to first business
        $this->actingAs($firstOwner);
        $response = $this->postJson("/api/businesses/{$firstBusiness->id}/invitations", [
            'user_id' => $user->id,
            'role' => 'staff'
        ]);
        $response->assertStatus(201);

        // Accept the invitation
        $this->actingAs($user);
        $invitation = $firstBusiness->invitations()->where('profile_id', $profile->id)->first();
        $response = $this->postJson("/api/invitations/{$invitation->id}/accept");
        $response->assertOk();

        // Try to add the same profile to second business
        $this->actingAs($secondOwner);
        $response = $this->postJson("/api/businesses/{$secondBusiness->id}/invitations", [
            'user_id' => $user->id,
            'role' => 'staff'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'Profile is already a member of another business'
                ]);
    }

    public function test_profile_can_join_business_after_leaving_previous_one()
    {
        // Create first business and owner
        $firstOwner = $this->createUser(['email' => 'owner1@example.com']);
        $firstOwnerProfile = $this->createVerifiedProfile($firstOwner);
        $firstBusiness = $this->createVerifiedBusiness($firstOwner);

        // Create second business and owner
        $secondOwner = $this->createUser(['email' => 'owner2@example.com']);
        $secondOwnerProfile = $this->createVerifiedProfile($secondOwner);
        $secondBusiness = $this->createVerifiedBusiness($secondOwner);

        // Create a verified profile to test with
        $user = $this->createUser(['email' => 'staff@example.com']);
        $profile = $this->createVerifiedProfile($user);

        // Add profile to first business
        $this->actingAs($firstOwner);
        $response = $this->postJson("/api/businesses/{$firstBusiness->id}/invitations", [
            'user_id' => $user->id,
            'role' => 'staff'
        ]);
        $response->assertStatus(201);

        // Accept the invitation
        $this->actingAs($user);
        $invitation = $firstBusiness->invitations()->where('profile_id', $profile->id)->first();
        $response = $this->postJson("/api/invitations/{$invitation->id}/accept");
        $response->assertOk();

        // Leave the first business
        $response = $this->postJson("/api/businesses/{$firstBusiness->id}/leave");
        $response->assertOk();

        // Try to add the profile to second business
        $this->actingAs($secondOwner);
        $response = $this->postJson("/api/businesses/{$secondBusiness->id}/invitations", [
            'user_id' => $user->id,
            'role' => 'staff'
        ]);
        $response->assertStatus(201);

        // Accept the invitation for second business
        $this->actingAs($user);
        $invitation = $secondBusiness->invitations()->where('profile_id', $profile->id)->first();
        $response = $this->postJson("/api/invitations/{$invitation->id}/accept");
        $response->assertOk();
    }

    public function test_business_owner_cannot_create_another_business()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $ownerProfile = $this->createVerifiedProfile($owner);
        $firstBusiness = $this->createVerifiedBusiness($owner);

        $this->actingAs($owner);

        $registrationDoc = UploadedFile::fake()->create('registration.pdf', 100);

        $response = $this->postJson('/api/businesses', [
            'name' => 'Second Business',
            'registration_number' => 'REG123457',
            'business_type' => 'sole_proprietorship',
            'address' => 'Test Business Address',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'registration_document' => $registrationDoc
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'You are already a member or owner of another business'
                ]);
    }

    public function test_business_member_cannot_create_business()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $ownerProfile = $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $member = $this->createUser(['email' => 'member@example.com']);
        $memberProfile = $this->createVerifiedProfile($member);
        $business->members()->attach($memberProfile->id, ['role' => 'staff']);

        $this->actingAs($member);

        $registrationDoc = UploadedFile::fake()->create('registration.pdf', 100);

        $response = $this->postJson('/api/businesses', [
            'name' => 'Member Business',
            'registration_number' => 'REG123458',
            'business_type' => 'sole_proprietorship',
            'address' => 'Test Business Address',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'registration_document' => $registrationDoc
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'You are already a member or owner of another business'
                ]);
    }

    public function test_business_owner_cannot_join_another_business()
    {
        // Create first business and owner
        $firstOwner = $this->createUser(['email' => 'owner1@example.com']);
        $firstOwnerProfile = $this->createVerifiedProfile($firstOwner);
        $firstBusiness = $this->createVerifiedBusiness($firstOwner);

        // Create second business and owner
        $secondOwner = $this->createUser(['email' => 'owner2@example.com']);
        $secondOwnerProfile = $this->createVerifiedProfile($secondOwner);
        $secondBusiness = $this->createVerifiedBusiness($secondOwner);

        // Try to add the first owner to the second business
        $this->actingAs($secondOwner);
        $response = $this->postJson("/api/businesses/{$secondBusiness->id}/invitations", [
            'user_id' => $firstOwner->id,
            'role' => 'staff'
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'message' => 'Profile is already a member of another business'
                ]);
    }

    public function test_profile_can_create_business_after_leaving_previous_one()
    {
        // Create first business with owner
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $ownerProfile = $this->createVerifiedProfile($owner);
        $firstBusiness = $this->createVerifiedBusiness($owner);

        // Create a member
        $member = $this->createUser(['email' => 'member@example.com']);
        $memberProfile = $this->createVerifiedProfile($member);
        $firstBusiness->members()->attach($memberProfile->id, ['role' => 'staff']);

        $this->actingAs($member);

        // Member leaves the business
        $response = $this->postJson("/api/businesses/{$firstBusiness->id}/leave");
        $response->assertOk();

        // Try to create a new business
        $registrationDoc = UploadedFile::fake()->create('registration.pdf', 100);

        $response = $this->postJson('/api/businesses', [
            'name' => 'New Business',
            'registration_number' => 'REG123459',
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
} 