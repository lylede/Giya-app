<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the devotee agreed to the Terms and Privacy Policy (RA 10173 consent).
 * Accounts made before the policy existed stay empty: GIYA does not pretend
 * they agreed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devotees', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devotees', function (Blueprint $table) {
            $table->dropColumn('terms_accepted_at');
        });
    }
};
