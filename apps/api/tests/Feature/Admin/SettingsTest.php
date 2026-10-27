<?php

namespace Tests\Feature\Admin;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gym.tax.enabled' => false, 'gym.tax.prices_inclusive' => true]);

        $this->organization = Organization::factory()->create();
        $this->branch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        Sanctum::actingAs(User::factory()->owner()->create(['organization_id' => $this->organization->id]));
    }

    private function settings(string $url = '/api/v1/settings'): array
    {
        return $this->getJson($url)->assertOk()->json('settings');
    }

    public function test_defaults_come_from_config(): void
    {
        $settings = $this->settings();

        $this->assertFalse($settings['tax.enabled']);
        $this->assertTrue($settings['tax.prices_inclusive']);
        $this->assertEquals(15, $settings['tax.rate']);
    }

    public function test_owner_switches_vat_on_and_to_exclusive_pricing(): void
    {
        $this->patchJson('/api/v1/settings', ['settings' => ['tax.enabled' => true, 'tax.prices_inclusive' => false]])
            ->assertOk();

        $settings = $this->settings();
        $this->assertTrue($settings['tax.enabled']);
        $this->assertFalse($settings['tax.prices_inclusive']);

        // Writing again updates the same row rather than adding one.
        $this->patchJson('/api/v1/settings', ['settings' => ['tax.enabled' => false]]);
        $this->assertDatabaseCount('settings', 2);
    }

    public function test_branch_override_wins_and_null_removes_it(): void
    {
        $this->patchJson('/api/v1/settings', ['settings' => ['tax.enabled' => true]]);
        $url = "/api/v1/branches/{$this->branch->ulid}/settings";

        $this->patchJson($url, ['settings' => ['tax.enabled' => false]])
            ->assertOk()
            ->assertJsonPath('overridden', ['tax.enabled']);
        $this->assertFalse($this->settings($url)['tax.enabled']);

        $this->patchJson($url, ['settings' => ['tax.enabled' => null]])->assertJsonPath('overridden', []);
        $this->assertTrue($this->settings($url)['tax.enabled']);
    }

    public function test_values_are_validated_and_unknown_keys_ignored(): void
    {
        $this->patchJson('/api/v1/settings', ['settings' => ['tax.rate' => 150]])->assertUnprocessable();
        $this->patchJson('/api/v1/settings', ['settings' => ['tax.name' => 'GST']])->assertUnprocessable();

        $this->patchJson('/api/v1/settings', ['settings' => ['bogus.key' => true, 'tax.rate' => 2]])->assertOk();
        $this->assertDatabaseMissing('settings', ['key' => 'bogus.key']);
        $this->assertEquals(2, $this->settings()['tax.rate']);
    }

    public function test_managers_can_read_but_not_change_money_settings(): void
    {
        Sanctum::actingAs(User::factory()->staffAt($this->branch, BranchRole::Manager)->create());

        $this->getJson('/api/v1/settings')->assertOk();
        $this->patchJson('/api/v1/settings', ['settings' => ['tax.enabled' => true]])->assertForbidden();
        $this->patchJson("/api/v1/branches/{$this->branch->ulid}/settings", ['settings' => ['tax.enabled' => true]])->assertForbidden();
    }
}
