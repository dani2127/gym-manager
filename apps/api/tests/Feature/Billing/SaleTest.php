<?php

namespace Tests\Feature\Billing;

use App\Enums\BranchRole;
use App\Models\Branch;
use App\Models\DiscountRule;
use App\Models\Fee;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private Plan $monthly;

    private Fee $registration;

    private Member $member;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 09:00:00');

        $this->organization = Organization::factory()->create();
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id, 'code' => 'BOLE']);
        $this->monthly = Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Monthly', 'am' => 'ወርሃዊ'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 250000]);
        $this->registration = Fee::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Registration', 'am' => 'ምዝገባ'], 'kind' => 'registration', 'amount_cents' => 50000]);
        $this->member = Member::factory()->at($this->bole)->create();
        $this->reception = User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create();
        $this->tax(['tax.enabled' => true, 'tax.name' => 'VAT', 'tax.rate' => 15, 'tax.prices_inclusive' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function tax(array $values): void
    {
        app(SettingsRepository::class)->put($this->organization, $values);
    }

    private function sale(array $overrides = []): array
    {
        return [
            'member' => $this->member->ulid,
            'plan' => $this->monthly->ulid,
            'fees' => [$this->registration->ulid],
            ...$overrides,
        ];
    }

    private function url(string $path = ''): string
    {
        return "/api/v1/branches/{$this->bole->ulid}/sales{$path}";
    }

    public function test_cash_sale_with_inclusive_vat_issues_a_paid_invoice_and_activates_the_membership(): void
    {
        Sanctum::actingAs($this->reception);

        $response = $this->postJson($this->url(), $this->sale(['payment' => ['method' => 'cash', 'amount_cents' => 300000]]))
            ->assertCreated()
            ->assertJsonPath('data.number', 'BOLE-2026-000001')
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.subtotal_cents', 300000)
            ->assertJsonPath('data.total_cents', 300000)
            // 2,500 → 326.09 and 500 → 65.22 VAT, carved out of the prices.
            ->assertJsonPath('data.tax_cents', 32609 + 6522)
            ->assertJsonPath('data.net_cents', 300000 - 39131)
            ->assertJsonPath('data.tax_name', 'VAT')
            ->assertJsonPath('data.tax_inclusive', true)
            ->assertJsonPath('data.balance_cents', 0)
            ->assertJsonPath('data.payments.0.method', 'cash')
            ->assertJsonPath('data.memberships.0.status', 'active')
            ->assertJsonPath('data.memberships.0.state', 'current')
            ->assertJsonPath('data.memberships.0.starts_on', '2026-10-27')
            ->assertJsonPath('data.memberships.0.ends_on', '2026-11-25')
            ->assertJsonPath('data.memberships.0.days_left', 30);

        $this->assertSame('Monthly', $response->json('data.items.0.description'));
        $membership = Membership::first();
        $this->assertSame(250000, $membership->plan_snapshot['price_cents']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.issued']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.received']);
    }

    public function test_exclusive_vat_adds_tax_on_top_and_tax_off_charges_none(): void
    {
        Sanctum::actingAs($this->reception);

        $this->tax(['tax.prices_inclusive' => false]);
        $this->postJson($this->url('/quote'), $this->sale(['fees' => []]))
            ->assertOk()
            ->assertJsonPath('subtotal_cents', 250000)
            ->assertJsonPath('tax_cents', 37500)
            ->assertJsonPath('total_cents', 287500)
            ->assertJsonPath('tax.inclusive', false);

        $this->tax(['tax.enabled' => false]);
        $this->postJson($this->url('/quote'), $this->sale(['fees' => []]))
            ->assertJsonPath('tax_cents', 0)
            ->assertJsonPath('total_cents', 250000)
            ->assertJsonPath('tax.enabled', false);
    }

    public function test_branch_tax_override_is_used(): void
    {
        app(SettingsRepository::class)->put($this->organization, ['tax.name' => 'TOT', 'tax.rate' => 2, 'tax.prices_inclusive' => false], $this->bole);
        Sanctum::actingAs($this->reception);

        $this->postJson($this->url('/quote'), $this->sale(['fees' => []]))
            ->assertJsonPath('tax.name', 'TOT')
            ->assertJsonPath('tax_cents', 5000)
            ->assertJsonPath('total_cents', 255000);
    }

    public function test_quote_suggests_registration_fee_only_for_first_membership_and_queues_renewals(): void
    {
        Sanctum::actingAs($this->reception);

        $this->postJson($this->url('/quote'), $this->sale(['fees' => []]))
            ->assertJsonPath('suggested_fees', [$this->registration->ulid])
            ->assertJsonPath('suggested_start', '2026-10-27');

        $this->postJson($this->url(), $this->sale(['payment' => ['method' => 'cash', 'amount_cents' => 300000]]))->assertCreated();

        // Renewing early starts the day after the current membership ends.
        $this->postJson($this->url('/quote'), $this->sale(['fees' => []]))
            ->assertJsonPath('suggested_fees', [])
            ->assertJsonPath('suggested_start', '2026-11-26')
            ->assertJsonPath('starts_on', '2026-11-26')
            ->assertJsonPath('ends_on', '2026-12-25');

        $renewal = $this->postJson($this->url(), $this->sale(['fees' => [], 'payment' => ['method' => 'pos_card', 'amount_cents' => 250000]]))
            ->assertJsonPath('data.number', 'BOLE-2026-000002');
        $this->assertSame('upcoming', $renewal->json('data.memberships.0.state'));
    }

    public function test_group_discounts_follow_the_members_group(): void
    {
        $partner = Member::factory()->at($this->bole)->create();
        $group = MemberGroup::create(['organization_id' => $this->organization->id, 'type' => 'couple', 'name' => 'Us']);
        $group->members()->sync([$this->member->id, $partner->id]);

        $couple = DiscountRule::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Couple'], 'kind' => 'couple', 'value_type' => 'percent', 'value' => 10]);
        $family = DiscountRule::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Family'], 'kind' => 'family', 'value_type' => 'percent', 'value' => 20]);
        DiscountRule::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Old'], 'kind' => 'couple', 'value_type' => 'percent', 'value' => 50, 'valid_until' => '2026-01-01']);
        Sanctum::actingAs($this->reception);

        $this->postJson($this->url('/quote'), $this->sale())
            ->assertJsonCount(1, 'available_discounts')
            ->assertJsonPath('available_discounts.0.id', $couple->ulid)
            ->assertJsonPath('available_discounts.0.amount_cents', 25000)
            ->assertJsonPath('available_discounts.0.automatic', true);

        $this->postJson($this->url('/quote'), $this->sale(['discount' => $family->ulid]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount');

        $this->postJson($this->url(), $this->sale(['discount' => $couple->ulid, 'payment' => ['method' => 'cash', 'amount_cents' => 275000]]))
            ->assertCreated()
            ->assertJsonPath('data.discount_cents', 25000)
            ->assertJsonPath('data.total_cents', 275000)
            ->assertJsonPath('data.items.0.discount.kind', 'couple')
            ->assertJsonPath('data.status', 'paid');
    }

    public function test_student_discount_needs_the_id_checked_and_promo_codes_match(): void
    {
        $student = DiscountRule::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Student'], 'kind' => 'student', 'value_type' => 'percent', 'value' => 15, 'requires_proof' => true]);
        DiscountRule::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Opening'], 'kind' => 'promo', 'value_type' => 'fixed', 'value' => 50000, 'promo_code' => 'BOLE500']);
        DiscountRule::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Other plan'], 'kind' => 'promo', 'value_type' => 'fixed', 'value' => 50000, 'promo_code' => 'YEARLY', 'plan_id' => Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Annual'], 'type' => 'time', 'duration_days' => 365, 'price_cents' => 1])->id]);
        Sanctum::actingAs($this->reception);

        // The preview shows the price; the sale insists on the ID check.
        $this->postJson($this->url('/quote'), $this->sale(['discount' => $student->ulid]))->assertOk()->assertJsonPath('discount_cents', 37500);
        $this->postJson($this->url(), $this->sale(['discount' => $student->ulid]))->assertJsonValidationErrors('discount_proof_checked');
        $this->postJson($this->url(), $this->sale(['discount' => $student->ulid, 'discount_proof_checked' => true]))->assertCreated()->assertJsonPath('data.discount_cents', 37500);

        $this->postJson($this->url('/quote'), $this->sale(['promo_code' => 'bole500 ']))->assertJsonPath('discount_cents', 50000)->assertJsonPath('discount.kind', 'promo');
        $this->postJson($this->url('/quote'), $this->sale(['promo_code' => 'NOPE']))->assertJsonValidationErrors('promo_code');
        $this->postJson($this->url('/quote'), $this->sale(['promo_code' => 'YEARLY']))->assertJsonValidationErrors('promo_code');
    }

    public function test_part_payment_holds_the_membership_until_paid_and_late_payment_starts_it_on_the_day(): void
    {
        Sanctum::actingAs($this->reception);

        $invoice = $this->postJson($this->url(), $this->sale(['payment' => ['method' => 'cash', 'amount_cents' => 100000]]))
            ->assertJsonPath('data.status', 'partially_paid')
            ->assertJsonPath('data.balance_cents', 200000)
            ->assertJsonPath('data.memberships.0.status', 'pending_payment')
            ->json('data.id');

        $this->postJson("/api/v1/invoices/{$invoice}/payments", ['method' => 'cash', 'amount_cents' => 250000])
            ->assertJsonValidationErrors('amount_cents');

        Carbon::setTestNow('2026-10-30 10:00:00');
        $this->postJson("/api/v1/invoices/{$invoice}/payments", ['method' => 'pos_card', 'amount_cents' => 200000, 'reference' => 'SLIP-1182'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonCount(2, 'data.payments')
            ->assertJsonPath('data.memberships.0.status', 'active')
            ->assertJsonPath('data.memberships.0.starts_on', '2026-10-30')
            ->assertJsonPath('data.memberships.0.ends_on', '2026-11-28');

        $this->postJson("/api/v1/invoices/{$invoice}/payments", ['method' => 'cash', 'amount_cents' => 1])
            ->assertJsonValidationErrors('amount_cents');
    }

    public function test_reused_pos_slip_number_is_rejected(): void
    {
        Sanctum::actingAs($this->reception);

        $this->postJson($this->url(), $this->sale(['payment' => ['method' => 'pos_card', 'amount_cents' => 300000, 'reference' => 'SLIP-77']]))->assertCreated();
        $other = Member::factory()->at($this->bole)->create();
        $this->postJson($this->url(), $this->sale(['member' => $other->ulid, 'payment' => ['method' => 'pos_card', 'amount_cents' => 300000, 'reference' => 'SLIP-77']]))
            ->assertJsonValidationErrors('reference');
    }

    public function test_who_can_sell(): void
    {
        $piassa = Branch::factory()->create(['organization_id' => $this->organization->id]);

        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Trainer)->create());
        $this->postJson($this->url('/quote'), $this->sale())->assertForbidden();

        Sanctum::actingAs(User::factory()->staffAt($piassa, BranchRole::Receptionist)->create());
        $this->postJson($this->url(), $this->sale())->assertForbidden();

        // A member from another branch can buy at this one.
        $visitor = Member::factory()->at($piassa)->create();
        Sanctum::actingAs($this->reception);
        $this->postJson($this->url(), $this->sale(['member' => $visitor->ulid]))->assertCreated();

        $visitor->update(['status' => 'banned']);
        $this->postJson($this->url('/quote'), $this->sale(['member' => $visitor->ulid]))->assertJsonValidationErrors('member');
    }

    public function test_inactive_plans_cannot_be_sold_and_buying_reactivates_a_lapsed_member(): void
    {
        Sanctum::actingAs($this->reception);
        $this->member->update(['status' => 'inactive']);

        $this->postJson($this->url(), $this->sale())->assertCreated();
        $this->assertSame('active', $this->member->fresh()->status);

        $this->monthly->update(['is_active' => false]);
        $this->postJson($this->url('/quote'), $this->sale())->assertJsonValidationErrors('plan');
        $this->postJson($this->url(), $this->sale(['plan' => null, 'fees' => []]))->assertJsonValidationErrors('plan');
    }

    public function test_invoice_numbers_are_sequential_per_branch(): void
    {
        $piassa = Branch::factory()->create(['organization_id' => $this->organization->id, 'code' => 'PIASSA']);
        Sanctum::actingAs(User::factory()->owner()->create(['organization_id' => $this->organization->id]));

        $this->postJson($this->url(), $this->sale())->assertJsonPath('data.number', 'BOLE-2026-000001');
        $this->postJson("/api/v1/branches/{$piassa->ulid}/sales", $this->sale())->assertJsonPath('data.number', 'PIASSA-2026-000001');
        $this->postJson($this->url(), $this->sale(['plan' => null]))->assertJsonPath('data.number', 'BOLE-2026-000002');

        Carbon::setTestNow('2027-01-01 08:00:00');
        $this->postJson($this->url(), $this->sale())->assertJsonPath('data.number', 'BOLE-2027-000001');
    }
}
