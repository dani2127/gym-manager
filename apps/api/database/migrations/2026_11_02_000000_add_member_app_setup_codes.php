<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Members join the app with a setup code the desk gives them in person, so
 * one-time codes gain a purpose (app_setup) and a channel (desk).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->enum('purpose', ['phone_verify', 'password_reset', 'app_setup'])->change();
            $table->enum('channel', ['telegram', 'desk'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('otp_codes', function (Blueprint $table) {
            $table->enum('purpose', ['phone_verify', 'password_reset'])->change();
            $table->enum('channel', ['telegram'])->change();
        });
    }
};
