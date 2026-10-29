<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A schedule may now belong to a town instead of a GIYA destination: the
 * province's festivals (tourism.cebu.gov.ph/festivals) happen all over Cebu,
 * most of them at parishes that are not on GIYA's map yet. Such a row keeps
 * church_id empty and names the town in `location`. Deleting a destination
 * still deletes its schedules.
 *
 * Safe on live data: existing rows keep their church.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('church_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Rows without a destination cannot survive the column going back to
        // NOT NULL, so they are removed first.
        \Illuminate\Support\Facades\DB::table('schedules')->whereNull('church_id')->delete();

        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('church_id')->nullable(false)->change();
        });
    }
};
