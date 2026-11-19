<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phones (and computers) that said yes to GIYA notifications.
 *
 * One row per browser per devotee: the address the phone's push service gave
 * it (endpoint) and the two keys GIYA encrypts each notification with. A
 * devotee with a phone and a laptop has two rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('devotees')->cascadeOnDelete();
            $table->text('endpoint');
            // Endpoints run to several hundred characters: unique on a hash.
            $table->char('endpoint_hash', 64)->unique();
            $table->string('p256dh', 120);
            $table->string('auth', 40);
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
