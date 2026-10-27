<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog (plans, fees, discounts), memberships and billing (invoices,
 * items, payments). Money is integer santim throughout. The tax rate lives in
 * settings (tax.rate per organization or branch) and is snapshotted on each
 * invoice, so no separate tax_rates table is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            // {"en": "...", "am": "..."}
            $table->json('name');
            $table->json('description')->nullable();
            $table->enum('type', ['time', 'visits', 'time_visits']);
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->unsignedSmallInteger('visit_count')->nullable();
            $table->unsignedBigInteger('price_cents');
            $table->enum('access_scope', ['home_branch', 'all_branches', 'selected'])->default('home_branch');
            $table->boolean('freeze_allowed')->default(false);
            $table->unsignedSmallInteger('max_freeze_days')->nullable();
            $table->unsignedTinyInteger('max_freezes')->nullable();
            // Null means unlimited classes (M8).
            $table->unsignedSmallInteger('class_credits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active', 'sort']);
        });

        Schema::create('plan_branch', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->primary(['plan_id', 'branch_id']);
        });

        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->json('name');
            $table->enum('kind', ['registration', 'card_replacement', 'other']);
            $table->unsignedBigInteger('amount_cents');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'kind', 'is_active']);
        });

        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->json('name');
            $table->enum('kind', ['student', 'couple', 'family', 'corporate', 'promo']);
            $table->enum('value_type', ['percent', 'fixed']);
            // Whole percent (1–100) or santim.
            $table->unsignedBigInteger('value');
            $table->unsignedSmallInteger('min_group_size')->nullable();
            $table->boolean('requires_proof')->default(false);
            $table->string('promo_code', 40)->nullable();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'promo_code']);
            $table->index(['organization_id', 'kind', 'is_active']);
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('next_number')->default(1);
            $table->primary(['branch_id', 'year']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->restrictOnDelete();
            // BOLE-2026-000123
            $table->string('number', 40);
            $table->enum('status', ['issued', 'partially_paid', 'paid', 'void']);
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('discount_cents');
            $table->unsignedBigInteger('tax_cents');
            $table->unsignedBigInteger('total_cents');
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->string('tax_name', 10)->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('tax_inclusive')->default(true);
            $table->char('currency', 3)->default('ETB');
            $table->timestamp('issued_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->index(['organization_id', 'branch_id', 'status', 'issued_at']);
            $table->index(['member_id', 'issued_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('itemable');
            // Snapshot in both languages: {"en": "...", "am": "..."}
            $table->json('description');
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedBigInteger('unit_price_cents');
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->foreignId('discount_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->json('plan_snapshot');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('visits_total')->nullable();
            $table->unsignedSmallInteger('visits_remaining')->nullable();
            $table->enum('status', ['pending_payment', 'active', 'frozen', 'expired', 'cancelled']);
            $table->foreignId('invoice_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'status', 'ends_on']);
            $table->index(['organization_id', 'branch_id', 'status', 'ends_on']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('method', ['cash', 'pos_card', 'bank_transfer', 'telebirr', 'chapa']);
            $table->unsignedBigInteger('amount_cents');
            $table->enum('status', ['pending', 'awaiting_verification', 'completed', 'rejected', 'failed', 'refunded']);
            // POS slip, FT number, Telebirr transaction or Chapa tx_ref.
            $table->string('reference', 64)->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            // Blocks a reused transfer or slip number.
            $table->unique(['organization_id', 'method', 'reference']);
            $table->index(['organization_id', 'branch_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('discount_rules');
        Schema::dropIfExists('fees');
        Schema::dropIfExists('plan_branch');
        Schema::dropIfExists('plans');
    }
};
