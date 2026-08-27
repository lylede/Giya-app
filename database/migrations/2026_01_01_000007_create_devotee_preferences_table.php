<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NOT in the current ERD - add this entity to the diagram and Data Dictionary.
 * One row per devotee, created on first save.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devotee_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('devotees')->cascadeOnDelete();
            $table->string('font_size', 20)->default('Medium');   // Small | Medium | Large
            $table->string('theme_style', 20)->default('Light');  // Light | Dark
            $table->string('language', 20)->default('English');   // English | Cebuano | Filipino
            $table->boolean('notify_mass_schedule')->default(true);
            $table->boolean('notify_itinerary')->default(true);
            $table->boolean('notify_feast_day')->default(true);
            $table->boolean('notify_saved_destination')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devotee_preferences');
    }
};