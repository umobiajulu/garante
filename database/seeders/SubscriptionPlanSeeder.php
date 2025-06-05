<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Bronze',
                'description' => 'Basic plan for small businesses',
                'price' => 4999.99,
                'features' => [
                    'Up to 10 active guarantees',
                    'Basic dispute resolution',
                    'Email support',
                ],
                'trial_period_days' => 7,
            ],
            [
                'name' => 'Silver',
                'description' => 'Advanced plan for growing businesses',
                'price' => 9999.99,
                'features' => [
                    'Up to 50 active guarantees',
                    'Priority dispute resolution',
                    'Email and phone support',
                    'Detailed transaction analytics',
                ],
                'trial_period_days' => 14,
            ],
            [
                'name' => 'Gold',
                'description' => 'Premium plan for established businesses',
                'price' => 19999.99,
                'features' => [
                    'Unlimited active guarantees',
                    'Priority dispute resolution',
                    '24/7 dedicated support',
                    'Advanced analytics and reporting',
                    'Custom contract templates',
                ],
                'trial_period_days' => 30,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
