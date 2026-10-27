<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            // NULL only for future platform administrators.
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
            $table->enum('account_type', ['owner', 'staff', 'member'])->default('member');
            $table->string('name');
            // E.164, e.g. +251911234567. Unique per organization, not globally.
            $table->string('phone', 16);
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('email')->nullable();
            $table->string('password');
            $table->enum('locale', ['en', 'am'])->default('en');
            $table->string('photo_path')->nullable();
            $table->enum('status', ['active', 'disabled'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['organization_id', 'phone']);
            $table->unique(['organization_id', 'email']);
            $table->index(['organization_id', 'account_type', 'status']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
