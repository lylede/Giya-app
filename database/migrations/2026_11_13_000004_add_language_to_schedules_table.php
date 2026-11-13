<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The language a Mass is said in. Empty until an admin sets it: the parish
 * pages the times came from rarely say, and a guessed language shown as fact
 * would send an English speaker to a Cebuano Mass.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('language', 20)->nullable()->after('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
