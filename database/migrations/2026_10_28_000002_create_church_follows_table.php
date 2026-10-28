<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Notify me" on a church page.
 *
 * One row per devotee who has pressed the button, either way: notify = true
 * means "tell me about this church", false means "stop telling me" (which
 * matters for a church they saved, because saved churches notify by default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('church_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('devotees')->cascadeOnDelete();
            $table->foreignId('church_id')->constrained('churches')->cascadeOnDelete();
            $table->boolean('notify')->default(true);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['user_id', 'church_id']);
            $table->index(['church_id', 'notify']);
        });

        /* A key that makes a reminder impossible to send twice: the same
           devotee can never get two notifications with the same key, even
           when two requests try at the same moment. Null for ordinary
           notifications, which may repeat. */
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('dedupe_key', 120)->nullable()->after('url');
            $table->unique(['user_id', 'dedupe_key']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'dedupe_key']);
            $table->dropColumn('dedupe_key');
        });

        Schema::dropIfExists('church_follows');
    }
};
