<?php

namespace Database\Seeders;

use App\Models\Dispute;
use App\Models\Guarantee;
use App\Models\User;
use App\Models\Verdict;
use Illuminate\Database\Seeder;

class DisputeSeeder extends Seeder
{
    public function run(): void
    {
        $arbitrator = User::where('role', 'arbitrator')->first();
        $guarantees = Guarantee::where('status', 'accepted')->take(3)->get();

        // Create disputes for some guarantees
        $guarantees->each(function ($guarantee) use ($arbitrator) {
            $dispute = Dispute::create([
                'guarantee_id' => $guarantee->id,
                'initiated_by' => $guarantee->buyer_id,
                'reason' => 'Delayed delivery',
                'description' => 'The service was not delivered on the agreed date',
                'evidence' => [
                    'timeline' => 'documents/evidence/timeline.pdf',
                    'communication' => 'documents/evidence/emails.pdf'
                ],
                'status' => 'resolved',
                'resolution_notes' => 'Dispute resolved with partial refund',
                'resolved_by' => $arbitrator->id,
                'resolved_at' => now(),
            ]);

            // Create verdict for the dispute
            Verdict::create([
                'dispute_id' => $dispute->id,
                'guarantee_id' => $guarantee->id,
                'arbitrator_id' => $arbitrator->id,
                'decision' => 'partial_refund',
                'refund_amount' => $guarantee->price * 0.3, // 30% refund
                'notes' => 'Due to the delay, a partial refund is warranted',
                'evidence_reviewed' => [
                    'timeline' => true,
                    'communication' => true
                ],
                'decided_at' => now(),
            ]);

            // Update guarantee status
            $guarantee->update(['status' => 'disputed']);
        });
    }
} 