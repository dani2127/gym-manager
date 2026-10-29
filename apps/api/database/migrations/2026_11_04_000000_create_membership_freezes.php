<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pauses on a membership: the end date moves out by the days frozen. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_freezes', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id')->constrained()->cascadeOnDelete();
            // The membership's branch, so each desk sees its own requests.
            $table->foreignId('branch_id')->constrained();
            $table->date('starts_on');
            // Last frozen day; the member is back the day after.
            $table->date('ends_on');
            $table->unsignedSmallInteger('days');
            $table->string('reason', 255)->nullable();
            // requested → approved → active → completed; or rejected / cancelled.
            $table->enum('status', ['requested', 'approved', 'rejected', 'active', 'completed', 'cancelled']);
            $table->boolean('over_limit')->default(false);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['membership_id', 'status']);
            $table->index(['organization_id', 'branch_id', 'status']);
            $table->index(['status', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_freezes');
    }
};
