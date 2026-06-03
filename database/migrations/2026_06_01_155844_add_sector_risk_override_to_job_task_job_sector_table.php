<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_task_job_sector', function (Blueprint $table) {
            $table->boolean('sector_risk_override')
                ->default(false)
                ->after('task_risk_level');
        });
    }

    public function down(): void
    {
        Schema::table('job_task_job_sector', function (Blueprint $table) {
            $table->dropColumn('sector_risk_override');
        });
    }
};
