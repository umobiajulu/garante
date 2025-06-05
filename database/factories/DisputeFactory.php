<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\Guarantee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DisputeFactory extends Factory
{
    public function definition()
    {
        return [
            'guarantee_id' => Guarantee::factory(),
            'initiated_by' => User::factory(),
            'reason' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'evidence' => [
                'screenshots' => ['screenshot1.jpg', 'screenshot2.jpg'],
                'documents' => ['document1.pdf']
            ],
            'defense_description' => null,
            'defense' => null,
            'status' => Dispute::STATUS_PENDING,
            'resolution_notes' => null,
            'resolved_by' => null,
            'resolved_at' => null
        ];
    }
} 