<?php

namespace Tests\Feature\Billing;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\Fee;
use App\Models\Invoice;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private Plan $plan;

    private Member $member;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 09:00:00');

        $this->organization = Organization::factory()->create();
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id, 'code' => 'BOLE']);
        $this->plan = Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Monthly', 'am' => 'ወርሃዊ'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 250000]);
        $this->member = Member::factory()->at($this->bole)->create();
        $this->reception = User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sell(int $paid = 250000, string $method = 'cash', ?Member $member = null): string
    {
        Sanctum::actingAs($this->reception);

        return $this->postJson("/api/v1/branches/{$this->bole->ulid}/sales", [
            'member' => ($member ?? $this->member)->ulid,
            'plan' => $this->plan->ulid,
            'payment' => ['method' => $method, 'amount_cents' => $paid],
        ])->assertCreated()->json('data.id');
    }

    public function test_manager_voids_an_invoice_and_the_number_stays_used(): void
    {
        $invoice = $this->sell();

        $this->postJson("/api/v1/invoices/{$invoice}/void", ['reason' => 'Wrong plan'])->assertForbidden();

        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Manager)->create());
        $this->postJson("/api/v1/invoices/{$invoice}/void", ['reason' => 'Wrong plan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.balance_cents', 0)
            ->assertJsonPath('data.payments.0.status', 'refunded')
            ->assertJsonPath('data.memberships.0.status', 'cancelled')
            ->assertJsonPath('data.void_reason', 'Wrong plan');

        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.voided']);
        $this->postJson("/api/v1/invoices/{$invoice}/payments", ['method' => 'cash', 'amount_cents' => 1])->assertJsonValidationErrors('amount_cents');

        $this->sell();
        $this->assertSame(['BOLE-2026-000001', 'BOLE-2026-000002'], Invoice::orderBy('id')->pluck('number')->all());
    }

    public function test_listing_is_limited_to_branches_the_user_handles_money_for(): void
    {
        $this->sell();
        $piassa = Branch::factory()->create(['organization_id' => $this->organization->id]);

        $this->getJson('/api/v1/invoices')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.member.id', $this->member->ulid);
        $this->getJson('/api/v1/invoices?status=unpaid')->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/invoices?member={$this->member->ulid}")->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/invoices?date=2026-10-27')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/invoices?date=2026-10-26')->assertJsonCount(0, 'data');

        $other = User::factory()->staffAt($piassa, BranchRole::Receptionist)->create();
        Sanctum::actingAs($other);
        $this->getJson('/api/v1/invoices')->assertJsonCount(0, 'data');
        $invoice = Invoice::first();
        $this->getJson("/api/v1/invoices/{$invoice->ulid}")->assertForbidden();

        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Trainer)->create());
        $this->getJson('/api/v1/invoices')->assertForbidden();
    }

    public function test_other_gyms_invoices_are_not_found(): void
    {
        $invoice = $this->sell();
        $stranger = User::factory()->owner()->create();
        Sanctum::actingAs($stranger);

        $this->getJson("/api/v1/invoices/{$invoice}")->assertNotFound();
        $this->getJson("/api/v1/invoices/{$invoice}/receipt")->assertNotFound();
    }

    public function test_receipt_is_a_bilingual_pdf_in_two_sizes(): void
    {
        Fee::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Registration', 'am' => 'ምዝገባ'], 'kind' => 'registration', 'amount_cents' => 50000]);
        $invoice = $this->sell(100000);

        $a5 = $this->get("/api/v1/invoices/{$invoice}/receipt")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $a5->getContent());
        // The Ethiopic font is embedded for the Amharic labels.
        $this->assertStringContainsString('AbyssinicaSIL', $a5->getContent());

        $thermal = $this->get("/api/v1/invoices/{$invoice}/receipt?format=thermal")->assertOk();
        $this->assertStringStartsWith('%PDF', $thermal->getContent());
        $this->getJson("/api/v1/invoices/{$invoice}/receipt?format=letter")->assertJsonValidationErrors('format');
    }

    public function test_takings_add_up_by_method_for_the_day(): void
    {
        $this->sell(250000, 'cash');
        $this->sell(250000, 'pos_card', Member::factory()->at($this->bole)->create());
        $this->sell(100000, 'cash', Member::factory()->at($this->bole)->create());

        Carbon::setTestNow('2026-10-28 09:00:00');
        $this->sell(250000, 'cash', Member::factory()->at($this->bole)->create());

        $this->getJson("/api/v1/branches/{$this->bole->ulid}/takings?date=2026-10-27")
            ->assertOk()
            ->assertJsonPath('total_cents', 600000)
            ->assertJsonPath('by_method.0.method', 'cash')
            ->assertJsonPath('by_method.0.amount_cents', 350000)
            ->assertJsonPath('by_method.0.count', 2)
            ->assertJsonPath('by_method.1.amount_cents', 250000)
            ->assertJsonPath('invoices_count', 3)
            ->assertJsonPath('unpaid_cents', 150000);

        $this->getJson("/api/v1/branches/{$this->bole->ulid}/takings")->assertJsonPath('date', '2026-10-28')->assertJsonPath('total_cents', 250000);

        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Trainer)->create());
        $this->getJson("/api/v1/branches/{$this->bole->ulid}/takings")->assertForbidden();
    }

    public function test_member_memberships_and_expiry(): void
    {
        $this->sell();
        $this->getJson("/api/v1/members/{$this->member->ulid}/memberships")
            ->assertOk()
            ->assertJsonPath('data.0.plan.name', 'Monthly')
            ->assertJsonPath('data.0.state', 'current')
            ->assertJsonPath('data.0.invoice.number', 'BOLE-2026-000001');

        Carbon::setTestNow('2026-11-26 01:00:00');
        $this->artisan('memberships:expire')->assertSuccessful();
        $this->assertSame('expired', Membership::first()->status);
    }
}
