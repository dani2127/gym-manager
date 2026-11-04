<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code', 16);
            $table->string('phone', 16)->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['manager', 'receptionist', 'trainer']);
            $table->timestamps();

            $table->primary(['branch_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            // 0 = Sunday … 6 = Saturday (Carbon dayOfWeek).
            $table->unsignedTinyInteger('weekday');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['branch_id', 'weekday']);
        });

        Schema::create('branch_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            // Both NULL = closed all day; otherwise special hours for that date.
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'date']);
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // NULL = organization-wide value; otherwise a branch override.
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 64);
            $table->json('value');
            $table->timestamps();

            // MySQL treats NULL branch_id as distinct, so org-level uniqueness
            // is also enforced in SettingsRepository.
            $table->unique(['organization_id', 'branch_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('branch_closures');
        Schema::dropIfExists('opening_hours');
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('branches');
    }
};
