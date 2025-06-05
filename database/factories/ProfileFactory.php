<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    protected $model = Profile::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'nin' => $this->faker->numerify('##########'),
            'bvn' => $this->faker->numerify('###########'),
            'nin_verified' => false,
            'bvn_verified' => false,
            'nin_phone' => $this->faker->phoneNumber,
            'bvn_phone' => $this->faker->phoneNumber,
            'nin_dob' => $this->faker->date(),
            'bvn_dob' => $this->faker->date(),
            'address' => $this->faker->address,
            'state' => $this->faker->state,
            'city' => $this->faker->city,
            'profession' => $this->faker->jobTitle,
            'verification_status' => 'pending',
            'id_document_url' => null,
            'address_document_url' => null,
            'verified_by' => null,
            'verified_at' => null
        ];
    }

    public function verified()
    {
        return $this->state(function (array $attributes) {
            $verifier = User::factory()->create();
            
            return [
                'verification_status' => 'verified',
                'nin_verified' => true,
                'bvn_verified' => true,
                'verified_by' => $verifier->id,
                'verified_at' => now()
            ];
        });
    }
} 