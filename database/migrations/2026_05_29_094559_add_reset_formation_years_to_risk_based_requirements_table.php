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
        Schema::table('risk_based_requirements', function (Blueprint $table) {
            $table->unsignedInteger('reset_formation_years')
                ->nullable()
                ->after('validity_months');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risk_based_requirements', function (Blueprint $table) {
            $table->dropColumn('reset_formation_years');
        });
    }
};
