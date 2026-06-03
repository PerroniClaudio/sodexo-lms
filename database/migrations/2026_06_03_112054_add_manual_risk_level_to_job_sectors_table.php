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
        Schema::table('job_sectors', function (Blueprint $table) {
            $table->string('manual_risk_level')
                ->nullable()
                ->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_sectors', function (Blueprint $table) {
            $table->dropColumn('manual_risk_level');
        });
    }
};
