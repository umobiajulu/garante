<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->company(),
            'registration_number' => 'REG' . $this->faker->unique()->numerify('######'),
            'business_type' => $this->faker->randomElement(['sole_proprietorship', 'partnership', 'limited_company']),
            'address' => $this->faker->address(),
            'state' => $this->faker->state(),
            'city' => $this->faker->city(),
            'owner_id' => User::factory(),
            'verification_status' => 'verified',
            'registration_document_url' => 'documents/business/test.pdf',
            'verified_by' => User::factory()->state(['role' => 'arbitrator']),
            'verified_at' => now(),
            'trust_score' => 100
        ];
    }
} 