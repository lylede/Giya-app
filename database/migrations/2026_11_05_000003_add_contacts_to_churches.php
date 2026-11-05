<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* The parish office's phone, email and Facebook page, shown on the church page. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->string('contact_phone', 150)->nullable()->after('address');
            $table->string('contact_email', 150)->nullable()->after('contact_phone');
            $table->string('facebook_url', 300)->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn(['contact_phone', 'contact_email', 'facebook_url']);
        });
    }
};
