<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Dispute;
use App\Models\Guarantee;
use App\Models\Verdict;
use App\Models\Restitution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestitutionTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;
    private User $buyer;
    private User $arbitrator;
    private Guarantee $guarantee;
    private Dispute $dispute;
    private Verdict $verdict;
    private Restitution $restitution;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->seller = User::factory()->create(['role' => 'user', 'trust_score' => 100]);
        $this->buyer = User::factory()->create(['role' => 'user']);
        $this->arbitrator = User::factory()->create(['role' => 'arbitrator']);

        // Create guarantee
        $this->guarantee = Guarantee::factory()->create([
            'seller_id' => $this->seller->id,
            'buyer_id' => $this->buyer->id,
            'price' => 1000,
            'status' => 'accepted'
        ]);

        // Create dispute
        $this->dispute = Dispute::factory()->create([
            'guarantee_id' => $this->guarantee->id,
            'initiated_by' => $this->buyer->id,
            'status' => Dispute::STATUS_IN_REVIEW
        ]);

        // Create verdict with full refund
        $this->verdict = Verdict::factory()->create([
            'dispute_id' => $this->dispute->id,
            'guarantee_id' => $this->guarantee->id,
            'arbitrator_id' => $this->arbitrator->id,
            'decision' => 'refund',
            'notes' => 'Full refund required',
            'evidence_reviewed' => ['test' => 'evidence']
        ]);

        // Create restitution
        $this->restitution = Restitution::create([
            'verdict_id' => $this->verdict->id,
            'amount' => 1000,
            'status' => 'pending'
        ]);
    }

    public function test_restitution_is_created_when_verdict_requires_refund()
    {
        $dispute = Dispute::factory()->create([
            'guarantee_id' => $this->guarantee->id,
            'initiated_by' => $this->buyer->id,
            'status' => Dispute::STATUS_IN_REVIEW
        ]);

        $response = $this->actingAs($this->arbitrator)->postJson("/api/disputes/{$dispute->id}/resolve", [
            'decision' => 'refund',
            'notes' => 'Full refund required',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'verdict',
                'restitution'
            ]);

        $this->assertDatabaseHas('restitutions', [
            'verdict_id' => $response->json('verdict.id'),
            'amount' => $this->guarantee->price,
            'status' => 'pending'
        ]);
    }

    public function test_restitution_is_created_with_partial_refund()
    {
        $dispute = Dispute::factory()->create([
            'guarantee_id' => $this->guarantee->id,
            'initiated_by' => $this->buyer->id,
            'status' => Dispute::STATUS_IN_REVIEW
        ]);

        $response = $this->actingAs($this->arbitrator)->postJson("/api/disputes/{$dispute->id}/resolve", [
            'decision' => 'partial_refund',
            'refund_amount' => 500,
            'notes' => 'Partial refund required',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'verdict',
                'restitution'
            ]);

        $this->assertDatabaseHas('restitutions', [
            'verdict_id' => $response->json('verdict.id'),
            'amount' => 500,
            'status' => 'pending'
        ]);
    }

    public function test_seller_can_process_restitution()
    {
        $response = $this->actingAs($this->seller)
            ->postJson("/api/restitutions/{$this->restitution->id}/process", [
                'proof_of_payment' => 'payment_receipt.jpg'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Restitution processed successfully'
            ]);

        $this->assertDatabaseHas('restitutions', [
            'id' => $this->restitution->id,
            'status' => 'processed',
            'proof_of_payment' => 'payment_receipt.jpg'
        ]);
    }

    public function test_buyer_cannot_process_restitution()
    {
        $response = $this->actingAs($this->buyer)
            ->postJson("/api/restitutions/{$this->restitution->id}/process", [
                'proof_of_payment' => 'payment_receipt.jpg'
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseHas('restitutions', [
            'id' => $this->restitution->id,
            'status' => 'pending'
        ]);
    }

    public function test_buyer_can_complete_restitution()
    {
        // First process the restitution
        $this->restitution->update([
            'status' => 'processed',
            'proof_of_payment' => 'payment_receipt.jpg',
            'processed_at' => now()
        ]);

        // Store initial trust score
        $initialTrustScore = $this->seller->trust_score;

        $response = $this->actingAs($this->buyer)
            ->postJson("/api/restitutions/{$this->restitution->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Restitution completed successfully'
            ]);

        $this->assertDatabaseHas('restitutions', [
            'id' => $this->restitution->id,
            'status' => 'completed',
            'completed_by' => $this->buyer->id
        ]);

        // Check if trust score was restored
        $this->seller->refresh();
        $this->assertEquals(min(100, $initialTrustScore + 50), $this->seller->trust_score);
    }

    public function test_arbitrator_can_complete_restitution()
    {
        // First process the restitution
        $this->restitution->update([
            'status' => 'processed',
            'proof_of_payment' => 'payment_receipt.jpg',
            'processed_at' => now()
        ]);

        // Store initial trust score
        $initialTrustScore = $this->seller->trust_score;

        $response = $this->actingAs($this->arbitrator)
            ->postJson("/api/restitutions/{$this->restitution->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Restitution completed successfully'
            ]);

        $this->assertDatabaseHas('restitutions', [
            'id' => $this->restitution->id,
            'status' => 'completed',
            'completed_by' => $this->arbitrator->id
        ]);

        // Check if trust score was restored
        $this->seller->refresh();
        $this->assertEquals(min(100, $initialTrustScore + 50), $this->seller->trust_score);
    }

    public function test_seller_cannot_complete_restitution()
    {
        // First process the restitution
        $this->restitution->update([
            'status' => 'processed',
            'proof_of_payment' => 'payment_receipt.jpg',
            'processed_at' => now()
        ]);

        $response = $this->actingAs($this->seller)
            ->postJson("/api/restitutions/{$this->restitution->id}/complete");

        $response->assertStatus(403);

        $this->assertDatabaseHas('restitutions', [
            'id' => $this->restitution->id,
            'status' => 'processed'
        ]);
    }

    public function test_cannot_complete_unprocessed_restitution()
    {
        $response = $this->actingAs($this->buyer)
            ->postJson("/api/restitutions/{$this->restitution->id}/complete");

        $response->assertStatus(422);

        $this->assertDatabaseHas('restitutions', [
            'id' => $this->restitution->id,
            'status' => 'pending'
        ]);
    }

    public function test_cannot_process_completed_restitution()
    {
        // First process and complete the restitution
        $this->restitution->update([
            'status' => 'completed',
            'proof_of_payment' => 'payment_receipt.jpg',
            'processed_at' => now(),
            'completed_at' => now(),
            'completed_by' => $this->buyer->id
        ]);

        $response = $this->actingAs($this->seller)
            ->postJson("/api/restitutions/{$this->restitution->id}/process", [
                'proof_of_payment' => 'new_receipt.jpg'
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('restitutions', [
            'id' => $this->restitution->id,
            'status' => 'completed',
            'proof_of_payment' => 'payment_receipt.jpg'
        ]);
    }

    public function test_dispute_resolution_timing_rules()
    {
        // Create a new dispute
        $dispute = Dispute::factory()->create([
            'guarantee_id' => $this->guarantee->id,
            'initiated_by' => $this->buyer->id,
            'status' => Dispute::STATUS_PENDING,
            'created_at' => now()
        ]);

        // Try to resolve immediately - should fail
        $response = $this->actingAs($this->arbitrator)
            ->postJson("/api/disputes/{$dispute->id}/resolve", [
                'decision' => 'refund',
                'notes' => 'Full refund required'
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Dispute cannot be resolved at this stage'
            ]);

        // Travel through time to simulate passage of 5 days
        \Illuminate\Support\Facades\Date::setTestNow(now()->addDays(5));

        // Now should be able to resolve
        $response = $this->actingAs($this->arbitrator)
            ->postJson("/api/disputes/{$dispute->id}/resolve", [
                'decision' => 'refund',
                'notes' => 'Full refund required'
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'verdict',
                'restitution'
            ]);

        // Reset time
        \Illuminate\Support\Facades\Date::setTestNow();
    }

    public function test_dispute_moves_to_review_on_defense()
    {
        // Create a new dispute
        $dispute = Dispute::factory()->create([
            'guarantee_id' => $this->guarantee->id,
            'initiated_by' => $this->buyer->id,
            'status' => Dispute::STATUS_PENDING
        ]);

        // Ensure the seller is properly associated with the guarantee
        $this->guarantee->update(['seller_id' => $this->seller->id]);

        // Submit defense
        $response = $this->actingAs($this->seller)
            ->postJson("/api/disputes/{$dispute->id}/defense", [
                'defense_description' => 'Valid defense',
                'defense' => [
                    'proof' => 'defense.pdf'
                ]
            ]);

        $response->assertStatus(200);

        // Verify status changed to in_review
        $this->assertDatabaseHas('disputes', [
            'id' => $dispute->id,
            'status' => Dispute::STATUS_IN_REVIEW
        ]);

        // Should be able to resolve immediately after defense
        $response = $this->actingAs($this->arbitrator)
            ->postJson("/api/disputes/{$dispute->id}/resolve", [
                'decision' => 'no_refund',
                'notes' => 'Valid defense provided'
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'verdict'
            ]);
    }
} 