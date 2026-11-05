<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A Mass time GIYA filled in from the usual Cebu parish pattern, not yet
 * confirmed by the parish. Shown as "usual time, please confirm" and
 * replaced as soon as the real time is imported.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('is_estimate')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('is_estimate');
        });
    }
};
