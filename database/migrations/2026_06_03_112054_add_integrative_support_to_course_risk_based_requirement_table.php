<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('course_risk_based_requirement', function (Blueprint $table) {
            $table->json('integrative_start_risk_levels')
                ->nullable()
                ->after('course_validity_type');
        });

        Schema::table('course_risk_based_requirement', function (Blueprint $table) {
            $table->string('course_validity_type')
                ->default('first_achievement')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_risk_based_requirement', function (Blueprint $table) {
            $table->dropColumn('integrative_start_risk_levels');
        });
    }
};
