<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which guided tours an account has finished (or skipped): "home", "map",
 * "trip", "profile". Kept on the account (devotees table) so the tour shows once, on any
 * phone or computer. Empty or null means none yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devotees', function (Blueprint $table) {
            $table->json('tours_done')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('devotees', function (Blueprint $table) {
            $table->dropColumn('tours_done');
        });
    }
};
