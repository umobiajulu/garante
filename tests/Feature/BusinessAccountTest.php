<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Traits\TestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Account;

class BusinessAccountTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_business_owner_can_add_account()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);
        
        $this->actingAs($owner, 'sanctum');

        $response = $this->postJson("/api/businesses/{$business->id}/accounts", [
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'account' => [
                    'id',
                    'business_id',
                    'bank_name',
                    'account_name',
                    'account_number',
                    'status',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('accounts', [
            'business_id' => $business->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890'
        ]);
    }

    public function test_business_member_cannot_add_account()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $member = $this->createUser();
        $memberProfile = $this->createVerifiedProfile($member);
        $business->members()->attach($memberProfile->id, ['role' => 'staff']);

        $this->actingAs($member, 'sanctum');

        $response = $this->postJson("/api/businesses/{$business->id}/accounts", [
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890'
        ]);

        $response->assertStatus(403);
    }

    public function test_business_member_can_view_accounts()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $member = $this->createUser();
        $memberProfile = $this->createVerifiedProfile($member);
        $business->members()->attach($memberProfile->id, ['role' => 'staff']);

        Account::create([
            'business_id' => $business->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'status' => 'active'
        ]);

        $this->actingAs($member, 'sanctum');

        $response = $this->getJson("/api/businesses/{$business->id}/accounts");

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'accounts' => [
                    '*' => [
                        'id',
                        'business_id',
                        'bank_name',
                        'account_name',
                        'account_number',
                        'status',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    public function test_non_member_cannot_view_accounts()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $nonMember = $this->createUser();
        $this->createVerifiedProfile($nonMember);

        $this->actingAs($nonMember, 'sanctum');

        $response = $this->getJson("/api/businesses/{$business->id}/accounts");

        $response->assertStatus(403);
    }

    public function test_business_owner_can_remove_account()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $account = Account::create([
            'business_id' => $business->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'status' => 'active'
        ]);

        $this->actingAs($owner, 'sanctum');

        $response = $this->deleteJson("/api/businesses/{$business->id}/accounts/{$account->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Account removed successfully'
            ]);

        $this->assertDatabaseMissing('accounts', [
            'id' => $account->id
        ]);
    }

    public function test_business_member_cannot_remove_account()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $member = $this->createUser();
        $memberProfile = $this->createVerifiedProfile($member);
        $business->members()->attach($memberProfile->id, ['role' => 'staff']);

        $account = Account::create([
            'business_id' => $business->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'status' => 'active'
        ]);

        $this->actingAs($member, 'sanctum');

        $response = $this->deleteJson("/api/businesses/{$business->id}/accounts/{$account->id}");

        $response->assertStatus(403);
    }

    public function test_account_validation_rules()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $this->actingAs($owner, 'sanctum');

        // Test empty fields
        $response = $this->postJson("/api/businesses/{$business->id}/accounts", []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank_name', 'account_name', 'account_number']);

        // Test invalid account number format
        $response = $this->postJson("/api/businesses/{$business->id}/accounts", [
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => 'abc123' // Non-numeric
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_number']);

        // Test duplicate account number for same bank
        Account::create([
            'business_id' => $business->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'status' => 'active'
        ]);

        $response = $this->postJson("/api/businesses/{$business->id}/accounts", [
            'bank_name' => 'Test Bank',
            'account_name' => 'Another Account',
            'account_number' => '1234567890' // Duplicate for same bank
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_number']);

        // Test same account number but different bank (should be allowed)
        $response = $this->postJson("/api/businesses/{$business->id}/accounts", [
            'bank_name' => 'Different Bank',
            'account_name' => 'Another Account',
            'account_number' => '1234567890'
        ]);
        $response->assertStatus(201);
    }

    public function test_cannot_access_account_from_different_business()
    {
        $owner1 = $this->createUser();
        $this->createVerifiedProfile($owner1);
        $business1 = $this->createVerifiedBusiness($owner1);

        $owner2 = $this->createUser();
        $this->createVerifiedProfile($owner2);
        $business2 = $this->createVerifiedBusiness($owner2);

        $account = Account::create([
            'business_id' => $business1->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'status' => 'active'
        ]);

        $this->actingAs($owner2, 'sanctum');

        // Try to view account
        $response = $this->getJson("/api/businesses/{$business1->id}/accounts/{$account->id}");
        $response->assertStatus(403);

        // Try to delete account
        $response = $this->deleteJson("/api/businesses/{$business1->id}/accounts/{$account->id}");
        $response->assertStatus(403);
    }
} 