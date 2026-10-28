<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A church's history, kept apart from its description.
 *
 * The description is the one- or two-line summary shown on cards and in the
 * home carousel (cut at 120 characters). History can run to paragraphs, so it
 * gets a column of its own rather than stretching the description until the
 * cards break. Giya AI reads it to answer "what is the history of ...".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->text('history')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn('history');
        });
    }
};
