<?php

namespace Database\Factories;

use App\Models\Dispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class VerdictFactory extends Factory
{
    public function definition()
    {
        $dispute = Dispute::factory()->create();
        
        return [
            'dispute_id' => $dispute->id,
            'guarantee_id' => $dispute->guarantee_id,
            'arbitrator_id' => User::factory()->state(['role' => 'arbitrator']),
            'decision' => $this->faker->randomElement(['refund', 'partial_refund', 'no_refund']),
            'refund_amount' => null,
            'notes' => $this->faker->paragraph(),
            'evidence_reviewed' => [
                'evidence' => [
                    'screenshots' => ['screenshot1.jpg'],
                    'documents' => ['document1.pdf']
                ],
                'defense' => null
            ],
            'decided_at' => now()
        ];
    }
} 