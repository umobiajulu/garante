<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuaranteeFactory extends Factory
{
    public function definition()
    {
        return [
            'seller_id' => User::factory(),
            'buyer_id' => User::factory(),
            'business_id' => Business::factory(),
            'service_description' => $this->faker->paragraph(),
            'price' => $this->faker->randomFloat(2, 100, 10000),
            'terms' => [
                'delivery_date' => now()->addDays(30)->toDateString(),
                'payment_terms' => '50% upfront, 50% on completion',
                'deliverables' => ['Item 1', 'Item 2', 'Item 3']
            ],
            'status' => 'accepted',
            'progress' => 0,
            'accepted_at' => now()
        ];
    }
} 