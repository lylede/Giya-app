<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a schedule repeats, as data the app can calculate dates from
 * (see App\Support\Recurrence). The old free-text "recurrence" column stays
 * as the human label ("Yearly - 4th Sunday of January"), so existing rows
 * and screens keep working; it is widened for the longer labels.
 *
 * Additive only: safe to run on the live database with `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->json('recurrence_rule')->nullable()->after('recurrence');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->string('recurrence', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('recurrence_rule');
        });
    }
};
