<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A 6-digit code beside the confirm link. Gmail switches off every link in
 * an email it files under Spam, so a pilgrim whose confirmation landed there
 * could not confirm at all. A code can be read anywhere and typed into GIYA.
 * Stored hashed, like a password.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devotees', function (Blueprint $table) {
            $table->string('verify_code', 100)->nullable();
            $table->timestamp('verify_code_expires_at')->nullable();
            $table->unsignedTinyInteger('verify_code_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('devotees', function (Blueprint $table) {
            $table->dropColumn(['verify_code', 'verify_code_expires_at', 'verify_code_attempts']);
        });
    }
};
