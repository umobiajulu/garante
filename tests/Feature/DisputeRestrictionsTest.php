<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Traits\TestHelpers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Guarantee;
use App\Models\Dispute;

class DisputeRestrictionsTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_business_owner_cannot_update_business_with_unresolved_disputes()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        // Create a guarantee with an unresolved dispute
        $guarantee = Guarantee::factory()->create([
            'business_id' => $business->id,
            'price' => 10000,
            'status' => 'active'
        ]);

        Dispute::create([
            'guarantee_id' => $guarantee->id,
            'reason' => 'Test dispute',
            'status' => 'pending',
            'initiated_by' => $owner->id,
            'description' => 'Test dispute description',
            'evidence' => ['test_evidence' => 'Test evidence details']
        ]);

        $this->actingAs($owner);

        $response = $this->putJson("/api/businesses/{$business->id}", [
            'name' => 'Updated Business Name',
            'address' => 'Updated Address',
            'state' => 'Lagos',
            'city' => 'Lagos'
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Cannot update business details while there are unresolved disputes'
            ]);

        // Verify business details were not updated
        $this->assertDatabaseMissing('businesses', [
            'id' => $business->id,
            'name' => 'Updated Business Name'
        ]);
    }

    public function test_business_owner_cannot_update_profile_with_unresolved_disputes()
    {
        $owner = $this->createUser();
        $profile = $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        // Create a guarantee with an unresolved dispute
        $guarantee = Guarantee::factory()->create([
            'business_id' => $business->id,
            'price' => 10000,
            'status' => 'active'
        ]);

        Dispute::create([
            'guarantee_id' => $guarantee->id,
            'reason' => 'Test dispute',
            'status' => 'pending',
            'initiated_by' => $owner->id,
            'description' => 'Test dispute description',
            'evidence' => ['test_evidence' => 'Test evidence details']
        ]);

        $this->actingAs($owner);

        $idDocument = UploadedFile::fake()->create('new_id.pdf', 100);
        $addressDocument = UploadedFile::fake()->create('new_address.pdf', 100);

        $response = $this->putJson("/api/profiles/{$profile->id}", [
            'address' => 'Updated Address',
            'state' => 'Updated State',
            'city' => 'Updated City',
            'profession' => 'Updated Profession',
            'id_document' => $idDocument,
            'address_document' => $addressDocument
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Cannot update profile while there are unresolved disputes'
            ]);

        // Verify profile details were not updated
        $this->assertDatabaseMissing('profiles', [
            'id' => $profile->id,
            'address' => 'Updated Address'
        ]);
    }

    public function test_business_member_cannot_update_profile_with_unresolved_disputes()
    {
        $owner = $this->createUser(['email' => 'owner@example.com']);
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $member = $this->createUser(['email' => 'member@example.com']);
        $memberProfile = $this->createVerifiedProfile($member);
        $business->members()->attach($memberProfile->id, ['role' => 'staff']);

        // Create a guarantee with an unresolved dispute
        $guarantee = Guarantee::factory()->create([
            'business_id' => $business->id,
            'price' => 10000,
            'status' => 'active'
        ]);

        Dispute::create([
            'guarantee_id' => $guarantee->id,
            'reason' => 'Test dispute',
            'status' => 'pending',
            'initiated_by' => $member->id,
            'description' => 'Test dispute description',
            'evidence' => ['test_evidence' => 'Test evidence details']
        ]);

        $this->actingAs($member);

        $idDocument = UploadedFile::fake()->create('new_id.pdf', 100);
        $addressDocument = UploadedFile::fake()->create('new_address.pdf', 100);

        $response = $this->putJson("/api/profiles/{$memberProfile->id}", [
            'address' => 'Updated Address',
            'state' => 'Updated State',
            'city' => 'Updated City',
            'profession' => 'Updated Profession',
            'id_document' => $idDocument,
            'address_document' => $addressDocument
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Cannot update profile while there are unresolved disputes'
            ]);

        // Verify profile details were not updated
        $this->assertDatabaseMissing('profiles', [
            'id' => $memberProfile->id,
            'address' => 'Updated Address'
        ]);
    }

    public function test_can_update_after_dispute_resolution()
    {
        $owner = $this->createUser();
        $profile = $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        // Create a guarantee with a dispute
        $guarantee = Guarantee::factory()->create([
            'business_id' => $business->id,
            'price' => 10000,
            'status' => 'active'
        ]);

        $dispute = Dispute::create([
            'guarantee_id' => $guarantee->id,
            'reason' => 'Test dispute',
            'status' => 'pending',
            'initiated_by' => $owner->id,
            'description' => 'Test dispute description',
            'evidence' => ['test_evidence' => 'Test evidence details']
        ]);

        $this->actingAs($owner);

        // Try to update business - should fail
        $response = $this->putJson("/api/businesses/{$business->id}", [
            'name' => 'Updated Business Name',
            'address' => 'Updated Address',
            'state' => 'Lagos',
            'city' => 'Lagos'
        ]);
        $response->assertStatus(403);

        // Resolve the dispute
        $dispute->update(['status' => 'resolved']);

        // Try to update business again - should succeed
        $response = $this->putJson("/api/businesses/{$business->id}", [
            'name' => 'Updated Business Name',
            'address' => 'Updated Address',
            'state' => 'Lagos',
            'city' => 'Lagos'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Business updated successfully'
            ]);

        // Verify business details were updated
        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'name' => 'Updated Business Name'
        ]);
    }
} 