<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Notifications module named in the manuscript. Add this entity to the ERD
 * and Data Dictionary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('devotees')->cascadeOnDelete();
            $table->string('type', 50)->default('general');   // schedule | feedback | itinerary | system
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->string('url', 500)->nullable();           // where tapping it goes
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'is_read']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
