<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Feature\Traits\TestHelpers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Dispute;

class GuaranteeAndArbitrationTest extends TestCase
{
    use TestHelpers, RefreshDatabase;

    protected $seller;
    protected $buyer;
    protected $arbitrator;
    protected $sellerBusiness;
    protected $buyerBusiness;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        // Create users
        $this->seller = $this->createUser(['email' => 'seller@example.com']);
        $this->buyer = $this->createUser(['email' => 'buyer@example.com']);
        $this->arbitrator = $this->createArbitrator();

        // Create profiles
        $sellerProfile = $this->createVerifiedProfile($this->seller);
        $buyerProfile = $this->createVerifiedProfile($this->buyer);

        // Create businesses
        $this->sellerBusiness = $this->createVerifiedBusiness($this->seller);
        $this->buyerBusiness = $this->createVerifiedBusiness($this->buyer);

        // Ensure seller is a member of their business
        $this->sellerBusiness->members()->syncWithoutDetaching([$sellerProfile->id => ['role' => 'owner']]);
    }

    protected function createGuarantee()
    {
        $this->actingAs($this->seller);

        $response = $this->postJson('/api/guarantees', [
            'business_id' => $this->sellerBusiness->id,
            'buyer_id' => $this->buyer->id,
            'service_description' => 'Test service',
            'price' => 100000,
            'terms' => [
                'delivery_date' => now()->addDays(7)->toDateString(),
                'payment_terms' => '50% upfront, 50% on completion',
                'deliverables' => ['Item 1', 'Item 2']
            ]
        ]);

        if ($response->status() !== 201) {
            dd($response->json());
        }

        return json_decode($response->getContent())->guarantee;
    }

    public function test_complete_guarantee_lifecycle()
    {
        $this->actingAs($this->seller);

        // 1. Seller creates guarantee
        $response = $this->postJson('/api/guarantees', [
            'business_id' => $this->sellerBusiness->id,
            'buyer_id' => $this->buyer->id,
            'service_description' => 'Test service',
            'price' => 100000,
            'terms' => [
                'delivery_date' => now()->addDays(7)->toDateString(),
                'payment_terms' => '50% upfront, 50% on completion',
                'deliverables' => ['Item 1', 'Item 2']
            ]
        ]);

        if ($response->status() !== 201) {
            dd($response->json());
        }

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'guarantee' => [
                        'id',
                        'seller_id',
                        'business_id',
                        'buyer_id',
                        'service_description',
                        'price',
                        'terms',
                        'status'
                    ]
                ]);

        $guaranteeId = $response->json('guarantee.id');

        // 2. Buyer accepts guarantee
        $this->actingAs($this->buyer);
        $response = $this->postJson("/api/guarantees/{$guaranteeId}/accept");

        if ($response->status() !== 200) {
            dd([
                'response' => $response->json(),
                'buyer_id' => $this->buyer->id,
                'guarantee_id' => $guaranteeId,
                'seller_id' => $this->seller->id
            ]);
        }

        $response->assertOk();

        // 3. Create dispute
        $response = $this->postJson('/api/disputes', [
            'guarantee_id' => $guaranteeId,
            'reason' => 'Service not delivered on time',
            'description' => 'The service was not delivered by the agreed date and caused significant delays',
            'evidence' => [
                'timeline' => 'documents/evidence/timeline.pdf',
                'communication' => 'documents/evidence/emails.pdf'
            ]
        ]);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'message',
                    'dispute' => [
                        'id',
                        'guarantee_id',
                        'initiated_by',
                        'reason',
                        'description',
                        'evidence',
                        'status'
                    ]
                ]);

        $disputeId = $response->json('dispute.id');

        // Submit defense
        $this->actingAs($this->seller);
        $response = $this->postJson("/api/disputes/{$disputeId}/defense", [
            'defense_description' => 'The delay was caused by factors beyond our control',
            'defense' => [
                'proof_of_attempt' => 'documents/defense/attempts.pdf',
                'external_factors' => 'documents/defense/factors.pdf'
            ]
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'message',
                    'dispute' => [
                        'id',
                        'guarantee_id',
                        'initiated_by',
                        'reason',
                        'description',
                        'evidence',
                        'defense',
                        'defense_description',
                        'status'
                    ]
                ]);

        // Set dispute status to in_review
        $dispute = Dispute::find($disputeId);
        $dispute->update(['status' => Dispute::STATUS_IN_REVIEW]);

        // 4. Arbitrator resolves dispute
        $this->actingAs($this->arbitrator);
        $response = $this->postJson("/api/disputes/{$disputeId}/resolve", [
            'decision' => 'partial_refund',
            'refund_amount' => 50000,
            'notes' => 'Partial refund due to late delivery'
        ]);

        $response->assertOk()
                ->assertJsonStructure([
                    'message',
                    'verdict' => [
                        'id',
                        'dispute_id',
                        'arbitrator_id',
                        'decision',
                        'refund_amount',
                        'notes'
                    ]
                ]);
    }

    public function test_guarantee_validation_rules()
    {
        $this->actingAs($this->seller);

        // Test invalid price
        $response = $this->postJson('/api/guarantees', [
            'business_id' => $this->sellerBusiness->id,
            'buyer_id' => $this->buyer->id,
            'service_description' => 'Test service',
            'price' => -100,
            'terms' => []
        ]);

        $response->assertStatus(422);
    }

    public function test_dispute_validation_rules()
    {
        $this->actingAs($this->buyer);

        // Create a guarantee first
        $guarantee = $this->createGuarantee();

        // Test missing evidence
        $response = $this->postJson('/api/disputes', [
            'guarantee_id' => $guarantee->id,
            'reason' => 'Service not delivered',
            'description' => 'The service was not delivered',
            'evidence' => []
        ]);

        $response->assertStatus(422);
    }
} 