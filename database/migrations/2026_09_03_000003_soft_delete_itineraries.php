<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERD entity: Itineraries (extended)
 *
 * The free tier is three itineraries PER ACCOUNT - a lifetime allowance, not
 * three at a time. That only works if a deleted itinerary still counts, and
 * that only works if a deleted itinerary still exists.
 *
 * So deletion becomes a soft delete: the row stays, stamped with deleted_at,
 * and disappears from every list the devotee sees. Eloquent's SoftDeletes
 * trait excludes it from ordinary queries automatically, so the only place
 * that has to ask for it back is the allowance count.
 *
 * The alternative - a plain counter column incremented on create - was
 * rejected because it can drift from reality and cannot be audited. Keeping
 * the rows means the count is always derivable from the data itself, which
 * also means an admin can see what a devotee actually planned.
 *
 * Note this is not the same as ending a pilgrimage: a Completed itinerary is
 * not deleted and has always counted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('itineraries', function (Blueprint $table) {
            $table->softDeletes();      // nullable deleted_at

            // The allowance check filters on both columns together.
            $table->index(['user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('itineraries', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'deleted_at']);
            $table->dropSoftDeletes();
        });
    }
};
