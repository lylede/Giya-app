<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A record of what each admin did in the admin panel, and when. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('devotees')->nullOnDelete();
            // Kept beside the id so the entry still reads if the account is deleted.
            $table->string('admin_name', 100);
            $table->string('action', 80);
            $table->string('subject', 200)->nullable();
            $table->string('details', 300)->nullable();
            $table->string('route', 80)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
