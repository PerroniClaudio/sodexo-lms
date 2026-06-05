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
        Schema::table('job_tasks', function (Blueprint $table) {
            $table->string('global_risk_level')->nullable()->after('description');
            $table->boolean('global_sector_risk_override')->default(false)->after('global_risk_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_tasks', function (Blueprint $table) {
            $table->dropColumn(['global_risk_level', 'global_sector_risk_override']);
        });
    }
};
