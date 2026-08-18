<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The User Manager design shows Active / Inactive / Suspended per account.
 * The ERD's Devotees entity only carries `role`, so add `status`.
 *
 * Add this column to the diagram and Data Dictionary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devotees', function (Blueprint $table) {
            $table->string('status', 20)->default('Active')->after('role');
            $table->timestamp('last_seen_at')->nullable()->after('status');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('devotees', function (Blueprint $table) {
            $table->dropColumn(['status', 'last_seen_at']);
        });
    }
};
