<?php

namespace Tests\Feature\Billing;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\DiscountRule;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id]);
    }

    private function owner(): User
    {
        return User::factory()->owner()->create(['organization_id' => $this->organization->id]);
    }

    public function test_owner_creates_time_and_visit_plans(): void
    {
        Sanctum::actingAs($this->owner());

        $this->postJson('/api/v1/plans', [
            'name' => ['en' => 'Monthly', 'am' => 'ወርሃዊ'],
            'type' => 'time',
            'duration_days' => 30,
            'visit_count' => 12,
            'price_cents' => 250000,
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Monthly')
            ->assertJsonPath('data.visit_count', null)
            ->assertJsonPath('data.access_scope', 'home_branch');

        $this->withHeader('X-Locale', 'am')->getJson('/api/v1/plans')->assertJsonPath('data.0.name', 'ወርሃዊ');

        $this->postJson('/api/v1/plans', ['name' => ['en' => '10 visits'], 'type' => 'visits', 'price_cents' => 150000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('visit_count');

        $this->postJson('/api/v1/plans', ['name' => ['en' => 'Selected'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 1, 'access_scope' => 'selected'])
            ->assertJsonValidationErrors('branches');

        $this->postJson('/api/v1/plans', [
            'name' => ['en' => 'Bole only'], 'type' => 'time_visits', 'duration_days' => 60, 'visit_count' => 10,
            'price_cents' => 150000, 'access_scope' => 'selected', 'branches' => [$this->bole->ulid],
        ])->assertCreated()->assertJsonPath('data.branches.0.id', $this->bole->ulid);
    }

    public function test_only_owners_change_the_catalog_but_staff_can_read_it(): void
    {
        $plan = Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Monthly'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 250000]);
        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Manager)->create());

        $this->getJson('/api/v1/plans')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/fees')->assertOk();
        $this->patchJson("/api/v1/plans/{$plan->ulid}", ['price_cents' => 1])->assertForbidden();
        $this->postJson('/api/v1/fees', ['name' => ['en' => 'Registration'], 'kind' => 'registration', 'amount_cents' => 50000])->assertForbidden();
        $this->getJson('/api/v1/discount-rules')->assertForbidden();
    }

    public function test_price_change_is_audited_and_archived_plans_disappear(): void
    {
        Sanctum::actingAs($this->owner());
        $plan = Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Monthly'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 250000]);

        $this->patchJson("/api/v1/plans/{$plan->ulid}", ['price_cents' => 280000])->assertOk()->assertJsonPath('data.price_cents', 280000);
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.updated', 'subject_id' => $plan->id]);

        $this->deleteJson("/api/v1/plans/{$plan->ulid}")->assertNoContent();
        $this->getJson('/api/v1/plans')->assertJsonCount(0, 'data');
    }

    public function test_discount_rules_validate_by_kind(): void
    {
        Sanctum::actingAs($this->owner());

        $this->postJson('/api/v1/discount-rules', ['name' => ['en' => 'Student'], 'kind' => 'student', 'value_type' => 'percent', 'value' => 15, 'promo_code' => 'IGNORED'])
            ->assertCreated()
            ->assertJsonPath('data.requires_proof', true)
            ->assertJsonPath('data.promo_code', null);

        $this->postJson('/api/v1/discount-rules', ['name' => ['en' => 'Too much'], 'kind' => 'student', 'value_type' => 'percent', 'value' => 120])
            ->assertJsonValidationErrors('value');

        $this->postJson('/api/v1/discount-rules', ['name' => ['en' => 'Promo'], 'kind' => 'promo', 'value_type' => 'fixed', 'value' => 50000])
            ->assertJsonValidationErrors('promo_code');

        $this->postJson('/api/v1/discount-rules', ['name' => ['en' => 'Promo'], 'kind' => 'promo', 'value_type' => 'fixed', 'value' => 50000, 'promo_code' => 'bole500'])
            ->assertCreated()
            ->assertJsonPath('data.promo_code', 'BOLE500');

        $this->postJson('/api/v1/discount-rules', ['name' => ['en' => 'Again'], 'kind' => 'promo', 'value_type' => 'fixed', 'value' => 100, 'promo_code' => 'BOLE500'])
            ->assertJsonValidationErrors('promo_code');

        // The same code is free in another gym.
        $other = Organization::factory()->create();
        DiscountRule::create(['organization_id' => $other->id, 'name' => ['en' => 'X'], 'kind' => 'promo', 'value_type' => 'fixed', 'value' => 1, 'promo_code' => 'OTHER1']);
        $this->postJson('/api/v1/discount-rules', ['name' => ['en' => 'Mine'], 'kind' => 'promo', 'value_type' => 'fixed', 'value' => 1, 'promo_code' => 'OTHER1'])->assertCreated();
    }

    public function test_other_gyms_plans_are_invisible(): void
    {
        $other = Organization::factory()->create();
        $plan = Plan::create(['organization_id' => $other->id, 'name' => ['en' => 'Theirs'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 1]);
        Sanctum::actingAs($this->owner());

        $this->getJson('/api/v1/plans')->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/plans/{$plan->ulid}", ['price_cents' => 2])->assertNotFound();
    }
}
