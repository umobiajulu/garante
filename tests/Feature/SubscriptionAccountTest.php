<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Traits\TestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\SubscriptionAccount;

class SubscriptionAccountTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_business_owner_can_view_subscription_accounts()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $subscriptionAccount = SubscriptionAccount::create([
            'business_id' => $business->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'status' => 'active',
            'metadata' => ['key' => 'value']
        ]);

        $this->actingAs($owner, 'sanctum');

        $response = $this->getJson("/api/businesses/{$business->id}/subscription-accounts");

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
                        'metadata',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    public function test_business_member_can_view_subscription_accounts()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $member = $this->createUser();
        $memberProfile = $this->createVerifiedProfile($member);
        $business->members()->attach($memberProfile->id, ['role' => 'staff']);

        SubscriptionAccount::create([
            'business_id' => $business->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'status' => 'active',
            'metadata' => ['key' => 'value']
        ]);

        $this->actingAs($member, 'sanctum');

        $response = $this->getJson("/api/businesses/{$business->id}/subscription-accounts");

        $response->assertOk();
    }

    public function test_business_owner_cannot_create_subscription_account()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $this->actingAs($owner, 'sanctum');

        $response = $this->postJson("/api/businesses/{$business->id}/subscription-accounts", [
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'metadata' => ['key' => 'value']
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_subscription_account()
    {
        $admin = $this->createUser(['email' => 'admin@garante.admin']);
        $this->createVerifiedProfile($admin);

        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson("/api/businesses/{$business->id}/subscription-accounts", [
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'metadata' => ['key' => 'value']
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
                    'metadata'
                ]
            ]);

        $this->assertDatabaseHas('subscription_accounts', [
            'business_id' => $business->id,
            'bank_name' => 'Test Bank',
            'account_number' => '1234567890'
        ]);
    }

    public function test_admin_can_update_subscription_account()
    {
        $admin = $this->createUser(['email' => 'admin@garante.admin']);
        $this->createVerifiedProfile($admin);

        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $account = SubscriptionAccount::create([
            'business_id' => $business->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'status' => 'active',
            'metadata' => ['key' => 'value']
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson("/api/businesses/{$business->id}/subscription-accounts/{$account->id}", [
            'status' => 'inactive'
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Subscription account updated successfully',
                'account' => [
                    'status' => 'inactive'
                ]
            ]);

        $this->assertDatabaseHas('subscription_accounts', [
            'id' => $account->id,
            'status' => 'inactive'
        ]);
    }

    public function test_business_owner_cannot_update_subscription_account()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $account = SubscriptionAccount::create([
            'business_id' => $business->id,
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'external_id' => 'ext_123',
            'status' => 'active',
            'metadata' => ['key' => 'value']
        ]);

        $this->actingAs($owner, 'sanctum');

        $response = $this->putJson("/api/businesses/{$business->id}/subscription-accounts/{$account->id}", [
            'status' => 'inactive'
        ]);

        $response->assertStatus(403);
    }

    public function test_non_member_cannot_view_subscription_accounts()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $nonMember = $this->createUser();
        $this->createVerifiedProfile($nonMember);

        $this->actingAs($nonMember, 'sanctum');

        $response = $this->getJson("/api/businesses/{$business->id}/subscription-accounts");

        $response->assertStatus(403);
    }
} 