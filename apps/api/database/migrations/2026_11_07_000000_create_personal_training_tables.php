<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Personal training: trainer profiles, packages, what members bought, and the sessions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('bio')->nullable();
            $table->json('specialties')->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('pt_packages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->unsignedSmallInteger('sessions_count');
            $table->unsignedInteger('price_cents');
            $table->unsignedSmallInteger('validity_days');
            // When set, only this trainer sells it; otherwise any trainer.
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('pt_enrollments', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users');
            $table->foreignId('pt_package_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->json('package_snapshot');
            $table->unsignedSmallInteger('sessions_total');
            $table->unsignedSmallInteger('sessions_remaining');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->enum('status', ['pending_payment', 'active', 'completed', 'expired', 'cancelled']);
            $table->foreignId('sold_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['member_id', 'status']);
            $table->index(['trainer_id', 'status']);
        });

        Schema::create('pt_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pt_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->constrained('users');
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->enum('status', ['scheduled', 'completed', 'no_show', 'cancelled'])->default('scheduled');
            // Only the trainer (and the owner) ever see these.
            $table->text('trainer_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['trainer_id', 'starts_at']);
            $table->index(['member_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pt_sessions');
        Schema::dropIfExists('pt_enrollments');
        Schema::dropIfExists('pt_packages');
        Schema::dropIfExists('trainer_profiles');
    }
};
