<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name');
            $table->string('slug', 63)->unique();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->string('timezone', 64)->default('Africa/Addis_Ababa');
            $table->enum('default_locale', ['en', 'am'])->default('en');
            $table->char('currency', 3)->default('ETB');
            $table->string('tin_number', 32)->nullable();
            $table->string('plan_tier', 32)->default('standard');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
