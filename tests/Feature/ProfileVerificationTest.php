<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Traits\TestHelpers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Profile;

class ProfileVerificationTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_user_can_submit_profile_verification()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $idDocument = UploadedFile::fake()->create('id.pdf', 100);
        $addressDocument = UploadedFile::fake()->create('address.pdf', 100);

        $response = $this->postJson('/api/profiles', [
            'nin' => '12345678901',
            'bvn' => '12345678901',
            'address' => 'Test Address',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'profession' => 'Business Person',
            'id_document' => $idDocument,
            'address_document' => $addressDocument
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'profile' => [
                        'id',
                        'user_id',
                        'verification_status',
                        'address',
                        'state',
                        'city',
                        'profession'
                    ]
                ]);
    }

    public function test_user_cannot_create_multiple_profiles()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        // Create first profile
        $idDocument1 = UploadedFile::fake()->create('id1.pdf', 100);
        $addressDocument1 = UploadedFile::fake()->create('address1.pdf', 100);

        $firstResponse = $this->postJson('/api/profiles', [
            'nin' => '12345678901',
            'bvn' => '12345678901',
            'address' => 'Test Address 1',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'profession' => 'Business Person',
            'id_document' => $idDocument1,
            'address_document' => $addressDocument1
        ]);

        $firstResponse->assertStatus(201);

        // Attempt to create second profile
        $idDocument2 = UploadedFile::fake()->create('id2.pdf', 100);
        $addressDocument2 = UploadedFile::fake()->create('address2.pdf', 100);

        $secondResponse = $this->postJson('/api/profiles', [
            'nin' => '98765432109',
            'bvn' => '98765432109',
            'address' => 'Test Address 2',
            'state' => 'Abuja',
            'city' => 'Abuja',
            'profession' => 'Engineer',
            'id_document' => $idDocument2,
            'address_document' => $addressDocument2
        ]);

        // Assert that the second attempt fails
        $secondResponse->assertStatus(422)
            ->assertJson([
                'message' => 'User already has a profile',
                'error' => 'Only one profile per user is allowed'
            ]);

        // Verify that only one profile exists for the user
        $this->assertEquals(1, $user->fresh()->profile()->count());
    }

    public function test_user_can_delete_profile_with_reason()
    {
        $user = $this->createUser();
        $profile = $this->createVerifiedProfile($user);
        $this->actingAs($user);

        $response = $this->deleteJson("/api/profiles/{$profile->id}", [
            'reason' => 'Moving to a different country and closing all accounts'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Profile and user account have been deactivated successfully'
            ]);

        // Verify that the profile and user are soft deleted
        $this->assertTrue($profile->fresh()->trashed());
        $this->assertTrue($user->fresh()->trashed());

        // Verify that the deletion reason was stored
        $this->assertEquals(
            'Moving to a different country and closing all accounts',
            $profile->fresh()->deletion_reason
        );

        // Verify that the profile doesn't appear in regular queries
        $this->assertNull(Profile::find($profile->id));
        
        // But it should appear when including trashed records
        $this->assertNotNull(Profile::withTrashed()->find($profile->id));
    }

    public function test_user_cannot_delete_profile_without_reason()
    {
        $user = $this->createUser();
        $profile = $this->createVerifiedProfile($user);
        $this->actingAs($user);

        $response = $this->deleteJson("/api/profiles/{$profile->id}", [
            'reason' => ''
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        // Verify that the profile and user are not deleted
        $this->assertFalse($profile->fresh()->trashed());
        $this->assertFalse($user->fresh()->trashed());
    }

    public function test_user_cannot_delete_another_users_profile()
    {
        $user1 = $this->createUser();
        $profile1 = $this->createVerifiedProfile($user1);

        $user2 = $this->createUser();
        $this->actingAs($user2);

        $response = $this->deleteJson("/api/profiles/{$profile1->id}", [
            'reason' => 'Trying to delete someone else\'s profile'
        ]);

        $response->assertStatus(403);

        // Verify that the profile and user are not deleted
        $this->assertFalse($profile1->fresh()->trashed());
        $this->assertFalse($user1->fresh()->trashed());
    }

    public function test_nin_verification_process()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        // Create profile first
        $profile = Profile::create([
            'user_id' => $user->id,
            'nin' => null,
            'bvn' => null,
            'address' => 'Test Address',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'profession' => 'Business Person',
            'verification_status' => 'pending',
            'id_document_url' => 'documents/id/test.pdf',
            'address_document_url' => 'documents/address/test.pdf'
        ]);

        // Submit NIN for verification
        $response = $this->postJson('/api/verify-nin', [
            'nin' => '12345678901',
            'nin_phone' => $user->phone_number,
            'nin_dob' => '1990-01-01'
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'message',
                    'profile' => [
                        'id',
                        'nin_verified',
                        'verification_status'
                    ]
                ]);

        // Check NIN verification status
        $response = $this->getJson('/api/verification-status');

        $response->assertOk()
                ->assertJsonStructure([
                    'verification_status' => [
                        'nin_verified',
                        'bvn_verified',
                        'phone_verified',
                        'dob_verified',
                        'verification_status',
                        'missing_requirements'
                    ]
                ]);
    }

    public function test_bvn_verification_process()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        // Create profile first
        $profile = Profile::create([
            'user_id' => $user->id,
            'nin' => null,
            'bvn' => null,
            'address' => 'Test Address',
            'state' => 'Lagos',
            'city' => 'Lagos',
            'profession' => 'Business Person',
            'verification_status' => 'pending',
            'id_document_url' => 'documents/id/test.pdf',
            'address_document_url' => 'documents/address/test.pdf'
        ]);

        // Submit BVN for verification
        $response = $this->postJson('/api/verify-bvn', [
            'bvn' => '12345678901',
            'bvn_phone' => $user->phone_number,
            'bvn_dob' => '1990-01-01'
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'message',
                    'profile' => [
                        'id',
                        'bvn_verified',
                        'verification_status'
                    ]
                ]);

        // Check BVN verification status
        $response = $this->getJson('/api/verification-status');

        $response->assertOk()
                ->assertJsonStructure([
                    'verification_status' => [
                        'nin_verified',
                        'bvn_verified',
                        'phone_verified',
                        'dob_verified',
                        'verification_status',
                        'missing_requirements'
                    ]
                ]);
    }

    public function test_profile_verification_completion()
    {
        $user = $this->createUser();
        $arbitrator = $this->createArbitrator();

        $profile = Profile::create([
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

        $this->assertTrue($profile->isFullyVerified());
    }
} 