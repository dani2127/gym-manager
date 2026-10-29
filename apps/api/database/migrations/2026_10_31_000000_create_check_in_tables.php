<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Check-ins, lockers and kiosk devices. Lockers and kiosks get their screens
 * in M4b; their tables exist now so check-ins can reference them.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Printed member cards carry a fixed QR code. Raising the version
        // makes every earlier card stop working (lost or lent cards).
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedSmallInteger('card_version')->default(1)->after('qr_secret');
        });

        Schema::create('lockers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 10);
            $table->enum('zone', ['male', 'female', 'unisex'])->default('unisex');
            $table->enum('status', ['available', 'out_of_order'])->default('available');
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
        });

        Schema::create('kiosk_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('method', ['qr', 'card', 'search', 'kiosk', 'manual']);
            $table->enum('result', ['allowed', 'denied', 'overridden']);
            $table->string('denial_reason', 40)->nullable();
            $table->string('override_reason', 255)->nullable();
            $table->boolean('visit_used')->default(false);
            $table->foreignId('locker_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['branch_id', 'checked_in_at']);
            $table->index(['member_id', 'checked_in_at']);
            $table->index(['branch_id', 'result', 'checked_out_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
        Schema::dropIfExists('kiosk_devices');
        Schema::dropIfExists('lockers');
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn('card_version'));
    }
};
