<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Giya AI reply keeps its mood (the guide's face) and, for a route, the
 * planner link, so both come back when an old chat is opened again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('mood', 20)->nullable();
            $table->string('plan_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['mood', 'plan_url']);
        });
    }
};
