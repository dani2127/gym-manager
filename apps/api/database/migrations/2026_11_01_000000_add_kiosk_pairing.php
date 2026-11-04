<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiosks pair with a short code shown once to the manager; the device then
 * keeps a long secret token. Re-pairing replaces both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kiosk_devices', function (Blueprint $table) {
            $table->ulid('ulid')->nullable()->unique()->after('id');
            $table->string('token_hash', 64)->nullable()->change();
            $table->string('pairing_code_hash', 64)->nullable()->unique()->after('token_hash');
            $table->timestamp('pairing_expires_at')->nullable()->after('pairing_code_hash');
            $table->timestamp('paired_at')->nullable()->after('pairing_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('kiosk_devices', function (Blueprint $table) {
            $table->dropColumn(['ulid', 'pairing_code_hash', 'pairing_expires_at', 'paired_at']);
        });
    }
};
