<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Messages from the team to every pilgrim: a banner on the site and a bell notification. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->text('message');
            $table->foreignId('created_by')->nullable()->constrained('devotees')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('show_until')->nullable();
            $table->unsignedInteger('sent_to')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'show_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
