<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Free-licensed photos (Wikimedia Commons) must show who took them and
 * under which licence, with a link back to the photo's page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('church_images', function (Blueprint $table) {
            $table->string('credit', 255)->nullable()->after('caption');
            $table->string('credit_url', 500)->nullable()->after('credit');
        });
    }

    public function down(): void
    {
        Schema::table('church_images', function (Blueprint $table) {
            $table->dropColumn(['credit', 'credit_url']);
        });
    }
};
