<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns the Schedule Manager design needs that the ERD's Schedules entity
 * does not carry. Add these to the diagram and Data Dictionary.
 *
 *   time_frame_label  "1st Mass", "2nd Mass" — distinguishes repeated services
 *   is_whole_day      all-day events such as a fiesta
 *   location          "Main Church", "Adoration Chapel" — where inside the site
 *   status            Published | Draft — mirrors churches.is_active
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->string('time_frame_label', 100)->nullable()->after('event_type');
            $table->boolean('is_whole_day')->default(false)->after('end_time');
            $table->string('location', 150)->nullable()->after('is_whole_day');
            $table->string('status', 20)->default('Published')->after('location');
            $table->timestamp('updated_at')->nullable();

            $table->index(['church_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn(['time_frame_label', 'is_whole_day', 'location', 'status', 'updated_at']);
        });
    }
};
