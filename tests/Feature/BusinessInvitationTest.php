<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Traits\TestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Business;
use App\Models\BusinessInvitation;

class BusinessInvitationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_business_owner_can_send_invitation()
    {
        $owner = $this->createUser();
        $ownerProfile = $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $user = $this->createUser();
        $profile = $this->createVerifiedProfile($user);

        $this->actingAs($owner, 'api');

        $response = $this->postJson("/api/businesses/{$business->id}/invitations", [
            'user_id' => $user->id,
            'role' => 'staff'
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Invitation sent successfully'
            ]);

        $this->assertDatabaseHas('business_invitations', [
            'business_id' => $business->id,
            'profile_id' => $profile->id,
            'role' => 'staff',
            'status' => 'pending'
        ]);
    }

    public function test_user_can_accept_invitation()
    {
        $owner = $this->createUser();
        $ownerProfile = $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $user = $this->createUser();
        $profile = $this->createVerifiedProfile($user);

        $invitation = BusinessInvitation::create([
            'business_id' => $business->id,
            'profile_id' => $profile->id,
            'role' => 'staff',
            'expires_at' => now()->addDays(7)
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson("/api/invitations/{$invitation->id}/accept");

        $response->assertOk()
            ->assertJson([
                'message' => 'Invitation accepted successfully'
            ]);

        $this->assertDatabaseHas('business_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted'
        ]);

        $this->assertDatabaseHas('business_members', [
            'business_id' => $business->id,
            'profile_id' => $profile->id,
            'role' => 'staff'
        ]);
    }

    public function test_user_can_reject_invitation()
    {
        $owner = $this->createUser();
        $ownerProfile = $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $user = $this->createUser();
        $profile = $this->createVerifiedProfile($user);

        $invitation = BusinessInvitation::create([
            'business_id' => $business->id,
            'profile_id' => $profile->id,
            'role' => 'staff',
            'expires_at' => now()->addDays(7)
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson("/api/invitations/{$invitation->id}/reject");

        $response->assertOk()
            ->assertJson([
                'message' => 'Invitation rejected successfully'
            ]);

        $this->assertDatabaseHas('business_invitations', [
            'id' => $invitation->id,
            'status' => 'rejected'
        ]);

        $this->assertDatabaseMissing('business_members', [
            'business_id' => $business->id,
            'profile_id' => $profile->id
        ]);
    }

    public function test_expired_invitation_cannot_be_accepted()
    {
        $owner = $this->createUser();
        $ownerProfile = $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $user = $this->createUser();
        $profile = $this->createVerifiedProfile($user);

        $invitation = BusinessInvitation::create([
            'business_id' => $business->id,
            'profile_id' => $profile->id,
            'role' => 'staff',
            'expires_at' => now()->subDay()
        ]);

        $this->actingAs($user, 'api');

        $response = $this->postJson("/api/invitations/{$invitation->id}/accept");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Invitation has expired'
            ]);

        $this->assertDatabaseMissing('business_members', [
            'business_id' => $business->id,
            'profile_id' => $profile->id
        ]);
    }

    public function test_user_can_list_pending_invitations()
    {
        $owner = $this->createUser();
        $ownerProfile = $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $user = $this->createUser();
        $profile = $this->createVerifiedProfile($user);

        $invitation = BusinessInvitation::create([
            'business_id' => $business->id,
            'profile_id' => $profile->id,
            'role' => 'staff',
            'expires_at' => now()->addDays(7)
        ]);

        $this->actingAs($user, 'api');

        $response = $this->getJson('/api/invitations');

        $response->assertOk()
            ->assertJsonStructure([
                'invitations' => [
                    '*' => [
                        'id',
                        'business_id',
                        'profile_id',
                        'role',
                        'status',
                        'expires_at',
                        'business' => [
                            'id',
                            'name'
                        ]
                    ]
                ]
            ]);
    }

    public function test_user_cannot_accept_other_users_invitation()
    {
        $owner = $this->createUser();
        $ownerProfile = $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $user1 = $this->createUser();
        $profile1 = $this->createVerifiedProfile($user1);

        $invitation = BusinessInvitation::create([
            'business_id' => $business->id,
            'profile_id' => $profile1->id,
            'role' => 'staff',
            'expires_at' => now()->addDays(7)
        ]);

        $user2 = $this->createUser();
        $profile2 = $this->createVerifiedProfile($user2);

        $this->actingAs($user2, 'api');

        $response = $this->postJson("/api/invitations/{$invitation->id}/accept");

        $response->assertStatus(403);

        $this->assertDatabaseMissing('business_members', [
            'business_id' => $business->id,
            'profile_id' => $profile1->id
        ]);
    }
} 