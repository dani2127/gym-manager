<?php

namespace Tests\Feature\Billing;

use App\Enums\AccountType;
use App\Enums\BranchRole;
use App\Events\PaymentChanged;
use App\Models\Branch;
use App\Models\DiscountRule;
use App\Models\Fee;
use App\Models\Member;
use App\Models\MemberGroup;
use App\Models\Membership;
use App\Models\NotificationPreference;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\PaymentReviewedNotification;
use App\Support\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Branch $bole;

    private Plan $monthly;

    private Member $member;

    private User $memberUser;

    private User $reception;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 06:00:00');
        Storage::fake('local');
        Event::fake([PaymentChanged::class]);
        Notification::fake();

        $this->organization = Organization::factory()->create();
        $this->bole = Branch::factory()->create(['organization_id' => $this->organization->id, 'code' => 'BOLE']);
        $this->monthly = Plan::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Monthly'], 'type' => 'time', 'duration_days' => 30, 'price_cents' => 250000]);
        Fee::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Registration'], 'kind' => 'registration', 'amount_cents' => 50000]);
        $this->memberUser = User::factory()->create(['organization_id' => $this->organization->id, 'account_type' => AccountType::Member]);
        $this->member = Member::factory()->at($this->bole)->create(['user_id' => $this->memberUser->id]);
        $this->reception = User::factory()->staffAt($this->bole, BranchRole::Receptionist)->create();
        app(SettingsRepository::class)->put($this->organization, ['tax.enabled' => true, 'tax.rate' => 15, 'tax.prices_inclusive' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(): string
    {
        Sanctum::actingAs($this->memberUser);

        return $this->postJson('/api/v1/me/member/orders', ['plan' => $this->monthly->ulid])->assertCreated()->json('data.id');
    }

    private function transfer(string $invoice, array $overrides = [])
    {
        Sanctum::actingAs($this->memberUser);

        return $this->post("/api/v1/me/member/invoices/{$invoice}/transfers", [
            'method' => 'telebirr',
            'reference' => 'DJ81 KQ2 77X',
            'amount_cents' => 300000,
            'proof' => UploadedFile::fake()->image('receipt.png', 900, 1600),
            ...$overrides,
        ], ['Accept' => 'application/json']);
    }

    public function test_owner_sets_the_accounts_members_pay_into(): void
    {
        Sanctum::actingAs(User::factory()->owner()->create(['organization_id' => $this->organization->id]));
        $this->postJson('/api/v1/payment-accounts', ['kind' => 'telebirr', 'account_name' => 'Demo Gym PLC', 'account_number' => '0911 000 001'])->assertCreated()->assertJsonPath('data.bank_name', null);
        $this->postJson('/api/v1/payment-accounts', ['kind' => 'bank', 'account_name' => 'Demo Gym PLC', 'account_number' => '1000123456789'])->assertJsonValidationErrors('bank_name');
        $this->postJson('/api/v1/payment-accounts', ['kind' => 'bank', 'bank_name' => 'Commercial Bank of Ethiopia', 'account_name' => 'Demo Gym PLC', 'account_number' => '1000123456789'])->assertCreated();

        Sanctum::actingAs($this->reception);
        $this->postJson('/api/v1/payment-accounts', ['kind' => 'telebirr', 'account_name' => 'x', 'account_number' => '1'])->assertForbidden();

        Sanctum::actingAs($this->memberUser);
        $this->getJson('/api/v1/me/member/shop')->assertOk()->assertJsonCount(2, 'data.accounts')->assertJsonCount(1, 'data.plans');
    }

    public function test_member_quote_includes_group_discount_registration_fee_and_vat(): void
    {
        $partner = Member::factory()->at($this->bole)->create();
        MemberGroup::create(['organization_id' => $this->organization->id, 'type' => 'couple', 'name' => 'Us'])->members()->sync([$this->member->id, $partner->id]);
        DiscountRule::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Couple'], 'kind' => 'couple', 'value_type' => 'percent', 'value' => 10]);
        DiscountRule::create(['organization_id' => $this->organization->id, 'name' => ['en' => 'Student'], 'kind' => 'student', 'value_type' => 'percent', 'value' => 50, 'requires_proof' => true]);
        Sanctum::actingAs($this->memberUser);

        $this->postJson('/api/v1/me/member/shop/quote', ['plan' => $this->monthly->ulid])
            ->assertOk()
            ->assertJsonPath('data.discount.kind', 'couple')
            ->assertJsonPath('data.total_cents', 225000 + 50000)
            ->assertJsonPath('data.tax.enabled', true)
            ->assertJsonCount(2, 'data.lines');
    }

    public function test_one_open_order_at_a_time_and_it_can_be_cancelled(): void
    {
        $invoice = $this->order();
        $this->postJson('/api/v1/me/member/orders', ['plan' => $this->monthly->ulid])->assertJsonValidationErrors('plan');
        $this->getJson('/api/v1/me/member/shop')->assertJsonPath('data.open_order', $invoice);

        $this->deleteJson("/api/v1/me/member/orders/{$invoice}")->assertNoContent();
        $this->assertSame('cancelled', Membership::first()->status);
        $this->order();
    }

    public function test_transfer_waits_for_the_desk_and_approval_starts_the_membership(): void
    {
        $invoice = $this->order();
        $this->transfer($invoice)
            ->assertCreated()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.payments.0.status', 'awaiting_verification')
            ->assertJsonPath('data.payments.0.reference', 'DJ81KQ277X');
        Event::assertDispatched(PaymentChanged::class, fn ($e) => $e->type === 'submitted');
        $this->transfer($invoice, ['reference' => 'OTHER123'])->assertJsonValidationErrors('reference');

        Sanctum::actingAs($this->reception);
        $queue = $this->getJson('/api/v1/payments/transfers')->assertOk()->assertJsonPath('meta.waiting', 1)->assertJsonPath('data.0.member.id', $this->member->ulid);
        $payment = $queue->json('data.0.id');
        $this->get("/api/v1/payments/{$payment}/proof")->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        NotificationPreference::create(['organization_id' => $this->organization->id, 'user_id' => $this->memberUser->id, 'channel' => 'telegram', 'destination' => '42', 'verified_at' => now()]);
        $this->postJson("/api/v1/payments/{$payment}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.invoice.status', 'paid');

        $this->assertSame('active', Membership::first()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.transfer_approved']);
        Notification::assertSentTo($this->memberUser, PaymentReviewedNotification::class);
        $this->postJson("/api/v1/payments/{$payment}/approve")->assertJsonValidationErrors('payment');
        $this->getJson('/api/v1/payments/transfers?status=reviewed')->assertJsonCount(1, 'data');
    }

    public function test_reused_transfer_numbers_are_blocked_even_after_rejection(): void
    {
        $invoice = $this->order();
        $this->transfer($invoice)->assertCreated();
        $id = Payment::first()->ulid;

        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/payments/{$id}/reject", ['reason' => 'No money received on Telebirr'])
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'No money received on Telebirr');

        // Same number, any spacing or case: refused. A new transfer is fine.
        $this->transfer($invoice, ['reference' => 'dj81kq277x'])->assertJsonValidationErrors('reference');
        $this->transfer($invoice, ['reference' => 'DJ81KQ2999'])->assertCreated();

        // Another member reusing someone else's screenshot number.
        $otherUser = User::factory()->create(['organization_id' => $this->organization->id, 'account_type' => AccountType::Member]);
        Member::factory()->at($this->bole)->create(['user_id' => $otherUser->id]);
        Sanctum::actingAs($otherUser);
        $other = $this->postJson('/api/v1/me/member/orders', ['plan' => $this->monthly->ulid])->json('data.id');
        $this->post("/api/v1/me/member/invoices/{$other}/transfers", ['method' => 'telebirr', 'reference' => 'DJ81KQ2999', 'amount_cents' => 300000, 'proof' => UploadedFile::fake()->image('r.png')], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('reference');
    }

    public function test_desk_can_correct_the_amount_and_only_branch_desk_staff_review(): void
    {
        $invoice = $this->order();
        $this->transfer($invoice, ['amount_cents' => 400000])->assertJsonValidationErrors('amount_cents');
        $this->transfer($invoice, ['proof' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertJsonValidationErrors('proof');
        $this->transfer($invoice)->assertCreated();
        $id = Payment::first()->ulid;

        Sanctum::actingAs(User::factory()->staffAt($this->bole, BranchRole::Trainer)->create());
        $this->getJson('/api/v1/payments/transfers')->assertForbidden();
        Sanctum::actingAs(User::factory()->staffAt(Branch::factory()->create(['organization_id' => $this->organization->id]), BranchRole::Receptionist)->create());
        $this->postJson("/api/v1/payments/{$id}/approve")->assertForbidden();

        Sanctum::actingAs($this->reception);
        $this->postJson("/api/v1/payments/{$id}/approve", ['amount_cents' => 200000])
            ->assertJsonPath('data.amount_cents', 200000)
            ->assertJsonPath('data.invoice.status', 'partially_paid')
            ->assertJsonPath('data.invoice.balance_cents', 100000);
        $this->assertSame('pending_payment', Membership::first()->status);
    }

    public function test_desk_records_telebirr_and_bank_payments_with_a_reference(): void
    {
        Sanctum::actingAs($this->reception);
        $sale = fn (array $payment) => $this->postJson("/api/v1/branches/{$this->bole->ulid}/sales", ['member' => $this->member->ulid, 'plan' => $this->monthly->ulid, 'payment' => $payment]);

        $sale(['method' => 'telebirr', 'amount_cents' => 250000])->assertJsonValidationErrors('payment.reference');
        $sale(['method' => 'bank_transfer', 'amount_cents' => 250000, 'reference' => 'FT26300ABC12'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.payments.0.method', 'bank_transfer');
    }
}
