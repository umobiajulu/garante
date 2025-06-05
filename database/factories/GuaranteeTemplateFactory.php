<?php

namespace Database\Factories;

use App\Models\GuaranteeTemplate;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GuaranteeTemplateFactory extends Factory
{
    protected $model = GuaranteeTemplate::class;

    public function definition()
    {
        return [
            'business_id' => Business::factory(),
            'created_by' => User::factory(),
            'name' => $this->faker->words(3, true),
            'service_description' => $this->faker->paragraph,
            'price' => $this->faker->randomFloat(2, 100, 10000),
            'terms' => [
                $this->faker->sentence,
                $this->faker->sentence,
                $this->faker->sentence
            ],
            'expires_in_days' => $this->faker->numberBetween(7, 90)
        ];
    }
} 