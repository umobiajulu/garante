<?php

namespace Tests\Feature;

use Tests\TestCase;
use Carbon\Carbon;
use Tests\Feature\Traits\TestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Subscription;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private const SUBSCRIPTION_RATE = 5000.00;

    public function test_business_owner_can_view_subscriptions()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $subscription = Subscription::create([
            'business_id' => $business->id,
            'duration_months' => 12,
            'amount' => self::SUBSCRIPTION_RATE * 12,
            'start_date' => now(),
            'end_date' => now()->addMonths(12),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum');

        $response = $this->getJson("/api/businesses/{$business->id}/subscriptions");

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'subscriptions' => [
                    '*' => [
                        'id',
                        'business_id',
                        'duration_months',
                        'amount',
                        'start_date',
                        'end_date',
                        'created_by',
                        'notes',
                        'created_at',
                        'updated_at'
                    ]
                ]
            ]);
    }

    public function test_business_member_can_view_subscriptions()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $member = $this->createUser();
        $memberProfile = $this->createVerifiedProfile($member);
        $business->members()->attach($memberProfile->id, ['role' => 'staff']);

        Subscription::create([
            'business_id' => $business->id,
            'duration_months' => 12,
            'amount' => self::SUBSCRIPTION_RATE * 12,
            'start_date' => now(),
            'end_date' => now()->addMonths(12),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($member, 'sanctum');

        $response = $this->getJson("/api/businesses/{$business->id}/subscriptions");

        $response->assertOk();
    }

    public function test_business_owner_cannot_create_subscription()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $this->actingAs($owner, 'sanctum');

        $response = $this->postJson("/api/businesses/{$business->id}/subscriptions", [
            'duration_months' => 12,
            'notes' => 'Test subscription'
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_create_subscription()
    {
        $admin = $this->createUser(['email' => 'admin@garante.admin']);
        $this->createVerifiedProfile($admin);

        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson("/api/businesses/{$business->id}/subscriptions", [
            'duration_months' => 12,
            'notes' => 'Test subscription'
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'subscription' => [
                    'id',
                    'business_id',
                    'duration_months',
                    'amount',
                    'start_date',
                    'end_date',
                    'created_by',
                    'notes'
                ]
            ]);

        $this->assertDatabaseHas('subscriptions', [
            'business_id' => $business->id,
            'duration_months' => 12,
            'amount' => self::SUBSCRIPTION_RATE * 12,
        ]);
    }

    public function test_subscription_dates_are_calculated_correctly_for_first_subscription()
    {
        $admin = $this->createUser(['email' => 'admin@garante.admin']);
        $this->createVerifiedProfile($admin);

        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $this->actingAs($admin, 'sanctum');

        $beforeCreate = now();

        $response = $this->postJson("/api/businesses/{$business->id}/subscriptions", [
            'duration_months' => 6,
            'notes' => 'First subscription'
        ]);

        $response->assertStatus(201);
        
        $subscription = Subscription::first();
        
        // Start date should be around now
        $this->assertTrue(
            $beforeCreate->copy()->subMinute()->lte($subscription->start_date) &&
            now()->addMinute()->gte($subscription->start_date)
        );
        
        // End date should be 6 months from start
        $this->assertTrue(
            $subscription->start_date->copy()->addMonths(6)->equalTo($subscription->end_date)
        );
    }

    public function test_subscription_dates_are_calculated_correctly_for_subsequent_subscription()
    {
        $admin = $this->createUser(['email' => 'admin@garante.admin']);
        $this->createVerifiedProfile($admin);

        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        // Create first subscription
        $firstSubscription = Subscription::create([
            'business_id' => $business->id,
            'duration_months' => 6,
            'amount' => self::SUBSCRIPTION_RATE * 6,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum');

        // Create second subscription
        $response = $this->postJson("/api/businesses/{$business->id}/subscriptions", [
            'duration_months' => 12,
            'notes' => 'Second subscription'
        ]);

        $response->assertStatus(201);
        
        $secondSubscription = Subscription::latest('id')->first();
        
        // Start date should be the end date of the first subscription
        $this->assertTrue($firstSubscription->end_date->equalTo($secondSubscription->start_date));
        
        // End date should be 12 months from start
        $this->assertTrue(
            $secondSubscription->start_date->copy()->addMonths(12)->equalTo($secondSubscription->end_date)
        );
    }

    public function test_admin_can_delete_subscription()
    {
        $admin = $this->createUser(['email' => 'admin@garante.admin']);
        $this->createVerifiedProfile($admin);

        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $subscription = Subscription::create([
            'business_id' => $business->id,
            'duration_months' => 12,
            'amount' => self::SUBSCRIPTION_RATE * 12,
            'start_date' => now(),
            'end_date' => now()->addMonths(12),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum');

        $response = $this->deleteJson("/api/businesses/{$business->id}/subscriptions/{$subscription->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
    }

    public function test_business_owner_cannot_delete_subscription()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $subscription = Subscription::create([
            'business_id' => $business->id,
            'duration_months' => 12,
            'amount' => self::SUBSCRIPTION_RATE * 12,
            'start_date' => now(),
            'end_date' => now()->addMonths(12),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner, 'sanctum');

        $response = $this->deleteJson("/api/businesses/{$business->id}/subscriptions/{$subscription->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id]);
    }

    public function test_subscription_validation_rules()
    {
        $admin = $this->createUser(['email' => 'admin@garante.admin']);
        $this->createVerifiedProfile($admin);

        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $this->actingAs($admin, 'sanctum');

        // Test duration_months validation
        $response = $this->postJson("/api/businesses/{$business->id}/subscriptions", [
            'duration_months' => 0,
            'notes' => 'Test subscription'
        ]);
        $response->assertStatus(422);

        $response = $this->postJson("/api/businesses/{$business->id}/subscriptions", [
            'duration_months' => 61,
            'notes' => 'Test subscription'
        ]);
        $response->assertStatus(422);

        $response = $this->postJson("/api/businesses/{$business->id}/subscriptions", [
            'duration_months' => 'invalid',
            'notes' => 'Test subscription'
        ]);
        $response->assertStatus(422);

        // Test notes validation
        $response = $this->postJson("/api/businesses/{$business->id}/subscriptions", [
            'duration_months' => 12,
            'notes' => str_repeat('a', 1001) // More than 1000 characters
        ]);
        $response->assertStatus(422);
    }

    public function test_non_member_cannot_view_subscriptions()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $nonMember = $this->createUser();
        $this->createVerifiedProfile($nonMember);

        $this->actingAs($nonMember, 'sanctum');

        $response = $this->getJson("/api/businesses/{$business->id}/subscriptions");

        $response->assertStatus(403);
    }
}
