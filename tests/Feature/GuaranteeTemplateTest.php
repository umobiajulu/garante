<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Profile;
use App\Models\Business;
use App\Models\GuaranteeTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GuaranteeTemplateTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private $owner;
    private $member;
    private $business;
    private $ownerProfile;
    private $memberProfile;

    protected function setUp(): void
    {
        parent::setUp();

        // Create business owner
        $this->owner = User::factory()->create();
        $this->ownerProfile = Profile::factory()->create([
            'user_id' => $this->owner->id,
            'verification_status' => 'verified'
        ]);

        // Create business member
        $this->member = User::factory()->create();
        $this->memberProfile = Profile::factory()->create([
            'user_id' => $this->member->id,
            'verification_status' => 'verified'
        ]);

        // Create business
        $this->business = Business::factory()->create([
            'owner_id' => $this->owner->id,
            'verification_status' => 'verified'
        ]);

        // Add member to business
        $this->business->members()->attach($this->memberProfile->id, ['role' => 'staff']);
    }

    #[Test]
    public function only_business_owner_can_create_template()
    {
        $templateData = [
            'name' => 'Test Template',
            'service_description' => 'Test Service Description',
            'price' => 1000.00,
            'terms' => ['Term 1', 'Term 2'],
            'expires_in_days' => 30
        ];

        // Test with non-authenticated user
        $response = $this->postJson("/api/businesses/{$this->business->id}/templates", $templateData);
        $response->assertStatus(401);

        // Test with authenticated member (non-owner)
        $response = $this->actingAs($this->member)
            ->postJson("/api/businesses/{$this->business->id}/templates", $templateData);
        $response->assertStatus(403);

        // Test with business owner
        $response = $this->actingAs($this->owner)
            ->postJson("/api/businesses/{$this->business->id}/templates", $templateData);
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'template' => [
                    'id',
                    'business_id',
                    'created_by',
                    'name',
                    'service_description',
                    'price',
                    'terms',
                    'expires_in_days',
                    'created_at',
                    'updated_at'
                ]
            ]);

        $this->assertDatabaseHas('guarantee_templates', [
            'business_id' => $this->business->id,
            'created_by' => $this->owner->id,
            'name' => $templateData['name']
        ]);
    }

    #[Test]
    public function business_members_can_view_templates()
    {
        // Create a template
        $template = GuaranteeTemplate::factory()->create([
            'business_id' => $this->business->id,
            'created_by' => $this->owner->id
        ]);

        // Test with non-authenticated user
        $response = $this->getJson("/api/businesses/{$this->business->id}/templates");
        $response->assertStatus(401);

        // Test with business member
        $response = $this->actingAs($this->member)
            ->getJson("/api/businesses/{$this->business->id}/templates");
        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => [
                    'id',
                    'business_id',
                    'created_by',
                    'name',
                    'service_description',
                    'price',
                    'terms',
                    'expires_in_days'
                ]
            ]);

        // Test with business owner
        $response = $this->actingAs($this->owner)
            ->getJson("/api/businesses/{$this->business->id}/templates");
        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    #[Test]
    public function template_validation_rules_are_enforced()
    {
        $this->actingAs($this->owner);

        $response = $this->postJson("/api/businesses/{$this->business->id}/templates", []);
        $response->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'name' => ['The name field is required.'],
                    'service_description' => ['The service description field is required.'],
                    'price' => ['The price field is required.'],
                    'terms' => ['The terms field is required.']
                ]
            ]);

        $response = $this->postJson("/api/businesses/{$this->business->id}/templates", [
            'name' => '',
            'service_description' => '',
            'price' => -1,
            'terms' => 'not-an-array',
            'expires_in_days' => 0
        ]);
        $response->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'name' => ['The name field is required.'],
                    'service_description' => ['The service description field is required.'],
                    'price' => ['The price field must be at least 0.'],
                    'terms' => ['The terms field must be an array.'],
                    'expires_in_days' => ['The expires in days field must be at least 1.']
                ]
            ]);
    }

    #[Test]
    public function only_business_owner_can_update_template()
    {
        $template = GuaranteeTemplate::factory()->create([
            'business_id' => $this->business->id,
            'created_by' => $this->owner->id
        ]);

        $updateData = [
            'name' => 'Updated Template',
            'service_description' => 'Updated Description',
            'price' => 2000.00,
            'terms' => ['Updated Term 1', 'Updated Term 2'],
            'expires_in_days' => 60
        ];

        // Test with non-authenticated user
        $response = $this->putJson("/api/businesses/{$this->business->id}/templates/{$template->id}", $updateData);
        $response->assertStatus(401);

        // Test with business member
        $response = $this->actingAs($this->member)
            ->putJson("/api/businesses/{$this->business->id}/templates/{$template->id}", $updateData);
        $response->assertStatus(403);

        // Test with business owner
        $response = $this->actingAs($this->owner)
            ->putJson("/api/businesses/{$this->business->id}/templates/{$template->id}", $updateData);
        $response->assertStatus(200)
            ->assertJsonFragment([
                'name' => 'Updated Template',
                'price' => '2000.00'
            ]);

        $this->assertDatabaseHas('guarantee_templates', [
            'id' => $template->id,
            'name' => 'Updated Template'
        ]);
    }

    #[Test]
    public function only_business_owner_can_delete_template()
    {
        $template = GuaranteeTemplate::factory()->create([
            'business_id' => $this->business->id,
            'created_by' => $this->owner->id
        ]);

        // Test with non-authenticated user
        $response = $this->deleteJson("/api/businesses/{$this->business->id}/templates/{$template->id}");
        $response->assertStatus(401);

        // Test with business member (non-owner)
        $response = $this->actingAs($this->member)
            ->deleteJson("/api/businesses/{$this->business->id}/templates/{$template->id}");
        $response->assertStatus(403);

        // Test with business owner
        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/businesses/{$this->business->id}/templates/{$template->id}");
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Template deleted successfully'
            ]);

        $this->assertDatabaseMissing('guarantee_templates', [
            'id' => $template->id
        ]);

        // Test deleting non-existent template
        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/businesses/{$this->business->id}/templates/99999");
        $response->assertStatus(404);

        // Test deleting template from another business
        $otherBusiness = Business::factory()->create();
        $otherTemplate = GuaranteeTemplate::factory()->create([
            'business_id' => $otherBusiness->id
        ]);

        $response = $this->actingAs($this->owner)
            ->deleteJson("/api/businesses/{$this->business->id}/templates/{$otherTemplate->id}");
        $response->assertStatus(404);
    }
} 