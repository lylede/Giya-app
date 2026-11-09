<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * When the devotee confirmed their email address. Accounts that existed
 * before verification was added count as confirmed, so turning it on never
 * locks anyone out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devotees', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable();
        });

        DB::table('devotees')->update(['email_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('devotees', function (Blueprint $table) {
            $table->dropColumn('email_verified_at');
        });
    }
};
