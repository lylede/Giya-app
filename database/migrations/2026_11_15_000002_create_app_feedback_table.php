<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Feedback about GIYA itself, not about a church: how the app is working
 * for someone. Kept apart from the church reviews (feedback table), which
 * need a church and go through approval before anyone sees them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('devotees')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');            // 1-5, the overall experience
            $table->string('topic', 30);                       // map, ai, planning, mass, account, other
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['topic', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_feedback');
    }
};
