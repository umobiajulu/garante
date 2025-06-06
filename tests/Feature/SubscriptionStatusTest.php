<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Traits\TestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubscriptionStatusTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_user_without_profile_cannot_check_subscription()
    {
        $user = $this->createUser();
        $this->actingAs($user);

        $response = $this->getJson('/api/subscription-status');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'User does not have a profile',
                'has_active_subscription' => false,
                'details' => null
            ]);
    }

    public function test_user_without_business_cannot_check_subscription()
    {
        $user = $this->createUser();
        $this->createVerifiedProfile($user);
        $this->actingAs($user);

        $response = $this->getJson('/api/subscription-status');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'User is not a member of any business',
                'has_active_subscription' => false,
                'details' => null
            ]);
    }

    public function test_user_with_expired_subscription()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        // Create an expired subscription
        $business->subscriptions()->create([
            'duration_months' => 1,
            'amount' => 5000,
            'start_date' => now()->subMonths(2),
            'end_date' => now()->subMonths(1),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        $response = $this->getJson('/api/subscription-status');

        $response->assertOk()
            ->assertJson([
                'message' => 'Business subscription has expired',
                'has_active_subscription' => false
            ])
            ->assertJsonStructure([
                'message',
                'has_active_subscription',
                'details' => [
                    'business_name',
                    'business_id',
                    'subscription_id',
                    'start_date',
                    'end_date',
                    'duration_months',
                    'amount'
                ]
            ]);
    }

    public function test_user_with_active_subscription()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        // Create an active subscription
        $business->subscriptions()->create([
            'duration_months' => 12,
            'amount' => 60000,
            'start_date' => now(),
            'end_date' => now()->addMonths(12),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($owner);

        $response = $this->getJson('/api/subscription-status');

        $response->assertOk()
            ->assertJson([
                'message' => 'Business has an active subscription',
                'has_active_subscription' => true
            ])
            ->assertJsonStructure([
                'message',
                'has_active_subscription',
                'details' => [
                    'business_name',
                    'business_id',
                    'subscription_id',
                    'start_date',
                    'end_date',
                    'duration_months',
                    'amount'
                ]
            ]);
    }

    public function test_business_member_can_check_subscription()
    {
        $owner = $this->createUser();
        $this->createVerifiedProfile($owner);
        $business = $this->createVerifiedBusiness($owner);

        $member = $this->createUser();
        $memberProfile = $this->createVerifiedProfile($member);
        $business->members()->attach($memberProfile->id, ['role' => 'staff']);

        // Create an active subscription
        $business->subscriptions()->create([
            'duration_months' => 12,
            'amount' => 60000,
            'start_date' => now(),
            'end_date' => now()->addMonths(12),
            'created_by' => $owner->id,
        ]);

        $this->actingAs($member);

        $response = $this->getJson('/api/subscription-status');

        $response->assertOk()
            ->assertJson([
                'message' => 'Business has an active subscription',
                'has_active_subscription' => true
            ])
            ->assertJsonStructure([
                'message',
                'has_active_subscription',
                'details' => [
                    'business_name',
                    'business_id',
                    'subscription_id',
                    'start_date',
                    'end_date',
                    'duration_months',
                    'amount'
                ]
            ]);
    }
} 