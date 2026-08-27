<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Destination Manager design has an "Exact Address (Optional)" field that
 * the ERD does not cover - `location` there is the city or municipality only.
 * Add `address` to the Churches entity in your diagram and Data Dictionary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->string('address', 255)->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('churches', function (Blueprint $table) {
            $table->dropColumn('address');
        });
    }
};
