<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Group classes: types (gym-wide), rooms and a weekly timetable per branch,
 * dated sessions made from the timetable, and members' bookings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_types', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->json('name');
            $table->json('description')->nullable();
            $table->string('color', 16)->default('track');
            $table->unsignedSmallInteger('default_duration_min')->default(60);
            $table->unsignedSmallInteger('default_capacity')->default(20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedSmallInteger('capacity')->nullable();
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });

        Schema::create('class_schedules', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            // 0 = Sunday … 6 = Saturday, local time.
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_min');
            $table->unsignedSmallInteger('capacity');
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('class_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('class_schedules')->nullOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('capacity');
            $table->unsignedSmallInteger('booked_count')->default(0);
            $table->unsignedSmallInteger('waitlist_count')->default(0);
            $table->enum('status', ['scheduled', 'cancelled'])->default('scheduled');
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamps();
            // One session per timetable slot, so the nightly run can't make doubles.
            $table->unique(['schedule_id', 'starts_at']);
            $table->index(['branch_id', 'starts_at']);
            $table->index(['trainer_id', 'starts_at']);
        });

        Schema::create('class_bookings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['booked', 'waitlisted', 'cancelled', 'attended', 'no_show']);
            $table->unsignedSmallInteger('waitlist_position')->nullable();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('booked_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->boolean('late_cancel')->default(false);
            $table->timestamps();
            $table->unique(['class_session_id', 'member_id']);
            $table->index(['member_id', 'status']);
            $table->index(['membership_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_bookings');
        Schema::dropIfExists('class_sessions');
        Schema::dropIfExists('class_schedules');
        Schema::dropIfExists('rooms');
        Schema::dropIfExists('class_types');
    }
};
