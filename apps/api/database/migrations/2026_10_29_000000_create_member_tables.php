<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-organization counter for member numbers (the number on the bib).
        Schema::table('organizations', function (Blueprint $table) {
            $table->unsignedInteger('next_member_no')->default(1)->after('plan_tier');
        });

        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('home_branch_id')->constrained('branches')->restrictOnDelete();
            // Set when the member gets an app login (M5).
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->unsignedInteger('member_no');
            $table->string('first_name', 60);
            $table->string('last_name', 60);
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('phone', 16)->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('emergency_name', 120)->nullable();
            $table->string('emergency_phone', 16)->nullable();
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
            // Seeds the rotating check-in QR (M4). Stored encrypted.
            $table->text('qr_secret');
            $table->date('joined_at');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organization_id', 'member_no']);
            $table->index(['organization_id', 'phone']);
            $table->index(['organization_id', 'first_name']);
            $table->index(['organization_id', 'last_name']);
            $table->index(['organization_id', 'home_branch_id', 'status']);
        });

        Schema::create('member_groups', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['couple', 'family', 'corporate']);
            $table->string('name', 120);
            $table->foreignId('primary_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->timestamps();

            $table->index(['organization_id', 'type']);
        });

        Schema::create('member_group_members', function (Blueprint $table) {
            $table->foreignId('member_group_id')->constrained()->cascadeOnDelete();
            // A member belongs to at most one group, which keeps discounts unambiguous.
            $table->foreignId('member_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['member_group_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_group_members');
        Schema::dropIfExists('member_groups');
        Schema::dropIfExists('members');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('next_member_no'));
    }
};
