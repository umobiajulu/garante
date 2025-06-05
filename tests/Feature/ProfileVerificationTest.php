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